var oscdenoxClass = function()
{
	// Variables
	var self = this;
	var bFormPreventSendingTwice = false;

	// Extendemos la clase
	$.extend( self, new containerClass() );

	// Funciones del proyecto que no sabemos donde encajarlas
	this.functionsDisaster = function()
	{
		// Information por ajax
		$("#info-pages .main li a").click(function(e)
		{
			// Quitamos activo
			$(this).closest(".main").find(".actv").removeClass("actv");
			$(this).addClass("actv");

			// Detenemos evento
			e.stopPropagation();

			// Ajax
			app.get("ajax").send({
				"url": $(this).attr("href"),
				"success": function(sHtml){ $("#info-pages .load").html( sHtml ); }
			});

			// Return
			return false;
		}).first().trigger("click");

		// Para escribir opiniones se usan radio y checkbox y al final dejan un campo de texto para comentar en plan "otros", mostrar y ocultarlo si esta activo
		$(".form-bopi input[type=radio], .form-bopi input[type=checkbox]").click(function()
		{
			var dmParent = $(this).closest(".form-bopi");
			var dmLastInput = $( dmParent.find("input[type=" + $(this).attr("type") + "]" ).last() );
			var dmLastElement = $(dmParent.children().last());

			if( dmLastElement.prop("tagName") == "DIV" )
			{
				if( dmLastInput.prop( "checked" ) )
					dmLastElement.css("display", "block").find("input").focus();
				else
					dmLastElement.css("display", "none").find("input").val("");
			}
		});

		// Si tenemos algun checkbox o radio seleccionado llamamos su evento para mostar el campo hidden
		$(".form-bopi input[type=radio]:checked, .form-bopi input[type=checkbox]:checked").trigger("click");

		// Comentario de producto en la ficha de producto
		$("#cmtr-form").submit( function()
		{
			var dmForm = $("#cmtr-form");

			// Ajax escribir comentario
			app.get("ajax").send({
				"url": dmForm.attr( 'action' ),
				"data": dmForm.serializeArray(),
				"success": function(sHtml)
				{
					// Si no es error reseteamos
					if( !sHtml.match( /Error/g ) )
					{
						dmForm[0].reset('');
						dmForm.find(".xform-star .actv").removeClass("actv");
					}

					// Mostramos mensaje
					$("#cmtr-crrt-ajax").html( sHtml );
				}
			});

			return false;
		});

		// Create account tipo de cliente/empresa
		$(".create_account_type").change(function()
		{
			if( $(this).attr("id") == "empresa" )
				$(".company-hidden").css("display", "flex");
			else
				$(".company-hidden").css("display", "none");
		});

		// Filtro fabricantes
		$(".abc .row a").click( function(e)
		{
			$("#box-prdct .ltra").removeClass( 'actv' );

			if( $("#box-prdct #brn-" + $(this).attr( "data-val" )).length > 0 )
			{
				$('html,body').animate({ scrollTop: $("#box-prdct #brn-" + $(this).attr( "data-val" )).offset().top - 110}, 'slow');
				$("#box-prdct #brn-" + $(this).attr( "data-val" )).addClass( 'actv' );
			}
		});

		// Formularios que se envian por ajax y muestran un mensaje
		$(".alertMessageForm").submit(function(){ app.get("alert").formAjax(this); return false; });
	}

	// Busqueda autocomplete
	this.searchAutocomplete = function(dmInput)
	{
		// Variables
		var nSearchTimer = 0;
		var dmSearch = dmInput.attr("autocomplete", "off");
		var dmForm = dmInput.parent();
		var dmSearchElements;
		var nSearchMove;

		// Creamos las capas
		dmSearch.after( '<div class="rslt-ajax"></div>' );
		dmSearch.after( '<div class="rslt-ajax-load"></div>' );
		var dmResultadoAjax = dmForm.find(".rslt-ajax");
		var dmLoadAjax = dmForm.find(".rslt-ajax-load");

		// Creamos eventos //
		// Cuando realizamos click en el no cerrar
		$(dmResultadoAjax).click(function(event){event.stopPropagation();});

		// Cuando realizamos un click fuera cerramos
		var eAutocompleteOutClose = function()
		{
			$(dmResultadoAjax).hide();

			// Eliminamos eventos
			$("html").unbind( "click.EventOutClose" );
			$("html").unbind( "keyup.EventMoveResult" );
			$(dmSearch).unbind("keypress");
		};

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
					dmSearchElements.removeClass("auto-prdt-row-hover");

					// Posicionamos
					$(dmSearchElements[nSearchMove]).addClass("auto-prdt-row-hover");
				break;

				case 40: // Abajo
					// Aumentamos
					nSearchMove++;

					// Si llegamos al final reseteamos
					if( nSearchMove > dmSearchElements.length -1 )
						nSearchMove = 0;

					// Todos sin clase
					dmSearchElements.removeClass("auto-prdt-row-hover");

					// Posicionamos
					$(dmSearchElements[nSearchMove]).addClass("auto-prdt-row-hover");
				break;

				case 13: // Enter
					if( dmResultadoAjax.find(".auto-prdt-row-hover").length > 0 )
						window.location = dmResultadoAjax.find(".auto-prdt-row-hover").data("href");
					else
						dmForm.submit();
				break;
			}
		}

		// Buscar autocomplete
		dmInput.keyup(function(e)
		{
			var sSearch = $(this).val();
			sSearch = sSearch.replace(RegExp("\\+|!|%","g"),"");
			sSearch = sSearch.replace(/^\s+|\s+$/g,"");
			sSearch = sSearch.toLowerCase();

			for(var b=0;27>b;b++)sSearch = sSearch.replace(new RegExp("\u00e0\u00e1\u00e2\u00e3\u00e4\u00e5\u00f2\u00f3\u00f4\u00f5\u00f6\u00f8\u00e8\u00e9\u00ea\u00eb\u00e7\u00ec\u00ed\u00ee\u00ef\u00f9\u00fa\u00fb\u00fc\u00ff\u00f1".charAt(b),"g"),"aaaaaaooooooeeeeciiiiuuuuyn".charAt(b));

			sSearch = sSearch.replace(/[^a-z0-9 -]/g,"");

			if( $.inArray( e.which, [13, 35, 36, 37, 38, 39, 40] ) == -1 && sSearch != "" && 3 < sSearch.length && 1024 <= $(document).width() )
			{
				clearTimeout(nSearchTimer), nSearchTimer = setTimeout(function()
				{
					// Mostramos load
					dmLoadAjax.css("display", "block");

					$.ajax({
						type:"GET",
						url:"search.php",
						data:{buscar: sSearch, numero: "5", a: "autocomplete"},
						success:function(sHtml)
						{
							// Lanzamos este evento para quitar eventos
							eAutocompleteOutClose();

							// Reseteamos
							nSearchMove = -1;

							// Cerramos al pulsar en otro lado de la pantalla
							$("html").bind( "click.EventOutClose", eAutocompleteOutClose );

							// Movernos por los resultados de busquedas
							$("html").bind( "keyup.EventMoveResult", eAutocompleteMoveResult );

							// No permitir dar a enter
							dmSearch.keypress(function(e){if( e.which == 13 ) e.preventDefault(); });

							// Mostramos resultados
							dmResultadoAjax.html(sHtml).show();

							// Obtenemos los resultados
							dmSearchElements = dmResultadoAjax.find(".rsmn-prdt");

							// Enviar el formulario
							dmResultadoAjax.find(".auto-prdt-row.rsmn a").click(function(){ dmForm.submit(); });

							// Quitamos load
							dmLoadAjax.css("display", "none");
						},
						error:function(b,a,f){}
					});
				}, 500);
			}
		});
	}

	// Eventos para la rgpd
	this.rgpd = function()
	{
		// Tooltip
		var fnTooltipRgpd = function()
		{
			$(".rgpd-check .fa").tooltip({
				position: {
					my: "center bottom-10",
					at: "center top",
					using: function( position, feedback )
					{
						$( this ).css( position );
						$( "<div>" ).addClass( "arrow" ).addClass( feedback.vertical ).addClass( feedback.horizontal ).appendTo( this );
					}
				},
				tooltipClass: "rgpd-tooltip",
				open:function(event,ui)
				{
					if( typeof( event.originalEvent ) === 'undefined' )
					{
					   ui.tooltip.remove();
					   return false;
					}

					var $id= $(ui.tooltip).attr('id');
					$('div.ui-tooltip').not('#'+$id).remove();
				},
				close:function(event,ui)
				{
					ui.tooltip.hover(function()
					{
						$(this).stop(true).fadeTo(600,1);
					},
					function()
					{
						$(this).fadeOut('600',function()
						{
							$(this).remove();
						});
					});
				},
				content: function (){ return $(this).prop('title'); }
			});
		}
		$(document).ajaxComplete(function() {fnTooltipRgpd(); });
		fnTooltipRgpd();

		// Soy mayor de edad
		$("#rgpd-dob-accp").click(function()
		{
			$.magnificPopup.instance.close();
		});

		// Aceptar terminos y condiciones generales
		$("#rgpd-accp").click(function()
		{
			// Newsletter
			var nNewletter = $("#rgpd-wndw").data("newsletter");

			// Cerramos
			$.magnificPopup.instance.close();

			// Ajax para guardar el cambio de vista
			app.get("ajax").send({
				"url": "information.php?a=rgpd_accept&status=1",
				"success": function()
				{
					if( nNewletter == "1" )
						window.location.href = "account/account_newsletters.php";
				}
			});
		});
	}

	// Mensaje de advertencia
	this.messageWarning = function()
	{
		if( $("#dx-wrng").length > 0 )
		{
			$( "#dx-wrng").css( {"display": "block", "opacity": 0 }).animate({"opacity": 1}, 1500);

			$("#dx-wrng-clse").click( function()
			{
				$("#dx-wrng").animate({opacity: 0}, 1500, "swing",  function(){$("#dx-wrng").css("display", "none");});
			});
		}
	}

	// Cambiar vista
	this.changeView = function()
	{
		if( $("#chng-vsta") || ($("#asrch-vsta") && $("#asrch-vsta").find("a").length > 0) )
		{
			$("#chng-vsta, #asrch-vsta a").click(function(e)
			{
				(e ? e.stopPropagation() : false);

				if( $(this).hasClass( "chng-vsta-hrzt" ) )
				{
					$(".xprdt").removeClass("prdt-hrzt").addClass("prdt-vrtl");
					$(this).removeClass("chng-vsta-hrzt").addClass("chng-vsta-vrtl").data("view","chng-vsta-vrtl");
				}
				else
				{
					$(".xprdt").removeClass("prdt-vrtl").addClass("prdt-hrzt");
					$(this).removeClass("chng-vsta-vrtl").addClass("chng-vsta-hrzt").data("view","chng-vsta-hrzt");
				}

				// Ajax para guardar el cambio de vista
				app.get("ajax").send({
					"url": "information.php?action=cambiar_vista&c=" + $(this).data("view"),
					"showLoad": false
				});
			});
		}
	}

	// Saber si una cookie existe
	this.getCookie = function(name)
	{
		var dc = document.cookie;
		var prefix = name + "=";
		var begin = dc.indexOf("; " + prefix);
		if (begin == -1) {
			begin = dc.indexOf(prefix);
			if (begin != 0) return null;
		}
		else
		{
			begin += 2;
			var end = document.cookie.indexOf(";", begin);
			if (end == -1) {
			end = dc.length;
			}
		}
		return unescape(dc.substring(begin + prefix.length, end));
	}

	// Funcion para los codigo postales, provincias, paises
	this.loadCitiesCP = function()
	{
		// Variables
		var dmCp = $("input[data-ajax-postcode]");
		var dmTargetCountry = $("#ajax-country");
		var dmTargetZone = $("#ajax-zone");
		var dmTargetCity = $("#ajax-city");

		// Si escribimos un codigo postal pintamos todos los combobox
		dmCp.change(function()
		{
			// Boton formulario
			var dmSubmitForm = $(this).closest( "form" ).find( "input[type=submit]" );

			// Disable
			dmSubmitForm.attr("disabled", true);

			$.ajax( {
				"url": "information.php?call=ajaxCountryZoneCity",
				"type": "post",
				"data": {"a": "getCp", "country": $(this).closest("form").find("select[name=country]").val(), "cp": $(this).val()},
				"success": function( sJson )
				{
	                sJson = $.parseJSON(sJson);

					if( sJson.zones.length > 0 )
						dmTargetZone.html(sJson.zones).find("select").select2();

					if( sJson.cities.length > 0 )
						dmTargetCity.html(sJson.cities).find("select").select2();

					if( sJson.country != null )
						dmTargetCountry.find("select").val( sJson.country ).select2( {data: sJson.country} );

					dmSubmitForm.removeAttr("disabled");
				}
			});
		});

		// Si seleccionamos un pais mostramos sus zonas
		dmTargetCountry.on("change", "select", function()
		{
			// Reiniciamos el codigo postal
			dmCp.val("");

			// Boton formulario
			var dmSubmitForm = $(this).closest( "form" ).find( "input[type=submit]" );

			// Disable
			dmSubmitForm.attr("disabled", true);

			$.ajax( {
				"url": "information.php?call=ajaxCountryZoneCity",
				"type": "post",
				"data": {"a": "getZones", "country": $(this).val()},
				"success": function( sJson )
				{
	                sJson = $.parseJSON(sJson);

					if( sJson.zones.length > 0 )
						dmTargetZone.html(sJson.zones).find("select").select2();

					if( sJson.cities.length > 0 )
						dmTargetCity.html(sJson.cities).find("select").select2();

					dmSubmitForm.removeAttr("disabled");
				}
			});
		});

		// Si seleccionamos una zona mostramos sus ciudades
		dmTargetZone.on("change", "select", function()
		{
			// Reiniciamos el codigo postal
			dmCp.val("");

			// Boton formulario
			var dmSubmitForm = $(this).closest( "form" ).find( "input[type=submit]" );

			// Disable
			dmSubmitForm.attr("disabled", true);

			$.ajax( {
				"url": "information.php?call=ajaxCountryZoneCity",
				"type": "post",
				"data": {"a": "getCities", "country": dmTargetCountry.find("select").val(), "zone": $(this).val()},
				"success": function( sHtml )
				{
					dmTargetCity.html(sHtml).find("select").select2();
					dmSubmitForm.removeAttr("disabled");
				}
			});
		});

		// Si selecciono una ciudad pintamos codigo postal
        dmTargetCity.on("change", "select", function()
		{
			if ($(this).val() < 0) {
				let parent = $(this).parent();
				parent.find("select").select2('destroy').remove();
				parent.find("input").attr("name", "city_id").focus();
			}
			else {
				dmCp.val( $(this).find('option:selected').text().replace(/.+\[|/, "").replace("]", "") );
			}
        });
	}

	// Funcion que hace que los formularios no se envien dos veces al hacer click
	this.formPreventSendingTwice = function()
	{
		// Recorremos todos los formularios
        $("form").each(function()
		{
            var dmForm = $(this);

			// Cuando enviemos el formulario
			dmForm.submit(function()
			{
				var dmElement = dmForm.find( "input[type='image'],input[type='submit']" );

				dmElement.click(function()
				{
					if( self.bFormPreventSendingTwice )
						return false;

					return true;
				});
			});
        });

        $(window).bind('beforeunload', function()
		{
            self.bFormPreventSendingTwice = true;
        });
	}
}
