<?php
/**
 * google_merchant_envios.php — Sincroniza las tarifas de envío de la web con
 * Google Merchant Center (cuenta 7605527) vía Merchant API v1.
 *
 * Qué hace:
 *  1. Lee las tarifas VIVAS de la web (lo que cobra el checkout):
 *     - familia Correos / TIPSA: tabla `configuration` (MODULE_*_COST, formato kg:precio sin IVA + handling)
 *     - SEUR punto / SEUR 13:30: constantes de clase de sus módulos (coste + margen + redondeo 0,05 con IVA)
 *  2. Calcula por zona (Península / Baleares / Canarias / Ceuta-Melilla / …) la tarifa
 *     MÍNIMA por tramo de peso — el precio final CON IVA, que es lo que pide Google.
 *  3. Descarga la configuración actual de envíos de Merchant Center (GET shippingSettings),
 *     la muestra junto a la calculada y permite empujarla (shippingSettings:insert) con backup previo.
 *
 * Notas:
 *  - insert REEMPLAZA el recurso completo → siempre partimos del GET fresco (etag incluido) y
 *    solo se toca el mainTable del rateGroup "catch-all" de los servicios mapeados.
 *  - Antes de cada push se guarda un backup JSON en el dir de backups (restaurable desde la página).
 *  - SEUR 13:30 (seurnacional) queda EXCLUIDO de los mínimos: solo se ofrece L-V 06-15h con stock real,
 *    y Google necesita un precio válido a cualquier hora.
 *  - Mapeo servicio GMC → perfil de zona persistido en gm_mapping.json (junto a la config, fuera del docroot).
 *
 * Requiere /home/francobordo/google_merchant_config.php + JSON de service account (ver checklist en la página).
 */
require 'includes/application_top.php';
require_once DIR_FS_CATALOG . 'includes/classes/google_merchant.php';

$gm = new google_merchant();

/* ====================================================================== *
 *  Tarifas web                                                            *
 * ====================================================================== */

function gmw_conf_all() {
    static $c = null;
    if ($c === null) {
        $c = array();
        $q = tep_db_query("SELECT configuration_key k, configuration_value v FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE\\_%'");
        while ($r = tep_db_fetch_array($q)) $c[$r['k']] = $r['v'];
    }
    return $c;
}
function gmw_conf($k, $d = '') { $a = gmw_conf_all(); return isset($a[$k]) ? $a[$k] : $d; }

function gmw_geozones() {
    static $z = null;
    if ($z === null) {
        $z = array();
        $q = tep_db_query("SELECT geo_zone_id, geo_zone_name FROM " . TABLE_GEO_ZONES);
        while ($r = tep_db_fetch_array($q)) $z[(int)$r['geo_zone_id']] = $r['geo_zone_name'];
    }
    return $z;
}
function gmw_geozone_name($id) { $z = gmw_geozones(); $id = (int)$id; return isset($z[$id]) ? $z[$id] : ('zona #' . $id); }

/** "2:4.3,5:5.00,10:5.90" → array(array(2.0,4.3), ...) ordenado por peso. */
function gmw_parse_tabla($s) {
    $t = array();
    $p = preg_split('/[:,]/', (string)$s);
    for ($i = 0, $n = count($p); $i + 1 < $n; $i += 2) {
        if (!is_numeric(trim($p[$i])) || !is_numeric(trim($p[$i + 1]))) continue;
        $t[] = array((float)trim($p[$i]), (float)trim($p[$i + 1]));
    }
    usort($t, function ($a, $b) { return $a[0] <=> $b[0]; });
    return $t;
}

function gmw_nickel($x) { return round(round($x / 0.05) * 0.05, 2); }

/** Perfil de zona a partir del nombre de la geo zone (heurística, solo para sugerir). */
function gmw_profile_from_zone($name) {
    $n = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    if (strpos($n, 'balear') !== false) return 'BAL';
    if (strpos($n, 'canar') !== false) return 'CAN';
    if (strpos($n, 'ceuta') !== false || strpos($n, 'melilla') !== false) return 'CEU';
    if (strpos($n, 'andorra') !== false) return 'AND';
    if (strpos($n, 'penins') !== false || strpos($n, 'espa') !== false || strpos($n, 'nacional') !== false) return 'PEN';
    if (strpos($n, 'portugal') !== false || strpos($n, 'euro') !== false) return 'EU';
    return '';
}

$GMW_PROFILES = array(
    'PEN' => 'España península',
    'BAL' => 'Baleares',
    'CAN' => 'Canarias',
    'CEU' => 'Ceuta y Melilla',
    'AND' => 'Andorra',
    'EU'  => 'Internacional zona euro',
    'INT' => 'Internacional resto',
);

/**
 * Catálogo de fuentes de tarifa. Cada fuente:
 *  key, label, zona (texto informativo), profile (clave de $GMW_PROFILES o ''),
 *  activo (bool), gated (bool: condicionada, no apta para GMC),
 *  sem ('<=' o '<'), base (array de [kg, € sin IVA con handling]), kg_max,
 *  extra_base (€/kg sin IVA por encima de kg_max; 0 = no cubre más peso),
 *  margen (multiplicador), ivaf (1.21 o 1), nickel (bool), nota
 */
