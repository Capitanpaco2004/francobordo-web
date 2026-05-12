<?php
	include( 'includes/application_top.php' );

	// Breadcrumb
	$breadcrumb->add( 'Opiniones', 'opiniones.php' );

	// Variables
	$sId = tep_db_prepare_input( $_GET['id'] );
	$sPaginacion = '';

	// Obtenemos todas las opiniones paginadas
	$aSplitOpiniones = new splitPageResults( 'select date_format(fecha_envio,"%d/%m/%Y") as fecha_envio, o.general, c.customers_firstname, o.comentario_general
											  from opinion o
											  inner join customers c on (c.customers_id = o.customers_id )
											  where o.status_aprobado = true
											  order by fecha_envio desc', 10 );

	$aOpiniones = tep_db_query( $aSplitOpiniones->sql_query );
	
	// Comprobamos la paginacion
	if( $aSplitOpiniones->number_of_rows > 0 )
		$sPaginacion = $aSplitOpiniones->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array( 'page', 'info', 'x', 'y' ) ) );

	include( DIR_THEME. 'html/header.php' );
	include( DIR_THEME. 'html/column_left.php' );

	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// Liberamos
	unset( $sId, $sPaginacion );
	
	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php');
	include( DIR_WS_INCLUDES . 'application_bottom.php' );

?>