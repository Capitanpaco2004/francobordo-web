// Variables
var am_reloadEventActionForm;

// Function para mostrar el loadding
var am_showLoad = function()
{
	$("#dxload").css("display", "block");
	$("#dxbg").css("display", "block");
};

// Function para ocultar el loadding
var am_hideLoad = function()
{
	$("#dxload").css("display", "none");
	$("#dxbg").css("display", "none");
};

(function($)
{
	// Variables
	var nProductsId;
	var aProductsAttributes;
	var nPosicionAntigua = 0;

	// Funcion que recarga los eventos del formulario de nueva acción
	am_reloadEventActionForm = function(sCombinacion)
	{
		// Evento para eliminar accion
		$("#dialog .subNav .icos-trash").click(function(e)
		{
			// Detenemos evento
			e.stopPropagation();

			// Confirmamos
			if( confirm("¿Realmente deseas eliminar la acción?") )
			{
				// Cerramos dialog
				$("#dialog").dialog("close");

				// Mostramos loadding
				am_showLoad();

				// Enviamos el form por ajax
				$.ajax({
					type: "POST",
					url: "categories.php?action=attributeManager&method=attributeManagerActionDelete",
					data: {products_id: nProductsId, combi: sCombinacion, action: $(this).data("action")},
				}).done(function(sJson)
				{
					// Variables
					aJson = $.parseJSON(sJson);

					// Modificamos el dialog
					$("#dialog").html(aJson.html);

					// Cantidad total de acciones
					$("#atrb-mngr .subNav .activeli strong").text(aJson.cantidad_total);

					// Si la cantidad de combinaciones es mayor que 0 mostramos el boton en azul
					if( aJson.cantidad > 0 )
						$("#atrb-mngr .drch a[data-combi='" + sCombinacion + "']").removeClass("bDefault").addClass("bBlue");
					else
						$("#atrb-mngr .drch a[data-combi='" + sCombinacion + "']").removeClass("bBlue").addClass("bDefault");

					// Recargamos eventos
					am_reloadEventActionForm(sCombinacion);

					// Ocultamos loadding
					am_hideLoad();

					// Añadimos clase
					$("#dialog").addClass("form-actn");

					// Abrimos dialog
					$("#dialog").dialog("open");
				});
			}

			// Retornamos
			return false;
		});

		// Evento para pasar entre acciones
		$("#dialog .subNav li").click(function(e)
		{
			// Detenemos evento
			e.stopPropagation();

			// Desactivamos
			$("#dialog .subNav li").removeClass("activeli");
			$("#dialog .subNav li a").removeClass("this");

			// Activamos
			$(this).addClass("activeli");
			$(this).find("a").addClass("this");

			// Ocultamos formularios
			$("#dialog.form-actn .drch .cntd").css("display", "none");

			// Mostramos
			$("#dialog.form-actn .drch .cntd[data-id=" + $(this).data("id") + "]").css("display", "block");
		});
	}

	// Funcion para recargar los eventos de nueva acción
	var am_reloadEventAction = function()
	{
		// Combobox seleccionar combinaciones
		$("#sltc-cmbi").change(function()
		{
			// Mostramos loadding
			am_showLoad();

			// Realizamos peticion
			$.ajax({
				url: "categories.php?action=attributeManager&method=attributeManagerAction",
				type: "POST",
				data: {products_id: nProductsId, combinacion: $(this).val()},
				success: function(sHtml)
				{
					// Ocultamos loadding
					am_hideLoad();

					// Eliminamos lo que tengamos en el panel derecho
					$("#atrb-mngr .drch").html(sHtml);

					// Recargamos eventos
					am_reloadEventAction();
				}
			});
		});

		// Boton nueva acción, abrir dialog con el fomulario de las opciones
		$("#atrb-mngr .drch .new-actn").click(function(e)
		{
			// Detenemos evento
			e.stopPropagation();

			// Mostramos loadding
			am_showLoad();

			// Combinacion
			var sCombinacion = $(this).data("combi");

			// Realizamos peticion ajax para el formulario
			$.ajax({
				url: "categories.php?action=attributeManager&method=attributeManagerActionForm",
				data: {products_id: nProductsId, combi: sCombinacion},
				type: "POST",
				success: function(sHtml)
				{
					// Ocultamos loadding
					am_hideLoad();

					// Modificamos el dialog y mostramos
					$("#dialog").html(sHtml);

					// Recargamos eventos
					am_reloadEventActionForm(sCombinacion);

					// Titulo
					$("#dialog").dialog("option", "title", "Añadir nueva acción");

					// Añadimos clase
					$("#dialog").addClass("form-actn");

					// Mostramos la ventana
					$("#dialog").dialog("open");
				}
			});
		});
	};

	// Funcion para recargar los eventos de stock
	var am_reloadEventStock = function()
	{
		// Cambiar valores input //
		$("#atrb-mngr tr input").bind('blur keyup focus', function(e)
		{
			// Si es el evento focus
			if(e.type == 'focus')
			{
				$(this).data("value", $(this).val());

				return true;
			}

			// Si hemos dado a ENTER o hemos salido del input
			if(e.type == 'blur' || e.keyCode == '13')
			{
				// Variables
				var dmInput = $(this);

				// Si no se han cambiado los valores o no es un campo numerico no hacemos peticion
				if( dmInput.data("value") == dmInput.val() || !$.isNumeric(dmInput.val()) )
				{
					dmInput.val(dmInput.data("value"))
					return false;
				}

				// Mostramos loadding
				am_showLoad();

				// Actualizamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerChangeStock",
					type: "POST",
					data: {products_id: nProductsId, combinacion: $(this).data("combi"), key: dmInput.attr("name"), value: dmInput.val()},
					success: function(sReturn)
					{
						// Ocultamos loadding
						am_hideLoad();

						// Lo que ha sido devuelto modificamos el valor
						dmInput.val(sReturn);
						dmInput.data("value", sReturn);
						
						// Si es mayor a 0 cambiamos el color
						if( sReturn != 0 )
							dmInput.css("background", "#f9f9df");
						else
							dmInput.css("background", "#fff");
					}
				});
			}
		});
	};

	// Funcion para recargar los eventos de los valores
	var am_reloadEventValues = function(bReloadUniform)
	{
		// Uniform
		if( bReloadUniform == undefined )
			$("#atrb-mngr .drch .check :checkbox").uniform();

		// Opciones masiva, eliminar //
		$("#opciones_masivas .dltes").click(function()
		{
			// Variables
			var aCheckboxs = $("#atrb-mngr .drch").find("tbody td.check input:checked");

			// Comprobamos si tenemos activo algun checkbox
			if( aCheckboxs.length == 0 )
			{
				alert( "Para eliminar necesitas seleccionar algún registro" );
				return false;
			}

			// Si aceptamos eliminar
			if( confirm( '¿Realmente deseas eliminar estos valores?' ) )
			{
				// Variables
				var dmLiActive = $("#atrb-mngr .subNav .activeli");
				var nOid =  dmLiActive.find("a").data("oid");
				var nKey = dmLiActive.index();
				var nCantidadArray = 0;
				var aDelete = [];

				// Mostramos loadding
				am_showLoad();

				// Guardamos los ids que seran eliminados
				$(aCheckboxs).each(function()
				{
					// Valor
					nValue = $(this).val();

					// Añadimos al array
					aDelete.push(nValue);

					// Eliminamos del array
					aProductsAttributes[nKey] = jQuery.grep(aProductsAttributes[nKey], function(value){ return value != nValue; });
				});

				// Cantidad de valores en el array
				nCantidad = aProductsAttributes[nKey].length;

				// Quitamos undefined que ha podido quedar en el array
				if( nCantidad == 0 )
					aProductsAttributes.clean("");

				// Eliminamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerDelete",
					type: "POST",
					data: {products_id: nProductsId, option_id: nOid, ids_delete: aDelete, ids: JSON.stringify(aProductsAttributes)},
					success: function(sHtml)
					{
						// Restamos uno
						dmLiActive.find(".cant").text( parseInt(dmLiActive.find(".cant").text()) - aDelete.length )

						// Eliminamos lo que tengamos en el panel derecho
						$("#atrb-mngr .drch").html(sHtml);

						// Recargamos eventos de valores
						am_reloadEventValues();

						// Ocultamos loadding
						am_hideLoad();
					}
				});
			}

			// Retornamos
			return false;
		});

		// Combobox con búsquedas
		$("#inst-vlor").chosen({no_results_text: "No existen resultados"});

		// Seleccionar todos los combobox de una tabla para opciones masivas
		$(".all-checked").click(function(e)
		{
			// Si esta pulsado quitamos checkbox
			if( $(this).is(":checked") )
				$(this).closest("table").find("tbody td.check input:not(:checked)").click();
			else
				$(this).closest("table").find("tbody td.check input:checked").click();

			// Recargamos uniform
			$.uniform.update();
		});

		// Crear valor //
		$("#crte-vlor").click(function()
		{
			// Mostramos loadding
			am_showLoad();

			// Variables
			var dmLiActive = $("#atrb-mngr .subNav li.activeli");
			var nOid =  dmLiActive.find("a").data("oid");

			// Realizamos peticion ajax para el formulario
			$.ajax({
				url: "categories.php?action=attributeManager&method=attributeManagerProductsAttributes",
				data: {a: "value_add_form", ido: nOid},
				type: "POST",
				success: function(sHtml)
				{
					// Ocultamos loadding
					am_hideLoad();

					// Modificamos el dialog y mostramos
					$("#dialog").html(sHtml);

					// Formulario
					var dmForm = $("#dialog form");

					// Send form
					dmForm.submit(function(e)
					{
						// Detenemos evento
						e.stopPropagation();

						// Cerramos dialog
						$("#dialog").dialog("close");

						// Mostramos loadding
						am_showLoad();

						// Enviamos el form por ajax
						$.ajax({
							type: "POST",
							url: dmForm.attr("action"),
							data: dmForm.serialize()
						}).done(function(sJson)
						{
							// Variables
							aJson = $.parseJSON(sJson);
							var dmElement = $("<div/>", {html: aJson.mensaje});

							// Creamos una option para añadirlo al combobox
							var dmOption = $("<option/>").attr("value", aJson.id ).text( aJson.nombre );

							// Lo insertamos en el combobox
							$("#inst-vlor").append(dmOption);

							// Le damos valor
							$("#inst-vlor").val(aJson.id).trigger("change");
						});

						// Retornamos
						return false;
					});

					// Titulo
					$("#dialog").dialog("option", "title", "Nuevo valor");

					// Mostramos la ventana
					$("#dialog").dialog("open");
				}
			});
		});

		// Cambiar valores select //
		$("#atrb-mngr tr select").change(function()
		{
			am_showLoad();
			$.ajax({url: "categories.php?action=attributeManager&method=attributeManagerChangeData", type: "POST", data: {attribute_id: $(this).data("id"), key: $(this).attr("name"), value: $(this).val()}, success: function(){am_hideLoad();} });
		});

		// Cambiar valores input //
		$("#atrb-mngr tr input").bind('blur keyup focus', function(e)
		{
			// Si es el evento focus
			if(e.type == 'focus')
			{
				$(this).data("value", $(this).val());

				return true;
			}

			// Si hemos dado a ENTER o hemos salido del input
			if(e.type == 'blur' || e.keyCode == '13')
			{
				// Variables
				var dmInput = $(this);

				// Si no se han cambiado los valores no hacemos peticion
				if( dmInput.data("value") == dmInput.val() )
					return false;

				// Mostramos loadding
				am_showLoad();

				// Actualizamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerChangeData",
					type: "POST",
					data: {attribute_id: $(this).data("id"), key: dmInput.attr("name"), value: dmInput.val()},
					success: function(sReturn)
					{
						// FIX: Si cambiamos el precio debemos enviar tambien el prefijo a cambiarlo
						if( dmInput.attr("name") in ["options_values_price", "options_values_weight"] )
							dmInput.prev().trigger("change");

						// Ocultamos loadding
						am_hideLoad();

						// Lo que ha sido devuelto modificamos el valor
						dmInput.val(sReturn);
						dmInput.data("value", sReturn);
					}
				});
			}
		});

		// Insertar valor //
		// Cuando seleccionamos un valor a insertar
		$("#inst-vlor").unbind().change(function()
		{
			// Variables
			var nId = $(this).val();
			var dmLiActive = $("#atrb-mngr .subNav li.activeli");
			var nKey = dmLiActive.index();
			var nOid =  dmLiActive.find("a").data("oid");

			// Mostramos loadding
			am_showLoad();

			// Aumentamos uno
			dmLiActive.find(".cant").text( parseInt(dmLiActive.find(".cant").text()) + 1 );

			// Añadimos
			$.ajax({
				url: "categories.php?action=attributeManager&method=attributeManagerAdd",
				type: "POST",
				data: {products_id: nProductsId, option_id: nOid, value_id: nId, key: nKey, ids: JSON.stringify(aProductsAttributes)},
				success: function(sJson)
				{
					// Obtenemos lo recibido
					aJson = jQuery.parseJSON(sJson);

					// Eliminamos lo que tengamos en el panel derecho
					$("#atrb-mngr .drch").html(aJson.html);

					// Si no existe el key lo creamos
					if( !(nKey in aProductsAttributes) )
						aProductsAttributes[nKey] = [];

					// Insertamos el registro en el array
					aProductsAttributes[nKey].push(aJson.id);

					// Recargamos eventos de valores
					am_reloadEventValues();

					// Ocultamos loadding
					am_hideLoad();
				}
			});
		});

		// Ordenar valores //
		$("#atrb-mngr .drch tbody").sortable(
		{
			cursor: "move",
			over: function(e, ui)
			{
				nPosicionAntigua = ui.item.index();
			},
			beforeStop: function(e, ui)
			{
				// Mostramos loadding
				am_showLoad();

				// Variables
				var dmLiActive = $("#atrb-mngr .subNav .activeli");
				var nKey = dmLiActive.index();
				var nIdo = dmLiActive.find("a").data("oid");

				// Cambiamos el array
				aProductsAttributes[nKey] = aProductsAttributes[nKey].filter(Boolean);
				aProductsAttributes[nKey].move(nPosicionAntigua, ui.item.index());

				// Actualizamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerSort",
					type: "POST",
					data: {products_id: nProductsId, ids: JSON.stringify(aProductsAttributes), returns: true, options_id: nIdo},
					success: function(sHtml)
					{
						// Ocultamos loadding
						am_hideLoad();

						// Eliminamos lo que tengamos en el panel derecho
						$("#atrb-mngr .drch").html(sHtml);

						// Recargamos eventos de valores
						am_reloadEventValues();
					}
				});
			},
			helper: function(e)
			{
				return "<tr style='background: #cecece; opacity: 0.3;' class='drag'></tr>";
			}
		}).addClass("orden");

		// Eliminar valor //
		$("#atrb-mngr .drch .dlte").unbind().click(function(e)
		{
			// Detenemos evento
			e.stopPropagation();

			// Preguntamos si deseamos eliminar
			if( confirm("¿Realmente deseas eliminar el valor?") )
			{
				// Mostramos loadding
				am_showLoad();

				// Variables
				var dmLiActive = $("#atrb-mngr .subNav .activeli");
				var nOid =  dmLiActive.find("a").data("oid");
				var nKey = dmLiActive.index();
				var nId = $(this).data("id");

				// Variable para guardar los ids que seran eliminados
				var aDelete = [nId];

				// Eliminamos del array
				aProductsAttributes[nKey] = jQuery.grep(aProductsAttributes[nKey], function(value){ return value != nId; });

				// Cantidad de valores en el array
				nCantidad = aProductsAttributes[nKey].length

				// Quitamos undefined que ha podido quedar en el array
				if( nCantidad == 0 )
					aProductsAttributes.clean("");

				// Eliminamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerDelete",
					type: "POST",
					data: {products_id: nProductsId, option_id: nOid, ids_delete: aDelete, ids: JSON.stringify(aProductsAttributes)},
					success: function(sHtml)
					{
						// Restamos uno
						dmLiActive.find(".cant").text( parseInt(dmLiActive.find(".cant").text()) - 1 )

						// Eliminamos lo que tengamos en el panel derecho
						$("#atrb-mngr .drch").html(sHtml);

						// Recargamos eventos de valores
						am_reloadEventValues();

						// Ocultamos loadding
						am_hideLoad();
					}
				});
			}

			// Retornamos
			return false;
		});
	};

	// Funcion para recargar los eventos de las opciones
	var am_reloadEventOptions = function()
	{
		// Click en el menu izquierda donde se encuentra las opciones y acciones //
		$("#atrb-mngr .subNav a").unbind().click(function(e)
		{
			// Variables
			var dmThis = $(this);

			// Detenemos evento
			e.stopPropagation();

			// Mostramos loadding
			am_showLoad();

			// Peticion ajax para mostrar los valores de la opcion
			$.ajax({
				url: "categories.php?action=attributeManager&method=attributeManagerValuesOption",
				method: "post",
				data: {products_id: nProductsId, options_id: dmThis.data("oid")},
				success: function(sHtml)
				{
					// Quitamos activo
					$("#atrb-mngr .subNav li.activeli").removeClass("activeli").find("a").removeClass("this");

					// Añadimos activo
					dmThis.addClass("this").parent().addClass("activeli");

					// Eliminamos lo que tengamos en el panel derecho
					$("#atrb-mngr .drch").html(sHtml);

					// Recargamos eventos de valores
					if( $.isNumeric(dmThis.data("oid")) )
						am_reloadEventValues();
					else
					{
						switch(dmThis.data("oid"))
						{
							case "stock":
								am_reloadEventStock();
							break;

							case "accion":
								am_reloadEventAction();
							break;
						}
					}

					// Ocultamos loadding
					am_hideLoad();
				}
			});
		});

		// Eliminar opcion //
		$("#atrb-mngr .subNav a span.icos-trash").unbind().click(function(e)
		{
			// Detenemos evento
			e.stopPropagation();

			// Preguntamos si deseamos eliminar
			if( confirm("¿Realmente deseas eliminar la opción?") )
			{
				// Mostramos loadding
				am_showLoad();

				// Variables
				var dmLiActive = $(this).closest("li")
				var nKey = dmLiActive.index();
				var nOid =  dmLiActive.find("a").data("oid");

				// Variable para guardar los ids que seran eliminados
				var aDelete = [];

				// Si existe el key en el array eliminamos
				if( nKey in aProductsAttributes )
				{
					// Obtenemos los ids
					$.each(aProductsAttributes[nKey], function(key, value)
					{
						aDelete.push(value);
					});

					// Eliminamos
					delete aProductsAttributes[nKey];

					// Quitamos undefined que ha podido quedar en el array
					aProductsAttributes = aProductsAttributes.filter(Boolean);
				}

				// Eliminamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerDelete",
					type: "POST",
					data: {products_id: nProductsId, option_id: nOid, ids_delete: aDelete, ids: JSON.stringify(aProductsAttributes)},
					success: function()
					{
						// Obtenemos la siguiente opción en la lista y pulsamos
						dmLiActive.next().find("a").trigger("click");

						// Creamos un option para añadirlo al combobox
						var dmOption = $("<option/>").attr("value", dmLiActive.find("a").data("oid") ).text( dmLiActive.text().replace( / \(.+$/, "" ) );

						// Lo insertamos en el combobox
						$("#inst-optn").append(dmOption);

						// Actualizamos el combobox
						$('#inst-optn').trigger('chosen:updated');

						// Eliminamos li
						dmLiActive.remove();

						// Ocultamos loadding
						am_hideLoad();
					}
				});
			}

			// Retornamos
			return false;
		});
	};

	// Funcion que recarga los eventos del attributeManager
	var am_reloadEventAttributeManager = function(bReloadUniform)
	{
		// Uniform
		if( bReloadUniform == true )
			$("#atrb-mngr .drch .check :checkbox").uniform();

		// Obtenemos el products_id
		nProductsId = $("#atrb-mngr").data("pid");

		// Obtenemos el array con todos los id del products_atributes, esto nos servira para ordenar
		aProductsAttributes = jQuery.parseJSON($("#atrb-mngr .array").text());

		// Eventos opciones //
		am_reloadEventOptions();

		// Eventos valores //
		am_reloadEventValues(false);

		// Creamos la ventana para insertar enlaces
		$("#dialog").dialog({
			position:{my: "center",at: "center",of: window},
			autoOpen: false,
			width: "940",
			resizable: false,
			modal: true,
			open: function()
			{
				$("body").css("overflow","hidden");
				$('.ui-widget-overlay').unbind().click(function() {$('#dialog').dialog('close');})
			},
			close: function()
			{
				$("body").css("overflow-y","scroll");
				$("#dialog").removeClass("form-actn");
			}
		});

		// Crear opcion //
		$("#crte-optn").click(function()
		{
			// Mostramos loadding
			am_showLoad();

			// Realizamos peticion ajax para el formulario
			$.ajax({
				url: "categories.php?action=attributeManager&method=attributeManagerProductsAttributes",
				data: {a: "option_add_form"},
				type: "POST",
				success: function(sHtml)
				{
					// Ocultamos loadding
					am_hideLoad();

					// Modificamos el dialog y mostramos
					$("#dialog").html(sHtml);

					// Formulario
					var dmForm = $("#dialog form");

					// Send form
					dmForm.submit(function(e)
					{
						// Detenemos evento
						e.stopPropagation();

						// Cerramos dialog
						$("#dialog").dialog("close");

						// Mostramos loadding
						am_showLoad();

						// Enviamos el form por ajax
						$.ajax({
							type: "POST",
							url: dmForm.attr("action"),
							data: dmForm.serialize()
						}).done(function(sJson)
						{
							// Variables
							aJson = $.parseJSON(sJson);
							var dmElement = $("<div/>", {html: aJson.mensaje});

							// Creamos una option para añadirlo al combobox
							var dmOption = $("<option/>").attr("value", aJson.id ).text( aJson.nombre );

							// Lo insertamos en el combobox
							$("#inst-optn").append(dmOption);

							// Le damos valor
							$("#inst-optn").val(aJson.id).trigger("change");
						});

						// Retornamos
						return false;
					});

					// Titulo
					$("#dialog").dialog("option", "title", "Nueva opción");

					// Mostramos la ventana
					$("#dialog").dialog("open");
				}
			});
		});

		// Insertar opcion //
		// Combobox con búsquedas
		$("#inst-optn").chosen({no_results_text: "No existen resultados"});

		// Cuando seleccionamos una opción a insertar
		$("#inst-optn").change(function()
		{
			// Variables
			var nId = $(this).val();
			var dmElement = $(this).find("option[value=" + nId + "]");
			var sNombre = dmElement.text();

			// Reseteamos el combobox
			$(this).val("");

			// Eliminamos del combobox
			dmElement.remove();

			// Creamos la opción html
			var dmLi = $("<li/>").html('<a class="new" data-oid="' + nId + '" href="javascript:void(0);"><span class="icos-trash"></span>' + sNombre + ' (<span class="cant">0</span>)</a>');

			// Insertamos en la lista
			$($("#atrb-mngr .subNav .cncl")[0]).before(dmLi);

			// Recargamos eventos
			am_reloadEventOptions();

			// Realizamos click y quitamos la clase
			$("#atrb-mngr .subNav .new").trigger("click").removeClass("new");

			// Actualizamos el combobox
			$('#inst-optn').trigger('chosen:updated');

			// Insertamos en el array
			aProductsAttributes.push([]);
		});

		// Ordenar opciones //
		$("#atrb-mngr .subNav").sortable(
		{
			cursor: "move",
			helper : 'clone',
			items : 'li:not(.cncl)',
			start:  function(e, ui)
			{
				$(this).attr('data-previndex', ui.item.index());
			},
			over: function(e, ui)
			{
				nPosicionAntigua = ui.item.index()
			},
			beforeStop: function(e, ui)
			{
				// Mostramos loadding
				am_showLoad();

				// Cambiamos el array
				aProductsAttributes.move(nPosicionAntigua, ui.item.index());

				// Actualizamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerSort",
					type: "POST",
					data: {
						products_id: nProductsId,
						ids: JSON.stringify(aProductsAttributes),
						bReturn: false,
						oid: ui.item.find("a").data("oid"),
						prevPosition: $(this).attr('data-previndex'),
						nowPosition: ui.item.index()
					},
					success: function()
					{
						am_hideLoad();
					}
				});
			}
		});

		// Inicio, Template //
		// Cargar template
		$("#am_template .load").click(function()
		{
			// Variables
			var nValue = $("#am_template select").val();

			// Si el valor es mayor de 0
			if( nValue >= 1 )
			{
				// Obtenemos el nombre de la plantilla
				var nNameTemplate = $("#am_template select option:selected").text();

				// Preguntamos si queremos remplazar
				if( confirm( "¿Seguro que quiere recuperar la plantilla '" + nNameTemplate + "'?. Se sobreescribirán los atributos actuales del producto. La operación no se puede deshacer." ) )
				{
					// Mostramos loadding
					am_showLoad();

					// Actualizamos
					$.ajax({
						url: "categories.php?action=attributeManager&method=attributeManagerLoadTemplate",
						type: "POST",
						data: {products_id: nProductsId, id: nValue},
						success: function(sHtml)
						{
							// Posicionamos elemento donde añadiremos html
							var dmElementHtml = $("#atrb-mngr").next();

							// Eliminamos elementos
							$("#atrb-mngr").prev().remove();
							$("#atrb-mngr").remove();

							// Pintamos
							dmElementHtml.before( sHtml );

							// Recargamos eventos
							am_reloadEventAttributeManager(true);

							// Ocultamos loadding
							am_hideLoad();
						}
					});
				}
			}
			else
				alert("Debes seleccionar antes una plantilla de la lista");
		});

		// Guardar template
		$("#am_template .save").click(function()
		{
			// Variables
			var nValue = $("#am_template select").val();
			var nNameTemplate = $("#am_template select option:selected").text();

			// Si el valor es 0, debemos selccionar una opcion de la lista
			if( nValue == 0 )
			{
				alert("Seleccione una plantilla de la lista para editarla o si deseas crear una nueva pantilla seleccione la opción '+ Nueva plantilla'");
				return false;
			}

			// Si la opcion es -1 es crear una nueva plantilla
			if( nValue == -1 )
			{
				var nNameTemplate = prompt( "Por favor introduce el nombre de la nueva plantilla.");

				if( nNameTemplate != null )
				{
					// Mostramos loadding
					am_showLoad();

					// Actualizamos
					$.ajax({
						url: "categories.php?action=attributeManager&method=attributeManagerNewTemplate",
						type: "POST",
						data: {products_id: nProductsId, name: nNameTemplate},
						success: function(nId)
						{
							// Añadimos el template al combobox
							$("#am_template select").append($("<option/>").attr("value", nId ).text( nNameTemplate ));

							// Ocultamos loadding
							am_hideLoad();
						}
					});
				}

				return false;
			}

			// Si el valor es mayor de 0, vamos a editar una plantilla
			if( nValue >= 1 )
			{
				if( confirm( "ATENCIÓN: Vas a editar la plantilla '" + nNameTemplate + "', si deseas crear una nueva pantilla seleccione la opción '+ Nueva plantilla'. ¿Estas totalmente deacuerdo?") )
				{
					// Actualizamos
					$.ajax({url: "categories.php?action=attributeManager&method=attributeManagerNewTemplate", type: "POST", data: {products_id: nProductsId, name: nNameTemplate, id: nValue},success: function(){am_hideLoad();}});
				}

				return false;
			}
		});

		// Editar template
		$("#am_template .edit").click(function()
		{
			// Variables
			var nValue = $("#am_template select").val();
			var nNameTemplate = $("#am_template select option:selected").text();

			// Si el valor es mayor de 0
			if( nValue >= 1 )
			{
				var nNameTemplate = prompt( "Por favor introduce el nuevo nombre de la plantilla.", nNameTemplate);

				if( nNameTemplate != null )
				{
					// Actualizamos
					$.ajax({
						url: "categories.php?action=attributeManager&method=attributeManagerEditNameTemplate",
						type: "POST",
						data: {id: nValue, name: nNameTemplate},
						success: function()
						{
							// Cambiamos el nombre en el combobox
							$("#am_template select option:selected").text(nNameTemplate);

							// Ocultamos loadding
							am_hideLoad();
						}
					});
				}
			}
			else
				alert("Debes seleccionar antes una plantilla de la lista");
		});

		// Eliminar template
		$("#am_template .delete").click(function()
		{
			// Variables
			var nValue = $("#am_template select").val();
			var nNameTemplate = $("#am_template select option:selected").text();

			// Si el valor es mayor de 0
			if( nValue >= 1 && confirm("¿Estas totalmente deacuerdo para eliminar la plantilla '" + nNameTemplate + "'?") )
			{
				// Actualizamos
				$.ajax({
					url: "categories.php?action=attributeManager&method=attributeManagerDeleteTemplate",
					type: "POST",
					data: {id: nValue},
					success: function()
					{
						// Cambiamos el nombre en el combobox
						$("#am_template select option:selected").remove();

						// Ocultamos loadding
						am_hideLoad();
					}
				});
			}
			else
				alert("Debes seleccionar antes una plantilla de la lista");
		});
		// Fin, Template //
	};

	// Load Jquery
	$(document).ready(function()
	{
		// Obtenemos la capa donde estan los atributos para mostrarla para reproducir eventos
		var dmLayoutOptions = $(".tab-new[data-id=" + $('.nav li a span:contains("Opciones")').closest("a").data("id") + "]");
				
		// Mostramos
		dmLayoutOptions.css("display", "block");
		
		// Recargamos eventos
		am_reloadEventAttributeManager();
		
		// Ocultamos
		dmLayoutOptions.css("display", "none");
	});
})(jQuery);


// Funciones //
Array.prototype.move = function(from,to)
{
	this.splice(to,0,this.splice(from,1)[0]);
	return this;
};

Array.prototype.clean = function(deleteValue)
{
	for(var i = 0; i < this.length; i++)
	{
		if (this[i] == deleteValue)
		{
			this.splice(i, 1);
			i--;
		}
	}

	return this;
};
