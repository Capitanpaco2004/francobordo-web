<?php

	include( DIR_WS_LANGUAGES . $language . '/comentarios.php' );

	// Variables
	$aComentarios    = null;
	$sFormulario = '';

	// Obtenemos todos los comentarios del producto
	$aComentarios = tep_db_query( 'SELECT r.customers_name, r.reviews_rating, r.reviews_recomendar, rd.reviews_pros, rd.reviews_contras, DATE_FORMAT(r.date_added, "%d/%m/%Y") as date_added, rd.reviews_text
								   FROM reviews r
								   INNER JOIN reviews_description rd ON(rd.reviews_id = r.reviews_id)
								   WHERE approved = 1 and products_id="' . (int)preg_replace( '/{.+$/i', '', $_GET['products_id'] ) . '" ORDER BY r.date_added DESC');

	// Numero de comentarios
	$nTotalComentarios = tep_db_num_rows( $aComentarios );
	
	// Formulario
	$sFormulario = 'action="' . tep_href_link( 'escribir_comentarios.php' ) . '"';	
	
	// Nombre de usuario
	$sNombre = '';
	if( tep_session_is_registered( 'customer_id' ) )
	{
		$aUsuarios = tep_db_query( 'SELECT customers_id, customers_firstname, customers_lastname 
									FROM ' . TABLE_CUSTOMERS . '
									WHERE customers_id = ' . (int)$customer_id );
		$aUsuario = tep_db_fetch_array( $aUsuarios );
		$sNombre = $aUsuario['customers_firstname'];
	}	
	
	// Incluimos el theme
	include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>