<?php
/**
 * Módulo de envío "SEUR antes de las 13:30" (servicio SEUR 13:30, 9/2).
 *
 * REESCRITO 2026-06-11 (antes: zonas genéricas expedición+€/kg, desactivado).
 * Tarifa S-13:30 [P,L] del contrato 2026 (01/11/2025) Península, con MARGEN
 * del 20% (decisión usuario; a diferencia de seurpunto/europack que van a coste).
 * Redondeo a múltiplos de 0,05 sobre el importe CON IVA (regla de la tienda).
 *
 * Solo se ofrece si:
 *   - destino España PENINSULAR (la tarifa S-13:30 no cubre Baleares; fuera
 *     también Canarias 35/38, Ceuta 51, Melilla 52),
 *   - TODOS los productos del carrito tienen stock real (products_quantity > 0;
 *     los sentinels NO cuentan: 2000=bajo demanda, -100=en proveedor,
 *     -800=bajo pedido, -900=fuera de catálogo),
 *   - es día laborable (L-V) entre las 06:00 y las 15:00 (hora del servidor,
 *     Europe/Madrid): el almacén tiene que poder sacar el pedido en el día.
 *
 * El almacén expide estos pedidos con la agencia Vstock "SEUR 13:30" (TRA 7,
 * integración vieja, transmite 9/2). Ver memoria francobordo_seur_api.
 */
class seurnacional
{
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    /* Margen sobre el coste SEUR (1.20 = +20%). */
    const MARGEN = 1.20;
    const FUEL = 1.1654;  // sobrecoste fuel SEUR 16,54% repercutido al cliente (2026-06-25)

    /* Tarifa S-13:30 Península (coste sin IVA, contrato 01/11/2025). kg => €/exp. */
    const TARIFA_1330_PENINSULA = array(
        1=>7.09, 2=>8.20, 3=>8.20, 4=>8.59, 5=>8.59, 10=>13.29,
        15=>23.10, 20=>27.85, 25=>32.58, 30=>37.34, 40=>48.88, 50=>60.42,
        70=>80.82, 90=>103.93, 110=>127.02, 130=>150.12, 150=>173.19,
    );
    const TARIFA_1330_EXTRA_KG = 1.16;   // €/kg por encima de 150 kg

    /* Ventana de oferta: días laborables (1=lunes..5=viernes) y franja horaria. */
    const HORA_DESDE = 6;    // se ofrece desde las 06:00...
    const HORA_HASTA = 15;   // ...hasta las 14:59 (a las 15:00 deja de ofrecerse)

    public function __construct()
    {
        $this->code        = 'seurnacional';
        $this->title       = MODULE_SEUR_NACIONAL_TEXT_TITLE;
        $this->description = MODULE_SEUR_NACIONAL_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SEUR_NACIONAL_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'seur.png';
        $this->tax_class   = MODULE_SEUR_NACIONAL_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SEUR_NACIONAL_STATUS') && MODULE_SEUR_NACIONAL_STATUS == 'True');

        // Ventana horaria: L-V de 06:00 a 14:59. Fuera de ella no se ofrece
        // (no se puede garantizar la salida en el día → entrega 13:30 mañana).
        if ($this->enabled) {
            $dia  = (int) date('N');   // 1=lunes .. 7=domingo
            $hora = (int) date('G');
            if ($dia > 5 || $hora < self::HORA_DESDE || $hora >= self::HORA_HASTA) {
                $this->enabled = false;
            }
        }
    }

    /** Coste SEUR S-13:30 (sin IVA, sin margen) por peso en kg (Península). */
    public static function costePorPeso($kg)
    {
        $kg = (float) $kg;
        if ($kg <= 0) $kg = 1;
        foreach (self::TARIFA_1330_PENINSULA as $maxkg => $precio) {
            if ($kg <= $maxkg) return $precio;
        }
        // > 150 kg: último tramo + €/kg sobre 150
        return self::TARIFA_1330_PENINSULA[150] + (ceil($kg) - 150) * self::TARIFA_1330_EXTRA_KG;
    }

