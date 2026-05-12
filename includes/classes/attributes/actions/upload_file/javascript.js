(function($)
{
    $.fn.uploadFiles = function()
	{
		var fnShow = function(sJson, dmForm)
		{
			// Json
			aJson = $.parseJSON(sJson);

			// Obtenemos combinaciones
			var sCombinacion = dmForm.find("input[name=combinaciones]").val();

			// Mostramos html
			$("#dialog").html(aJson.html);

			// Cantidad total de acciones
			$("#atrb-mngr .subNav .activeli strong").text(aJson.cantidad_total);

			// Si la cantidad de combinaciones es mayor que 0 mostramos el boton en azul
			if( aJson.cantidad > 0 )
				$("#atrb-mngr .drch a[data-combi='" + sCombinacion + "']").removeClass("bDefault").addClass("bBlue");
			else
				$("#atrb-mngr .drch a[data-combi='" + sCombinacion + "']").removeClass("bBlue").addClass("bDefault");

			// Añadimos clase
			$("#dialog").addClass("form-actn");

			// Ocultamos loadding
			am_hideLoad();

			// Recargamos evento
			am_reloadEventActionForm( sCombinacion );

			// Mostramos la ventana
			$("#dialog").dialog("open");
		}
	
        // Recorremos los elementos
        $(this).each(function()
        {
			// Elementos
			var dmBotonUpload = $(this).find(".bton-fake-upload");
			var dmTable = $(this).find("tbody");
			var dmForm = $(this).closest("form");

			// Cuando hacemos click en el boton subir
			dmBotonUpload.click(function()
			{
				// Creamos el input si este no existe
				if( dmBotonUpload.prev().length == 0 )
				{
					var dmInput = $("<input />",
					{
						type: "file",
						style: "visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;",
						change: function(evt)
						{
							// Cerramos dialog
							$("#dialog").dialog("close");

							// Mostramos loadding
							am_showLoad();

							// Seleccionamos todos los archivos que hemos subido
							var files = evt.target.files;
							var nTotal = files.length;
							var nCont = 0;

							// Recorremos
							var f = null;
							for( nCont = 0; f = files[nCont]; nCont++ )
							{
								var reader = new FileReader();

								// Cargamos la informacion
								reader.onload = (function(theFile)
								{
									return function(e)
									{
										var sHtml = "";

										// Pintamos los elementos
										sHtml += '<td><input type="hidden" name="images[]" value="' + e.target.result + '"/>' + escape(theFile.name) + '</td>';
										dmTable.append( '<tr>' + sHtml + '</tr>' );
									};
								})(f);

								// Read in the image file as a data URL.
								reader.readAsDataURL(f);
							}

							// Vamos comprobando si se han cargado todas las imagenes
							var tmTimer = setInterval(function()
							{
								// Si hemos cargado todas las imagenes
								if( nCont == nTotal )
								{
									// Paramos la llamada de funcion
									clearInterval(tmTimer);

									// Enviamos el form por ajax
									$.ajax({
										type: "POST",
										url: dmForm.attr("action"),
										data: dmForm.serialize()
									}).done(function(sJson)
									{
										fnShow(sJson, dmForm);
									});
								}
							}, 500);

							// Retornamos
							return false;
						}
					});

					// Creamos elemento
					dmBotonUpload.before(dmInput);
				}

				// Lanzamos evento para la ventana de file upload
				dmBotonUpload.prev().trigger("click");
			});
		});
	}
}(jQuery));

$(document).ready(function()
{
	// Subir imagenes
	$("#dx-file-upload2").uploadFiles();
});