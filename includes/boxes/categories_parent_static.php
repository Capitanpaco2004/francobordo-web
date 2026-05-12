<?php
	// Variables
	$aDatos = null;
	$aCategoriaPadreActual = null;

	// Consulta con las categorias
	$aDatos = tep_db_query( 'select c.categories_id, cd.categories_name
							 from categories c
							 inner join categories_description cd on (cd.categories_id = c.categories_id)
							 where c.parent_id = 0 and cd.language_id = ' . (int)$languages_id . ' and c.categories_status != 0
							 order by c.sort_order asc' );

	// Si hemos obtenido datos cargamos el box html
	if( tep_db_num_rows( $aDatos ) )
	{
		// Obtenemos el la categoria padre que se esta mostrando
		$aCategoriaPadreActual = explode( '_', $cPath );
		$aCategoriaPadreActual = $aCategoriaPadreActual[0];

		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
	}
	
	// Liberamos
	unset( $aDatos, $aCategoriaPadreActual );
?>