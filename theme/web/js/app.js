// Variables
var bFixed = false;
var dmNav = $("#head");
var dmHead = $("#banrs-home").length > 0 ? $("#banrs-home") : $("#titu1");
var nHeightNav;

// Galeria de ficha (feature "imagen por valor de atributo"):
// reconstruye #slick-fich. Si el valor seleccionado tiene imagen(es) de atributo, muestra
// SOLO esas (reemplaza la galeria); si no tiene, restaura la galeria original del producto.
// window.amFichGalleryOrig se captura en createSlick() antes de inicializar Slick (HTML limpio).
window.amAttrImgPending = null;
function amRebuildFichGallery(aImages) {
	var $gal = $("#slick-fich");
	if (!$gal.length || typeof window.amFichGalleryOrig === 'undefined') return;

	var sPrepend = "";
	if (aImages && aImages.length && aImages[0] !== "") {
		// Caja de referencia = altura de la imagen principal original del producto, para encajar
		// la imagen del atributo (object-fit:contain) y que no salga mas grande/alta que las demas.
		var refH = 0;
		try {
			var tmpDiv = document.createElement('div');
			tmpDiv.innerHTML = window.amFichGalleryOrig;
			var refImg = tmpDiv.querySelector('img[height]');
			if (refImg) refH = parseInt(refImg.getAttribute('height'), 10) || 0;
		} catch (e) {}
		var sImgStyle = refH
			? 'max-width:100%;width:auto;height:auto;max-height:' + refH + 'px;object-fit:contain;display:block;margin:0 auto;'
			: 'max-width:100%;height:auto;display:block;margin:0 auto;';

		$.each(aImages, function(i, sFile) {
			if (sFile === "") return;
			var sSrc = "images/atributos/" + sFile;
			sPrepend += '<div class="item img attr-img" data-thumb="' + sSrc + '" data-src="' + sSrc + '"><picture><img src="' + sSrc + '" style="' + sImgStyle + '" border="0" alt="" /></picture></div>';
		});
	}

	// Evitamos reconstruir si el estado (prepend) no cambia, para no parpadear ni resetear posicion.
	if ($gal.data("amAttrState") === sPrepend) return;
	$gal.data("amAttrState", sPrepend);

	if ($gal.hasClass("slick-initialized")) $gal.slick("unslick");
	try { if ($gal.data("lightGallery")) $gal.data("lightGallery").destroy(true); } catch (e) {}

	// Si el valor seleccionado tiene imagen(es) de atributo, mostramos SOLO esas (reemplazamos
	// la galeria). Si no tiene, restauramos la galeria original del producto.
	$gal.html(sPrepend !== "" ? sPrepend : window.amFichGalleryOrig);
	$gal.children().removeClass("actv").first().addClass("actv");

	$gal.not(".slick-initialized").slick({
		slidesToShow: 1, slidesToScroll: 1, dots: true, infinite: true, cssEase: 'linear',
		prevArrow: false, nextArrow: false,
		customPaging: function(slider, i) {
			var thumb = $(slider.$slides[i]).data('thumb');
			return '<a><img src="' + thumb + '"></a>';
		}
	});
	$gal.lightGallery({selector: '.item:not(.slick-cloned):not(.item-3d)'});
}

