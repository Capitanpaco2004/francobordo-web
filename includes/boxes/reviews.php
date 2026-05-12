<?php
	// Variables
	$sId = tep_db_prepare_input( preg_replace( '/\{.+$/i', '', $_GET['products_id'] ) );

	// Consulta
	$sSql = 'SELECT r.reviews_id, r.reviews_rating, p.products_id, p.products_image, pd.products_name, rd.reviews_text 
			 FROM reviews r
			 INNER JOIN reviews_description rd ON (r.reviews_id = rd.reviews_id)
			 INNER JOIN products p ON (p.products_id = r.products_id)
			 INNER JOIN products_description pd ON (p.products_id = pd.products_id)
			 WHERE p.products_status = 1 and rd.languages_id = "' . (int)$languages_id . '" and pd.language_id = "' . (int)$languages_id . '" and r.approved = 1';

	// Si nos encontramos en un producto mostramos sus comentarios
	if( $sId != '' )
		$sSql .= ' AND p.products_id = "' . (int)$sId . '"';

	// Orden
	$sSql .= ' ORDER BY r.reviews_id DESC LIMIT 5';

	// Obtenemos los datos
	$aDatos = tep_db_query( $sSql );

	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
?>