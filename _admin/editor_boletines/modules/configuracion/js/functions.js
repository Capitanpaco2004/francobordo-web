// Lanzar formulario
$("#form-configuracion").submit(function()
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
			data: $("#form-configuracion").serialize(),
			success: function()
			{			
				// Obtenemos los boxes que contiene productos
				var dmBoxProducts = $("tr [data-theme='control'] td [data-theme-edit-products='true']");
				
				dmBoxProducts.each(function(nIndex, dmBox)
				{
					// Reseteamos variables
					var sIds = "";
					var aTheme = {};

					// Recorremos y obtenemos los id
					$(dmBox).find("a").each(function(nIndex2, dmElement)
					{
						sIds += $(dmElement).data("theme-id") + ",";
						aTheme[$(dmElement).data("theme-id")] = $(dmElement).data("theme-theme");
					});
					
					if( sIds != "" )
					{
						// Realizamos la peticion ajax 
						$.ajax(
						{
							type: "POST",
							url: "editor_boletines.php?m=producto&a=select_productos",
							data: {"id_producto": sIds, "themes": JSON.stringify(aTheme)},
							success: function(sHtml)
							{
								// Sustituimos el html resultante
								$(dmBox).html(sHtml);

								// Recargamos eventos
								eventSortableProductos();
								
								// Ocultamos
								$("#lgbox-load").fadeOut();
								$("#lgbox-bg").fadeOut();
							}
						});
					}
				});
			}
		});
	});

    return false;
});