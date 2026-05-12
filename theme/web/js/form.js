var formClass;

!function ($){
	formClass = function(dmElement, aArguments)
	{
		// Si contiene form no tenemos que volver a crearle eventos
		if( dmElement.data( "form" ) )
			return false;

		// Variables
		var self = this;

		// Crear input file
		this.inputFileCreate = function()
		{
			// Padre que encapsula el input
			var dmParent = $('<div class="xffile"></div>');

			// Variables
			var sFile = dmElement.data("file") ? dmElement.data("file") : "";
			var sButtonIcon = dmElement.data("button-icon") ? dmElement.data("button-icon") : "fa-folder-open";
			var sButtonText = dmElement.data("button-text") ? dmElement.data("button-text") : "Seleccionar archivo";
			var sButtonColor = dmElement.data("button-color") ? dmElement.data("button-color") : "";
			var dmInner = dmElement.data("inner") ? dmElement.data("inner") : "";

			// Añadimos el div interior que tiene el nombre del archivo y el boton de subir archivo
			var dmFake = dmElement.wrap(dmParent).parent().append( '<div class="xffile-name">' + sFile + '</div> <div class="xbutton ' + sButtonColor + ' afixed"><i class="fa ' + sButtonIcon + '"></i> ' + sButtonText + '</div>' );

			// Si contenemos al lado del fake un elemento
			if( dmInner!= "" && dmFake.next().hasClass( dmInner ) )
				app.get( "responsive" ).moveElement( dmFake.next(), dmFake, "prepend" );

			// Cuando seleccionamos un archivo mostramos el nombre
			dmElement.change(function(e)
			{
				$(this).next().text( e.target.value.split( '\\' ).pop() );
			});
		}

		// Cambiar cantidad de los input de incrementar y disminuir
		this.increaseDecrease = function( nQuantity, dmeElement )
		{
			// Variables
			var dmInput = dmElement.find( "input" );
			var nAllowMin = dmElement.data("min") ? dmElement.data("min") : 0;
			var nAllowMax = dmElement.data("min") ? dmElement.data("min") : 99;
			var nValue = parseInt( dmInput.val() );

			if( dmInput.attr( "readonly" ) )
				return;

			if( nQuantity < nAllowMin )
			{
				if( nValue == nAllowMin + 1 )
				{
					clearInterval( dmeElement.data( "tmTimer" ) );
					return;
				}
			}

			if( nQuantity > 0 )
			{
				if( nValue == nAllowMax )
				{
					clearInterval( dmeElement.data( "tmTimer" ) );
					return;
				}
			}

			dmInput.val( nValue + nQuantity );
		};

		// Crear campo incrementar y disminuir
		this.increaseDecreaseCreate = function()
		{
			// Variables
			var dmUp = $("<span/>", {"class": "up fas fa-angle-up"});
			var dmDown = $("<span/>", {"class": "down fas fa-angle-down"});
			var dmInput = $("<input/>", {"name": dmElement.data("name"), "value": dmElement.data("value"), "id": dmElement.data("id")});

			// Timer
			dmElement.data( "tmTimer" );

			// Evento subir cantidad
			$(dmUp).click( function(evEvent)
			{
				if( dmElement.data("alert") != null )
				{
					alert( dmElement.data( "alert" ) );
					return false;
				}

				self.increaseDecrease( 1, dmElement );
			}).mousedown( function()
			{
				clearInterval( dmElement.data( "tmTimer" ) );
				dmElement.data( "tmTimer", setInterval( function(){ self.increaseDecrease( 1, dmElement );  }, 150 ) );
			}).mouseup(function()
			{
				clearInterval( dmElement.data( "tmTimer" ) );
				setTimeout(function(){dmInput.trigger("change");}, 100);
			}).mouseleave(function()
			{
				clearInterval( dmElement.data( "tmTimer" ) );
			});

			//Evento bajar cantidad
			$(dmDown).click( function()
			{
				if( dmElement.data("alert") != null )
				{
					alert( dmElement.data( "alert" ) );
					return false;
				}

				self.increaseDecrease( -1, dmElement );
			}).mousedown( function()
			{
				clearInterval( dmElement.data( "tmTimer" ) );
				dmElement.data( "tmTimer", setInterval( function(){ self.increaseDecrease( -1, dmElement );  }, 150 ) );
			}).mouseup( function()
			{
				clearInterval( dmElement.data( "tmTimer" ) );
				setTimeout(function(){dmInput.trigger("change");}, 100);
			}).mouseleave( function()
			{
				clearInterval( dmElement.data( "tmTimer" ) );
			});

			// Si tenemos evento
			if (dmElement.data("change")){
				$(dmElement).on("change", dmInput, function(){window[dmElement.data("change")](dmInput);});
			}

			// Creamos elementos
			dmElement.append( dmUp );
			dmElement.append( dmInput );
			dmElement.append( dmDown );
		}

		// Crear campo estrellas
		this.star = function()
		{
			// Variables
			var nMax = dmElement.data("max") ? dmElement.data("max") : 5;
			var nValue = (dmElement.data("value") ? dmElement.data("value") : 0);
			var dmInput = $("<input/>", {"type": "hidden", "name": dmElement.data("name"), "value": nValue});

			// Recreamos las estrellas
			for( nCont = 0; nCont < nMax; nCont++ )
			{
				var nAux = (nMax - nCont);
				dmElement.append( '<span data-value="' + nAux + '" class="fa fa-star' + (nAux == nValue ? " actv" : "") + '"></span>' );
			}

			// Creamos input
			dmElement.append( dmInput );

			// Elementos span
			var dmSpan = dmElement.find("span");

			// Evento
			dmSpan.click(function()
			{
				dmSpan.removeClass( "actv" );
				$(this).addClass( "actv" );
				dmInput.val( $(this).data("value") );
			});
		}

		// Select mostrar texto seleccionado del combobox
		this.selectShowText = function(dmElement)
		{
			dmElement.parent().find("div").text( dmElement.find("option:selected").text() );
		}

		// Resetear select
		this.selectReset = function()
		{
			self.selectShowText( dmElement );
		}

		// Select remove
		this.selectRemove = function()
		{
			dmElement.parent().remove();
		}

		// Select Crear
		this.selectCreate = function()
		{
			// Padre que encapsula el select
			var dmParent = $('<div class="xfselect"></div>');

			// Añadimos el select y el div que sera el fake
			dmElement.wrap(dmParent).parent().append( $("<div/>").text("") );

			// Evento
			dmElement.on("change.form", function()
			{
				self.selectShowText( dmElement )
			});

			// Mostrar texto en el select por defecto
			self.selectShowText( dmElement );
		}

		// Comprueba el elemento que sea y lo mando a crear
		this.create = function()
		{
			switch(true)
			{
				case dmElement.is("div") && dmElement.data("type") == "increaseDecrease":
					this.increaseDecreaseCreate();
				break;

				case dmElement.is("div") && dmElement.data("type") == "star":
					this.star();
				break;

				case dmElement.is("input"):
					switch( dmElement.attr("type") )
					{
						case "file":
							this.inputFileCreate();
						break;
					}
				break;

				case dmElement.is("select"):
					this.selectCreate();
				break;
			}
		}

		// Crear elemento
		this.create();

		// Añadimos la instancia de la clase al elemento
		dmElement.data( "form", this );
	}

	$.fn.form = function(sMethod, aArguments)
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
			var instance = $(this).data("form");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new formClass( $(this), aArguments );

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
}(jQuery);
