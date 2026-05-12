<?php
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

	// Variables
	$aBoletines = getAllBoletines();
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'load':
			// Variables
			$sPostBoletin = tep_db_prepare_input( $_POST['boletin'] );
			$aReturn = array();

			// Obtenemos el directorio del boletin
			$sDirBoletin = DIR_EDITOR_BOLETINES_HTML . $sPostBoletin . '/';

			// Cargamos configuracion
			$aReturn['config'] = file_get_contents( $sDirBoletin . 'config.cgf' );

			// Configuracion
			$aAux = json_decode( $aReturn['config'], true );
			$sThemeBoletin = $aAux['theme'];
			$sNombreBoletin = $aAux['nombre_boletin'];
			$aThemePaddingProducts = $aAux['padding_products'];
			$nCustomerGroupId = $aAux['grupo_cliente'];

			// Html
			$aReturn['html'] = file_get_contents( $sDirBoletin . 'boletin_editor.html' );

			echo json_encode( $aReturn );
			exit();
		break;
	}
?>

<div id="lgbox-izqd">
	<form id="form-theme" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=load">
		<select name="boletin" id="boletin">
			<?php
				foreach( $aBoletines as $aBoletin )
					echo '<option value="' . $aBoletin['id'] . '">' . $aBoletin['text'] . '</option>';
			?>
		</select>
		<button class="bton bton-vrde" type="submit">Aceptar</button>
	</form>
</div>
<div id="lgbox-drch">
	<div class="box-info">
		<div class="icon"></div>
		Si seleccionas un boletín para ser cargado el trabajo actual no sera guardado.
	</div>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>