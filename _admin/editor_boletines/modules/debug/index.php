<?php
	// if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	// {
		// $sHtml = tep_db_prepare_input( $_POST['html'] );		
		// tep_mail(STORE_OWNER, 'rsanchezsergio@gmail.com', 'Prueba de boletin', $sHtml, 'Jose Maria', 'sampedro.denox@gmail.com');
	// }

	// echo '<form action="editor_boletines.php" method="post">
		// <textarea name="html" style="width: 500px; height: 500px;"></textarea>
		// <input type="submit"/>
	// </form>';
	
	// die();
	
	// if( $_SERVER['REMOTE_ADDR'] != '62.117.136.178')
		// die( 'mantenimiento' );
	
	// http://www.e-nuc.com/_admin/editor_boletines.php?theme=vertical&titulo=Controller%20PRO%20CUSTOM%20WIRELESS%20SILVER&precio=19,94%E2%82%AC&imagen=plataps2-ps3-pc2.jpg&tax=1
	/*if( $_SERVER['REMOTE_ADDR'] == '62.117.136.178')
	{
		$sTheme = stripslashes( $_GET['theme'] );
		$sPrecio = stripslashes( $_GET['precio'] );
		$sTitulo = stripslashes( $_GET['titulo'] );
		$sImagen = stripslashes( $_GET['imagen'] );
		$sTax = array_key_exists( 'tax', $_GET ) ? $_GET['tax'] : false;
		$sEnvio = array_key_exists( 'envio', $_GET ) ? $_GET['envio'] : false;
		$sOferta = array_key_exists( 'oferta', $_GET ) ? $_GET['oferta'] : false;
		$sOferta = array_key_exists( 'oferta', $_GET ) ? $_GET['oferta'] : false;
		

		if( $sTax !== false )
			if( $sTax == 0 )
				$sTax = 'IVA NO Incl.';
			else
				$sTax = 'IVA Incl.';

		$objProductos = new dxGdProducts( array(
			'padding' => array( 8, 26.50, 8, 26.50 ),
			'theme' => DIR_EDITOR_BOLETINES_THEME . 'producto/' . $sTheme . '/',
			'titulo' => $sTitulo,
			'directorio_imagen' => getcwd() . '/../images/',
			'imagen' => $_GET['imagen'],
			'precio' => $sPrecio,
			'tax' => $sTax,
			'envio_gratis' => $sEnvio,
			'oferta' => $sOferta,
			'descripcion' => '',
			'producto_estrella' => false,
			'oferta' => $sOferta
		) );

		echo '<img data-theme-64="true" data-theme-id="' . $aDato['products_id'] . '"  style="display: block;" src="data:image/png;base64,' . $objProductos->show(true) . '" />';
		die();
	}*/

	if( array_key_exists( 'debug', $_GET ) )
	{
		$aProductos = array(
			array( 
				'theme' => 'vertical',
				'padding' => array( 0, 0, 6, 6 ),
				'titulo' => 'Hola',
				'imagen' => 'xboxsemi.jpg',
				'precio' => '30,54€',
				'tax' => true,
				'envio_gratis' => false,
				'oferta' => false
			),
			array( 
				'theme' => 'vertical',
				'padding' => array( 2, 1, 6, 6 ),
				'titulo' => 'Hola2',
				'imagen' => 'tabletjxds7300b.jpg',
				'precio' => '32,54€',
				'tax' => true,
				'envio_gratis' => false,
				'oferta' => false
			),
			array( 
				'theme' => 'vertical',
				'padding' => array( 2, 12, 6, 6 ),
				'titulo' => 'Hola32',
				'imagen' => 'antenagrid24.jpg',
				'precio' => '38,54€',
				'tax' => true,
				'envio_gratis' => true,
				'oferta' => false
			)
		);
		
		$nCont = 0;
		
		foreach( $aProductos as $aProducto )
		{
			// Clase con el producto
			$objProductos = new dxGdProducts( array(
				'padding' => $aProducto['padding'],
				'theme' => DIR_EDITOR_BOLETINES_THEME . 'producto/' . $aProducto['theme'] . '/',
				'titulo' => $aProducto['titulo'],
				'directorio_imagen' => getcwd() . '/../images/',
				'imagen' => $aProducto['imagen'],
				'precio' => $aProducto['precio'],
				'tax' => $aProducto['tax'],
				'envio_gratis' => $aProducto['envio_gratis'],
				'oferta' => $aProducto['oferta']
			) );

			$sImagen64 = $objProductos->show(true);
			
			// echo '<img data-theme-64="true" style="display: block;" src="data:image/png;base64,' . $sImagen64 . '" />';
			
			// Imagen en base 64
			$sImagen64 = $objProductos->show(true);

			// Guardamos la imagen en una variable de session de imagenes
			$_SESSION['imagen_64_' . $nCont] = $sImagen64;
			
			$nCont++;
		}

		die();
	}
	
	// Limpiar las imagenes en base 64
	if( array_key_exists( 'delete_64', $_GET ) )
	{
		foreach( $_SESSION as $key => $value )
		{
			if( preg_match( '/^imagen_64/', $key ) )
				unset( $_SESSION[$key] );
		}
		
		die();
	}

	// Mostrar imagenes
	if( array_key_exists( 'show_64', $_GET ) )
	{
		foreach( $_SESSION as $key => $value )
		{
			if( preg_match( '/^imagen_64/', $key ) )
				echo $key . '<br/>';
		}
		
		die();
	}
?>