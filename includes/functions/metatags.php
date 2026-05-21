<?php
	function metatags()
	{
		// Variables
		global $cPath_array, $languages_id;

		$sHtml = '';
		$sHtmlDefecto = '';

		$aMetas = array(
			'title' => '',
			'description' => ''
		);

		// Si tenemos categorias y no un products_id, esque estamos navegando por categorias
		if( is_array( $cPath_array ) && !array_key_exists( 'products_id', $_GET ) )
		{
			// Damos la vuelta a las categorias para obtener la ultima
			$aAux = array_reverse( $cPath_array );
			$aAux = $aAux[0];

			$aDatos = tep_db_query( 'select cd.categories_seo_title, cd.categories_seo_description
									 from categories_description cd
									 where categories_id = "' . (int)$aAux . '" and language_id = "' . (int)$languages_id . '"' );
			$aDatos = tep_db_fetch_array( $aDatos );

			$aMetas['title'] = trim( $aDatos['categories_seo_title'] ?? '' );
			$aMetas['description'] = trim( $aDatos['categories_seo_description'] ?? '' );

			// Si no tenemos title o description
			if( $aMetas['title'] == '' || $aMetas['description'] == '' )
			{
				// Damos la vuelta a las categorias
				$aAux = array_reverse( $cPath_array );

				// Obtenemos las categorias
				$aDatos = tep_db_query( 'select cd.categories_name
										 from categories_description cd
										 where categories_id in (' . implode( ',', $aAux ) . ') and language_id = "' . (int)$languages_id . '"' );

				while( $aDato = tep_db_fetch_array( $aDatos ) )
					$sHtmlDefecto .= $aDato['categories_name'] . '[dxsepare]';

				// Si no contiene title
				if( $aMetas['title'] == '' )
					$aMetas['title'] = preg_replace( '/ &gt; $/i', '', ucwords( strtolower( str_replace( '[dxsepare]', ' &gt; ', $sHtmlDefecto ) ) ) );

				// Si no contiene description
				if( $aMetas['description'] == '' )
					$aMetas['description'] = preg_replace( '/, $/i', '', 'comprar ' . strtolower( str_replace( '[dxsepare]', ', ', $sHtmlDefecto ) ) );
			}
		}

		// Si estamos navegando por la ficha del producto
		if( array_key_exists( 'products_id', $_GET ) && is_array( $cPath_array ) )
		{
			// Variables
			$sIdProducto = preg_replace( '/\{.+$/i', '', tep_db_prepare_input( $_GET['products_id'] ) );

			// Obtenemos los datos del producto
			$aProducto = tep_db_query( 'select p.products_model, pd.products_description, pd.products_name, pd.products_seo_title, pd.products_seo_description
										from products p
										inner join products_description pd on (p.products_id = pd.products_id)
										where pd.products_id = "' . (int)$sIdProducto . '" and language_id = "' . (int)$languages_id . '"' );

			if( tep_db_num_rows( $aProducto ) > 0 )
			{
				$aProducto = tep_db_fetch_array( $aProducto );

				$aMetas['title'] = trim( $aProducto['products_seo_title'] ?? '' );
				$aMetas['description'] = trim( $aProducto['products_seo_description'] ?? '' );

				// Si no tenemos title o description
				if( $aMetas['title'] == '' || $aMetas['description'] == '' )
				{
					// Variables
					$sHtmlDefecto = '';
					$aAux = '';

					// Damos la vuelta a las categorias
					if( isset( $cPath_array ) && count( $cPath_array ) > 0 )
						$aAux = array_reverse( $cPath_array );

					if( $aAux != '' )
					{
						// Obtenemos las categorias
						$aDatos = tep_db_query( 'select cd.categories_name
												 from categories_description cd
												 where categories_id in (' . implode( ',', $aAux ) . ') and language_id = "' . (int)$languages_id . '"' );

						while( $aDato = tep_db_fetch_array( $aDatos ) )
							$sHtmlDefecto .= $aDato['categories_name'] . '[dxsepare]';
					}

					// Si no contiene title
					if( $aMetas['title'] == '' )
						$aMetas['title'] =  htmlspecialchars( $aProducto['products_name'] ) . ($sHtmlDefecto != '' ? ' &gt; ' . preg_replace( '/ &gt; $/i', '', str_replace( '[dxsepare]', ' &gt; ', $sHtmlDefecto ) ) : '');

					// Si no contiene description
					if( $aMetas['description'] == '' )
						$aMetas['description'] = substr( htmlspecialchars( strip_tags( preg_replace("/[\r\n\t]+|\"/", "", (string)($aProducto['products_description'] ?? '') ) ) ), 0, 250 );

				}
			}
		}

		// Si estamos navegando por noticias
		if( basename( $_SERVER['SCRIPT_NAME'] ) == 'noticias.php' && array_key_exists( 'id', $_GET ) )
		{
			// Obtenemos datos
			$aDatos = tep_db_query( 'select seo_title, seo_description
									 from noticia
									 where id_noticia = "' . (int)tep_db_prepare_input( $_GET['id'] ) . '"' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) )
			{
				$aDatos = tep_db_fetch_array( $aDatos );

				// Si existe title
				if( $aDatos['seo_title'] != '' )
					$aMetas['title'] = $aDatos['seo_title'];
				else
					$aMetas['title'] = $aDatos['titulo'];

				// Si existe description
				if( $aDatos['seo_description'] != '' )
					$aMetas['description'] = $aDatos['seo_description'];
			}
		}

		// Si estamos navegando por information
		if( basename( $_SERVER['SCRIPT_NAME'] ) == 'information.php' && array_key_exists( 'info_id', $_GET ) )
		{
			// Obtenemos datos
			$aDatos = tep_db_query( 'select information_description, information_title, information_seo_title, information_seo_description
									 from information
									 where information_id = "' . (int)tep_db_prepare_input( $_GET['info_id'] ) . '" and language_id = "' . (int)$languages_id . '"' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) )
			{
				$aDatos = tep_db_fetch_array( $aDatos );

				// Si existe title
				if( $aDatos['information_seo_title'] != '' )
					$aMetas['title'] = $aDatos['information_seo_title'];

				// Si existe description
				if( $aDatos['information_seo_description'] != '' )
					$aMetas['description'] = $aDatos['information_seo_description'];

				// Si no tenemos title o description
				if( $aMetas['title'] == '' || $aMetas['description'] == '' )
				{
					// Si no contenemos title
					if( $aMetas['title'] == '' )
						$aMetas['title'] = $aDatos['information_title'];

					// Si no contemos description
					if( $aMetas['description'] == '' )
						$aMetas['description'] = trim( substr( preg_replace("/[\n|\r|\n\r]/", ' ', strip_tags( $aDatos['information_description'] )), 0, 350) );
				}
			}
		}

		// Si estamos filtrando por fabricantes
		if( basename( $_SERVER['SCRIPT_NAME'] ) == 'manufacturers.php' && array_key_exists( 'manufacturers_id', $_GET ) )
		{
			// Obtenemos datos
			$aDatos = tep_db_query( 'select manufacturers_name
									 from manufacturers
										 where manufacturers_id= "' . (int)tep_db_prepare_input( $_GET['manufacturers_id'] ). '"' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) )
			{
				$aDatos = tep_db_fetch_array( $aDatos );
				$aDatos['manufacturers_name'] = ucfirst( strtolower( $aDatos['manufacturers_name'] ) );

					// Si no contiene title
					if( $aMetas['title'] == '' )
						$aMetas['title'] = 'Productos de ' . $aDatos['manufacturers_name'];

					// Si no contiene description
					if( $aMetas['description'] == '' )
						$aMetas['description'] = 'Comprar, ' . $aDatos['manufacturers_name'];

			}
		}

		// Si estamos filtrando por landings
		if( basename( $_SERVER['SCRIPT_NAME'] ) == 'landings.php' && array_key_exists( 'landing_id', $_GET ) )
		{
			// Obtenemos datos
			$aDatos = tep_db_query( 'select landing_title, landing_description
									 from ' . TABLE_LANDINGS_DESCRIPTION . '
									 where landing_id= "' . tep_db_prepare_input( $_GET['landing_id'] ). '" AND language_id = "' . (int)$languages_id . '"' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) )
			{
				$aDatos = tep_db_fetch_array( $aDatos );
				$aDatos['landing_title'] = ucfirst( strtolower( $aDatos['landing_title'] ) );

				// Title
				$aMetas['title'] = $aDatos['landing_title'];

				// Description
				$aMetas['description'] = substr($aDatos['landing_description'] ?? '', 0, 160);

			}
		}

		// Si esta vacio algun meta consultaremos por el archivo
		if( $aMetas['title'] == '' || $aMetas['description'] == '' )
		{
			// Obtenemos datos
			$aDatos = tep_db_query( 'select title, keywords, description
									 from seo_files
									 where file = "' . basename( $_SERVER['SCRIPT_NAME'] ) . '" and language_id = "' . (int)$languages_id . '" limit 1' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) )
			{
				$aDatos = tep_db_fetch_array( $aDatos );

				$aMetas['title'] = $aDatos['title'];
				$aMetas['description'] = strip_tags($aDatos['description']);
			}
		}

		// Si esta vacio algun meta consultaremos los meta por defecto
		if( $aMetas['title'] == '' || $aMetas['description'] == '' )
		{
			// Obtenemos datos
			$aDatos = tep_db_query( 'select title, description
									 from seo_files
									 where file = "default" limit 1' );
			// Si existe
			if( tep_db_num_rows( $aDatos ) )
			{
				$aDatos = tep_db_fetch_array( $aDatos );

				// Si title esta vacio y existe title default
				if( $aMetas['title'] == '' && $aDatos['title'] != '' )
					$aMetas['title'] = $aDatos['title'];

				// Si description esta vacio y existe description default
				if( $aMetas['description'] == '' && $aDatos['description'] != '' )
					$aMetas['description'] = $aDatos['description'];

			}
		}

		// Title
		echo '<title>' . $aMetas['title'] . '</title>' . "\n";


		// Description
		echo '<meta name="description" content="' . htmlspecialchars($aMetas['description'], ENT_QUOTES, "UTF-8") . '"/>' . "\n";

		// NoIndex para páginas marcadas o si existe en el Array GET el valor page
		if (array_key_exists('page', $_GET) || array_key_exists('language', $_GET) || array_key_exists('dxfilter', $_GET) || array_key_exists('orden', $_GET) || array_key_exists('color', $_GET) || array_key_exists('filtro', $_GET)) {
			echo '<meta name="robots" content="noindex,follow" />' . "\n";
		} elseif(!empty($aSeo)) {
			echo '<meta name="robots" content="'.implode(',', $aSeo).'" />' . "\n";
		}

	}
?>