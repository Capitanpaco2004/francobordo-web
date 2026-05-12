(function($)
{
	// Variables
	var xhr;
	var nSearchMove;
	var dmSearchElements;

	$(document).ready(function()
	{
		// Enviar el formulario de añadir productos
		$("#saveform-send").submit(function()
		{
			$("#all_check").trigger("click");
		});

		// Inicio, contador seo
		$(".seo-cms span[data-row]").each(function()
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
