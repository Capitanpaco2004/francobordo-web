// Enviar formulario checkout con boton fake que se encuentra en la columna derecha
$("#checkout").on("click", ".checkout_form_target", function() {
	if(!$(this).hasClass("actv")){
		$(this).addClass("actv");
		$("#checkout_form").submit();
	}
});

// Seleccionar metodo de envio/pago
$("body").on("click", ".chkc-mthh", function() {
	$(this).parent().find(".actv").removeClass("actv");
	$(this).addClass("actv");
	$(this).find("input").prop("checked", true);

	// Si deseamos enviar el formulario automaticamente tras seleccionar un metodo
	if ($(this).data("submit")){
		$(this).parent().submit();
	}

	if ($('.chkc-mthh .infr-wrp .infr .selct select').length) {
		$('.chkc-mthh .infr-wrp .infr .selct select').change()
	}

});

// Sticky de la columna
$("#chkc-left-wrpr").stick_in_parent({"offset_top": 150});

// El seleccionar dirección tiene un boton de editar, haremos que no relize ninguna accion más solo el href
$("#chkc-shpg-slct").on("click", ".edit", function(e) {
	e.stopImmediatePropagation();
});

// Añadir cupon descuento
$("#checkout").on('click', '.chkc-add-cupn, .chkc-dlte-cupn', function() {
	// Refrescamos el checkout
	app.get("ajax").send({
		"url": FILENAME_SHOPPING_CART + "coupon/",
		"data": {"coupon": $(this).parent().find("input[name=coupon]").val()},
		"success": function(sHtml)
		{
			// Pintamos
			$("#chkc-trgt").html(sHtml);
		}
	});
});

// Refrescar carrito
var checkoutCartRefresh = function()
{
	// Refrescamos el checkout
	app.get("ajax").send({
		"url": FILENAME_SHOPPING_CART,
		"success": function(sHtml)
		{
			// Pintamos
			$("#chkc-trgt").html(sHtml);

			// Refrescamos el carrito flotante una vez camabiada la cantidad
			app.get("cart").refreshCart();
		}
	});
}

// Cambiar cantidad de la cesta
var checkoutCartChangeQuantity = function (dmInput){
	app.get("ajax").send({
		"url": FILENAME_SHOPPING_CART + "change_quantity/",
		"data": {"quantity": $(dmInput).val(), "products_id": $(dmInput).parent().data("pid")},
		"success": checkoutCartRefresh
	});
}

$("#checkout").on('change', '.chkc-mthh .infr-wrp .infr .selct select', function() {
	var dmThis = $(this);

	let price = $(this).find(':selected').data('price')

	app.get("ajax").send({
		"url": "/checkout/shipping/get-address-text",
		"data": {
			"store_id": dmThis.val(),
			"shipping": $('input[name=shipping]:checked').val()
		},
		"success": function(text) {
			$('#address-shipping-text').html(text)
			dmThis.parents('.chkc-mthh').find('.prco').text(price)
		}
	});

});

$("#checkout").on('change', '#customer_shopping_points_spending', function() {
	if($(this).is(':checked')) {
		$('.paymentRedeem label').addClass('checked')
	} else {
		$('.paymentRedeem label').removeClass('checked')
	}

	$(this).val($('.paymentRedeem label').data('customer_shopping_points_spending'))
});


if($('#choose_insurance').is(':checked')) {
	$('.box-insurance label').addClass('checked')
}

$("#checkout").on('change', '#choose_insurance', function() {
	if($(this).is(':checked')) {
		$('.box-insurance label').addClass('checked')
	} else {
		$('.box-insurance label').removeClass('checked')
	}
});

// Eliminar productos de la cesta
$("#checkout").on('click', '.chkc-dlet-prdt', function() {
	var dmThis = $(this);

	app.get("ajax").send({
		"url": "information.php?call=cart:remove&args=" + $(this).data("id"),
		"success": function(){
			// Si estamos en modificar productos
			if ($("#cart_modified").length > 0) {
				dmThis.parent().parent().remove();
				app.get("cart").refreshCart();
			}
			else {
				checkoutCartRefresh();
			}
		}
	});
});

// Añadir/Eliminar producto a favorito de la cesta
$("#checkout").on('click', '.chkc-add-whlt', function() {
	// Variables
	var sId = new String($(this).data("id"));
	var aData = [];

	// Si contenemos atributos
	if (sId.match(/\{/)){
		$.each(sId.split("{"), function(key,value){
			if (value.match(/\}/)){
				var aAux = value.split("}");
				aData.push({name: "id[" + aAux[0] +"]", value: aAux[1]});
			}
		});

		sId = sId.replace(/\{.+$/, "");
	}

	// Añadimos producto al wishlist
	app.get("wishlist").setProduct( sId, aData, $(this).hasClass("actv") ? "remove" : $(this).hasClass("actv") ? "remove" : "add" );

	// Cambiamos la clase
	$(this).toggleClass("actv");
});

// Productos modificados, modificar la cesta, seleccionar atributos
$("#cart_modified div[id*=prdt-]").each(function()
{
	// Variables
	var id = new String($(this).data("id")).replace(/\{/g, "\\{").replace(/\}/g, "\\}");

	$.fn.attributes( $("#prdt-" + id), {
		execute_all_actions: false,
		product_price: $("#prdt-" + id + " .prco[data-price]").data("price-format"),
		name_box_attribute: ".oeAttr",
		name_class_disabled: "zero",
		name_quantity: "#cart_quantity_" + id,
		element_array_stock: "array_option_stock_" + id,
		callInStock: function()
		{
			$("#prdt-" + id).removeClass("no-stock");
		},
		callOutStock: function()
		{
			$("#prdt-" + id).addClass("no-stock");
		},
		callAction: function(sCombinacion, sValue, sAction)
		{
			switch(sAction)
			{
				case "change_image":
					$("#prdt-" + id + " .imge img").attr("src", "images/atributos/" + sValue);
				break;
			}
		},
		changePrice:function(sPriceNew)
		{
			$("#prdt-" + id + " .prco[data-price]").text(sPriceNew);
		}
	});
});

// Cambiar cantidad de productos modificados
var checkoutCartModifiedChangeQuantity = function(dmInput) {
	$(dmInput).closest(".col").prev().find("select").trigger("change");
}

// Al pulsar el boton de confirmar cambios en el carrito
$("#cart_modified_confirm").click(function()
{
	var post = {};

	$("div[id*=prdt-]").each(function()
	{
		post[$(this).data("id")] = {
			quantity: $(this).find("input[name=cart_quantity]").val(),
			attributes: $(this).find(".xform select").serializeArray()
		}
	});

	// Enviamos formulario
	$("#cart_modified form input").val(JSON.stringify(post));
	$("#cart_modified form").submit();
});

const formatter = new Intl.NumberFormat(
	'de-DE',
	{
		style: 'currency',
		currency: 'EUR',
	}
);

$(document).ready(function() {

	$("#checkout").on("click", ".showDetail a", function() {
		/*$('.totales').toggleClass('show-detail')*/
		$('.showDetail').toggleClass('on')
		$('.checkout-detail').slideToggle(150)
	});


	//Verificamos si el pedido tiene IVA, para añadirlo en el precio
	if ($('#checkout .box-ttal > .col.ot_tax').length) {
		$('<span>IVA inc.</span>').appendTo('#checkout .box-ttal .ttal-n strong')
	}
})