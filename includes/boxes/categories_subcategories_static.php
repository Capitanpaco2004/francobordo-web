<?php

	// Variables
	$aCategoriesParent = array();
	$aSubCategories    = array();


	// Obtenemos todas las categorias padres
	$aDatos = tep_db_query( 'select c.categories_id, cd.categories_name 
							 from ' . TABLE_CATEGORIES . ' c
							 inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (c.categories_id = cd.categories_id)
							 where c.parent_id = 0 and c.categories_status != 0 and cd.language_id=' . (int)$languages_id . '
							 order by sort_order, cd.categories_name' );

	// Si hemos obtenido categorias
	if( tep_db_num_rows( $aDatos ) > 0 )
	{
		// Recorremos las categorias padres
		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			$aCategoriesParent[$aDato['categories_id']] = $aDato['categories_name'];

			// Obtenemos las subcategorias
			$aSubDatos = tep_db_query( 'select c.categories_id, cd.categories_name
										from ' . TABLE_CATEGORIES . ' c
										inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (c.categories_id = cd.categories_id)
										where c.parent_id = ' . $aDato['categories_id'] . ' and c.categories_status != 0 and cd.language_id = ' . (int)$languages_id . '
										order by sort_order, cd.categories_name' );
			// Si contiene subcategorias
			if( tep_db_num_rows( $aSubDatos ) > 0 )
			{
				// Creamos el array para ir añadiendo las subcategorias
				$aSubCategories[$aDato['categories_id']] = array();

				// Recorremos las categorias de las subcategorias
				while( $aSubDato = tep_db_fetch_array( $aSubDatos ) )
					$aSubCategories[$aDato['categories_id']][] = array( 'ID' => $aSubDato['categories_id'], 'TEXT' => $aSubDato['categories_name'] );
			}	
		}
		
		// Incluimos el html
		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
	}
?>