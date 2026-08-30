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

		// 2026-08-29 secfix: escapamos SIEMPRE lo que sale. Hoy solo se pasan constantes de idioma
		// (texto plano, sin HTML), asi que es no-op; blinda el endpoint si alguna vez se le pasa
		// texto del usuario.
		echo '<div class="msje msje-' . $aMensaje[$sType] . '"><div class="msje-icon"></div>' . htmlspecialchars( (string)$sMensaje, ENT_QUOTES, 'UTF-8' ) . '</div>';
		
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
		$nProductoID = getCleanerString( isset( $_POST['product_id'] ) ? $_POST['product_id'] : '' );
		$nProductoID = preg_replace( '/{.+$/i', '', $nProductoID );
		$nProductoID = (int)$nProductoID;

		// Comentario
		$sComentario = getCleanerString( isset( $_POST['reviews_text'] ) ? $_POST['reviews_text'] : '' );

		// Ventajas
		$sVentajas = getCleanerString( isset( $_POST['reviews_pros'] ) ? $_POST['reviews_pros'] : '' );

		// Desventajas
		$sDesventajas = getCleanerString( isset( $_POST['reviews_contras'] ) ? $_POST['reviews_contras'] : '' );

		// Recomendar
		$nRecomendar = ( isset( $_POST['reviews_recomendar'] ) && (int)$_POST['reviews_recomendar'] == 1 ) ? 1 : 0;

		// Puntos
		$nPuntos = (int)getCleanerString( isset( $_POST['rating'] ) ? $_POST['rating'] : '' );

		// Nombre
		$sNombre = getCleanerString( isset( $_POST['customers_name'] ) ? $_POST['customers_name'] : '' );

		// Comprobamos si el producto existe
		// #FB-RV-ATOMIC [10]: solo productos ACTIVOS. Antes se podian dejar opiniones (y generar
		// puntos) sobre productos desactivados o de staging (products_status 0 / 2).
		$aProductos = tep_db_query( 'SELECT products_id
									 FROM ' . TABLE_PRODUCTS . '
									 WHERE products_id = ' . $nProductoID . '
									   AND products_status = 1' );

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
		
		// 2026-08-29 secfix: el cliente SIEMPRE sale de la sesion, nunca de la peticion.
		// Anonimo = 0, que es lo que acababa guardando el codigo anterior (insertaba la cadena "NULL"
		// en una columna int, y MySQL la convertia en 0).
		$nIdUsuario = 0;
		if( tep_session_is_registered( 'customer_id' ) && isset( $_SESSION['customer_id'] ) )
			$nIdUsuario = (int)$_SESSION['customer_id'];

		// 2026-08-29 secfix: corta el reenvio duplicado del mismo formulario dentro de la sesion.
		// La huella incluye el texto, asi que una opinion corregida o la de otro producto SI pasa;
		// solo se descarta el envio identico repetido (doble click o script en bucle).
		$sHuellaOpinion = md5( $nProductoID . '|' . $nPuntos . '|' . $nRecomendar . '|' . $sNombre . '|' . $sComentario . '|' . $sVentajas . '|' . $sDesventajas );
		$aUltimasOpiniones = ( isset( $_SESSION['dxreviews_last'] ) && is_array( $_SESSION['dxreviews_last'] ) ) ? $_SESSION['dxreviews_last'] : array();

		if( isset( $aUltimasOpiniones[$sHuellaOpinion] ) && ( time() - (int)$aUltimasOpiniones[$sHuellaOpinion] ) < 3600 )
			showMensaje( MODULE_COMENTARIO_SUCCESS, 'correcto' );

		// #FB-RV-ATOMIC [3]: la huella se graba DESPUES de los INSERT (ver mas abajo). Grabarla
		// aqui hacia que, si el INSERT fallaba, el reintento del cliente devolviese "correcto"
		// sin guardar nada y la opinion se perdiera en silencio durante una hora.

		// Si todo esta correcto introducimos el comentario
		tep_db_query( 'INSERT INTO reviews (products_id, customers_id, customers_name, reviews_rating, reviews_recomendar, date_added, approved)
					   VALUES (' . $nProductoID . ', ' . $nIdUsuario . ', "' . tep_db_input( $sNombre ) . '", ' . $nPuntos . ', ' . $nRecomendar . ', "' . date( 'Y-m-d H:i:s' ) . '", 0)' );

		$nIdComentario = (int)tep_db_insert_id();

		tep_db_query( 'INSERT INTO reviews_description (reviews_id, languages_id, reviews_text, reviews_pros, reviews_contras)
					   VALUES (' . $nIdComentario . ', ' . (int)$languages_id . ', "' . tep_db_input( $sComentario ) . '", "' . tep_db_input( $sVentajas ) . '", "' . tep_db_input( $sDesventajas ) . '")' );

		#### Points/Rewards Module V2.1rc2a BOF ####*/
		// 2026-08-29 secfix: solo cliente autenticado de sesion, un unico abono por cliente y
		// producto, importe SIEMPRE de la constante del servidor (nunca de $_POST) y los puntos
		// quedan SIEMPRE pendientes de aprobacion.
		if( $nIdUsuario > 0 && USE_POINTS_SYSTEM == 'true' && tep_not_null(USE_POINTS_FOR_REVIEWS) )
		{
			// #FB-RV-ATOMIC [7]: mismo redondeo que tep_add_pending_points() (redemptions.php:158).
			$points_toadd = (int)round( (float)USE_POINTS_FOR_REVIEWS );
			$comment = 'TEXT_DEFAULT_REVIEWS';
			$points_type = 'RV';

			// #FB-RV-ATOMIC [1][5]: UNA SOLA sentencia. La version anterior hacia SELECT y luego
			// INSERT, y como NO hay bloqueo de sesion (STORE_SESSIONS='mysql', el save handler no
			// usa FOR UPDATE ni GET_LOCK), N peticiones simultaneas del mismo cliente veian las N
			// el SELECT vacio y abonaban N veces. Con INSERT ... SELECT ... WHERE NOT EXISTS la
			// comprobacion y la insercion son la misma sentencia y InnoDB las serializa.
			// El indice idx_cpp_cust_order_type (customer_id, orders_id, points_type) es NECESARIO
			// para que el gap lock sea estrecho; sin el seria un full scan bloqueante.
			// OJO: ese indice NO puede ser UNIQUE — hay 36.508 grupos duplicados historicos
			// (tipos SP/RD), un ALTER ... ADD UNIQUE fallaria.
			//
			// [5] Ademas se limita a 5 abonos RV por cliente y 24 h: sin esto una sola cuenta
			// gratuita podia emitir 20 puntos x 22.932 productos activos (~458.000 puntos).
			if( $points_toadd > 0 )
			{
				// No usamos tep_add_pending_points() a proposito: esa funcion acredita el saldo al
				// instante si POINTS_AUTO_ON = "0", y los puntos por opinion deben pasar por la
				// aprobacion manual de _admin/customers_points_pending.php (points_status = 1).
				$sTabla = TABLE_CUSTOMERS_POINTS_PENDING;
				$sTipo  = tep_db_input( $points_type );

				tep_db_query( 'INSERT INTO ' . $sTabla . '
							   (customer_id, orders_id, points_pending, date_added, points_comment, points_type, points_status)
							   SELECT ' . $nIdUsuario . ', ' . $nProductoID . ', ' . $points_toadd . ', now(), "' . tep_db_input( $comment ) . '", "' . $sTipo . '", 1
							   FROM DUAL
							   WHERE NOT EXISTS ( SELECT 1 FROM ' . $sTabla . '
												  WHERE customer_id = ' . $nIdUsuario . '
													AND orders_id = ' . $nProductoID . '
													AND points_type = "' . $sTipo . '" )
								 AND ( SELECT COUNT(*) FROM ' . $sTabla . '
									   WHERE customer_id = ' . $nIdUsuario . '
										 AND points_type = "' . $sTipo . '"
										 AND date_added > DATE_SUB( now(), INTERVAL 1 DAY ) ) < 5' );
			}
		}
		#### Points/Rewards Module V2.1rc2a EOF ####*/

		// #FB-RV-ATOMIC [3]: la huella anti-reenvio se graba AQUI, ya con la opinion guardada.
		// NO es un control de seguridad (se salta con osCsid=<aleatorio> en el POST o cambiando
		// un byte del texto); solo evita el duplicado por doble clic.
		$aUltimasOpiniones[$sHuellaOpinion] = time();
		$_SESSION['dxreviews_last'] = array_slice( $aUltimasOpiniones, -20, 20, true );

		// Eliminamos
		 tep_session_unregister('dxreviews_text');

		showMensaje( MODULE_COMENTARIO_SUCCESS, 'correcto' );
	}
	else
	{
		tep_redirect( 'index.php' );
	}
?>