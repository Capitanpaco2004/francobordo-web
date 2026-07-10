<?php
/**
 * Módulo de envío "SEUR - Punto de Recogida" (2shop / pickupCentreCode).
 *
 * El cliente elige un punto SEUR cercano (lista + mapa Leaflet en el checkout);
 * el punto se guarda en sesión (seur_pudo_sel) en checkout_shipping y al grabar
 * el pedido (checkout_process) la dirección de entrega pasa a ser la del punto
 * + fila en seur_pudo_orders (pudoId para la API: servicio 2shop 1/48 ó 77/48).
 *
 * FASE PRUEBAS: visible solo desde las IPs de MODULE_SHIPPING_SEURPUNTO_TEST_IP
 * (vaciar ese campo en admin para abrirlo a todo el mundo).
 *
 * Puntos vía API PIC de SEUR (includes/classes/seur.php, endpoint pickups) con
 * caché en fichero cache/seur_pudo_{ISO}_{CP}.json (TTL 6h).
 * Ver memoria francobordo_seur_api.
 */
class seurpunto {
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    const CACHE_TTL = 21600; // 6 h

    function __construct() {
        global $customer_group_id;

        $this->code        = 'seurpunto';
        $this->title       = MODULE_SHIPPING_SEURPUNTO_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_SEURPUNTO_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SHIPPING_SEURPUNTO_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'seur.png';
        $this->tax_class   = MODULE_SHIPPING_SEURPUNTO_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SHIPPING_SEURPUNTO_STATUS') && MODULE_SHIPPING_SEURPUNTO_STATUS == 'True');

        // FASE PRUEBAS: gate por IP de origen (lista separada por comas). Campo
        // vacío = visible para todos (producción).
        if ($this->enabled && defined('MODULE_SHIPPING_SEURPUNTO_TEST_IP') && trim(MODULE_SHIPPING_SEURPUNTO_TEST_IP) !== '') {
            $aIPs = array_map('trim', explode(',', MODULE_SHIPPING_SEURPUNTO_TEST_IP));
            if (!in_array($_SERVER['REMOTE_ADDR'], $aIPs, true)) $this->enabled = false;
        }

        // Solo cliente final (los grupos B2B tienen sus condiciones de envío).
        if ($this->enabled && isset($customer_group_id) && (int) $customer_group_id !== 0) {
            $this->enabled = false;
        }
    }

    /* Tarifa 2SHOP 24 SEUR (coste sin IVA, contrato AUTORIZADO julio 2026). Tramos "hasta N kg".
     * Solo zonas que ofrece el módulo: Península y Baleares (Canarias/Ceuta/Melilla
     * y Portugal quedan excluidos por el gate). Se aplica MARGEN +10% y el fuel SEUR 16,54%. */
    const MARGEN = 1.10;   // margen sobre el coste SEUR (+10%, decisión usuario 2026-07-02)
    const TARIFA_PENINSULA = array(   // kg => €/expedición
        1=>2.90, 2=>3.21, 3=>3.43, 4=>3.65, 5=>3.84, 6=>4.19, 7=>4.48,
        8=>4.78, 9=>5.08, 10=>5.36, 15=>7.24, 20=>8.94,
    );
    const TARIFA_BALEARES = array(
        1=>5.50, 2=>6.45, 3=>7.19, 4=>7.73, 5=>8.35, 6=>9.21, 7=>10.08,
        8=>11.04, 9=>12.06, 10=>13.07, 15=>16.62, 20=>20.76,
    );
    const MAX_KG = 20;   // SEUR 2shop/punto de recogida: tope de peso por envío (>15 kg = tarifa plana)
    const FUEL = 1.1654;  // sobrecoste fuel SEUR (16,54%) repercutido al cliente (2026-06-25)

    /** Coste SEUR (sin IVA) según peso (kg) y zona ('BAL' Baleares / 'PEN' resto). */
    public static function costePorPeso($kg, $zona) {
        $tabla = ($zona === 'BAL') ? self::TARIFA_BALEARES : self::TARIFA_PENINSULA;
        $kg = (float) $kg; if ($kg <= 0) $kg = 1;
        foreach ($tabla as $maxkg => $precio) {
            if ($kg <= $maxkg) return $precio;
        }
        // > 15 kg (hasta MAX_KG): tarifa plana del último tramo (2shop no cobra €/kg extra)
        return end($tabla);
    }