    public function quote($method = '')
    {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: España PENINSULAR (sin Baleares 07, Canarias 35/38, Ceuta 51, Melilla 52).
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = preg_replace('/\s+/', '', (string) $order->delivery['postcode']);
        if ($iso !== 'ES' || !preg_match('/^\d{5}$/', $cp) || preg_match('/^(07|35|38|51|52)/', $cp)) {
            $this->enabled = false;
            return array();
        }

        // Cada linea debe tener stock REAL que CUBRA la cantidad pedida. Variantes:
        // se mira products_stock de la combinacion EXACTA (no el stock agregado del
        // producto, que es la suma de todas las variantes). Sentinels excluidos:
        // <=0 (proveedor / bajo pedido / agotado) y 2000 (stock virtual por metro).
        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $aProduct = $order->products[$i];
            $ordered  = (float) (isset($aProduct['qty']) ? $aProduct['qty'] : 1);
            $rawId    = (string) (isset($aProduct['id']) ? $aProduct['id'] : (isset($aProduct['products_id']) ? $aProduct['products_id'] : ''));
            $baseId   = (strpos($rawId, '{') !== false) ? (int) strstr($rawId, '{', true) : (int) $rawId;

            // Stock disponible de EXACTAMENTE lo pedido.
            $avail = null;
            if (strpos($rawId, '{') !== false && preg_match_all('/\{(\d+)\}(\d+)/', $rawId, $mm, PREG_SET_ORDER)) {
                $pairs = array();
                foreach ($mm as $pr) $pairs[] = $pr[1] . '-' . $pr[2];
                $comb = implode(',', $pairs);
                $rs = tep_db_query("SELECT products_stock_quantity FROM products_stock WHERE products_id = " . (int) $baseId . " AND products_stock_attributes = '" . tep_db_input($comb) . "'");
                if ($r = tep_db_fetch_array($rs)) $avail = (float) $r['products_stock_quantity'];
            }
            if ($avail === null) {   // sin variante (o combinacion no encontrada): stock a nivel producto
                $q = tep_db_query('SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . (int) $baseId);
                $r = tep_db_fetch_array($q);
                $avail = $r ? (float) $r['products_quantity'] : 0;
            }
            // No se ofrece 13:30 si: stock virtual (2000), agotado/bajo pedido (<=0), o no cubre lo pedido.
            if ((int) $avail === 2000 || $avail <= 0 || $avail < $ordered) {
                $this->enabled = false;
                return array();
            }
        }

        // Precio: tarifa S-13:30 + 20% de margen; redondeo a 0,05 sobre el CON IVA.
        $kg   = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        $base = self::costePorPeso($kg) * self::MARGEN * self::FUEL;

        $iva = ($this->tax_class > 0)
            ? tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])
            : 0;
        $conIva      = $base * (1 + $iva / 100);
        $conIvaRound = round($conIva / 0.05) * 0.05;
        $cost = ($iva > 0) ? ($conIvaRound / (1 + $iva / 100)) : $conIvaRound;

        $this->quotes = array(
            'id'      => $this->code,
            'module'  => $this->title,
            'methods' => array(array(
                'id'    => $this->code,
                'title' => MODULE_SEUR_NACIONAL_TEXT_WAY,
                'cost'  => round($cost, 4),
            )),
        );
        if ($iva > 0) $this->quotes['tax'] = $iva;
        // El checkout moderno espera un NOMBRE DE FICHERO de su carpeta images/.
        $this->quotes['icon'] = 'shipping_seur.png';

        return $this->quotes;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SEUR_NACIONAL_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar SEUR 13:30', 'MODULE_SEUR_NACIONAL_STATUS', 'True', '¿Ofrecer SEUR antes de las 13:30?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SEUR_NACIONAL_TAX_CLASS', '1', 'IVA aplicado al envío.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SEUR_NACIONAL_SORT_ORDER', '10', 'Orden de aparición.', '6', '0', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SEUR\_NACIONAL\_%'");
    }

    public function keys()
    {
        return array('MODULE_SEUR_NACIONAL_STATUS', 'MODULE_SEUR_NACIONAL_TAX_CLASS', 'MODULE_SEUR_NACIONAL_SORT_ORDER');
    }
}
