<?php
/**
 * Módulo de envío "Correos Express - Entrega en Sábado".
 *
 * Clona la lógica del módulo seursabado, adaptada a Correos Express:
 *  - Solo se ofrece en VIERNES (Europe/Madrid) antes de las 15:00 (el almacén lo
 *    expide hoy para entrega mañana sábado).
 *  - Destino España PENINSULAR (sin Baleares 07, Canarias 35/38, Ceuta 51,
 *    Melilla 52; no Portugal).
 *  - Peso del carrito <= 30 kg.
 *  - Todas las líneas con stock real de la VARIANTE pedida que cubra la cantidad
 *    (sentinels excluidos: <=0 y 2000).
 *
 * PRECIO = tarifa Correos Express DOMICILIO (módulo tipsa, por zona/peso) + 6,00 €
 * sobre el precio FINAL con IVA (decisión usuario 2026-06-25). CEX cobra +3 € de
 * suplemento "Entrega en Sábado"; el +6 € es el cargo al cliente.
 *
 * ⚠️ La etiqueta de SALIDA la genera el watcher del almacén (.112), NO la web: para
 * que el envío lleve entrSabado=S hay que marcarlo allí según
 * orders.shipping_module = 'tipsawednesday_tipsawednesday'. Ver memoria
 * francobordo_correos_express_api.
 */
  class tipsawednesday {
    var $code, $title, $description, $enabled, $num_zones, $sort_order, $icon, $tax_class, $_check, $quotes;

    const MAX_KG        = 30;
    const DIA_OFERTA    = 5;     // viernes (1=lunes .. 7=domingo)
    const HORA_HASTA    = 15;    // hasta las 14:59
    const RECARGO_SABADO = 6.00; // € añadidos al precio FINAL (con IVA) del domicilio

    function __construct() {
      $this->code        = 'tipsawednesday';
      $this->title       = MODULE_TIPSA_WEDNESDAY_TEXT_TITLE;
      $this->description = MODULE_TIPSA_WEDNESDAY_TEXT_DESCRIPTION;
      $this->sort_order  = MODULE_TIPSA_WEDNESDAY_SORT_ORDER;
      $this->icon        = DIR_WS_ICONS . 'gls.png';
      $this->tax_class   = MODULE_TIPSA_WEDNESDAY_TAX_CLASS;
      $this->enabled     = ((MODULE_TIPSA_WEDNESDAY_STATUS == 'True') ? true : false);
      $this->num_zones   = 9;    // sin uso en el cálculo; se mantiene por compatibilidad de keys()

      // Solo viernes y antes de las 15:00 (igual que seursabado): solo tiene sentido
      // pedir hoy (viernes) para entregar mañana (sábado).
      if ($this->enabled) {
        $dia  = (int) date('N');
        $hora = (int) date('G');
        if ($dia !== self::DIA_OFERTA || $hora >= self::HORA_HASTA) $this->enabled = false;
      }
    }

    function quote($method = '') {
      global $order, $cart, $shipping_weight;

      if (!$this->enabled) return array();

      // Destino: España PENINSULAR (sin islas, Ceuta, Melilla; no Portugal).
      $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
      $cp  = preg_replace('/\s+/', '', (string) $order->delivery['postcode']);
      if ($iso !== 'ES' || !preg_match('/^\d{5}$/', $cp) || preg_match('/^(07|35|38|51|52)/', $cp)) {
        $this->enabled = false;
        return array();
      }

      // Peso máximo 30 kg.
      $kg = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
      if ($kg > self::MAX_KG) { $this->enabled = false; return array(); }

      // Cada línea debe tener stock REAL de la VARIANTE pedida que cubra la cantidad
      // (mismo criterio que seursabado/seurnacional). Sentinels: <=0 y 2000.
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

      // Precio base = Correos Express DOMICILIO (módulo tipsa) para este MISMO pedido.
      require_once DIR_WS_MODULES . 'shipping/tipsa.php';
      $oTipsa = new tipsa();
      $aTipsa = $oTipsa->quote();
      if (!is_array($aTipsa) || isset($aTipsa['error']) || empty($aTipsa['methods'][0]) || !isset($aTipsa['methods'][0]['cost'])) {
        // Sin tarifa de domicilio para este destino -> no ofrecer sábado.
        $this->enabled = false;
        return array();
      }
      $baseCost        = (float) $aTipsa['methods'][0]['cost'];     // domicilio, sin IVA
      $baseTax         = (float) ($aTipsa['tax'] ?? 0);            // 21
      $baseFinalConIva = $baseCost * (1 + $baseTax / 100);        // precio final domicilio con IVA

      // Sábado = domicilio + 6 € (sobre el final con IVA), redondeo a 0,05.
      $iva = ($this->tax_class > 0)
        ? tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])
        : 0;
      $finalConIva      = $baseFinalConIva + self::RECARGO_SABADO;
      $finalConIvaRound = round($finalConIva / 0.05) * 0.05;
      $cost = ($iva > 0) ? ($finalConIvaRound / (1 + $iva / 100)) : $finalConIvaRound;

      $this->quotes = array(
        'id'      => $this->code,
        'module'  => $this->title,
        'methods' => array(array(
          'id'    => $this->code,
          'title' => MODULE_TIPSA_WEDNESDAY_TEXT_WAY,
          'cost'  => round($cost, 4),
        )),
      );
      if ($iva > 0) $this->quotes['tax'] = $iva;
      $this->quotes['icon'] = 'shipping_correos_exp.png';

      return $this->quotes;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_TIPSA_WEDNESDAY_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar Correos Express Sábado', 'MODULE_TIPSA_WEDNESDAY_STATUS', 'True', '¿Ofrecer Correos Express - Entrega en Sábado (solo viernes hasta 15:00)?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo Impuesto', 'MODULE_TIPSA_WEDNESDAY_TAX_CLASS', '1', 'IVA aplicado al envío.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden Visualizaci&oacute;n', 'MODULE_TIPSA_WEDNESDAY_SORT_ORDER', '0', 'El menor se visualiza primero.', '6', '0', now())");
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\\_TIPSA\\_WEDNESDAY\\_%'");
    }

    function keys() {
      $keys = array('MODULE_TIPSA_WEDNESDAY_STATUS', 'MODULE_TIPSA_WEDNESDAY_TAX_CLASS', 'MODULE_TIPSA_WEDNESDAY_SORT_ORDER');
      for ($i = 1; $i <= $this->num_zones; $i++) {
        $keys[] = 'MODULE_TIPSA_WEDNESDAY_COUNTRIES_' . $i;
        $keys[] = 'MODULE_TIPSA_WEDNESDAY_COST_' . $i;
        $keys[] = 'MODULE_TIPSA_WEDNESDAY_HANDLING_' . $i;
      }
      return $keys;
    }
  }
