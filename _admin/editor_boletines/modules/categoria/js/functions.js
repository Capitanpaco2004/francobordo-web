var bFormEdit = false;

// Funcion que se llamada cuando se va a editar
var editForm = function()
{
	// El estado cambia a edicion
	bFormEdit = true;

	// Añadimos el texto a editar
	$("#form-categoria-edit #categoria").val( $(dmElementToJs).data("theme-text") );
};

// Lanzar formulario
$("#form-categoria-edit").submit(function()
{
	// Variables
	var dmForm = $(this);
	var aData = {};
	
	// Si no escribimos nada cerramos
	if( $("#form-categoria-edit #categoria").val() == "" )
	{
		closeWindow();
		return false;
	}

	// Especificamos que se ha editado el boletin
	bEditado = true;
	
	// Añadimos la categoria
	aData[$(dmElementToJs).data("theme-id")] = {parent: $(dmElementToJs).data("theme-imagen"), value: $("#form-categoria-edit #categoria").val(), imagen: true };
	
	// Ocultamos y lanzamos ajax
	$("#lgbox").fadeOut(400, function()
	{
		$.ajax(
		{
			type: "POST",
			url: dmForm.attr("action"),
			data: {categorias: JSON.stringify(aData)},
			success: function(sHtml)
			{
				// Ocultamos
				$("#lgbox-load").fadeOut();
				$("#lgbox-bg").fadeOut();

				// Pintamos html
				$(dmElementToJs).find("[data-theme-edit='true']").parent().html( sHtml );

				// Modificamos valor
				$(dmElementToJs).data("theme-text", $("#form-categoria-edit #categoria").val());

				// Recargamos eventos
				initBotonesControl();
			}
		});
	});

    return false;
});

// Lanzar formulario
$("#form-categoria").submit(function()
{
	// Variables
	var dmForm = $(this);
	var aList = $("#box1View option");
	var aData = {};
	
	// Si no contenemos ningun elemento cerramos
	if( aList.length == 0 )
	{
		closeWindow();
		return false;
	}

	// Especificamos que se ha editado el boletin
	bEditado = true;
	
	// Recorremos la lista para crear el array que enviaremos
	aList.each( function( nIndex, dmElment )
	{
		aData[$(dmElment).attr("id")] = {parent: $(dmElment).data("parent"), value: $(dmElment).data("name"), path: $(dmElment).text()};
	});
	
	// Ocultamos y lanzamos ajax
	$("#lgbox").fadeOut(400, function()
	{
		$.ajax(
		{
			type: "POST",
			url: dmForm.attr("action"),
			data: {categorias: JSON.stringify(aData)},
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

// Autocomplete de categorias
$( "#form-categoria #categoria" ).autocomplete(
{
	source: "editor_boletines.php?m=categoria&a=search",
	minLength: 2,
	select: function( event, ui )
	{
		// Deteemos los eventos select por defecto
		event.preventDefault();

		// Creamos el option
		if( $('#box1View option[id="' + ui.item.id + '"]').length == 0 )
		{		
			jQuery("<option/>", {
				"value": ui.item.value,
				"id": ui.item.id,
				"data-parent": ui.item.id_parent,
				"data-name": ui.item.name,
				"text": ui.item.value
			}).appendTo("#box1View");
		}

		// Vaciamos la caja de texto
		$("#form-categoria #categoria").val("");
	}
}).focus();

// Eliminar los seleccionados
$("#form-categoria #all-dlte").click(function(e)
{
	// Detenemos evento
	e.stopPropagation();
	
	// Variables
	var aOptions = $("#box1View option");
	
	// Si contenemos elementos eliminamos
	if( aOptions.length > 0 )
		aOptions.remove();
	else
		alert( "No existe ninguna categoría para ser eliminada" );
});

// Eliminar los seleccionados
$("#form-categoria #slct-dlte").click(function(e)
{
	// Detenemos evento
	e.stopPropagation();
	
	// Variables
	var aOptions = $("#box1View option:selected");
	
	// Si contenemos elementos eliminamos
	if( aOptions.length > 0 )
		aOptions.remove();
	else
		alert( "Debes seleccionar alguna categoría para poder eliminarla" );
});