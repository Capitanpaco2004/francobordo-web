(function($)
{
	$(".date-range").click(function(e) {
		// Obtenemos los elementos
		var dmFrom = $(this).parent().find(".from");
		var dmTo = $(this).parent().find(".to");

		// Obtenemos las 2 fechas que estamos filtrando
		var sFrom = dmFrom.val();
		var sTo = dmTo.val();

		// Limpiamos fechas
		dmFrom.val("");
		dmTo.val("");

		// Desde....
		dmFrom.datepicker({
			dateFormat: "dd/mm/yy",
			changeMonth: true,
			changeYear: true,
			yearRange: "-100:+0",
			onClose: function( selectedDate )
			{
				// Si al cerrar tenemos seleccionada alguna fecha mostramos hasta....
				if( dmFrom.val() != "" )
					dmTo.datepicker("show");
				else // Si no ponemos las que teniamos
				{
					dmFrom.val( sFrom );
					dmTo.val( sTo );
				}
			}
		});

		// Hasta..
		dmTo.datepicker({
			dateFormat: "dd/mm/yy",
			changeMonth: true,
			changeYear: true,
			yearRange: "-100:+0",
			beforeShow: function()
			{
				// Antes de mostrar cargamos la restricción de fecha
				var aFecha = dmFrom.val().split( "/" );
				var minDate = new Date( parseInt( aFecha[2] ), parseInt( aFecha[1] ) - 1, parseInt( aFecha[0] ) );

				return { minDate: minDate };
			},
			onClose: function( selectedDate )
			{
				// Si contenemos una fecha enviamos el form
				if( dmTo.val() != "" )
				{}//$("#date-range-form").submit();
				else // Si no ponemos las que teniamos
				{
					dmFrom.val( sFrom );
					dmTo.val( sTo );
				}
			}
		});

		// Al hacer click siempre mostramos antes el desde...
		dmFrom.datepicker("show");
	});

	if ($(".atload").length > 0) {
		window.open($(".atload").attr('href'), '_blank');
	}

	$(".ext-frm-btn").click(function() {
		$(".ext-frm").submit();
	});
})(jQuery);