var appClass = function()
{
	// Variables
	var self = this;

	// Paginación por scroll //
	var next_data_cache;
	var prev_data_cache;
	var last_scroll = 0;
	var is_loading = 0;
	var hide_on_load = false;

	// Extendemos la clase
	$.extend( self, new containerClass() );

	// Funcion que se llama para el eventReady
	this.eventReady = function()
	{
		// Alert
		self.set( "alert", new alertClass() );

		// LazyLoad
		self.set( "lazy", new LazyLoad({elements_selector: ".lazy"}) );

		// Eventos
		self.reloadEventAjax();

		// Metodos oscdenox
		self.set( "oscdenox", new oscdenoxClass() );

		// Prevenir doble click en formularios
		self.get("oscdenox").formPreventSendingTwice();

		// Cambiar de vista
		self.get("oscdenox").changeView();

		// Evento para los codigo postales, provincias, paises
		self.get("oscdenox").loadCitiesCP();

		// Search autocomplete
		if ($("#buscar").attr("denox")) {
			self.get("oscdenox").searchAutocomplete( $("#buscar") );
		}

		// RGPD
		self.get("oscdenox").rgpd();

		// Carrito
		self.set( "cart", new cartClass({
			"class_button_buy": ".xprdt .buy:not(.mgp-ajax):not(.opcns)",
			"id_cart": "#crrt",
			"class_product_closest": ".xprdt"
		}) );

		// Wishlist
		self.set( "wishlist", new wishlistClass({
			"class_wishlist_button": ".xprdt .fvrt"
		}))

		// Cargar carrito en shopping_cart de oscommerce que da fallo con mediaqueries
		if( $("#span_cart").length > 0 ) loadCartOsc();

		// Programaciones a medida del proyecto
		self.customProject();

		// Varias funciones de oscdenox que no sabemos donde encajarlas
		self.get("oscdenox").functionsDisaster();

		// Forzamos el event responsive
		// self.resizingResponsive();

		// Forzamos el event resize
		self.resizing();

		// Forzamos el event scroll
		self.eventScroll();

		// Paginacion por scroll //
		if( $('.contentScroll').length > 0 ) {
			self.primeCache();

			self.scroll_infinite();
			$(window).scroll(self.scroll_infinite);
		}

		// Atributos
        $.fn.attributes( $("#fich"),
		{
            execute_all_actions: true,
            product_price: $("#fich .right .wrpr-prco-buy .wrpr-prco .prco").text().replace("€", "").replace(".", "").replace(",", "."),
            product_price_special: $("#fich .right .wrpr-prco-buy .wrpr-prco span").text().replace("€", "").replace(".", "").replace(",", "."),
            name_box_attribute: ".box-attr",
            name_class_disabled: "zero",
            name_quantity: ".cart_quantity",
            callInStock: function()
			{
				if( $("#fich").length > 0 ) {
					$("#fich").removeClass("prdt-agtd");
					$("#fich-fixe").removeClass("prdt-agtd");
				}
            },
            callOutStock: function(sCombinacion)
			{
				if( $("#fich").length > 0 ) {
					$("#fich").addClass("prdt-agtd");
					$("#fich-fixe").addClass("prdt-agtd");
				}

				if( $("#fich-info a.icon-agtd").length > 0 )
				{
					$("#fich-info a.icon-agtd").attr('href', 'notify.php?id=' + $('input[name="products_id"]').val() + '&atributo=' + sCombinacion);
				}
            },
            callAction: function(sCombinacion, sValue, sAction)
			{
                switch (sAction)
				{
                    case "change_image":
						// Solo marcamos la imagen pendiente; la galeria la reconstruye callSelectOption
						// (que se dispara despues, en cada seleccion) conservando la galeria original.
						window.amAttrImgPending = sValue.split("[dxsepare]");
					break;
					case "wishlist":
						var sCombinacion = self.get("wishlist").attributeSelect(sCombinacion);

						$(".wrpr-buy .fvrt").removeClass("actv");

					break;
                }
            },
            changePrice: function(sPriceNew, dmThis)
			{
				sPriceNewFormat = new Intl.NumberFormat('de-DE').format(parseFloat(sPriceNew.toString().replace("€", ""))) + '€'

				if (dmThis.data("price-text") != '') {
					sPriceNewFormat = dmThis.data("price-text")
				}

                $("#fich .right .wrpr-prco-buy .wrpr-prco .prco").text(sPriceNewFormat);

				// Tachado (precio anterior) POR VARIANTE: el <s> es hermano de .prco dentro de .wrpr-prco y
				// solo lo emite el template cuando hay oferta. .prco.text() (arriba) no lo toca (no es descendiente).
				// combobox.class.php expone data-price-last = precio completo (sin oferta) de la variante, solo si hay oferta.
				var dmWrprPrco = $("#fich .right .wrpr-prco-buy .wrpr-prco");
				var sPriceLast = (typeof dmThis.data("price-last") !== 'undefined') ? String(dmThis.data("price-last")) : '';
				if (sPriceLast !== '') {
					var dmS = dmWrprPrco.children("s");
					if (dmS.length === 0) {
						dmS = $('<s></s>').insertBefore(dmWrprPrco.children(".prco").first());
					}
					dmS.text(sPriceLast);
				} else {
					dmWrprPrco.children("s").remove();
				}

				// Color rosa de oferta (#fich.prdt-ofrt .prco): la variante en oferta debe verse
				// como oferta tambien en el precio. Recordamos si la clase venia de serie
				// (special de producto, server-side) para no quitarla nunca en ese caso.
				var dmFich = $("#fich");
				if (typeof dmFich.data("ofrt-base") === 'undefined') {
					dmFich.data("ofrt-base", dmFich.hasClass("prdt-ofrt") ? 1 : 0);
				}
				if (sPriceLast !== '') {
					dmFich.addClass("prdt-ofrt");
				} else if (dmFich.data("ofrt-base") !== 1) {
					dmFich.removeClass("prdt-ofrt");
				}
            },
            changePriceSpecial: function(sPriceNew)
			{
                $("#fich .right .wrpr-prco-buy .wrpr-prco .prco span").text(sPriceNew.replace(".", ","));
			},
			callSelectOption: function(dmThis)
			{
				if ($(".date-expiry")) {
					if (dmThis.data("expiry") != '') {
						$(".date-expiry").css("display", "block");
						$(".date-expiry strong").html(dmThis.data("expiry"));
					} else {
						$(".date-expiry").css("display", "none");
					}
				}

				if ($("#fich .right .mdel").length) {
					$("#fich .right .mdel").text('Ref.: ');

					if (typeof dmThis.data("reference") === 'undefined') {
						$("#fich .right .mdel").text('Ref.: No seleccionada');


						var price_list = []
						$('#fich .box-attr.cmbo select option').each(function() {
							if (!price_list.includes($(this).attr('data-price-text')) && typeof $(this).attr('data-price-text') !== 'undefined') {
								price_list.push($(this).attr('data-price-text'))
							}
						});

						if ($('#fich .right .wrpr-prco-buy .wrpr-prco .prco').length && $('#fich .right .wrpr-prco-buy .wrpr-prco .prco .price-from').length == 0 && price_list.length > 1) {
							$('<span class="price-from" style="font-size: 18px;display: block;font-weight: normal;line-height: 0.5;">Desde:</span>').prependTo('#fich .right .wrpr-prco-buy .wrpr-prco .prco')
						}

					} else {
						if(dmThis.data("reference") !== null) {
							$("#fich .right .mdel").text('Ref.: ' + dmThis.data("reference"));

							if ($('#fich .right .wrpr-prco-buy .wrpr-prco .prco .price-from').length) {
								$('#fich .right .wrpr-prco-buy .wrpr-prco .prco .price-from').remove()
							}

						}
					}
				}

				if ($("#fich .ship").length && dmThis.data("value") !== null) {

					products_id = $("#fich").find('input[name=products_id]').val()
					value = dmThis.attr("value")
					option = dmThis.parent('select').data("oid")

					$.ajax( {
						type: "POST",
						url: "product_info.php?a=get-shipping-text",
						dataType: 'json',
						data: {
							'products_id': products_id,
							'option': option,
							'value': value
						},
						success: function(data)
						{
							if (data.text != '') {
								$("#fich .ship .text-shipping").html(data.text)
								$("#fich .ship").css("color", "")
							}

							if (data.button != '' && typeof data.button !== 'undefined') {
								$("#fich .right .wrpr-prco-buy .wrpr-buy .buy").val(data.button)
							}

							$("#fich").removeClass('prdt-4dias prdt-5dias prdt-bjpdd prdt-agtd');
							if (data.classes && data.classes !== '') {
								$("#fich").addClass(data.classes);
							}

							if (data.buttonDisabled) {
								$("#fich .right .wrpr-prco-buy .wrpr-buy input.buy").hide()
								if ($("#fich .right .wrpr-prco-buy .wrpr-buy .buy.button-notify-original").length == 0) {
									$("#fich .right .wrpr-prco-buy .wrpr-buy .buy.button-notify").show()
								}

							} else {
								$("#fich .right .wrpr-prco-buy .wrpr-buy input.buy").show()
								$("#fich .right .wrpr-prco-buy .wrpr-buy .buy.button-notify").hide()
							}

							dmThis.closest('aside').find(".atitu .color").css('display', 'none')
							if (data.color != '') {
								dmThis.closest('aside').find(".atitu .color").html(data.color)
								dmThis.closest('aside').find(".atitu .color").css('display', 'flex')
							}
						}
					});
				}

				// Si tenemos atributo pack
				if( $("#fich .buy.ntb.mgp-ajax").length > 0 && dmThis.text().match(/^(Pack \d*)/) ) {
					var nPack = parseInt( dmThis.text().match(/^(Pack \d*)/)[0].replace("Pack", "") );

					// Si son unidades en lote
					if( dmThis.text().match(/(\d*[x]\d*)/) ) {
						// Obtenemos el lote
						var nLote = dmThis.text().match(/(\d*[x]\d*)/)[0];
						// Nos quedamos con el primer valor
						var num = nLote.match(/([\d]+)/g)[0];
						nPack = nPack / parseInt( num );
					}

					 $("#fich .buy.ntb.mgp-ajax").attr('href', function(i,a){
						return a.replace( /(pack=)[0-9]+/ig, '$1' + parseInt( nPack ) );
					});
				}
				var dmParent = $(dmThis).closest(".cntd");
				dmParent.find(".val.actv").removeClass("actv");
				dmParent.find(".val").eq( $(dmThis).parent().prop("selectedIndex") ).addClass("actv");

					// Galeria: conservar la original y anteponer la imagen del atributo si el valor la tiene
					// (change_image, que corre antes, ha dejado la imagen en window.amAttrImgPending).
					amRebuildFichGallery(window.amAttrImgPending);
					window.amAttrImgPending = null;
			}
        });
	}

	// Recargar eventos ajax
	this.reloadEventAjax = function()
	{
		// Tabs
		tabs();

		// Accordion
		accordion();

		// Formularios
		$(".xform select, .xform input, .xfcant, .xform-star").not(".not").form();

		// Magnific-popup inline
		$(".mgp-inln").each(function()
		{
			$(this).magnificPopup({type: 'inline', modal: $(this).data("modal"), fixedContentPos: true, fixedBgPos: false, preloader: false, midClick: true, removalDelay: 300, mainClass: 'my-mfp-zoom-in', callbacks:{open: function(){var dmContent = this.content; setTimeout( function(){$(dmContent).find("form *:input[type!=hidden]:first").focus(); }, 100 ); } }});
		});

		// Magnific-popup ajax
		$(".mgp-ajax").magnificPopup({type: 'ajax', mainClass: 'mgp-ajax', callbacks: {}});

		// Trigger Magnific-popup auto
		$(".mgp-auto").trigger("click");

		// Anchor que tengan que confirmar antes de realizar el href
		$("a[data-confirm]").unbind( "click.confirm" ).on( "click.confirm", function(e){ e.stopPropagation(); return confirm( $(this).data("confirm") ); });

		// Anchor que tengan que confirmar antes de realizar el href
		$("*[data-alert]").unbind( "click.alert" ).on( "click.alert", function(e){
			e.stopPropagation();

			self.get("alert").alert( $(this).attr('data-alert-icon') || "success", $(this).attr('data-alert-title') || "", $(this).attr('data-alert'), $(this).attr('data-alert-button') || "");

			return false;
		});

		// Select2
		$(".select2").each(function(){ $(this).select2({placeholder: $(this).data("holder")});});
		$(".select2-attributes").each(function(){
			$(this).select2({
				placeholder: $(this).data("holder"),
				templateResult: function(optionElement) {

					if (!optionElement.id) {
						return optionElement.text;
					}
					var $state = $('<span>' + optionElement.text + '</span> ' + '<strong style="margin-left: 10px;">' + $(optionElement.element).attr('data-price-text') + '</strong> <span style="margin-left: 10px; font-size: 0.85em;color: ' + $(optionElement.element).attr('data-status-text-color') + ';">' + $(optionElement.element).attr('data-status-text') + '</span>' );
					return $state;

				}
			});
		});

        // Placeholders
        $('input, textarea').placeholder();

        // Lazyload
        self.get("lazy").update();
	}

	// Programaciones a medida del proyecto
	this.customProject = function()
	{

		/**
		YKV-200-68693
		 */
		if (typeof attr_seleted !== 'undefined') {
			$('#fich .box-attr.cmbo select option[value="' + attr_seleted + '"]').prop('selected', true)
			$('#fich .box-attr.cmbo select').change()
		}

		$("body").on("change", ".load-states-shipping-estimator", function () {

			$.ajax( {
				"url": "information.php?call=ajaxCountryZoneCity",
				"type": "post",
				"data": {"a": "getZones", "country": $(this).val(), 'name_zone_select': 'state'},
				"success": function( sJson )
				{
	                sJson = $.parseJSON(sJson);

					if( sJson.zones.length > 0 )
						$('.states-shipping-estimator').html(sJson.zones).find('select').select2();;

				}
			});
			return false;
		});

		if ($('body').hasClass('Christmas')) {
			$.fn.snow({
				flakeColor: '#00aff0'
			});
		}

		if ($('body').hasClass('BlackFriday')) {
			$.fn.snow({
				flakeChar: '%',
				flakeColor: '#00aff0'
			});
		}


		if (window.location.hash == '#cmtr-buttn') {
			if ($('#fich .right .wrpe-str .infr u').length) {
				setTimeout(() => {
					$('#fich .right .wrpe-str .infr u').click()
					$('#new-cmtr').click()
					$('#fich-cmmt').parent().find('.xaccordion-title').click()

					setTimeout(() => {
						$('.opin-righ input[name="customers_name"]').focus()
						$('html, body').animate({
							scrollTop: $('.opin-righ input[name="customers_name"]').offset().top - 75
						}, 100);
					}, 300);
				}, 1000);
			}
		}

		// Ocultar redes flotantes
		$('#redsoc span').click( function()
		{
			$(this).parent().parent().toggleClass('show');
		});

		$(".delete-card").click(function () {
			if (confirm($(this).attr("data-msg")))
				window.location = $(this).attr("data-href");
		});

		// Inicio, slide descripciones ficha producto
		$(".list-down .titl").click(function () {
			$(this).toggleClass("actv");
			$(this).next().slideToggle("slow");
		});
		// Fin, slide descripciones ficha producto

		// Productos relacionados con oferta
		$(".fich-wrpr-rows .wrpr-rows .col-1 .xform select").change(function () {
			var dmOption = $(this).find("option:selected");

			obj = $(this)
			products_id = obj.closest(".xprdt.row").find('input[name=products_id]').val()
			value = obj.val()
			option = obj.data("oid")

			obj.closest(".xprdt.row").removeClass('prdt-4dias prdt-5dias prdt-bjpdd prdt-agtd')

			$.ajax( {
				type: "POST",
				url: "product_info.php?a=get-shipping-text",
				dataType: 'json',
				data: {
					'products_id': products_id,
					'option': option,
					'value': value ,
					"products_together": true
				},
				success: function(data)
				{
					obj.closest(".xprdt.row").find(".xform .text").html(data.text)
					//console.log(obj.closest(".xprdt.row").find(".ref .cl2").length)
					if (data.text != '') {
						obj.closest(".xprdt.row").find(".xform .text").html(data.text)
						if (obj.closest(".xprdt.row").find(".ref .cl2").length > 0) {
							obj.closest(".xprdt.row").find(".xform .text").css('display', 'none')
						}
					}

					if (data.button != '' && typeof data.button !== 'undefined') {
						obj.closest(".xprdt.row").find(".buy").val(data.button)
					}

					obj.closest(".xprdt.row").find(".buy").prop("disabled", data.buttonDisabled);

					if (data.classes != '') {
						obj.closest(".xprdt.row").addClass(data.classes)
					}

					obj.closest(".xprdt.row").find("label .color").css('display', 'none')
					if (data.color != '') {
						obj.closest(".xprdt.row").find("label .color").html(data.color)
						obj.closest(".xprdt.row").find("label .color").css('display', 'flex')
					}
				}
			});

			//console.log([dmOption.data("price-last"), dmOption.data("price")])
			if( dmOption.data("price-last") != dmOption.data("price") )
			{
				obj.closest(".xprdt.row").find(".prco").addClass("prdt-ofrt");
				obj.closest(".xprdt.row").find(".prco").html("<s>" + dmOption.data("price-last") + "</s>" + dmOption.data("price"));
			}
			else
			{
				obj.closest(".xprdt.row").find(".prco").removeClass("prdt-ofrt");
				obj.closest(".xprdt.row").find(".prco").html(dmOption.data("price"));
			}
		});

		// Slide comentario
		$("#new-cmtr").click(function(nIndex, dmElement)
		{
			$("#cmtr-form").slideToggle("slow");
		});

		// Comprueba que al introducir cantidad de un producto sea un número.
		$(".cart_quantity").on('keypress', function (event)
		{
			var key = window.event ? event.keyCode : event.which;

			if( key == 8 || event.keyCode == 37 || event.keyCode == 39 )
				return true;
			else if ( key < 48 || key > 57 )
				return false;
			else
				return true;
		});

		// Ver más productos
		$("#more-prdt").click( function()
		{
			var sThis = $(this);

			app.get("ajax").send({
				"url": sThis.data("url") + "?page=" + (sThis.data("page")+1) + (sThis.data("param") ? "&" + sThis.data("param") : ""),
				"success": function( sHtml )
				{
					$(".prdt-cntd .col.a12:last-child").before( sHtml );

					self.set( "cart", new cartClass({
						"class_button_buy": ".xprdt .buy:not(.mgp-ajax):not(.opcns)",
						"id_cart": "#crrt",
						"class_product_closest": ".xprdt"
					}) );

					// Wishlist
					self.set( "wishlist", new wishlistClass({
						"class_wishlist_button": ".prdt .fvrt"
					}))

					// Comparar productos
					$(".compr").unbind().click(function () {
						if ($(this).hasClass("actv")) {
							$(this).removeClass("actv");
							$(this).parent().find("input").prop("checked", false);
						} else {
							$(this).addClass("actv");
							$(this).parent().find("input").prop("checked", true);
						}
					});

					$(".compr input:checked").parent().addClass("actv");

					if( sThis.data("maxpage") <= (sThis.data("page")+1) )
						sThis.css("display", "none");
					else
						sThis.data("page", (sThis.data("page")+1));

					$("#fltr .ctdrows").text( $(".prdt-cntd .xprdt").length );
				}
			});
		});

		// Ver productos anteriores
		$("#less-prdt").click( function()
		{
			var sThis = $(this);

			app.get("ajax").send({
				"url": sThis.data("url") + "?page=" + (sThis.data("page")-1) + (sThis.data("param") ? "&" + sThis.data("param") : ""),
				"success": function( sHtml )
				{
					$(".prdt-cntd .col.a12:first-child").after( sHtml );

					self.set( "cart", new cartClass({
						"class_button_buy": ".xprdt .buy:not(.mgp-ajax):not(.opcns)",
						"id_cart": "#crrt",
						"class_product_closest": ".xprdt"
					}) );

					// Wishlist
					self.set( "wishlist", new wishlistClass({
						"class_wishlist_button": ".prdt .fvrt"
					}))

					// Comparar productos
					$(".compr").unbind().click(function () {
						if ($(this).hasClass("actv")) {
							$(this).removeClass("actv");
							$(this).parent().find("input").prop("checked", false);
						} else {
							$(this).addClass("actv");
							$(this).parent().find("input").prop("checked", true);
						}
					});

					$(".compr input:checked").parent().addClass("actv");

					if( (sThis.data("page")-1) <= 1 )
						sThis.css("display", "none");
					else
						sThis.data("page", (sThis.data("page")-1));

					$("#fltr .ctdrows").text( $(".prdt-cntd .xprdt").length );
				}
			});
		});

		$('.rmaHistoryStatus p a').click(function () {
			$(this).parents('.rmaHistoryStatus').toggleClass('Active')
		})

		$('.initRma').click(function () {
			var sUrl = $(this).attr('href')
			$('<div class="rmaAjax">').appendTo('body')
			$.get(sUrl, function (data) {
				$(data).appendTo('.rmaAjax')
				$('.rmaAjax .rmaContent').addClass('Active')
				$('<a href="javascript:void(0);" class="rmaClose">&times;</a>').appendTo('.rmaContent')
			})

			return false;
		})

		// Buffer de archivos seleccionados por el cliente (uno a uno, con thumbnails)
		// Se llena vía change del input file y se vacía al hacer submit.
		var rmaSelectedFiles = []
		var RMA_MAX_FILES = 5
		var RMA_MAX_BYTES = 5 * 1024 * 1024

		// Helper i18n: usa window.RMA_LANG si existe (inyectado por el template PHP), fallback a ES
		function rmaT(key, vars) {
			var dict = (window.RMA_LANG || {})
			var defaults = {
				attach_max:    'Máximo {N} archivos. Se ignoran los restantes.',
				attach_toobig: '"{NAME}" supera 5 MB y no se subirá.',
				attach_remove: 'Quitar este archivo',
				attach_count:  '{N} archivo(s) seleccionado(s)'
			}
			var s = dict[key] || defaults[key] || key
			if (vars) for (var k in vars) s = s.split('{' + k + '}').join(vars[k])
			return s
		}

		function rmaRenderPreview() {
			var $list = $('.rmaAttachmentsPreview')
			$list.empty()
			rmaSelectedFiles.forEach(function (f, idx) {
				var kb = (f.size / 1024).toFixed(1)
				var $item = $(
					'<li style="display:flex;align-items:center;gap:10px;padding:6px 8px;margin:4px 0;background:#f4f4f4;border:1px solid #ddd;border-radius:4px">' +
						'<span class="rmaThumb" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:3px;border:1px solid #ddd;font-size:18px;color:#888">📄</span>' +
						'<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>' +
						'<button type="button" class="rmaRemoveFile" data-idx="' + idx + '" title="' + rmaT('attach_remove') + '" style="background:#a02020;color:#fff;border:none;border-radius:3px;padding:3px 10px;cursor:pointer;font-size:11px">✕</button>' +
					'</li>'
				)
				$item.find('span').eq(1).text(f.name + ' (' + kb + ' KB)')
				if (f.type && f.type.indexOf('image/') === 0) {
					var reader = new FileReader()
					reader.onload = (function ($thumb) {
						return function (e) {
							$thumb.html('<img src="' + e.target.result + '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:3px" />')
						}
					})($item.find('.rmaThumb'))
					reader.readAsDataURL(f)
				}
				$list.append($item)
			})
			// El input file: se oculta si se llegó al máximo, se reactiva si hay hueco
			var $input = $('.rmaAttachments')
			if (rmaSelectedFiles.length >= RMA_MAX_FILES) {
				$input.prop('disabled', true).next('.rmaAddMoreHint').show()
			} else {
				$input.prop('disabled', false)
				$('.rmaAddMoreHint').hide()
			}
			// Contador visible
			$('.rmaAttachmentsCount').text(rmaSelectedFiles.length > 0 ? rmaT('attach_count', { N: rmaSelectedFiles.length }) : '')
		}

		$("body").on("change", ".rmaAttachments", function () {
			var files = this.files
			for (var i = 0; i < files.length; i++) {
				if (rmaSelectedFiles.length >= RMA_MAX_FILES) {
					alert(rmaT('attach_max', { N: RMA_MAX_FILES }))
					break
				}
				if (files[i].size > RMA_MAX_BYTES) {
					alert(rmaT('attach_toobig', { NAME: files[i].name }))
					continue
				}
				rmaSelectedFiles.push(files[i])
			}
			this.value = ''  // reiniciar input para poder elegir otro archivo después
			rmaRenderPreview()
		})

		$("body").on("click", ".rmaRemoveFile", function () {
			var idx = parseInt($(this).data('idx'), 10)
			if (!isNaN(idx)) {
				rmaSelectedFiles.splice(idx, 1)
				rmaRenderPreview()
			}
		})

		$("body").on("submit", ".rmaPage", function () {
			formRma = $(this)
			$('.rmaAjax').addClass('Loading')
			var sAction = formRma.attr('action')
			var bMultipart = (formRma.attr('enctype') || '').toLowerCase() === 'multipart/form-data'
			var oncomplete = function (data) {
				$(".rmaContent").replaceWith(data)
				formRma.removeClass('Loading')
				$('.rmaAjax .rmaContent').addClass('Active')
				$('<a href="javascript:void(0);" class="rmaClose">&times;</a>').appendTo('.rmaContent')
				// Limpiar buffer una vez enviado
				rmaSelectedFiles = []
			}
			if (bMultipart) {
				// FormData con los archivos del buffer JS (no del input nativo)
				var fd = new FormData(formRma[0])
				fd.delete('attachments[]')
				rmaSelectedFiles.forEach(function (f) {
					fd.append('attachments[]', f, f.name)
				})
				$.ajax({ url: sAction, type: 'POST', data: fd, processData: false, contentType: false, success: oncomplete })
			} else {
				$.post(sAction, formRma.serialize(), oncomplete)
			}
			return false;
		});

		$("body").on("click", ".rmaFinish", function () {
			$(this).addClass('Loading')
			location.reload();
			return false;
		});

		$("body").on("click", ".rmaAjax .rmaClose", function () {
			$('.rmaAjax').remove()
			return false;
		});

		$("body").on("click", ".rmaAjax .rmaCloseButton", function () {
			$('.rmaAjax').remove()
			return false;
		});

		$("body").on("change", ".rmaTypeReturn", function () {
			isAgencia = parseInt($(this).attr('data-agencia'))
			$('.rmaTypeReturnView').removeClass('Visible')
			$('.rmaTypeReturnView').find('input').prop('required', false)
			$('.rmaTypeReturnView[data-agencia = "' + isAgencia + '"]').addClass('Visible')
			if ($('.rmaTypeReturnView').find('input').is(":visible")) {
				$('.rmaTypeReturnView[data-agencia = "' + isAgencia + '"]').find('input').prop('required', true)
			}
			return false;
		});

		$("body").on("change", ".rmaIsAddress", function () {
			isAddress = parseInt($(this).attr('data-address'))
			if (isAddress == 1) {
				$('.rmaIsAddressContent').addClass('Active')
			} else {
				$('.rmaIsAddressContent').removeClass('Active')
			}

			$('.rmaIsAddressContent').find('input, select').prop('required', false)
			$('.rmaIsAddressContent.Active').find('input, select').prop('required', true)

			return false;
		});

		$('.checkFormWaiting').submit(function () {
			if ($('input[name=dxconfianza]').length > 0) {
				if ($('input[name=dxconfianza]').is(':checked')) {
					$('#checkoutShippingButton').text($('#checkoutShippingButton').data('text'))
					$('#checkoutShippingButton').addClass('Loading')
					$('#checkoutShippingButton').prop("disabled", true)
				}
			} else {
				$('#checkoutShippingButton').text($('#checkoutShippingButton').data('text'))
				$('#checkoutShippingButton').addClass('Loading')
				$('#checkoutShippingButton').prop("disabled", true)
			}
		});

		$('.buttonValorar').click(function () {
			boton = $(this);
			order_id = parseInt(boton.attr('data-id'))
			text = boton.text()

			if (order_id > 0) {
				boton.html('<i class="fa fa-sync fa-spin fa-fw"></i>')
				$.post("cron_opiniones.php?order_id=" + order_id, function (data) {
					result = parseInt(data)
					if (result == 0)
						swal("No hemos podido llevar a cabo el proceso", "Esto puede ser debido a que ya hemos enviado un correo electrónico indicando las instrucciones para valorar el pedido. \nSi el problema persite, no dude <a href='contact_us.php'>en contactar con nosotros</a>\nMuchas gracias.")
					else
						swal("Bien!", "Hemos enviado un correo electrónica a su correo electrónico con las instrucciones para valorar su pedido.\nMuchas gracias.", "success")
					boton.text(text)
				});
			}

		});

		// Inicio, tabla responsive
		$(".fble").each(function (nIndex, dmElement) {
			$(dmElement).find(".fble-th .fble-td").each(function (nIndex1, dmTh) {
				var dmAux = $(dmTh).clone().attr("class", "fble-fake");

				$(dmElement).find(".fble-tr .fble-col-" + (nIndex1 + 1)).prepend(dmAux);
			});
		});
		// Fin, tabla responsive

		// Inicio, select con las tiendas en checkout
		if ($(".pagos input:checked").length > 0)
			$(".pagos input:checked").closest(".moduleRow").trigger("click")
		else
			$($(".pagos input")[0]).closest(".moduleRow").trigger("click");

		$(".envios input:checked").closest(".moduleRow").trigger("click");
		// Fin, select con las tiendas en checkout

		// Comparar productos
		$("#bton-cmpr").click(function () {
			var sValue = "";
			$(".compr input:checked").each(function (nIndex, nElement) {
				sValue += $(nElement).val() + "_";
			});

			$("#form-cmpr textarea").val(sValue);
			$("#form-cmpr").submit();
		});

		$(".compr").click(function () {
			if ($(this).hasClass("actv")) {
				$(this).removeClass("actv");
				$(this).parent().find("input").prop("checked", false);
			} else {
				$(this).addClass("actv");
				$(this).parent().find("input").prop("checked", true);
			}
		});

		$(".compr input:checked").parent().addClass("actv");
		// Fin, Comparar productos

		if (notificationsActive == true && acceptNotifications == true) {
			preguntarNotificationPermissions();
			notificationsFunctions();
		}

		// Ofertas Flash
		$(".hour").each(function (nIndex, dmElement) {
			$(dmElement).data('dia', $(dmElement).find(".d").text());
			$(dmElement).data('hora', $(dmElement).find(".h").text());
			$(dmElement).data('minuto', $(dmElement).find(".m").text());
			$(dmElement).data('segundo', $(dmElement).find(".s").text());

			// Cronometro
			var tmCronometro = setInterval(function () {
				// Variables
				var sCronometro = "";
				var sSegundo = "";
				var sMinuto = "";
				var sHora = "";
				var sDia = "";

				var nDia = $(dmElement).data('dia');
				var nHora = $(dmElement).data('hora');
				var nMinuto = $(dmElement).data('minuto');
				var nSegundo = $(dmElement).data('segundo');

				if (nSegundo <= 0 && nMinuto <= 0 && nHora <= 0 && nDia <= 0) {
					clearInterval($(dmElement).data('cronometro'));

					return false;
				}

				--nSegundo;

				if (nSegundo <= 0 && nMinuto > 0) {
					nSegundo = 59;
					--nMinuto
				}

				if (nMinuto == 0 && nHora > 0) {
					nMinuto = 59;
					--nHora
				}

				if (nHora == 0 && nMinuto == 0 && nSegundo == 0 && nDia > 0) {
					nHora = 23;
					nMinuto = 59;
					nSegundo = 59;
					--nDia;
				}

				sSegundo = nSegundo + "";
				sMinuto = nMinuto + "";
				sHora = nHora + "";
				sDia = nDia + "";

				$(dmElement).find(".d").html((nDia < 10 ? '0' : '') + sDia);
				$(dmElement).find(".h").html((nHora < 10 ? '0' : '') + sHora);
				$(dmElement).find(".m").html((nMinuto < 10 ? '0' : '') + sMinuto);
				$(dmElement).find(".s").html((nSegundo < 10 ? '0' : '') + sSegundo);

				$(dmElement).data('dia', nDia);
				$(dmElement).data('hora', nHora);
				$(dmElement).data('minuto', nMinuto);
				$(dmElement).data('segundo', nSegundo);
			}, 1000);

			$(dmElement).data('cronometro', tmCronometro);
		});

		// Ajax Bajo demanda
		$(".ajx-bjo").magnificPopup({
			type: 'ajax', fixedContentPos: true, midClick: true, removalDelay: 300, mainClass: 'ajx-bjo', callbacks: {
				ajaxContentAdded: function () {
					$("#ajax-bajo-continue").click(function () {
						$.magnificPopup.instance.close();
					});
				}
			}
		});

		// Menu
		new mmenuClass( $("#main-fake"), $("#menu-panel") )

		// Ir a cabecera
		$("#toTop").click(function(){ $("html, body").animate({ scrollTop: 0 }, "slow") } );

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

		// Login desplegable
		$("#menu-panel .head .wrpr .lgin:not(.mgp-inln), #head .icons .lgin:not(.mgp-inln), #lgin .close .far").click(function()
		{
			// Si idioma esta abierto
			if ($("#head .icons .lnge").hasClass("actv")) {
				$("#head .icons .lnge").trigger("click");
			}

			// Si el menu esta abierto cerramos
			if ($("html").hasClass("mm-panel-open")) {
				$("#main-fake").trigger("click");
			}

 			$(window).scrollTop(0);
			$("#head .icons .lgin").toggleClass("actv");
			$("body").toggleClass("login");
		});

		// Mensaje envio
		$("#sphg-mess .close").click(function()
		{
			$(this).parent().parent().toggleClass("actv");

			var currentDate = new Date();
			expirationDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate()+7, 0, 0, 0);
			var expires = "; expires=" + expirationDate.toGMTString();

			document.cookie = 'promotional_close=' + currentDate.getFullYear().toString() + '-' + (currentDate.getMonth()+1).toString() + '-' + (currentDate.getDate()+7).toString() + expires + '; path=/';
		});

		// Buscador cabecera
		$("#head .icons .srch").click(function(e)
		{
			e.stopPropagation();

			$("#head .srch-wrpr").addClass("actv").focus();
			$("#head .srch-wrpr").find("input[type=text]").focus();
		});

		$("#head .srch-wrpr").on('focusout', function () {
  			$("#head .srch-wrpr").removeClass("actv");
		});

		// Up web
		$("#up-web div").click(function(){$("html, body").animate({scrollTop:0}, 'slow')});;

		// Habilitar efectos
		setTimeout(function(){$("body").removeClass("preload");},100);

		// Crear slider
		self.createSlick();

		// Ampliar imagenes de productos (excluimos el slide 3D: no es una foto que ampliar)
		$("#slick-fich").lightGallery({selector: '.item:not(.slick-cloned):not(.item-3d)'});
	}

	// Crear slider
	this.createSlick = function()
	{
		// Sliders de productos
		$('.prdt-sldr-cntd:not(.prdt-cntd)').each(function()
		{
			var nLenght = $(this).find(".prdt").length;
			var column = (nLenght >= $(this).data("column") ? $(this).data("column") : nLenght);
			var options = {
				infinite: true,
				speed: 1000,
				autoplay: false,
				autoplaySpeed: 4000,
				slidesToShow: column,
				slidesToScroll: column,
				arrows: true,
				dots: false,
				prevArrow: '<i class="prev fas fa-angle-left mhide"></i>',
				nextArrow: '<i class="next fas fa-angle-right mhide"></i>',
				appendArrows: $(this).parent().find(".titu1 .xarrow")
			};

			if ($(this).closest(".prdt-bner").hasClass("none")){
				options.slidesToShow = (nLenght >= 4 ? 4 : nLenght);
				options.slidesToScroll = (nLenght >= 4 ? 4 : nLenght);

				options.responsive = [
				    {
				      breakpoint: 990,
				      settings: {
				        slidesToShow: (nLenght >= 3 ? 3 : nLenght),
				        slidesToScroll: (nLenght >= 3 ? 3 : nLenght)
				      }
				  	}
				];
			}

			$(this).not(".slick-initialized").slick(options);
		});

		// Banners destacados
		$('#home-slde').not(".slick-initialized").slick({
		    slidesToShow: 1,
		    slidesToScroll: 1,
		    dots: false,
		    infinite: true,
			autoplay: true,
			autoplaySpeed: 4000,
		    cssEase: 'linear',
			prevArrow: '<div class="fa fa-chevron-left"></div>',
			nextArrow: '<div class="fa fa-chevron-right"></div>',
		});

		// Bloque relacionados
		$('.fich-prdt .prdt-cntd').each(function()
		{
			var nLenght = $(this).find(".prdt").length;
			var options = {
				infinite: true,
				speed: 1000,
				autoplay: true,
				autoplaySpeed: 4000,
				dots: false,
				prevArrow: '<i class="prev fas fa-angle-left mhide"></i>',
				nextArrow: '<i class="next fas fa-angle-right mhide"></i>',
				appendArrows: $(this).parent().find(".titu1")
			};

			options.slidesToShow = (nLenght >= 2 ? ($(".prdt-sldr-cntd.prdt-cntd").length < 3 ? 2 : 1) : nLenght);
			options.slidesToScroll = (nLenght >= 2 ? ($(".prdt-sldr-cntd.prdt-cntd").length < 3 ? 2 : 1) : nLenght);

			options.responsive = [
				{
				  breakpoint: 990,
				  settings: {
					slidesToShow: (nLenght >= 2 ? 2 : nLenght),
					slidesToScroll: (nLenght >= 2 ? 2 : nLenght)
				  }
				},
				{
				  breakpoint: 700,
				  settings: {
					slidesToShow: (nLenght >= 1 ? 1 : nLenght),
					slidesToScroll: (nLenght >= 1 ? 1 : nLenght)
				  }
				}
			];

			if( $(".prdt-sldr-cntd.prdt-cntd").length == 3 && nLenght == 1 )
			{
				$(this).find('.prdt').addClass('rjst');
				$(this).find('.prdt').attr('style', 'display: block !important');
			}
			else
				$(this).not('.slick-initialized').slick(options);
		});


		// Marcas
		$('#marcas .content').not(".slick-initialized").slick({
			dots: false,
			prevArrow: false,
			nextArrow: false,
		    autoplay: false,
		    autoplaySpeed: 0,
		    cssEase: 'linear',
		    variableWidth: true,
			slidesToShow: 1,
			slidesToScroll: 1,
			rows: 1,
			swipeToSlide: true
		});

		// Ficha de producto
		// Capturamos el HTML original de la galeria (slides limpios, sin clones) antes de inicializar
		// Slick, para poder restaurarla al anteponer/quitar la imagen del atributo (feature imagen por atributo).
		if (typeof window.amFichGalleryOrig === 'undefined' && $('#slick-fich').length && !$('#slick-fich').hasClass('slick-initialized'))
			window.amFichGalleryOrig = $('#slick-fich').html();

		$('#slick-fich').not(".slick-initialized").slick({
		    slidesToShow: 1,
		    slidesToScroll: 1,
		    dots: true,
		    infinite: true,
		    cssEase: 'linear',
			prevArrow: false,
			nextArrow: false,
			customPaging : function(slider, i) {
			        var thumb = $(slider.$slides[i]).data('thumb');
			        return '<a><img src="'+thumb+'"></a>';
		    }
		});

		// En el slide del visor 3D desactivamos el arrastre de Slick para que el gesto
		// rote el modelo en vez de cambiar de slide; se navega fuera del 3D por los thumbnails (dots).
		$('#slick-fich').off('afterChange.mv3d').on('afterChange.mv3d', function(e, slick, current) {
			var on3d = $(slick.$slides.get(current)).hasClass('item-3d');
			slick.slickSetOption('swipe', !on3d, false);
			slick.slickSetOption('draggable', !on3d, false);
		});

	}

	// Funcion que se llama para el resizing
	this.resizing = function()
	{
		// Movil
		if (self.get("responsive").movil) {
			// Quitar slider por overflow scroll
			setTimeout(function(){
				$('.prdt-sldr-cntd.slick-initialized').slick("unslick");
			}, 150);

        	// Convertimos a acordeon
        	$("#home-tab").tab( "convertAccordion" );
		}
		else
		{
			// Convertimos a acordeon
        	$("#home-tab").accordion( "convertTabs" );

			// Crear slider
			setTimeout(function(){
				self.createSlick();
			}, 160);
		}

		// Inicio, tabla responsive
		$(".fble").each(function (nIndex, dmElement) {
			if ($(dmElement).css("display") == "block" && $(dmElement).data("responsive") == false) {
				$(dmElement).data("responsive", true);
				$(dmElement).addClass("resp");
			} else if ($(dmElement).css("display") == "table" && $(dmElement).data("responsive") == true) {
				$(dmElement).data("responsive", false);
				$(dmElement).removeClass("resp");
			}
		});
		// Fin, tabla responsive
	}

	// Funcion que se llama para el resizing
	this.resizingResponsive = function()
	{

	}

	// Funcion que se llama para el eventScroll
	this.eventScroll = function()
	{
		if (!bFixed){
			nHeightNav = dmNav.offset().top;
		}

		// Variables
        var nScroll = $(window).scrollTop();
		var bHaveFixed = nScroll > nHeightNav + ($("body").hasClass("fixed-head") ? 0 : 250);

		if( bHaveFixed && !bFixed )
		{
			dmHead.css( "marginTop", dmNav.height() );
			$("body").addClass("fixed-head");
			bFixed = true;
		}
		else if( !bHaveFixed && bFixed )
		{
			$("body").removeClass("fixed-head");
			dmHead.attr( "style", "" );
			bFixed = false;
		}
	}

	this.scroll_infinite = function()
	{
		/**
		 * @author daniel.lucia
		 * #GXZ-390-91846
		 * Modificación para que el scroll infinito funcione en la app
		 */
		scroll_pos = $(window).scrollTop();

		/**
		 * @author daniel.lucia
		 * #DJR-247-84890
		 * La paginación no funciona correctamente. Como el error es diferente dependiendo del responsive. Los divido.
		 */
		nAux = parseInt($("#responsive").css("min-width"));
		if (nAux == 1) {
			nFooterHeight = 475;
		} else {
			nFooterHeight = 475;
		}

		if( ((scroll_pos + $(window).height()) >= $("#fotr").offset().top - nFooterHeight) )
		{
			if (is_loading==0)
				self.loadFollowing();
		}

		head_height = $(document).height() - ($(".web-cntd").height() + $('#fotr').height());

		if (scroll_pos <= head_height+200)
		{
			if (is_loading==0)
				self.loadPrevious();
		}

		if (Math.abs(scroll_pos - last_scroll) > $(window).height()*0.1)
		{
			last_scroll = scroll_pos;

			$(".contentScroll").each( function(index)
			{
				if (self.mostlyVisible(this))
				{
					history.replaceState(null, null, $(this).attr("data-url"));
					$(".Nav").html($(this).attr("data-pagination"));
					return(false);
				}
			});
		}
	}

	this.primeCache = function()
	{
		$.getJSON(prev_data_url, function(data) { prev_data_cache=data; } );
		$.getJSON(next_data_url, function(data) { next_data_cache=data; } );
	}

	this.loadFollowing = function()
	{
		//console.log(next_data_url);
		if (next_data_url!="")
		{
			$( '<p class="loadingAjax" style="display: none;"></p>' ).appendTo( ".contentScroll:last" );
			$('.loadingAjax').slideDown(200);

			is_loading = 1;

			function showFollowing(data)
			{
				$('div.contentScroll:last').after(data.response);
				next_data_url = data.next_data_url;
				next_data_cache = false;

				$.getJSON(next_data_url, function(preview_data)
				{
					next_data_cache = preview_data;

					// Lazyload
					self.get("lazy").update();
				});
			}

			if (next_data_cache)
			{
				showFollowing(next_data_cache);
				is_loading = 0;
				$('.loadingAjax').slideUp(200).remove()

				// Lazyload
				self.get("lazy").update();
			}
			else
			{
				$.getJSON(next_data_url, function(data)
				{
					showFollowing(data);
					is_loading = 0;

					$('.loadingAjax').slideUp(200).remove()
				});
			}
		}
	};

	this.loadPrevious = function()
	{
		if (prev_data_url!="")
		{
			$( '<p class="loadingAjax" style="display: none;"></p>' ).prependTo( "#scrollingcontent" );
			$('.loadingAjax').slideDown(200);

			is_loading = 1; // note: this will break when the server doesn't respond

			function showPrevious(data)
			{
				$('div.contentScroll:first').before(data.response);
				item_height = $("div.contentScroll:first").height();
				window.scrollTo(0, $(window).scrollTop()+item_height); // adjust scroll

				prev_data_url = data.prev_data_url;
				prev_data_cache = false;

				$.getJSON(prev_data_url, function(preview_data)
				{
					prev_data_cache = preview_data;

					// Lazyload
					self.get("lazy").update();
				});

				if (hide_on_load)
				{
					$(hide_on_load).hide();
					hide_on_load = false;
				}
			}

			if (prev_data_cache)
			{
				showPrevious(prev_data_cache);
				is_loading = 0;
				$('.loadingAjax').slideUp(200).remove()

				// Lazyload
				self.get("lazy").update();
			}
			else
			{
				$.getJSON(prev_data_url, function(data)
				{
					showPrevious(data);
					is_loading = 0;
					$('.loadingAjax').slideUp(200).remove()

					// Lazyload
					self.get("lazy").update();
				});
			}
		}
	};

	this.mostlyVisible = function(element)
	{
		/**
		 * @author daniel.lucia
		 * #GXZ-390-91846
		 * Modificación para que el scroll infinito funcione en la app
		 */
		scroll_pos = $(window).scrollTop();

		/*if ($('#debug-app').length > 0) {
			$('#debug-app').text('Scroll: ' + scroll_pos)
		}*/

		var window_height = $(window).height();
		var el_top = $(element).offset().top;
		var el_height = $(element).height();
		var el_bottom = el_top + el_height;

		return ((el_bottom - el_height*0.05 > scroll_pos) && (el_top < (scroll_pos+0.5*window_height)));
	}

	// Iniciar aplicación
	this.init = function()
	{
		// Añadimos evento responsive
		self.get("ajax").addEventAjaxSucess( self.reloadEventAjax );

		// Añadimos evento responsive
		self.get("responsive").addEventResponsive( self.resizingResponsive );

		// Añadimos evento resizing
		app.get("kernel").addEventResize( self.resizing )

		// Iniciamos responsive
		self.get( "responsive" ).init()

		// Añadimos scroll
		self.get("kernel").addEventScroll( self.eventScroll );

		// Iniciamos kernel
		self.get("kernel").init();

		// Llamamos al evento resize
		$(window).trigger( "resize.app" );

		// Llamamos event ready
		self.eventReady();
	}
}

