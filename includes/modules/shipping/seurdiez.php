<?php
/**
 * Módulo de envío "SEUR antes de las 10h" (servicio SEUR 10, 3/2).
 *
 * Clon del módulo seurnacional (SEUR 13:30). Tarifa S-10 [P,L] del contrato
 * AUTORIZADO julio 2026, con MARGEN del 20% (decisión usuario). Redondeo a
 * múltiplos de 0,05 sobre el importe CON IVA (regla de la tienda).
 *
 * Tarifa S-10 (coste sin IVA) con las DOS columnas del contrato: "Medio España
 * Peninsular" y "Medio España Pen Portugal" (coinciden hasta 5 kg; desde 10 kg
 * Portugal es más caro). Peninsular: 1kg 5,58 · 5 8,67 · 10 10,74 · 30 41,88 ·
 * 50 67,44 · +1,21/kg. Portugal: igual hasta 5 kg · 10 11,44 · 30 50,35 · 50
 * 81,84 · +1,50/kg (tabla PT propia desde 2026-07-08; antes usaba la peninsular).
 *
 * Solo se ofrece si:
 *   - destino España PENINSULAR (sin Baleares 07, Canarias 35/38, Ceuta 51,
 *     Melilla 52) o PORTUGAL CONTINENTAL (excluye Madeira/Azores, CP 9xxx),
 *   - todas las líneas tienen stock real de la VARIANTE pedida que CUBRA la
 *     cantidad (sentinels excluidos: <=0 y 2000),
 *   - es día laborable (L-V) entre las 06:00 y las 15:00 (Europe/Madrid): el
 *     almacén tiene que poder sacar el pedido en el día (misma recogida que 13:30).
 *
 * El almacén expide estos pedidos con la agencia Vstock "SEUR 10" (el watcher
 * los manda al endpoint con svc=1000 -> servicio 3 / producto 2, ES y PT).
 * Ver memoria francobordo_seur_api.
 */
class seurdiez
{
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    /* Margen sobre el coste SEUR (1.20 = +20%). */
    const MARGEN = 1.20;
    const FUEL = 1.1654;  // sobrecoste fuel SEUR 16,54% repercutido al cliente (2026-06-25)

    /* Tarifa S-10 (coste sin IVA, contrato AUTORIZADO julio 2026). kg => €/exp. */
    const TARIFA_S10_PENINSULA = array(   // columna "Medio España Peninsular"
        1=>5.58, 3=>7.14, 5=>8.67, 10=>10.74, 15=>20.28, 20=>30.21,
        25=>36.05, 30=>41.88, 40=>55.38, 50=>67.44,
    );
    const TARIFA_S10_PORTUGAL = array(    // columna "Medio España Pen Portugal"
        1=>5.58, 3=>7.14, 5=>8.67, 10=>11.44, 15=>23.49, 20=>35.94,
        25=>43.15, 30=>50.35, 40=>66.94, 50=>81.84,
    );
    const TARIFA_S10_EXTRA_KG = array('PEN'=>1.21, 'PT'=>1.50);   // €/kg por encima de 50 kg

    /* Ventana de oferta: días laborables (1=lunes..5=viernes) y franja horaria. */
    const HORA_DESDE = 6;    // se ofrece desde las 06:00...
    const HORA_HASTA = 15;   // ...hasta las 14:59 (a las 15:00 deja de ofrecerse)

    public function __construct()
    {
        $this->code        = 'seurdiez';
        $this->title       = MODULE_SEUR_DIEZ_TEXT_TITLE;
        $this->description = MODULE_SEUR_DIEZ_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SEUR_DIEZ_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'seur.png';
        $this->tax_class   = MODULE_SEUR_DIEZ_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SEUR_DIEZ_STATUS') && MODULE_SEUR_DIEZ_STATUS == 'True');

        // Ventana horaria: L-V de 06:00 a 14:59. Fuera de ella no se ofrece
        // (no se puede garantizar la salida en el día -> entrega antes de 10h mañana).
        if ($this->enabled) {
            $dia  = (int) date('N');   // 1=lunes .. 7=domingo
            $hora = (int) date('G');
            if ($dia > 5 || $hora < self::HORA_DESDE || $hora >= self::HORA_HASTA) {
                $this->enabled = false;
            }
        }
    }

