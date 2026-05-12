<?php
	// Preparamos los filtros
	// Si estamos filtrando un fabricante mostramos las categorias de ese fabricante
	if (isset($_GET['manufacturers_id']))
	{
		$aFiltro = array('-1' => array('TEXT' => ($languages_id == 3 ? 'ver todas las categorias' : 'see all categories'), 'ACTION' => ''));

		$sFiltroSql = 'select distinct c.categories_id as id, cd.categories_name as name
					   from ' . TABLE_PRODUCTS . ' p
					   inner join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c on (p.products_id = p2c.products_id)
					   inner join ' . TABLE_CATEGORIES . ' c on (p2c.categories_id = c.categories_id)
					   inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (p2c.categories_id = cd.categories_id)
					   where p.products_status = 1 and cd.language_id = ' . $languages_id . ' and p.manufacturers_id = "' . (int) $_GET['manufacturers_id'] . '"
					   order by cd.categories_name';
	}
	// Si no mostramos los fabricantes filtrados por la categoria
	elseif ($current_category_id)
	{
		$aFiltro = array('-1' => array('TEXT' => ($languages_id == 3 ? 'ver todas las marcas' : 'see all brands'), 'ACTION' => ''));
		$bFiltroFabricanteCategoria = false;

		// Si nos encontramos en el filtro de una categoria principal
		if (count(explode('_', $cPath)) == 1 && array_key_exists('filtro', $_GET))
		{
			$bFiltroFabricanteCategoria = true;
			$sIdsCategorias = getIdCategoriasHijasRecursivoByIdCategoriaPadre($current_category_id);
			$sIdsCategorias = ($sIdsCategorias == '' ? $current_category_id : $sIdsCategorias);
		}

		// Construcción del filtro de categoría
		$condCategoria = $bFiltroFabricanteCategoria
			? 'IN(' . $sIdsCategorias . ')'
			: '= ' . (int)$current_category_id;

		// Consulta optimizada
		$sFiltroSql = '
						SELECT m.manufacturers_id AS id, m.manufacturers_name AS name
						FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c
						STRAIGHT_JOIN ' . TABLE_PRODUCTS . ' p
							ON p.products_id = p2c.products_id
						INNER JOIN ' . TABLE_MANUFACTURERS . ' m
							ON p.manufacturers_id = m.manufacturers_id
						WHERE m.manufacturers_status = 1
						  AND p.products_status = 1
						  AND p2c.categories_id ' . $condCategoria . '
						GROUP BY m.manufacturers_id
						ORDER BY m.manufacturers_name';


	}
	// Si no mostramos todos los fabricantes
	else
	{
		$aFiltro = array('-1' => array('TEXT' => ($languages_id == 3 ? 'ver todas las marcas' : 'see all brands'), 'ACTION' => ''));

		$sFiltroSql = 'select manufacturers_id as id, manufacturers_name as name
					   from manufacturers
					   order by manufacturers_name asc';
	}

	// @Denox. Ticket #RER-310-13126
	if( basename( $_SERVER['SCRIPT_NAME'] ) != 'landings.php' )
	{
		// Obtenemos todos los fabricantes o categorias
		$aDatos = tep_db_query($sFiltroSql);
	
		// Filtro
		while ($aDato = tep_db_fetch_array($aDatos))
			$aFiltro[$aDato['id']] = array('TEXT' => $aDato['name'], 'ACTION' => (isset($_GET['manufacturers_id']) ? 'p2c.categories_id = ' : 'p.manufacturers_id = ') . $aDato['id']);

		// Filtro ordenar
		$aFiltroOrdenar = array('-1' => array('TEXT' => ($languages_id == 3 ? 'ordenar por defecto' : 'order by default')),
								'1' => array('TEXT' => ($languages_id == 3 ? 'ordenar por titulo ascendente' : 'order by title up'), 'ACTION' => 'pd.products_name asc'),
								'2' => array('TEXT' => ($languages_id == 3 ? 'ordenar por titulo descendente' : 'order by title descending'), 'ACTION' => 'pd.products_name desc'),
								'3' => array('TEXT' => ($languages_id == 3 ? 'ordenar por precio descendente' : 'order by price descending'), 'ACTION' => 'final_price desc'),
								'4' => array('TEXT' => ($languages_id == 3 ? 'ordenar por precio ascendente' : 'order by price up'), 'ACTION' => 'final_price asc'));
	}
	else
		$aFiltro = array();
?>