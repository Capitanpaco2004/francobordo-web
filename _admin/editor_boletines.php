<?php
	// Sin limite de memoria y tiempo
	ini_set( 'memory_limit', '-1' );
	set_time_limit( -1 );	

	// Definiciones
	define( 'DIR_EDITOR_BOLETINES', 'editor_boletines/' );
	
	// Librerias
	include( 'includes/application_top.php' );
	include( DIR_EDITOR_BOLETINES . 'functions/functions.php' );
	include( DIR_EDITOR_BOLETINES . 'functions/gd.php' );
	include( DIR_EDITOR_BOLETINES . 'classes/dxGdProducts.php' );
	include( DIR_EDITOR_BOLETINES . 'classes/spyc.php' );

	// Definiciones
	define( 'DIR_EDITOR_BOLETINES_THEME', '../boletines/themes/' );
	define( 'DIR_EDITOR_BOLETINES_HTML', '../boletines/html/' );
	define( 'HTTP_BANNER', HTTPS_SERVER . '/images/banners/' );
	define( 'DIR_BANNER', getcwd() . '/../images/banners/' );
	
	// Debug
	include( DIR_EDITOR_BOLETINES . 'modules/debug/index.php' );
		
	// Variables
	$sModule = tep_db_prepare_input( $_GET['m'] );
	$sAction = tep_db_prepare_input( $_GET['a'] );
	
	// Variables globales
	// Variable de theme de email
	if( !tep_session_is_registered('sThemeBoletin') )
	{
		tep_session_register( 'sThemeBoletin' );
		$aAux = getAllThemeEmail();
		$sThemeBoletin = $aAux[0]['id'];
	}

	// Variable grupo de cliente
	if( !tep_session_is_registered('nCustomerGroupId') )
	{
		tep_session_register( 'nCustomerGroupId' );
		$nCustomerGroupId = 0;
	}

	// Variable con el nombre del boletin
	if( !tep_session_is_registered('sNombreBoletin') )
	{
		tep_session_register( 'sNombreBoletin' );
		$sNombreBoletin = '';
	}
	
	// Variable con los padding de los distintos tipos de productos
	if( !tep_session_is_registered('aThemePaddingProducts') )
	{
		$aThemesProductos = getAllThemeProducto();
		$aAux = array();

		// Recorremos los themes de productos
		foreach( $aThemesProductos as $aProducto )
			$aAux[$aProducto['id']] = array( 0, 0, 0, 0 );
		
		tep_session_register( 'aThemePaddingProducts' );
		$aThemePaddingProducts = $aAux;
	}
	
	// Si es ajax
	if( isAjax() )
	{
		// Cargamos modulos
		if( $sModule != '' && file_exists( DIR_EDITOR_BOLETINES . 'modules/' . $sModule . '/index.php' ) )
		{
			include( DIR_EDITOR_BOLETINES . 'modules/' . $sModule . '/index.php' );
			exit();
		}

		// Eliminar boletin
		if( $sModule == 'delete_boletin' )
		{
			if( $sNombreBoletin != '' && file_exists( DIR_EDITOR_BOLETINES_HTML . $sNombreBoletin ) and is_dir( DIR_EDITOR_BOLETINES_HTML . $sNombreBoletin ) )
				recursiveDelete( DIR_EDITOR_BOLETINES_HTML . $sNombreBoletin . '/' );
		}

		// Detenemos script
		exit();
	}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html lang="es">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="language" content="es" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
		<title>Editor de boletines</title>
		<link rel="stylesheet" type="text/css" href="<?php echo DIR_EDITOR_BOLETINES; ?>css/style.css"/>
		<link rel="stylesheet" type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/cupertino/jquery-ui.css"/>
	</head>
	<body style="margin: 0px; padding: 0px;">
		<div id="menu-izqd">
			<ul>
				<li>
					<a href="javascript:void(0);" data-module="theme" data-class="">
						<span class="icon thme"></span>
						<span class="nav-tooltip">Nuevo boletin</span>
					</a>
				</li>
				<li>
					<a href="javascript:void(0);" data-module="categoria" data-class="all">
						<span class="icon catg"></span>
						<span class="nav-tooltip">Añadir categoría</span>
					</a>
				</li>
				<li>
					<a href="javascript:void(0);" data-module="banner" data-class="all">
						<span class="icon bner"></span>
						<span class="nav-tooltip">Añadir banner</span>
					</a>
				</li>
				<li class="sbmenu">
					<a href="javascript:void(0);" data-module="" data-class="">
						<span class="icon vrus"></span>
					</a>
					<ul>
						<li>
							<a href="javascript:void(0);" id="addsepare">
								<span class="icon sepr"></span>
								<span class="nav-tooltip">Añadir separacion</span>
							</a>
						</li>
						<li>
							<a href="javascript:void(0);" title="Añadir texto" data-module="text" data-class="all">
								<span class="icon txto"></span>
								<span class="nav-tooltip">Añadir texto</span>
							</a>
						</li>
						<li>
							<a href="javascript:void(0);" title="Añadir imagen" data-module="image" data-class="">
								<span class="icon image"></span>
								<span class="nav-tooltip">Añadir imagen</span>
							</a>
						</li>
					</ul>
				</li>
				<li>
					<a href="javascript:void(0);" data-module="configuracion" data-class="">
						<span class="icon clnt"></span>
						<span class="nav-tooltip">Configuración</span>
					</a>
				</li>
				<li>
					<a href="javascript:void(0);" data-module="cargar-boletin" data-class="">
						<span class="icon load"></span>
						<span class="nav-tooltip">Cargar boletin</span>
					</a>
				</li>
				<li>
					<a href="javascript:void(0);" data-module="guardar-boletin" data-class="">
						<span class="icon save"></span>
						<span class="nav-tooltip">Guardar/Exportar boletin</span>
					</a>
				</li>
				
				<div class="botm">
					<li>
						<a href="javascript:void(0);" id="deleteBoletin">
							<span class="icon dlte"></span>
							<span class="nav-tooltip">Eliminar boletin</span>
						</a>
					</li>
					<li>
						<a href="index.php" data-module="exit">
							<span class="icon slir"></span>
							<span class="nav-tooltip">Salir del editor</span>
						</a>
					</li>
				</div>
			</ul>
		</div>
		
		<div id="dlte-prdt"></div>
		
		<div id="bton-cntr">
			<a class="new" href="javascript:void(0);"></a>
			<a class="edit" href="javascript:void(0);"></a>
			<a class="dlte" href="javascript:void(0);"></a>
		</div>
		<div id="hoverControl"></div>
		
		<div id="web">
			
		</div>
		
		<div id="lgbox-bg"></div>
		<div id="lgbox-load"></div>
		<div id="lgbox">
			<div id="lgbox-clse"></div>
			<div id="lgbox-titl"></div>
			<div id="lgbox-cntd"></div>
		</div>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
		<script src="<?php echo DIR_EDITOR_BOLETINES; ?>js/tinymce/tinymce.min.js"></script>
		<script src="<?php echo DIR_EDITOR_BOLETINES; ?>js/functions.js"></script>
	</body>
</html>