function gmw_sources() {
    static $S = null;
    if ($S !== null) return $S;
    $S = array();

    // --- familia Correos (tabla en BD, semántica <=) ---
    $correos = array(
        'CORREOS'              => array('Correos — ordinario',                'PEN'),
        'CORREOSCERT'          => array('Correos — certificado',              'PEN'),
        'CORREOSPAESP'         => array('Correos Paquetería — Península',     'PEN'),
        'CORREOSPAESPBAL'      => array('Correos Paquetería — Baleares',      'BAL'),
        'CORREOSPAESPCANAR'    => array('Correos Paquetería — Canarias',      'CAN'),
        'CORREOSPAESPCEUTAMEL' => array('Correos Paquetería — Ceuta/Melilla', 'CEU'),
        'CORREOSAND'           => array('Correos — Andorra',                  'AND'),
        'CORREOSINT'           => array('Correos internacional — zona euro',  'EU'),
        'CORREOSINTLATIN'      => array('Correos internacional — LATAM',      'INT'),
    );
    foreach ($correos as $K => $meta) {
        $pre = 'MODULE_SHIPPING_' . $K . '_';
        $tabla = gmw_parse_tabla(gmw_conf($pre . 'COST'));
        if (!count($tabla)) continue;
        $hand = (float)gmw_conf($pre . 'HANDLING', '0');
        $base = array();
        foreach ($tabla as $row) $base[] = array($row[0], $row[1] + $hand);
        $zone = (int)gmw_conf($pre . 'ZONE', '0');
        $S[] = array(
            'key'    => strtolower($K),
            'label'  => $meta[0],
            'zona'   => $zone > 0 ? gmw_geozone_name($zone) : 'todas',
            'profile'=> $meta[1],
            'activo' => gmw_conf($pre . 'STATUS') === 'True',
            'gated'  => false,
            'sem'    => '<=',
            'base'   => $base,
            'kg_max' => $base[count($base) - 1][0],
            'extra_base' => 0.0,
            'margen' => 1.0,
            'ivaf'   => ((int)gmw_conf($pre . 'TAX_CLASS', '0') > 0) ? 1.21 : 1.0,
            'nickel' => false,
            'nota'   => ($hand > 0 ? 'handling ' . number_format($hand, 2, ',', '.') . ' € incluido · ' : '') . 'BD configuration',
        );
    }

    // --- TIPSA / GLS (multi-zona en BD, semántica <, extra €/kg) ---
    if (gmw_conf('MODULE_TIPSA_STATUS') !== '') {
        $ivaf = ((int)gmw_conf('MODULE_TIPSA_TAX_CLASS', '0') > 0) ? 1.21 : 1.0;
        for ($i = 1; $i <= 9; $i++) {
            $gz = (int)gmw_conf('MODULE_TIPSA_COUNTRIES_' . $i, '0');
            if ($gz <= 0) continue;
            $tabla = gmw_parse_tabla(gmw_conf('MODULE_TIPSA_COST_' . $i));
            if (!count($tabla)) continue;
            $hand = (float)gmw_conf('MODULE_TIPSA_HANDLING_' . $i, '0');
            $base = array();
            foreach ($tabla as $row) $base[] = array($row[0], $row[1] + $hand);
            $kgmax = (float)gmw_conf('MODULE_TIPSA_KG_MAX_' . $i, '0');
            $extra = (float)gmw_conf('MODULE_TIPSA_KG_ADICIONAL_' . $i, '0');
            $zname = gmw_geozone_name($gz);
            $S[] = array(
                'key'    => 'tipsa:' . $i,
                'label'  => 'TIPSA/GLS — zona ' . $i,
                'zona'   => $zname,
                'profile'=> gmw_profile_from_zone($zname),
                'activo' => gmw_conf('MODULE_TIPSA_STATUS') === 'True',
                'gated'  => false,
                'sem'    => '<',
                'base'   => $base,
                'kg_max' => ($kgmax > 0 ? $kgmax : $base[count($base) - 1][0]),
                'extra_base' => $extra,
                'margen' => 1.0,
                'ivaf'   => $ivaf,
                'nickel' => false,
                'nota'   => ($hand > 0 ? 'handling ' . number_format($hand, 2, ',', '.') . ' € · ' : '')
                          . ($extra > 0 ? '+' . number_format($extra, 2, ',', '.') . ' €/kg sobre ' . $kgmax . ' kg · ' : '')
                          . 'tramos "menor que" · BD configuration',
            );
        }
    }

    // --- SEUR punto (constantes de clase, coste sin margen, nickel con IVA) ---
    $fSeurPunto = DIR_FS_CATALOG . 'includes/modules/shipping/seurpunto.php';
    if (is_file($fSeurPunto)) {
        include_once $fSeurPunto;
        if (class_exists('seurpunto')) {
            $rc = new ReflectionClass('seurpunto');
            $cc = $rc->getConstants();
            $ivaf = ((int)gmw_conf('MODULE_SHIPPING_SEURPUNTO_TAX_CLASS', '1') > 0) ? 1.21 : 1.0;
            $act  = gmw_conf('MODULE_SHIPPING_SEURPUNTO_STATUS') === 'True';
            foreach (array('PEN' => 'TARIFA_PENINSULA', 'BAL' => 'TARIFA_BALEARES') as $prof => $constName) {
                if (empty($cc[$constName]) || !is_array($cc[$constName])) continue;
                $base = array();
                $t = $cc[$constName]; ksort($t);
                foreach ($t as $kg => $p) $base[] = array((float)$kg, (float)$p);
                $extra = isset($cc['TARIFA_EXTRA_KG'][$prof]) ? (float)$cc['TARIFA_EXTRA_KG'][$prof] : 0.0;
                $S[] = array(
                    'key'    => 'seurpunto:' . $prof,
                    'label'  => 'SEUR punto de recogida — ' . ($prof === 'BAL' ? 'Baleares' : 'Península'),
                    'zona'   => $prof === 'BAL' ? 'CP 07xxx' : 'ES sin 07/35/38/51/52',
                    'profile'=> $prof,
                    'activo' => $act,
                    'gated'  => false,
                    'sem'    => '<=',
                    'base'   => $base,
                    'kg_max' => $base[count($base) - 1][0],
                    'extra_base' => $extra,
                    'margen' => 1.0,
                    'ivaf'   => $ivaf,
                    'nickel' => true,
                    'nota'   => 'tarifa 2SHOP hardcodeada en el módulo · margen 0 · redondeo 0,05 con IVA',
                );
            }
        }
    }

    // --- SEUR 13:30 (ventana horaria L-V 06-15h → NO apto para GMC, solo informativo) ---
    $fSeurNac = DIR_FS_CATALOG . 'includes/modules/shipping/seurnacional.php';
    if (is_file($fSeurNac)) {
        include_once $fSeurNac;
        if (class_exists('seurnacional')) {
            $rc = new ReflectionClass('seurnacional');
            $cc = $rc->getConstants();
            if (!empty($cc['TARIFA_1330_PENINSULA']) && is_array($cc['TARIFA_1330_PENINSULA'])) {
                $base = array();
                $t = $cc['TARIFA_1330_PENINSULA']; ksort($t);
                foreach ($t as $kg => $p) $base[] = array((float)$kg, (float)$p);
                $S[] = array(
                    'key'    => 'seurnacional:PEN',
                    'label'  => 'SEUR 13:30 — Península',
                    'zona'   => 'ES peninsular',
                    'profile'=> 'PEN',
                    'activo' => gmw_conf('MODULE_SEUR_NACIONAL_STATUS') === 'True',
                    'gated'  => true,
                    'sem'    => '<=',
                    'base'   => $base,
                    'kg_max' => $base[count($base) - 1][0],
                    'extra_base' => isset($cc['TARIFA_1330_EXTRA_KG']) ? (float)$cc['TARIFA_1330_EXTRA_KG'] : 0.0,
                    'margen' => isset($cc['MARGEN']) ? (float)$cc['MARGEN'] : 1.2,
                    'ivaf'   => ((int)gmw_conf('MODULE_SEUR_NACIONAL_TAX_CLASS', '1') > 0) ? 1.21 : 1.0,
                    'nickel' => true,
                    'nota'   => 'solo L-V 06:00-15:00 con stock real → EXCLUIDO de los mínimos GMC',
                );
            }
        }
    }

    return $S;
}

