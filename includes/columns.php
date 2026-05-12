<?php
	// Si no existe las columnas realizamos consulta para obtener las columnas
	if( ! isset( $aThemeColumn ) )
	{
		$aThemeColumn = array();

		$aDatos = tep_db_query( 'select configuration_column AS cfgcol, location, configuration_title AS cfgtitle, configuration_value AS cfgvalue, configuration_key AS cfgkey, box_heading
								 from ' . TABLE_THEME_CONFIGURATION . ' 
								 WHERE configuration_value = "yes"
								 order by location asc' );

		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			// Si no existe la columna la creamos
			if( !array_key_exists( $aDato['cfgcol'], $aThemeColumn ) )
				$aThemeColumn[$aDato['cfgcol']] = array();

			// Añadimos el box
			$aThemeColumn[$aDato['cfgcol']][] = $aDato;
		}
	}

	// Si existe
	if( array_key_exists( $sColumna, $aThemeColumn ) )
	{
		foreach( $aThemeColumn[$sColumna] as $aBox )
		{
			$aBox['cfgtitle'] = str_replace( array(' ', "'"), array( '_', '' ), $aBox['cfgtitle'] );
			$sFile = $aBox['cfgtitle'] . '.php';
		  
			if( file_exists( DIR_WS_BOXES . $sFile ) ) 
			{
				switch( $sFile )
				{
					case 'categories.php':
						if( USE_CACHE == 'true' && empty( $SID ) )
							echo tep_cache_ . $aBox['cfgtitle'] . _box();
						else
							require( DIR_WS_BOXES . $sFile );
						break;

					case "manufacturers":
						if( USE_CACHE == 'true' && empty( $SID ) )
							echo tep_cache_ . $aBox['cfgtitle'] . _box();
						else
							require(DIR_WS_BOXES . $sFile );
					break;

					case "order_history":
						if( tep_session_is_registered( 'customer_id' ) )
							require( DIR_WS_BOXES . $sFile );
					break;

					default:
						require( DIR_WS_BOXES . $sFile );
					break;
				}
			}
		}
	}
?>