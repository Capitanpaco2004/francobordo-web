(function($)
{
	$(document).ready(function()
	{
		// Quitamos efectos
		$("#solenopsis").removeClass("preload");
		
		// Anchor que tengan que confirmar antes de realizar el href
		$("a[data-confirm]").click(function(e){ e.stopPropagation(); return confirm( $(this).data("confirm") ); });

		// Accordion
		accordion();
		
		// Formularios
		$("select:not(.skip), input, textarea").form();
		
		// Dropdown
		$(".drop").dropdown();
	
		// Magnific-popup inline
		$(".mgp-inln").magnificPopup({type: 'inline', fixedContentPos: true, fixedBgPos: false, preloader: false, midClick: true, removalDelay: 300, mainClass: 'my-mfp-zoom-in'});
	
		// Si existe boton de enviar formulario
		$("#saveform").click(function()
		{
			$("#saveform-send").submit();
		});

		// Doble click en un registro de la tabla llamara a su src
		$("tr[data-dblclick] td").not(".notdbclick").dblclick(function()
		{
			var dmParent = $(this).parent();
			
			if( dmParent.data( "target" ) )
				window.open( dmParent.data("dblclick"), dmParent.data("target") );
			else
				document.location.href = dmParent.data("dblclick");
		});
	
		// Clonamos cabecera para que nos persiga
		var domClone = $(".oeHead").clone(true);
		var domCloneWrapper = $('<div id="oeHead"></div>').append( domClone );
		$(".oeHead").after( domCloneWrapper.addClass("Fixed") );
        $(window).scroll(function (event)
		{
            if( $(window).scrollTop() > 100 && document.documentElement.clientWidth > 768 )
                $('body').addClass('Fixed');
            else
                $('body').removeClass('Fixed');
        });

		// Click en cambiar estado
		$("body").on("click", ".grop-stts", function(e)
		{
			var dmElement = $(this);
			loaddingShow_oe()

			$.ajax( {
				"url": $(this).data("href") + "&flag=" + (!$(this).hasClass("actv")),
				"success": function(sHtml)
				{
					// Loadding
					loaddingHide_oe();

					dmElement.toggleClass("actv");
				}
			});
		});
	});

	// Ordenar filas
	$(".tble2-sorting tbody").sortable(
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

			// Mostramos load
			loaddingShow_oe()

			// Enviamos por post la informacion de ordenacion
			$.ajax( {
				"url": $(this).parent().data("sort-url"),
				method: "get",
				data:
				{
					id: ui.item.data("id"),
					prevPosition: nPrev,
					nowPosition: nNow
				},
				success: function(sHtml)
				{
					loaddingHide_oe();
				}
			});
		},
		helper: function(e)
		{
			return "<tr style='background: #cecece; opacity: 0.3;' class='drag'></tr>";
		}
	});
})(jQuery);

// Ocultar loadding
var loaddingHide_oe = function()
{
	$("#dxbg").css( "display", "none" );
	$("#dxload").css( "display", "none" );
}

// Mostrar loadding
var loaddingShow_oe = function()
{
	$("#dxbg").css( "display", "block" );
	$("#dxload").css( "display", "block" );
}

// Oscommerce para no dar error javascript
var goOnLoad = function(){};