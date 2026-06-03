<?php
	header ('Content-type: text/html; charset=utf-8');

	require( 'includes/application_top.php' );
	include( DIR_WS_LANGUAGES . $language . '/comentarios.php' );

	// Funcion que muestra los mensajes
	function showMensaje($sMensaje, $sType)
	{
		// Comprobamos el tipo
		if( ! in_array( $sType , array( 'warning', 'correcto', 'error', 'info' ) ) )
			die( 'No existe el tipo de mensaje' );

		$aMensaje = array( 'warning' => 'wrng', 'correcto' => 'crrt', 'error' => 'eror', 'info' => 'info' );

		echo '<div class="msje msje-' . $aMensaje[$sType] . '"><div class="msje-icon"></div>' . $sMensaje . '</div>';
		
		exit();
	}
	
	// Funcion que limpia las cadenas enviadas por post
	function getCleanerString( $sString )
	{
		$sString = strip_tags( (string)$sString );

		return tep_db_prepare_input( $sString );
	}

	// Comprobamos que la peticion sea por AJAX
	if( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' )
	{	
			// Producto ID
		$nProductoID = getCleanerString( $_POST['product_id'] );
		$nProductoID = preg_replace( '/{.+$/i', '', $nProductoID ); 

		// Comentario
		$sComentario = getCleanerString( $_POST['reviews_text'] );
		
		// Ventajas
		$sVentajas = getCleanerString( $_POST['reviews_pros'] );

		// Desventajas
		$sDesventajas = getCleanerString( $_POST['reviews_contras'] );

		// Recomendar
		$nRecomendar = getCleanerString( $_POST['reviews_recomendar'] );

		// Puntos
		$nPuntos = getCleanerString( $_POST['rating'] );
		
		// Nombre
		$sNombre = getCleanerString( $_POST['customers_name'] );
		
		// Comprobamos si el producto existe
		$aProductos = tep_db_query( 'SELECT products_id 
									 FROM ' . TABLE_PRODUCTS . '
									 WHERE products_id = ' . (int)$nProductoID );

		// Si el producto no existe paramos
		if( tep_db_num_rows( $aProductos ) == 0 )
			exit();
		
		// Comprobamos los puntos se encuentre en 1 y 5
		if( $nPuntos <= 0 || $nPuntos > 5 )
			showMensaje( MODULE_COMENTARIO_ERROR_PUNTUACION, 'error' );
			
		// Comprobamos el comentario
		if( $sComentario == '' )
			showMensaje( MODULE_COMENTARIO_ERROR_COMENTARIO, 'error' );
		
		// Comprobamos el nombre
		if( $sNombre == '' )
			showMensaje( MODULE_COMENTARIO_ERROR_NOMBRE, 'error' );
		
		$nIdUsuario = 'NULL';
		if( tep_session_is_registered( 'customer_id' ) )
			$nIdUsuario = $customer_id;		
		
		// Si todo esta correcto introducimos el comentario
		tep_db_query( 'INSERT INTO reviews (products_id, customers_id, customers_name, reviews_rating, reviews_recomendar, date_added, approved) 
					   VALUES (' . $nProductoID . ', "' . $nIdUsuario . '", "' . $sNombre . '", ' . $nPuntos . ', ' . (int)$nRecomendar . ', "' . date( 'Y-m-d H:i:s' ) . '", 0)' );

		tep_db_query( 'INSERT INTO reviews_description (reviews_id, languages_id, reviews_text, reviews_pros, reviews_contras) 
					   VALUES (' . tep_db_insert_id() . ', ' . $languages_id . ', "' . $sComentario . '", "' . $sVentajas . '", "' . $sDesventajas . '")' );

		#### Points/Rewards Module V2.1rc2a BOF ####*/
		if( tep_session_is_registered( 'customer_id' ) && USE_POINTS_SYSTEM == 'true' && tep_not_null(USE_POINTS_FOR_REVIEWS) )
		{
			$points_toadd = USE_POINTS_FOR_REVIEWS;
			$comment = 'TEXT_DEFAULT_REVIEWS';
			$points_type = 'RV';
			tep_add_pending_points($customer_id, $nProductoID, $points_toadd, $comment, $points_type);
		}
		#### Points/Rewards Module V2.1rc2a EOF ####*/
					   
		// Eliminamos
		 tep_session_unregister('dxreviews_text');

		showMensaje( MODULE_COMENTARIO_SUCCESS, 'correcto' );
	}
	else
	{
		tep_redirect( 'index.php' );
	}
?>