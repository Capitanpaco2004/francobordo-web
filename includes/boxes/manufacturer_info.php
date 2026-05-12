<?php
	// Si no nos encontramos en una categoria mostramos todos los fabricantes
	if( $current_category_id == '' )
	{
		$aDatos = tep_db_query( 'select m.manufacturers_id, m.manufacturers_name, m.manufacturers_image
								 from manufacturers m 
								 inner join manufacturers_info mi on(mi.manufacturers_id = m.manufacturers_id)
								 where mi.languages_id = ' . (int)$languages_id . '
								 ORDER BY rand() limit 9' );
	}
	// Si nos encontramos en una categoria mostramos todos los fabricantes de esa categoria y de sus hijos
	else
	{
		// Obtenemos todas las categorias desde la categoria donde nos encontramos
		$sIdCategoria = getIdCategoriasHijasRecursivoByIdCategoriaPadre($current_category_id);

		// Si no obtenemos categoria obtenemos solo la actual
		if( $sIdCategoria == '' )
			$sIdCategoria = $current_category_id;

		$aDatos = tep_db_query( 'select distinct( m.manufacturers_id ), m.manufacturers_name, m.manufacturers_image 
								 from  products p
								 inner join manufacturers m on(m.manufacturers_id = p.manufacturers_id)
								 inner join manufacturers_info mi on(mi.manufacturers_id = m.manufacturers_id)
								 inner join products_to_categories p2c on(p2c.products_id = p.products_id)
								 where p2c.categories_id in(' . $sIdCategoria . ') and mi.languages_id = ' . (int)$languages_id . '
								 ORDER BY rand() limit 9' );
	}

	// Incluimos el html	
	if( tep_db_num_rows( $aDatos ) > 0 )
	{
		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );

		unset( $aDatos );
	}
?>