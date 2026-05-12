<?php
	// Aplicacion
	include( 'includes/application.php' );
	
	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_OPINION, '', 'SSL'));
	include( DIR_WS_LANGUAGES . $language . '/comentarios.php' );

	// Header
	include( 'account/includes/header.php' );

	// Consulta
	$sSql = 'select date_format(fecha_envio,"%d/%m/%Y") as fecha_envio, status_aprobado, o.general, c.customers_firstname, o.comentario_general
			  from opinion o
			  inner join customers c on (c.customers_id = o.customers_id )
			  where o.customers_id = "' . (int)$customer_id . '" 
			  order by fecha_envio desc';

	$sSqlCount = 'select count(*) as total from ( ' . $sSql . ' ) as taux';

	$aDatosSlipt = new splitPageResults( $sSql, 25, '*', $sSqlCount );
	
	$aDatos = tep_db_query( $aDatosSlipt->sql_query );

	// Direcciones
	echo '<div class="ccTitle">' . ACCOUNT_OPINION_TITLE . ' (' . $aDatosSlipt->number_of_rows . ')</div>';

	if( tep_db_num_rows( $aDatos ) > 0 )
	{
		echo '<div class="ccCnt ccInfo6">';		
			while( $aDato = tep_db_fetch_array( $aDatos ) ) 
			{
				echo '<div class="column">';
					echo '<div class="user"><i class="fa fa-user"></i></div>';
					echo '<div class="Rating">';
						echo '<span class="stars st' . $aDato['general'] . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></span>';
					echo '</div>';
					echo '<strong class="date">' . $aDato['fecha_envio'] . '</strong>';
					echo '<div class="dscrp">' . $aDato['comentario_general'] . '</div>';
					
					if( $aDato['status_aprobado'] != 'true' )
						echo '<br/>' . $messageStack->show( array( 'text' => ACCOUNT_OPINION_PENDIENTE, 'class' => 'wrng' ) );
				echo '</div>';
			}
		echo '</div>';
				
		// Paginacion
		echo '<div class="pgnc" style="width: 100%;">' . str_replace( basename( FILENAME_ACCOUNT_OPINION ), FILENAME_ACCOUNT_OPINION, $aDatosSlipt->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array( 'page', 'info', 'x', 'y' ) ) ) ) . '</div>';
	}
	else
		echo $messageStack->show( array( 'text' => ACCOUNT_OPINION_NO, 'class' => 'eror' ) );
	

	
	// Footer
	include( 'account/includes/footer.php' );
?>