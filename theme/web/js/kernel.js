var kernelClass;

!function ($){
	kernelClass = function(aArguments)
	{
		// Variables
		var self = this;

		// Touch
		this.isTouch = 'ontouchstart' in window || 'onmsgesturechange' in window;

		// Lista de funciones a llamar cuando se redimensiona
		this.aFunctionsCallResize = [];

		// Lista de funciones a llamar cuando se inicia el DOM
		this.aFunctionsCallReady = [];

		// Lista de funciones a llamar cuando se realiza scroll
		this.aFunctionsCallScroll = [];

		// Añadir una llamada a ready
		this.addEventReady = function(fnFunction)
		{
			this.aFunctionsCallReady.push( fnFunction )
		}

		// Añadir una llamada a resize
		this.addEventResize = function(fnFunction)
		{
			this.aFunctionsCallResize.push( fnFunction )
		}

		// Añadir una llamada a scroll
		this.addEventScroll = function(fnFunction)
		{
			this.aFunctionsCallScroll.push( fnFunction )
		}

		// Crear evento ready
		this.createEventReady = function()
		{
			// Eliminamos evento
			$(window).unbind( "ready.app" );

			// Añadimos evento
			$(document).bind( "ready.app", function()
			{
				// Realizamos llamadas a todas los eventos añadidos
				for( var nCont = 0; nCont < self.aFunctionsCallReady.length; nCont++ )
					if( self.aFunctionsCallReady[nCont] != undefined )
						self.aFunctionsCallReady[nCont].call()
			});
		}

		// Crear evento resize
		this.createEventResize = function()
		{
			// Eliminamos evento
			$(window).unbind( "resize.app" );

			// Añadimos evento
			$(window).bind( "resize.app", function()
			{
				// Realizamos llamadas a todas los eventos añadidos
				for( var nCont = 0; nCont < self.aFunctionsCallResize.length; nCont++ )
					if( self.aFunctionsCallResize[nCont] != undefined )
						self.aFunctionsCallResize[nCont].call()
			});
		}

		// Crear evento scroll
		this.createEventScroll = function()
		{
			// Eliminamos evento
			$(window).unbind( "scroll.app" );

			// Añadimos evento
			$(window).bind( "scroll.app", function()
			{
				// Realizamos llamadas a todas los eventos añadidos
				for( var nCont = 0; nCont < self.aFunctionsCallScroll.length; nCont++ )
					if( self.aFunctionsCallScroll[nCont] != undefined )
						self.aFunctionsCallScroll[nCont].call()
			});
		}

		// Iniciamos
		this.init = function()
		{
			// Creamos el evento resize
			this.createEventResize();

			// Creamos el evento scroll
			this.createEventScroll();

			// Creamos el evento ready
			this.createEventReady();
		}
	}
}(jQuery);