    public function quote($method = '') {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: España peninsular + Baleares (sin Canarias 35/38, Ceuta 51, Melilla 52).
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        // La zona/tarifa (PEN vs BAL) y el gate los marca el CP del PUNTO elegido
        // (destino real del paquete); mientras no haya punto, la direccion de entrega.
        $cpDir = preg_replace('/\D/', '', (string) $order->delivery['postcode']);
        $cpPto = isset($_SESSION['seur_pudo_sel']['cp']) ? preg_replace('/\D/', '', (string) $_SESSION['seur_pudo_sel']['cp']) : '';
        $cp = ($cpPto !== '') ? $cpPto : $cpDir;
        if ($iso !== 'ES' || preg_match('/^(35|38|51|52)/', $cp)) {
            $this->enabled = false;
            return array();
        }

        // NOTA: a diferencia de seurnacional, este módulo SÍ se ofrece con productos
        // sin stock inmediato (decisión 2026-06-09: el punto custodia el paquete,
        // la promesa de plazo es menos crítica que en entrega a domicilio 24h).

        // Peso del carrito. SEUR 2shop no admite envíos de más de MAX_KG (20 kg):
        // no ofrecer punto de recogida para carritos más pesados (irían a domicilio).
        $kg   = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        if ($kg > self::MAX_KG) {
            $this->enabled = false;
            return array();
        }

        // Categorías vetadas para punto (bultos LARGOS): checkbox
        // "No permitir Punto de Recogida" en la categoría del admin;
        // HEREDA a subcategorías. Compartido con cexpunto (misma columna).
        $aPids = array();
        foreach ((array) $cart->get_products() as $aProd) {
            $nPid = (int) $aProd['id'];
            if ($nPid > 0) $aPids[$nPid] = true;
        }
        if (count($aPids)) {
            $aFlag = array(); $aTree = array();
            $qCat = tep_db_query("select categories_id, parent_id, categories_no_paqpunto from " . TABLE_CATEGORIES);
            while ($rCat = tep_db_fetch_array($qCat)) {
                $aTree[(int) $rCat['categories_id']] = (int) $rCat['parent_id'];
                if ((int) $rCat['categories_no_paqpunto'] === 1) $aFlag[(int) $rCat['categories_id']] = true;
            }
            if (count($aFlag)) {
                $qP2c = tep_db_query("select distinct categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id in (" . implode(',', array_keys($aPids)) . ")");
                while ($rP2c = tep_db_fetch_array($qP2c)) {
                    $nCid = (int) $rP2c['categories_id']; $nGuard = 0;
                    while ($nCid > 0 && $nGuard++ < 25) {
                        if (isset($aFlag[$nCid])) { $this->enabled = false; return array(); }
                        $nCid = isset($aTree[$nCid]) ? $aTree[$nCid] : 0;
                    }
                }
            }
        }

        $zona = (strncmp($cp, '07', 2) === 0) ? 'BAL' : 'PEN';
        $base = self::costePorPeso($kg, $zona) * self::MARGEN * self::FUEL;   // coste sin IVA + margen 10% + fuel 16,54%

        $iva = ($this->tax_class > 0)
            ? tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])
            : 0;
        // Redondeo a múltiplos de 0,05 sobre el precio CON IVA (regla de la tienda);
        // devolvemos el coste sin IVA equivalente para que el checkout muestre el redondeado.
        $conIva     = $base * (1 + $iva / 100);
        $conIvaRound = round($conIva / 0.05) * 0.05;
        $cost = ($iva > 0) ? ($conIvaRound / (1 + $iva / 100)) : $conIvaRound;

        $sTitle = MODULE_SHIPPING_SEURPUNTO_TEXT_WAY;
        if (!empty($_SESSION['seur_pudo_sel']['name'])) {
            $sTitle .= ' — ' . $_SESSION['seur_pudo_sel']['name'];
        }

        $this->quotes = array(
            'id'      => $this->code,
            'module'  => $this->title,
            'methods' => array(array(
                'id'    => $this->code,
                'title' => $sTitle,
                'cost'  => round($cost, 4),
            )),
        );
        if ($iva > 0) $this->quotes['tax'] = $iva;
        // El checkout moderno (includes/modules/checkout) espera un NOMBRE DE FICHERO
        // de su carpeta images/ (no HTML de tep_image como el legacy).
        $this->quotes['icon'] = 'shipping_seur.png';

        return $this->quotes;
    }

    /* ================================================================== *
     *  Puntos SEUR (API pickups) con caché en fichero                     *
     * ================================================================== */

    /** Lista de puntos cercanos a un CP. Devuelve array de
     *  {id,name,address,cp,city,lat,lng,hours} (máx $limit). Cachea 6h. */
    public static function puntos($cp, $city = '', $iso = 'ES', $limit = 10) {
        $cp  = preg_replace('/[^0-9A-Za-z]/', '', (string) $cp);
        $iso = strtoupper((string) $iso) ?: 'ES';
        if ($cp === '') return array();

        $cacheFile = DIR_FS_CATALOG . 'cache/seur_pudo_' . $iso . '_' . $cp . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            $data = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($data)) return array_slice($data, 0, $limit);
        }

        require_once DIR_FS_CATALOG . 'includes/classes/seur.php';
        $env = 'pre';
        $q = tep_db_query("SELECT config_value FROM seur_config WHERE config_key = 'env'");
        if ($q && tep_db_num_rows($q)) { $v = tep_db_fetch_array($q); $env = ($v['config_value'] === 'pro') ? 'pro' : 'pre'; }

        $s = new seur($env);
        $s->setTimeout(8); // checkout: no colgar la página si SEUR va lento

        // pickups EXIGE cityName (400 "City name value cannot be missing or empty").
        // Resolvemos la población oficial vía cities() (así también funciona buscar
        // por un CP arbitrario, y evitamos 400 por poblaciones mal escritas).
        $cityName = '';
        $rc = $s->cities($cp, $iso);
        $dc = seur::payload($rc);
        if (is_array($dc) && !empty($dc[0]['cityName'])) $cityName = (string) $dc[0]['cityName'];
        if ($cityName === '') $cityName = trim((string) $city); // fallback: la del pedido

        $r = $s->pickups($cp, $iso, $cityName);
        $d = seur::payload($r);

        $out = array();
        if (is_array($d)) {
            foreach ($d as $p) {
                if (empty($p['pudoId'])) continue;
                $hours = array();
                if (!empty($p['openingTime']['weekDays']) && is_array($p['openingTime']['weekDays'])) {
                    foreach ($p['openingTime']['weekDays'] as $wd) {
                        if (!empty($wd['openingHours'])) $hours[] = ($wd['day'] ?? '') . ': ' . $wd['openingHours'];
                    }
                }
                $out[] = array(
                    'id'      => (string) $p['pudoId'],
                    'name'    => trim((string) ($p['name'] ?? '')),
                    'address' => trim((string) ($p['address'] ?? '')),
                    'cp'      => trim((string) ($p['postalCode'] ?? $cp)),
                    'city'    => trim((string) ($p['cityName'] ?? $city)),
                    'lat'     => (float) ($p['latitude'] ?? 0),
                    'lng'     => (float) ($p['longitude'] ?? 0),
                    'hours'   => implode(' · ', $hours),
                );
            }
        }
        if ($out) @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
        return array_slice($out, 0, $limit);
    }

    /** Resuelve un punto por su id contra la lista cacheada del CP (anti-tampering). */
    public static function puntoById($id, $cp, $city = '', $iso = 'ES') {
        $id = trim((string) $id);
        if ($id === '') return false;
        foreach (self::puntos($cp, $city, $iso, 50) as $p) {
            if ($p['id'] === $id) return $p;
        }
        return false;
    }

    /* ================================================================== *
     *  Admin (instalación)                                                *
     * ================================================================== */

    public function check() {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_SEURPUNTO_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install() {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar SEUR Punto de Recogida', 'MODULE_SHIPPING_SEURPUNTO_STATUS', 'True', 'Ofrecer entrega en punto SEUR', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Coste (sin IVA)', 'MODULE_SHIPPING_SEURPUNTO_COST', '4.09', 'Coste del envío a punto SEUR, sin IVA', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SHIPPING_SEURPUNTO_TAX_CLASS', '1', 'Tipo de impuesto aplicado', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SHIPPING_SEURPUNTO_SORT_ORDER', '15', 'Orden de visualización', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('IPs de prueba', 'MODULE_SHIPPING_SEURPUNTO_TEST_IP', '217.127.199.171', 'FASE PRUEBAS: solo estas IPs (separadas por comas) ven el módulo. Vaciar para producción.', '6', '0', now())");
    }

    public function remove() {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        return array('MODULE_SHIPPING_SEURPUNTO_STATUS', 'MODULE_SHIPPING_SEURPUNTO_COST', 'MODULE_SHIPPING_SEURPUNTO_TAX_CLASS', 'MODULE_SHIPPING_SEURPUNTO_SORT_ORDER', 'MODULE_SHIPPING_SEURPUNTO_TEST_IP');
    }
}
