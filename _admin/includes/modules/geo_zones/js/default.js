!function ($){
	// Toogle option para el buscador
	$.fn.toggleOption = function (show) {
		$(this).toggle(show);
		if (show) {
			if ($(this).parent('span.toggleOption').length)
				$(this).unwrap();
		} else {
			if ($(this).parent('span.toggleOption').length === 0)
				$(this).wrap('<span class="toggleOption" style="display: none;" />');
		}
	};

	// Cache option para el buscador
	$.fn.cacheOption = function () {
		$(this).data("cache_options", $(this).find("option").map(function () {
			return [[$(this).val(), $(this).text()]];
		}));
	};

	// Al cambiar de pais
	$("[data-ajax-states]").unbind("change.state").on("change.state", function() {
		var dmThis = $(this);

		$.ajax( {
			"url": "geo_zones.php",
			"type": "post",
			"data": {"action": "zones_to_geo_zones_get_country_zones", "country": dmThis.val()},
			"success": function(json)
			{
				let zones = $.parseJSON(json);
				let htmlOption = "";
				let dmSelects = $("#dialog-insert-subzone .select-search");

				dmSelects.empty();
				$("#dialog-insert-subzone .input-search").val("");

				$.each(zones,function(index,value){
					htmlOption += '<option value="' + value.id + '">' + value.text + '</option>';
				});

				$("#dialog-insert-subzone .select-search.from").append(htmlOption);
				dmSelects.cacheOption();
			}
		});
	}).trigger("change.state");

	// Tabla
	let table = $("#zone").DataTable({
		paging: false,
		language: {
			"sProcessing": $(this).data("processing"),
			"sLengthMenu": $(this).data("show-records"),
			"sZeroRecords": $(this).data("no-results"),
			"sEmptyTable": $(this).data("no-data"),
			"sInfo": $(this).data("view"),
			"sInfoEmpty": $(this).data("show-records"),
			"sInfoFiltered": $(this).data("show-filter"),
			"sSearch": $(this).data("search"),
			"sInfoThousands": ",",
			"sLoadingRecords": $(this).data("loading"),
			"oPaginate": {
				"sFirst": $(this).data("first"),
				"sLast": $(this).data("last"),
				"sNext": $(this).data("next"),
				"sPrevious": $(this).data("before")
			},
			"oAria": {
				"sSortAscending": ": " + $(this).data("order-asc"),
				"sSortDescending": ": " + $(this).data("order-desc")
			},
			"buttons": {
				"copy": $(this).data("copy"),
				"colvis": $(this).data("visibility")
			}
		}
	});

	// Buscar zonas
	$("#zone_search").on("keyup", function () {
		table.search(this.value).draw();
		$("#zone_delete_filter").addClass("actv");

		if (this.value == "") {
			$("#zone_delete_filter").removeClass("actv");
		}
	});

	// Selected
	$("#selected-zones-type").change(function () {
		let value = $(this).val();

		if (value === ""){
			$("#zone").find('tbody tr').css("display", "table-row");
		}
		else{
			$("#zone").find('tbody tr').css("display", "none");
			$("#zone").find('tbody tr[data-zone-type="' + value + '"]').css("display", "table-row");
		}
	});

	// Boton buscar zonas, quitar filtro
	$("#zone_delete_filter").click(function () {
		$("#zone_search").val("");
		$("#selected-zones-type").val("");
		table.search("").draw();
		$("#zone_delete_filter").removeClass("actv");
	});

	// Ventana
	$("#dialog-insert-subzone").dialog({
		position: ['middle', 20],
		width: "900",
		autoOpen: false,
		resizable: true,
		modal: true,
		close: function () {
			$("#dialog-insert-subzone form").trigger("reset");
		}
	});

	$("#dialog-insert-country").dialog({
		position: ['middle', 20],
		width: "900",
		autoOpen: false,
		resizable: true,
		modal: true,
		close: function () {
			$("#dialog-insert-country form").trigger("reset");
		}
	});

	// Abrir ventana insertar provincias
	$(".insert-subzone").click(function () {
		$("#dialog-insert-subzone").dialog("open");
		$(".ui-widget-overlay").unbind().click(function () {
			$("#dialog-insert-subzone").dialog("close");
		});
	});

	// Abrir ventana insertar pais
	$(".insert-country").click(function () {
		$("#dialog-insert-country").dialog("open");
		$(".ui-widget-overlay").unbind().click(function () {
			$("#dialog-insert-country").dialog("close");
		});
	});

	// Buscador select multiple
	$(".select-search").each(function () {
		let selectSearch = $(this);
		let inputSearch = selectSearch.parent().find(".input-search");

		selectSearch.cacheOption();

		$(inputSearch).keyup(function () {
			var rxp = new RegExp($(this).val(), 'i');
			var optlist = selectSearch.empty();

			selectSearch.data("cache_options").each(function () {
				if (rxp.test(this[1])) {
					optlist.append($('<option/>').attr('value', this[0]).text(this[1]));
				} else {
					optlist.append($('<option/>').attr('value', this[0]).text(this[1]).addClass("hidden"));
				}
			});
			$(".hidden").toggleOption(false);
		});
	})

	// Insertar selecionados
	$(".dialog-insert-subzone .add-right").click(function () {
		let selectLeft = $(this).parent().prev().find("select");
		let selecRight = $(this).parent().next().find("select");

		selectLeft.find('option:selected').remove().appendTo(selecRight);
		selectLeft.cacheOption();
		selecRight.cacheOption();
	});

	// Insertar todos
	$(".dialog-insert-subzone .add-all-right").click(function () {
		$(this).parent().prev().find("select").find('option').prop('selected', true);
		$(".dialog-insert-subzone .add-right").trigger("click");
	});

	// Quitar selecionados
	$(".dialog-insert-subzone .add-left").click(function () {
		let selectLeft = $(this).parent().prev().find("select");
		let selecRight = $(this).parent().next().find("select");

		selecRight.find('option:selected').remove().appendTo(selectLeft);
		selectLeft.cacheOption();
		selecRight.cacheOption();
	});

	// Quitar todas
	$(".dialog-insert-subzone .add-all-left").click(function () {
		$(this).parent().next().find("select").find('option').prop('selected', true);
		$(".dialog-insert-subzone .add-left").trigger("click");
	});

	// Doble click
	$(".dialog-insert-subzone select").on("dblclick", "option", function()
	{
		if ($(this).parent().hasClass("from")) {
			$(this).closest("form").find(".buttons .add-right").trigger("click");
			return true;
		}

		if ($(this).parent().hasClass("to")) {
			$(this).closest("form").find(".buttons .add-left").trigger("click");
			return true;
		}
	});


	// Formularios
	$(".dialog-insert-subzone").submit(function(){
		let checkboxAllZones = $(this).find("input[name=all_zones]");

		checkboxAllZones.prop("checked", $(this).find(".select-search.from option").length === 0);
		$(this).find(".input-search").val("").trigger("keyup");
		$(this).find(".select-search.to option").prop('selected', true);

		return true;
	});
}(jQuery);
