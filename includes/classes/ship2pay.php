<?php
	class ship2pay
	{
		public $modules;

		function __construct()
		{
			// Variables
			global $language, $PHP_SELF,$shipment,$GLOBALS;
			$this->modules = array();

			$q_ship2pay = tep_db_query("SELECT shipment, payments_allowed FROM ".TABLE_SHIP2PAY." where status=1");
			
			while( $mods = tep_db_fetch_array( $q_ship2pay ) )
				$this->modules[$mods['shipment']] = $mods['payments_allowed'];
	  
			if( count( $this->modules ) > 0 )
			{		
				// Modulos instalados y ordenamos
				$aInstallModules = explode( ';', MODULE_PAYMENT_INSTALLED );
			
				// Recorremos los modulos del ship2pay para ordenarlos segun los modulos ordenados
				foreach( $this->modules as $key => $value )
				{
					$aMetodos = explode( ';', $value );
					$aArray = array();
					
					// Recorremos los metodos actuales
					foreach( $aMetodos as $sAux )
					{
						// Recorremos los metodos instalados que tienen el orden para buscarlo y añadirlo
						foreach( $aInstallModules as $nCont => $sModuleInstall )
							if( $sModuleInstall == $sAux )						
								$aArray[(int)$nCont] = $sModuleInstall;
					}
					
					// Lo guardamos
					ksort($aArray);
					$this->modules[$key] = implode( ';', $aArray );
				}
			}
		}
    
		function get_pay_modules($ship_module)
		{
			$ship_module = (string)$ship_module;
      			return (isset( $this->modules[$ship_module] ) ? $this->modules[$ship_module] : '');
		}
	}
?>
