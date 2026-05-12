// Eliminamos eventos
$("#guardar").unbind();
$("#exportar").unbind();
$("#form-guardar #nombre").unbind();
$("#form-guardar").off("submit");

// Exportar ha html
$("#exportar").click(function()
{
	// Comprobamos que se haya guardado antes de poder exportar
	if( bEditado )
	{
		alert( "Para poder exportar el boletín este deberá guardarse antes" );
		return false;
	}

	// Ocultamos
	$("#lgbox-load").css("display", "none");
	$("#lgbox").css("display", "none");
	$("#lgbox-bg").css("display", "none");

	showWindow( "", "editor_boletines.php?m=guardar-boletin&a=exportar", "Boletín HTML" );
});

// Guardar boletin
$("#guardar").click(function()
{
	// Comprobamos que se haya guardado antes de poder exportar
	if( !bEditado && sNombreBoletin == $("#form-guardar #nombre").val())
	{
		alert( "No hace falta guardar el boletín ya que no se ha modificado" );
		return false;
	}

	// Si no hemos añadido ningun nombre
	if( $("#form-guardar #nombre").val() == "" )
	{
		alert( "Debes especificar un nombre al boletin" );
		return false;
	}
	
	// Guardamos el nombre del boletin
	sNombreBoletin = $("#form-guardar #nombre").val();
	
	// Ocultamos
	$("#lgbox").fadeOut(400, function()
	{
		// Datos que se enviara al servidor
		var aData = {};

		// Añadimos el nombre del boletin
		aData["nombre"] = $("#form-guardar #nombre").val();

		// Obtenemos el html
		var sHtmlBoletin = $("#web").clone();

		// Eliminamos la clase sortable para que puedan ser ordenados cuando se guarde
		$(sHtmlBoletin).find(".sortable").removeClass("sortable");
		
		// Imagenes con data64
		var dmImage64 = $(sHtmlBoletin).find("img[data-theme-64='true']");
		
		dmImage64.each(function(nIndex, dmImagen)
		{
			aData["imagen_64_" + nIndex] = $(dmImagen).attr("src");
			$(dmImagen).attr("src", "editor_boletines/images/loading.gif");
		});

		// Añadimos el html
		aData["html"] = $(sHtmlBoletin).html();
		
		// Comprobamos si exsite el boletin
		$.ajax(
		{
			type: "POST",
			url: "editor_boletines.php?m=guardar-boletin&a=check_exists",
			data: {nombre: $("#form-guardar #nombre").val()},
			success: function(sHtml)
			{
				// Si existe y no deseamos remplazarlo
				if( sHtml == "true" && !confirm("El boletín ya existe, ¿realmente deseas remplazarlo?" ) )
					$("#lgbox").fadeIn();
				else // Guardar por que no existe o queremo remplazarlo
				{	
					// Especificamos que no se ha editado el boletin
					bEditado = false;
				
					// Lanzamos ajax
					$.ajax(
					{				
						type: "POST",
						url: $("#form-guardar").attr("action"),
						data: aData,
						success: function()
						{
							// Mostramos
							$("#lgbox").fadeIn();
						}
					});
				}
			}
		});
	});
	
	return false;
});

// Cuando salimos de la caja del nombre cambiamos el nombre po su slug
$("#form-guardar #nombre").blur(function()
{
	$(this).val( slug($(this).val()) );
});

// Slug
var slug = function(str) 
{
  str = str.replace(/^\s+|\s+$/g, ''); // trim
  str = str.toLowerCase();

  // remove accents, swap ñ for n, etc
  var from = "ãàáäâẽèéëêìíïîõòóöôùúüûñç·/_,:;";
  var to   = "aaaaaeeeeeiiiiooooouuuunc------";
  for (var i=0, l=from.length ; i<l ; i++) {
    str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
  }

  str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
    .replace(/\s+/g, '-') // collapse whitespace and replace by -
    .replace(/-+/g, '-'); // collapse dashes

  return str;
};