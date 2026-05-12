(function($)
{
	// Variables
	var xhr;
	var nSearchMove;
	var dmSearchElements;

	$(document).ready(function()
	{
		// Eventos para los puntos
		var fnEventTablePuntos = function(nTotalTr, nRespn)
		{
			// Evento drag
			$( ".banner-imagen" + nRespn + " .pnto" + (nTotalTr > 0 ? "[data-id=" + nTotalTr + "]" : "") ).not(".ui-draggable").draggable({
				containment: ".banner-imagen" + nRespn,
				scroll: false,
				stop: function()
				{
					var offset = $(this).offset();
					var offsetParent = $(".banner-imagen" + nRespn).offset()

					var nLeft = offset.left - offsetParent.left;
					var nTop = offset.top - offsetParent.top;

					$(".banner-imagen" + nRespn + " .pnto[data-id=" + $(this).data("id") + "]").find("input[name='bp_x[" + ($(this).data("id")) + "]']").val(nLeft);
					$(".banner-imagen" + nRespn + " .pnto[data-id=" + $(this).data("id") + "]").find("input[name='bp_y[" + ($(this).data("id")) + "]']").val(nTop);
				}
			});
		};

		// Input file event, cuando se sube la imagen mostramos en base 64
		$(".banner-input-upload-imagen").change(function(event)
		{
			sThis = $(this);
			nWidth = $(this).data('width');
			$.each(event.target.files, function(index, file)
			{
				var reader = new FileReader();
				reader.onload = function(event)
				{
					var bannerImage = sThis.parent().parent().parent();
					var sHtml = '<img width="' + nWidth + '" src="' + event.target.result + '"/>';
					var sRespons = 'web';

					if( bannerImage.find(".banner-imagen").hasClass( 'vrsweb' ) )
						sRespons = 'web';
					else if( bannerImage.find(".banner-imagen").hasClass( 'vrstablet' ) )
						sRespons = 'tablet';
					else if( bannerImage.find(".banner-imagen").hasClass( 'vrsmovil' ) )
						sRespons = 'movil';

					sHtml += '<input type="hidden" name="bp_image[' + sRespons + '][' + bannerImage.find(".banner-imagen .imge div:visible").data('id') + ']" value="' + event.target.result + '"/>';

					bannerImage.find(".banner-imagen .imge div:visible").html(sHtml);
				};

				reader.readAsDataURL(file);
			});
		});

		// Boton que cuando es pulsado muestra la ventana file para subir archivo
		$(".banner-boton-upload-imagen").click(function()
		{
			$(this).parent().find(".banner-input-upload-imagen").trigger("click");
		});

		// Boton para eliminar las imagenes
		$(".banner-boton-eliminar-imagen").click(function()
		{
			if( confirm($(this).data("confirm")) )
			{
				var bannerImage = $(this).parent();

				if( bannerImage.find(".banner-imagen").hasClass( 'vrsweb' ) )
					sRespons = 'web';
				else if( bannerImage.find(".banner-imagen").hasClass( 'vrstablet' ) )
					sRespons = 'tablet';
				else if( bannerImage.find(".banner-imagen").hasClass( 'vrsmovil' ) )
					sRespons = 'movil';

				$(this).parent().find(".banner-imagen .imge div:visible").html("<input name='bp_image[" + sRespons + "][" + bannerImage.find(".banner-imagen .imge div:visible").data('id') + "]' type='hidden' value='eliminar' />");
			}
		});

		// LLamamos para recrear eventos en puntos
		if( $(".banner-imagen div.pnto").length > 0 )
		{
			$(".banner-imagen div.pnto").each(function(nIndex, dmElement)
			{
				fnEventTablePuntos(nIndex + 1, $(this).parent().hasClass('vrsweb') ? ".vrsweb" : ".vrstablet");
			});
		}
		// Fin, puntos

		// Creamos la ventana para añadir puntos
		$("#dialog-products").dialog({position:['middle',20], width: "900", autoOpen: false, resizable: true, modal: true, close: function(){$(".pnt-ttle").val("");$("#pnt-prce").val("");$("#pnt-enlc").val("");$("#pnt-edt").val(0);$("#pnt-x").val(0);$("#pnt-y").val(0);}});

		// Añadir puntos
		$(".addpoint").click(function()
		{
			// Eliminamos los productos anteriormente buscados y el campo search lo reseteamos
			$("#autocomplete").val( "" );

			// Abriamos la ventana
			$("#dialog-products").dialog("open");
			$("#pnt-rsp").val( $(this).data("resp") );
		});

		var fnEventPuntosClick = function(nTotalTr)
		{
			$(".pnt-pls" + (nTotalTr > 0 ? "[data-id=" + nTotalTr + "]" : "")).click( function()
			{
				var sThis = $(this)

				// Eliminamos los productos anteriormente buscados
				$("#autocomplete").val( "" );

				$("#pnt-edt").val( sThis.data('id') );
				if( sThis.data('id') > 0 )
					$(".dlt-pnt").css( "display", "inline-block" );

				$("#pnt-prdid").val( sThis.parent().find( "input[name='bp_products_id[" + (sThis.data('id')) + "]']" ).val() );
				
				$("#pnt-rsp").val( sThis.data("resp") );
				
				$("#pnt-x").val( sThis.parent().find( "input[name='bp_x[" + (sThis.data('id')) + "]']" ).val() );
				$("#pnt-y").val( sThis.parent().find( "input[name='bp_y[" + (sThis.data('id')) + "]']" ).val() );
				

				$.each(aLanguages, function(i, item) {
					$(".pnt-ttle[data-id=" + aLanguages[i].id + "]").val( sThis.parent().find( "input[name='bp_titulo[" + (sThis.data('id')) + "][" + aLanguages[i].id + "]']" ).val() );
				});

				$("#pnt-prce").val( parseFloat(sThis.parent().find( "input[name='bp_precio[" + (sThis.data('id')) + "]']" ).val()).toFixed(2).replace( /\./g, ',' ) + "€" );
				$("#pnt-enlc").val( sThis.parent().find( "input[name='bp_enlace[" + (sThis.data('id')) + "]']" ).val() );

				// Abriamos la ventana
				$("#dialog-products").dialog("open");
			});
		}

		fnEventPuntosClick();

		$( "#frm-point" ).submit( function()
		{
			nResp = ($("#pnt-rsp").val() == "web" ? ".vrsweb" : ".vrstablet");
			
			var nTotalTr = 0;
			$(".banner-imagen .pnto.ui-draggable").each(function()
			{
				var nId = $(this).data('id');
				if(nId > nTotalTr) nTotalTr = nId;
			});
			nTotalTr = nTotalTr + ($("#pnt-edt").val() == 0 ? 1 : 0);

			var sHtml = "";
			var nIdPoint = ($("#pnt-edt").val() == 0 ? nTotalTr : $("#pnt-edt").val());

			if( $("#pnt-edt").val() == 0 )
				sHtml = "<div style='left: 0px; top: 0px; height: " + pAlto + "px; width: " + pAncho + "px;' class='pnto' data-id='" + nTotalTr + "'>";

					sHtml += "<span>" + $(".pnt-ttle[data-id=3]").val() + "</span>";
					sHtml += "<span class=\"prco\">" + $("#pnt-prce").val() + "</span>";
					sHtml += "<a data-id='" + nIdPoint + "' data-resp='" + $("#pnt-rsp").val() + "' href='javascript:void(0);' class='pnt-pls'>+</a>";
					sHtml += "<div class='bgc'></div>";

					sHtml += "<input type='hidden' name='bp_products_id[" + (nIdPoint) + "]' value='" + $("#pnt-prdid").val() + "' />";
					sHtml += "<input type='hidden' name='bp_responsive[" + (nIdPoint) + "]' value='" + $("#pnt-rsp").val() + "' />";
					sHtml += "<input type='hidden' name='bp_x[" + (nIdPoint) + "]' value='" + $("#pnt-x").val() + "' />";
					sHtml += "<input type='hidden' name='bp_y[" + (nIdPoint) + "]' value='" + $("#pnt-y").val() + "' />";

					$.each(aLanguages, function(i, item) {
						sHtml += "<input type='hidden' name='bp_titulo[" + (nIdPoint) + "][" + aLanguages[i].id + "]' value='" + $(".pnt-ttle[data-id=" + aLanguages[i].id + "]").val() + "' />";
					});

					sHtml += "<input type='hidden' name='bp_precio[" + (nIdPoint) + "]' value='" + $("#pnt-prce").val().replace(/€/g, '').replace( /,/g, '.' ) + "' />";
					sHtml += "<input type='hidden' name='bp_enlace[" + (nIdPoint) + "]' value='" + $("#pnt-enlc").val() + "' />";

			if( $("#pnt-edt").val() == 0 )
				sHtml += "</div>";

			if( $("#pnt-edt").val() == 0 )
			{
				$(".banner-imagen" + nResp).append( $( sHtml ) );
				fnEventTablePuntos(nTotalTr, nResp);
			}
			else
				$(".banner-imagen" + nResp + " .pnto[data-id=" + $("#pnt-edt").val() + "]").html( sHtml );

			fnEventPuntosClick(nIdPoint);

			$("#dialog-products").dialog("close");

			return false;
		});

		$(".dlt-pnt").click( function()
		{
			$(".banner-imagen" + ($("#pnt-rsp").val() == "web" ? ".vrsweb" : ".vrstablet") + " .pnto[data-id=" + $("#pnt-edt").val() + "]").remove();
			$("#dialog-products").dialog("close");
		})

		$("#dpl-img .down a").unbind("click.form_input_language").bind("click.form_input_language", function()
		{
			// Ocultamos
			$(".banner-imagen .imge div").css("display", "none");

			// Mostramos
			$(".banner-imagen .imge div[data-id=" + $(this).data("id") + "]").css("display", "block");
		});

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
						"url": "banners_destacados.php",
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
								$("#autocomplete").val("");

								$(".pnt-ttle[data-id=3]").val( $(this).text() );

								// Nombre por idiomas
								$(this).filter(function()
								{
									for( var property in $(this).data() )
									{
										if( property.match(/lang/g) )
											$(".pnt-ttle[data-id=" + property.match(/\d+/) + "]").val( $(this).data(property) );
									}
								});
								
								$("#pnt-prce").val( $(this).data("price") );
								$("#pnt-prdid").val( $(this).data("id") );
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