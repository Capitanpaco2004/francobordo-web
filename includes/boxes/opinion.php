<?php
	// Total de opiniones
	$aDatos = tep_db_query( 'select count(id_opinion) as cantidad from opinion where status_aprobado = "true"' );
	$aDatos = tep_db_fetch_array( $aDatos );
	$nCantidad = $aDatos['cantidad'];
	
	if( $nCantidad >= SISTEMA_OPINION_BOX_CANTIDAD )
	{
		// Suma total de puntos
		$aDatos = tep_db_query( 'select sum(general) as suma from opinion where status_aprobado = "true"' );
		$aDatos = tep_db_fetch_array( $aDatos );
		$nTotalPuntos = $aDatos['suma'];

		// Obtenemos el porcentaje
		$nPorcentaje = round( ($nTotalPuntos * 5) / ($nCantidad * 5), 1 );
		$nPorcentaje = (136 / 5) * $nPorcentaje;

		// Obtenemos una opinion al azar
		$aDatoOpinion = tep_random_select( 'select o.comentario_general from opinion o where o.status_aprobado = true order by rand() limit 1' );
		
		// Theme
		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
	}
?>