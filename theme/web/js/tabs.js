var tabsClass;
var tab;

!function ($){
	tabsClass = function(dmElement, aOptions)
	{
		// Si el elemento tiene ya tabs pasamos de el
		if( dmElement.data("tabs") != "" )
			return false;

		// Variables
		var self = this;

		// Opciones
		this.options = {
			class_name: "xtabs",
			class_name_active: "actv",
			class_name_item: "xtabs-item",
			class_name_title: "xtabs-title",
			class_name_content: "xtabs-content"
		};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Elemento con el contenedor de los tabs
		this.dmContentTabs = $("[data-tabs-content=" + dmElement.attr("id") + "]" );

		// Elementos
		this.aElements = dmElement.find("[data-tabs-link]");

		// Eliminamos los eventos que contiene
		this.eventRemove = function()
		{
			self.aElements.unbind( "click.tabsEventClick" );
		}

		// Evento para abrir tabs
		this.open = function(e)
		{
			// Detenemos propagación
			e.stopPropagation();

			// Quitamos activos
			self.aElements.removeClass( self.options.class_name_active );
			self.dmContentTabs.find("." + self.options.class_name_active).removeClass( self.options.class_name_active );

			// Activamos
			$(this).addClass( self.options.class_name_active );
			self.dmContentTabs.find(">*").eq( $(this).index() ).addClass( self.options.class_name_active );
		}

		// Convertir a acordeon
		this.convertAccordion = function(self, aOptions)
		{
			// Opciones
			var options = {
				class_name: "xaccordion",
				class_name_active: "actv",
				class_name_item: "xaccordion-item",
				class_name_title: "xaccordion-title",
				class_name_content: "xaccordion-content",
				accordion_options: {}
			};

			// Unimos las opciones
			options = $.extend({}, options, aOptions);

			// Eliminamos
			self.destroy();

			// Añadimos el data-accordion
			dmElement.attr( "data-accordion", "" ).removeAttr( "data-tabs" );

			// Añadimos la clase
			dmElement.removeClass( self.options.class_name ).addClass( options.class_name );

			// Recorremos los title del tab
			self.aElements.each(function()
			{
				// Titulo
				var dmTitle = $("<div/>", {html: $(this).html(), class: options.class_name_title, "data-accordion-link": ""});

				// Eliminamos la clase del tab-title y Eliminamos el contenido y añadimos el titulo
				$(this).attr("data-accordion-item", "").addClass(options.class_name_item).removeClass( self.options.class_name_title ).empty().append( dmTitle );

				// Obtenemos el contenido
				var dmContent = self.dmContentTabs.find(">*").eq(0).removeClass( self.options.class_name_item ).addClass( options.class_name_content ).attr( "data-accordion-content", "" ).detach();

				// Añadimos el contenido
				$(this).append( dmContent );
			});

			// Convertimos a acordeon
			dmElement.accordion( options.accordion_options );

			// Eliminamos el contenedor de tabs
			self.dmContentTabs.remove();
		}

		// Elimina el objeto tab
		this.destroy = function()
		{
			// Eliminamos eventos
			self.eventRemove();

			// Eliminamos la instancia
			dmElement.removeData( "tabs" );
		}

		// Crear eventos
		this.eventCreate = function()
		{
			// Eliminamos eventos
			self.eventRemove();

			// Cuando hacemos click mostramos
			self.aElements.each(function()
			{
				$(this).on( "click.tabsEventClick", self.open );
			});
		}

		// Creamos eventos
		self.eventCreate();

		// Añadimos la instancia de la clase al elemento
		dmElement.data( "tabs", self );

		// Activamos
		this.dmContentTabs.find( ">*" ).eq( dmElement.find("." + self.options.class_name_active ).index() ).addClass( self.options.class_name_active );

		// Buscamos anchor
		$("*[data-tabs-anchor*=" + self.options.class_name + "-]").each(function()
		{
			// Evento click
			$(this).click(function()
			{
				// Obtenemos la clase y el index del tab que queremos pulsar
				var nIndex = $(this).data("tabs-anchor").replace( self.options.class_name + "-", "" );

				// Forzamos evento
				self.aElements.eq(nIndex - 1).trigger("click");

				// Movemos
                $('html, body').animate({ scrollTop: dmElement.offset().top }, 500);
			});
		});
	}

	$.fn.tab = function(sMethod, aOptions)
	{
		// Si no nos envian un metodo sera los argumentos
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
			var instance = $(this).data("tabs");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new tabsClass( $(this), aOptions );

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

	tabs = function(aOptions)
	{
		$("[data-tabs]").tab( "init",  aOptions );
	}
}(jQuery);
