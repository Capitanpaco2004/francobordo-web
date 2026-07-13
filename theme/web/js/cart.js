var cartClass;
var cartBuy;
var cartBuyClass;

!function ($){
	cartClass = function(aOptions)
	{
		// Variables
		var self = this;

		// Opciones
		this.options = {
			"id_cart": "",
			"class_product_closest": ".prdt",
			"class_product_button_buy": ".xprdt .buy:not(.mgp-ajax):not(.opcns)",
			"class_product_quantity": ".cart_quantity",
			"class_product_image": ".img",
			"stock": true
		};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Refrescar carrito
		this.refreshCart = function(bActv)
		{
			// Peticion ajax
			app.get("ajax").send({
				"url": "information.php?call=cart:getHtmlCart",
				"success": function(sHtml)
				{
					if( $(".csta").hasClass("actv") )
						bActv = true;

					// Remplazamos el carrito
					if( sHtml != "" )
						$(self.options.id_cart).replaceWith( sHtml );

					if( bActv )
						$(".csta").addClass("actv");

					// Carrito
					$("#crrt .icon").click(function()
					{
						$(this).parent().toggleClass("actv");
					});

					// Recorremos inputs
					$(".csta .cntd .row input").each(function()
					{
						// Le damos evento al cambiar su contenido
						$(this).change( function()
						{
							// Obtenemos los datos del producto
							var aData = {"products":{}};
							aData["products"][0] = {};
							aData["products"][0]["products_id"] = $(this).data( "id" );
							aData["products"][0]["cart_quantity"] = $(this).val();
							aData["in"] = 1;

							// Añadimos al carrito
							app.get("ajax").send({
								"url": "getCart.php?Cart=1&crt=1",
								"data": {
									products_id: $(this).data( "id" ),
									quantity: $(this).val()
								},
								"success": function()
								{
									// Refrescamos el carrito una vez este insertado
									app.get("cart").refreshCart(false);
								}
							});
						});
					});

					// Eliminar producto carrito
					$("#crrt").on('click', '.dlte', function() {
						// Peticion ajax
						app.get("ajax").send({
							"url": "information.php?call=cart:remove&args=" + $(this).data("id"),
							"success": function()
							{
								// Refrescamos el carrito una vez este insertado
								app.get("cart").refreshCart();
							}
						});
					});
				}
			});
		};

		// Efecto hacia el carrito
		this.effectCart = function(dmElement)
		{
			// Obtenemos la imagen
			var dmImage = $(dmElement.closest( self.options.class_product_closest ).find( self.options.class_product_image ));
            var imgclone = dmImage.clone();

            imgclone.offset({
                "top": dmImage.offset().top,
                "left": dmImage.offset().left
            }).css({
                "opacity": "0.5",
				"position": 'absolute',
				"height": dmImage.height(),
				"width": dmImage.width(),
				"z-index": '100',
				"display": 'block'
            }).appendTo( $('body') ).animate({
				"top": $( self.options.id_cart ).offset().top + 10,
				"left": $( self.options.id_cart ).offset().left + 10,
				"width": 75,
				"height": 75
            }, 1000, 'easeInOutExpo');


            setTimeout(function () {
                $( self.options.id_cart ).effect("shake", {
                    times: 2
                }, 200);
            }, 1500);

            imgclone.animate({
                'width': 0,
                    'height': 0
            }, function () {
                $(this).detach()
            });
		};

		// Boton comprar
		$(this.options.class_button_buy).unbind().cartBuy( this.options );
	}

	// Objeto boton comprar
	cartBuyClass = function(dmElement, aOptions)
	{
		// Si el elemento tiene ya cart pasamos de el
		if( $.type( dmElement.data("cartBuy") ) != "undefined" )
			return false;

		// Variables
		var self = this;
		dmElement = $(dmElement);

		// Opciones
		this.options = {};

		// Unimos las opciones
		this.options = $.extend({}, this.options, aOptions);

		// Peticion ajax para añadir productos al carrito
		this.addProduct = function(aData)
		{
			// Peticion ajax
			app.get("ajax").send({
				"url": "information.php?call=cart:addProductAjax",
				"data": aData,
				"success": function(sHtml)
				{
					$("head").append(sHtml);

					// Refrescamos el carrito una vez este insertado
					app.get("cart").refreshCart();

					// Efecto hacia el carrito
					app.get("cart").effectCart( dmElement );
				}
			});
		};

		// Evento click
		dmElement.click(function()
		{
			// Si tenemos popup de relacionados
			if( $("#rltd-buy").length > 0 )
				 $('#rltd-sldr').resize();

			// Comprobamos el control de stock en caso de ser <= a 0 no realizamos nada
			if( self.options.stock == true && dmElement.data( "qty" ) != null && dmElement.data( "qty" ) <= 0 )
				return false;

			// Array con los datos
			var aData = {"products":{}};
			aData["products"][0] = {};

			// Si el producto se encuentra en un formulario
			if( dmElement.data( "form" ) != null )
			{
				// Atributos
				aData["products"][0]["id"] = {};

				// Obtenemos los posibles elementos requeridos
				var aElementsRequired = dmElement.closest("form").find("[data-required=true]");
				var dmForm = dmElement.closest("form");

				// Recorremos los elementos required
				if( aElementsRequired.length > 0 )
				{
					// Mensaje de error
					var sMensaje = "";

					// Recorremos elementos
					$(aElementsRequired).each(function()
					{
						// Radio
						if( $(this).is("input:radio") && dmForm.find("input[name='" + $(this).attr("name") + "']:checked").length == 0 )
						{
							sMensaje = "Debes seleccionar un valor para \"" + $(this).data("name") + "\"";
							return false;
						}

						// Select
						if( $(this).is("select") && ($(this).find("option:selected").length == 0 || $(this).find("option:selected").val() == '') )
						{
							sMensaje = "Debes seleccionar un valor para \"" + $(this).data("name") + "\"";
							return false;
						}
					});

					// Si tenemos un error
					if( sMensaje != "" )
					{
						alert(sMensaje);
						return false;
					}
				}

				// Obtenemos el form
				var aAux = dmElement.closest("form").serializeArray();

				aData["products"][0]["id"] = [];

				$.each( aAux,function(a,b)
				{
					var value = $(this)[0].value;
					var key = $(this)[0].name;

					if( $(this).attr("name").match( /^id/g ) )
						aData["products"][0]["id"].push( [key.replace( /^id\[|\]$/g, "" ),value] );
					else
						aData["products"][0][key] = value;
				});
				dmForm.find('select').each(function() {
					var selected = $(this).find('option:selected');
					if (selected.attr('data-status-text') && selected.attr('data-status-text').indexOf('(Bajo pedido') !== -1) {
						$('#fich').find('.ajx-bjo').click();
						return false;
					}
				});
			}
			// Si no nos encontramos en un form
			else
			{
				// Variables
				var nProductId = dmElement.data( "id" );
				var nCantidad = 1;

				// Cantidad
				if( self.options.class_product_quantity != "" && dmElement.closest( self.options.class_product_closest ).find( self.options.class_product_quantity ).length > 0 )
					nCantidad = dmElement.closest( self.options.class_product_closest ).find( self.options.class_product_quantity ).val();

				// Si el producto contiene atributos
				if( dmElement.data( "atribute" ) != "" )
				{
					window.location.href = dmElement.data("href");
					return false;
				}
				// Si no contiene atributos
				else
				{
					// Obtenemos el products_id
					aData["products"][0]["products_id"] = nProductId;

					// Obtenemos la cantidad
					aData["products"][0]["cart_quantity"] = nCantidad;
				}
			}

			// Popup bajo demanda
			if( dmElement.closest(".prdt-bjpdd") )
				$(dmElement.closest(".prdt-bjpdd").find(".ajx-bjo")).trigger("click");

			// Pre-comprobación de stock: si la cantidad pedida supera el stock real
			// y el producto NO tiene check_stock, mostramos el modal de confirmación
			// (Cancelar/Aceptar). Sólo aplica al form de la ficha de producto.
			var dmFormCheck = dmElement.closest("form#fich");
			if( dmFormCheck.length > 0 )
			{
				var nFormCheckStock = parseInt( dmFormCheck.attr("data-check-stock"), 10 ) || 0;
				if( nFormCheckStock === 0 )
				{

					// Control de stock POR VARIANTE: si la variante seleccionada tiene flag
					// propio (mapa #array_option_checkstock emitido por option.class.php),
					// NO ofrecemos el modal "7-10 dias" y dejamos el submit normal: el
					// servidor capa la cantidad al stock real, igual que con el check_stock
					// del producto principal.
					var bVariantCheck = false;
					var dmMapCS = $("#array_option_checkstock");
					if( dmMapCS.length > 0 && dmMapCS.text().trim() !== "" && dmMapCS.text().trim() !== "[]" )
					{
						try {
							var aMapCS = JSON.parse( dmMapCS.text() );
							var aKeyCS = [];
							dmFormCheck.find("select[data-oid]").each(function(){
								var vCS = $(this).val();
								var oidCS = $(this).data("oid");
								if( vCS && oidCS ) aKeyCS.push( oidCS + "-" + vCS );
							});
							if( aKeyCS.length > 0 && aMapCS.hasOwnProperty( aKeyCS.join(",") ) )
								bVariantCheck = true;
						} catch(eCS) {}
					}
					// Cantidad pedida
					var nReqQty = parseInt( dmFormCheck.find("input.cart_quantity").val(), 10 ) || 0;
					// Stock disponible (variante o producto)
					var nAvailStock = parseInt( dmFormCheck.attr("data-stock"), 10 ) || 0;
					var dmSelStock = $("#array_option_stock");
					if( dmSelStock.length > 0 && dmSelStock.text().trim() !== "" && dmSelStock.text().trim() !== "[]" )
					{
						try {
							var aMap = JSON.parse( dmSelStock.text() );
							// Construimos la clave a partir de los selects con value
							var aKey = [];
							dmFormCheck.find("select[data-oid]").each(function(){
								var v = $(this).val();
								var oid = $(this).data("oid");
								if( v && oid ) aKey.push( oid + "-" + v );
							});
							if( aKey.length > 0 )
							{
								var sKey = aKey.join(",");
								if( aMap.hasOwnProperty( sKey ) )
									nAvailStock = parseInt( aMap[sKey], 10 ) || 0;
							}
						} catch(e) {}
					}

					if( !bVariantCheck && nReqQty > nAvailStock && nAvailStock > 0 && typeof window.showStockConfirm === "function" )
					{
						// Componemos nombre de producto + variante seleccionada (p.ej. "...Oval - 200mm")
						var sProductName = dmFormCheck.attr("data-product-name") || "";
						var aVariant = [];
						dmFormCheck.find("select[data-oid] option:selected").each(function(){
							var s = $.trim( $(this).text() );
							if( s !== "" ) aVariant.push( s );
						});
						if( aVariant.length > 0 )
							sProductName += " - " + aVariant.join(", ");

						window.showStockConfirm({
							stock: nAvailStock,
							qty: nReqQty,
							productName: sProductName,
							mode: "confirm",
							onAccept: function(){
								aData["stock_confirmed"] = 1;
								self.addProduct( aData );
							},
							onCancel: function(){ /* no-op */ }
						});
						return;
					}
				}
			}

			// Compramos
			self.addProduct( aData );
		});
	}

	$.fn.cartBuy = function(sMethod, aOptions)
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
			var instance = $(this).data("cartBuy");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new cartBuyClass( $(this), aOptions );

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

	cartBuy = function(aOptions)
	{
		$("[data-cartBuy]").cartBuy( "init",  aOptions );
	}
}(jQuery);
