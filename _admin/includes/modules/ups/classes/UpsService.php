<?php
class UpsService {
	public $id_ups_service;
	public $id_ups_account;
	public $service_code;
	public $service_name;
	public $dest_countries;
	
	public function __construct($id_ups_service = 0){
		if($id_ups_service){
			$this->id_ups_service = (int) $id_ups_service;
			$this->load();
		}
	}
	
	private function load(){
		$query = tep_db_query('SELECT * FROM `ups_services` WHERE `id_ups_service` = '.(int) $this->id_ups_service);
		$result = tep_db_fetch_array($query);
		$this->id_ups_account = $result['id_ups_account'];
		$this->service_code = $result['service_code'];
		$this->service_name = $result['service_name'];
		$this->dest_countries = $result['dest_countries'];
	}
	
	public function save(){
		$result = false;
		if($this->id_ups_service){
			//update
			$result = tep_db_query("update `ups_services` SET 
				id_ups_account = ".(int) $this->id_ups_account .", 
				service_code = '". $this->service_code ."', 
				service_name = '". $this->service_name ."', 
				dest_countries = '". $this->dest_countries ."'
				WHERE id_ups_service = ".(int) $this->id_ups_service);
		}
		else{
			//insert
			$result = tep_db_query("INSERT INTO `ups_services` (id_ups_account, service_code, service_name, dest_countries) 
				VALUES (
					".(int) $this->id_ups_account.",
					'".$this->service_code."',
					'".$this->service_name."',
					'".$this->dest_countries."'
				)");
		}
		return $result;
	}
	
	public function delete(){
		$result = tep_db_query("DELETE FROM `ups_services` WHERE `id_ups_service` = ".(int) $this->id_ups_service);
		if($result)
			$result = tep_db_query("UPDATE `ups_selected_service` SET `deleted` = 1 WHERE `id_ups_service` = ".(int) $this->id_ups_service);
		return $result;
	}
	
	public function getAccount(){
		return new UpsAccount((int) $this->id_ups_account);
	}
	public static function getServices(){
		$services = array();
		$query = tep_db_query('SELECT * FROM `ups_services`');
		while ($service = tep_db_fetch_array($query)) {
		 	$services[] = new objectInfo($service);
		}
		return $services;
	}
	
	public static function getServicesForCountry(){
		$services = array();
		$query = tep_db_query('SELECT * FROM `ups_services`');
		while ($service = tep_db_fetch_array($query)) {
		 	$services[] = new objectInfo($service);
		}
		return $services;
	}
		
	public static function getUpsServicesList($id_ups_account){
		if(!$id_ups_account)
			return;
		$services_list = array();
		$services_list[] = array('id' => '-1', 'text' => UPS_ACCOUNT_SELECT_SERVICE);
		$account = new UpsAccount((int) $id_ups_account);
		$accountCountryCode = Tools::getCountryCode($account->id_country);
		$services = UPSRegistrationApi::getUPSServices();
		
		foreach($services as $key => $service){			
			if(in_array($accountCountryCode, $service['originCountries'])){
				if(($account->is_ups_ape && $key == 70) || !$account->is_ups_ape && $key != 70){
					$services_list[] = array('id' => $key, 'text' => $service['name']);
				}
			}
		}		
		return $services_list;
	}
	
	public static function getUpsServicesDest($ups_service_code, $id_ups_account){
		$dest_countries = array();
		$account = new UpsAccount((int) $id_ups_account);
		$accountCountryCode = Tools::getCountryCode($account->id_country);
		$services = UPSRegistrationApi::getUPSServices();		
		foreach($services as $key => $service){
			if($key == $ups_service_code){
				foreach($service['destinationCountries'] as $destCountryCode){
					if(!$service['onlySameAsOrigin'] || $accountCountryCode == $destCountryCode){
						$dest_countries[] = $destCountryCode;
					}					 
				}
			}
		}
		return $dest_countries;
	}
	
	public static function getUpsServicesName($ups_service_code){
		$services = UPSRegistrationApi::getUPSServices();
		foreach($services as $key => $service){
			if($key == $ups_service_code){
				return $service['name'];
			}
		}
		return false;
	}
	
}