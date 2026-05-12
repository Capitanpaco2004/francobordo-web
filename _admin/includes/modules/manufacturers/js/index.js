(function($)
{
	$(document).ready(function()
	{
		// Enviar el formulario de añadir productos
		$("#saveform-send").submit(function()
		{
			$("#all_check").trigger("click");
		});

		// Input file event, cuando se sube la imagen mostramos en base 64
		$(".brand-input-upload-imagen").change(function(event)
		{
			sThis = $(this);
			$.each(event.target.files, function(index, file)
			{
				var reader = new FileReader();
				reader.onload = function(event)
				{
					var brandImage = sThis.parent().parent().parent();
					var sHtml = '<img src="' + event.target.result + '"/>';

					sHtml += '<input type="hidden" name="br_image[]" value="' + event.target.result + '"/>';

					brandImage.find(".brand-imagen:visible").html(sHtml);
				};

				reader.readAsDataURL(file);
			});
		});

		// Boton que cuando es pulsado muestra la ventana file para subir archivo
		$(".brand-boton-upload-imagen").click(function()
		{
			$(this).parent().find(".brand-input-upload-imagen").trigger("click");
		});

		// Boton para eliminar las imagenes
		$(".brand-boton-eliminar-imagen").click(function()
		{
			if( confirm($(this).data("confirm")) )
			{
				var brandImage = $(this).parent();

				$(this).parent().find(".brand-imagen:visible").html("<input name='br_image[]' type='hidden' value='eliminar' />");
			}
		});

		// Inicio, contador seo
		$(".seo-mrcs span[data-row]").each(function()
		{
			var dmPreview = $(this);
			var dmRow = $('[name="' + $(this).data("row") + '"]');

			if( dmRow.length <= 0 )
				return false;

			dmRow.after( '<div class="text-seo-cntr input-language" data-id="' + $(this).data("id") + '" data-max="' +  $(this).data("max") + '"' + ($("#saveform-send .drop .down li:first-child a").data("id") != $(this).data("id") ? ' style="display: none;"' : '') + '>' + dmRow.val().length + '</div>' );

			dmRow.on("keyup keydown", function()
			{
				var dmContador = $(this).next();
				var nSize = $(this).val().length;

				dmContador.html(nSize);
				dmPreview.html($(this).val());

				if( nSize >= dmContador.data("max") - 15 && nSize <= dmContador.data("max") )
					dmContador.removeClass("red").addClass("ylow");
				else if( nSize > dmContador.data("max") )
					dmContador.removeClass("ylow").addClass("red");
				else
					dmContador.removeClass("ylow").removeClass("red");
			});

			dmRow.trigger("keyup");
		});
		// Fin, contador seo
	});
})(jQuery);