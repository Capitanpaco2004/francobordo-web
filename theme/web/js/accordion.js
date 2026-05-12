var accordionClass;
var accordion;

!function ($){
	accordionClass = function(dmElement, aOptions)
	{
		// Si el elemento tiene ya accordion pasamos de el
		if( dmElement.data("accordion") != "" )
			return false;

		// Variables
		var self = this;

		// Opciones
		this.options = {
			class_name: "xaccordion",
			class_name_active: "actv",
			class_name_item: "xaccordion-item",
			class_name_title: "xaccordion-title",
			class_name_content: "xaccordion-content",
			close_type: "all",
			speed: "slow"
		};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Elementos
		this.aElements = dmElement.find("[data-accordion-item]");

		// Evento para abrir accordion
		this.open = function(e, bForceOpen)
		{
			// Detenemos propagación
			e.stopPropagation();

			// Padre
			var dmParent = $(this).closest("[data-accordion-item]");

			// Comprobamos si esta abierto
			var bIsOpen = dmParent.hasClass( self.options.class_name_active );

			// Segun el tipo de cierre
			if( self.options.close_type == "basic" )
					dmElement.find("." + self.options.class_name_active + "[data-accordion-item]").removeClass( self.options.class_name_active ).find("[data-accordion-content]").slideUp( self.speed );

			// Abrimos el contendor
			if( !bIsOpen || bForceOpen === true )
			{
				dmParent.find("[data-accordion-content]").stop().slideDown( self.speed );
				dmParent.addClass( self.options.class_name_active );
			}
			else
			{
				dmParent.find("[data-accordion-content]").stop().slideUp( self.speed );
				dmParent.removeClass( self.options.class_name_active );
			}
		}

		// Convertir a tabs
		this.convertTabs = function(self, aOptions)
		{
			// Opciones
			var options = {
				class_name: "xtabs",
				class_name_active: "actv",
				class_name_item: "xtabs-item",
				class_name_title: "xtabs-title",
				class_name_content: "xtabs-content",
				tabs_options: {}
			};

			// Unimos las opciones
			options = $.extend({}, options, aOptions);

			// Eliminamos
			self.destroy();

			// Añadimos el data-tabs
			dmElement.attr( "data-tabs", "" ).removeAttr( "data-accordion" );

			// Añadimos la clase
			dmElement.removeClass( self.options.class_name ).addClass( options.class_name );

			// Buscamos si existe el posible contenedor para eliminarlo
			$('[data-tabs-content="' + dmElement.attr("id") + '"]').remove();

			// Creamos el contenedor
			dmElement.after( $("<div/>", {class: options.class_name_content, "data-tabs-content": dmElement.attr("id")}) );

			// Decidimos quien estara activo
			var dmActive = dmElement.find("." + self.options.class_name_active + "[data-accordion-item]");

			// Desactivamos
			$(dmActive).removeClass( self.options.class_name_active );

			// Si es mas de uno, solo estara activo el primero que encontremos y si no tenemos ninguno activo sera el primero por defecto
			if( dmActive.length > 1 )
				dmActive = dmActive[0];
			else if( dmActive.length == 0 )
				dmActive = self.aElements[0];

			// Recorremos los item
			self.aElements.each(function()
			{
				// Eliminamos clase
				$(this).removeClass( self.options.class_name_item ).addClass( options.class_name_title ).attr("data-tabs-link", "");

				// Eliminamos la clase title
				$(this).find( "[data-accordion-link]" ).removeClass( self.options.class_name_title );

				// Obtenemos el contenido
				var dmContent = $(this).find( "[data-accordion-content]" ).removeClass( self.options.class_name_content ).addClass( options.class_name_item ).detach();

				// Eliminamos display y activo
				dmContent.removeClass(options.class_name_active).css("display", "");

				// Vamos añadiendo contenidos
				dmElement.next().append( dmContent );
			});

			// Añadimos el activo
			$(dmActive).addClass( options.class_name_active );

			// Convertimos a acordeon
			dmElement.tab( options.tabs_options );
		}

		// Elimina el objeto accordion
		this.destroy = function()
		{
			// Eliminamos eventos
			self.eventRemove();

			// Eliminamos la instancia
			dmElement.removeData( "accordion" );
		}

		// Eliminamos los eventos que contiene
		this.eventRemove = function()
		{
			self.aElements.find("*[data-accordion-link]").unbind( "click.accordionEventClick" );
		}

		// Crear eventos
		this.eventCreate = function()
		{
			// Eliminamos eventos
			self.eventRemove();

			// Cuando hacemos click desplegamos
			self.aElements.find("*[data-accordion-link]").each(function()
			{
				$(this).on( "click.accordionEventClick", self.open );
			});
		}

		// Creamos eventos
		self.eventCreate();

		// Añadimos la instancia de la clase al elemento
		dmElement.data( "accordion", self );

		// Si esta activo abrimos
		dmElement.find("." + self.options.class_name_active + "[data-accordion-item] > [data-accordion-link]").trigger("click.accordionEventClick", [true]);
	}

	$.fn.accordion = function(sMethod, aOptions)
	{
		// Si no nos envian un metodo sera las opciones
		if( typeof sMethod !== 'string' )
		{
		    aOptions = sMethod;
		    sMethod = 'init';
		}

		// Argumentos
		aOptions = $.type( aOptions ) != "undefined" ? aOptions : {};

		// Retorno
		var objReturn = this;

		// Recorremos los elementos
		this.each( function()
		{
			// Instancia si ha sido creado anteriormente
			var instance = $(this).data("accordion");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new accordionClass( $(this), aOptions );

				// Añadimos las opciones
				$.extend( instance.options, aOptions );

			    // Si tenemos instancia y existe el metodo
			    if( instance[sMethod] )
			    {
			    	var returnAux = instance[sMethod].apply( instance, [instance, aOptions] );

			    	if( returnAux != "undefined" )
			    		objReturn = returnAux;
			    }
			}
		});

		// Retornamos
		return objReturn;
	}

	accordion = function(aOptions)
	{
		$("[data-accordion]").accordion( "init",  aOptions );
	}
}(jQuery);