<?php
	// Variables
	$aListas = array();

	// Obtenemos las categorias padres
	$aDatos = tep_db_query( 'select c.categories_id, cd.categories_name, c.parent_id 
							 from ' . TABLE_CATEGORIES . ' c
							 inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (c.categories_id = cd.categories_id)
							 where c.parent_id = 0 and c.categories_status != 0 and cd.language_id=' . (int)$languages_id . '
							 order by sort_order, cd.categories_name' );

	categories_tree_create_list($aDatos);
							 
	function categories_tree_create_list($aDatos)
	{
		global $cPath_array, $aListas, $languages_id;
		
		if( ! isset( $cPath_array ) )
			$cPath_array = array();
		
		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			// Reiniciamos
			$sHref = '';

			// Posicion dentro de array de categorias que estan desplegadas
			$nPos = array_search( $aDato['parent_id'], $cPath_array );

			// Creamos el href si la posicion es igual o mayor a 0 
			if( is_numeric( $nPos ) && $nPos >= 0 )
				for( $nCont = 0; $nCont <= $nPos; $nCont++ )
					$sHref .= $cPath_array[$nCont] . '_';
			
			// Añadimos espacios
			if( is_numeric( $nPos ) )
				$nEspacios = ($nPos + 1) * 3;
			else
				$nEspacios = 0;
				
			// Añadimos al array
			$aListas[] = array( 'ACTIVO' => in_array( $aDato['categories_id'], $cPath_array ), 'ID' => $aDato['categories_id'], 'TEXT' => str_repeat( '&nbsp;', $nEspacios ) . $aDato['categories_name'], 'HREF' => tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $sHref . $aDato['categories_id'] ) );

			// Comprobamos si se encuentra desplegado
			if( in_array( $aDato['categories_id'], $cPath_array ) )
			{
				$aSubDatos = tep_db_query( 'select c.categories_id, cd.categories_name, c.parent_id 
											from ' . TABLE_CATEGORIES . ' c
											inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (c.categories_id = cd.categories_id)
											where c.parent_id = ' . $aDato['categories_id'] . ' and c.categories_status != 0 and cd.language_id = ' . (int)$languages_id . '
											order by sort_order, cd.categories_name' );

				categories_tree_create_list( $aSubDatos );
			}
		}		
	}

	// Incluimos el html
	if( count( $aListas ) > 0 )
		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
?>