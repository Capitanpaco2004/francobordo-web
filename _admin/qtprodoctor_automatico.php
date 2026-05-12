<?php
	
	require('includes/application_top.php');
	require('includes/functions/qtpro_functions.php');
 
	$products_query = tep_db_query('select products_id from products');
	$total = 0;
	while($products = tep_db_fetch_array($products_query)){
		$facts_array = qtpro_doctor_investigate_product($products['products_id']);
	
		if(!$facts_array['stock_entries_healthy']){
			//Amputation (Elimina todas las filas desordenadas)
			$total += qtpro_doctor_amputate_bad_from_product($products['products_id']);
			qtpro_update_summary_stock($products['products_id']);
		}
		if(!$facts_array['summary_and_calc_stock_match']){
			//Establecer el resumen de stock en el stock correcto
			qtpro_update_summary_stock($products['products_id']);
		}
	}
	
	echo $total . ' registros actualizados.';
?>