/** Precio FINAL (con IVA, como lo ve el cliente y como lo quiere Google) de una fuente a un peso dado. */
function gmw_eval($s, $kg) {
    $base = null;
    $n = count($s['base']);
    if (!$n) return null;
    foreach ($s['base'] as $row) {
        if ($s['sem'] === '<' ? ($kg < $row[0]) : ($kg <= $row[0])) { $base = $row[1]; break; }
    }
    if ($base === null) {
        if ($s['extra_base'] <= 0) return null;          // no cubre este peso
        $base = $s['base'][$n - 1][1];
    }
    if ($s['extra_base'] > 0 && $kg > $s['kg_max']) {
        $base += ceil($kg - $s['kg_max']) * $s['extra_base'];
    }
    $v = $base * $s['margen'] * $s['ivaf'];
    return $s['nickel'] ? gmw_nickel($v) : round($v, 2);
}

/**
 * Tabla [kg_hasta, precio_final] para un conjunto de fuentes (mínimo entre ellas
 * en cada tramo). Si alguna cobra €/kg extra, se extiende hasta 300 kg. La fila
 * final [-1, null] = "y superior" → SIN ENVÍO (igual que las tablas manuales
 * históricas de GMC: mejor no cotizar que infracotizar un peso fuera de tabla).
 */
function gmw_rows_from_members($members) {
    $ends = array();
    $hasExtra = false;
    foreach ($members as $s) {
        foreach ($s['base'] as $row) $ends[] = (float)$row[0];
        if ($s['extra_base'] > 0) $hasExtra = true;
    }
    if ($hasExtra) foreach (array(60.0, 70.0, 80.0, 90.0, 100.0, 125.0, 150.0, 175.0, 200.0, 250.0, 300.0) as $w) $ends[] = $w;
    $ends = array_values(array_unique($ends, SORT_REGULAR));
    sort($ends);

    $rows = array();
    foreach ($ends as $W) {
        $best = null;
        foreach ($members as $s) {
            $p = gmw_eval($s, $W);
            if ($p !== null && ($best === null || $p < $best)) $best = $p;
        }
        if ($best === null) break;   // a partir de aquí nadie cubre el peso
        $rows[] = array($W, $best);
    }
    if (!count($rows)) return null;

    // Colapsar tramos consecutivos con el mismo precio (el tramo mayor absorbe)
    $collapsed = array();
    for ($i = 0, $n = count($rows); $i < $n; $i++) {
        if ($i + 1 < $n && abs($rows[$i][1] - $rows[$i + 1][1]) < 0.001) continue;
        $collapsed[] = $rows[$i];
    }
    $collapsed[] = array(-1, null);   // ∞ => sin envío
    return $collapsed;
}

