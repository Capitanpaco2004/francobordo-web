<?php
/*
   SeQura Payment GateWay
   Copyright (c) 2014 SeQura WorldWide SL
 */
if(!class_exists('sequra')){
	include_once(DIR_FS_CATALOG . 'includes/modules/payment/sequra.php');
}
class sequra_pp extends sequra {
	const MORE_INFO = '<span id="sequra_partpayments_method_link" class="sequra_popup_trigger sequra_more_info" rel="sequra_partpayments_popup_checkout" title="Más información"><span class="icon-info"></span></span>';

	public $signature;
	public $api_version;
	public $code;
	public $title;
	public $public_title;
	public $description;
	public $text_confirmation_other_methods;
	public $text_confirmation_header;
	public $text_confirmation_title;
	public $public_description;
	public $pp_cost_url;
	public $mode;
	public $sort_order;

	function __construct() {
		global $order;
		parent::__construct();
		$this->signature   = 'sequra_pp|sequra|' . self::VERSION . '|3.0.2';
		$this->api_version = '1.0.0';

		$this->code                            = 'sequra_pp';
		$this->title                           = defined('MODULE_PAYMENT_SEQURA_PP_TEXT_TITLE')?MODULE_PAYMENT_SEQURA_PP_TEXT_TITLE:'';
		$this->public_title                    = defined('MODULE_PAYMENT_SEQURA_PP_TEXT_PUBLIC_TITLE')?MODULE_PAYMENT_SEQURA_PP_TEXT_PUBLIC_TITLE:'';
		$this->description                     = defined('MODULE_PAYMENT_SEQURA_PP_TEXT_DESCRIPTION')?MODULE_PAYMENT_SEQURA_PP_TEXT_DESCRIPTION:'';
		$this->text_confirmation_other_methods = defined('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_OTHER_METHODS')?MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_OTHER_METHODS:'';
		$this->text_confirmation_header        = defined('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_HEADER')?MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_HEADER:'';
		//$this->text_confirmation_title         = defined(MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_TITLE)?'':'';
		$this->public_description              = defined('MODULE_PAYMENT_SEQURA_PP_TEXT_PUBLIC_DESCRIPTION')?MODULE_PAYMENT_SEQURA_PP_TEXT_PUBLIC_DESCRIPTION:'';
		$this->pp_cost_url				       = defined('MODULE_PAYMENT_SEQURA_PP_COST_URL')?MODULE_PAYMENT_SEQURA_PP_COST_URL:'';
		$this->mode                            = 'pp3';
		$this->sort_order                      = MODULE_PAYMENT_SEQURA_SORT_ORDER + 1;
		if ( is_object( $order ) ) {
			$this->update_status();
		}
	}

	function update_status() {
	}

	static function isAmountInRange($amount) {
		$toohigh = is_numeric(MODULE_PAYMENT_SEQURA_PP_MAX_AMOUNT) && MODULE_PAYMENT_SEQURA_PP_MAX_AMOUNT > 0 && $amount > MODULE_PAYMENT_SEQURA_PP_MAX_AMOUNT;
		$toolow  = is_numeric(MODULE_PAYMENT_SEQURA_PP_MIN_AMOUNT) && MODULE_PAYMENT_SEQURA_PP_MIN_AMOUNT > 0 && $amount < MODULE_PAYMENT_SEQURA_PP_MIN_AMOUNT;
		return !is_null($amount) && !$toohigh && !$toolow;
	}

	function check() {
		return $this->allowedIp() &&
				$this->enabled &&
				$this->isOrderAmountInRange();
	}

	function install() {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar m&oacute;dulo SeQura: Pagos fraccionados', 'MODULE_PAYMENT_SEQURA_PP_STATUS', 'True', '&iquest;Quiere aceptar pagos fraccionados?', '6', '10', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Importe m&aacute;ximo', 'MODULE_PAYMENT_SEQURA_PP_MAX_AMOUNT', '10000', 'Importe m&aacute;ximo para los pedidos a tramitar por Pagos fraccionados con SeQura', '6', '110', '', '', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Importe m&iacute;nimo', 'MODULE_PAYMENT_SEQURA_PP_MIN_AMOUNT', '50', 'Importe m&iacute;nimo para los pedidos a tramitar por Pagos fraccionados con SeQura', '6', '120', '', '', now())");
	}

	function keys() {
		return array
		(
			'MODULE_PAYMENT_SEQURA_PP_STATUS',
			'MODULE_PAYMENT_SEQURA_PP_MAX_AMOUNT',
			'MODULE_PAYMENT_SEQURA_PP_MIN_AMOUNT',
		);
	}

	function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function getSuffix() {
		return '_' . $this->mode;
	}

	static function drawTeaser() {
		$instance = new sequra_pp();
		if(!self::allowedIp() || !$instance->isEnabled()) return;
		$vars = $instance->getTemplateVars();
		echo SequraHelper::render('partpayment_teaser',$vars);
	}
}
