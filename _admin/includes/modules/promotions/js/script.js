// Overflow
$("#cntd").css("overflow", "hidden");

// Cambiar estado banner
$(".btus").click(function () {
	var dmElmt = $(this);
	var sStatus = dmElmt.data("banner");

	sStatus = (sStatus == 1 ? 0 : 1);

	$.ajax({
		url: sUrlPage + "?a=banner_status&id=" + dmElmt.data("id") + "&status=" + sStatus
	}).done(function (sHtml) {
		if (sHtml != "") return false;

		dmElmt.data("banner", sStatus);

		if (sStatus == 1) {
			dmElmt.html('<img width="10" height="10" src="images/icon_status_green.gif"/> <img width="10" height="10" src="images/icon_status_red_light.gif"/>');
		} else {
			dmElmt.html('<img width="10" height="10" src="images/icon_status_green_light.gif"/> <img width="10" height="10" src="images/icon_status_red.gif"/>');
		}
	});
});

// Cambiar estado promoción
$(".stus").click(function () {
	var dmElmt = $(this);
	var sStatus = dmElmt.data("status");

	sStatus = (sStatus == 1 ? 0 : 1);

	$.ajax({
		url: sUrlPage + "?a=status&id=" + dmElmt.data("id") + "&status=" + sStatus
	}).done(function (sHtml) {
		if (sHtml != "") return false;

		dmElmt.data("status", sStatus);

		if (sStatus == 1) {
			dmElmt.html('<img width="10" height="10" src="images/icon_status_green.gif"/> <img width="10" height="10" src="images/icon_status_red_light.gif"/>');
		} else {
			dmElmt.html('<img width="10" height="10" src="images/icon_status_green_light.gif"/> <img width="10" height="10" src="images/icon_status_red.gif"/>');
		}
	});
});

// Autocomplete productos
$('.prd-srh').autocomplete({
	source: sUrlPage + "?a=search_products",
	minLength: 2,
	select: function (event, ui) {
		$('#products_id_' + $(this).attr('data-number')).val(ui.item.id);
	}
});

// Buscador productos
if ($('#products_search').length) droped_elements('products_search', 'get_products', 'prd');
if ($('#products_search_2').length) droped_elements('products_search_2', 'get_products', 'prd2');

