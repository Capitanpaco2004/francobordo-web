var accordionMenuClass;
var accordionMenu;

!function ($){
	accordionMenuClass = function(dmElement, aOptions)
	{
		// Si el elemento tiene ya accordionmenu pasamos de el
		if( dmElement.data("accordionMenu") != "" )
			return false;

		// Variables
		var self = this;

		// Opciones
		this.options = {
			class_name_active: "actv",
			class_name_parent: "parent",
			close_type: "all",
			speed: "slow"
		};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Eliminamos los eventos que contiene
		this.eventRemove = function()
		{
			dmElement.find("li." + self.options.class_name_parent).children("a").unbind("click.accordionMenuEventClick");
		}

		// Elimina el objeto accordion
		this.destroy = function()
		{
			// Eliminamos eventos
			self.eventRemove();

			// Eliminamos clases
			dmElement.find("li." + self.options.class_name_parent).removeClass( self.options.class_name_parent ).find("ul").css("display", "");

			// Eliminamos la instancia
			dmElement.removeData( "accordionMenu" );
		}

		// Abrir cerrar accordion
		this.toggle = function(e, bCloseType)
		{
			// Detenemos propagación
			e.preventDefault();

			// Si estamos realizando un efecto no dejamos realizar más click a no ser que nos fuercen a ello
			if( !bCloseType && dmElement.hasClass( "has-actv" ) )
				return false;

			// Añadimos la clase al elemento especificando que estamos realizando un efecto
			dmElement.addClass( "has-actv" );

			// Padre
			var dmParent = $(this).parent();

			// Comprobamos si esta abierto
			var bIsOpen = dmParent.hasClass( self.options.class_name_active );

			// Segun el tipo de cierre
			if( !bCloseType && self.options.close_type == "basic" )
				dmParent.parent().find("." + self.options.class_name_active).children("a").trigger( "click.accordionMenuEventClick", [true] );

			// Abrimos
			if( !bIsOpen )
			{
				$(this).next().slideDown( self.speed, function(){ dmElement.removeClass("has-actv"); } );
				dmParent.addClass( self.options.class_name_active );
			}
			else
			{
				$(this).next().slideUp( self.speed, function(){ dmElement.removeClass("has-actv"); }  );
				dmParent.removeClass( self.options.class_name_active );
			}
		}

		// Crear eventos
		this.eventCreate = function()
		{
			// Eliminamos eventos
			self.eventRemove();

			// Obtenemos todos los LI
			var dmsLi = dmElement.find('li');

			// Recorremos LI
			dmsLi.each( function()
			{
				var $dmLi = $(this);

				// Si tiene hijos
				if( $dmLi.children('ul').length )
				{
					// Añadimos la clase padre
					$dmLi.addClass( self.options.class_name_parent );

					// Cerramos
					$dmLi.not("." + self.options.class_name_active).children('ul').css("display", "none");

					// Primer anchor
					$(this).children("a").on( "click.accordionMenuEventClick", self.toggle );
				}
			});
		}

		// Creamos eventos
		self.eventCreate();

		// Añadimos la instancia de la clase al elemento
		dmElement.data( "accordionMenu", self );
	}

	$.fn.accordionMenu = function(sMethod, aOptions)
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
			var instance = $(this).data("accordionMenu");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new accordionMenuClass( $(this), aOptions );

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

	accordionMenu = function(aOptions)
	{
		$("[data-accordion-menu]").accordionMenu( "init",  aOptions );
	}
}(jQuery);