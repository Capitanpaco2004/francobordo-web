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
											  order by o.fecha_envio desc', 10 );

	$aOpiniones = tep_db_query( $aSplitOpiniones->sql_query );
	
	// Comprobamos la paginacion
	if( $aSplitOpiniones->number_of_rows > 0 )
		$sPaginacion = $aSplitOpiniones->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array( 'page', 'info', 'x', 'y' ) ) );

	include( DIR_THEME. 'html/header.php' );
	include( DIR_THEME. 'html/column_left.php' );

	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// SEO: datos estructurados de la pagina de Opiniones. Organization + BreadcrumbList.
	// SIN aggregateRating a proposito: las resenas de TIENDA auto-recogidas no son validas para rich results de Google (self-serving, desde 2019).
	// WebSite + SearchAction + LocalBusiness ya se emiten en la home, no se duplican aqui.
	echo '<script type="application/ld+json">' . json_encode( array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type'     => 'Organization',
				'@id'       => 'https://www.francobordo.com/#organization',
				'name'      => 'Francobordo',
				'legalName' => 'Francobordo artículos náuticos, S.L.',
				'url'       => 'https://www.francobordo.com/',
				'logo'      => 'https://www.francobordo.com/theme/web/logo-trans.png',
				'email'     => 'info@francobordo.com',
				'telephone' => '+34 916 52 88 58',
				'sameAs'    => array( 'https://es.trustpilot.com/review/francobordo.com' ),
			),
			array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio',    'item' => 'https://www.francobordo.com/' ),
					array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Opiniones', 'item' => 'https://www.francobordo.com/opiniones.php' ),
				),
			),
		),
	), JSON_UNESCAPED_SLASHES ) . '</script>';

	// Liberamos
	unset( $sId, $sPaginacion );
	
	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php');
	include( DIR_WS_INCLUDES . 'application_bottom.php' );

?>