// Drop admin
function droped_elements(obj, action, prefix) {
	$('#' + obj).keyup(function () {
		// Mostramos loadding
		$("#" + obj).parent().find(".img-load").css("display", "block");

		// Borramos la llamada a funcion search
		clearTimeout(tmSearch);

		// Si estamos usando ajax abortamos
		if (ajx != undefined) ajx.abort();

		// Lanzamos la funcion search cuando pase X ms
		tmSearch = setTimeout(function () {
			ajx = $.ajax({
				url: sUrlPage + "?a=" + action + "&text=" + $('#' + obj).val(),
				cache: false
			}).done(function (v) {
				// Ocultamos loadding
				$("#" + obj).parent().find(".img-load").css("display", "none");

				// Cargamos html
				$('#rows-drag-' + prefix).html(v);

				// Ajustamos altura
				if ($('#drop-rows-' + prefix).height() < $('#rows-drag-' + prefix).height()) {
					$('#drop-rows-' + prefix).height($('#rows-drag-' + prefix).height());
				}

				// Lista drag
				jQuery("#rows-drag-" + prefix + " li").draggable({
					scroll: true,
					cursorAt: { top: 8, left: 8 },
					cursor: "move",
					cancel: '.drag-sltc',
					revert: function (bDrop) {
						if (bDrop) {
							return false;
						} else {
							jQuery(this).removeClass("drag-sltc");
							return true;
						}
					},
					start: function (e) {
						var dmElement = jQuery(e.currentTarget);
						dmElement.addClass("drag-sltc");
					},
					helper: function () {
						return $("<div style='width:" + $(this).width() + "px; z-index: 100;'></div>").append($(this).clone());
					}
				});

				jQuery("#drop-rows-" + prefix + " li").each(function (index, elmt) {
					if (jQuery("#rows-drag-" + prefix + " #" + jQuery(elmt).data("id")).length > 0) {
						jQuery("#rows-drag-" + prefix + " #" + jQuery(elmt).data("id")).addClass("drag-sltc");
					}
				});
			});
		}, 190);
	});

	// Zona drop
	$("#drop-rows-" + prefix).droppable({
		cursorAt: { top: 8, left: 8 },
		greedy: true,
		drop: function (e, ui) {
			var dmElement = $(ui.draggable);

			if (!dmElement.data("drop")) {
				var el = dmElement.text()
					.replace("::", "")
					.replace(/(Producto)$/g, '<b style="position: absolute; right: 30px; color: #4fc22b;">Producto</b>')
					.replace(/(Categoría)$/g, '<b style="position: absolute; right: 30px; color: #881111;">Categoría</b>')
					.replace(/(Marca)$/g, '<b style="position: absolute; right: 30px; color: #2b78c2;">Marca</b>')
					.replace(/(Tipo de producto)$/g, '<b style="position: absolute; right: 30px; color: #000;">Tipo de producto</b>');

				var tp = "p";
				if (el.match(/(\#4fc22b)/i)) tp = "p";
				else if (el.match(/(\#881111)/i)) tp = "c";
				else if (el.match(/(\#2b78c2)/i)) tp = "m";
				else if (el.match(/(\#000)/i)) tp = "t";

				var dmDiv = $("<li/>", {
					"data-id": $(dmElement).attr("id"),
					"data-drop": "true",
					html: el
						+ '<input type="hidden" name="row-' + prefix + '[]" value="' + $(dmElement).attr("id") + '"/>'
						+ '<input type="hidden" name="row-' + prefix + '-name[]" value="' + dmElement.text() + '"/>'
						+ '<input type="hidden" name="row-' + prefix + '-type[]" value="' + tp + '"/>'
				});


				dmDiv.appendTo(this);
				$(this).removeClass("drop-hovr");
			}

			if ($(this).find("li").length >= 1) {
				$(this).removeClass("drop-empty");
			}
		},
		over: function () { $(this).addClass("drop-hovr"); },
		out: function () { $(this).removeClass("drop-hovr"); }
	}).sortable({
		cursor: "move",
		over: function () { sortableIn = 1; },
		out: function () { sortableIn = 0; },
		receive: function () { sortableIn = 1; },
		beforeStop: function (e, ui) {
			if (sortableIn == 0) {
				var dmElement = ui.item;
				if ($("#rows-drag-" + prefix + " #" + dmElement.data("id")).length > 0) {
					$("#rows-drag-" + prefix + " #" + dmElement.data("id")).removeClass("drag-sltc");
				}
				ui.item.remove();
			}
		},
		stop: function () {
			if ($(this).find("li").length == 0) {
				$(this).addClass("drop-empty");
			}
		}
	});
}

// Botón para añadir todos (promoción)
$("#btn-prd").click(function (e) {
	e.preventDefault();
	add_all_products("rows-drag-prd", "drop-rows-prd");
});

// Botón para añadir todos (descuento)
$("#btn-prd2").click(function (e) {
	e.preventDefault();
	add_all_products("rows-drag-prd2", "drop-rows-prd2");
});

// Botón para quitar todos (promoción)
$("#btn-prd-clear").click(function (e) {
	e.preventDefault();
	$("#drop-rows-prd").empty().addClass("drop-empty");
});

// Botón para quitar todos (descuento)
$("#btn-prd2-clear").click(function (e) {
	e.preventDefault();
	$("#drop-rows-prd2").empty().addClass("drop-empty");
});


function add_all_products(sOrigin, sDestiny) {
	var s = "", r = "", ta = "", tr = "";

	$("#" + sOrigin).find("li").each(function () {
		if (!$(this).hasClass("drag-sltc")) {
			var el = $(this).html();
			var tp = "p";
			if (el.match(/#881111/)) tp = "c";
			else if (el.match(/#2b78c2/)) tp = "m";

			s += $(this).attr("id") + ";";
			ta += tp + ";";
			$(this).addClass("drag-sltc");
		}
	});
	if (s !== "") s = s.slice(0, -1);
	if (ta !== "") ta = ta.slice(0, -1);

	$("#" + sDestiny).find("li").each(function () {
		var el = $(this).html();
		var tp = "p";
		if (el.match(/#881111/)) tp = "c";
		else if (el.match(/#2b78c2/)) tp = "m";

		r += $(this).attr("data-id") + ";";
		tr += tp + ";";
	});
	if (r !== "") r = r.slice(0, -1);
	if (tr !== "") tr = tr.slice(0, -1);

	$.ajax({
		method: "POST",
		url: sUrlPage + "?a=add_all_products",
		data: {
			add: s,
			rows: r,
			types_add: ta,
			types_rows: tr,
			box: (sDestiny.indexOf("prd2") !== -1 ? "2" : "1"),
		}
	}).done(function (html) {
		$("#" + sDestiny).html(html).removeClass("drop-empty");
	});
}

document.addEventListener("DOMContentLoaded", function () {
	const input = document.querySelector('input[name="promotion_discount_percent"]');
	const select = document.getElementById('discount_type');

	function updateAttrs() {
		if (select.value === 'percent') {
			input.setAttribute('min', 0);
			input.setAttribute('max', 100);
			input.setAttribute('step', 1);
		} else {
			input.removeAttribute('max');
			input.setAttribute('min', 0);
			input.setAttribute('step', 5);
		}
	}

	select.addEventListener('change', updateAttrs);
	updateAttrs(); // inicializar
});
