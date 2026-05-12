// Variables
var bEmpezado = false;
var bNuevo = false;
var bEditado = false;
var sThemeBoletin = "";
var sNombreBoletin = "";
var dmElementToJs;

// No cache
$.ajaxSetup({ cache: false });

// var bEmpezado = true;
// var bNuevo = true;
// var bEditado = true;
// var sThemeBoletin = "mayoristas";


// Instancia del producto cuando ordenamos mediante sortable
var dmElementSortable;
// Temporizador para hacer desparecer el boton de eliminar producto
var tmDeleteProducts;

// Comprueba antes de cerrar la web que algun dado no se haya guardado
$(window).bind("beforeunload", function()
{ 
	if( bEditado )
		return '¿Estas seguro?';
})

var showWindow = function(sClass, sEnlace, sTitulo)
{
	// Añadimos la clase
	if( sClass == "" )
		$("#lgbox").attr("class", "");
	else
		$("#lgbox").addClass(sClass);

	// Cambiamos el titulo
	$("#lgbox-titl").html(sTitulo);
		
	// Mostramos fondo
	$("#lgbox-bg").fadeIn(400, function()
	{
		$.ajax(
		{
			url: sEnlace
		}).done(function(sHtml)
		{
			$("#lgbox-cntd").html( sHtml );
			$("#lgbox").fadeIn();
				
			// Posicionamos foco
			$(".focus").focus();
		});
	});

	// Mostramos loading
	$("#lgbox-load").fadeIn();
};

var closeWindow = function()
{
	$("#lgbox-load").css("display", "none");
	$("#lgbox").fadeOut(400, function()
	{
		$("#lgbox-cntd").html("");
	});
	$("#lgbox-bg").fadeOut();
};

// Ordenar productos
var eventSortableProductos = function()
{
	// Variables
	var dmELementTd = $("td[data-theme-products='sortable']");

	// Eliminamos los posibles eventos que le hemos podido dar anteriormente
	dmELementTd.find("a").mouseenter(function()
	{
		// Detenemos
		clearTimeout( tmDeleteProducts );
	
		// Mostramos el boton de eliminar producto
		$("#dlte-prdt").css({top: $(this).position().top, left: $(this).position().left + $(this).width() - 30, display: "block"});

		// Guardamos el elemento
		dmElementSortable = $(this);
	}).mouseleave(function()
	{
		// Pasando 200 milisegundos ocultamos el boton eliminar
		tmDeleteProducts = setTimeout(function(){ $("#dlte-prdt").css( "display", "none"); }, 200)
	});

	// Añadimos el evento ordenar a las tablas que aun no la tienen
	dmELementTd.not(".sortable").sortable(
	{
		forcePlaceholderSize: true,
		cursor: "move",
        placeholder:'drop-control2',
		receive: function (event, ui) {
            dmElementSortable = ui.item;
        },
		helper: function(e)
		{	
			return "<div class='drag'><img src='" + dmElementSortable.find("img").attr("src") + "'/></div>";
		},
		stop: function()
		{
			bEditado = true;
		}
	});
};

// Ordenar elementos del boletin
var eventSortableControl = function()
{
	$('#web tbody[data-theme="sortable"]').sortable(
	{
		cursorAt: { top: 37.5 },
		items: "tr[data-theme='control']",
		forcePlaceholderSize: true,
        placeholder:'drop-control',
		cursor: "move",
		helper: function(e)
		{	
			return "<div class='drag-control'></div>";
		},
		stop: function()
		{
			bEditado = true;
		}
	});
};

