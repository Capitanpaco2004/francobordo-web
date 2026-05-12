// Funcion fake para el admin
function goOnLoad(){};

(function($)
{
	// Hotkeys
	$(document).unbind("keydown").keydown(function(e)
	{
		// Cambiar idioma, ctrl + derecha
		if( e.ctrlKey && e.keyCode == 39 )
		{
			var dmElement = $(".change_idioma");
			var nCantidad = dmElement.length;

			if( nCantidad > 0)
			{
				var nElement = $(dmElement).find("option:selected").next();

				// Si hemos llegado al final volvemos al principio
				if( nElement.length == 0 )
					dmElement.val( $($(dmElement).find("option")[0]).val() );
				else
					dmElement.val(nElement.val());
				
				$(".change_idioma").trigger( "change" );
				$.uniform.update();
				
				return false;
			}
		}
		
		// Cambiar idioma, ctrl + izquierda
		if( e.ctrlKey && e.keyCode == 37 )
		{
			var dmElement = $(".change_idioma");
			var nCantidad = dmElement.length;

			if( nCantidad > 0)
			{
				var nElement = $(dmElement).find("option:selected").prev();

				// Si hemos llegado al final volvemos al principio
				if( nElement.length == 0 )
					dmElement.val( $(dmElement).find("option:last-child").val() );
				else
					dmElement.val(nElement.val());
				
				$(".change_idioma").trigger( "change" );
				$.uniform.update();
				
				return false;
			}
		}

		// Añadir nuevo, ctrl + arriba
		if( e.ctrlKey && e.keyCode == 38 && $("#hotkey-new").length > 0 )
			window.location.href = $("#hotkey-new").attr("href");
			
		// Volver, ctrl + abajo
		if( e.ctrlKey && e.keyCode == 40 && $("#hotkey-return").length > 0 )
			window.location.href = $("#hotkey-return").attr("href");
	});

	// Load Jquery
	$(document).ready(function()
	{
		// Autotabs en formulario
		var nTab = 1;
		$('form.autotab input, form.autotab select').each(function() 
		{
			if (this.type != "hidden") 
			{
				var $input = $(this);
				$input.attr("tabindex", nTab);
				nTab++;
			}
		});
	
		// Seleccionar opcion masiva
		$("#opciones_masivas ul a").click(function()
		{
			var dmForm = $($(this).closest(".tableFooter").prev());
			var aCheckboxs = dmForm.find("tbody td.check input:checked");

			// Comprobamos si tenemos activo algun checkbox
			if( aCheckboxs.length == 0 )
			{
				alert( "Para realizar alguna de estas operaciones necesitas seleccionar algún registro" );
				return false;
			}

			// Si aceptamos la opcion modificamos el action del form y enviamos
			if( confirm( $(this).data("text") ) )
			{
				// Cambiamos la acción
				dmForm.attr( "action", $(this).data("action") );
				
				// Creamos los elementos get al form
				var p = $(this).data("action").split("?");
				var action = p[0];
				var params = p[1].split("&");
				
				for (var i in params) 
				{
					var tmp = params[i].split("=");
					var key = tmp[0], value = tmp[1];
					$("<input/>").attr("type", "hidden").attr("name", key).attr("value", value).appendTo(dmForm);
				}
				
				// Enviamos el form
				dmForm.submit();
			}

			// Retornamos
			return false;
		});

		// Seleccionar todos los combobox de una tabla para opciones masivas
		$(".all-checked").click(function(e)
		{
			// Si esta pulsado quitamos checkbox
			if( $(this).is(":checked") )
				$(this).closest("table").find("tbody td.check input:not(:checked)").click();
			else
				$(this).closest("table").find("tbody td.check input:checked").click();

			// Recargamos uniform
			$.uniform.update();
		});
	
		// Cuando cambiamos idioma hacemos focus
		$(".change_idioma").change(function()
		{
			$("#change-idma-" + $(this).data("id") + "-" + $(this).val()).find(".formRow input").focus();
		});
		
		// Lanzamos el evento change para posicionarnos en el primer elemento del form
		setTimeout(function(){$(".change_idioma").trigger( "change" );},150);
	
		// Guardar formulario via ajax
		$("#save_ajax").click(function(e)
		{
			var dmForm = $("#form");

			$("#dxbg").fadeIn(400, function()
			{
				$("#dxload").fadeIn(400);

				$.ajax({
					type: "POST",
					url: dmForm.attr("action"),
					data: dmForm.serialize()
				}).done(function(sHtml)
				{
					$("#dxbg").fadeOut();
					$("#dxload").fadeOut();

					var dmElement = $("<div/>", {html: sHtml}).insertAfter($(".toolbarHead").parent());

					setTimeout(function()
					{
						dmElement.fadeOut( 700, function(){ $(this).remove; } );
					},3200 );
				});
			});
		});
	
		// Doble click en un registro de la tabla llamara a su src
		$(".dbclick td").not(".negative").dblclick(function()
		{
			if( $(this).parent().data( "target" ) )
				window.open( $(this).parent().data("src"), "_blank" );
			else
				document.location.href = $(this).parent().data("src");
		});
	
		// Preguntar antes de realizar alguna acción
		$(".confirm").click(function(e)
		{
			e.stopPropagation();
			return confirm( $(this).data("text") );
		});

		// Colores //
		// Si indicamos una cantidad de colores a elegir
		$("#setNum").change(function(e)
		{
			$.ajax({
					url: "products_attributes.php?a=setColors&num=" + $(this).val(),
					type: "GET",
				}).done(function(sHtml)
				{
					$("#dxbg").fadeOut();
					$("#dxload").fadeOut();

					$("#setColor").html( sHtml );

					$("input[name=color]").change(function()
					{
						$("#color_hex" + ($(this).attr("id") ? "_" + $(this).attr("id") : "")).val($(this).val());
					});
				});
		});
		
		$("input[name=color]").change(function()
		{
			$("#color_hex" + ($(this).attr("id") ? "_" + $(this).attr("id") : "")).val($(this).val());
		});
	});
})(jQuery);