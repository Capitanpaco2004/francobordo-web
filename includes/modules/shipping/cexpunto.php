<?php
/**
 * Módulo de envío "Correos Express - Paq Punto" (entrega en punto de conveniencia).
 *
 * El cliente elige un punto CEX cercano (lista + mapa Leaflet en el checkout);
 * el punto se guarda en sesión (cex_pudo_sel) en checkout_shipping y al grabar
 * el pedido (checkout_process) la dirección de entrega pasa a ser la del punto
 * + fila en cex_pudo_orders (idPtoExterno para la API: producto 18 Paq Punto).
 *
 * FASE PRUEBAS: visible solo desde las IPs de MODULE_SHIPPING_CEXPUNTO_TEST_IP
 * (vaciar ese campo en admin para abrirlo a todo el mundo).
 *
 * Puntos vía API consultPudo de Correos Express (includes/classes/correos_express.php)
 * con caché en fichero cache/cex_pudo_{ISO}_{CP}.json (TTL 6h).
 * Gemelo de seurpunto / correosoficina. Ver memoria francobordo_correos_express_api.
 */
class cexpunto {
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    const CACHE_TTL = 21600; // 6 h

    function __construct() {
        global $customer_group_id;

        $this->code        = 'cexpunto';
        $this->title       = MODULE_SHIPPING_CEXPUNTO_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_CEXPUNTO_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SHIPPING_CEXPUNTO_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'correos_exp.png';
        $this->tax_class   = MODULE_SHIPPING_CEXPUNTO_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SHIPPING_CEXPUNTO_STATUS') && MODULE_SHIPPING_CEXPUNTO_STATUS == 'True');

        // FASE PRUEBAS: gate por IP de origen (lista separada por comas). Campo
        // vacío = visible para todos (producción).
        if ($this->enabled && defined('MODULE_SHIPPING_CEXPUNTO_TEST_IP') && trim(MODULE_SHIPPING_CEXPUNTO_TEST_IP) !== '') {
            $aIPs = array_map('trim', explode(',', MODULE_SHIPPING_CEXPUNTO_TEST_IP));
            if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $aIPs, true)) $this->enabled = false;
        }

        // Solo cliente final (los grupos B2B tienen sus condiciones de envío).
        if ($this->enabled && isset($customer_group_id) && (int) $customer_group_id !== 0) {
            $this->enabled = false;
        }
    }

    /* Tarifa Paq Punto Correos Express (PVP SIN IVA, vigente 01/01/2026 – 31/12/2026,
     * contrato Francobordo 632140001). Tramos "hasta N kg". Solo zonas que ofrece el
     * módulo: Península y Baleares.
     * Margen +10% sobre el coste CEX (2026-06-25). Coste base CEX entre paréntesis. */
    const TARIFA_PENINSULA = array(   // kg => €/expedición (coste CEX ×1,10)
        5=>4.301, 10=>5.357, 15=>5.951, 20=>7.843, 25=>8.833, 30=>10.010,   // (3,91 4,87 5,41 7,13 8,03 9,10)
    );
    const TARIFA_BALEARES = array(    // columna "Baleares" (coste CEX ×1,10)
        5=>7.920, 10=>12.265, 15=>16.665, 20=>21.021, 25=>25.388, 30=>29.766, // (7,20 11,15 15,15 19,11 23,08 27,06)
    );
    const TARIFA_EXTRA_KG = array('PEN'=>0.264, 'BAL'=>0.880);  // €/kg por encima del último tramo (×1,10)
    // Tope de peso del producto Paq Punto (punto de conveniencia). 20 kg (decisión
    // usuario 2026-06-25, igual que SEUR Punto). La tarifa llega a 30; confirmar con CEX.
    const MAX_KG = 20;

    /** Coste CEX Paq Punto (sin IVA) según peso (kg) y zona ('BAL' Baleares / 'PEN' resto). */
    public static function costePorPeso($kg, $zona) {
        $tabla = ($zona === 'BAL') ? self::TARIFA_BALEARES : self::TARIFA_PENINSULA;
        $kg = (float) $kg; if ($kg <= 0) $kg = 1;
        foreach ($tabla as $maxkg => $precio) {
            if ($kg <= $maxkg) return $precio;
        }
        // > último tramo: precio del último tramo + €/kg adicional
        end($tabla);
        return current($tabla) + (ceil($kg) - key($tabla)) * self::TARIFA_EXTRA_KG[$zona === 'BAL' ? 'BAL' : 'PEN'];
    }

    public function quote($method = '') {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: España peninsular + Baleares (sin Canarias 35/38, Ceuta 51, Melilla 52).
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = trim((string) $order->delivery['postcode']);
        if ($iso !== 'ES' || preg_match('/^(35|38|51|52)/', $cp)) {
            $this->enabled = false;
            return array();
        }

        // Paq Punto no admite envíos por encima de MAX_KG: no ofrecer punto de
        // recogida para carritos más pesados (irían a domicilio).
        $kg = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        if ($kg > self::MAX_KG) {
            $this->enabled = false;
            return array();
        }
        $zona = (strncmp($cp, '07', 2) === 0) ? 'BAL' : 'PEN';
        $base = self::costePorPeso($kg, $zona);   // coste sin IVA

        $iva = ($this->tax_class > 0)
            ? tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])
            : 0;
        // Redondeo a múltiplos de 0,05 sobre el precio CON IVA (regla de la tienda);
        // devolvemos el coste sin IVA equivalente para que el checkout muestre el redondeado.
        $conIva      = $base * (1 + $iva / 100);
        $conIvaRound = round($conIva / 0.05) * 0.05;
        $cost = ($iva > 0) ? ($conIvaRound / (1 + $iva / 100)) : $conIvaRound;

        $sTitle = MODULE_SHIPPING_CEXPUNTO_TEXT_WAY;
        if (!empty($_SESSION['cex_pudo_sel']['name'])) {
            $sTitle .= ' — ' . $_SESSION['cex_pudo_sel']['name'];
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
        // El checkout moderno espera un NOMBRE DE FICHERO de su carpeta images/.
        $this->quotes['icon'] = 'shipping_correos_exp.png';

        return $this->quotes;
    }

    /* ================================================================== *
     *  Puntos de conveniencia CEX (API consultPudo) con caché en fichero  *
     * ================================================================== */

    /** Formatea listaHorariosPtoConv (dia + horario1..4) a "L: 09:00-14:00 17:00-20:00 · ...". */
    private static function fmtHorario($lista) {
        if (!is_array($lista)) return '';
        $dias = array('1'=>'L','2'=>'M','3'=>'X','4'=>'J','5'=>'V','6'=>'S','7'=>'D');
        $out = array();
        foreach ($lista as $h) {
            $d = $dias[(string) ($h['dia'] ?? '')] ?? '';
            $tramos = array();
            $t1 = trim((string) ($h['horario1'] ?? '') . '-' . (string) ($h['horario2'] ?? ''), '-');
            $t2 = trim((string) ($h['horario3'] ?? '') . '-' . (string) ($h['horario4'] ?? ''), '-');
            if ($t1 !== '') $tramos[] = $t1;
            if ($t2 !== '') $tramos[] = $t2;
            if ($d !== '' && $tramos) $out[] = $d . ': ' . implode(' ', $tramos);
        }
        return implode(' · ', $out);
    }

    /** Lista de puntos cercanos a un CP. Devuelve array de
     *  {id,name,address,cp,city,lat,lng,hours} (máx $limit). Cachea 6h.
     *  id = idPtoExterno (el que necesita grabacionEnvio con producto 18). */
    public static function puntos($cp, $city = '', $iso = 'ES', $limit = 10) {
        $cp  = preg_replace('/[^0-9]/', '', (string) $cp);
        $iso = strtoupper((string) $iso) ?: 'ES';
        if ($cp === '') return array();

        $cacheFile = DIR_FS_CATALOG . 'cache/cex_pudo_' . $iso . '_' . $cp . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            $data = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($data)) return array_slice($data, 0, $limit);
        }

        require_once DIR_FS_CATALOG . 'includes/classes/correos_express.php';
        $env = 'pro';
        $q = tep_db_query("SELECT config_value FROM cex_config WHERE config_key = 'env'");
        if ($q && tep_db_num_rows($q)) { $v = tep_db_fetch_array($q); $env = ($v['config_value'] === 'test') ? 'test' : 'pro'; }

        $cex = new correos_express($env);
        $cex->setTimeout(8); // checkout: no colgar la página si CEX va lento

        $res = $cex->consultPudo(array('cpDest' => $cp, 'isoPaisDest' => $iso, 'peso' => '1'));

        $out = array();
        if (!empty($res['ok']) && !empty($res['data']['ptoConv']) && is_array($res['data']['ptoConv'])) {
            foreach ($res['data']['ptoConv'] as $p) {
                if (empty($p['idPtoExterno'])) continue;
                $out[] = array(
                    'id'      => (string) $p['idPtoExterno'],
                    'name'    => trim((string) ($p['nombrePtoConv'] ?? '')),
                    'address' => trim((string) ($p['direccionPtoConv'] ?? '')),
                    'cp'      => trim((string) ($p['codigoPostalPtoConv'] ?? $cp)),
                    'city'    => trim((string) ($p['ciudadPtoConv'] ?? $city)),
                    'lat'     => (float) ($p['latitudPtoConv'] ?? 0),
                    'lng'     => (float) ($p['longitudPtoConv'] ?? 0),
                    'hours'   => self::fmtHorario($p['listaHorariosPtoConv'] ?? array()),
                );
            }
        }
        $out = self::filtrarCoordsCorruptas($out);
        if ($out) @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
        return array_slice($out, 0, $limit);
    }

    /** Descarta puntos con lat/lng claramente erroneas. Correos Express a veces da
     *  de alta un punto con coordenadas mal metidas (visto: "Kingfix", CP 21800 Moguer,
     *  con coords de Madrid 40.45,-3.67) que descuadran el encuadre del mapa. Con >=3
     *  puntos descartamos los que esten muy lejos de la MEDIANA del grupo (>1,5 grados
     *  ~165 km) y los de coords nulas. */
    private static function filtrarCoordsCorruptas($pts) {
        $conCoord = array_values(array_filter($pts, function ($p) {
            return abs((float) $p['lat']) > 0.01 && abs((float) $p['lng']) > 0.01;
        }));
        if (count($conCoord) < 3) return $pts; // muy pocos para detectar outliers con fiabilidad
        $lats = array_map(function ($p) { return (float) $p['lat']; }, $conCoord); sort($lats);
        $lngs = array_map(function ($p) { return (float) $p['lng']; }, $conCoord); sort($lngs);
        $mlat = $lats[intdiv(count($lats), 2)];
        $mlng = $lngs[intdiv(count($lngs), 2)];
        $kept = array_values(array_filter($pts, function ($p) use ($mlat, $mlng) {
            return abs((float) $p['lat'] - $mlat) <= 1.5 && abs((float) $p['lng'] - $mlng) <= 1.5;
        }));
        return $kept ?: $pts; // si por lo que sea todo se descarta, devolver original
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
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_CEXPUNTO_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install() {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar Correos Express Paq Punto', 'MODULE_SHIPPING_CEXPUNTO_STATUS', 'True', 'Ofrecer entrega en punto de conveniencia (Paq Punto) de Correos Express', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Coste (sin IVA)', 'MODULE_SHIPPING_CEXPUNTO_COST', '3.91', 'Coste base del envío a Paq Punto, sin IVA (la tarifa real se calcula por peso)', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SHIPPING_CEXPUNTO_TAX_CLASS', '1', 'Tipo de impuesto aplicado', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SHIPPING_CEXPUNTO_SORT_ORDER', '16', 'Orden de visualización', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('IPs de prueba', 'MODULE_SHIPPING_CEXPUNTO_TEST_IP', '217.127.199.171', 'FASE PRUEBAS: solo estas IPs (separadas por comas) ven el módulo. Vaciar para producción.', '6', '0', now())");
    }

    public function remove() {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        return array('MODULE_SHIPPING_CEXPUNTO_STATUS', 'MODULE_SHIPPING_CEXPUNTO_COST', 'MODULE_SHIPPING_CEXPUNTO_TAX_CLASS', 'MODULE_SHIPPING_CEXPUNTO_SORT_ORDER', 'MODULE_SHIPPING_CEXPUNTO_TEST_IP');
    }
}
