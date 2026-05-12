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
	$(".form-admin-s2p .add-right").click(function () {
		let selectLeft = $(this).parent().prev().find("select");
		let selecRight = $(this).parent().next().find("select");

		selectLeft.find('option:selected').remove().appendTo(selecRight);

		selectLeft.find('option').prop('selected', false);
		selecRight.find('option').prop('selected', false);

		selectLeft.cacheOption();
		selecRight.cacheOption();
	});

	// Quitar selecionados
	$(".form-admin-s2p .add-left").click(function () {
		let selectLeft = $(this).parent().prev().find("select");
		let selecRight = $(this).parent().next().find("select");

		selecRight.find('option:selected').remove().appendTo(selectLeft);

		selectLeft.find('option').prop('selected', false);
		selecRight.find('option').prop('selected', false);

		selectLeft.cacheOption();
		selecRight.cacheOption();
	});

	// Insertar todos
	$(".form-admin-s2p .add-all-right").click(function () {
		$(this).parent().prev().find("select").find('option:not(.hidden)').prop('selected', true);
		$(".form-admin-s2p .add-right").trigger("click");
	});

	// Quitar todas
	$(".form-admin-s2p .add-all-left").click(function () {
		$(this).parent().next().find("select").find('option:not(.hidden)').prop('selected', true);
		$(".form-admin-s2p .add-left").trigger("click");
	});

	// Doble click
	$(".form-admin-s2p select").on("dblclick", "option", function()
	{
		if ($(this).parent().hasClass("from")) {
			$(this).closest(".groups-select-form").find(".buttons .add-right").trigger("click");
			return true;
		}

		if ($(this).parent().hasClass("to")) {
			$(this).closest(".groups-select-form").find(".buttons .add-left").trigger("click");
			return true;
		}
	});

	// Formularios
	$(".form-admin-s2p").submit(function(){
		let checkboxAllZones = $(this).find("input[name=all_zones]");

		checkboxAllZones.prop("checked", $(this).find(".select-search.from option").length === 0);
		$(this).find(".input-search").val("").trigger("keyup");
		$(this).find(".select-search.to option").prop('selected', true);

		return true;
	});

}(jQuery);
