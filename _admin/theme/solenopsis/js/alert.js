var alertClass;

!function ($){
	alertClass = function()
	{
		// Variables
		var self = this;

		// Funcion que devuelve el html de la ventana
		this.getHtml = function(sIcon, sTitle, sText, sTextButton)
		{
			// Componemos la ventana
			var sHtml = '<div class="alert zoom-anim-dialog ' + sIcon + '">';

				if( sTitle != "" )
					sHtml += '<div class="titl">' + sTitle + '</div>';

				sHtml += '<div class="text">' + sText + '</div>';

				if( sTextButton != "" )
					sHtml += '<div class="bton mfp-id-close hovr2">' + sTextButton + '</div>';
			sHtml += '</div>';

			// Retornamos
			return sHtml;
		}

		// Funcion que muestra un alert simple en pantalla
		this.alert = function(sIcon, sTitle, sText, sTextButton)
		{
			// Variables
			var magnificPopup = $.magnificPopup.instance;
			var sHtml = this.getHtml(sIcon, sTitle, sText, sTextButton);

			// Abriamos magnificpopup
			magnificPopup.open(
			{
				items: { src: sHtml, type: 'inline' },
				mainClass: "my-mfp-zoom-in",
				callbacks: {
					beforeOpen: function()
					{
						magnificPopup.bgOverlay.addClass( "alert_ovrl no" );
					},
					open: function()
					{
					    magnificPopup.items[0].inlineElement.find(".bton").click(function()
				    	{
				    		magnificPopup.close();
				    	});
					}
				}
			});
		}

		// Funcion que muestra un alert con html en pantalla
		this.alertHtml = function(sHtml)
		{
			// Variables
			var magnificPopup = $.magnificPopup.instance;

			// Abriamos magnificpopup
			magnificPopup.open(
			{
				items: { src: sHtml, type: 'inline' },
				mainClass: "my-mfp-zoom-in"
			});
		}

		// Funcion que realiza un ajax y muestra un mensaje retornado del servidor
		this.ajax = function(aArguments)
		{
			// Variables
			var sUrl = "url" in aArguments ? aArguments.url : null;
			var aData = "data" in aArguments ? aArguments.data : null;
			var success = "success" in aArguments ? aArguments.success : null;
			var magnificPopup = $.magnificPopup.instance;
			var aJson;
			var dmOverlay = null;

			// Abrimos magnificpopup
			magnificPopup.open(
			{
				items: { type: "ajax" },
				mainClass: "my-mfp-zoom-in",
				callbacks: {
					beforeOpen: function()
					{
						magnificPopup.bgOverlay.addClass( "alert_ovrl" );
					},
					afterClose: function()
					{
						// Si no existe overlay es que algo ha salido mal, así que no hacemos nada
						if( dmOverlay != null )
						{
							// Quitamos el overlay
							dmOverlay.css("display", "block");
							dmOverlay.animate({"opacity": 0}, 150, "swing", function(){dmOverlay.remove()});

							// Abriamos el nuevo popup con el mensaje
							self.alert( aJson[0], aJson[1], aJson[2], aJson[3] );
						}
					}
				},
				ajax:
				{
					settings:
					{
						type: "POST",
						url: sUrl,
						data: aData,
						success: function(sJson)
						{
							// Json
							aJson = $.parseJSON(sJson);

							// Duplicamos las capa overlay de magnificPopup para cerrar el actual magnificPopup
							dmOverlay = $(".mfp-bg").clone();

							// Ocultamos
							dmOverlay.css("display", "none");

							// Pintamos el overlay clonado
							$("body").append(dmOverlay);

							// Cerramos el actual magnificPopup que es el que ha realizado el ajax
							magnificPopup.close();

							// LLamamos al evento
							if( success != null )
								success(aJson);
						}
					}
				}
			});
		}

		// Funcion que llama una petición ajax de un formulario que devuelve mensajes
		this.formAjax = function(dmForm, eventSuccess)
		{
			// Magnificpopup
			var magnificPopup = $.magnificPopup.instance;

			// Si esta abierto ya
			if( magnificPopup.isOpen )
			{
				// Ajax
				var ajax = app.get("ajax");

				// Cerramos magnificpopup
				magnificPopup.close();

				// Mostramos loadding
				ajax.showLoad();

				// Realizamos peticion ajax normal
				setTimeout( function()
				{
					ajax.send({
						url: $(dmForm).attr( "action" ),
						data: $(dmForm).serialize(),
						success: function(html)
						{
							self.alertHtml(html);

							// LLamamos al evento
							if( eventSuccess != null )
								eventSuccess(html);

							// Si contiene un formulario le damos foco
							setTimeout( function(){$(magnificPopup.content).find("form *:input[type!=hidden]:first").focus(); }, 100 );
						}
					});
				}, 500 );

				// Retornamos
				return false;
			}

			// Si no esta abierto lanza la peticion mediante magnificpopup
			this.ajax({
				url: $(dmForm).attr( "action" ),
				data: $(dmForm).serialize(),
				success: eventSuccess
			});

			return false;
		}
	}
}(jQuery);

var alertPopup = new alertClass();
