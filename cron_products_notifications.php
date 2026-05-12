<?php
	require( 'includes/application_top.php' );
	require( DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/avisador_productos.php' );
	
	
	// Recorremos los usuarios que estan esperando ser notificados
	$aUsuarios = tep_db_query( 'select products_id, customers_email_address, atributo, idioma from products_notifications' );
	
	// Obtenemos los emails de clientes que si estan suscritos a enviarnos correos
	$aEmailsSubscribed = array_values( pharaonix_getArrayAssociativeSql( 'SELECT c.customers_email_address FROM customers c INNER JOIN rgpd_account_term rgat on(rgat.customers_id = c.customers_id) WHERE rgat.id_term_pivacy_trade = 3', 'customers_email_address', 'customers_email_address', false, 1 ) );
	
	while( $aUsuario = tep_db_fetch_array( $aUsuarios ) )
	{
		// Si el cliente existe y no tiene suscrito pasamos
		if( $aUsuario['customers_id'] != 'null' && !in_array( $aUsuario['customers_email_address'], $aEmailsSubscribed ) )
			continue;	
		
		// Comprobamos si existe la tabla products_stock para mirar clientes que esten con notificaciones por atributos
		if( existsTableDb( 'products_stock' ) )
		{
			// Obtenemos el producto
			$aStock = tep_db_query( 'SELECT * FROM products_stock ps INNER JOIN products_description pd ON (ps.products_id = pd.products_id) WHERE ps.products_stock_attributes = "' . $aUsuario['atributo'] . '" and ps.products_id = ' . $aUsuario['products_id'] );

			// Si hemos encontrado el producto
			if( tep_db_num_rows( $aStock ) > 0 )
			{
				$aStock = tep_db_fetch_array( $aStock );
				
				// Si tiene stock
				if( $aStock['products_stock_quantity'] > -900 )
				{
					// Descomponemos y obtenemos los products options
					$aAttribs = explode( ',', $aStock['products_stock_attributes'] );

					// Obtenemos los idiomas
					$aIdiomas = array();
					$languages_query = tep_db_query("select languages_id, name, code, image, directory from languages order by sort_order");
					while( $languages = tep_db_fetch_array($languages_query) )
						  $aIdiomas[] = array('id' => $languages['languages_id'], 'name' => $languages['name'], 'code' => $languages['code'], 'image' => $languages['image'], 'directory' => $languages['directory']);
									
					// Recorremos los atributos en diferentes idiomas
					foreach( $aIdiomas as $aIdioma )
					{
						foreach( $aAttribs as $aAttrib )
						{
							// Obtenemos el atributo
							$aAttrib = explode( '-', $aAttrib );

							// Obtenemos la opcion
							$aOption = tep_db_query( 'SELECT products_options_name FROM products_options WHERE products_options_id = ' . $aAttrib[0] . ' AND  language_id = ' . $aIdioma['id'] . ';' );
							$aOption = tep_db_fetch_array( $aOption );

							// Obtenemos el valor
							$aValue = tep_db_query( 'SELECT products_options_values_name FROM products_options_values WHERE products_options_values_id = ' . $aAttrib[1] . ' AND  language_id = ' . $aIdioma['id'] . ';' );
							$aValue = tep_db_fetch_array( $aValue );

							// Añadimos el registro al email
							$aAtributos[$aIdioma['id']] .= $aOption['products_options_name'] . ': ' . $aValue['products_options_values_name'] . '<br/>';
						}
					}

					// Obtenemos los usuarios a notificar
					$aClientes = tep_db_query( 'SELECT * FROM products_notifications WHERE products_id = ' . $aStock['products_id'] . ' AND atributo = "' . $aStock['products_stock_attributes'] . '";' );

					// Recorremos las notificaciones
					while( $aCliente = tep_db_fetch_array( $aClientes ) )
					{
						// Cambiamos el texto del email
						if( $aCliente['idioma'] == 3 )
						{
							$sEmail = $html_email_sp;
							$sAsunto = 'Aviso de reposición de producto - ' . STORE_NAME;
						}
						else
						{
							$sEmail = $html_email_en;
							$sAsunto = 'Notice of replacement product - ' . STORE_NAME;
						}

						$sEmail = str_replace( array(
							'{NOMBRE_CLIENTE}',
							'{NOMBRE_PRODUCTO}',
							'{ATRIBUTOS_PRODUCTO}',
							'{ENLACE_PRODUCTO}'
						),array(
							$aCliente['customers_firstname'],
							$aStock['products_name'],
							'<br/>' . $aAtributos[$aCliente['idioma']],
							HTTP_SERVER . '/product_info.php?products_id=' . $aStock['products_id']
						), $sEmail );
					
						// Enviamos el email
						tep_mail( $aCliente['customers_firstname'], $aCliente['customers_email_address'], $sAsunto, $sEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS_NOREPLY );

						// Eliminamos el registro de aviso
						tep_db_query( 'DELETE FROM products_notifications WHERE id = ' . $aCliente['id'] . ';' );
					}
				}
				else
					continue;
			}
		}
		
		// Obtenemos el producto
		$aStock = tep_db_query( 'SELECT * FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) WHERE p.products_id = ' . $aUsuario['products_id'] );

		// Si hemos encontrado el producto
		if( tep_db_num_rows( $aStock ) > 0 )
		{
			$aStock = tep_db_fetch_array( $aStock );
			
			// Si tiene stock
			if( $aStock['products_quantity'] > 0 )
			{
				// Obtenemos los usuarios a notificar
				$aClientes = tep_db_query( 'SELECT * FROM products_notifications WHERE products_id = ' . $aStock['products_id'] );

				// Recorremos las notificaciones
				while( $aCliente = tep_db_fetch_array( $aClientes ) )
				{
					// Cambiamos el texto del email
					if( $aCliente['idioma'] == 3 )
					{
						$sEmail = $html_email_sp;
						$sAsunto = 'Aviso de reposición de producto - ' . STORE_NAME;
					}
					else
					{
						$sEmail = $html_email_en;
						$sAsunto = 'Notice of replacement product - ' . STORE_NAME;
					}

					$sEmail = str_replace( array(
						'{NOMBRE_CLIENTE}',
						'{NOMBRE_PRODUCTO}',
						'{ATRIBUTOS_PRODUCTO}',
						'{ENLACE_PRODUCTO}'
					),array(
						$aCliente['customers_firstname'],
						$aStock['products_name'],
						'',
						HTTP_SERVER . '/product_info.php?products_id=' . $aStock['products_id']
					), $sEmail );

					// Enviamos el email
					tep_mail( $aCliente['customers_firstname'], $aCliente['customers_email_address'], $sAsunto, $sEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );

					// Eliminamos el registro de aviso
					tep_db_query( 'DELETE FROM products_notifications WHERE id = ' . $aCliente['id'] . ';' );
				}	
			}
		}
	}
	// Email informativo
mail( 'f.rodriguez@francobordo.com', '[CORRECTO] Notificador de productos', 'Se ha actualizado con exito la tienda Francobordo.', 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=UTF-8' . "\r\n" . 'From:info@francobordo.com' . "\r\n" );

?>