var dropdownClass = function(dmElement, aArguments)
{
	// Variables
	var self = this;

	// Argumentos
	aArguments = $.type( aArguments ) != "undefined" ? aArguments : {};

	// Clase open
	this.sClassNameOpen = "class_name_open" in aArguments ? aArguments.class_name_open : "open";

	// Identificador del elemento que contiene el down
	this.sClassNameDown = "down_name_element" in aArguments ? aArguments.class_name_down : ".down";

	// Clase del elemento que hace que no se cierre el down cuando pulsamos dentro, si no que se mantiene
	this.sClassNameDenegateClose = "class_name_denegate_close" in aArguments ? aArguments.class_name_denegate_close : "down-dngt";

	// Cuando es un evento hover podemos especifiar en cuanto tiempo aparecera así no aparece nada mas pasar el ratón por encima
	this.nTime = "time" in aArguments ? aArguments.time : "220";

	// Evento a realizar
	this.sEvent = "event" in aArguments ? aArguments.event : "click";

	// Timer para realizar la apertura
	this.tmTimer =  null;

	// Eliminamos los eventos que contiene el dropdown
	this.eventRemove = function()
	{
		$(dmElement).unbind( "click.EventDropDown" );
		$(dmElement).unbind( "mouseenter.EventDropDown" );
		$(dmElement).unbind( "mouseleave.EventDropDown" );
		$("html").unbind( "click.EventDropDownClose" );
	}

	// Evento para abrir el dropdown
	this.open = function(e)
	{
		// Detenemos propagación
		e.stopPropagation();

		// Comprobamos si esta abierta
		if( $(this).hasClass( self.sClassNameOpen ) )
			var bOpen = true;
		else
			var bOpen = false;

		// Cerramos posibles dropdown abiertos
		$(document).trigger( "click.EventDropDownClose" );

		// Si estaba abierto detenemos
		if( bOpen ) return false;

		// Tiempo para abrir, si estamos en eveto click sera 0
		self.tmTimer = setTimeout(function()
		{
			// Capa flotante
			var dmFlot = $(this).find(self.sClassNameDown);

			// Posicion fixed, posicionamos segun las coordenadas
			if( dmFlot.css("position") == "fixed" )
			{
				// Poisicon
				var aAux = $(this).offset();

				// Posicionamos el elemento
				dmFlot.css({ top: aAux.top - $(window).scrollTop(), left: aAux.left });

				// Cuando realizamos scroll se posicione
				$(window).bind( "scroll.dropdown", function()
				{
					dmFlot.css({ top: aAux.top - $(window).scrollTop(), left: aAux.left });	
				});
				
				// Cuando hacemos resize cerramos
				$(window).bind( "resize.dropdown", function()
				{
					self.close();
				});
			}

			// Añadimos la clase para abrir
			$(this).addClass( self.sClassNameOpen );

			// Añadimos evento al desplegable para que no se cierre si pulsamos click dentro
			if( dmFlot.hasClass( self.sClassNameDenegateClose ) )
				dmFlot.unbind().click(function(e){ e.stopPropagation(); })
			
			// Si tenemos data-value es que queremos que al pulsar una opcion se muestre
			if( $(this).data("value-update") == true )
			{
				// Fake
				var dmFake = $(this).find(" > div");

				dmFlot.find("a").unbind("click.dropdown_option").bind("click.dropdown_option", function()
				{
					dmFake.html( $(this).html() );
				});
			}

			// Cerramos al pulsar en otro lado de la pantalla
			if( self.sEvent == "click" )
				$(document).bind( "click.EventDropDownClose", self.close.bind(this) );
		}.bind(this), (self.sEvent == "click" ? 0 : self.nTime) );
	}

	// Evento para cerrar el dropdown
	this.close = function()
	{
		// Si está cerrado no hacemos nada
		if( !dmElement.hasClass( self.sClassNameOpen ) )
			return false;

		// Quitamos evento al html si cerramos el dropdown
		if( self.sEvent == "click" )
		 	$(document).unbind( "click.EventDropDownClose" );

		// Quitamos la clase
  		dmElement.removeClass( self.sClassNameOpen );

		// Quitamos evento cuando hacemos scroll
		$(window).unbind( "scroll.dropdown" );

		// Quitamos evento cuando hacemos resize
		$(window).unbind( "resize.dropdown" );
		
  		// Limpiamos el timer
        clearTimeout( self.tmTimer );
	}

	// Crear eventos
	this.eventCreate = function()
	{
		// Eliminamos eventos
		this.eventRemove();

		// // Si es con evento click
		if( this.sEvent == "click" )
		 	dmElement.on( "click.EventDropDown", this.open );
		else
		{
			dmElement.on( "mouseenter.EventDropDown", this.open);
			dmElement.on( "mouseleave.EventDropDown", this.close);
		}
	}

	// Creamos eventos
	this.eventCreate();

	// Añadimos la instancia de la clase al elemento
	dmElement.data( "dropdown", this );
}

$.fn.dropdown = function(sMethod, aArguments)
{
	// Si no nos envian un metodo sera los argumentos
	if( typeof sMethod !== 'string' )
	{
	    aArguments = sMethod;
	    sMethod = 'init';
	}

	// Argumentos
	aArguments = $.type( aArguments ) != "undefined" ? aArguments : {};

	// Retorno
	var objReturn = this;

	// Recorremos los elementos
	this.each( function()
	{
		// Instancia si ha sido creado anteriormente
		var instance = $(this).data("dropdown");

		// Si tenemos instancia o si vamos a empezar
		if( instance || sMethod === 'init' )
		{
			// Si no tenemos instancia creamos el elemento
		    if( !instance )
		        instance = new dropdownClass( $(this), aArguments );

		    // Si tenemos instancia y existe el metodo
		    if( instance[sMethod] )
		    {
		    	var returnAux = instance[sMethod].apply( instance, [instance, aArguments] );

		    	if( returnAux != "undefined" )
		    		objReturn = returnAux;
		    }
		}
	});

	// Retornamos
	return objReturn;
}