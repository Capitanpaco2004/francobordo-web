<?php
	// Variables
	$aThemes = getAllThemeEmail();
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'select_theme':
			// Variables
			$sPostTheme = tep_db_prepare_input( $_POST['theme'] );
		
			// Damos valor al theme seleccionado
			$sThemeBoletin = $sPostTheme;
		
			// El nombre del boletin por defecto
			$sNombreBoletin = 'boletin-' . date('d-m-Y');

			// Grupo de cliente por defecto
			$nCustomerGroupId = 0;
			
			// Incluimos el theme seleccionado
			include( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/theme.php' );
		
			// Intentamos dar los padding automaticos a los productos
			$aThemesProductos = getAllThemeProducto();
			$aAux = array();

			// Recorremos los themes de productos
			foreach( $aThemesProductos as $aProducto )
			{	
				// Obtenemos el style del producto
				$aStyle = @Spyc::YAMLLoad( DIR_EDITOR_BOLETINES_THEME . 'producto/' . $aProducto['id'] . '/' . 'style.yml' );
				
				// Calculamos el numero total de productos por fila
				$nNumeroProductosFila = (int)($nThemeWidth / $aStyle['fondo']['width']);
			
				// Calculamos el total restante de espacio
				$nRestoEspacio = $nThemeWidth - ($aStyle['fondo']['width'] * $nNumeroProductosFila);
			
				// Calculamos el espacio entre los productos, padding izquierda y derecha
				$nPadding = number_format( $nRestoEspacio / ($nNumeroProductosFila * 2), 2 );
				
				// Guardamos el padding
				$aThemePaddingProducts[$aProducto['id']] = array( $nThemeSepareProducts, $nPadding, $nThemeSepareProducts, $nPadding );
			}
		
			echo theme_init();
			exit();
		break;
	}
?>

<div id="lgbox-izqd">
	<form id="form-theme" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=select_theme">
		<select name="theme" id="theme">
			<?php
				foreach( $aThemes as $aTheme )
					echo '<option data-preview="' . DIR_EDITOR_BOLETINES_THEME . 'email/' . $aTheme['id'] . '/preview.png" ' . ($sThemeBoletin == $aTheme['id'] ? 'selected=""' : '') . ' value="' . $aTheme['id'] . '">' . $aTheme['text'] . '</option>';
			?>
		</select>
		<div class="box-info">
			<div class="icon"></div>
			Si seleccionas un theme nuevo el actual trabajo que estas realizando será borrado y deberas de empezar de nuevo.
		</div>
		<button class="bton bton-vrde" type="submit">Aceptar</button>
	</form>
</div>
<div id="lgbox-drch">
	<img src="<?php echo DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin; ?>/preview.png"/>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>