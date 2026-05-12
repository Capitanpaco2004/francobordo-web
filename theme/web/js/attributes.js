(function ($) {
	// Funcion que realiza las combinaciones de un array
	var multiplecombinations = function (arrays, i) {
		if (!(i in arrays))
			return [];

		if (i == arrays.length - 1)
			return arrays[i];

		var tmp = multiplecombinations(arrays, i + 1);

		var result = [];

		$(arrays[i]).each(function (a, v) {
			$(tmp).each(function (a, t) {
				result.push($.isArray(t) ? $.merge([v], t) : [v, t]);
			});
		});

		return result;
	}

	// Funcion que recrea el codigo para realizar combinaciones entre arrays
	var combinationsAttributes = function (aAtributes, nCombination) {
		// Variables
		var sCode = '';
		var nTotal = aAtributes.length
		var aReturn = [];

		// Restamos combinacion ya que si nos piden 3 sera 2 para empezar desde el 0
		nCombination--;

		// Recorremos para crear el codigo
		for (var nCont = 0; nCont <= nCombination; nCont++) {
			sCode += 'for( var nCont' + nCont + ' = ' + (nCont == 0 ? '0' : 'nCont' + (nCont - 1) + ' + 1') + '; nCont' + nCont + ' < ' + nTotal + '; nCont' + nCont + '++ )';

			if (nCont == nCombination) {
				sCode += '{';
				var sAux = '';
				var sOpciones = '';

				for (var nCont1 = 0; nCont1 <= nCombination; nCont1++) {
					sAux += '[aAtributes[nCont' + nCont1 + ']],';
					sOpciones += 'nCont' + nCont1 + '+\',\'+';
				}

				sAux = sAux.replace(/\,$/, "");

				if (sAux.match(/\,/i))
					sCode += 'aAux = [].concat(' + sAux + ');';
				else
					sCode += 'aAux = ' + sAux + ';';

				sCode += 'aAux = multiplecombinations(aAux, 0);';

				sCode += 'aReturn.push(aAux);';
				sCode += '}';
			}
		}

		// Procesamos código
		eval(sCode);

		// Retornamos
		return aReturn;
	}

	$.fn.attributes = function (dmContent, options) {
		"use strict";

		// Si no nos envian contenedor principal o no existe no hacemos nada
		if (!dmContent || dmContent.length <= 0)
			return false;

		// Options
		var options = options || {};
		var element_array_stock = options.element_array_stock || "array_option_stock";
		var element_array_action = options.element_array_action || "array_option_action";
		var fnChangePrice = options.changePrice || false;
		var fnChangePriceSpecial = options.changePriceSpecial || false;
		var fnSelectedOptionCuston = options.callSelectOption || false;
		var execute_all_actions = typeof options.execute_all_actions !== 'undefined' ? options.execute_all_actions : true;
		var product_price = typeof options.product_price !== 'undefined' ? options.product_price : 0;
		var product_price_special = typeof options.product_price_special !== 'undefined' ? options.product_price_special : 0;
		var sClassNameBoxAttribute = options.name_box_attribute || false;
		var sClassNameDisabled = options.name_class_disabled || false;
		var sClassNameQuantity = options.name_quantity || false;

		// Variables
		var dmArrayStock = dmContent.find("#" + element_array_stock);
		var dmArrayAction = dmContent.find("#" + element_array_action);
		var aArrayStock = {};
		var aArrayAction = {};
		var dmOptions = dmContent.find("[name*=id\\[]");

		// Si no existe los array detenemos
		if (dmArrayAction.length <= 0 || dmArrayStock.length <= 0)
			return false;


		// Convertimos los json
		aArrayStock = $.parseJSON(dmArrayStock.text());
		aArrayAction = $.parseJSON(dmArrayAction.text());

		// Damos eventos a los objetos del formulario que tengan el name="id[" es decir atributos
		dmOptions.each(function () {
			// Si es radio o select evento change
			if ($(this).is("input:radio") || $(this).is("select"))
				$(this).change(function () {
					fnSelectedOptionLayout($(this).parent())
				});
		});

		// Cuando tiene focus el quantity comprobamos si existe stock con dicha cantidad
		$(sClassNameQuantity).focus(function () {
			var dmFirst = $($(sClassNameBoxAttribute + ":first"));
			fnSelectedOptionLayout(dmFirst);
		});

		// Evento que se dispara cuando seleccionamos una opcion
		var fnSelectedOption = function (dmThis) {

			// Variables
			var aCombinacion = fnGetCombinationNow();
			var sCombinacionTotal = aCombinacion.combinacion_total;
			var sCombinacionStock = aCombinacion.combinacion_stock;
			var sPrecio = parseFloat(product_price) + aCombinacion.precio;
			var sPrecioSpecial = parseFloat(product_price_special) + aCombinacion.precio;
			var bStock = true;
			let classProduct = '';
			let quantityProduct = 0;

			// Si tenemos seleccionado opciones con control de stock
			if (sCombinacionStock != "") {
				// Si existe esa combinacion en el array de stock y tiene stock
				if (sCombinacionStock in aArrayStock && aArrayStock[sCombinacionStock] >= $(sClassNameQuantity).val())
					bStock = true;
				else
					bStock = false;
			}

			// Comprobar acciones //
			// Convertimos la combinacion a array
			var aSlip = sCombinacionTotal.split(",");

			// Total de opciones
			var nTotal = aSlip.length;

			// Variable auxiliar para añadir las opciones
			var aAux = [];

			// Añadimos las opciones
			$(aSlip).each(function (a, b) {
				aAux.push([b]);
			});

			// Donde guardaremos la ultima combinacion de la acción
			var sLastCombination = "";

			// Recorremos
			for (var nCont = 1; nCont <= nTotal; nCont++) {
				var aReturn = combinationsAttributes(aAux, nCont);

				$(aReturn).each(function (a, b) {
					$(b).each(function (a, c) {
						if ($.isArray(c))
							var sCombination = c.toString();
						else
							var sCombination = c;

						// Si existe la combinacion en las acciones
						if (sCombination in aArrayAction) {
							// Si deseamos ejecutar todas las acciones
							if (execute_all_actions)
								fnAction(sCombination, aArrayAction[sCombination].value, aArrayAction[sCombination].action);
							else
								sLastCombination = sCombination;
						}

						// Llamada a whilist siempre
						fnAction(sCombination, "", "wishlist");
					});
				});
			}

			// Si solo deseamos ejecutar la ultima acción
			if (sLastCombination != "" && !execute_all_actions)
				fnAction(sLastCombination, aArrayAction[sLastCombination].value, aArrayAction[sLastCombination].action);

			// Cambiamos precio
			if (fnChangePrice != false)
				fnChangePrice(new String(sPrecio.toFixed(2)) + "€", dmThis);

			// Cambiamos precio oferta
			if (fnChangePriceSpecial != false)
				fnChangePriceSpecial(new String(sPrecioSpecial.toFixed(2).replace(".", ",")) + "€");

			// Ponemos con stock o sin stock las opciones
			fnOutStockInStockOptions(dmThis);

			// Solo ejecutamos callSelectOption si el atributo tiene valor válido
			if (
				fnSelectedOptionCuston &&
				typeof dmThis.val() !== 'undefined' &&
				dmThis.val() !== '' &&
				parseInt(dmThis.val()) > 0
			) {
				options.callSelectOption(dmThis);
			}

		}

		// Funcion que se encarga de poner con stock o sin stock los atributos segun su seleccion
		var fnOutStockInStockOptions = function (dmThis) {
			// Obtenemos la capa padre actual del atributo pulsado
			var dmParentAttributeNow = dmThis.closest(sClassNameBoxAttribute);
			var dmAux = dmParentAttributeNow;
			var sCombi = "";

			// Obtenemos la combinacion actual por encima
			while (true) {
				// Si la capa no es una opcion
				if (!dmAux.is(sClassNameBoxAttribute))
					break;

				// Obtenemos el valor solo si es track-stock
				if (fnCheckTrackStockOptionLayout(dmAux))
					sCombi += fnGetValueOptionLayout(dmAux, true) + ",";

				// Capa anterior
				dmAux = dmAux.prev();
			}

			// Si tenemos combinacion
			if (sCombi != "") {
				// Eliminamos la ultima coma y convertimos array para darle la vuelva
				aAux = sCombi.replace(/\,$/, "", sCombi).split(",");
				aAux.reverse();
				sCombi = aAux.join(",");
			}

			// Obtenemos las opciones que existen por abajo
			var dmAux = dmParentAttributeNow.next();
			var aOptionsLayout = [];

			while (true) {
				// Si la capa no es una opcion
				if (!dmAux.is(sClassNameBoxAttribute))
					break;

				// Guardamos la opcion si es un track-stock
				if (fnCheckTrackStockOptionLayout(dmAux))
					aOptionsLayout.push(dmAux);

				// Capa anterior
				dmAux = dmAux.next();
			}

			// Varibles
			var sCombiNew = sCombi;

			// Recorremos las opciones
			$(aOptionsLayout).each(function () {
				// Variables
				var dmThis = $(this);

				// Deshabilitamos
				fnSetDisabledAttribute(dmThis);

				// Recorremos el array de stock
				$.each(aArrayStock, function (key, value) {
					// Si encuentra la combinacion en el array de stock
					if (key.match(new RegExp("^" + sCombiNew))) {

						// Solo habilitamos si hay stock positivo
						if (value > 0 && value >= $(sClassNameQuantity).val()) {
							// obtenemos el primer valor de la combinación
							let v = new String(
								key.replace(sCombiNew, "").replace(/^\,/, "").split(",")[0]
							).split("-")[1];

							// Input
							if (dmThis.find("input").length > 0)
								dmThis.find("input[value=" + v + "]").parent().removeClass(sClassNameDisabled);

							// Select
							if (dmThis.find("select").length > 0) {
								let dmElement = dmThis.find("select option[value=" + v + "]");
								dmElement.text(dmElement.text().replace(" (Sin stock)", ""));
							}
						}

						// Si value es 0 o negativo → no hacemos nada, para no pisar la clase de colores
					}
				});


				// Creamos combinacion
				sCombiNew += "," + fnGetValueOptionLayout(dmThis, true);
			});
		}

		// Funcion que pasandole como argumento la capa padre del atributo hace forzar su evento
		var fnSelectedOptionLayout = function (dmParentAttribute) {
			// Si es un checkbox o radio
			if ($(dmParentAttribute.find("input:checkbox")).length > 0 || $(dmParentAttribute.find("input:radio")).length > 0)
				fnSelectedOption($(dmParentAttribute.find("input:checked")[0]));

			// Si es un select
			if ($(dmParentAttribute.find("select")).length > 0)
				fnSelectedOption(dmParentAttribute.find("select option:selected"));
		}

		// Funcion que añade la clase disable a todos las opciones
		var fnSetDisabledAttribute = function (dmThis) {
			// Input, deshabilitamos
			if (dmThis.find("input").length > 0)
				dmThis.find("input").parent().addClass(sClassNameDisabled);

			// Select, deshabilitamos
			if (dmThis.find("select").length > 0) {
				dmThis.find("select option").filter(function () {
					var sText = $(this).text();

					//if( sText.match( / \(Sin stock\)/ ) == null )
					//	$(this).text( sText + " (Sin stock)" );
				});
			}
		}

		// Funcion que te devuelve el valor del atributo pasandole como argumento la capa padre del atributo
		var fnGetValueOptionLayout = function (dmParentAttribute, bValueOption) {
			// Variable
			var sOption;
			var sAttribute;

			// Select
			if (dmParentAttribute.find("select").length > 0) {
				sOption = dmParentAttribute.find("select").data("oid");
				sAttribute = dmParentAttribute.find("select option:selected").val();
			}

			// Input
			if ($(dmParentAttribute.find("input:checkbox")).length > 0 || $(dmParentAttribute.find("input:radio")).length > 0) {
				sOption = dmParentAttribute.find("input:checked").data("oid");
				sAttribute = dmParentAttribute.find("input:checked").val();
			}

			// Retornamos
			if (bValueOption)
				return sOption + "-" + sAttribute;
			else
				return sAttribute;
		}

		// Funcion que comprueba si la opcion tiene track-stock pasandole como argumento la capa padre del atributo
		var fnCheckTrackStockOptionLayout = function (dmParentAttribute) {
			// Variable
			var bReturn = false;

			// Select
			if (dmParentAttribute.find("select").length > 0) {
				if (dmParentAttribute.find("select").data("track") == "1")
					bReturn = true;
			}

			// Input
			if (dmParentAttribute.find("input")) {
				if ($(dmParentAttribute.find("input")[0]).data("track") == "1")
					bReturn = true;
			}

			// Retornamos
			return bReturn;
		}

		// Funcion que otiene la combinación seleccionada actual
		var fnGetCombinationNow = function () {
			// Variables
			var sCombinacionTotal = "";
			var sCombinacionStock = "";
			var bCombinacionTotal = false;
			var bCombinacionStock = false;
			var nSumTotal = 0;

			// Recorremos los elementos
			dmOptions.each(function () {
				bCombinacionTotal = false;
				bCombinacionStock = false;
				var nAux = 0;

				// Elemento que tenemos seleccionado al recorrer, ya que no es lo mismo un option de un select para referiora el que un checkbox
				var dmElemenNow;


				// Si es un checkbox o radio
				if (($(this).is("input:checkbox") || $(this).is("input:radio")) && $(this).is(':checked')) {
					// Combinacion total
					bCombinacionTotal = true

					// Combinacion stock
					if ($(this).data("track") == 1)
						bCombinacionStock = true;

					// Precio
					nAux = $(this).data("price");

					// Elemento
					dmElemenNow = $(this);
				}

				// Si es un select
				if ($(this).is("select")) {
					// Combinacion total
					bCombinacionTotal = true;

					// Combinacion stock
					if ($(this).data("track") == 1)
						bCombinacionStock = true;

					// Precio
					nAux = $(this).find("option:selected").data("price");

					// Elemento
					dmElemenNow = $(this).find("option:selected");
				}

				// Si tenemos una combinacion es un elemento seleccionado
				if (bCombinacionTotal) {
					// Guardamos combinacion total
					sCombinacionTotal += $(this).data("oid") + "-" + $(this).val() + ",";

					// Si tenemos precio
					if (typeof nAux !== 'undefined') {
						// Comprobamos prefijo
						if (dmElemenNow.data("price-prefix") == "+")
							nSumTotal += parseFloat(nAux);
						else
							nSumTotal -= parseFloat(nAux);
					}
				}

				// Guardamos combinacion stock
				if (bCombinacionStock)
					sCombinacionStock += $(this).data("oid") + "-" + $(this).val() + ",";
			});

			// Si hemos tenido combinaciones eliminamos la ultima coma
			sCombinacionTotal = sCombinacionTotal != "" ? sCombinacionTotal.replace(/,$/i, "") : "";
			sCombinacionStock = sCombinacionStock != "" ? sCombinacionStock.replace(/,$/i, "") : "";

			// Retornamos
			return {combinacion_total: sCombinacionTotal, combinacion_stock: sCombinacionStock, precio: nSumTotal};
		}

		// Funcion que se llama cuando existe una accion
		var fnAction = function (sCombinacion, sValue, sAction) {
			if ("callAction" in options)
				options.callAction(sCombinacion, sValue, sAction);
		}

		// Funcion que se llama cuando existe stock
		var fnInStock = function () {
			if ("callInStock" in options)
				options.callInStock();
		}

		// Funcion que se llama cuando no existe stock
		var fnOutStock = function () {
			if ("callOutStock" in options)
				options.callOutStock();
		}

		// Funcion recursiva que se llama para comprobar si la opción seleccionada no tiene stock para pasar a la siguiente opción
		var fnSelectedAtri = function (dmThis) {
			// Si la primera opcion tiene indicado "Sin stock", seleccionamos el siguiente
			if (dmThis.length > 0 && dmThis.text().match(/ \(Sin stock\)/)) {
				dmThis.parent().find("option:selected").prop("selected", false);

				var oNext = dmThis.next();
				oNext.prop("selected", true);
				oNext.trigger("click");

				fnSelectedOption(oNext);

				if (dmThis.text().match(/ \(Sin stock\)/))
					fnSelectedAtri(dmThis.next());
				else
					return true;
			} else
				return true;
		}

		// Obtenemos la primera opcion y realizamos evento en el primer valor que tenga asignado
		var dmFirst = $($(sClassNameBoxAttribute + ":first"));

// Si hemos encontrado la primera opción
		if (dmFirst.length > 0) {
			// Disabled
			if (fnCheckTrackStockOptionLayout(dmFirst))
				fnSetDisabledAttribute(dmFirst);

			// Si es un checkbox o radio
			if ($(dmFirst.find("input:checkbox")).length > 0 || $(dmFirst.find("input:radio")).length > 0) {
				// Seleccionamos la opcion
				fnSelectedOption($(dmFirst.find("input:checked")[0]));

				// Recorremos los input
				dmFirst.find("input").each(function () {
					var dmThis = $(this);
					var nOpcion = dmThis.data("oid");
					var nValue = dmThis.val();

					// Recorremos el array de stock
					$.each(aArrayStock, function (key, value) {
						// Si encuentra la combinacion en el array de stock
						if (key.match(new RegExp("^" + nOpcion + "-" + nValue)))
							dmThis.parent().removeClass(sClassNameDisabled);
					});
				});
			}

			// Si es un select
			if ($(dmFirst.find("select")).length > 0) {
				var dmSelected = dmFirst.find("select option:selected");

				// 🚨 Solo lanzamos si hay valor válido
				if (dmSelected.val() !== '' && parseInt(dmSelected.val()) > 0) {
					fnSelectedOption(dmSelected);
					fnSelectedOptionLayout(dmFirst);
				}

				// Recorremos los options
				dmFirst.find("option").each(function () {
					var dmThis = $(this);
					var nOpcion = dmThis.parent().data("oid");
					var nValue = dmThis.val();

					// Recorremos el array de stock
					$.each(aArrayStock, function (key, value) {
						// Si encuentra la combinacion en el array de stock
						if (key.match(new RegExp("^" + nOpcion + "-" + nValue)) && value != -900)
							dmThis.text(dmThis.text().replace(" (Sin stock)", ""));
					});
				});

				// Obtenemos todos los atributos
				var dmAtri = $($(sClassNameBoxAttribute));

				// Recorremos los atributos
				dmAtri.each(function () {
					var dmFirstOption = $(this).find("select option:selected");

					// Solo lanzamos si hay valor válido
					if (
						dmFirstOption.val() !== '' &&
						parseInt(dmFirstOption.val()) > 0 &&
						fnSelectedAtri(dmFirstOption) &&
						fnSelectedOptionCuston
					) {
						options.callSelectOption(dmFirstOption);
					}
				});
			}
		}

	}
})(window.jQuery);
