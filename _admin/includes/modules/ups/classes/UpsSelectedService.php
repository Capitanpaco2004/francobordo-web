<?php
class UpsSelectedService {
	public $id_ups_selected_service;
  	public $id_cart;
  	public $id_order;
  	public $id_ups_service;
  	public $location_id;
  	public $public_access_point_id;
  	public $name;
 	public $address;
  	public $postal_code;
  	public $city;
  	public $country_code;
  	public $declared_value;
  	public $access_point_cod;
  	public $to_addressee_only;
  	public $signature;
  	public $order_weight;
  	public $order_amount;
  	public $deleted;
	
	public function __construct($id_ups_selected_service = 0){
		if($id_ups_selected_service){
			$this->id_ups_selected_service = (int) $id_ups_selected_service;
			$this->load();
		}
	}
	
	private function load(){
		$query = tep_db_query('SELECT * FROM `ups_selected_service` WHERE `id_ups_selected_service` = '.(int) $this->id_ups_selected_service);
		$result = tep_db_fetch_array($query);
		$this->id_cart = (int) $result['id_cart'];
	  	$this->id_order = (int) $result['id_order'];
	  	$this->id_ups_service = (int) $result['id_ups_service'];
	  	$this->location_id = $result['location_id'];
	  	$this->public_access_point_id = $result['public_access_point_id'];
	  	$this->name = $result['name'];
	 	$this->address = $result['address'];
	  	$this->address = $result['address'];
	  	$this->city = $result['city'];
	  	$this->country_code = $result['country_code'];
	  	$this->declared_value = $result['declared_value'];
	  	$this->access_point_cod = $result['access_point_cod'];
	  	$this->to_addressee_only = $result['to_addressee_only'];
	  	$this->signature = $result['signature'];
	  	$this->order_weight = (float) $result['order_weight'];
	  	$this->order_amount = (float) $result['order_amount'];
	  	$this->deleted = $result['deleted'];
	}
	
	public function save(){
		$result = false;
		if($this->id_ups_selected_service){
			//update
			$result = tep_db_query("update `ups_selected_service` SET 
				`id_cart` = ".(int) $this->id_cart .", 
				`id_order` = ".(int) $this->id_order .", 
				`id_ups_service` = ".(int) $this->id_ups_service .", 
				`location_id` = ".(int) $this->location_id .", 
				`public_access_point_id` = '".$this->public_access_point_id ."', 
				`name` = '". $this->name ."', 
				`address` = '". $this->address ."', 
				`postal_code` = '". $this->postal_code ."', 
				`city` = '". $this->city ."', 
				`country_code` = '". $this->country_code ."', 
				`declared_value` = ".(int) $this->declared_value .", 
				`access_point_cod` = ".(int) $this->access_point_cod .", 
				`to_addressee_only` = ".(int) $this->to_addressee_only .", 
				`signature` = ".(int) $this->signature .",
				`order_weight` = ".(float) $this->order_weight .",
				`order_amount` = ".(float) $this->order_amount .",
				`deleted` = ". $this->deleted ."			
				WHERE id_ups_selected_service = ".(int) $this->id_ups_selected_service);
		}
		else{
			//insert
			$result = tep_db_query("INSERT INTO `ups_selected_service` (`id_cart`, `id_order`, `id_ups_service`, `location_id`, `public_access_point_id`, `name`, `address`, `postal_code`, `city`, `country_code`, `declared_value`, `access_point_cod`, `to_addressee_only`, `signature`, `order_weight`, `order_amount`, `deleted`) 
				VALUES (
					".(int) $this->id_cart.",
					".(int) $this->id_order.",
  					".(int) $this->id_ups_service.",
  					".(int) $this->location_id.",
  					'".$this->public_access_point_id."',
				  	'".$this->name."',
				 	'".$this->address."',
				  	'".$this->postal_code."',
				  	'".$this->city."',
				  	'".$this->country_code."',
				  	'".$this->declared_value."',
				  	'".$this->access_point_cod."',
				  	".(int) $this->to_addressee_only.",
				  	".(int) $this->signature.",
				  	".(float) $this->order_weight.",
				  	".(float) $this->order_amount.",
				  	".(int) $this->deleted."
				)");
		}
		return $result;
	}
	
	public function delete(){
		$result = tep_db_query("UPDATE `ups_selected_service` SET `deleted` = 1 WHERE `id_ups_selected_service` = ".(int) $this->id_selected_ups_service);
		return $result;
	}
	
	public function getService(){
		return new UpsService((int) $this->id_ups_service);
	}
	
	public static function getServices(){
		$selected_service = array();
		$query = tep_db_query('SELECT * FROM `ups_selected_service` WHERE `deleted` = 0');
		while ($selected_service = tep_db_fetch_array($query)) {
		 	$selected_service[] = new objectInfo($selected_service);
		}
		return $selected_service;
	}
	
	public static function getSelectedServiceByCartID($id_cart){
		if(!$id_cart)
			return false;
		$query = tep_db_query('SELECT `id_ups_selected_service` FROM `ups_selected_service` WHERE `id_cart` = '.(int)$id_cart.' AND `deleted` = 0');
		$result = tep_db_fetch_array($query);
		if(isset($result['id_ups_selected_service'])){
			return new UpsSelectedService((int) $result['id_ups_selected_service']);
		}
		return false;
	}
	
	public static function getSelectedServiceByOrderID($id_order){
		if(!$id_order)
			return false;
		$query = tep_db_query('SELECT `id_ups_selected_service` 
			FROM `ups_selected_service` 
			WHERE `id_order` = '.(int)$id_order.' AND `deleted` = 0');
		$result = tep_db_fetch_array($query);
		if(isset($result['id_ups_selected_service'])){
			return new UpsSelectedService((int) $result['id_ups_selected_service']);
		}
		return false;
	}
}