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
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes;

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

    public function quote($method = '') {
        global $order, $cart;

        if (!$this->enabled) return array();

        // Destino: España peninsular + Baleares (sin Canarias 35/38, Ceuta 51, Melilla 52).
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = trim((string) $order->delivery['postcode']);
        if ($iso !== 'ES' || preg_match('/^(35|38|51|52)/', $cp)) {
            $this->enabled = false;
            return array();
        }

        // NOTA: a diferencia de seurnacional, este módulo SÍ se ofrece con productos
        // sin stock inmediato (decisión 2026-06-09: el punto custodia el paquete,
        // la promesa de plazo es menos crítica que en entrega a domicilio 24h).

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
                'cost'  => (float) MODULE_SHIPPING_SEURPUNTO_COST,
            )),
        );
        if ($this->tax_class > 0) {
            $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
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
