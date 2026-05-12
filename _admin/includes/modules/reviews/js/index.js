(function($)
{
	// Variables
	var xhr;
	var nSearchMove;
	var dmSearchElements;

	$(document).ready(function()
	{
		// Foco
		setTimeout( function(){$("#autocomplete").focus();}, 500 );

		// Autocomplete de producto
		var nSearchTimer = 0;

		var eAutocompleteOutClose = function()
		{
			$("#autocomplete-target").hide();
			$("html").unbind( "click.EventOutClose" );
			$("html").unbind("keyup.EventMoveResult");
		}

		// Evento para movernos por los resultados
		var eAutocompleteMoveResult = function(e)
		{
			switch(e.which)
			{
				case 38: // Subir
					// Disminuimos
					nSearchMove--;

					// Si pasamos el principio reseteamos
					if( nSearchMove < 0 )
						nSearchMove = dmSearchElements.length -1;

					// Todos sin clase
					dmSearchElements.removeClass("actv");

					// Posicionamos
					$(dmSearchElements[nSearchMove]).addClass("actv");
				break;

				case 40: // Abajo
					// Aumentamos
					nSearchMove++;

					// Si llegamos al final reseteamos
					if( nSearchMove > dmSearchElements.length -1 )
						nSearchMove = 0;

					// Todos sin clase
					dmSearchElements.removeClass("actv");

					// Posicionamos
					$(dmSearchElements[nSearchMove]).addClass("actv");
				break;

				case 13: // Enter
					$(dmSearchElements[nSearchMove]).trigger("click");
				break;
			}
		}

		// Autocomplete
		$("#autocomplete").unbind().keyup(function(e)
		{
			var sSearch = $(this).val();
			sSearch = sSearch.replace(RegExp("\\+|!|%","g"),"");
			sSearch = sSearch.replace(/^\s+|\s+$/g,"");
			sSearch = sSearch.toLowerCase();

			for(var b=0;27>b;b++)sSearch = sSearch.replace(new RegExp("\u00e0\u00e1\u00e2\u00e3\u00e4\u00e5\u00f2\u00f3\u00f4\u00f5\u00f6\u00f8\u00e8\u00e9\u00ea\u00eb\u00e7\u00ec\u00ed\u00ee\u00ef\u00f9\u00fa\u00fb\u00fc\u00ff\u00f1".charAt(b),"g"),"aaaaaaooooooeeeeciiiiuuuuyn".charAt(b));

			sSearch = sSearch.replace(/[^a-z0-9 -]/g,"");

			if( $.inArray( e.which, [13, 17, 18, 35, 36, 37, 38, 39, 40, 91, 16, 20] ) == -1 && sSearch != "" && 1 < sSearch.length )
			{
				// Abortamos ajax
				if( xhr && xhr.readyState != 4 )
					xhr.abort();

				clearTimeout(nSearchTimer), nSearchTimer = setTimeout(function()
				{
					// Loadding
					$("#dialog-products-loading").css("display", "block");

					// Post
					var aPost = [];
					aPost.push( { "name": "action", "value": "autocomplete" } );
					aPost.push( { "name": "term", "value": sSearch } );

					// Ajax
					xhr = $.ajax({
						type:"POST",
						"url": "reviews.php",
						"data": aPost,
						success:function(sHtml)
						{
							// Si contenemos datos
							if( sHtml != "" )
							{
								// Reseteamos
								nSearchMove = -1;

								// Datos
								$("#autocomplete-target").html( sHtml ).show();

								// Cerramos al pulsar en otro lado de la pantalla
								$("html").unbind( "click.EventOutClose" ).bind( "click.EventOutClose", eAutocompleteOutClose );

								// Movernos por los resultados de busquedas
								$("html").unbind("keyup.EventMoveResult").bind( "keyup.EventMoveResult", eAutocompleteMoveResult );

								// Obtenemos los resultados
								dmSearchElements = $("#autocomplete-target li");
							}
							else
								eAutocompleteOutClose();

							// Eventos
							$("#autocomplete-target li").click(function()
							{
								// Vaciamos
								$("#autocomplete").val( $(this).text() );

								$('#products_id').val( $(this).data("id") );
							});

							// Loadding
							$("#dialog-products-loading").css("display", "none");
						},
						error:function(b,a,f){}
					});
				}, 500);
			}
		});
	});
})(jQuery);