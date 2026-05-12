var ajaxClass;

!function ($){
	ajaxClass = function()
	{
		// Variables
		var self = this;

		// Argumentos en la petición
		this.aArguments = {};

		// Lista de funciones a llamar cuando se realiza una peticion ajax
		this.functionsCallSuccess = [];

		// Capa overlay
		this.overlay = $("<div/>", {class: "mfp-bg new_messge_ovrl my-mfp-zoom-in mfp-ready"});

		// Capa load
		this.load = $("<div/>", {class: "mfp-wrap mfp-close-btn-in mfp-auto-cursor my-mfp-zoom-in", tabindex: "-1", style: "overflow-y: hidden; overflow-x: hidden;"}).html('<div class="mfp-container mfp-s-loading mfp-ajax-holder"><div class="mfp-content"></div><div class="mfp-preloader alert_load"><i class="fa fa-spinner fa-pulse"></i></div></div>');

		// Añadir funcion que se llamara cuando se carge el ajax
		this.addEventAjaxSucess = function(fnFunction)
		{
			this.functionsCallSuccess.push( fnFunction )
		}

		// Mostrar loadding
		this.showLoad = function()
		{
			$("body").append( this.overlay );
			$("body").append( this.load );
		}

		// Ocultar loadding
		this.hideLoad = function()
		{
			this.overlay.remove();
			this.load.remove();
		}

		// Enviar formulario
		this.form = function(dmForm, eventSuccess)
		{
			// Realizamos peticion ajax normal
			self.send({
				url: $(dmForm).attr( "action" ),
				data: $(dmForm).serialize(),
				success: eventSuccess
			});

			// Retornamos
			return false;
		}

		// Enviar petición ajax
		this.send = function(aArguments)
		{
			// Argumentos
			var aArguments = $.type( aArguments ) != "undefined" ? aArguments : {};

			// Propiedades
			aArguments.method = "method" in aArguments ? aArguments.method : "post";
			aArguments.success = "success" in aArguments ? aArguments.success : null;
			aArguments.data = "data" in aArguments ? aArguments.data : null;
			aArguments.url = "url" in aArguments ? aArguments.url : null;
			aArguments.showLoad = "showLoad" in aArguments ? aArguments.showLoad : true;

			// Cargamos los argumentos
			this.aArguments = aArguments;

			// Realizamos petición ajax
			$.ajax({
				url: aArguments.url,
				data: aArguments.data,
				method: aArguments.method,
				beforeSend: function()
				{
					// Mostramos loadding
					if( aArguments.showLoad )
						self.showLoad();
				},
				complete: function()
				{
					// Ocultamos loadding
					if( aArguments.showLoad )
						self.hideLoad();
				},
				success: function(sData, sStatus)
				{
					// Llamamos a la fucion success
					if( aArguments.success != null )
						aArguments.success( sData, sStatus );

					// Realizamos llamadas a todas los eventos añadidos
					for( var nCont = 0; nCont < self.functionsCallSuccess.length; nCont++ )
						if( self.functionsCallSuccess[nCont] != undefined )
							self.functionsCallSuccess[nCont].call()
				}
			});
		}
	}
}(jQuery);