<?php

class submodules_all
{
	public array $boxes = [];


	public function addBox($register){

		$parentId = $register['admin_files_id'];
		$submodule = new submodules_class($register['admin_files_submodule_id'],$register['admin_files_name'], $register['admin_groups_id']);

		if(array_key_exists($parentId,$this->boxes)){
			$container = $this->boxes[$parentId];
			if($container instanceof submodule_box_class){
				$container->add_submodule($submodule);
			}else{
				throw new \http\Exception\InvalidArgumentException("La clase contiene un objeto de tipo ". $container::class." solo estan permitidos los objetos de tipo submodule_box_class");
			}

		}else{
			$container =  new submodule_box_class($register['parent_name'], $parentId);
			$container->add_submodule($submodule);
			$this->boxes[$parentId] =$container;

		}
	}

	public function print(){
		foreach ($this->boxes as $box){
			$box->print_box();
		}
	}

	public function update_all(array $info){
		if($info === []){
			foreach ($this->boxes as $box){
				$box->empty_database();
			}
			return;
		}
		foreach ($info as $key => $box){
			$this->boxes[$key]->update_database($box);
		}
	}
}
