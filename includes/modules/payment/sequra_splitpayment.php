<?php
/*
   SeQura Payment Gateway
   Copyright (c) 2020 SeQura WorldWide SL
 */
if(!class_exists('sequra')){
	include_once(DIR_FS_CATALOG . 'includes/modules/payment/sequra.php');
}
define('MODULE_PAYMENT_SEQURA_SP_MAX_AMOUNT','2500');
define('MODULE_PAYMENT_SEQURA_SP_MIN_AMOUNT','50');

class sequra_splitpayment extends sequra {
	const MORE_INFO =
	'<span
		class="sequra-educational-popup"
		data-amount="%d"
		data-product="sp1"
  	></span>';
	const SQ_PRODUCT = 'sp1';

	const VERSION         = '1.0.0';

	public $signature;
	public $api_version;
	public $code;
	public $title;
	public $public_title;
	public $description;
	public $cost_url;
	public $mode;
	public $sort_order;
	public $max_amount;
	public $_check;

	function __construct() {
		parent::__construct();
		$this->signature   = 'sequra_splitpayment|sequra|' . self::VERSION . '|2.4';
		$this->api_version = '1.0.0';

		$this->code                            = 'sequra_splitpayment';
		$this->title                           = 'SeQura - Divide en 3';
		// FIX 2026-05-29 payment_method vacio: fallback no vacio si falta la constante de idioma
		$this->public_title                    = defined('MODULE_PAYMENT_SEQURA_SPLITPAYMENT_TEXT_PUBLIC_TITLE')?MODULE_PAYMENT_SEQURA_SPLITPAYMENT_TEXT_PUBLIC_TITLE:'SeQura - Divide en 3';
		$this->description                     = 'SeQura - Divide en 3';
		$this->cost_url  				       = defined('MODULE_PAYMENT_SEQURA_SPLITPAYMENT_COST_URL')?MODULE_PAYMENT_SEQURA_SPLITPAYMENT_COST_URL:'';
		$this->mode                            = self::SQ_PRODUCT;
		$this->sort_order                      = 8;
		$this->max_amount					   = 250000;
	}

	function isEnabled() {
		$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SEQURA_SPLITPAYMENT_STATUS'");
		return !!tep_db_num_rows($check_query);
	}

	static function isAmountInRange($amount) {
		$toohigh = is_numeric(MODULE_PAYMENT_SEQURA_SP_MAX_AMOUNT) && MODULE_PAYMENT_SEQURA_SP_MAX_AMOUNT > 0 && $amount > MODULE_PAYMENT_SEQURA_SP_MAX_AMOUNT;
		$toolow  = is_numeric(MODULE_PAYMENT_SEQURA_SP_MIN_AMOUNT) && MODULE_PAYMENT_SEQURA_SP_MIN_AMOUNT > 0 && $amount < MODULE_PAYMENT_SEQURA_SP_MIN_AMOUNT;
		return !is_null($amount) && !$toohigh && !$toolow;
	}

	function getOptionsForIdentityFrom(){
		return array(
			'product' => $this->mode,
		);
	}

	function install() {
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar m&oacute;dulo SeQura: Divide en 3', 'MODULE_PAYMENT_SEQURA_SPLITPAYMENT_STATUS', 'True', '&iquest;Quiere activar la campaña?', '6', '10', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
	}

	function keys() {
		return array
		(
			'MODULE_PAYMENT_SEQURA_SPLITPAYMENT_STATUS',
			'MODULE_PAYMENT_SEQURA_SPLITPAYMENT_CODE'
		);
	}

	function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function getSuffix() {
		return '_splitpayment';
	}

	function getTemplateVars( $filename = 'sequrasplitpayment' ) {
		$vars = parent::getTemplateVars($filename);
		$vars['product'] = self::SQ_PRODUCT;
		$vars['max-amount'] = $this->max_amount;
		return $vars;
	}

	static function drawTeaser() {
		$instance = new sequra_splitpayment();
		if(!self::allowedIp() || !$instance->isEnabled()) return;
		$vars = $instance->getTemplateVars();
		echo SequraHelper::render('splitpayment_teaser',$vars);
	}
	
	public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SEQURA_SPLITPAYMENT_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }
}
