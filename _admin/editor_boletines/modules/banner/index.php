<?php
	// Variables
	$aBanners = getAllBanners();
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'select_banner':
			// Variables
			$sPostBanner = tep_db_prepare_input( $_POST['banner'] );
			$sPostEnlace = tep_db_prepare_input( $_POST['enlace'] );
		
			// Incluimos el theme
			include( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/theme.php' );
		
			echo theme_addBanner( $sPostBanner, $sPostEnlace );
			exit();

			exit();
		break;
	}
?>

<div id="lgbox-izqd">
	<form id="form-banner" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=select_banner">
		<select name="banner" id="banner">
			<?php
				foreach( $aBanners as $aBanner )
					echo '<option data-enlace="' . $aBanner['enlace'] . '" data-preview="' . getImagenBannerSrc( $aBanner['id'] ) . '" value="' . getImagenBannerSrc( $aBanner['id'] ) . '">' . $aBanner['text'] . '</option>';
			?>
		</select>
		<input type="text" name="enlace" id="enlace" placeholder="Escribe la url para el banner" />
		
		<iframe style="border: 1px solid rgb(204, 204, 204); width: 100%; height: 299px; margin-bottom: 25px; overflow: auto;" src=""/>
		
		<button class="bton bton-vrde" type="submit">Aceptar</button>
	</form>
</div>
<div id="lgbox-drch">
	<div class="box-info">
		<div class="icon"></div>
		Selecciona el banner que deseas añadir, recuerda que este se mostrara centrado. Si el banner seleccionado es superior al ancho del boletín puede dar problemas.
	</div>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>