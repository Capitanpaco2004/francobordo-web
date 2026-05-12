<?php
class UpsExports {
	const EXPORT_WORLDSHIP = 1;
    const EXPORT_UPS = 2;
    const EXPORT_PDF = 3;
    
    public $id_export;
	public $orders;
	public $date_add;
	public $type;
	public $image;
	
	public function __construct($id_export = 0){
		if($id_export){
			$this->id_export = (int) $id_export;
			$this->load();
		}
	}
	
	private function load(){
		$query = tep_db_query('SELECT * FROM `ups_exports` WHERE `id_export` = '.(int) $this->id_export);
		$result = tep_db_fetch_array($query);
		$this->orders = explode(';', $result['orders']);
		$this->date_add = $result['date_add'];
		$this->type = $result['type'];
		$this->image = $result['image'];
	}
	
	public function save(){
		$result = false;
		if($this->id_export){
			//update
			$result = tep_db_query("update `ups_exports` SET 
				orders = '".implode(';', $this->orders) ."', 
				date_add = '". $this->date_add ."', 
				type = '". $this->type ."', 
				image = '". $this->image ."'
				WHERE id_export = ".(int) $this->id_export);
		}
		else{
			//insert
			$result = tep_db_query("INSERT INTO `ups_exports` (orders, date_add, type, image) 
				VALUES (
					'".implode(';', $this->orders) ."',
					'".$this->date_add."',
					'".$this->type."',
					'".$this->image."'
				)");
			if($result)
				$this->id_export = tep_db_insert_id();
		}
		return $result;
	}
	
	public function delete(){
		$result = tep_db_query("DELETE FROM `ups_exports` WHERE `id_export` = ".(int) $this->id_export);
		return $result;
	}
	
/**
	 * getExportablesOrders get exportables orders for UPS
	 *
	 * @return array
	 */
	
	public static function getExportablesOrders()
	{
		$orders = array();
		$order_ids = self::getPastsOrdersIds();
		$query = tep_db_query('SELECT * FROM ' . TABLE_ORDERS . ' o 
			INNER JOIN ' . TABLE_ORDERS_TOTAL . ' ot ON (o.`orders_id` = ot.`orders_id`) 
			LEFT JOIN `ups_selected_service` uss ON (uss.`id_order` = o.`orders_id`) 
			LEFT JOIN `ups_services` us ON (us.`id_ups_service` = uss.`id_ups_service`) 			
			WHERE (`title` LIKE "%UPS%" OR `title` LIKE "%Kiala%") 
			AND ot.`class` = "ot_shipping" 
			AND o.`orders_status` = '. (int) Tools::getConfigValue('MODULE_SHIPPING_UPS_EXPORTABLE_ORDER_STATE').
			((!empty($order_ids)) ? ' AND o.`orders_id` NOT IN ('. implode(',', $order_ids).')' : '').
			' ORDER BY o.`orders_id` DESC');
		
		while ($order = tep_db_fetch_array($query)) {
		 	$orders[] = $order;
		}
		return $orders;
	}
	/**
	 * getExportablesOrdersId get exportables orders for UPS
	 *
	 * @return array
	 */
	
	public static function getExportablesOrdersId()
	{
		$orders = array();
		$order_ids = self::getPastsOrdersIds();
		$query = tep_db_query('SELECT o.`orders_id` FROM ' . TABLE_ORDERS . ' o 
			INNER JOIN ' . TABLE_ORDERS_TOTAL . ' ot ON (o.`orders_id` = ot.`orders_id`) 
			LEFT JOIN `ups_selected_service` uss ON (uss.`id_order` = o.`orders_id`) 
			LEFT JOIN `ups_services` us ON (us.`id_ups_service` = uss.`id_ups_service`) 			
			WHERE (`title` LIKE "%UPS%" OR `title` LIKE "%Kiala%") 
			AND ot.`class` = "ot_shipping" 
			AND o.`orders_status` = '. (int) Tools::getConfigValue('MODULE_SHIPPING_UPS_EXPORTABLE_ORDER_STATE').
			((!empty($order_ids)) ? ' AND o.`orders_id` NOT IN ('. implode(',', $order_ids).')' : '').
			' ORDER BY o.`orders_id` DESC');
		
		while ($order = tep_db_fetch_array($query)) {
		 	$orders[] = $order['orders_id'];
		}
		return $orders;
	}
	
	public static function getPastsOrdersIds()
	{
		$order_ids = array();
		$query = tep_db_query('SELECT orders FROM `ups_exports`');
		while ($export = tep_db_fetch_array($query)) {
			$export_orders = explode(';', $export['orders']);
			foreach($export_orders as $export_order){
				if((int)$export_order)
					$order_ids[] = (int)$export_order;
			}
		}
		if(!empty($order_ids))
			$order_ids = array_unique($order_ids);
		return $order_ids;
	}
	
	public static function getPastsOrders()
	{
		$past_orders = array();
		$order_ids = self::getPastsOrdersIds();
		
		if(!empty($order_ids)){
			$query = tep_db_query('SELECT * FROM ' . TABLE_ORDERS . ' o 
				INNER JOIN ' . TABLE_ORDERS_TOTAL . ' ot ON (o.`orders_id` = ot.`orders_id`) 
				LEFT JOIN `ups_selected_service` uss ON (uss.`id_order` = o.`orders_id`) 
				LEFT JOIN `ups_services` us ON (us.`id_ups_service` = uss.`id_ups_service`) 			
				WHERE (`title` LIKE "%UPS%" OR `title` LIKE "%Kiala%") 
				AND ot.`class` = "ot_shipping" 
				AND o.`orders_id` IN ('. implode(',', $order_ids).')
				ORDER BY o.`orders_id` ASC');
			
			while ($order = tep_db_fetch_array($query)) {
			 	$past_orders[] = $order;
			}
		}
		return $past_orders;
	}
}