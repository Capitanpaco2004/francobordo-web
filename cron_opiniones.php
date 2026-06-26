<?php
	// Libreria oscommerce
	include( 'includes/application_top.php' );
define('SISTEMA_OPINION_DIAS_DELETE', '30');

	$resultado = 0;

	// 2026-06-23: ya NO se filtra por consentimiento de marketing (se envia a todos). Solo respetamos las BAJAS explicitas (opinion_optout).
	$aOptOut = array_values( pharaonix_getArrayAssociativeSql( 'SELECT email FROM opinion_optout', 'email', 'email', false, 1 ) );
		
	# Inicio, sistema de opiniones
	if( SISTEMA_OPINION_ENABLED == 'true' )
	{
	// Limpiamos registros de más de X días sin responder después de haberse enviado el primer email
	tep_db_query('DELETE FROM opinion
						WHERE `email_primero_enviado` = "true"
						AND status = "false"
						AND DATE_FORMAT( fecha_envio, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), -' . ((int)SISTEMA_OPINION_DIAS_PEDIDO + (int)SISTEMA_OPINION_DIAS_DELETE) . ')');
		if( (int)$_GET['order_id'] > 0 ) /* @daniel.lucia: modificado para recibir el id de pedido. Se llama directamente desde account_history_info y fuera el envio del correo de nuevo */
		{
			$aDatosOpiniones = tep_db_query( 'select id_opinion, orders_id, uniqid from opinion
											  where email_primero_enviado = "false" AND orders_id = '.(int)$_GET['order_id'] );
			  // Si contenemos opiniones a enviar
			if( tep_db_num_rows( $aDatosOpiniones ) > 0 )
			{
				// Recorremos las opiniones
				while( $aDatoOpinion = tep_db_fetch_array( $aDatosOpiniones ) )
				{
					// Consultamos si existe el pedido
					$aDatosOrders = tep_db_query( 'select customers_id, customers_name, customers_email_address, orders_status, date_purchased
												   from orders
												   where orders_id = ' . $aDatoOpinion['orders_id'] );

					// Si no existe el pedido, continuamos con otro y borramos la opinion por que jamas sera contestada
					if( tep_db_num_rows( $aDatosOrders ) == 0 )
					{
						// Fix 2026-05-16: antes era die(0) que mataba todo el cron al primer huérfano.
						// Borramos la opinión huérfana y seguimos con el resto.
						tep_db_query( 'delete from opinion where id_opinion = ' . (int)$aDatoOpinion['id_opinion'] );
						continue;
					}

					// Obtenemos el pedido
					$aDatoOrder = tep_db_fetch_array( $aDatosOrders );

					// 2026-06-23: a TODOS excepto quien se dio de baja (opinion_optout); ya no se filtra por consentimiento.
					if( in_array( $aDatoOrder['customers_email_address'], $aOptOut ) )
					{
						// Actualizamos
						tep_db_query( 'update opinion set email_primero_enviado = true where id_opinion = '  . $aDatoOpinion['id_opinion'] );

						continue;	
					}
					
					// Cargamos el email
					include( DIR_WS_LANGUAGES . 'espanol/modules/UHtmlEmails/Standard/opinion.php' );
					include( DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/opinion.php' );
					$sHtmlEmail = $sHtmlEmail;

					// Debug
					// $aDatoOrder['customers_email_address'] = 'sampedro.denox@gmail.com';

					// Enviamos
					tep_mail( $aDatoOrder['customers_name'], $aDatoOrder['customers_email_address'], OPINION_EMAIL_SUBJECT, $sHtmlEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
					$resultado = 1;
					// Actualizamos
					tep_db_query( 'update opinion set email_primero_enviado = true where id_opinion = '  . $aDatoOpinion['id_opinion'] );
				}
			}
			die($resultado);
		}
		// Comprobamos que tenemos habilitado el primer email
		if( (int)SISTEMA_OPINION_DIAS_PEDIDO > 0 ) /* @daniel.lucia: modificado para recibir el id de pedido. Se llama directamente desde account_history_info */
		{
			// Comprobamos si existen pedidos de hace X dias para enviar email de opinion general
			$aDatosOpiniones = tep_db_query( 'select id_opinion, orders_id, uniqid from opinion
												  where DATE_FORMAT( fecha_envio, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), -' . SISTEMA_OPINION_DIAS_PEDIDO . ')
												  and email_primero_enviado = "false"');

			// Si contenemos opiniones a enviar
			if( tep_db_num_rows( $aDatosOpiniones ) > 0 )
			{
				// Recorremos las opiniones
				while( $aDatoOpinion = tep_db_fetch_array( $aDatosOpiniones ) )
				{
					// Consultamos si existe el pedido
					$aDatosOrders = tep_db_query( 'select customers_id, customers_name, customers_email_address, orders_status, date_purchased
												   from orders
												   where orders_id = ' . $aDatoOpinion['orders_id'] );

					// Si no existe el pedido, continuamos con otro y borramos la opinion por que jamas sera contestada
					if( tep_db_num_rows( $aDatosOrders ) == 0 )
					{
						tep_db_query( 'delete from opinion where id_opinion = ' . $aDatoOpinion['id_opinion'] );
						continue;
					}

					// Obtenemos el pedido
					$aDatoOrder = tep_db_fetch_array( $aDatosOrders );

					// 2026-06-23: a TODOS excepto quien se dio de baja (opinion_optout); ya no se filtra por consentimiento.
					if( in_array( $aDatoOrder['customers_email_address'], $aOptOut ) )
					{
						// Actualizamos
						tep_db_query( 'update opinion set email_primero_enviado = true where id_opinion = '  . $aDatoOpinion['id_opinion'] );

						continue;	
					}
					
					// 2026-06-25: email nativo de 4 dias (opinion de TIENDA) DESACTIVADO por redundancia con Trustpilot (decision del usuario):
					// Trustpilot pasa a ser el unico canal que pide la experiencia de tienda. NO se envia el email; pero se mantiene
					// el marcado email_primero_enviado=true para que la limpieza (DELETE a los 34 dias, arriba) siga purgando la tabla
					// opinion y no crezca sin control. El email de 10 dias (resenas de PRODUCTO -> estrellas en Google) sigue ACTIVO mas abajo.

					// Actualizamos (sin enviar email)
					tep_db_query( 'update opinion set email_primero_enviado = true where id_opinion = '  . $aDatoOpinion['id_opinion'] );
				}
			}
		}


		// Comprobamos que tenemos habilitado el segundo email
		if( (int)SISTEMA_OPINION_DIAS_PRODUCTO > 0 )
		{
			// Comprobamos si existen pedidos de hace 10 dias para enviar email de opinion de productos
			$aDatosOpiniones = tep_db_query( 'select id_opinion, orders_id, uniqid from opinion
											  where DATE_FORMAT( fecha_envio, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), -' . SISTEMA_OPINION_DIAS_PRODUCTO . ')
											  and email_posterior_enviado = "false"' );

			// Si contenemos opiniones a enviar
			if( tep_db_num_rows( $aDatosOpiniones ) > 0 )
			{
				// Recorremos las opiniones
				while( $aDatoOpinion = tep_db_fetch_array( $aDatosOpiniones ) )
				{
					// Consultamos si existe el pedido
					$aDatosOrders = tep_db_query( 'select customers_id, customers_name, customers_email_address, orders_status, date_purchased
												   from orders
												   where orders_id = ' . $aDatoOpinion['orders_id'] );

					// Si no existe el pedido, continuamos con otro
					if( tep_db_num_rows( $aDatosOrders ) == 0 )
						continue;

					// Obtenemos el pedido
					$aDatoOrder = tep_db_fetch_array( $aDatosOrders );

					// 2026-06-23: a TODOS excepto quien se dio de baja (opinion_optout); ya no se filtra por consentimiento.
					if( in_array( $aDatoOrder['customers_email_address'], $aOptOut ) )
					{
						// Actualizamos
						tep_db_query( 'update opinion set email_posterior_enviado = true where id_opinion = '  . $aDatoOpinion['id_opinion'] );

						continue;	
					}
					
					// Cargamos el email
					include( DIR_WS_LANGUAGES . 'espanol/modules/UHtmlEmails/Standard/opinion.php' );
					include( DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/opinion_posterior.php' );
					$sHtmlEmail = $sHtmlEmail;

					// Debug
					//$aDatoOrder['customers_email_address'] = 'sampedro.denox@gmail.com';

					// Enviamos
					tep_mail( $aDatoOrder['customers_name'], $aDatoOrder['customers_email_address'], OPINION_EMAIL_POSTERIOR_SUBJECT, $sHtmlEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

					// Actualizamos
					tep_db_query( 'update opinion set email_posterior_enviado = true where id_opinion = '  . $aDatoOpinion['id_opinion'] );
				}
			}
		}
	}
	# Fin, sistema de opiniones
?>