// Inicio, botones control
var initBotonesControl = function()
{
	// Obtenemos los elementos que necesitan el evento data control para mostrar los botones
	var dmElementsControl = $("#web").find("[data-theme='control']")
	
	// Eliminamos los posibles eventos que le hemos podido dar anteriormente
	dmElementsControl.unbind();
	
	// Cuando entramos con el raton mostramos el menu
	dmElementsControl.mouseenter(function()
	{
		// Elemento
		var dmElement = $(this);
		
		// Titulo
		var sTitulo = dmElement.data("theme-title");
			
		// Eliminamos los posibles eventos que le hemos podido dar anteriormente
		$("#bton-cntr a").unbind();
	
		// Si contiene evento para new
		if( dmElement.data("theme-url-new") != undefined )
		{		
			$("#bton-cntr a.new").css("display", "inline-block").click(function()
			{
				if( dmElement.data("theme-new-title") != '' )
					sTitulo = dmElement.data("theme-new-title");				
			
				dmElementToJs = dmElement;
				showWindow("all", dmElement.data("theme-url-new"), "Insertar " + sTitulo);
			});			
		}
		else
			$("#bton-cntr a.new").css("display", "none");

		// Si contiene evento para edit
		if( dmElement.data("theme-url-edit") != undefined )
		{		
			$("#bton-cntr a.edit").css("display", "inline-block").click(function()
			{
				dmElementToJs = dmElement;
				var sClass = "";
				
				if( dmElement.data("theme-title") == "texto" )
					sClass = "all";

				showWindow(sClass, dmElement.data("theme-url-edit"), "Editar " + sTitulo);
			});			
		}
		else
			$("#bton-cntr a.edit").css("display", "none");
			
		// Si contiene evento para edit
		if( dmElement.data("theme-event-edit") != undefined )
		{
			$("#bton-cntr a.edit").css("display", "inline-block").click(function()
			{
				dmElementToJs = dmElement;
				window[dmElement.data("theme-event-edit")]();
			});	
		}
	
		// Si contiene evento para delete
		if( dmElement.data("theme-url-dlte") != "" )
		{		
			$("#bton-cntr a.dlte").css("display", "inline-block").click(function()
			{
				bEditado = true;
			
				dmElement.fadeOut(400, function()
				{
					dmElement.remove();
				});

				$("#bton-cntr").css("display", "none");
			});			
		}
		else
			$("#bton-cntr a.dlte").css("display", "none");
	
		// Posicionamos
		$("#bton-cntr").css({"top": $(this).position().top + 5, "left": $(this).position().left + $(this).width() + 10});
	
		// Mostramos
		$("#bton-cntr").stop( true, true ).fadeIn();
	});
	
	// Cuando salimos con el raton ocultamos el menu pasado X segundos
	dmElementsControl.mouseleave(function()
	{
		// Mostramos
		$("#bton-cntr").stop( true, true ).fadeOut();
	});
};
// Fin, botones control

// Inicio, eliminar separacion
var editSepare = function()
{
	var nHeight = prompt( "Introduce el alto deseado", $(dmElementToJs).find("td.separe").attr("height") );

	if( nHeight != null && nHeight >= 0 )
	{
		bEditado = true;
		$(dmElementToJs).find("td.separe").attr("height", nHeight);
	}
};
// Fin, eliminar separacion

