<?php
//These are functions for calculating and dooing different QTPro things.
//The future goal is that this kit of functions will make the integration of other contributions easier.
//Contributors: Please feel free to ad new functions to this kit. But please make sure that they are error free.


//-------------------------//
//---  Small Tools  ---//
//-------------------------//
/*
This function will take a string looking like "1-2,3-4,5-6" and return an array looking like:
Array
(
    [1] => 2
    [3] => 4
    [5] => 6
)
*/
function qtpro_products_attributes_string2array($products_attributes_string){
$ret_array = array();

	$optionchoise_array =explode(',', $products_attributes_string);// values in $option_choise_array looks like: '1-2'
	//Now put them into $ret_array in a correct way:
	foreach($optionchoise_array as $optionchoise){
		$splitted_array = explode('-', $optionchoise);
		$option = $splitted_array[0];
		$choise = $splitted_array[1];
		
		$ret_array[$option] = $choise;
	}

return $ret_array;
}

/*
This function will take a string looking like "1-2,3-4,5-6" and return an array looking like:
Array
(
    [0] => 1
    [2] => 3
    [3] => 5
)
*/
function qtpro_products_attributes_string2options_array($products_attributes_string){
$ret_array = array();

	$optionchoise_array =explode(',', $products_attributes_string);// values in $option_choise_array looks like: '1-2'
	//Now put them into $ret_array in a correct way:
	foreach($optionchoise_array as $optionchoise){
		$splitted_array = explode('-', $optionchoise);		
		$ret_array[] = $splitted_array[0];;
	}

return $ret_array;
}

//-------------------------//
//---  Doctor Functions  ---//
//-------------------------//

