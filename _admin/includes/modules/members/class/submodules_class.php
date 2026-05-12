<?php

class submodules_class
{
	public bool $selected;
	//Indica que administradores tienen acceso al submodulo
	private array $admins;

	public function __construct(public int $id, public string $name, string  $selected)
	{
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$this->admins = explode(",", $selected);
		$this->selected = in_array($sGetId,$this->admins);
	}

	public function update_database(array $selected){
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

		//Si esta seleccionado antes, miramos si sigue seleccionado, si no lo esta lo quitamos
		if ($this->selected) {
            if(!in_array($this->id, $selected)){
				$pos = array_search($sGetId, $this->admins);
				if( $pos !== false){
					unset($this->admins[$pos]);
				}
			}
        } elseif (in_array($this->id, $selected)) {
            //Si no esta seleccionado antes, comprobamos si ahora lo esta, si lo esta actualizamos, si no volvemos, no hay que actualizar
            $this->admins[] = $sGetId;
        } else{
				return;
			}
		$adminsString = "";
		if($this->admins !== []){
			$adminsString = implode(",",$this->admins);
		}

		tep_db_query("UPDATE admin_files_submodules SET admin_groups_id = '{$adminsString}' WHERE admin_files_submodule_id = {$this->id}");
	}

	public function remove_current_database(){
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$pos = array_search($sGetId, $this->admins);
		if( $pos !== false){
			unset($this->admins[$pos]);
		}
		$adminsString = "";
		if($this->admins !== []){
			$adminsString = implode(",",$this->admins);
		}

		tep_db_query("UPDATE admin_files_submodules SET admin_groups_id = '{$adminsString}' WHERE admin_files_submodule_id = {$this->id}");
	}


}
