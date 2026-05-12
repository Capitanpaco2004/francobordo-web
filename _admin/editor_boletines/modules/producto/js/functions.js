// Escribimos el path de la categoria
$("#form-producto .text-info").html( "Estas en la categoría " + $(dmElementToJs).data("theme-path") );

// Lanzar formulario
$("#form-producto").submit(function()
{
	// Variables
	var dmForm = $(this);
	var aList = $("#box1View option");
	var sIdsProductos = "";
	
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
		sIdsProductos += $(dmElment).val() + ",";
	});
	
	// Ocultamos y lanzamos ajax
	$("#lgbox").fadeOut(400, function()
	{
		$.ajax(
		{
			type: "POST",
			url: dmForm.attr("action") + "&theme=" + $("#form-producto #style").val(),
			data: {id_producto: sIdsProductos},
			success: function(sHtml)
			{
				// Elemento donde se incluiran los productos
				var dmElement = $(dmElementToJs).find("[data-theme-edit-products='true']");
				
				// Añadimos los elementos
				dmElement.html( dmElement.html() + sHtml );
				
				// Cargamos eventos
				eventSortableProductos();
				
				dmElement.not(".sortable").addClass("sortable");
				
				// Ocultamos
				$("#lgbox-load").fadeOut();
				$("#lgbox-bg").fadeOut();
			}
		});
	});

    return false;
});

// Buscar por fecha
$("#search-date").click(function()
{
	// Comprobamos que tengamos fecha para filtrar
	if( $("#from").val() == "" || $("#to").val() == "" )
	{
		alert("Debes de selccionar un rango de fecha");
		return false;
	}
	
	// Realizamos la peticion ajax para buscar los productos
	$("#lgbox").fadeOut(400, function()
	{
		$.ajax(
		{
			type: "POST",
			url: "editor_boletines.php?m=producto&a=search_date&id_categoria=" + $(dmElementToJs).data("theme-id") + "&from=" + $("#from").val() + "&to=" + $("#to").val(),
			success: function(sHtml)
			{
				// Vaciamos fechas
				$("#from").val("");
				$("#to").val("");
			
				// Mostramos
				$("#lgbox").fadeIn();

				// Array con datos
				var aDatos = $.parseJSON(sHtml);

				// Si contiene datos
				if( aDatos.length > 0 )
				{
					// Recorremos los datos y vamos añadiendo
					$(aDatos).each(function(nIndex, dmElment)
					{
						// Si el elemento aun no esta insertado
						if( $('#box1View option[value="' + dmElment.id + '"]').length == 0 )
						{
							jQuery("<option/>", {
								"value": dmElment.id,
								"text": dmElment.value
							}).dblclick(function()
							{
								window.open('../product_info.php?products_id=' + dmElment.id);
							}).appendTo("#box1View");
						}
					});
				}
				else
					alert("No se han encontrado productos");
			}
		});
	});
	
	return false;
});

// Autocomplete de productos
$( "#form-producto #producto" ).autocomplete(
{
	source: "editor_boletines.php?m=producto&a=search&id_categoria=" + $(dmElementToJs).data("theme-id"),
	minLength: 2,
	select: function( event, ui )
	{
		// Deteemos los eventos select por defecto
		event.preventDefault();

		// Creamos el option
		if( $('#box1View option[value="' + ui.item.id + '"]').length == 0 )
		{		
			jQuery("<option/>", {
				"value": ui.item.id,
				"text": ui.item.value
			}).dblclick(function()
			{
				window.open('../product_info.php?products_id=' + ui.item.id);
			}).appendTo("#box1View");
		}

		// Vaciamos la caja de texto
		$("#form-producto #producto").val("");
	}
});

// Eliminar los seleccionados
$("#form-producto #all-dlte").click(function(e)
{
	// Detenemos evento
	e.stopPropagation();
	
	// Variables
	var aOptions = $("#box1View option");
	
	// Si contenemos elementos eliminamos
	if( aOptions.length > 0 )
		aOptions.remove();
	else
		alert( "No existe ningun producto para ser eliminado" );
});

// Eliminar los seleccionados
$("#form-producto #slct-dlte").click(function(e)
{
	// Detenemos evento
	e.stopPropagation();
	
	// Variables
	var aOptions = $("#box1View option:selected");
	
	// Si contenemos elementos eliminamos
	if( aOptions.length > 0 )
		aOptions.remove();
	else
		alert( "Debes seleccionar algun producto para poder eliminarlo" );
});

// Seleccionar fechas
$("#date-range").click(function(e)
{
	// Obtenemos las 2 fechas que estamos filtrando
	var sFrom = $( "#from" ).val();
	var sTo = $( "#from" ).val();

	// Limpiamos fechas
	$( "#from" ).val("");
	$( "#to" ).val("");
	
	// Desde....
	$( "#from" ).datepicker({
		dateFormat: "dd/mm/yy",
		onClose: function( selectedDate ) 
		{
			// Si al cerrar tenemos seleccionada alguna fecha mostramos hasta....
			if( $( "#from" ).val() != "" )
				$('#to').datepicker("show");
			else // Si no ponemos las que teniamos
			{
				$('#from').val( sFrom );
				$('#to').val( sTo );
			}
		}										
	});

	// Desde..
	$( "#to" ).datepicker({
		dateFormat: "dd/mm/yy",
		beforeShow: function()
		{
			// Antes de mostrar cargamos la restricción de fecha
			var aFecha = $('#from').val().split( "/" );
			var minDate = new Date( parseInt( aFecha[2] ), parseInt( aFecha[1] ) - 1, parseInt( aFecha[0] ) );											

			return { minDate: minDate };
		},
		onClose: function( selectedDate ) 
		{
			// Si contenemos una fecha enviamos el form
			if( $( "#to" ).val() != "" )
			{}//$("#date-range-form").submit();
			else // Si no ponemos las que teniamos
			{
				$('#from').val( sFrom );
				$('#to').val( sTo );
			}
		}
	});

	// Al hacer click siempre mostramos antes el desde...
	$('#from').datepicker("show");								
});