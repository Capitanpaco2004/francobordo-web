<?php
	/**
	 * Devuelve todas las id hijas recursivamente desde una categoria padre
	 * Argumentos:
	 *     - @param int $nIdParentPrincipal, Categoria desde donde quieres obtener las demas categorias recursivamente
	**/
	function getIdCategoriasHijasRecursivoByIdCategoriaPadre($nIdParentPrincipal)
	{
		// Funcion recursiva
		if( ! function_exists( '_getIdCategoriasHijasRecursivoByIdCategoriaPadre' ) )
		{
			function _getIdCategoriasHijasRecursivoByIdCategoriaPadre($aCategorias, $nIdParent)
			{
				$sIds = '';

				foreach( $aCategorias as $key => $value )
				{
					if( $value == $nIdParent )
					{
						$sIds .= $key . ', '; 
						$sIds .= _getIdCategoriasHijasRecursivoByIdCategoriaPadre($aCategorias, $key);
						
					}
				}
				
				return $sIds;
			}
		}
	
		// Obtenemos todas las categorias para no realizar muchas consultas
		$aDatos = tep_db_query( 'select categories_id, parent_id from categories order by parent_id desc' );
		$aCategorias = array();

		while( $aDato = tep_db_fetch_array( $aDatos ) )
			$aCategorias[$aDato['categories_id']] = $aDato['parent_id'];

		return substr( _getIdCategoriasHijasRecursivoByIdCategoriaPadre($aCategorias, $nIdParentPrincipal), 0, -2);
	}
	
	// Funcion que devuelve el html de un nodo DOM
	function getHtml($dcNode)
	{
		$dcAux = new DOMDocument( "1.0" );
		$dcNodeAux = $dcAux->importNode( $dcNode->cloneNode( true ), true );
		$dcAux->appendChild($dcNodeAux);

		return $dcAux->saveHTML();
	}
	
	// Eliminar directorio recursivo
	function recursiveDelete($str)
	{
		if( is_file($str) )
			return unlink($str);
        elseif( is_dir($str) )
		{
            $scan = glob(rtrim($str,'/').'/*');
            foreach($scan as $index=>$path)
                recursiveDelete($path);

            return rmdir($str);
        }
    }
	
	// Cambia las fechas de formato español a ingles
	function chageDateFormat($sDate)
	{
		$sDate = explode( '/', $sDate );
		
		return $sDate[2] . '/' . $sDate[1] . '/' . $sDate[0];
	}
	
	// Obtenemos los banners
	function getAllBanners()
	{
		// Variables
		$aReturn = array();
		
		// Consultamos los grupo de cliente
		$aDatos = tep_db_query( 'SELECT banners_title, banners_id
								 FROM banners
								 where status = 1
								 order by banners_title asc' );

		// Recorremos grupos de cliente
		while( $aDato = tep_db_fetch_array( $aDatos ) )
			$aReturn[] = array( 'id' => $aDato['banners_id'], 'text' => $aDato['banners_title'] );

		return $aReturn;
	}
	
	// Obtenemos los grupos de cliente
	function getAllGruposClientes()
	{
		// Variables
		$aReturn = array();
		
		// Consultamos los grupo de cliente
		$aDatos = tep_db_query( 'SELECT customers_group_id, customers_group_name
								 FROM customers_groups
								 order by customers_group_id asc' );

		// Recorremos grupos de cliente
		while( $aDato = tep_db_fetch_array( $aDatos ) )
			$aReturn[] = array( 'id' => $aDato['customers_group_id'], 'text' => $aDato['customers_group_name'] );

		return $aReturn;
	}

	// Dado un tamaño de imagen y un tamaño maximo, tanto ancho como alto, escala las dimensiones si sobrepasan el maximo permitido
	function scaleSize($nWidth, $nHeight, $nWidthMax, $nHeightMax)
	{
		// Si el alto supera lo permitido reducimos
		if( $nHeight > $nHeightMax )
		{
			$nWidth  = (int)( ( $nHeightMax / $nHeight ) * $nWidth );
			$nHeight = $nHeightMax;
		}

		// Si el ancho supera lo permitido reducimos
		if( $nWidth > $nWidthMax )
		{
			$nHeight = (int)( ( $nWidthMax / $nWidth ) * $nHeight);
			$nWidth  = $nWidthMax;
		}
		
		return array( 'WIDTH' => $nWidth, 'HEIGHT' => $nHeight );
	}

	// Obtenemos todos los boletines que existen
	function getAllBoletines()
	{
		// Variables
		$aDirs = scandir( DIR_EDITOR_BOLETINES_HTML );
		$aReturn = array();

		foreach( $aDirs as $sName )
		{
			if( in_array( $sName, array( '.', '..' ) ) )
				continue;

			$aReturn[] = array( 'id' => $sName, 'text' => $sName );
		}

		return $aReturn;
	}
	
	// Obtenemos todos los theme de productos que existen
	function getAllThemeProducto()
	{
		// Variables
		$aDirs = scandir( DIR_EDITOR_BOLETINES_THEME . 'producto/' );
		$aReturn = array();

		foreach( $aDirs as $sName )
		{
			if( in_array( $sName, array( '.', '..' ) ) )
				continue;

			$aReturn[] = array( 'id' => $sName, 'text' => ucfirst( $sName ) );
		}
	
		return $aReturn;
	}

	// Obtenemos todos los theme de email que existen
	function getAllThemeEmail()
	{
		// Variables
		$aDirs = scandir( DIR_EDITOR_BOLETINES_THEME . 'email/' );
		$aReturn = array();

		foreach( $aDirs as $sName )
		{
			if( in_array( $sName, array( '.', '..' ) ) )
				continue;

			$aReturn[] = array( 'id' => $sName, 'text' => ucfirst( $sName ) );
		}
	
		return $aReturn;
	}
?>