    /** Coste SEUR S-10 (sin IVA, sin margen) por peso en kg y zona ('PEN' / 'PT'). */
    public static function costePorPeso($kg, $zona = 'PEN')
    {
        $tabla = ($zona === 'PT') ? self::TARIFA_S10_PORTUGAL : self::TARIFA_S10_PENINSULA;
        $extra = self::TARIFA_S10_EXTRA_KG[($zona === 'PT') ? 'PT' : 'PEN'];
        $kg = (float) $kg;
        if ($kg <= 0) $kg = 1;
        foreach ($tabla as $maxkg => $precio) {
            if ($kg <= $maxkg) return $precio;
        }
        // > 50 kg: último tramo + €/kg sobre 50
        return $tabla[50] + (ceil($kg) - 50) * $extra;
    }

    public function quote($method = '')
    {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: España PENINSULAR o PORTUGAL CONTINENTAL.
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = preg_replace('/\s+/', '', (string) $order->delivery['postcode']);
        if ($iso === 'ES') {
            // 5 dígitos; fuera Baleares 07, Canarias 35/38, Ceuta 51, Melilla 52.
            if (!preg_match('/^\d{5}$/', $cp) || preg_match('/^(07|35|38|51|52)/', $cp)) {
                $this->enabled = false;
                return array();
            }
        } elseif ($iso === 'PT') {
            // PT continental: primeros 4 dígitos 1000-8999; fuera Madeira/Azores (9xxx).
            $cp4 = substr(preg_replace('/\D/', '', $cp), 0, 4);
            if (!preg_match('/^[1-8]\d{3}$/', $cp4)) {
                $this->enabled = false;
                return array();
            }
        } else {
            $this->enabled = false;
            return array();
        }

        // Cada línea debe tener stock REAL de la VARIANTE pedida que CUBRA la
        // cantidad (mismo criterio que seurnacional). Sentinels excluidos: <=0 y 2000.
        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $aProduct = $order->products[$i];
            $ordered  = (float) (isset($aProduct['qty']) ? $aProduct['qty'] : 1);
            $rawId    = (string) (isset($aProduct['id']) ? $aProduct['id'] : (isset($aProduct['products_id']) ? $aProduct['products_id'] : ''));
            $baseId   = (strpos($rawId, '{') !== false) ? (int) strstr($rawId, '{', true) : (int) $rawId;

            $avail = null;
            if (strpos($rawId, '{') !== false && preg_match_all('/\{(\d+)\}(\d+)/', $rawId, $mm, PREG_SET_ORDER)) {
                $pairs = array();
                foreach ($mm as $pr) $pairs[] = $pr[1] . '-' . $pr[2];
                $comb = implode(',', $pairs);
                $rs = tep_db_query("SELECT products_stock_quantity FROM products_stock WHERE products_id = " . (int) $baseId . " AND products_stock_attributes = '" . tep_db_input($comb) . "'");
                if ($r = tep_db_fetch_array($rs)) $avail = (float) $r['products_stock_quantity'];
            }
            if ($avail === null) {
                $q = tep_db_query('SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . (int) $baseId);
                $r = tep_db_fetch_array($q);
                $avail = $r ? (float) $r['products_quantity'] : 0;
            }
            if ((int) $avail === 2000 || $avail <= 0 || $avail < $ordered) {
                $this->enabled = false;
                return array();
            }
        }

        // Precio: tarifa S-10 (zona del destino) + 20% de margen; redondeo a 0,05 sobre el CON IVA.
        $kg   = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        $zona = ($iso === 'PT') ? 'PT' : 'PEN';
        $base = self::costePorPeso($kg, $zona) * self::MARGEN * self::FUEL;

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
                'title' => MODULE_SEUR_DIEZ_TEXT_WAY,
                'cost'  => round($cost, 4),
            )),
        );
        if ($iva > 0) $this->quotes['tax'] = $iva;
        $this->quotes['icon'] = 'shipping_seur.png';

        return $this->quotes;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SEUR_DIEZ_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar SEUR 10', 'MODULE_SEUR_DIEZ_STATUS', 'False', '¿Ofrecer SEUR antes de las 10h?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SEUR_DIEZ_TAX_CLASS', '1', 'IVA aplicado al envío.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SEUR_DIEZ_SORT_ORDER', '9', 'Orden de aparición.', '6', '0', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SEUR\_DIEZ\_%'");
    }

    public function keys()
    {
        return array('MODULE_SEUR_DIEZ_STATUS', 'MODULE_SEUR_DIEZ_TAX_CLASS', 'MODULE_SEUR_DIEZ_SORT_ORDER');
    }
}
