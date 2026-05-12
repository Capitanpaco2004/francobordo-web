<?php
	include( 'includes/application_top.php' );
	include( DIR_WS_LANGUAGES . $language . '/' . basename(__FILE__) );

	// breadcrumb
	$breadcrumb->add( NAVBAR_TITLE, tep_href_link( basename(__FILE__) ) );

	// Variables
	$sWhere = '';

	if( isset( $_GET['search'] ) && $_GET['search'] != '' )
	{
		$sBuscar = strtolower( tep_db_prepare_input( $_GET['search'] ) );

		// Separamos las palabras
		$aWords = preg_split( "/[\s]|[,]|[.]|[-]/", $sBuscar, -1, PREG_SPLIT_NO_EMPTY );

		// Formateamos las palabras de la búsqueda
		foreach( $aWords as $aWord )
		{
			// Eliminamos las palabras demasiado cortas (pronombres, etc)
			if( strlen( $aWord ) > 1 )
			{
				// Si es una preposición, artículo o nexo continuamos
				if( in_array( $aWord, array( 'a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e' ) ) )
					continue;

				// Si es un número
				if( is_numeric( $aWord ) )
				{
					// Añadimos el número en singular y plural
					$aSearchPlural[] = $aWord;
					$aSearchSingular[] = $aWord;
					continue;
				}

				// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
				if( preg_match( '/s$/', $aWord ) || preg_match( '/es$/', $aWord ) || preg_match( '/ces$/', $aWord ) )
				{
					// Añadimos al array plural
					$aSearchPlural[] = $aWord;

					// Añadimos al array singular
					if( preg_match( '/ces$/', $aWord ) )
						$aSearchSingular[] = preg_replace( '/ces$/', 'z', $aWord );
					else if( preg_match( '/es$/', $aWord ) )
						$aSearchSingular[] = preg_replace( '/es$/', '', $aWord );
					else if( preg_match( '/s$/', $aWord ) )
						$aSearchSingular[] = preg_replace( '/s$/', '', $aWord );
				}
				// Si la palabra está en singular
				else
				{
					// Añadimos al array singular
					$aSearchSingular[] = $aWord;

					// Obtenemos su plural
					if( preg_match( '/[a]$|[e]$|[o]$/', $aWord ) )
						$aSearchPlural[] = $aWord . 's';
					else if( preg_match( '/[z]$/', $aWord ) )
						$aSearchPlural[] = preg_replace( '/z$/', 'ces', $aWord );
					else
						$aSearchPlural[] = $aWord . 'es';
				}
			}
		}

		// WHERE //

		// Recorremos las palabras y las añadimos al WHERE para el nombre
		if(!empty($aSearchSingular) && count( $aSearchSingular ) > 0 )
		{
			foreach( $aSearchSingular as $nWord => $aWord )
				$sWhere .= '( ( LCASE( manufacturers_name ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) OR ( LCASE( manufacturers_name ) LIKE "%' . tep_db_input( $aSearchPlural[$nWord] ) . '%" ) ) AND ';
			$sWhere = substr( $sWhere, 0, -4 );
		}
		else
			$sWhere .= '( LCASE( manufacturers_name ) LIKE "%' . tep_db_input( $sBuscar ) . '%" )';
	}

	// Consultamos
	$aRowsManufacturers = tep_db_query( 'select manufacturers_name, manufacturers_id
										 from manufacturers where manufacturers_status = 1 ' . ($sWhere != '' ? ' AND ' . $sWhere : '') . '
										 order by manufacturers_name' );

	if( tep_db_num_rows( $aRowsManufacturers ) == 1 )
	{
		$aRow = tep_db_fetch_array( $aRowsManufacturers );
		tep_redirect( tep_href_link( FILENAME_MANUFACTURERS, 'manufacturers_id=' . $aRow['manufacturers_id'] ) );
	}

	// Cabecera y columna
	include(DIR_THEME. 'html/header.php');
	include(DIR_THEME. 'html/column_left.php');

	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// Columna y pie
	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>