/** Tabla mínima de un perfil de zona, o null si no tiene fuentes activas. */
function gmw_profile_table($profile) {
    $members = array();
    foreach (gmw_sources() as $s) {
        if ($s['profile'] === $profile && $s['activo'] && !$s['gated']) $members[] = $s;
    }
    if (!count($members)) return null;
    $rows = gmw_rows_from_members($members);
    if ($rows === null) return null;
    $labels = array();
    foreach ($members as $s) $labels[] = $s['label'];
    return array('rows' => $rows, 'members' => $labels);
}

/* ====================================================================== *
 *  Mapeo servicio GMC → perfil (persistido junto a la config)             *
 * ====================================================================== */

function gmw_mapping_file() {
    return dirname(google_merchant::CONFIG_FILE) . '/gm_mapping.json';
}
function gmw_mapping_load() {
    $m = json_decode((string)@file_get_contents(gmw_mapping_file()), true);
    return is_array($m) ? $m : array();
}
function gmw_mapping_save($m) {
    @file_put_contents(gmw_mapping_file(), json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* ====================================================================== *
 *  Construcción del mainTable de Merchant API                             *
 * ====================================================================== */

function gmw_build_maintable($rows) {
    $weights = array();
    $cells   = array();
    foreach ($rows as $r) {
        $weights[] = array(
            'amountMicros' => ($r[0] < 0) ? '-1' : (string)(int)round($r[0] * 1000000),
            'unit'         => 'KILOGRAM',   // enum WeightUnit de Merchant API v1 ("kg" era Content API)
        );
        $cells[] = array('cells' => array(
            ($r[1] === null)
                ? array('noShipping' => true)
                : array('flatRate' => array(
                    'amountMicros' => (string)google_merchant::eur2micros($r[1]),
                    'currencyCode' => 'EUR',
                  ))
        ));
    }
    return array('rowHeaders' => array('weights' => $weights), 'rows' => $cells);
}

/** ¿El rateGroup usa carrier rates o subtables? (entonces no lo tocamos) */
function gmw_rategroup_is_complex($rg) {
    if (!empty($rg['carrierRates']) || !empty($rg['subtables'])) return true;
    if (!empty($rg['mainTable'])) {
        $mt = $rg['mainTable'];
        if (!empty($mt['columnHeaders'])) return true;   // tabla 2D (peso × precio pedido)
        if (!empty($mt['rows'])) {
            foreach ($mt['rows'] as $row) {
                if (empty($row['cells'])) continue;
                foreach ($row['cells'] as $cell) {
                    if (isset($cell['carrierRate']) || isset($cell['subtable'])) return true;
                }
            }
        }
    }
    return false;
}

/* ====================================================================== *
 *  Registro de perfiles empujables: mínimos por zona + por transportista  *
 * ====================================================================== */

$profiles = array();   // clave => array('label', 'rows', 'members')
foreach ($GMW_PROFILES as $pk => $plabel) {
    $pt = gmw_profile_table($pk);
    if ($pt !== null) $profiles[$pk] = array('label' => 'Mínimo — ' . $plabel, 'rows' => $pt['rows'], 'members' => $pt['members']);
}
foreach (gmw_sources() as $s) {
    if (!$s['activo'] || $s['gated']) continue;
    $rows = gmw_rows_from_members(array($s));
    if ($rows === null) continue;
    $profiles['src:' . $s['key']] = array('label' => $s['label'], 'rows' => $rows, 'members' => array($s['label']));
}

/* ====================================================================== *
 *  Acciones                                                               *
 * ====================================================================== */

$action   = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$mensajes = array();   // array de array('ok'|'warn'|'err', texto)

if ($action === 'savemap' || $action === 'push') {
    $posted = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : array();
    $map = array();
    foreach ($posted as $svc => $prof) {
        $svc  = (string)$svc;
        $prof = (string)$prof;
        if ($prof !== '' && isset($profiles[$prof])) $map[$svc] = $prof;
    }
    gmw_mapping_save($map);
    if ($action === 'savemap') $mensajes[] = array('ok', 'Mapeo guardado.');
}

if ($action === 'push' && $gm->configured()) {
    if (empty($_POST['confirmar'])) {
        $mensajes[] = array('err', 'Push cancelado: marca la casilla de confirmación.');
    } else {
        $g = $gm->getShippingSettings();
        if ($g['code'] !== 200 || !is_array($g['data'])) {
            $mensajes[] = array('err', 'No se pudo leer la configuración actual de GMC: ' . ($g['error'] !== '' ? $g['error'] : 'HTTP ' . $g['code']));
        } else {
            $settings = $g['data'];
            $map      = gmw_mapping_load();

            // Backup previo (restaurable desde esta página)
            $bdir = $gm->backupDir();
            if (!is_dir($bdir)) @mkdir($bdir, 0700, true);
            $bfile = $bdir . '/shippingsettings-' . date('Ymd-His') . '.json';
            @file_put_contents($bfile, $g['raw']);

            $cambios = array();
            if (!empty($settings['services']) && is_array($settings['services'])) {
                foreach ($settings['services'] as $i => $svc) {
                    $sname = isset($svc['serviceName']) ? $svc['serviceName'] : '';
                    if ($sname === '' || empty($map[$sname])) continue;
                    $perfil = $map[$sname];
                    $pt = isset($profiles[$perfil]) ? $profiles[$perfil] : null;
                    if ($pt === null) {
                        $mensajes[] = array('warn', 'Servicio "' . htmlspecialchars($sname) . '": el perfil ' . htmlspecialchars($perfil) . ' no tiene fuentes activas, se deja como está.');
                        continue;
                    }
                    if (empty($svc['rateGroups']) || !is_array($svc['rateGroups'])) {
                        $settings['services'][$i]['rateGroups'] = array(array());
                    }
                    $ti = count($settings['services'][$i]['rateGroups']) - 1;   // catch-all = último rateGroup
                    if (!empty($settings['services'][$i]['rateGroups'][$ti]['applicableShippingLabels'])) {
                        $mensajes[] = array('warn', 'Servicio "' . htmlspecialchars($sname) . '": su rateGroup va por etiquetas de producto (p.ej. freeshipping), no tiene catch-all — no se toca.');
                        continue;
                    }
                    if (gmw_rategroup_is_complex($settings['services'][$i]['rateGroups'][$ti])) {
                        $mensajes[] = array('warn', 'Servicio "' . htmlspecialchars($sname) . '": su rateGroup usa carrier rates / subtablas / tabla 2D — no se toca por seguridad.');
                        continue;
                    }
                    $settings['services'][$i]['rateGroups'][$ti]['mainTable'] = gmw_build_maintable($pt['rows']);
                    unset($settings['services'][$i]['rateGroups'][$ti]['singleValue']);
                    $cambios[] = $sname . ' ← ' . $pt['label'] . ' (' . count($pt['rows']) . ' tramos)';
                }
            }

            if (!count($cambios)) {
                $mensajes[] = array('warn', 'Ningún servicio mapeado a un perfil: no hay nada que empujar. Asigna perfiles en la tabla de servicios y guarda.');
            } else {
                $r = $gm->insertShippingSettings($settings);
                if ($r['code'] === 200) {
                    $mensajes[] = array('ok', 'Tarifas actualizadas en Merchant Center: ' . implode(' · ', $cambios)
                        . ' — backup previo en ' . basename($bfile));
                } else {
                    $mensajes[] = array('err', 'El insert falló (' . ($r['error'] !== '' ? $r['error'] : 'HTTP ' . $r['code']) . '). GMC se queda como estaba. Respuesta: '
                        . htmlspecialchars(substr($r['raw'], 0, 600)));
                }
            }
        }
    }
}

if ($action === 'restore' && $gm->configured()) {
    $bfile = isset($_POST['bfile']) ? basename((string)$_POST['bfile']) : '';
    if (!preg_match('/^shippingsettings-[0-9-]+\.json$/', $bfile)) {
        $mensajes[] = array('err', 'Backup no válido.');
    } else {
        $path = $gm->backupDir() . '/' . $bfile;
        $old  = json_decode((string)@file_get_contents($path), true);
        if (!is_array($old)) {
            $mensajes[] = array('err', 'No se pudo leer el backup ' . htmlspecialchars($bfile));
        } else {
            $g = $gm->getShippingSettings();   // etag fresco obligatorio
            if ($g['code'] !== 200 || !is_array($g['data'])) {
                $mensajes[] = array('err', 'No se pudo obtener etag fresco: ' . $g['error']);
            } else {
                $old['etag'] = isset($g['data']['etag']) ? $g['data']['etag'] : '';
                $r = $gm->insertShippingSettings($old);
                $mensajes[] = ($r['code'] === 200)
                    ? array('ok', 'Restaurado el backup ' . htmlspecialchars($bfile) . ' en Merchant Center.')
                    : array('err', 'Restauración fallida: ' . ($r['error'] !== '' ? $r['error'] : 'HTTP ' . $r['code']));
            }
        }
    }
}

$testResult = null;
if ($action === 'test' && $gm->configured()) {
    $testResult = $gm->getAccount();
}

/* ====================================================================== *
 *  Datos para el render                                                   *
 * ====================================================================== */

$sources = gmw_sources();

$gmcGet      = null;
$gmcServices = array();
if ($gm->configured()) {
    $gmcGet = $gm->getShippingSettings();
    if ($gmcGet['code'] === 200 && !empty($gmcGet['data']['services'])) {
        $gmcServices = $gmcGet['data']['services'];
    }
}
$mapping = gmw_mapping_load();

$backups = array();
if ($gm->configured() && is_dir($gm->backupDir())) {
    foreach ((array)@scandir($gm->backupDir(), SCANDIR_SORT_DESCENDING) as $f) {
        if (preg_match('/^shippingsettings-[0-9-]+\.json$/', (string)$f)) $backups[] = $f;
        if (count($backups) >= 10) break;
    }
}

function gmw_eur($x) { return number_format((float)$x, 2, ',', '.') . ' €'; }
function gmw_kg($x)  { $s = rtrim(rtrim(number_format((float)$x, 3, ',', '.'), '0'), ','); return $s . ' kg'; }

/** Render de un mainTable del API (tolerante: 1D peso o 2D peso×precio). */
function gmw_render_api_table($mt) {
    $w = isset($mt['rowHeaders']['weights']) ? $mt['rowHeaders']['weights'] : array();
    $cols = isset($mt['columnHeaders']['prices']) ? $mt['columnHeaders']['prices'] : null;
    $html = '<table class="gmw-t"><tr><th>hasta</th>';
    if ($cols) {
        foreach ($cols as $c) {
            $v = google_merchant::micros2eur(isset($c['amountMicros']) ? $c['amountMicros'] : -1);
            $html .= '<th>pedido ≤ ' . ($v === null ? '∞' : gmw_eur($v)) . '</th>';
        }
    } else {
        $html .= '<th>coste</th>';
    }
    $html .= '</tr>';
    $rows = isset($mt['rows']) ? $mt['rows'] : array();
    foreach ($rows as $ri => $row) {
        $wm = isset($w[$ri]['amountMicros']) ? $w[$ri]['amountMicros'] : null;
        $wv = ($wm === null) ? null : google_merchant::micros2eur($wm);   // micros de kg
        $html .= '<tr><td>' . ($wv === null ? '∞ (y superior)' : gmw_kg($wv)) . '</td>';
        if (!empty($row['cells'])) {
            foreach ($row['cells'] as $cell) {
                if (isset($cell['flatRate']['amountMicros'])) {
                    $html .= '<td>' . gmw_eur(google_merchant::micros2eur($cell['flatRate']['amountMicros'])) . '</td>';
                } elseif (isset($cell['noShipping'])) {
                    $html .= '<td>sin envío</td>';
                } elseif (isset($cell['pricePercentage'])) {
                    $html .= '<td>' . htmlspecialchars($cell['pricePercentage']) . ' %</td>';
                } else {
                    $html .= '<td>—</td>';
                }
            }
        }
        $html .= '</tr>';
    }
    return $html . '</table>';
}
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.gmw-wrap{max-width:1280px;margin:0 auto;padding:12px;font-size:13px}
.gmw-wrap h1{font-size:20px;margin:8px 0 14px}
.gmw-wrap h2{font-size:16px;margin:22px 0 8px;border-bottom:2px solid #3598DB;padding-bottom:4px}
.gmw-cards{display:flex;flex-wrap:wrap;gap:12px}
.gmw-card{border:1px solid #d5dbe1;border-radius:6px;padding:10px 12px;background:#fff;min-width:230px}
.gmw-card h3{font-size:13px;margin:0 0 6px}
.gmw-muted{color:#777;font-size:11px}
.gmw-t{border-collapse:collapse;margin-top:6px}
.gmw-t th,.gmw-t td{border:1px solid #e1e6ea;padding:2px 8px;text-align:right;font-size:12px}
.gmw-t th{background:#f4f7f9;font-weight:600}
.gmw-badge{display:inline-block;border-radius:3px;padding:1px 6px;font-size:11px;color:#fff}
.gmw-on{background:#27ae60}.gmw-off{background:#c0392b}.gmw-gated{background:#e67e22}
.gmw-msg{padding:8px 12px;border-radius:4px;margin:6px 0}
.gmw-msg.ok{background:#eafaf1;border:1px solid #27ae60}
.gmw-msg.warn{background:#fef9e7;border:1px solid #f1c40f}
.gmw-msg.err{background:#fdedec;border:1px solid #c0392b}
.gmw-setup{background:#f8f9fa;border:1px solid #d5dbe1;border-radius:6px;padding:14px 18px}
.gmw-setup code{background:#eef1f4;padding:1px 4px;border-radius:3px}
.gmw-btn{background:#3598DB;color:#fff;border:0;border-radius:4px;padding:7px 16px;cursor:pointer;font-size:13px}
.gmw-btn.danger{background:#c0392b}
.gmw-svc{border:1px solid #d5dbe1;border-radius:6px;padding:10px 14px;margin:10px 0;background:#fff}
.gmw-flex{display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start}
</style>
<div class="gmw-wrap">
<h1>Google Merchant Center — Tarifas de envío (cuenta <?php echo htmlspecialchars($gm->accountId() !== '' ? $gm->accountId() : '7605527'); ?>)</h1>

<?php foreach ($mensajes as $m) echo '<div class="gmw-msg ' . $m[0] . '">' . $m[1] . '</div>'; ?>

<?php if (!$gm->configured()) { ?>
  <div class="gmw-setup">
    <h2 style="margin-top:0">Configuración pendiente</h2>
    <p><b><?php echo htmlspecialchars($gm->error()); ?></b></p>
    <p>Pasos (una sola vez):</p>
    <ol>
      <li>En <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> (misma cuenta Google que administra Merchant Center): crear proyecto <i>francobordo-merchant</i> → <b>APIs y servicios → Biblioteca → habilitar "Merchant API"</b>.</li>
      <li><b>IAM y administración → Cuentas de servicio → Crear</b> (ej. <i>gmc-sync</i>) → en la cuenta creada, pestaña <b>Claves → Agregar clave → JSON</b>. Se descarga un fichero .json.</li>
      <li>Subir ese fichero al servidor como <code>/home/francobordo/google_merchant_sa.json</code> (chmod 600).</li>
      <li>En <a href="https://merchants.google.com/" target="_blank">Merchant Center</a> → ⚙️ <b>Configuración → Personas y acceso → Añadir persona</b> → pegar el email de la cuenta de servicio (<i>gmc-sync@…iam.gserviceaccount.com</i>) → rol <b>Administrador</b> (el insert de tarifas lo exige).</li>
      <li>Crear <code>/home/francobordo/google_merchant_config.php</code>:
        <pre>&lt;?php
return array(
  'account_id'           => '7605527',
  'service_account_json' => '/home/francobordo/google_merchant_sa.json',
  'token_cache'          => '/home/francobordo/.gm_token_cache.json',
  'backup_dir'           => '/home/francobordo/gm_backups',
);</pre></li>
      <li>Recargar esta página y pulsar <b>Probar conexión</b>.</li>
    </ol>
  </div>
<?php } else { ?>
  <form method="post" style="display:inline"><input type="hidden" name="action" value="test">
    <button class="gmw-btn" type="submit">Probar conexión</button>
    <span class="gmw-muted">service account: <?php echo htmlspecialchars($gm->saEmail()); ?></span>
  </form>
  <?php if ($testResult !== null) {
      if ($testResult['code'] === 200) {
          $an = isset($testResult['data']['accountName']) ? $testResult['data']['accountName'] : '(sin nombre)';
          echo '<div class="gmw-msg ok">Conexión OK — cuenta <b>' . htmlspecialchars($an) . '</b> (HTTP 200)</div>';
      } else {
          echo '<div class="gmw-msg err">Fallo de conexión: ' . htmlspecialchars($testResult['error'] !== '' ? $testResult['error'] : 'HTTP ' . $testResult['code'])
             . '<br><span class="gmw-muted">Si es 403: falta añadir el service account en Merchant Center → Personas y acceso (rol Administrador), o la Merchant API no está habilitada en el proyecto.</span></div>';
      }
  } ?>
<?php } ?>

<h2>1 · Tarifas vivas de la web (precio final con IVA que ve el cliente)</h2>
<div class="gmw-cards">
<?php foreach ($sources as $s) { ?>
  <div class="gmw-card">
    <h3><?php echo htmlspecialchars($s['label']); ?>
      <?php if ($s['gated']) echo ' <span class="gmw-badge gmw-gated">condicionado</span>';
            else echo $s['activo'] ? ' <span class="gmw-badge gmw-on">activo</span>' : ' <span class="gmw-badge gmw-off">inactivo</span>'; ?>
    </h3>
    <div class="gmw-muted">zona: <?php echo htmlspecialchars($s['zona']); ?> · perfil: <?php echo $s['profile'] !== '' ? $s['profile'] : '—'; ?><br><?php echo htmlspecialchars($s['nota']); ?></div>
    <table class="gmw-t"><tr><th><?php echo $s['sem'] === '<' ? '&lt;' : 'hasta'; ?></th><th>cliente paga</th><th class="gmw-muted">base s/IVA</th></tr>
    <?php foreach ($s['base'] as $row) {
        $final = gmw_eval($s, $s['sem'] === '<' ? $row[0] - 0.001 : $row[0]);
        echo '<tr><td>' . gmw_kg($row[0]) . '</td><td><b>' . ($final === null ? '—' : gmw_eur($final)) . '</b></td><td class="gmw-muted">' . gmw_eur($row[1]) . '</td></tr>';
    } ?>
    </table>
  </div>
<?php } ?>
</div>

<h2>2 · Tablas calculadas por zona (mínimo entre transportistas activos — lo que se empuja a Google)</h2>
<div class="gmw-cards">
<?php if (!count($profiles)) echo '<p>No hay fuentes activas.</p>';
foreach ($profiles as $pk => $pt) { if (strpos($pk, 'src:') === 0) continue; ?>
  <div class="gmw-card">
    <h3><?php echo htmlspecialchars($pt['label']) . ' <span class="gmw-muted">(' . $pk . ')</span>'; ?></h3>
    <div class="gmw-muted">fuentes: <?php echo htmlspecialchars(implode(' · ', $pt['members'])); ?></div>
    <table class="gmw-t"><tr><th>hasta</th><th>coste</th></tr>
    <?php foreach ($pt['rows'] as $r) {
        echo '<tr><td>' . ($r[0] < 0 ? '∞ (y superior)' : gmw_kg($r[0])) . '</td><td><b>' . ($r[1] === null ? 'sin envío' : gmw_eur($r[1])) . '</b></td></tr>';
    } ?>
    </table>
  </div>
<?php } ?>
<p class="gmw-muted">Los perfiles "por transportista" (un módulo concreto) no se listan aquí — sus tablas son las de la sección 1; están disponibles en el desplegable de cada servicio.</p>
</div>

<?php if ($gm->configured()) { ?>
<h2>3 · Servicios en Merchant Center y sincronización</h2>
<?php if ($gmcGet !== null && $gmcGet['code'] !== 200) { ?>
  <div class="gmw-msg err">No se pudo leer shippingSettings de GMC: <?php echo htmlspecialchars($gmcGet['error'] !== '' ? $gmcGet['error'] : 'HTTP ' . $gmcGet['code']); ?></div>
<?php } elseif (!count($gmcServices)) { ?>
  <div class="gmw-msg warn">La cuenta no tiene servicios de envío configurados (o el GET vino vacío).</div>
<?php } else { ?>
<form method="post">
<?php foreach ($gmcServices as $svc) {
    $sname = isset($svc['serviceName']) ? $svc['serviceName'] : '(sin nombre)';
    $sel   = isset($mapping[$sname]) ? $mapping[$sname] : '';
    $countries = !empty($svc['deliveryCountries']) ? implode(', ', (array)$svc['deliveryCountries']) : '?';
    $dt = isset($svc['deliveryTime']) ? $svc['deliveryTime'] : array();
    ?>
  <div class="gmw-svc">
    <div class="gmw-flex">
      <div>
        <h3 style="margin:0"><?php echo htmlspecialchars($sname); ?>
          <?php echo !empty($svc['active']) ? '<span class="gmw-badge gmw-on">activo</span>' : '<span class="gmw-badge gmw-off">inactivo</span>'; ?></h3>
        <div class="gmw-muted">países: <?php echo htmlspecialchars($countries); ?> · moneda: <?php echo htmlspecialchars(isset($svc['currencyCode']) ? $svc['currencyCode'] : '?'); ?>
          <?php if ($dt) echo ' · tránsito ' . (isset($dt['minTransitDays']) ? $dt['minTransitDays'] : '?') . '-' . (isset($dt['maxTransitDays']) ? $dt['maxTransitDays'] : '?') . ' días'; ?></div>
        <label>Sincronizar con perfil:
          <select name="map[<?php echo htmlspecialchars($sname); ?>]">
            <option value="">— no tocar —</option>
            <optgroup label="Mínimo por zona (entre transportistas activos)">
            <?php foreach ($profiles as $pk => $pt) {
                if (strpos($pk, 'src:') === 0) continue;
                echo '<option value="' . htmlspecialchars($pk) . '"' . ($sel === $pk ? ' selected' : '') . '>' . htmlspecialchars($pt['label']) . '</option>';
            } ?>
            </optgroup>
            <optgroup label="Por transportista (solo ese módulo)">
            <?php foreach ($profiles as $pk => $pt) {
                if (strpos($pk, 'src:') !== 0) continue;
                echo '<option value="' . htmlspecialchars($pk) . '"' . ($sel === $pk ? ' selected' : '') . '>' . htmlspecialchars($pt['label']) . '</option>';
            } ?>
            </optgroup>
          </select></label>
      </div>
      <div>
        <div class="gmw-muted">Tabla actual en GMC:</div>
        <?php
        $printed = false;
        if (!empty($svc['rateGroups'])) {
            foreach ($svc['rateGroups'] as $rg) {
                if (!empty($rg['mainTable'])) { echo gmw_render_api_table($rg['mainTable']); $printed = true; }
                elseif (isset($rg['singleValue']['flatRate']['amountMicros'])) {
                    echo '<p>tarifa plana: <b>' . gmw_eur(google_merchant::micros2eur($rg['singleValue']['flatRate']['amountMicros'])) . '</b></p>'; $printed = true;
                }
            }
        }
        if (!$printed) echo '<p class="gmw-muted">sin tabla (carrier rate u otro esquema)</p>';
        ?>
      </div>
      <?php if ($sel !== '' && isset($profiles[$sel])) { ?>
      <div>
        <div class="gmw-muted">Se empujaría (<?php echo htmlspecialchars($profiles[$sel]['label']); ?>):</div>
        <table class="gmw-t"><tr><th>hasta</th><th>coste</th></tr>
        <?php foreach ($profiles[$sel]['rows'] as $r)
            echo '<tr><td>' . ($r[0] < 0 ? '∞' : gmw_kg($r[0])) . '</td><td><b>' . ($r[1] === null ? 'sin envío' : gmw_eur($r[1])) . '</b></td></tr>'; ?>
        </table>
      </div>
      <?php } ?>
    </div>
  </div>
<?php } ?>
  <p>
    <label><input type="checkbox" name="confirmar" value="1"> Confirmo: reemplazar las tablas de tarifas de los servicios mapeados en Merchant Center (se guarda backup automático)</label><br><br>
    <button class="gmw-btn" type="submit" name="action" value="push">Empujar tarifas a Merchant Center</button>
    <button class="gmw-btn" type="submit" name="action" value="savemap" style="background:#7f8c8d">Guardar mapeo sin empujar</button>
  </p>
</form>
<details><summary class="gmw-muted">JSON crudo de shippingSettings (GET)</summary>
<pre style="font-size:11px;max-height:400px;overflow:auto"><?php echo htmlspecialchars(json_encode(isset($gmcGet['data']) ? $gmcGet['data'] : null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre></details>
<?php } ?>

<?php if (count($backups)) { ?>
<h2>4 · Backups (restaurar en GMC tal cual estaba)</h2>
<form method="post">
<input type="hidden" name="action" value="restore">
<select name="bfile"><?php foreach ($backups as $b) echo '<option>' . htmlspecialchars($b) . '</option>'; ?></select>
<button class="gmw-btn danger" type="submit" onclick="return confirm('¿Restaurar este backup en Merchant Center?')">Restaurar backup</button>
</form>
<?php } ?>
<?php } /* configured */ ?>

<p class="gmw-muted" style="margin-top:24px">
SEUR 13:30 se excluye de los mínimos (ventana horaria). Si <i>freeamount</i> (envío gratis desde
<?php echo htmlspecialchars(gmw_conf('MODULE_SHIPPING_FREEAMOUNT_AMOUNT', gmw_conf('MODULE_SHIPPING_FREEAMOUNT_OVER', '?'))); ?> €)
está activo, Google solo verá la tabla por peso: el cliente pagará igual o menos que la estimación, lo cual cumple política
(estimar de más es válido; de menos, no). Content API clásica muere el 18/08/2026 — esto ya usa Merchant API v1.
</p>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
