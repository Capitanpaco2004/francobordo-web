<?php
    include( 'includes/application_top.php' );
	
	// Funciones

	// Comprueba entre diversas formas de concatenacion y deuelve el string valido 
	function checkConcatenacion($sString)
	{
		$aCheck = array( $sString );

		foreach( $aCheck as $sCheck )
		{		
			if( evalSyntax($sCheck . ';') == 1 )
				return $sCheck;
		}

		return false;
	}
	
	function evalSyntax($code)
	{
		$braces = 0;
		$inString = 0;

		// We need to know if braces are correctly balanced.
		// This is not trivial due to variable interpolation
		// which occurs in heredoc, backticked and double quoted strings
		foreach (token_get_all('<?php ' . $code) as $token)
		{
			if (is_array($token))
			{
				switch ($token[0])
				{
				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_START_HEREDOC: ++$inString; break;
				case T_END_HEREDOC:   --$inString; break;
				}
			}
			else if ($inString & 1)
			{
				switch ($token)
				{
				case '`':
				case '"': --$inString; break;
				}
			}
			else
			{
				switch ($token)
				{
				case '`':
				case '"': ++$inString; break;

				case '{': ++$braces; break;
				case '}':
					if ($inString) --$inString;
					else
					{
						--$braces;
						if ($braces < 0) return false;
					}

					break;
				}
			}
		}

		if ($braces)
			return false; // Unbalanced braces would break the eval below
		else
		{
			ob_start(); // Catch potential parse error messages
			$code = eval('if(0){' . $code . '}'); // Put $code in a dead code sandbox to prevent its execution
			ob_end_clean();

			return false !== $code;
		}
	}

	function getRecursivePathFiles($sDirectorio, $sPath = '/', $aAllow = array())
	{
		$aReturn = array();
		$aDatos = scandir( $sDirectorio );

		foreach( $aDatos as $aDato )
		{
			if( !in_array( $aDato, array( '.', '..' ) ) )
			{
				if( is_dir( $sDirectorio . '/' . $aDato ) )
				{
					$aAux = getRecursivePathFiles( $sDirectorio . '/' . $aDato, $sPath . $aDato . '/', $aAllow );

					if( count( $aAux ) > 0 )
						$aReturn = array_merge( $aReturn, $aAux );
				}
				elseif( preg_match( '/(.+)(\.)(' . implode( '|', $aAllow ) . ')$/i', $aDato ) )
					$aReturn[] = $sPath . $aDato;
			}
		}
		
		return $aReturn;
	}

	function getLinesFileUtf8( $sFile, $sCharset = 'UTF-8' )
	{
		$sData = '';

		if( !file_exists( $sFile ) )
			return false;

		if( floatval( phpversion() ) >= 4.3 )
			$sData = file_get_contents( $sFile );
		else
		{
			$flFile = fopen( $sFile, 'r' );

			if( !$flFile )
				return false;

			while( !feof( $flFile ) )
				$sData .= fread( $flFile, filesize( $sFile ) );

			fclose($flFile);
		}

		if( ! isset( $sFile ) )
			return false;

		if( $sData && $sEncoding = mb_detect_encoding( $sData, 'auto', true ) != $sCharset )
			$sData = @mb_convert_encoding( $sData, $sCharset, $sEncoding );
			
		return preg_split( '/\R/', $sData );
	}
	
	function getDefines($sText)
	{
		// Obtenemos los define de la linea, normalmente sera uno por cada linea, pero puede existir el caso que haya mas de un define en una linea
		preg_match_all( "/(define)(\s?)*(\()(.*)(\);$)/Ui", $sText, $aDefines, PREG_PATTERN_ORDER );

		// Si no hemos obtenido nada es que hemos encontrado algun define sin ; al final
		if( count( $aDefines[0] ) == 0 )
			preg_match_all( "/(define)(\s?)*(\()(.*)(\))/Ui", $sText, $aDefines, PREG_PATTERN_ORDER );
			
		return $aDefines;
	}
	
	function clearLine($sLine)
	{
		// Quitamos tabuladores
		$sLine = str_replace( "\t", '', $sLine );
		
		// Quitamos los alt+255
		$sLine = str_replace( " ", '', $sLine );
		
		// Quitamos espacios
		$sLine = trim( $sLine );

		return $sLine;
	}
	
	function getDefineKeysValuesByFile($sRutaCompleta, $aDenegado)
	{
		// Array de retorno
		$aReturn = array();
	
		// Abrimos el archivo
		$flFile = getLinesFileUtf8( $sRutaCompleta );

		// Si hemos obtenido el archivo
		if( $flFile )
		{
			// Cantidad de lineas
			$nTotal = count( $flFile );
			
			// Recorremos las lineas
			for( $nCont = 0; $nCont < $nTotal; $nCont++ )
			{
				// Linea
				$sLine = $flFile[$nCont];

				// Limpiamos la linea
				$sLine = clearLine( $sLine );

				// Comprobamos que la linea obtenida no sea algo que no queremos
				if( in_array( $sLine, $aDenegado ) )
					continue;
				
				// Comprobamos que sea un define
				if( !preg_match( '/^(define)(\s?)(\()/i', $sLine ) )
					continue;

				// Obtenemos los define de la linea
				$aDefines = getDefines( $sLine );
					
				// Si no hemos obtenido nada del define esque esta compuesto en más de una linea
				if( count($aDefines[0]) == 0 )
				{
					$sLine = $flFile[$nCont];

					// Recorremos las lineas hasta que encontremos el final del define
					for( $nContAux = $nCont + 1; $nContAux < $nTotal; $nContAux++ )
					{
						// Limpiamos la linea nueva
						$sLineNew = clearLine( $flFile[$nContAux] );

						// Vamos creando el define
						$sLine .= $sLineNew;

						if( preg_match( '/(\))(\s?);$/i', $sLineNew ) )
							break;
					}

					// Posicionamos el contador que va leyendo las lineas en la nueva posicion
					$nCont = $nContAux;

					// Obtenemos los define de la linea
					$aDefines = getDefines( $sLine );					
				}
				
				// Recorremos los define obtenidos
				foreach( $aDefines[0] as $sLine )
				{
					// echo htmlentities($sLine) . '<br/><br/>-----------------------------------<br/><br/>';
				
					// Inicio, descomponer el define obtenido \\
					// Descomponemos el define obtenido en KEY y VALUE
					//preg_match('/(define)(\s*)(\()((\'|\")*)(?<KEY>[^,]+)((\'|\")*)(\s*)(\,)(\s*)((\'|\")*)(?<VALUE>.+)((\'|\")*)(\s*)(\))(\;?)$/i', $sLine, $aAux);
					preg_match('/(define)(\s*)(\()(?<KEY>[^,]+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);
				
					// Comprobamos que el key sea una llamada a funcion y se ha quedado rota, de ser asi utilizamos otro preg_match para obtener el KEY y VALUE
					if( preg_match( '/\(/i', $aAux['KEY'] ) && ! preg_match( '/\)$/i', $aAux['KEY'] ) )
						preg_match('/(define)(\s*)(\()(?<KEY>.+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);
					// Fin, descomponer el define obtenido \\
					
					// Inicio, limpiamos el key \\
					// Quitamos espacios
					$aAux['KEY'] = trim( $aAux['KEY'] );
					
					// Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
					if( ! preg_match( '/(\'|")(\s*)\.|\.(\s*)(\'|")/i', $aAux['KEY'] ) )
						$aAux['KEY'] = preg_replace( '/^(\'|")|(\'|")$/i', '', $aAux['KEY'] );
					// Fin, limpiamos el key \\

					// Inicio, limpiamos el value \\
					// Quitamos espacios
					$aAux['VALUE'] = trim( $aAux['VALUE'] );
					
					// Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
					// if( ! preg_match( '/(\'|")(\s*)\.(.+)|(\s*)\.(\s*)(\'|")(.+)/i', $aAux['VALUE'] ) )
						 // $aAux['VALUE'] = preg_replace( '/^(\'|")|(\'|")$/i', '', $aAux['VALUE'] );

					// Mostramos html como texto para que no afecte cuando se muestre en el input
					$aAux['VALUE'] = htmlentities( $aAux['VALUE'], ENT_QUOTES, "UTF-8");
					// Fin, limpiamos el value \\

					// Añadimos la linea al array
					$aReturn[$aAux['KEY']] = $aAux['VALUE'];
				}
				
				// die();
			}

			return $aReturn;
		}

		return false;
	}

	// Si es AJAX es que vamos a comprobar un key o value del define
	if( isAjax() )
	{
		$sCode = tep_db_prepare_input( $_POST['code'] );

		// Comprobamos que el codigo no comience y termine por comillas si no es asi se lo añadimos nosotros
		// if( !preg_match( '/^\'/i', $sCode ) && !preg_match( '/!\\\'$/i', $sCode ) )
			// $sCode = "'" . $sCode . "'";
		
		// como ha existido varios warning tenemos que limpir la salida antes de devolver echo 1;
		if( checkConcatenacion( $sCode ) !== false )
			echo 1;
		else
			echo 0;
	
		exit(1);
	}
	
	// Variables
	$aIdiomasEditar = array( array( 'id' => '', 'text' => 'Selecciona idioma' ) );
	$aIdiomasBuscar = array( array( 'id' => '*', 'text' => 'Buscar en todos los idiomas' ) );
	$aFilesPath = array();
	$aFilesEditar = array();
	$sAuxIdIdioma = null;
	$aDenegado = array( '<?', '<?php', '?>', '' ); // Lineas denegadas cuando leemos un archivo

	// echo '<pre>';
	// print_r( getDefineKeysValuesByFile( getcwd() . '/../includes/languages/espanol_borrar/prueba.php', $aDenegado ) );
	// die();
	
	// Variables post form
	$sPostIdiomaBuscar = tep_db_prepare_input( $_POST['idioma_buscar'] );
	$sPostIdiomaEditar = tep_db_prepare_input( $_POST['idioma_editar'] );
	$sPostBuscar = tep_db_prepare_input( $_POST['buscar'] );
	
	// Obtenemos los idiomas
	$aIdiomas = tep_get_languages(true);
	
	/* eliminar */
	/*$aIdiomas[69] = array(
		'id' => 69,
		'name' => 'Español borrar',
		'code' => 'es_borrar',
		'image' => 'icon.gif',
		'directory' => 'espanol_borrar'
	);*/
	/* eliminar */

	// Recorremos los idiomas y creamos los array
	foreach( $aIdiomas as $value )
	{
		$aIdiomasEditar[] = array( 'id' => $value['id'], 'text' => $value['name'] );
		$aIdiomasBuscar[] = array( 'id' => $value['id'], 'text' => 'Buscar solo en ' . $value['name'] );
	}
	
	// Si la peticion es post 
	if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	{	
		// Comprobamos la accion
		switch( $_POST['action'] )
		{
			// Cuando pulsamos guardar
			case 'save':
				// Variables
				$aFileIdioma = array();
			
				// Recorremos todo lo que nos venga por post
				foreach( $_POST as $sKeyPost => $sValuePost )
				{
					// Si empieza por val-dx- es un valor de un define
					if( preg_match( '/^val-dx-/i', $sKeyPost ) )
					{
						// Descomponemos el valor
						preg_match('/(?<post_key>.+)-dx-(?<archivo>.+)-dx-(?<key_define>.+)-dx-(?<id_idioma>.+)$/i', $sKeyPost, $sKeyPost);
						
						// Creamos el indice del archivo si no existe
						if( !array_key_exists( $sKeyPost['archivo'], $aFileIdioma ) )
							$aFileIdioma[$sKeyPost['archivo']] = array();

						// Creamos el indice del idioma si no existe, y añadimos todos los define del archivo
						if( !array_key_exists( $sKeyPost['id_idioma'], $aFileIdioma[$sKeyPost['archivo']] ) )
						{
							// Obtenemos la ruta completa del archivo
							if( preg_match( '/idioma_principal/i', $sKeyPost['archivo'] ) ) // Si es el archivo principal del idioma cambiamos la variable ya que solo es un alias para localizarlo
								$sRutaCompleta = DIR_FS_CATALOG_LANGUAGES . $aIdiomas[$sKeyPost['id_idioma']]['directory'] . '/../' . $aIdiomas[$sKeyPost['id_idioma']]['directory'] . '.php';
							else
								$sRutaCompleta = DIR_FS_CATALOG_LANGUAGES . $aIdiomas[$sKeyPost['id_idioma']]['directory'] . '/' . preg_replace( '/_php$/i', '.php', $sKeyPost['archivo'] );
						
							// Guardamos todos los define
							$aFileIdioma[$sKeyPost['archivo']][$sKeyPost['id_idioma']] = getDefineKeysValuesByFile( $sRutaCompleta, $aDenegado );
						}

						// Si el nombre es -dx-delete- eliminamos
						if( $sValuePost == '-dx-delete-' )
						{
							unset( $aFileIdioma[$sKeyPost['archivo']][$sKeyPost['id_idioma']][$sKeyPost['key_define']] );
							continue;
						}
						
						// Modificamos el valor
						$aFileIdioma[$sKeyPost['archivo']][$sKeyPost['id_idioma']][$sKeyPost['key_define']] = tep_db_prepare_input( $sValuePost );
					}
				}

				// Recorremos el array obtenido con las modificaciones
				foreach( $aFileIdioma as $sFile => $aIdioma )
				{
					// Recorremos los idiomas
					foreach( $aIdioma as $sIdIdioma => $aLines )
					{
						// Obtenemos la ruta completa del archivo
						if( preg_match( '/idioma_principal/i', $sFile ) ) // Si es el archivo principal del idioma cambiamos la variable ya que solo es un alias para localizarlo
							$sRutaCompleta = DIR_FS_CATALOG_LANGUAGES . $aIdiomas[$sIdIdioma]['directory'] . '/../' . $aIdiomas[$sIdIdioma]['directory'] . '.php';
						else
							$sRutaCompleta = DIR_FS_CATALOG_LANGUAGES . $aIdiomas[$sIdIdioma]['directory'] . '/' . preg_replace( '/_php$/i', '.php', $sFile );

						// Comienzo del fichero
						$sLinesFile = "<?php\n";

						// Recorremos para ir creando las lineas del archivo
						foreach( $aLines as $key => $value )
						{
							// Comprobamos que el value comience por comillas, si no le añadimos las comillas iniciales y finales
							// if( ! preg_match( '/^\'/i', $value ) )
								// $value = "'" . $value . "'";

							// Comprobamos que el key comience por comillas, si no le añadimos las comillas iniciales y finales
							if( ! preg_match( '/^\'/i', $key ) )
								$key = "'" . $key . "'";

							// Si el valor es nada guardamos dos comillas
							if( $value == '' )
								$value = "''";
								
							$sLinesFile .= "\tdefine( $key, $value );\n";
						}

						// Final del archivo
						$sLinesFile .= '?>';

						// Guardamos el archivo
						file_put_contents( $sRutaCompleta, $sLinesFile, LOCK_EX );
					}
				}

				// Redireccionamos
				$messageStack->add_session( 'Los datos se guardaron correctamente.', 'success' );
				tep_redirect( 'translate.php' );
			break;

			// Por defecto mostramos los define que hemos buscado
			default:
				// Si hemos seleccionado un idioma para editarlo entero
				if( $sPostIdiomaEditar != '' )
				{
					// Variable empleada despues para filtrar los archivos
					$sAuxIdIdioma = $sPostIdiomaEditar;
				
					// Recorremos los idiomas para crear el array de archivos
					foreach( $aIdiomas as $value )
					{
						// Continuamos hasta encontrar el idioma para editar
						if( $value['id'] != $sPostIdiomaEditar )
							continue;

						$aFilesPath = getRecursivePathFiles( DIR_FS_CATALOG_LANGUAGES . $value['directory'], '/', array( 'php' ) );
					}
				}
				else
				{
					// Variable empleada despues para filtrar los archivos
					$sAuxIdIdioma = $sPostIdiomaBuscar;

					// Recorremos los idiomas para crear el array de archivos
					foreach( $aIdiomas as $value )
					{
						// Continuamos hasta encontrar el idioma para editar o si hemos seleccinado todos los idiomas
						if( $value['id'] == $sPostIdiomaBuscar || $sPostIdiomaBuscar == '*' )
							$aFilesPath = array_merge( $aFilesPath, getRecursivePathFiles( DIR_FS_CATALOG_LANGUAGES . $value['directory'], '/', array( 'php' ) ) );
					}
				}

				// Eliminamos registros duplicados del array
				$aFilesPath = array_unique( $aFilesPath );
				
				// Creamos un alias para cuando vayamos a cargar el archivo principal
				$aFilesPath[] = '/../dx_idioma_principal.php';

				// Recorremos los idiomas
				foreach( $aIdiomas as $aIdioma )
				{
					// Continuamos hasta encontrar el idioma seleccionado si no hemos seleccionado buscar en todos los idiomas
					if( $aIdioma['id'] != $sAuxIdIdioma && $sPostIdiomaBuscar != '*' )
						continue;
						
					// Recorremos los archivos que hemos obtenido
					foreach( $aFilesPath as $sPathFile )
					{
						// Variables
						$aLines = array();
						
						// if( $sPathFile != '/prueba.php' )
							// continue;
						
						// Obtenemos la ruta completa del arvhivo
						if( strstr( $sPathFile, 'dx_idioma_principal' ) ) // Si es el archivo principal del idioma cambiamos la variable $sPathFile ya que solo es un alias para localizarlo
							$sRutaCompleta = DIR_FS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/../' . $aIdioma['directory'] . '.php';
						else
							$sRutaCompleta = DIR_FS_CATALOG_LANGUAGES . $aIdioma['directory'] . $sPathFile;

						// Obtenemos un array con los key y value de cada uno de los define
						$aDefinesKeyValue = getDefineKeysValuesByFile( $sRutaCompleta, $aDenegado );
						
						// Si hemos obtenido resultado
						if( $aDefinesKeyValue )
						{
							// Creamos el indice en el array si no existe
							if( !array_key_exists( $sPathFile, $aFilesEditar ) )
								$aFilesEditar[$sPathFile] = array();
							
							// Recorremos los defines y values
							foreach( $aDefinesKeyValue as $key => $value )
							{
								// Creamos el indice del KEY en el array si no existe
								if( !array_key_exists( $key, $aFilesEditar[$sPathFile] ) )
									$aFilesEditar[$sPathFile][$key] = array();
									
								// Si estamos buscando
								if( $sPostBuscar != '' )
								{
									// Comprobamos si la cadena no se encuentra dentro de la cadena
									if( !strstr( strtolower( $value ), htmlentities( strtolower( $sPostBuscar ), ENT_QUOTES, "UTF-8" ) ) && !strstr( strtolower( $key ), htmlentities( strtolower( $sPostBuscar ), ENT_QUOTES, "UTF-8" ) ) )
									{
										// Si el key no tiene mas textos eliminamos
										if( count( $aFilesEditar[$sPathFile][$key] ) == 0 )
											unset( $aFilesEditar[$sPathFile][$key] );

										continue;
									}
								}
								
								// Añadimos
								$aFilesEditar[$sPathFile][$key][$aIdioma['id']] = $value;
							}
						}
					}
				}

				// echo '<pre>';
				// print_r($aFilesEditar);
				// die();
			break;
		}
	}
	
	// Si no hemos seleccionado nada por defecto sera todo
	if( $sPostIdiomaBuscar == '' )
		$sPostIdiomaBuscar = '*';
	
	include( THEME . 'html/header.php' );
?>
<div>
<div class="toolbarHead">
	<div class="hdr-tlbr">
		<h1 class="pageHeading" style="top: 14px;">Traducciones</h1>
		
		<?php if( $_SERVER['REQUEST_METHOD'] == 'POST' ): ?>
			<div class="btn-right">
				<a style="margin-right: 20px;" href="<?php echo tep_href_link( 'translate.php' ); ?>"><img src="images/icons/icon_back.png" class="dx-hovr"/></a>
				<a href="javascript:void(0);" id="binfo"><img src="images/icons/icon_informacion.png" class="dx-hovr"/></a>
				<a href="javascript:void(0);" id="tgle-fles" data-open="false" class="tgle"><img src="images/icons/icon_mostrar_ocultar.png" class="dx-hovr"/></a>
				<a href="javascript:void(0);" id="form-save" class="save"><img src="images/icons/icon_save.png" class="dx-hovr"/></a>
			</div>
		<?php endif; ?>
	</div>
</div>
</div>			

<form action="<?php echo tep_href_link( 'translate.php' ); ?>" method="post" id="form-trdc">
	<div class="fluid grid">
		<div class="box-tbl grid6" style="margin-top: 10px;">
			<div class="box-head">
				<h6>Buscar por idioma</h6>
				<div class="clear"></div>
			</div>
			<div class="formRow">
				<div class="grid5">
					<input style="margin: 2px; padding-right: 29px;" type="text" name="buscar" value="<?php echo ($sPostBuscar ? str_replace( '"', "&quot;", $sPostBuscar ) : 'Buscar texto...'); ?>"/>
					<img style="top: 8px;" class="fieldIcon" alt="" src="theme/web/images/icons/usual/icon-search.png">
				</div>
				<div class="grid5">
					<?php echo tep_draw_pull_down_menu( 'idioma_buscar', $aIdiomasBuscar, $sPostIdiomaBuscar ); ?>
				</div>
				<div class="grid2">
					<input type="submit" value="Buscar" class="buttonS bBlue" style="float: right; margin: 0px;">
				</div>			
				<div class="clear"></div>
			</div>
		</div>
		
		<div class="box-tbl grid6" style="margin-top: 10px;">
			<div class="box-head">
				<h6>Editar por idioma</h6>
				<div class="clear"></div>
			</div>
			<div class="formRow">
				<div class="grid10" style="margin-top: 0px;">
					<?php echo tep_draw_pull_down_menu( 'idioma_editar', $aIdiomasEditar, $sPostIdiomaEditar ); ?>
				</div>
				<div class="clear"></div>
			</div>
		</div>
	</div>
</form>
	

			
<table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
        <td width="<?php echo BOX_WIDTH; ?>" valign="top">
            <table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
                <?php require( DIR_WS_INCLUDES . 'column_left.php' ); ?>
            </table>
        </td>
        <td width="100%" valign="top">			
			<?php
				// Si contenemos archivos...
				if( count($aFilesEditar) > 0 )
				{
					// Variable de control para saber si hemos encontrado algo o no
					$bEncontrado = false;
				
					echo '<form action="' . tep_href_link( 'translate.php' ) . '" method="post" id="form-trnl">';
						echo '<input name="action" value="save" type="hidden"/>';
						echo '<input name="buscar" value="' . $sPostBuscar . '" type="hidden"/>';
						echo '<input name="idioma_buscar" value="' . $sPostIdiomaBuscar . '" type="hidden"/>';
						echo '<input name="idioma_editar" value="' . $sPostIdiomaEditar . '" type="hidden"/>';
					
						// Pintamos los archivos obtenidos
						foreach( $aFilesEditar as $sPathFile => $aKey )
						{					
							$nCantidadKey = count( $aKey );
						
							if( $nCantidadKey == 0 )
								continue;

							echo '<div class="trdc-cntd">';
								echo '<div class="titl">';

									// Si el archivo es el principal del idioma
									if( strstr( $sPathFile, '../dx_idioma_principal.php' ) )
										$sPathFile = 'Idioma principal';
									else
										$sPathFile = preg_replace( '/^\//i', '', $sPathFile );

									echo '<p data-open="false">' . $sPathFile . ' <span>(' . $nCantidadKey . ')</span></p>';
									echo '<a href="javascript:void(0);" class="buttonS bBlack">Añadir texto</a>';
									//echo '<a href="javascript:void(0);" class="mtro-btom mtro-btom-gren">Añadir texto</a>';
								echo '</div>';
								
								echo '<div class="trdc-cntd-tgle">';
									foreach( $aKey as $sKey => $aTextos )
									{
										// Habilitamos que hemos encontrado algo en la busqueda
										$bEncontrado = true;
									
										$sNameInput = $sPathFile . '-dx-' . $sKey;
									
										echo '<div class="cntd">';
											echo '<div class="cntd-text" style="width:18%;">';
												echo '<label class="mtro-form-labl">Identificador</label>';
												echo '<div class="mtro-form-inpt key">';
													echo '<div class="icon"></div>';
													echo '<input type="text" readonly="readonly" name="key-dx-' . $sNameInput . '" value="' . $sKey . '"/>';
												echo '</div>';
											echo '</div>';
											
											$nCantidad = count( $aTextos );
											$nMargin = $nCantidad * ($nCantidad - 1);
											$nWidth = (80 - $nMargin) / $nCantidad;
											
											foreach( $aTextos as $sIdIdioma => $sValue )
											{
												echo '<div class="cntd-text" style="width:' . $nWidth . '%;">';
													echo '<label class="mtro-form-labl">Introduce texto</label>';
													echo '<div class="mtro-form-inpt img dlte">';
														echo '<div class="icon" style="background-image:url(\'../includes/languages/' . $aIdiomas[$sIdIdioma]['directory'] . '/images/' . $aIdiomas[$sIdIdioma]['image'] . '\');"></div>';
														echo '<input class="check" type="text" name="val-dx-' . $sNameInput . '-dx-' . $sIdIdioma . '" value="' . $sValue . '"/>';
														echo '<div class="dlte"></div>';
													echo '</div>';
												echo '</div>';
											}
											
										echo '</div>';
									}
								echo '</div>';
							echo '</div>';
						}

						// Si no hemos encontrado nada
						if( !$bEncontrado )
							echo $messageStack->show( array( 'class' => 'eror', 'text' => 'El texto "' . $sPostBuscar . '" no ha sido encontrado.' ) );

					echo '</form>';
				}
			?>
		</td>
	</tr>
</table>

<?php require(THEME . 'html/footer.php'); ?>

<script type="text/javascript" src="js/waypoints.min.js"></script>
	
<script type="text/javascript">
	// Variable que contiene un elemento donde queremos que se desplace el scroll cuando termine el efecto de abrir ventana
	var dmElementScrollPosition = null;
	// Comprueba si ajax esta funcionnado o no
	var bAjax = false;

	// Boton de informacion
	$("#binfo").click( function(e)
	{
		if(e)e.stopPropagation();
		alert( "- Para introducir de forma correcta un texto traducido, éste debe de ir SIEMPRE entre dos caracteres de comillas simples, como en el siguiente ejemplo: 'texto correcto'\n- Para unir cadenas de texto podemos usar el caracter punto, como el siguiente ejemplo: 'texto ' . 'unido'\n- El sistema antes de insertar textos traducidos, revisa si el texto enviado es correcto y cumple con las normativas web");
	});


	// Funcion que cuando realizamos focus en un input envia un ajax para comprobar el codigo
	var fnFocusCheckCode = function()
	{
		var elmt = jQuery(this);
	
		// Ajax funcionnado
		bAjax = true;
	
		// Enviamos petición ajax para comprobar si es un php válido
		jQuery.ajax({
			type: "POST",
			url: "translate.php",
			data: {code: jQuery(elmt).val()},
			success: function(msg)
			{
				// Si el html resultante no es 1 mostramos error
				if( msg != "1" )
					jQuery(elmt).parent().css( "borderColor", "red" ).attr("data-error", "true");
				else
					jQuery(elmt).parent().css( "borderColor", "#CCCCCC" ).attr("data-error", "false");
					
				// Ajax deja de funcionar
				bAjax = false;
			}
		});
	};
	
	// Funcion que se encarga de eliminar el texto del translate
	var fnDeleteText = function(e)
	{
		if( confirm( "¿Deseas eliminar este texto?. Si guardas no podras recuperarlo más" ) )
		{
			jQuery(this).prev().val("-dx-delete-");
			jQuery(this).parent().attr("data-error", "false");
			jQuery(this).parent().parent().parent().css( "display", "none" );
		}
	}
	
	jQuery(document).ready(function()
	{
		// Inicio, check code php \\
		jQuery(".check").focusout(fnFocusCheckCode);
		// Fin, check code php \\
	
				// Inicio, combobox \\
		jQuery(".mtro-form-cmbo select").change(function(e)
		{
			jQuery(this).parent().find("span").text( jQuery(this).find("option:selected").text() );
		});
		
		jQuery(".mtro-form-cmbo span").text( jQuery(".mtro-form-cmbo select option:selected").text() );
		// Fin, combobox \\
		
		// Cuando pulsamos sobre editar por idioma enviamos el form automaticamente
		jQuery('#form-trdc select[name="idioma_editar"]').change(function(e)
		{
			if( jQuery(this).val() == "" )
				return false;
			
			jQuery('#form-trdc input[name="buscar"]').val("");
			jQuery('#form-trdc select[name="idioma_buscar"]').val(jQuery(this).val());
			jQuery("#form-trdc").submit();
		});

		// Cuando pulsamos sobre el combobox de buscar por idioma movemos el combobox de editar para que no haya errores
		jQuery('#form-trdc select[name="idioma_buscar"]').change(function(e)
		{		
			jQuery('#form-trdc select[name="idioma_editar"]').val(jQuery(this).val());
		});
		
		
		// Cuando pulsamos sobre un title de los archivos desplegamos
		jQuery(".titl p").click(function(e)
		{
			var dmContenedor = jQuery(this).parent().parent().find(".trdc-cntd-tgle");
		
			if( dmContenedor.css("display") == "none" )
				jQuery(this).attr( "data-open", "true" );
			else
				jQuery(this).attr( "data-open", "false" );
		
			dmContenedor.slideToggle("slow", function()
			{
				// Si tenemos un elemento posicionamos el scroll
				if( dmElementScrollPosition )
				{
					var dmAux = dmElementScrollPosition;
					jQuery('html,body').animate({scrollTop: jQuery(dmElementScrollPosition).offset().top});
					dmElementScrollPosition = null;
				}
			});
		});
		
		// Cuando pulsamos sobre mostrar o ocultar todo
		jQuery("#tgle-fles").toggle(function()
		{
			jQuery('.titl p[data-open="false"]').trigger("click");
		}, function()
		{
			jQuery('.titl p[data-open="true"]').trigger("click");
		});
		
		// Eliminar texto
		jQuery( ".mtro-form-inpt .dlte" ).click(fnDeleteText);
		
		// Añadir texto
		jQuery( ".titl .buttonS" ).click(function(e)
		{
			// Detenemos evento
			if(e)e.stopPropagation();

			// Identificador para el nuevo key de idioma
			var sIdentificador = "";

			// Lanzamos la ventana para introducir el nombre identificativo hasta encontrar un identificador correcto
			while( true )
			{
				var sIdentificador = prompt( "Introduce un nombre identificativo, este debe ser único y no podrá tener caracteres extraños ni espacios (Ejemplo TEXT_EJEMPLO).", sIdentificador );

				// Comprobamos que no exista ya
				if( jQuery(this).parent().next().find("input[value=" + sIdentificador + "]").length > 0 )
					continue;

				if( sIdentificador == null )
					return false;
					
				// Comprobamos si ha introducido caracteres validos
				if( sIdentificador.match(/^[a-z0-9_]+$/i ) != null )
					break;
			}
			
			// Cantidad de elementos
			var nCantidad = jQuery(this).parent().next().find(".cntd").length;
			
			// Seleccionamos el ultimo elemento para clonar
			var dmElement = jQuery(this).parent().next().find(".cntd:last");
			
			// Clonamos
			var dmElementClone = jQuery(dmElement).clone();

			dmElementClone.css("backgroundColor", "#F7F7F7");
			
			// Evento borrar
			dmElementClone.find(".mtro-form-inpt .dlte").click( fnDeleteText );
			
			// Vaciamos los inputs
			dmElementClone.find("input").val("").focusout( fnFocusCheckCode );
					
			// Input key
			jQuery(dmElementClone.find("input")[0]).val(sIdentificador).attr( "name", jQuery(dmElementClone.find("input")[0]).attr( "name" ).replace( /\.php(.+)$/, ".php-dx-" + sIdentificador ) );

			// Input value
			var aAux = jQuery(dmElementClone.find("input")[1]).attr( "name" ).split( "-dx-" );
			jQuery(dmElementClone.find("input")[1]).attr( "name", "val-dx-" + aAux[1] + "-dx-" + sIdentificador + "-dx-" + aAux[3] );
			
			// Asignamos el elemento
			dmElementScrollPosition = dmElementClone;
			
			// Insertamos el nuevo elemento
			jQuery(dmElementClone).insertAfter( dmElement );
			
			// Comprobamos si no esta desplegado, si es asi desplegamos
			if( jQuery(this).parent().next().css("display") == "none" )
				jQuery(this).parent().find("p").trigger("click");
			else // Si no movemos scroll
			{
				jQuery('html,body').animate({scrollTop: jQuery(dmElementScrollPosition).offset().top});
			}
		});
		
		$("#form-save").click(function(e)
		{
			if(e)e.stopPropagation();
			
			// Ajax tiene que estar sin funcionar antes de guardar
			if( bAjax )
				return false;
			
			// Comprobamos si existen errores
			var aAux = jQuery("#form-trnl").find("div[data-error=true]");
			if( aAux.length > 0 ) 
			{
				var sSeccion = jQuery(aAux[0]).parent().parent().parent().parent().find("p").text().replace( / .+$/, "" );
				var sIdentificador = jQuery(aAux[0]).parent().parent().find("input").val()
				
				alert( "Antes de guardar debes solucionar el fallo que existe en el identificador \"" + sIdentificador + "\" de la sección \"" + sSeccion + "\"." );

				return false;
			}

			// Enviamos el formulario
			$('#form-trnl').submit();
		});
	});
</script>
	
<?php require(DIR_WS_INCLUDES . 'application_bottom.php');  ?>