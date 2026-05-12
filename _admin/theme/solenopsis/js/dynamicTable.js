$(document).ready(function()
{
	// Ordenar filas
	$(".oeTable.sorting tbody").sortable(
	{
		cursor: "move",
		start:  function(e, ui)
		{
			$(this).attr('data-previndex', ui.item.index());
		},
		stop: function(e, ui)
		{
			// Pagina
			var dmPage = $(this).closest("form").next().find(".pgnt .actv");

			// Obtenemos la cantidad de registros por pagina
			var nRowPerPage = $(this).closest("tbody").find("tr").length;

			// Obtenemos la pagina en la que estamos
			var nPage = dmPage.length == 0 ? 0 : parseInt( dmPage.text() ) - 1;

			// Variable
			var nVar = nPage * nRowPerPage;

			// Posicion aterior y nueva
			var nPrev = parseInt( $(this).attr( 'data-previndex' ) ) + nVar;
			var nNow = parseInt( ui.item.index() ) + nVar;

			// Enviamos por post la informacion de ordenacion
			app.get( "ajax" ).send({
				url: $(this).parent().data("sort-url"),
				method: "get",
				data:
				{
					id: ui.item.data("id"),
					prevPosition: nPrev,
					nowPosition: nNow
				}
			});
		},
		helper: function(e)
		{
			return "<tr style='background: #cecece; opacity: 0.3;' class='drag'></tr>";
		}
	});

	// Doble click en un registro de la tabla llamara a su src
	$("tr.dbclick td").not(".notdbclick").dblclick(function()
	{
		if( $(this).parent().data( "target" ) )
			window.open( $(this).parent().data("src"), "_blank" );
		else
			document.location.href = $(this).parent().data("src");
	});

	// Seleccionar opcion masiva
	$(".masv ul a").click(function()
	{
		var dmForm = $($(this).closest("form"));
		var aCheckboxs = dmForm.find("tbody input:checked");

		// Comprobamos si tenemos activo algun checkbox
		if( aCheckboxs.length == 0 )
		{
			let error = $(this).data("error") !== undefined ? $(this).data("error") : "Para realizar alguna de estas operaciones necesitas seleccionar algún registro";
			alert(error);
			return false;
		}

		// Si empieza por # es una llamada magnificPopup
		if( /^\#/.test( $(this).data("action") ) )
		{
			// Abriamos magnificpopup
			$.magnificPopup.open(
			{
				items: { src: $(this).data("action"), type: 'inline' },
				mainClass: "my-mfp-zoom-in"
			});
		}
		else
		{
			// Si aceptamos la opcion modificamos el action del form y enviamos
			if( confirm( $(this).data("question") ) )
			{
				// Cambiamos la acción
				dmForm.attr( "action", $(this).data("action") );				
				dmForm.attr( "method", "post" );
				dmForm.find( "input[name=action]" ).remove();

				// Enviamos el form
				dmForm.submit();
			}
		}

		// Retornamos
		return false;
	});
	
	$("body").on("change", ".oeTable thead th.chck input", function(e) 
	{
		// Si esta pulsado quitamos checkbox
		if( $(this).is(":checked") )
			$(this).closest("table").find("tbody input:not(:checked)").prop( "checked", true );
		else
			$(this).closest("table").find("tbody input:checked").prop( "checked", false );
	});
});