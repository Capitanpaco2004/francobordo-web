<?php
/* Filtros varios que pueden ser utilizados en varios lados, para sacar los desplegables de Fabricantes/Categorías */

function manufacturersList(){
	$manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");
	if ($number_of_rows = tep_db_num_rows($manufacturers_query)) {

		// Display a drop-down
		$manufacturers_array = [];
		if (MAX_MANUFACTURERS_LIST < 2) {
			$manufacturers_array[] = ['id' => '', 'text' => TEXT_ALL_MANUFACTURERS];
		}

		while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
			$manufacturers_name = ((strlen((string) $manufacturers['manufacturers_name']) > MAX_DISPLAY_MANUFACTURER_NAME_LEN) ? substr((string) $manufacturers['manufacturers_name'], 0, MAX_DISPLAY_MANUFACTURER_NAME_LEN) . '..' : $manufacturers['manufacturers_name']);

			$manufacturers_array[] = ['id' => $manufacturers['manufacturers_id'],
		                               	   'text' => $manufacturers_name];
		}

		return TEXT_MANUFACTURER . ': ' . tep_draw_pull_down_menu('manufacturers_id', $manufacturers_array, ($_GET['manufacturers_id'] ?? ''), 'onChange="this.form.submit();" size="' . MAX_MANUFACTURERS_LIST . '"');
	}
    return null;
}


function categoriesList(){
    return TEXT_CATEGORY . ': ' . getCategoryTreeInMemory();
}
?>