//This is the most detailed doctor function for examining a product.
//When is a product not healthy? Answer: there are three types of errors. The first is called "intruder" error. This is when there exist an attribute in the products_stock_attributes string which is not stocktracked. The Second type of error called "lack" error. This is when an attribute is missing in products_stock_attributes; that is: when the product has an attribute with tracked stock and that attribute is not in the products_stock_attributes. The third type of error is when the current summary stock for a product isn't the summary stock we get if we calculate it.
function qtpro_doctor_investigate_product($products_id){
$facts_array = array();
$facts_array['id'] = $products_id;
$facts_array['any_problems'] = false;
$facts_array['has_tracked_options'] = false;

$facts_array['summary_and_calc_stock_match'] = true; //If summary_stock and calc_stock is the same value; this = true; else this = false
$facts_array['summary_stock'] = 0; //The current summary stock for this product in the database.
$facts_array['calc_stock'] = 0; //The summary stock calculated by looking at the options products_stock.

$facts_array['stock_entries_healthy'] = true; //If any row is sick; this = true; else this = false;
$facts_array['stock_entries_count'] = 0; //The number of rows this product had in the options products_stock database table.
$facts_array['sick_stock_entries_count'] = 0;//The number of sick rows this product had in the options products_stock database table.
$facts_array['lacks_id_array'] = array(); //An array with all the id:s of the options that were lacked anywhere in the options products_stock table.
$facts_array['intruders_id_array'] = array(); //An array with all the id:s of the options that were intruding anywhere in the options products_stock table.

	$facts_array['has_tracked_options'] = qtpro_product_has_tracked_options($products_id);
	$facts_array['summary_stock'] = qtpro_get_products_summary_stock($products_id);
	
	//Calculate the summary stock by looking att the stock for the different options
	if($facts_array['has_tracked_options']){
		$products_stock_quantity_query = tep_db_query("SELECT products_stock_quantity FROM " . TABLE_PRODUCTS_STOCK . " WHERE products_id = '" . $products_id . "'");
		
		while($row = tep_db_fetch_array($products_stock_quantity_query)){
			if($row['products_stock_quantity'] > 0){ //If they are negative they are oversold and this do not affect what we have on stock.
				$facts_array['calc_stock']+= $row['products_stock_quantity'];
			}
		}
		
	}else{
		//Set the calc_stock to the summary stock
		$facts_array['calc_stock'] = $facts_array['summary_stock'];
	}
	
	//Decide summary_and_calc_stock_match
	if($facts_array['summary_stock'] == $facts_array['calc_stock']){
		$facts_array['summary_and_calc_stock_match'] = true;
	}else{
		$facts_array['summary_and_calc_stock_match'] = false;
	}

	//Get all products_stock entries for the product. ---------------------------------------
	$attributes_stock_query = tep_db_query("SELECT products_stock_attributes
											FROM " . TABLE_PRODUCTS_STOCK . " 
											WHERE products_id = '" . $products_id . "'");
											
	$facts_array['stock_entries_count'] = tep_db_num_rows($attributes_stock_query);
	
	if ($facts_array['stock_entries_count'] == 0){
		$facts_array['sick_stock_entries_count'] = 0;
		$facts_array['stock_entries_healthy'] = true;	
	}else{
		//Get the id for all options this product has and put them in the array: $products_options_array  ---------------------------------------
		$products_options_query = tep_db_query("SELECT DISTINCT options_id
												FROM " . TABLE_PRODUCTS_ATTRIBUTES . " 
												WHERE products_id = '" . $products_id . "'");
		$products_options_array = array();
		while ($products_option_id = tep_db_fetch_array($products_options_query)) {
			$products_options_array[] =$products_option_id['options_id'];
		}
												
		//Get the id for all attributes which are tracked and put them in the array: $tracked_options_array  ---------------------------------------
		$tracked_options_query = tep_db_query("SELECT DISTINCT products_options_id
												FROM " . TABLE_PRODUCTS_OPTIONS . " 
												WHERE products_options_track_stock = 1");
		$tracked_options_array = array();
		while ($tracked_option_id = tep_db_fetch_array($tracked_options_query)) {
			$tracked_options_array[] =$tracked_option_id['products_options_id'];
		}
		//Ok so now we can check if the option_id 8 is tracked by running: in_array(8, $tracked_options_array) =)
		

		//Check every products_stock_attributes for errors
		while ($products_stock_attributes = tep_db_fetch_array($attributes_stock_query)) {
			$this_row_is_sick = false;
			$string_options_array = qtpro_products_attributes_string2options_array($products_stock_attributes['products_stock_attributes']);
			
			//Now check for "intruder" errors (check for attributes which are there but should not as they are not stocktracked)
			foreach($string_options_array as $option){
				if(!in_array($option, $tracked_options_array)){
					$this_row_is_sick = false;
					$facts_array['sick_stock_entries_count']++;
					$facts_array['stock_entries_healthy'] = false;
					$facts_array['intruders_id_array'][] = $option;
				}
			}
			
			//Now check for "lack" errors (check for attributes should be there, because they are stocktracked, but for some reason are not)
			foreach($products_options_array as $product_option){
				if(in_array($product_option, $tracked_options_array) && !in_array($product_option, $string_options_array)){
					$this_row_is_sick = false;
					
					$facts_array['stock_entries_healthy'] = false;
					$facts_array['lacks_id_array'][] = $product_option;
				}
			}

			if($this_row_is_sick){
				$facts_array['sick_stock_entries_count']++;
			}
			
		}
	}
	
	//Set the overwiev variables:
	if(!$facts_array['summary_and_calc_stock_match'] or !$facts_array['stock_entries_healthy']){
		$facts_array['any_problems'] = true;
	}

return $facts_array;
}

function qtpro_doctor_formulate_database_investigation(){
	print "<p>Sick products in the database:</p>";
	$prod_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS);
	while($product = tep_db_fetch_array($prod_query)){
		$investigation= qtpro_doctor_investigate_product($product['products_id']);
		if($investigation['any_problems']){
			print '<p class="messageStackWarning">Product with ID '.$product['products_id'].': '.qtpro_doctor_formulate_product_investigation($investigation, 'short_suggestion').'</p>';
		}
	}
}

function qtpro_doctor_formulate_product_investigation($facts_array, $formulate_style){
$str_ret ='';
	switch($formulate_style){
		case 'short_suggestion':
			if($facts_array['any_problems']){
				if($facts_array['summary_and_calc_stock_match'] != true && $facts_array['stock_entries_healthy'] != true){
					$str_ret ='Las entradas en la base de datos del stock de este producto están incorrectas y el calculo del stock del producto esta mal. Por favor revíselos haciendo click <a class="clr-href" href="' . tep_href_link("stock.php", 'product_id=' . $facts_array['id']) . '" class="headerLink">aquí</a>.';
				}elseif(!$facts_array['summary_and_calc_stock_match']){
					$str_ret ='El cálculo del resume de stock esta incorrecto. Por favor revíselo <a class="clr-href" href="' . tep_href_link("stock.php", 'product_id=' . $facts_array['id']) . ' " class="headerLink">aquí</a>.';
				}elseif(!$facts_array['stock_entries_healthy']){
					$str_ret ='Hay errores en las entradas en base de datos del stock de este producto. Por favor revíselo <a class="clr-href"  href="' . tep_href_link("stock.php", 'product_id=' . $facts_array['id']) . ' " class="headerLink">aquí</a>.';
				}else{
					$str_ret ="Error";
				}
			}else{
				$str_ret ="Este producto está correcto.";
			}
		
		break;
		case 'detailed':
			//Create Header
			/*if($facts_array['any_problems']){
				$str_ret ='<span style="color:red; font-weight: bold; font-size:1.2em;">This product needs attention!</span><br /><br />';
			}else{
				$str_ret ='<span style="color:green; font-weight: bold;">This product is all ok.</span><br /><br />';
			}*/
			
			//Talk about summary and calc stock
			if($facts_array['summary_and_calc_stock_match']){
				$str_ret .='<span style="color:green; font-weight: bold; font-size:1.2em;">El resumen de la cantidad de productos en stock es correcto.</span><br />
				Esto significa que el resumen del Stock General actual de este producto registrado en base de datos, coincide con el valor que obtenemos del calculo actual.<br />
				<b>El total de stock es: '. $facts_array['summary_stock'] .'</b><br /><br />';
			}else{
				$str_ret .='<span style="color:red; font-weight: bold; font-size:1.2em;">ATENCIÓN: El resumen de la cantidad de productos en stock NO es correcto.</span><br />
				El Stock General actual registrado en la base de datos de este producto esta descuadrado frente a la suma de las unidades del stock por atributos.<br />
				<b>Stock General según el valor en la base de datos: '. $facts_array['summary_stock'] .'</b><br />
				<b>Stock General según la suma del valor del stock de cada opción asignada: '. $facts_array['calc_stock'] .'</b><br /><br />';
			}

			//Talk about the health of the stock entries
			if($facts_array['stock_entries_healthy']){
				$str_ret .='<span style="color:green; font-weight: bold; font-size:1.2em;">El stock de las opciones del producto es correcto</span><br />
				Todos los registros de la base de datos del Stock por Opciones para este producto aparecen correctamente.<br />
				<b>Número total de registros que tiene este producto: '. $facts_array['stock_entries_count'] .'</b><br />
				<b>Número de registros con errores: '. $facts_array['sick_stock_entries_count'] .'</b><br />';
				
			}else{
				$str_ret .='<span style="color:red; font-weight: bold; font-size:1.2em;">ATENCIÓN: El stock de las opciones del producto NO es correcto</span><br />
				Esto significa que al menos uno de los registros de base de datos para este producto no es correcto. O alguna de las opciones del producto no aparece en filas que deber’a o aparece en filas que no deberían.<br />
				<b>Número total de registros que tiene este producto: '. $facts_array['stock_entries_count'] .'</b><br />
				<b>Número de registros con errores: '. $facts_array['sick_stock_entries_count'] .'</b><br /><br />';
				
				if(sizeof($facts_array['lacks_id_array']) > 0){
					$str_ret .='<b>Estas opciones no aparecen en la fila(s):</b><br />';
					foreach($facts_array['lacks_id_array'] as $lack_id){
						$str_ret .= '<span style="color:red;"><b>'. tep_options_name($lack_id) .'</b></span><br />';
					}
					$str_ret .='<span style="color:blue; font-weight: bold;">Posibles soluciones: </span>Borrar la fila correspondiente de la base de datos o dejar de controlar el stock de esta opción.<br /><br />';
				}
				
				if(sizeof($facts_array['intruders_id_array']) > 0){
					$str_ret .= '<br /><b>Estas opciones existen en fila(s) aunque no deberían:</b><br />';
					foreach($facts_array['intruders_id_array'] as $intruder_id){
						$str_ret .= '<span style="color:red;"><b>'. tep_options_name($intruder_id) .'</b></span><br />';
					}
					$str_ret .='<span style="color:blue; font-weight: bold;">Posibles soluciones: </span>Borrar la fila correspondiente de la base de datos o iniciar el control del stock de esta opción.<br /><br />';
				}
				
			}
			
			//Talk about automatic solutions
			if($facts_array['any_problems']){
				$str_ret .='<p><br /><span style="color:blue; font-weight: bold; font-size:1.2em;">¿Solucionar Automáticamente?</span><br />';
				
				if(!$facts_array['stock_entries_healthy']){
					$str_ret .='<p><a href="' . tep_href_link(FILENAME_QTPRODOCTOR, 'action=amputate&pID='.$facts_array['id'], 'NONSSL') . '" class="menuBoxContentLink" target="_blank">Limpiar (Elimina todas las filas desordenadas)</a></p>';
				}
				if(!$facts_array['summary_and_calc_stock_match']){
					$str_ret .='<p><a href="' . tep_href_link(FILENAME_QTPRODOCTOR, 'action=update_summary&pID='.$facts_array['id'], 'NONSSL') . '" class="menuBoxContentLink" target="_blank">Actualizar el Stock General del producto a '. $facts_array['calc_stock'] .' unidades</a></p>';
				}
				
				$str_ret .='</p>';
			}
			
			
		break;
	}

return $str_ret;
}

function qtpro_doctor_product_healthy($products_id){
	$results = qtpro_doctor_investigate_product($products_id);
	if($results['any_problems'] == false){
		return true;
	}else{
		return false;
	}
}

//This function will delete all option stock entries from the product.
function qtpro_doctor_amputate_all_from_product($products_id){
	tep_db_query("DELETE FROM " . TABLE_PRODUCTS_STOCK . " WHERE products_id =". $products_id);	
}

function qtpro_doctor_amputate_bad_from_product($products_id){
$return_amputate_count = 0;

	//MISSION CODENAME "Get information" STARTS HERE
	//Get all products_stock entries for the product. ---------------------------------------
	$attributes_stock_query = tep_db_query("SELECT products_stock_attributes, products_stock_id
											FROM " . TABLE_PRODUCTS_STOCK . " 
											WHERE products_id = '" . $products_id . "'");
											
	//Ops! a sub mission to possibly save work:
	if (tep_db_num_rows($attributes_stock_query) == 0){
		//This is normal if the product has NO strackstocked attributes
		//BUT it can also happen for products WITH strackstocked attributes. Nothing in stock that is.
		return $return_amputate_count; //The surgery is complete. Doctor says nothing to amputate :D
	}
	//Submission complete; let's continue
	
	//Get the id for all options this product has and put them in the array: $products_options_array  ---------------------------------------
	$products_options_query = tep_db_query("SELECT DISTINCT options_id
											FROM " . TABLE_PRODUCTS_ATTRIBUTES . " 
											WHERE products_id = '" . $products_id . "'");
	$products_options_array = array();
	while ($products_option_id = tep_db_fetch_array($products_options_query)) {
		$products_options_array[] =$products_option_id['options_id'];
	}
											
	//Get the id for all attributes which are tracked and put them in the array: $tracked_options_array  ---------------------------------------
	$tracked_options_query = tep_db_query("SELECT DISTINCT products_options_id
											FROM " . TABLE_PRODUCTS_OPTIONS . " 
											WHERE products_options_track_stock = 1");
	$tracked_options_array = array();
	while ($tracked_option_id = tep_db_fetch_array($tracked_options_query)) {
		$tracked_options_array[] =$tracked_option_id['products_options_id'];
	}
	//Ok so now we can check if the option_id 8 is tracked by running: in_array(8, $tracked_options_array) =)
	
	//MISSION CODENAME "Get information" ENDS HERE
	
	
	//Check every row for errors
	while ($products_stock_attributes = tep_db_fetch_array($attributes_stock_query)) {
		$amputate_this = false;
		$string_options_array = qtpro_products_attributes_string2options_array($products_stock_attributes['products_stock_attributes']);
		
		//Now check for "intruder" errors (check for attributes which are there but should not as they are not stocktracked)
		foreach($string_options_array as $option){
			if(!in_array($option, $tracked_options_array)){
				//aha! an "intruder"
				$amputate_this = true; //The examination is complete. Doctor says this products_stock_id must be amputated :'(
			}
		}
		
		//Now check for "lack" errors (check for attributes should be there, because they are stocktracked, but for some reason are not)
		foreach($products_options_array as $products_option){
			if(in_array($products_option, $tracked_options_array) && !in_array($products_option, $string_options_array)){
				//aha! a "lack"
				$amputate_this = true; //The examination is complete. Doctor says this products_stock_id must be amputated :'(
			}
		}
		
		if($amputate_this){
			tep_db_query("DELETE 
						  FROM " . TABLE_PRODUCTS_STOCK . "
						  WHERE products_stock_id =". $products_stock_attributes['products_stock_id']);	
			$return_amputate_count++;		
		}
	}

return $return_amputate_count; //This will return the array with the amputate count.
}

//This function will update the summary_stock for a product
function qtpro_update_summary_stock($products_id){
      tep_db_query("UPDATE " . TABLE_PRODUCTS . " 
                      SET products_quantity = " . qtpro_calculate_summary_stock($products_id) . "
                      WHERE products_id = '" . $products_id . "'");

}

//------------------------------------------//
//---  Product Investigation Functions  ---//
//----------------------------------------//

function qtpro_product_exists($products_id){
	$prod_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = '" . $products_id . "'");
	if (tep_db_num_rows($prod_query) == 0){ 
		//Nothing was found so it did not exist.
		return false;
	}else{	
		return true;
	}
}

function qtpro_product_has_tracked_options($products_id){
	//Get the id for all options this product has and put them in the array: $products_options_array  ---------------------------------------
	$products_options_query = tep_db_query("SELECT DISTINCT options_id
											FROM " . TABLE_PRODUCTS_ATTRIBUTES . " 
											WHERE products_id = '" . $products_id . "'");
	$products_options_array = array();
	while ($products_option_id = tep_db_fetch_array($products_options_query)) {
		$products_options_array[] =$products_option_id['options_id'];
	}
											
	//Get the id for all attributes which are tracked and put them in the array: $tracked_options_array  ---------------------------------------
	$tracked_options_query = tep_db_query("SELECT DISTINCT products_options_id
											FROM " . TABLE_PRODUCTS_OPTIONS . " 
											WHERE products_options_track_stock = 1");
	$tracked_options_array = array();
	while ($tracked_option_id = tep_db_fetch_array($tracked_options_query)) {
		$tracked_options_array[] =$tracked_option_id['products_options_id'];
	}
	//Ok so now we can check if the option_id 8 is tracked by running: in_array(8, $tracked_options_array) =)

	//Do the test:
	foreach($products_options_array as $products_option){
		if(in_array($products_option, $tracked_options_array)){
			return true;
		}
	}

return false;
}

function qtpro_get_products_summary_stock($products_id){
	$products_summary_stock_query = tep_db_query("SELECT products_quantity
											FROM " . TABLE_PRODUCTS . " 
											WHERE products_id = '" . $products_id . "'");
	$product_facts = tep_db_fetch_array($products_summary_stock_query);
	return $product_facts ? $product_facts['products_quantity'] : 0;
}

//This function will calculate and return the summary_stock for a product. If it is a product without tracked attributes the summary_stock will be returned anyway.
//NOTE!!!: This function will include all entries. Even damaged ones...
function qtpro_calculate_summary_stock($products_id){
$summary_stock_to_return = 0;
	if(qtpro_product_has_tracked_options($products_id)){
		//Calculate the summary stock by looking att the stock for the different options
		//Get all products_stock entries for the product. ---------------------------------------
		$products_stock_quantity_query = tep_db_query("SELECT products_stock_quantity
												FROM " . TABLE_PRODUCTS_STOCK . " 
												WHERE products_id = '" . $products_id . "'");
		while($row = tep_db_fetch_array($products_stock_quantity_query)){
			if($row['products_stock_quantity'] > 0){ //If they are negative they are oversold and this do not affect what we have on stock.
				$summary_stock_to_return+= $row['products_stock_quantity'];
			}
		}
		
	}else{
		//Just return he current summary stock
		$summary_stock_to_return = qtpro_get_products_summary_stock($products_id);
	}
return $summary_stock_to_return;
}

function qtpro_products_summary_stock_is_as_calculated($products_id){
	if(qtpro_calculate_summary_stock($products_id) == qtpro_get_products_summary_stock($products_id)){
		return true;
	}else{
		return false;
	}
}







//-------------------------//
//---  Trash-Tools ---//
//-------------------------//

//This function will determine if the parameter row (taken from database table products_stock) is trash
//It is if the products it liks to not exists.
//The $row_array must contain the keys: 'products_id'
function qtpro_stock_row_is_trash($row_array){
	$prod_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = '" . $row_array['products_id'] . "'");

	if (qtpro_product_exists($row_array['products_id'])){ 
		return false;
	}else{
		//The product this row links to does not exists. So it is trash then
		return true;	
	}
}

//This function will count the number of strash rows in the database.
//These rows should never come to exist but this is a good statistical fact for progammers as this indicate something is wrong
function qtpro_number_of_trash_stock_rows(){
$trash_count_ret = 0;

	$products_stock_row_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS_STOCK);
	while($row = tep_db_fetch_array($products_stock_row_query)){
		if(qtpro_stock_row_is_trash($row)){
			$trash_count_ret++;
		}
	}

return $trash_count_ret;
}

// This function will erase all strash rows in the database table for products option stock.
function qtpro_chuck_trash(){
$trash_count_ret = 0;
	
	$products_stock_row_query = tep_db_query("SELECT products_stock_id, products_id FROM " . TABLE_PRODUCTS_STOCK);
	while($row = tep_db_fetch_array($products_stock_row_query)){
		if(qtpro_stock_row_is_trash($row)){
			tep_db_query("DELETE FROM " . TABLE_PRODUCTS_STOCK . " WHERE products_stock_id=" . $row['products_stock_id']);
			$trash_count_ret++;
		}
	}	
	
return $trash_count_ret;
}

//-------------------------//
//---     Statistics    ---//
//-------------------------//

function qtpro_normal_product_count(){
	$prod_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS);
	return tep_db_num_rows($prod_query);
}

function qtpro_tracked_product_count(){
$count_ret = 0;
	$prod_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS);
	while($product = tep_db_fetch_array($prod_query)){
		if(qtpro_product_has_tracked_options($product['products_id'])){
			$count_ret++;
		}	
	}

return $count_ret;
}

function qtpro_sick_product_count(){
$count_ret = 0;
	$prod_query = tep_db_query("SELECT products_id FROM " . TABLE_PRODUCTS);
	while($product = tep_db_fetch_array($prod_query)){
		if(!qtpro_doctor_product_healthy($product['products_id'])){
			$count_ret++;
		}	
	}

return $count_ret;
}


// BOF: MOD - QT Pro
// Function to build menu of available class files given a file prefix
// Used for configuring plug-ins for product information attributes
  function tep_cfg_pull_down_class_files($prefix, $current_file) {
    $d=DIR_FS_CATALOG . DIR_WS_CLASSES;
    $function_directory = dir ($d);

    while (false !== ($function = $function_directory->read())) {
      if (preg_match('/^'.$prefix.'(.+)\.php$/',$function,$function_name)) {
          $file_list[]=array('id'=>$function_name[1], 'text'=>$function_name[1]);
      }
    }
    $function_directory->close();

    return tep_draw_pull_down_menu('configuration_value', $file_list, $current_file);
  }
// EOF: MOD - QT Pro

?>