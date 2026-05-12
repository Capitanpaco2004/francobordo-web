<div id="lgbox-izqd">
	<form id="form-image" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=select_image">
		<input id="file" type="file" />
		<input type="text" name="enlace" id="enlace" placeholder="Escribe una url para la imagen" />
		<div class="bton bton-vrde">Aceptar</div>
	</form>
</div>
<div id="lgbox-drch">
	<div class="box-info">
		<div class="icon"></div>
		Selecciona la imagen de tu equipo que deseas añadir, esta debe ser exclusivamente una imagen PNG. Recuerda que este se mostrara centrado. Si la imagen seleccionada es superior al ancho del boletín puede dar problemas.
	</div>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>