<?php
  class banktransfer {
    public $code;
    public $title;
    public $description;
    public $enabled;
    public $email_footer;
    public $sort_order;
    public $order_status;
    public $check;
// class constructor
    function __construct() {
      $this->code = 'banktransfer';
      $this->title = MODULE_PAYMENT_BANKTRANSFER_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_BANKTRANSFER_TEXT_DESCRIPTION;
      $this->email_footer = MODULE_PAYMENT_BANKTRANSFER_TEXT_EMAIL_FOOTER;
      $this->enabled = (( MODULE_PAYMENT_BANKTRANSFER_STATUS == 'True') ? true : false);
      $this->sort_order = MODULE_PAYMENT_BANKTRANSFER_SORT_ORDER;
      if ((int)MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID > 0) {
				$this->order_status = MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID;
			}
                  global $order;
                  if (is_object($order)) $this->update_status();
    }
    function update_status() {
    }
// class methods
    function javascript_validation() {
      return false;
    }
    function selection() {
    return array('id' => $this->code,
		 'module' => $this->title);
    }
    function pre_confirmation_check() {
      return false;
    }
 function confirmation() {
      return array('title' => '          <tr>' . "\n" . '            <td class="main">&nbsp;' . MODULE_PAYMENT_BANKTRANSFER_TEXT_DESCRIPTION . '&nbsp;</td>' . "\n" .  '          </tr>' . "\n");
    }
    function process_button() {
      return false;
    }
    function before_process() {
      return false;
    }
    function after_process() {

		global $order;

		/**
		 * #OSZ-199-64134
		 * @author Daniel Lucia <daniel.lucia@denox.es>
		 */

		if (defined('SEND_MAIL_BANKTRANSFER') && constant('SEND_MAIL_BANKTRANSFER') == 'true') {

			$id_premade_comment = 38;
			//$id_premade_comment = 47; // Usar este ID en develop. Tienen ID diferentes

			$get_premades_query = tep_db_query("SELECT title, text FROM orders_premade_comments WHERE id = $id_premade_comment");
			if (tep_db_num_rows($get_premades_query)) {
				$comments = tep_db_fetch_array($get_premades_query);
				$mail = new util\mail();
				$mail->includeEmail('various.php', array(
					'content' => $comments['text']
				));

				tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], $comments['title'], $mail->html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
			}


		}

      return false;
    }
    function output_error() {
      return false;
    }
    function check() {
      if (!isset($this->check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BANKTRANSFER_STATUS'");
        $this->check = tep_db_num_rows($check_query);
      }
      return $this->check;
    }
    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Allow Bank Transfer Payment', 'MODULE_PAYMENT_BANKTRANSFER_STATUS', 'True', 'Do you want to accept Bank Transfer Order payments?', '6', '1', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Bank Sort Code', 'MODULE_PAYMENT_BANKTRANSFER_SORTCODE', '00-00-00', 'Bank sort code in the format 00-00-00', '6', '1', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Bank Account No.', 'MODULE_PAYMENT_BANKTRANSFER_ACCNUM', '12345678', 'Bank Account No.', '6', '1', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Bank Account Name', 'MODULE_PAYMENT_BANKTRANSFER_ACCNAM', 'Fred Bloggs', 'Bank account name', '6', '1', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Bank Name', 'MODULE_PAYMENT_BANKTRANSFER_BANKNAM', 'The Bank', 'Bank Name', '6', '1', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_BANKTRANSFER_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('IBAN Number', 'MODULE_PAYMENT_BANKTRANSFER_IBAN', '00000000', 'IBAN number', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('SWIFT Number', 'MODULE_PAYMENT_BANKTRANSFER_SWIFT', '00000001', 'SWIFT number', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
}
		function remove() {
			tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
		}
    function keys() {
     	return $keys = array(
             'MODULE_PAYMENT_BANKTRANSFER_STATUS',
             'MODULE_PAYMENT_BANKTRANSFER_SORTCODE',
             'MODULE_PAYMENT_BANKTRANSFER_ACCNUM',
             'MODULE_PAYMENT_BANKTRANSFER_ACCNAM',
             'MODULE_PAYMENT_BANKTRANSFER_BANKNAM',
             'MODULE_PAYMENT_BANKTRANSFER_SORT_ORDER',
	         'MODULE_PAYMENT_BANKTRANSFER_IBAN',
	         'MODULE_PAYMENT_BANKTRANSFER_SWIFT',
             'MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID');
    }
  }
?>
