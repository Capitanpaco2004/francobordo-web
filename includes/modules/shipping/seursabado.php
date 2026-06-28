<?php
/**
 * Módulo de envío "SEUR Sábado" (servicio SEUR SATURDAY, 57/2).
 *
 * Clon del módulo seurdiez (SEUR 10). SEUR no tiene tarifa propia de sábado: lo
 * cobra como "Servicio Complementario Entrega en Sábados" = +11,42 € sobre la
 * tarifa de portes (contrato 01/11/2025). Coste = tarifa SEUR 24 / Entrega
 * Particular (Medio España Peninsular, por peso) + 11,42 €. Precio = coste × 1,30
 * (MARGEN +30%, decisión usuario). Redondeo a 0,05 sobre el importe CON IVA.
 *
 * Tarifa SEUR 24 / Entrega Particular — Medio España Peninsular (coste sin IVA):
 *   1kg 4,20 · 2kg 4,26 · 3kg 4,46 · 4kg 4,78 · 5kg 5,22 · 7kg 5,66 · 10kg 5,66
 *   15kg 6,50 · 20kg 7,47 · 25kg 8,83 · 30kg 10,20  (> 30 kg: no se ofrece)
 *
 * Solo se ofrece si:
 *   - es VIERNES (Europe/Madrid) y antes de las 15:00 (el almacén lo expide hoy
 *     para entrega mañana sábado),
 *   - destino España PENINSULAR (sin Baleares 07, Canarias 35/38, Ceuta 51,
 *     Melilla 52; NO Portugal),
 *   - peso del carrito <= 30 kg,
 *   - todas las líneas tienen stock real de la VARIANTE pedida que CUBRA la
 *     cantidad (sentinels excluidos: <=0 y 2000).
 *
 * El almacén expide estos pedidos con la agencia Vstock "SEUR SABADO" (el watcher
 * los manda al endpoint con svc=sabado -> servicio 57 / producto 2).
 * Ver memoria francobordo_seur_api.
 */
class seursabado
{
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    /* Margen sobre el coste SEUR (1.30 = +30%). */
    const MARGEN = 1.30;
    const FUEL = 1.1654;  // sobrecoste fuel SEUR 16,54% repercutido al cliente (2026-06-25)

    /* Complemento "Entrega en Sábado" (contrato 01/11/2025), sin IVA. */
    const COMPLEMENTO_SABADO = 11.42;

    /* Peso máximo del envío (kg). */
    const MAX_KG = 30;

    /* Ventana de oferta: SOLO viernes (5) y hasta las 15:00. */
    const DIA_OFERTA = 5;    // 1=lunes .. 5=viernes .. 7=domingo
    const HORA_HASTA = 15;   // hasta las 14:59 (a las 15:00 deja de ofrecerse)

    public function __construct()
    {
        $this->code        = 'seursabado';
        $this->title       = MODULE_SEUR_SABADO_TEXT_TITLE;
        $this->description = MODULE_SEUR_SABADO_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SEUR_SABADO_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'seur.png';
        $this->tax_class   = MODULE_SEUR_SABADO_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SEUR_SABADO_STATUS') && MODULE_SEUR_SABADO_STATUS == 'True');

        // Solo viernes y antes de las 15:00 (Europe/Madrid). Fuera de esa ventana
        // no se ofrece: solo tiene sentido pedir hoy (viernes) para entregar mañana (sábado).
        if ($this->enabled) {
            $dia  = (int) date('N');   // 1=lunes .. 7=domingo
            $hora = (int) date('G');
            if ($dia !== self::DIA_OFERTA || $hora >= self::HORA_HASTA) {
                $this->enabled = false;
            }
        }
    }

    /** Tarifa SEUR 24 / Entrega Particular (Medio España Peninsular), sin IVA, por peso. */
    public static function tarifa24h($kg)
    {
        $kg = (float) $kg;
        if ($kg <= 0) $kg = 1;
        $kg = (int) ceil($kg);
        $tramos = array(1 => 4.20, 2 => 4.26, 3 => 4.46, 4 => 4.78, 5 => 5.22,
                        7 => 5.66, 10 => 5.66, 15 => 6.50, 20 => 7.47, 25 => 8.83, 30 => 10.20);
        foreach ($tramos as $max => $rate) {
            if ($kg <= $max) return $rate;
        }
        return null;   // > 30 kg: no aplica
    }

    /** Coste SEUR Sábado (sin IVA, sin margen) por peso: tarifa 24h + complemento. */
    public static function costePorPeso($kg)
    {
        $t = self::tarifa24h($kg);
        if ($t === null) return null;
        return $t + self::COMPLEMENTO_SABADO;
    }

    public function quote($method = '')
    {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: España PENINSULAR (sin islas, Ceuta, Melilla; NO Portugal).
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = preg_replace('/\s+/', '', (string) $order->delivery['postcode']);
        if ($iso !== 'ES' || !preg_match('/^\d{5}$/', $cp) || preg_match('/^(07|35|38|51|52)/', $cp)) {
            $this->enabled = false;
            return array();
        }

        // Peso máximo 30 kg.
        $kg = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        if ($kg > self::MAX_KG) {
            $this->enabled = false;
            return array();
        }

        // Cada línea debe tener stock REAL de la VARIANTE pedida que CUBRA la
        // cantidad (mismo criterio que seurnacional/seurdiez). Sentinels: <=0 y 2000.
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

        // Precio: (tarifa 24h + 11,42) * 1,30; redondeo a 0,05 sobre el CON IVA.
        $base = self::costePorPeso($kg);
        if ($base === null) { $this->enabled = false; return array(); }
        $base = $base * self::MARGEN * self::FUEL;

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
                'title' => MODULE_SEUR_SABADO_TEXT_WAY,
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
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SEUR_SABADO_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar SEUR Sábado', 'MODULE_SEUR_SABADO_STATUS', 'False', '¿Ofrecer SEUR Sábado (solo viernes hasta 15:00)?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SEUR_SABADO_TAX_CLASS', '1', 'IVA aplicado al envío.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SEUR_SABADO_SORT_ORDER', '10', 'Orden de aparición.', '6', '0', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SEUR\_SABADO\_%'");
    }

    public function keys()
    {
        return array('MODULE_SEUR_SABADO_STATUS', 'MODULE_SEUR_SABADO_TAX_CLASS', 'MODULE_SEUR_SABADO_SORT_ORDER');
    }
}
