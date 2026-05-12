// Cuando seleccionamos un theme mostramos su preview
$("#form-theme #theme").change(function()
{
	$("#lgbox-drch img").attr("src", $(this).find(":selected").data("preview") );
});

// Lanzar formulario
$("#form-theme").submit(function()
{
	// Variables
	var dmForm = $(this);
	
	// Especificamos que se ha editado el boletin
	bEditado = true;

	// Especificamos que es un nuevo boletin
	bNuevo = true;
	
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
				// Cambiamos el estado a empezado y el theme seleccionado
				bEmpezado = true;
				sThemeBoletin = $("#form-theme #theme").find(":selected");

				// Ocultamos
				$("#lgbox-load").fadeOut();
				$("#lgbox-bg").fadeOut();
				
				// Pintamos html
				$("#web").html( sHtml );
				
				// Recargamos eventos
				eventSortableControl();
			}
		});
	});

    return false;
});