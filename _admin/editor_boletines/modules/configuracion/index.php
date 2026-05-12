<?php
	// Variables
	$sAction = tep_db_prepare_input( $_GET['a'] );

	// Obtenemos los theme de productos
	$aThemesProductos = getAllThemeProducto();

	// Obtenemos los grupo de clientes
	$aGruposClientes = getAllGruposClientes();

	// Acciones
	switch( $sAction )
	{
		case 'save':
			// Guardamos el grupo de cliente nuevo
			$nCustomerGroupId = $_POST['grupo_cliente'];

			// Recorremos los theme de productos
			foreach( $aThemesProductos as $aProducto )
			{
				$aThemePaddingProducts[$aProducto['id']] = array(
					$_POST['producto_' . $aProducto['id'] . '_top'],
					$_POST['producto_' . $aProducto['id'] . '_right'],
					$_POST['producto_' . $aProducto['id'] . '_bottom'],
					$_POST['producto_' . $aProducto['id'] . '_left']
				);
			}

			exit();
		break;
	}
?>

<div id="lgbox-izqd" style="width: 780px;">
	<form id="form-configuracion" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=save" style="position: relative; height: 100%;">
		<label style="width: 170px;">Grupo de cliente: </label>
		<?php echo tep_draw_pull_down_menu( 'grupo_cliente', $aGruposClientes, $nCustomerGroupId, 'style="width: 605px;"' ); ?>

		<h2>Separacion para los productos en PX (arriba, derecha, abajo, izquierda)</h2>
		<?php
			// Recorremos los theme de productos
			foreach( $aThemesProductos as $aProducto )
			{
				echo '<label style="width: 170px;">' . $aProducto['text'] . ':</label>';

				echo '<input autocomplete="off" value="' . $aThemePaddingProducts[$aProducto['id']][0] . '" type="text" pattern="[0123456789\.]*" name="producto_' . $aProducto['id'] . '_top" style="width: 110px; text-align: center;" /> - ';
				echo '<input autocomplete="off" value="' . $aThemePaddingProducts[$aProducto['id']][1] . '" type="text" pattern="[0123456789\.]*" name="producto_' . $aProducto['id'] . '_right" style="width: 110px; text-align: center;" /> - ';
				echo '<input autocomplete="off" value="' . $aThemePaddingProducts[$aProducto['id']][2] . '" type="text" pattern="[0123456789\.]*" name="producto_' . $aProducto['id'] . '_bottom" style="width: 110px; text-align: center;" /> - ';
				echo '<input autocomplete="off" value="' . $aThemePaddingProducts[$aProducto['id']][3] . '" type="text" pattern="[0123456789\.]*" name="producto_' . $aProducto['id'] . '_left" style="width: 110px; text-align: center;" /><br/>';
			}
		?>		
		<button class="bton bton-vrde" type="submit" style="bottom: 0;left: 0;position: absolute;">Aceptar</button>
	</form>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>