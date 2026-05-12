<?php
	// Variables
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'insert_text':
			// Variables
			$sPostText = tep_db_prepare_input( $_POST['text'] );
		
			// Incluimos el theme
			include( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/theme.php' );
		
			echo theme_addText( $sPostText );
			exit();
		break;
		
		case 'edit_text_ok':
			echo tep_db_prepare_input( nl2br( $_POST['text'] ) );
			exit();
		break;
	}
?>

<div id="lgbox-izqd">
	<form id="form-text" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=<?php echo ($sAction == 'edit_text' ? 'edit_text_ok' : 'insert_text'); ?>">
		<textarea class="focus" name="text" id="text" placeholder="Escribir texto.." rows="10"></textarea>
		<button class="bton bton-vrde" style="margin-top: 15px;" type="submit">Aceptar</button>
	</form>
</div>
<div id="lgbox-drch">
	<div class="box-info">
		<div class="icon"></div>
		Escribe el texto que necesites para ser mostrado en el boletín. Este puede contener HTML si lo necesitas pero deberá usarse con cuidado para no romper el código resultante.
	</div>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>

<?php
	if( $sAction == 'edit_text' )
	{
		echo '<script type="text/javascript">
			editForm();
		</script>';
	}
?>