$(document).ready(function()
{
	// Inicio, calendario español
	$.datepicker.regional['es'] = {
		closeText: 'Cerrar',
		prevText: '<Ant',
		nextText: 'Sig>',
		currentText: 'Hoy',
		monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
		monthNamesShort: ['Ene','Feb','Mar','Abr', 'May','Jun','Jul','Ago','Sep', 'Oct','Nov','Dic'],
		dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
		dayNamesShort: ['Dom','Lun','Mar','Mié','Juv','Vie','Sáb'],
		dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sá'],
		weekHeader: 'Sm',
		dateFormat: 'dd/mm/yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''
	};
    $.datepicker.setDefaults($.datepicker.regional['es']);
	// Fin, calendario español

	// Inicio, eliminar boletin
	$("#deleteBoletin").click(function(e)
	{
		e.stopImmediatePropagation();

		// Si aun no hemos seleccionado un theme
		if( !sThemeBoletin )
		{
			alert("Debes de seleccionar un theme antes de empezar.");
			return false;
		}

		// Si el boletin es nuevo eliminamos sin mas, si no es nuevo eliminaremos tambien del servidor
		if( bNuevo && confirm("¿Realmente deseas eliminar el boletin?") )
		{
			bEditado = false;
			window.location.href = "editor_boletines.php";
		}
		else if( confirm("¿Realmente deseas eliminar el boletín?\n\n¡¡ATENCIÓN: Esto eliminara también el boletín del servidor!!") )
		{
			$.ajax(
			{
				type: "POST",
				url: "editor_boletines.php?m=delete_boletin",
				success: function(sHtml)
				{
					window.location.href = "editor_boletines.php";
				}
			});
			
		}
	});
	// Fin, eliminar boletin

	// Inicio, añadir separacion
	$("#addsepare").click(function(e)
	{
		e.stopImmediatePropagation();

		// Si aun no hemos seleccionado un theme
		if( !sThemeBoletin )
		{
			alert("Debes de seleccionar un theme antes de empezar.");
			return false;
		}
		
		var nHeight = prompt( "Introduce el alto deseado", "40" );
		
		if( nHeight != null && nHeight >= 0 )
		{
			bEditado = true;
		
			// Creamos la separacion
			var dmElmentBeforeInsert = $("#web").find("[data-theme='insert']");
			$('<tr data-theme="control" data-theme-event-edit="editSepare" data-theme-url-delete="true"><td bgcolor="#fff" class="separe" height="' + nHeight + '"> </td></tr>').insertBefore( dmElmentBeforeInsert );
			
			// Asignamos el elemento
			dmElementToJs = $(this);
			
			// Recargamos eventos
			initBotonesControl();
		} 
	});
	// Fin, añadir separacion

	// Inicio, eliminar producto
	$("#dlte-prdt").click(function()
	{
		bEditado = true;
	
		// Ocultamos
		$("#dlte-prdt").css("display", "none");

		// Eliminamos
		$(dmElementSortable).remove();
	}).mouseenter(function()
	{
		clearTimeout( tmDeleteProducts );
	}).mouseleave(function()
	{
		// Pasando 150 milisegundos ocultamos el boton eliminar
		tmDeleteProducts = setTimeout(function(){ $("#dlte-prdt").css( "display", "none"); }, 150)
	});
	// Fin, eliminar producto

	// Inicio, Botones control
	// Cuando estamos encima del menu de control este permanecera mostrado y oculto cuando salgamos
	$("#bton-cntr").mouseenter(function()
	{
		$("#bton-cntr").stop( true, true ).fadeIn();
	});

	$("#bton-cntr").mouseleave(function()
	{
		$("#bton-cntr").stop( true, true ).fadeOut();
	});
	// Fin, Botones control
	
	// Esto ai ke eliminarlo
	// initBotonesControl();
	// eventSortableControl();
	// eventSortableProductos();

	// Inicio, menu izquierdo
	$("#menu-izqd a").click( function(e)
	{
		// Si es el module exit
		if( $(this).data("module") == "exit" )
		{
			// Si hemos empezado editando un boletin preguntamos para salir
			if( bEmpezado )
			{
				if( confirm( "Tienes editando un boletin, ¿seguro que deseas salir sin guardar?" ) )
					return true;
				else
					return false;
			}

			return true;
		}

		// Si el modulo no es theme o cargar comprobamos si ya hemos seleccionado un theme
		if( $(this).data("module") != "theme" && $(this).data("module") != "cargar-boletin" && !sThemeBoletin )
		{
			alert("Debes de seleccionar un theme antes de empezar.");
			return false;
		}

		// Detenemos evento
		e.stopPropagation();
	
		// Mostramos la ventana
		showWindow( $(this).data("class"), "editor_boletines.php?m=" + $(this).data("module"), $(this).find(".nav-tooltip").html() );
		
		return false;
	});
	// Fin, menu izquierdo

	// Evento para cerrar el lighbox
	$("#lgbox-clse").click( closeWindow );
});