// Instanciamos
var app = new appClass();

// Añadimos objetos necesarios
app.sets({
	"ajax": new ajaxClass(),
	"responsive": new responsiveClass(),
	"kernel": new kernelClass()
});

// Iniciamos proyecto
app.init();


// =========================================================================
// Modal de confirmación de stock (qty > stock con check_stock = 0)
// =========================================================================
(function ($) {
	var sStyleId = 'fb-stock-modal-style';
	var sCss = ''
		+ '.fb-stock-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(0,140,198,0.16);z-index:99999;}'
		+ '.fb-stock-popup{width:590px;max-width:100%;background:#fff;border-radius:14px;box-shadow:0 14px 38px rgba(0,140,198,0.20);border:1px solid #b9dceb;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#222;font-size:15px;line-height:1.58;}'
		+ '.fb-stock-popup .fb-h{display:flex;align-items:center;gap:14px;padding:22px 28px 18px;background:linear-gradient(180deg,#fff 0%,#f7fbfd 100%);border-bottom:4px solid #008cc6;}'
		+ '.fb-stock-popup .fb-icon{width:44px;height:44px;border-radius:50%;background:#008cc6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;flex:0 0 auto;box-shadow:inset 0 0 0 3px #fff,0 0 0 2px #008cc6;}'
		+ '.fb-stock-popup .fb-title{margin:0;font-size:22px;line-height:1.2;color:#006f9d;font-weight:700;}'
		+ '.fb-stock-popup .fb-sub{margin:5px 0 0;font-size:14px;color:#666;}'
		+ '.fb-stock-popup .fb-body{padding:22px 28px 12px;}'
		+ '.fb-stock-popup .fb-box{margin:0 0 18px;padding:14px 16px;background:#eef7fb;border:1px solid #b9dceb;border-radius:10px;color:#123747;}'
		+ '.fb-stock-popup .fb-box strong, .fb-stock-popup .fb-notice strong{color:#006f9d;}'
		+ '.fb-stock-popup .fb-body p{margin:0 0 13px;}'
		+ '.fb-stock-popup .fb-notice{margin-top:16px;padding:15px 16px;background:#fafafa;border:1px solid #e5e5e5;border-left:5px solid #008cc6;border-radius:10px;color:#333;}'
		+ '.fb-stock-popup .fb-foot{display:flex;justify-content:flex-end;gap:12px;padding:20px 28px 26px;background:#fff;}'
		+ '.fb-stock-popup .fb-btn{appearance:none;border:0;border-radius:6px;padding:12px 26px;font-size:15px;font-weight:700;cursor:pointer;transition:transform .15s,background .15s;}'
		+ '.fb-stock-popup .fb-btn:hover{transform:translateY(-1px);}'
		+ '.fb-stock-popup .fb-btn-cancel{background:#eee;color:#333;border:1px solid #d8d8d8;}'
		+ '.fb-stock-popup .fb-btn-cancel:hover{background:#e4e4e4;}'
		+ '.fb-stock-popup .fb-btn-ok{background:#008cc6;color:#fff;box-shadow:0 2px 0 #006f9d;}'
		+ '.fb-stock-popup .fb-btn-ok:hover{background:#006f9d;}'
		+ '.fb-shipping-popup{width:610px;}'
		+ '.fb-shipping-popup .fb-deliv-list{margin:0;padding:0;list-style:none;}'
		+ '.fb-shipping-popup .fb-deliv-item{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid #eee;}'
		+ '.fb-shipping-popup .fb-deliv-item:last-child{border-bottom:0;}'
		+ '.fb-shipping-popup .fb-deliv-item strong{display:block;margin-bottom:3px;color:#006f9d;}'
		+ '.fb-shipping-popup .fb-deliv-item span{color:#333;}'
		+ '.fb-shipping-popup .fb-bullet{width:10px;height:10px;margin-top:7px;border-radius:50%;background:#008cc6;flex:0 0 auto;}'
		+ '@media(max-width:520px){.fb-stock-popup .fb-h,.fb-stock-popup .fb-body,.fb-stock-popup .fb-foot{padding-left:20px;padding-right:20px;}.fb-stock-popup .fb-h{align-items:flex-start;}.fb-stock-popup .fb-title{font-size:20px;}.fb-stock-popup .fb-foot{flex-direction:column-reverse;}.fb-stock-popup .fb-btn{width:100%;}}';

	function injectStyle() {
		if (document.getElementById(sStyleId)) return;
		var s = document.createElement('style');
		s.id = sStyleId;
		s.appendChild(document.createTextNode(sCss));
		document.head.appendChild(s);
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	// Modal de información de plazos de entrega (sustituye al popup mgp-ajax
	// que cargaba shipping_estimate_more_info.php).
	window.showShippingInfo = function () {
		injectStyle();

		var sHtml = ''
			+ '<div class="fb-stock-overlay" role="dialog" aria-modal="true">'
			+   '<div class="fb-stock-popup fb-shipping-popup">'
			+     '<div class="fb-h">'
			+       '<div class="fb-icon">i</div>'
			+       '<div>'
			+         '<h2 class="fb-title">Información sobre plazos de entrega</h2>'
			+         '<p class="fb-sub">Los plazos indicados pueden variar según el destino del envío.</p>'
			+       '</div>'
			+     '</div>'
			+     '<div class="fb-body">'
			+       '<div class="fb-box">Las fechas de entrega mostradas son <strong>plazos estimados</strong> para envíos dentro de la península.</div>'
			+       '<ul class="fb-deliv-list">'
			+         '<li class="fb-deliv-item">'
			+           '<div class="fb-bullet"></div>'
			+           '<div>'
			+             '<strong>Entregas en Baleares</strong>'
			+             '<span>Al plazo indicado habrá que añadirle <strong>1 día laborable adicional</strong>.</span>'
			+           '</div>'
			+         '</li>'
			+         '<li class="fb-deliv-item">'
			+           '<div class="fb-bullet"></div>'
			+           '<div>'
			+             '<strong>Entregas en Canarias, Ceuta, Melilla y destinos internacionales</strong>'
			+             '<span>Al plazo indicado habrá que añadirle entre <strong>5 y 7 días laborables adicionales</strong>.</span>'
			+           '</div>'
			+         '</li>'
			+       '</ul>'
			+       '<div class="fb-notice">Estos plazos son orientativos y pueden verse afectados por trámites logísticos, aduaneros o circunstancias ajenas a Francobordo.</div>'
			+     '</div>'
			+     '<div class="fb-foot"><button type="button" class="fb-btn fb-btn-ok">Aceptar</button></div>'
			+   '</div>'
			+ '</div>';

		var $modal = $(sHtml).appendTo('body');
		function close() { $modal.remove(); }
		$modal.on('click', '.fb-btn-ok', close);
		$modal.on('click', function (e) { if (e.target === this) close(); });
	};

	// Interceptamos clicks sobre el enlace "(+ info.)" en captura para que el
	// handler de magnific-popup nunca llegue a dispararse (la url sigue siendo
	// válida como fallback sin JS).
	document.addEventListener('click', function (e) {
		var t = e.target;
		while (t && t !== document) {
			if (t.tagName === 'A' && t.getAttribute && t.getAttribute('href') === 'shipping_estimate_more_info.php') {
				e.preventDefault();
				e.stopPropagation();
				if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
				window.showShippingInfo();
				return;
			}
			t = t.parentNode;
		}
	}, true);

	// opts: { stock: int, qty: int, productName: string, mode: 'confirm'|'info', onAccept: fn, onCancel: fn }
	window.showStockConfirm = function (opts) {
		injectStyle();
		opts = opts || {};
		var nStock = parseInt(opts.stock, 10) || 0;
		var nQty = parseInt(opts.qty, 10) || 0;
		var sName = opts.productName ? ' de "' + escapeHtml(opts.productName) + '"' : '';
		var bConfirm = (opts.mode !== 'info');

		var sBtns = bConfirm
			? '<button type="button" class="fb-btn fb-btn-cancel">Cancelar</button>'
			  + '<button type="button" class="fb-btn fb-btn-ok">Aceptar</button>'
			: '<button type="button" class="fb-btn fb-btn-ok">Aceptar</button>';

		var sHtml = ''
			+ '<div class="fb-stock-overlay" role="dialog" aria-modal="true">'
			+   '<div class="fb-stock-popup">'
			+     '<div class="fb-h">'
			+       '<div class="fb-icon">!</div>'
			+       '<div>'
			+         '<h2 class="fb-title">Disponibilidad limitada</h2>'
			+         '<p class="fb-sub">El pedido puede tener un plazo de entrega superior al habitual.</p>'
			+       '</div>'
			+     '</div>'
			+     '<div class="fb-body">'
			+       '<div class="fb-box">Solo tenemos <strong>' + nStock + ' unidad/es en stock</strong>' + sName + '.</div>'
			+       '<p>Puedes comprar <strong>' + nQty + ' unidad/es</strong>, pero las unidades restantes están sujetas a disponibilidad del fabricante.</p>'
			+       '<p>El pedido se enviará completo en un único envío, por lo que la entrega de todo el pedido se retrasará hasta que estén disponibles todas las unidades.</p>'
			+       '<div class="fb-notice">No podremos confirmar el plazo definitivo hasta consultar con el fabricante. En algunos casos, el plazo de entrega podría superar los <strong>30 días</strong>.</div>'
			+     '</div>'
			+     '<div class="fb-foot">' + sBtns + '</div>'
			+   '</div>'
			+ '</div>';

		var $modal = $(sHtml).appendTo('body');

		function close() { $modal.remove(); }

		$modal.on('click', '.fb-btn-cancel', function () {
			close();
			if (typeof opts.onCancel === 'function') opts.onCancel();
		});
		$modal.on('click', '.fb-btn-ok', function () {
			close();
			if (typeof opts.onAccept === 'function') opts.onAccept();
		});
		// Click fuera del popup = cancelar (sólo en modo confirm)
		$modal.on('click', function (e) {
			if (e.target === this) {
				close();
				if (bConfirm && typeof opts.onCancel === 'function') opts.onCancel();
				else if (!bConfirm && typeof opts.onAccept === 'function') opts.onAccept();
			}
		});
	};
})(jQuery);
