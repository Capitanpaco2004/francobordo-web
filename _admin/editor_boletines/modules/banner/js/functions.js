// Cuando seleccionamos un banner mostramos su preview
$("#form-banner #banner").change(function()
{
	$("#form-banner iframe").attr("src", $(this).find(":selected").data("preview") );
});

// Lanzamos el evento
$("#form-banner #banner").trigger("change");

// Lanzar formulario
$("#form-banner").submit(function()
{
	// Variables
	var dmForm = $(this);

	// Especificamos que se ha editado el boletin
	bEditado = true;
	
	// Ocultamos y lanzamos ajax
	$("#lgbox").fadeOut(400, function()
	{
		$.ajax(
		{
			type: "POST",
			url: dmForm.attr("action"),
			data: dmForm.serialize(),
			success: function(sHtml)
			{
				// Ocultamos
				$("#lgbox-load").fadeOut();
				$("#lgbox-bg").fadeOut();
				
				// Pintamos html
				var dmElmentBeforeInsert = $("#web").find("[data-theme='insert']");
				$( sHtml ).insertBefore( dmElmentBeforeInsert );

				// Recargamos eventos
				initBotonesControl();
			}
		});
	});

    return false;
});