var wishlistClass;
var wishlistButton;
var wishlistButtonClass;

!function ($){
	wishlistClass = function(aOptions)
	{
		// Variables
		var self = this;

		// Idioma
		this.language = $.parseJSON( aLanguageWishlist );

		// Opciones
		this.options = {
			"class_wishlist_button": ".fvrt",
			"class_active" : "actv",
			"id_wishlist_message" : "#fvrt-show"
		};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Realiza las llamadas ajax para añadir o eliminar
		this.callAjax = function(aArguments)
		{
			// Argumentos
			aArguments = $.type( aArguments ) != "undefined" ? aArguments : {};

			// Propiedades
			aArguments.success = "success" in aArguments ? aArguments.success : null;
			aArguments.action = "action" in aArguments ? aArguments.action : null;
			aArguments.data = "data" in aArguments ? aArguments.data : null;

			// Si no tenemos accion
			if( aArguments.action == null )
				return false;

			// Peticion ajax
			app.get("ajax").send({
				"url": "favoritos.php?call=dxWishlist:" + aArguments.action,
				"data": aArguments.data,
				"success": aArguments.success
			});
		}

		// Cuando se selecciona atributos de un producto
		this.attributeSelect = function(sCombinacion)
		{
			if (!$("#array_option_wishlist").length) {
				return
			}
			
			var wishlistAttrb = $.parseJSON($("#array_option_wishlist").text());
			var combinationArray = sCombinacion.split(",");
			var wishlistFound = false;

			// Obtenemos todas las combinaciones
			var alternatives = self.wordcombos(combinationArray);

			// Recorremos todos los favoritos y buscamos en sus alternativas si se encuentra en favoritos
			for(var a = 0; a < wishlistAttrb.length; ++a)
			{
				if(jQuery.inArray(wishlistAttrb[a], alternatives) !== -1){
					wishlistFound = true;
					break;
				}
			}

			// Si lo hemos encontrado
			if(wishlistFound){
				return wishlistAttrb[a];
			}
			else{
				return false;
			}
		}

		// Realiza todas las posibles combinaciones que puede tener un atributo
		this.wordcombos = function(words)
		{
			var result = [];

			if ( words.length <= 1 ) {
			    result = words;
			}
			else{
				for (var i = 0; i < words.length; ++i) {
		            var firstword = words[i];
		            var remainingwords = [];

		            for ( var j = 0; j < words.length; ++j ) {
		                if ( i != j ) remainingwords.push(words[j]);
		            }

		            var combos = this.wordcombos(remainingwords);

					for ( var j = 0; j < combos.length; ++j ) {
		                result.push(firstword + "," + combos[j]);
		            }
				}
			}

		    return result;
		}

		// Insertar o elimina producto al wishlist
		this.setProduct = function(nProductsId, aAttrId, sAction)
		{
			// Array con los valores que pasaremos pos post
			var aData = {};

			// Products_id
			aData["products_id"] = nProductsId;

			// Si contiene atributos
			if( $.type(aAttrId) != "undefined" )
			{
				// Creamos el array de atributos
				$.each( aAttrId, function(nIndex, value)
				{
					if( value.name.match( /^id/g ) )
						aData[value.name] = value.value;
				});
			}

			// Realizamos la peticion Ajax
			self.callAjax({
				"action": sAction,
				"data": aData,
				"success": function(jSon)
				{
					// Realizamos el efecto de insercción
					$( self.options.id_wishlist_message ).find("span").text( self.language[ (sAction == "add" ? "WISHLIST_BOTON_AÑADIDO" : "WISHLIST_BOTON_ELIMINADO") ] );
					$( self.options.id_wishlist_message ).stop().fadeIn();
					setTimeout(function(){ $( self.options.id_wishlist_message ).stop().fadeOut(); }, 3000);

					// Json
					var jSon = $.parseJSON( jSon );

					// Si existe el el input de atributos
					$("#array_option_wishlist").text(jSon.products);
				}
			});
		},

		// Boton wishlist
		$(this.options.class_wishlist_button).unbind().wishlistButton( this.options );

		// Eliminar del listado de favoritos
		$("#wlis-tble .icon-dlte").click(function(e)
		{
			// Variables
			var dmElement = $(this);

			// Detenemos evento
			(e ? e.stopPropagation() : false);

			if( confirm( app.get("wishlist").language['FAVORITOS_ELIMINAR'] ) )
			{
				// Obtenemos los atributos
				var aData = dmElement.data("atributo");

				// Products_id
				aData["products_id"] = dmElement.data("id");

				// Realizamos la peticion Ajax
				self.callAjax({
					"action": "remove",
					"data": aData,
					"success": function()
					{
						// Eliminamos la fila
						dmElement.closest("tr").remove();

						// Si la tabla se ha quedado sin filas, eliminamos la tabla y mostramos el mensaje
						if( $( "#wlis-tble tr" ).length == 1 )
						{
							// Eliminamos la tabla de wishlist, el boton de comprar todo y compartir
							$("#wlis-tble").remove();
							$("#bbuyall").remove();
							$("#bshare").remove();

							// Ocultamos mi lista de favoritos
							$("#whls").css("display", "none");

							// Mostramos el mensaje de no existen productos
							$("#msje-whls").css("display", "block");
						}
					}
				});
			}
		});

		// Wishlist comprar
		$("#wlis-tble .icon-crrt").click(function(e)
		{
			// Detenemos evento
			(e ? e.stopPropagation() : false);

			// Variables
			var dmElement = $(this);

			// Obtenemos products_id
			var sProductsId = dmElement.data("id");

			// Obtenemos los atributos
			var sAtributos = dmElement.data("atributo");

			// Array con los campos que enviaremos
			var aData = {"products":{}};
			var aAuxData = {};
			aAuxData["products_id"] = sProductsId;
			aAuxData["cart_quantity"] = dmElement.closest("tr").find("input").val();

			if( aAuxData["cart_quantity"] <= 0 )
			{
				alert( app.get("wishlist").language["FAVORITOS_CANTIDAD_COMPRAR"] );
				return;
			}

			// Si contiene atributos
			if( sAtributos )
			{
				aAuxData["id"] = {};
				var nCont = 0;

				// Creamos el array de atributos
				$.each( sAtributos["id"], function(key, value)
				{
					aAuxData["id"][nCont] = [];
					aAuxData["id"][nCont].push(key);
					aAuxData["id"][nCont].push(value);

					nCont++;
				});
			}

			// Añadimos el producto
			aData['products'][0] = aAuxData;

			// Efecto para comprar
			app.get("cart").effectCart( $(this) );

			// Ajax para comprar
			app.get("ajax").send({
				"url": "information.php?call=cart:addProductAjax",
				"data": aData,
				"success": function()
				{
					// Refrescamos el carrito una vez este insertado
					app.get("cart").refreshCart();
				}
			});
		});

		// Comprar todo el wishlist
		$("#bbuyall").click(function()
		{
			// Array con los campos que enviaremos
			var aData = {"products":{}};
			var nContBuy = 0;

			// Recorremos todos los iconos de comprar
			$("#wlis-tble .icon-crrt").each(function()
			{
				// Variables
				var dmIconBuy = $(this);
				var aAuxData = {};

				// Data
				aAuxData["products_id"] = dmIconBuy.data("id");
				aAuxData["cart_quantity"] = dmIconBuy.closest("tr").find("input").val();

				// Contaremos con el si hemos comprado mas de 0 unidades
				if( aAuxData["cart_quantity"] > 0 )
				{
					// Obtenemos los atributos
					var sAtributos = dmIconBuy.data("atributo");

					// Si contiene atributos, creamos el array de atributos
					if( sAtributos )
					{
						aAuxData["id"] = {};
						var nCont = 0;

						$.each( sAtributos["id"], function(key, value)
						{
							aAuxData["id"][nCont] = [];
							aAuxData["id"][nCont].push(key);
							aAuxData["id"][nCont].push(value);

							nCont++;
						});
					}

					// Añadimos el producto
					aData['products'][nContBuy] = aAuxData;

					// Aumentamos la cantidad de productos
					nContBuy++;
				}
			});

			// Si no hemos comprado nada
			if( nContBuy == 0 )
			{
				alert(  app.get("wishlist").language['FAVORITOS_CANTIDAD_COMPRAR_2'] );
				return false;
			}

			// Ajax para comprar
			app.get("ajax").send({
				"url": "information.php?call=cart:addProductAjax",
				"data": aData,
				"success": function()
				{
					// Refrescamos el carrito una vez este insertado
					app.get("cart").refreshCart();
				}
			});
		});
	}

	// Objeto boton wishlist
	wishlistButtonClass = function(dmElement, aOptions)
	{
		// Si el elemento tiene ya wishlist pasamos de el
		if( $.type( dmElement.data("wishlistButton") ) != "undefined" )
			return false;

		// Variables
		var self = this;
		dmElement = $(dmElement);

		// Opciones
		this.options = {};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Evento click
		dmElement.click(function()
		{
			// Si esta activo, eliminamos
			if( $(this).hasClass( self.options.class_active ) )
			{
				// Variables
				var aData = false

				// Le quitamos el activo
				$(dmElement).removeClass( self.options.class_active );

				// Si contiene atributos y estamos en product_info, añadimos
				if( $(dmElement).data("attr") && $(dmElement).data("info") ){
					// Obtenemos el formulario
					aData = $(dmElement).closest("form").serializeArray();
				}

				// Eliminamos producto del wishlist
				app.get("wishlist").setProduct( $(dmElement).data( "pid" ), aData, "remove" );
			}
			else
			{
				// Si contiene atributos y estamos en product_info, añadimos
				if( $(dmElement).data("attr") && $(dmElement).data("info") )
				{
					// Obtenemos el formulario
					aData = $(dmElement).closest("form").serializeArray();

					// Lo mostramos activo
					$(dmElement).addClass( self.options.class_active );

					// Añadimos producto al wishlist
					app.get("wishlist").setProduct( $(dmElement).data("pid"), aData, "add" );
				}
				// Si contiene atributos y no estamos en product_info, enviamos hacia la ficha del producto
				else if( $(dmElement).data( "attr" ) )
				{
					window.location.href = $(dmElement).data( "href" );
				}
				// Si no contiene atributos, añadimos
				else
				{
					// Lo mostramos activo
					$(dmElement).addClass( self.options.class_active );

					// Añadimos producto al wishlist
					app.get("wishlist").setProduct( $(dmElement).data( "pid" ), false, "add" );
				}
			}
		});
	}

	$.fn.wishlistButton = function(sMethod, aOptions)
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
			var instance = $(this).data("wishlistButton");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new wishlistButtonClass( $(this), aOptions );

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

	wishlistButton = function(aOptions)
	{
		$("[data-wishlistButton]").wishlistButton( "init",  aOptions );
	}
}(jQuery);
