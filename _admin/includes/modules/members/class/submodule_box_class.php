<?php

class submodule_box_class
{
	public array $submodules_selected = [];
	public array $submodules_unselected = [];

	public function __construct(public string $name, public int $id)
    {
    }

	public function add_submodule(submodules_class $submodule){
		if($submodule->selected) {
			$this->submodules_selected[] = $submodule;
		}else{
			$this->submodules_unselected[] = $submodule;
		}
	}

	public function print_box(){
		echo '<div class="oeWrpr groups-grid-item" style="margin-top: 40px;">';
			echo '<div class="oeTitu"><i class="fa fa-lock"></i>'.$this->name.'</div>';
			echo '<div class="oeCntd row ax xform xform-horizontal">';
				echo '<div class="column a12">';
					echo '<div class="rows ax xform sp10 groups-select-form">';
						echo '<div class="column a05">';
							echo '<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />';
							echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'boxes_from[]', $this->generate_array($this->submodules_unselected), '', 'class="skip select-search from" multiple="multiple"' )); ;
						echo '</div>';
						echo '<div class="column a02 buttons">';
							echo '<div class="add-right hvr7"><i class="fas fa-angle-right"></i></div>';
							echo '<div class="add-left hvr7"><i class="fas fa-angle-left"></i></div>';
							echo '<div class="add-all-right hvr7"><i class="fas fa-angle-double-right"></i></div>';
							echo '<div class="add-all-left hvr7"><i class="fas fa-angle-double-left"></i></div>';
						echo '</div>';
						echo '<div class="column a05">';
							echo '<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />';
							echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'boxes_to[]', $this->generate_array($this->submodules_selected), 'selected', 'class="skip select-search to" multiple="multiple"' ));
						echo '</div>';
					echo '</div>';
				echo '</div>';
			echo '</div>';
		echo '</div>';
	}

	private function generate_array(array $array){
		$auxArray = [];
		foreach ($array as $item){
			if($item instanceof submodules_class){
				$auxArray[] = ["text"=> $item->name, "id"=> $this->id . '-'. $item->id];
			}
		}
		return $auxArray;
	}

	public function update_database(array $selected){
		$fullArray = array_merge($this->submodules_selected, $this->submodules_unselected);
		foreach ($fullArray as $submodule){
			$submodule->update_database($selected);
		}
	}

	//Si queremos quitar el permiso de todos los submodulos
	public function empty_database(){
		foreach ($this->submodules_selected as $submodule){
			$submodule->remove_current_database();
		}
	}
}
