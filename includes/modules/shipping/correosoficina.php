<?php
/**
 * Módulo de envío "Correos - Recoger en oficina" (Paq Estándar, entrega en oficina / OFUAOF).
 *
 * El cliente elige una oficina de Correos cercana (lista + mapa en el checkout); la
 * oficina se guarda en sesión (correos_oficina_sel) en checkout_shipping y, al grabar
 * el pedido (checkout_process), se persiste en correos_oficina_orders. El watcher de
 * Vstock la usa como addressee.chosenOffice al preregistrar por API (correos_albaran.php
 * mode=ofi). Oficinas vía correos_oficinas.php (localizador público de Correos).
 *
 * FASE PRUEBAS: visible solo desde las IPs de MODULE_SHIPPING_CORREOSOFICINA_TEST_IP
 * (vaciar ese campo en admin para abrirlo a producción).
 *
 * PRECIO = tarifa Paq Estándar 2026 × descuento de oficina (contrato S0133) × 1,10
 * (combustible) × 1,20 (margen). Tablas SIN IVA; el módulo añade IVA + redondeo 0,05.
 * Tope de peso 40 kg (máximo Paq Estándar: tarifa tabulada a 30 kg + €/kg adicional
 * por encima). Ver memoria francobordo_correos_api.
 */
class correosoficina {
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    const MAX_KG = 40;   // Paq Estándar: máximo 40 kg (tarifa hasta 30 kg + €/kg adicional)

    // Localizador PÚBLICO de oficinas (sin auth; ver correos_oficinas.php).
    const LOC_BASE  = 'https://api1.correos.es/digital-services/searchloc/api/v1/';
    const LOC_CACHE = '/home/francobordo/correos_oficinas_cache/';
    const LOC_TTL   = 604800;   // 7 días (mapeo CP→oficinas estable)

