<?php
class UpsAccount {
	public $id_ups_account;
	public $account_name;
	public $account_number;
	public $shipper_name;
	public $shipper_attention_name;
	public $dni_number;
	public $phone_number;
	public $address_line_1;
	public $address_line_2;
	public $id_country;
	public $state;
	public $city;
	public $postal_code;
	public $is_ups_ape;
	public $pickup_type;
	public $date_add;
	public $date_upd;
	
	public function __construct($id_ups_account = 0){
		if($id_ups_account){
			$this->id_ups_account = (int) $id_ups_account;
			$this->load();
		}
	}
	
	private function load(){
		$query = tep_db_query('SELECT * FROM `ups_account` WHERE `id_ups_account` = '.(int) $this->id_ups_account);
		$result = tep_db_fetch_array($query);
		$this->account_name = $result['account_name'];
		$this->account_number = $result['account_number'];
		$this->shipper_name = $result['shipper_name'];
		$this->shipper_attention_name = $result['shipper_attention_name'];
		$this->dni_number = $result['dni_number'];
		$this->phone_number = $result['phone_number'];
		$this->address_line_1 = $result['address_line_1'];
		$this->address_line_2 = $result['address_line_2'];
		$this->id_country = (int) $result['id_country'];
		$this->state = $result['state'];
		$this->city = $result['city'];
		$this->postal_code = $result['postal_code'];
		$this->is_ups_ape = (int) $result['is_ups_ape'];
		$this->pickup_type = $result['pickup_type'];
		$this->date_add = $result['date_add'];
		$this->date_upd = $result['date_upd'];
	}
	
	public function save(){
		$result = false;
		if($this->id_ups_account){
			//update
			$result = tep_db_query("update `ups_account` SET 
				account_name = '". $this->account_name ."', 
				account_number = '". $this->account_number ."', 
				shipper_name = '". $this->shipper_name ."', 
				shipper_attention_name = '". $this->shipper_attention_name ."', 
				dni_number = '". $this->dni_number ."', 
				phone_number = '". $this->phone_number ."', 
				address_line_1 = '". $this->address_line_1 ."', 
				address_line_2 = '". $this->address_line_2 ."', 
				id_country = ". (int) $this->id_country .", 
				state = '". $this->state ."', 
				city = '". $this->city ."', 
				postal_code = '". $this->postal_code ."', 
				is_ups_ape = '". $this->is_ups_ape ."', 
				pickup_type = '". $this->pickup_type ."', 
				date_add = '". $this->date_add ."', 
				date_upd = now()
				WHERE id_ups_account = ".(int) $this->id_ups_account);
		}
		else{
			//insert
			$result = tep_db_query("INSERT INTO `ups_account` (account_name, account_number, shipper_name, shipper_attention_name, dni_number, phone_number, address_line_1, address_line_2, id_country, state, city, postal_code, is_ups_ape, pickup_type, date_add, date_upd) 
				VALUES (
					'".	$this->account_name."',
					'".$this->account_number."',
					'".$this->shipper_name."',
					'".$this->shipper_attention_name."',
					'".$this->dni_number."',
					'".$this->phone_number."',
					'".$this->address_line_1."',
					'".$this->address_line_2."',
					'".(int) $this->id_country."',
					'".$this->state."',
					'".$this->city."',
					'".$this->postal_code."',
					'".$this->is_ups_ape."',
					'".$this->pickup_type."',
					now(),
					now())");
		}
		
		if($this->is_ups_ape){
			tep_db_query("update `ups_account` SET is_ups_ape = 0 WHERE id_ups_account <> ".(int) $this->id_ups_account);
		}
		return $result;
	}
	
	public static function getAccounts(){
		$accounts = array();
		$query = tep_db_query('SELECT * FROM `ups_account`');
		while ($account = tep_db_fetch_array($query)) {
		 	$accounts[] = new objectInfo($account);
		}
		return $accounts;
	}
	
	public static function getAccountsList(){
		$accounts = array();
		$accounts[] = array('id' => '-1', 'text' => UPS_ACCOUNT_SELECT_ACCOUNT);
		$query = tep_db_query('SELECT `id_ups_account` AS id, `account_name` AS text FROM `ups_account`');
		while ($account = tep_db_fetch_array($query)) {
		 	$accounts[] = $account;
		}
		return $accounts;
	}
	
	public static function getUPSPickupTypes(){
		return array(
            '01' => array(
                'name' => UPS_PICKUP,
            ),
            '02' => array(
                'name' => UPS_OCCASIONAL,
            ),
        );
	}
	
	public static function getUPSPickupTypesList(){
		return array(
			array('id' => '01', 'text' =>UPS_PICKUP),
			array('id' => '02', 'text' =>UPS_OCCASIONAL),
        );
	}
}