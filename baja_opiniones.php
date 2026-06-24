<?php
	// baja_opiniones.php (2026-06-23)
	// Baja de UN CLIC (sin login) de las invitaciones a opinar. El token es el `uniqid` de la opinión
	// (no adivinable). Registra la baja por EMAIL en `opinion_optout`; `cron_opiniones.php` la respeta
	// (envía a todos los clientes EXCEPTO los que figuran en esa tabla).
	require( 'includes/application_top.php' );

	$sUniqid = tep_db_prepare_input( $_GET['o'] ?? '' );
	$bBaja   = false;

	if( $sUniqid !== '' )
	{
		$rEmail = tep_db_query( 'select ord.customers_email_address
								 from opinion o
								 inner join orders ord on ord.orders_id = o.orders_id
								 where o.uniqid = "' . $sUniqid . '" limit 1' );

		if( $rEmail && tep_db_num_rows( $rEmail ) > 0 )
		{
			$aEmail = tep_db_fetch_array( $rEmail );
			$sEmail = trim( (string) $aEmail['customers_email_address'] );

			if( $sEmail !== '' )
			{
				tep_db_query( 'insert ignore into opinion_optout (email, opted_out_at) values ("' . tep_db_input( $sEmail ) . '", now())' );
				$bBaja = true;
			}
		}
	}

	$breadcrumb->add( 'Baja de invitaciones a opinar', tep_href_link( 'baja_opiniones.php' ) );

	require( DIR_THEME . 'html/header.php' );
	require( DIR_THEME . 'html/column_left.php' );

	echo '<div class="web-cntd" style="padding:2em 1em; text-align:center; min-height:220px;">';
	if( $bBaja )
		echo '<h1>Baja realizada</h1><p>No volverás a recibir invitaciones a opinar sobre tus compras. Gracias.</p>';
	else
		echo '<h1>Enlace no válido</h1><p>No hemos podido procesar la baja. Si lo necesitas, <a href="' . tep_href_link( 'contact_us.php' ) . '">contáctanos</a>.</p>';
	echo '<p style="margin-top:1.5em;"><a href="' . tep_href_link( 'index.php' ) . '" class="Button">Volver a la tienda</a></p>';
	echo '</div>';

	include( DIR_THEME . 'html/column_right.php' );
	include( DIR_THEME . 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>
