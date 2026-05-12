<?php

    require( 'includes/application_top.php' );

    // Variables
    $sBuscar = strtolower( tep_db_real_escape_string( tep_db_real_escape_string( $_POST['value'] ) ) );
    $aProductos = null;
    $aProducto = null;
	$sWhere = '';
    $sHtml = '';

	// Construimos el where //

	// Empezamos con el primer where de la palabra a buscar
	$sWhere = '(pd.products_name = "' . $sBuscar . '" OR ';

	// Dividimos la palabra enviada
	$aAux = preg_split( "/[\s]|[,]|[.]|[-]/", $sBuscar, -1, PREG_SPLIT_NO_EMPTY );
	$sBuscar = '';
	$aBusquedasPlural = array();
	$aBusquedasSingular = array();

	// Formateamos las palabras de la búsqueda
	foreach( $aAux as $aBusqueda )
	{
		// Eliminamos las palabras demasiado cortas (pronombres, etc)
		if( strlen( $aBusqueda ) > 2 )
		{
			// Si es una preposición, artículo o nexo continuamos
			if( in_array( $aBusqueda, array('a','ante','bajo','cabe','con','contra','de','desde','en','entre','hacia','hasta','para','por','según','sin','so','sobre','tras','del','al') ) ) continue;
			if( in_array( $aBusqueda, array('el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas') ) ) continue;
			if( in_array( $aBusqueda, array('y', 'u', 'o', 'e') ) ) continue;

			// Si es un número vale igual para singular y plural
			if( is_numeric( $aBusqueda ) )
			{
				// Añadimos en singular y plural
				$aBusquedasPlural[] = $aBusqueda;
				$aBusquedasSingular[] = $aBusqueda;

				continue;
			}

			// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces)
			if( preg_match( '/s$/', $aBusqueda ) || preg_match( '/es$/', $aBusqueda ) || preg_match( '/ces$/', $aBusqueda ) )
			{
				// Añadimos al array plural
				$aBusquedasPlural[] = $aBusqueda;

				// Obtenemos su singular
				if( preg_match( '/ces$/', $aBusqueda ) )
					$aBusquedasSingular[] = preg_replace( '/ces$/', 'z', $aBusqueda );
				else if( preg_match( '/es$/', $aBusqueda ) )
					$aBusquedasSingular[] = preg_replace( '/es$/', '', $aBusqueda );
				else if( preg_match( '/s$/', $aBusqueda ) )
					$aBusquedasSingular[] = preg_replace( '/s$/', '', $aBusqueda );
			}
			// Si la palabra está en singular
			else
			{
				// Añadimos al array singular
				$aBusquedasSingular[] = $aBusqueda;

				// Obtenemos su plural
				if( preg_match( '/[a]$|[e]$|[o]$/', $aBusqueda ) )
					$aBusquedasPlural[] = $aBusqueda . 's';
				else if( preg_match( '/[z]$/', $aBusqueda ) )
					$aBusquedasPlural[] = preg_replace( '/z$/', 'ces', $aBusqueda );
				else
					$aBusquedasPlural[] = $aBusqueda . 'es';
			}

			// Recomponemos sBuscar
			$sBuscar .= $aBusqueda . ' ';
		}
	}

	// Limpiamos espacios
	$sBuscar = trim( $sBuscar );

	// Obtenemos todas las combinaciones para palabras plural y singular
	$aBusquedasSingular = combinations( implode( ' ', $aBusquedasSingular ) );
	$aBusquedasPlural = combinations( implode( ' ', $aBusquedasPlural ) );

	// Contruimos los likes
	foreach( $aBusquedasSingular as $aCadena )
		$sWhere .= 'LCASE( pd.products_name ) LIKE "%' . implode( '%', $aCadena ) . '%" OR ';
	foreach( $aBusquedasPlural as $aCadena )
		$sWhere .= 'LCASE( pd.products_name ) LIKE "%' . implode( '%', $aCadena ) . '%" OR ';

	// Busqueda para la descripción
	$sWhere .= 'LCASE( pd.products_description ) = "' . $sBuscar . '" OR ';

	// Contruimos los likes para la descripción
	foreach( $aBusquedasSingular as $aCadena )
		$sWhere .= 'LCASE( pd.products_description ) LIKE "%' . implode( '%', $aCadena ) . '%" OR ';
	foreach( $aBusquedasPlural as $aCadena )
		$sWhere .= 'LCASE( pd.products_description ) LIKE "%' . implode( '%', $aCadena ) . '%" OR ';

	// Eliminamos el ultimo OR
	$sWhere = substr( $sWhere, 0, -4 );

	// Cerramos paréntesis y añadimos el products status
	$sWhere .= ') and p.products_status = 1 AND pd.language_id = ' . (int)$languages_id;

	// Consulta //

    // Consulta de productos
    $aProductos = tep_db_query( 'SELECT p.products_id, p.products_image, pd.products_name, pd.products_description
                                 FROM products p
                                 INNER JOIN products_description pd on(p.products_id = pd.products_id)
                                 WHERE ' . $sWhere . '
                                 ORDER BY pd.products_name ASC
                                 LIMIT 15' );

    // Comprobamos si hemos obtenido productos
    if( tep_db_num_rows( $aProductos ) > 0 )
    {
        // Recorremos los productos
        while( $aProducto = tep_db_fetch_array( $aProductos ) )
        {
            $sHtml .= '<li>';
            $sHtml .= '<a href="' . tep_href_link( 'product_info.php', 'products_id=' . $aProducto['products_id'] ) . '" title="' . $aProducto['products_name'] . '">';
            $sHtml .= tep_image( DIR_WS_IMAGES . 'productos/' . $aProducto['products_image'], $aProducto['products_name'], 50, 50);
            $sHtml .= '<p><h6>' . $aProducto['products_name'] . '</h6>' . truncate( strip_tags( $aProducto['products_description'] ), 100 ) . '</p>';
			$sHtml .= '</a>';
            $sHtml .= '</li>';
        }
    }

    // Pintamos el html resultante
    header( "Content-type:text/html; charset=utf-8" );
    echo $sHtml;
?>