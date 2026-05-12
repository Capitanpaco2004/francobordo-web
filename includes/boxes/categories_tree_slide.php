<?php
	// Variables
	$aCategorias = array();
	$sIdHijas = getIdCategoriasHijasRecursivoByIdCategoriaPadre($nIdCategoriaPrincipalWeb);
	$aCategoriasActivas = array();
	$aFabricantes = array();

	// Si contenemos una categoria obtenemos todos las padres para poder activar el menu
	if( isset( $current_category_id ) )
	{
		$aAuxs = getCategoriesParent( $current_category_id );

		foreach( $aAuxs as $aAux )
			$aCategoriasActivas[] = $aAux['categories_id'];
	}
	
	// Obtenemos las categorias
	$aDatos = tep_db_query( 'select c.categories_id, cd.categories_name, c.parent_id, c.categories_image
							 from ' . TABLE_CATEGORIES . ' c
							 inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (c.categories_id = cd.categories_id)
							 where c.categories_status != 0 and cd.language_id=' . (int)$languages_id . '
							 and c.categories_id in( ' . $sIdHijas . ' )
							 order by sort_order, cd.categories_name' );

	// Creamos el array principal
	while( $aDato = tep_db_fetch_array( $aDatos ) )
		$aCategorias[] = $aDato;

	// Obtenemos las marcas
	$aMarcas = tep_db_query( 'select distinct m.manufacturers_id, m.manufacturers_name
							  from ' . TABLE_PRODUCTS . ' p
							  inner join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c on (p.products_id = p2c.products_id)
							  inner join ' . TABLE_MANUFACTURERS . ' m on (p.manufacturers_id = m.manufacturers_id)
							  where p.products_status = 1 and p2c.categories_id in (' . $sIdHijas . ')
							  order by m.manufacturers_name' );

	if( tep_db_num_rows( $aMarcas ) > 0 )
	{
		// Rellenamos los fabricantes
		$aFabricantes = array( array( 'id' => 0, 'text' => TEXT_FILTER_MANUFACTURERS ) );
		while( $aMarca = tep_db_fetch_array( $aMarcas ) )
			$aFabricantes[] = array( 'id' => $aMarca['manufacturers_id'], 'text' => $aMarca['manufacturers_name'] );
	}
		
	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
?>