    function __construct() {
        global $customer_group_id;

        $this->code        = 'correosoficina';
        $this->title       = MODULE_SHIPPING_CORREOSOFICINA_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_CORREOSOFICINA_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SHIPPING_CORREOSOFICINA_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'correos.png';
        $this->tax_class   = MODULE_SHIPPING_CORREOSOFICINA_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SHIPPING_CORREOSOFICINA_STATUS') && MODULE_SHIPPING_CORREOSOFICINA_STATUS == 'True');

        // FASE PRUEBAS: gate por IP de origen (lista separada por comas). Vacío = todos.
        if ($this->enabled && defined('MODULE_SHIPPING_CORREOSOFICINA_TEST_IP') && trim(MODULE_SHIPPING_CORREOSOFICINA_TEST_IP) !== '') {
            $aIPs = array_map('trim', explode(',', MODULE_SHIPPING_CORREOSOFICINA_TEST_IP));
            if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $aIPs, true)) $this->enabled = false;
        }

        // Solo cliente final (los grupos B2B tienen sus condiciones de envío).
        if ($this->enabled && isset($customer_group_id) && (int) $customer_group_id !== 0) {
            $this->enabled = false;
        }
    }

    /* Precio por tramo "hasta N kg". Transporte = tarifa Paq Estándar × dto oficina (S0133)
     * × 1,10 (combustible) × 1,20 (margen). 4 zonas:
     *  - PEN  Península (Zona 3)            → + IVA 21% en checkout.
     *  - BAL  Baleares (Zona 4, sin aduanas) → + IVA 21% en checkout.
     *  - CEUTAMEL Ceuta/Melilla (Zona 4 transp. + 14,42 € DUA exportación a coste) → IVA EXENTO.
     *  - CANAR Canarias (Zona 5 transp. + 14,42 € DUA exportación a coste)         → IVA EXENTO.
     * El DUA (despacho aduanero) se repercute SIN margen (decisión usuario 2026-06-16). */
    const DUA_ISLAS = 14.42;
    const TARIFA_PEN      = array(1=>4.08, 2=>4.30, 3=>4.58, 4=>4.79, 5=>4.92, 10=>6.32, 15=>8.08, 20=>9.91, 25=>11.73, 30=>13.56);
    const TARIFA_BAL      = array(1=>5.39, 2=>6.17, 3=>7.41, 4=>8.02, 5=>8.66, 10=>11.75, 15=>15.33, 20=>20.93, 25=>26.53, 30=>32.13);
    const TARIFA_CEUTAMEL = array(1=>19.81, 2=>20.59, 3=>21.83, 4=>22.44, 5=>23.08, 10=>26.17, 15=>29.75, 20=>35.35, 25=>40.95, 30=>46.55);
    const TARIFA_CANAR    = array(1=>24.62, 2=>25.61, 3=>27.37, 4=>28.50, 5=>29.61, 10=>35.71, 15=>46.76, 20=>60.80, 25=>74.83, 30=>88.87);

    /* €/kg adicional (o fracción) por encima de 30 kg, SIN IVA = tarifa adicional base 2026
     * × dto oficina (S0133) × 1,10 (combustible) × 1,20 (margen) — mismas reglas que las bandas.
     * Base: Z3=0,57 · Z4=1,69 · Z5=3,99 €/kg. El DUA de islas es fijo por envío (no por kg). */
    const ADIC_PEN      = 0.3658;   // Z3
    const ADIC_BAL      = 1.1203;   // Z4 transporte
    const ADIC_CEUTAMEL = 1.1203;   // Z4 transporte
    const ADIC_CANAR    = 2.8074;   // Z5 transporte

    /** Zona por código postal (España): 35/38=Canarias, 51/52=Ceuta/Melilla, 07=Baleares, resto=Península. */
    public static function zonaPorCp($cp) {
        $cp = trim((string) $cp);
        if (preg_match('/^(35|38)/', $cp)) return 'CANAR';
        if (preg_match('/^(51|52)/', $cp)) return 'CEUTAMEL';
        if (preg_match('/^07/', $cp))      return 'BAL';
        return 'PEN';
    }

    /** ¿Destino exento de IVA? (Canarias, Ceuta, Melilla — fuera del territorio IVA). */
    public static function exentaIva($zona) {
        return ($zona === 'CANAR' || $zona === 'CEUTAMEL');
    }

    /** Precio (transporte + DUA si isla) según peso (kg) y zona. */
    public static function costePorPeso($kg, $zona) {
        $map  = array('CANAR' => self::TARIFA_CANAR, 'CEUTAMEL' => self::TARIFA_CEUTAMEL, 'BAL' => self::TARIFA_BAL);
        $adic = array('CANAR' => self::ADIC_CANAR, 'CEUTAMEL' => self::ADIC_CEUTAMEL, 'BAL' => self::ADIC_BAL);
        $tabla = isset($map[$zona]) ? $map[$zona] : self::TARIFA_PEN;
        $kg = (float) $kg; if ($kg <= 0) $kg = 1;
        foreach ($tabla as $maxkg => $precio) {
            if ($kg <= $maxkg) return $precio;
        }
        // 30 < kg <= MAX_KG (40): tarifa de 30 kg + €/kg adicional (o fracción) de la zona.
        $a = isset($adic[$zona]) ? $adic[$zona] : self::ADIC_PEN;
        return end($tabla) + ceil($kg - 30) * $a;
    }

    public function quote($method = '') {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: solo España (todas las zonas, incl. islas/Ceuta/Melilla). Sin Portugal
        // ni internacional (la entrega en oficina solo aplica a destinos nacionales).
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = trim((string) $order->delivery['postcode']);
        if ($iso !== 'ES' || !preg_match('/^\d{5}$/', $cp)) {
            $this->enabled = false;
            return array();
        }

        // Paq Estándar: máximo 30 kg. Carritos más pesados no pueden ir a oficina.
        $kg = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        if ($kg > self::MAX_KG) {
            $this->enabled = false;
            return array();
        }

        $zona = self::zonaPorCp($cp);
        $base = self::costePorPeso($kg, $zona);   // precio (transporte + DUA si isla), sin IVA

        // IVA solo en Península y Baleares. Canarias/Ceuta/Melilla están fuera del
        // territorio IVA → envío EXENTO (el DUA ya va incluido a coste en su tarifa).
        $iva = (!self::exentaIva($zona) && $this->tax_class > 0)
            ? tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])
            : 0;
        // Redondeo a múltiplos de 0,05 sobre el precio CON IVA (regla de la tienda).
        $conIva      = $base * (1 + $iva / 100);
        $conIvaRound = round($conIva / 0.05) * 0.05;
        $cost = ($iva > 0) ? ($conIvaRound / (1 + $iva / 100)) : $conIvaRound;

        $sTitle = MODULE_SHIPPING_CORREOSOFICINA_TEXT_WAY;
        if (!empty($_SESSION['correos_oficina_sel']['name'])) {
            $sTitle .= ' — ' . $_SESSION['correos_oficina_sel']['name'];
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
        $this->quotes['icon'] = 'shipping_correos.png';

        return $this->quotes;
    }

    /* ================================================================== *
     *  Oficinas de Correos (localizador público) con caché en fichero     *
     * ================================================================== */

    /** GET al localizador público (sin auth). Devuelve array decodificado o null. */
    private static function locGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => array('Accept: application/json', 'User-Agent: Mozilla/5.0'),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $b = curl_exec($ch);
        $h = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
        return ($h >= 200 && $h < 300 && $b !== false) ? json_decode($b, true) : null;
    }

    /** Sanea texto de la API pública (sin etiquetas HTML ni espacios raros) — defensa
     *  anti-XSS aguas abajo (el popup del mapa y la persistencia consumen estos campos). */
    private static function clean($s) {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s)));
    }

    /** Normaliza el horario (timetables) a una cadena legible para el popup. */
    private static function fmtHours($tt) {
        if (is_string($tt)) return $tt;
        if (is_array($tt)) {
            $parts = array();
            foreach ($tt as $k => $v) {
                if (is_string($v)) { $parts[] = (is_string($k) ? $k . ': ' : '') . $v; }
                elseif (is_array($v)) {
                    $d = $v['day'] ?? (is_string($k) ? $k : '');
                    $hh = $v['hours'] ?? $v['schedule'] ?? $v['openingHours'] ?? '';
                    if ($hh) $parts[] = trim($d . ' ' . $hh);
                }
            }
            return implode(' · ', array_slice($parts, 0, 7));
        }
        return '';
    }

    /** Oficinas cercanas a un CP (+ texto opcional para desambiguar municipio).
     *  Devuelve array de {id,name,type,address,cp,city,lat,lng,distance,hours}.
     *  Mismo formato que consume el selector del checkout y correos_oficinas.php. */
    public static function oficinas($cp, $q = '', $limit = 15) {
        $cp = preg_replace('/\D/', '', (string) $cp);
        if (!preg_match('/^\d{5}$/', $cp)) return array();
        $q   = preg_replace('/[^\p{L}\p{N}\s.,\'\-]/u', '', trim((string) $q));   // texto saneado
        $geo = ($q !== '') ? ($q . ' ' . $cp) : $cp;

        $cacheFile = self::LOC_CACHE . substr(md5($geo), 0, 16) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < self::LOC_TTL)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached)) return array_slice($cached, 0, $limit);
        }
        if (!is_dir(self::LOC_CACHE)) @mkdir(self::LOC_CACHE, 0750, true);

        // 1) geocodificar (CP, o CP+ciudad) → lat/lon
        $sug = self::locGet(self::LOC_BASE . 'suggestions?text=' . rawurlencode($geo));
        $lat = $sug['suggestions'][0]['latitude']  ?? null;
        $lon = $sug['suggestions'][0]['longitude'] ?? null;
        if ($lat === null || $lon === null) return array();

        // 2) oficinas por lat/lon (¡minúscula!). Primero radio normal; si NO sale ninguna oficina
        //    válida (CP de pueblo sin oficina propia → el localizador solo da enlaces rurales que
        //    se filtran), reintentar ampliando a 20 km (&distance) para dar con la más cercana.
        $officesUrl = self::LOC_BASE . 'offices?latitude=' . rawurlencode($lat) . '&longitude=' . rawurlencode($lon);
        $out = self::fetchOficinas($officesUrl);
        if (!$out) $out = self::fetchOficinas($officesUrl . '&distance=20000');
        if ($out) @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
        return array_slice($out, 0, $limit);
    }

    /** Descarga (con reintentos por respuesta vacía transitoria), ordena por distancia y filtra
     *  (excluye unidades de reparto rural) las oficinas que devuelve una URL del localizador. */
    private static function fetchOficinas($officesUrl) {
        $list = array();
        for ($try = 0; $try < 4 && !$list; $try++) {
            if ($try > 0) usleep(300000);   // 0,3 s
            $off  = self::locGet($officesUrl);
            $list = $off['others']['offices'] ?? array();
        }
        usort($list, function ($a, $b) { return ($a['distance'] ?? 1e12) <=> ($b['distance'] ?? 1e12); });

        $out = array();
        foreach ($list as $o) {
            if (empty($o['officeId'])) continue;
            // Excluir unidades de reparto rural (no son puntos de recogida ni valen como chosenOffice).
            $tn = (string) ($o['typeName'] ?? '');
            $ot = (string) ($o['officeType'] ?? '');
            if ($ot === 'EAM' || preg_match('/ENLACE|RURAL|REPARTO|MOTORIZAD|UNIDAD/i', $tn)) continue;
            $out[] = array(
                'id'       => preg_replace('/[^0-9A-Za-z]/', '', (string) $o['officeId']),   // código de oficina: solo alfanumérico
                'name'     => self::clean($o['unitName'] ?? ($o['typeName'] ?? 'Oficina de Correos')),
                'type'     => self::clean($tn),
                'address'  => self::clean($o['address'] ?? ''),
                'cp'       => preg_replace('/\D/', '', (string) ($o['postalCode'] ?? '')),
                'city'     => self::clean($o['cityName'] ?? ''),
                'lat'      => (float) ($o['latitude']  ?? 0),
                'lng'      => (float) ($o['longitude'] ?? 0),
                'distance' => $o['distance'] ?? null,
                'hours'    => self::clean(self::fmtHours($o['timetables'] ?? null)),
            );
            if (count($out) >= 30) break;
        }
        return $out;
    }

    /** Resuelve una oficina por su id contra la lista del CP (anti-tampering). */
    public static function oficinaById($id, $cp, $q = '') {
        $id = trim((string) $id);
        if ($id === '') return false;
        foreach (self::oficinas($cp, $q, 50) as $o) {
            if ($o['id'] === $id) return $o;
        }
        return false;
    }

    /* ================================================================== *
     *  Admin (instalación)                                                *
     * ================================================================== */

    public function check() {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_CORREOSOFICINA_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install() {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar Correos Recoger en Oficina', 'MODULE_SHIPPING_CORREOSOFICINA_STATUS', 'True', 'Ofrecer entrega en oficina de Correos', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SHIPPING_CORREOSOFICINA_TAX_CLASS', '1', 'Tipo de impuesto aplicado', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SHIPPING_CORREOSOFICINA_SORT_ORDER', '24', 'Orden de visualización', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('IPs de prueba', 'MODULE_SHIPPING_CORREOSOFICINA_TEST_IP', '217.127.199.171', 'FASE PRUEBAS: solo estas IPs (separadas por comas) ven el módulo. Vaciar para producción.', '6', '0', now())");
    }

    public function remove() {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        return array('MODULE_SHIPPING_CORREOSOFICINA_STATUS', 'MODULE_SHIPPING_CORREOSOFICINA_TAX_CLASS', 'MODULE_SHIPPING_CORREOSOFICINA_SORT_ORDER', 'MODULE_SHIPPING_CORREOSOFICINA_TEST_IP');
    }
}
