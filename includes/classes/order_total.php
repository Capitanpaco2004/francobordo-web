<?php

class order_total
{
    public $modules;

	// class constructor
	public function __construct()
	{
		global $language;

		if (defined('MODULE_ORDER_TOTAL_INSTALLED') && tep_not_null(MODULE_ORDER_TOTAL_INSTALLED)) {
			global $customer_id, $customer_group_id;

			$defaultModules = explode(';', MODULE_ORDER_TOTAL_INSTALLED);

			$this->modules = $defaultModules;

			// Revisamos si hay totalizaciones seleccionadas especificas segun el cliente o grupo de cliente
			$customer_ot_query = tep_db_query("SELECT IF(c.customers_order_total_allowed <> '', c.customers_order_total_allowed, cg.group_order_total_allowed) as order_total_allowed FROM " . TABLE_CUSTOMERS . " c, " . TABLE_CUSTOMERS_GROUPS . " cg WHERE c.customers_id = '" . (int)$customer_id . "' AND cg.customers_group_id =  '" . (int)$customer_group_id . "'");

			if ($customer_ot = tep_db_fetch_array($customer_ot_query)) {
				if (tep_not_null($customer_ot['order_total_allowed'])) {
					$temp_ot_array = explode(';', $customer_ot['order_total_allowed']);
					$this->modules = array_intersect($defaultModules, $temp_ot_array);
				}
			}

			foreach ($this->modules as $value) {
				require_once(DIR_WS_LANGUAGES . $language . '/modules/order_total/' . $value);
				require_once(DIR_WS_MODULES . 'order_total/' . $value);

				$class = substr($value, 0, strrpos($value, '.'));
				$GLOBALS[$class] = new $class;
			}
		}
	}

    public function process($showInCart = false)
    {
		$records = array();
        $order_total_array = array();
        if (is_array($this->modules)) {
            foreach($this->modules as $value) {
                $class = substr($value, 0, strrpos($value, '.'));
                if ($GLOBALS[$class]->enabled) {
                    $GLOBALS[$class]->output = array();
                    $GLOBALS[$class]->process();

                    for ($i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++) {
                        if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                            $order_total_array[] = array('code' => $GLOBALS[$class]->code,
                                'title' => $GLOBALS[$class]->output[$i]['title'],
                                'text' => $GLOBALS[$class]->output[$i]['text'],
                                'value' => $GLOBALS[$class]->output[$i]['value'],
                                'sort_order' => $GLOBALS[$class]->sort_order);

							$records[$class][] = array('code' => $GLOBALS[$class]->code,
								'title' => $GLOBALS[$class]->output[$i]['title'],
								'text' => $GLOBALS[$class]->output[$i]['text'],
								'value' => $GLOBALS[$class]->output[$i]['value'],
								'sort_order' => $GLOBALS[$class]->sort_order);
                        }
                    }
                }
            }
        }

		return $showInCart ? $records : $order_total_array;
    }

    public function output($bTax = false, $returnHtml = true)
    {
        $output_string = '';
        $records = array();
        $exclude = ['ot_discount_coupon', 'ot_redemptions'];

        if (is_array($this->modules)) {
            foreach ($this->modules as $sValue) {
                $class = substr($sValue, 0, strrpos($sValue, '.'));

                if (!$bTax && in_array($class, array('ot_tax', 'ot_baseimponible')))
                    continue;

                if ($GLOBALS[$class]->enabled) {
                    $GLOBALS[$class]->process();
                    $aOutputs = $GLOBALS[$class]->output;

                    if (isset($GLOBALS[$class]->outputFrontend) && !isset($_GET['curl_oe']))
                        $aOutputs = $GLOBALS[$class]->outputFrontend;

                    foreach ($aOutputs as $aOutput) {
                        $sText = 'text' . (!$bTax ? '_tax' : '');

                        if (in_array($class, $exclude)) {
                            unset($aOutput['text_tax']);
                        }
                        
                        if (!array_key_exists($sText, $aOutput)) {
                            $sText = 'text';
                        }
                        
                        $output_string .= '<tr data-total="' . $class . '" data-value="' . $aOutput['value'] . '">' . "\n" .
                            '	<td align="right" class="main">' . $aOutput['title'] . '</td>' . "\n" .
                            '	<td align="right" class="main">' . $aOutput[$sText] . '</td>' . "\n" .
                            '</tr>';
                        $records[$class][] = $aOutput;
                    }
                }
            }
        }

        return $returnHtml ? $output_string : $records;
    }
}
