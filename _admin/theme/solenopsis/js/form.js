var formClass;

!function ($){
	formClass = function(dmElement, aArguments)
	{
		// Si contiene form no tenemos que volver a crearle eventos
		if( dmElement.data( "form" ) )
			return false;

		// Variables
		var self = this;

		// Crear input color
		this.inputColorCreate = function()
		{
			// Padre que encapsula el input
			var dmParent = $('<div class="xfcolor"></div>');

			// Añadimos el div interior con el color
			var dmFake = dmElement.wrap(dmParent).parent().append( '<div class="xfcolor-bg" style="background:' + dmElement.val() + '"></div> <div class="xfcolor-val">' + dmElement.val() + '</div>' );

			// Cuando seleccionamos color mostramos el color
			dmElement.change(function(e)
			{
				dmElement.parent().find(".xfcolor-bg").css( "background", dmElement.val() );
				dmElement.parent().find(".xfcolor-val").text( dmElement.val() );
			});
		}

		// Crear input file
		this.inputFileCreate = function()
		{
			// Padre que encapsula el input
			var dmParent = $('<div class="xffile"></div>');

			// Variables
			var sFile = dmElement.data("file") ? dmElement.data("file") : "";
			var sButtonIcon = dmElement.data("button-icon") ? dmElement.data("button-icon") : "fa-folder";
			var sButtonText = dmElement.data("button-text") ? dmElement.data("button-text") : "Seleccionar archivo";
			var sButtonColor = dmElement.data("button-color") ? dmElement.data("button-color") : "";
			var dmInner = dmElement.data("inner") ? dmElement.data("inner") : "";

			// Añadimos el div interior que tiene el nombre del archivo y el boton de subir archivo
			var dmFake = dmElement.wrap(dmParent).parent().append( '<div class="xffile-name">' + sFile + '</div> <div class="xbutton ' + sButtonColor + ' afixed"><i class="fa ' + sButtonIcon + '"></i> ' + sButtonText + '</div>' );

			// Si contenemos al lado del fake un elemento
			if( dmInner!= "" && dmFake.next().hasClass( dmInner ) )
				dmFake.prepend(dmFake.next().detach());

			// Cuando seleccionamos un archivo mostramos el nombre
			dmElement.change(function(e)
			{
				$(this).next().text( e.target.value.split( '\\' ).pop() );
			});
		}

    this.inputLanguage = function() {
      // Para cada .xfselect o .drop.xfselect individual
      $(".xfselect, .drop.xfselect").each(function() {
        var $selectContainer = $(this);

        // Buscar el contenedor padre que tiene los bloques de idioma
        var $parentRow = $selectContainer.closest(".column.a10");

        // Si no encuentra el contenedor correcto, buscar alternativas
        if ($parentRow.length === 0) {
          $parentRow = $selectContainer.parent();
        }

        $selectContainer.find(".down a, ul.down a").unbind("click.form_input_language").bind("click.form_input_language", function(e) {
          e.preventDefault(); // Prevenir comportamiento por defecto del enlace

          var idSeleccionado = $(this).data("id");

          // Ocultar todos los bloques de idioma en este contenedor
          $parentRow.find(".input-language").each(function() {
            $(this).css({
              "display": "none",
              "visibility": "hidden",
              "position": "absolute",
              "left": "-9999px"
            });
          });

          // Mostrar solo el bloque con el data-id correspondiente
          var $bloqueSeleccionado = $parentRow.find('.input-language[data-id="' + idSeleccionado + '"]');

          $bloqueSeleccionado.css({
            "display": "block",
            "visibility": "visible",
            "position": "static",
            "left": "auto"
          });
        });
      });
    };

		// Calendario
		this.dateTime = function()
		{
			// Si no tenemos calendarios retornamos
			if( $(".form-datetime-simple").length == 0 && $(".form-datetime-range").length == 0 && $(".form-date-simple").length == 0 && $(".form-date-range").length == 0 && $(".form-datetime-time").length == 0 )
				return false;

			var locale = [];

			locale["espanol"] = {
				"separator": " - ",
				"applyLabel": "Aplicar",
				"cancelLabel": "Cancelar",
				"monthNames": ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
				"daysOfWeek": ["DO", "LU", "MA", "MI", "JU", "VI", "SÁ"]
			};

			locale["english"] = {
				"separator": " - ",
				"applyLabel": "Apply",
				"cancelLabel": "Cancel",
				"monthNames": ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
				"daysOfWeek": ["SU", "MO", "TU", "WE", "TH", "FR", "SA"]
			};

			// Opciones
			var dateTimeOptionsDefault = {
				"applyClass": "xbutton verde small",
				"cancelClass": "xbutton rojo small",
				"locale": locale[($("#ck_lang").length && $("#ck_lang").val()) ? $("#ck_lang").val() : "espanol"]
			}

			// Extendemos las opciones
			var dateTimeSimpleOptions = new $.extend( true, dateTimeOptionsDefault );
			var dateSimpleOptions = new $.extend( true, dateTimeOptionsDefault );
			var dateTimeRangeOptions = new $.extend( true, dateTimeOptionsDefault );
			var dateRangeOptions = new $.extend( true, dateTimeOptionsDefault );

			// Opciones
			dateTimeSimpleOptions.singleDatePicker = true;
			dateTimeSimpleOptions.timePicker = true;
			dateTimeSimpleOptions.timePickerSeconds = true;
			dateTimeSimpleOptions.timePicker24Hour = true;
			dateTimeSimpleOptions.locale.format = "DD-MM-YYYY HH:mm:ss";

			dateSimpleOptions.locale.format = "DD-MM-YYYY";
			dateSimpleOptions.singleDatePicker = true;
			dateSimpleOptions.showDropdowns = true;

			dateTimeRangeOptions.timePicker = true;
			dateTimeRangeOptions.timePickerSeconds = true;
			dateTimeRangeOptions.timePicker24Hour = true;
			dateTimeRangeOptions.locale.format = "DD-MM-YYYY HH:mm:ss";

			dateRangeOptions.locale.format = "DD-MM-YYYY"

			// Muestra la hora
			if( dmElement.hasClass("form-datetime-time") )
			{
				// Variables
				var sHtmlHour = "";
				var sHtmlMinutes = "";
				var sHtmlSeconds = "";
				var sHour = "";
				var sMinute = "";
				var sSecond = "";

				// Si contenemos valor
				if( dmElement.val() != "" )
				{
					var aValue = dmElement.val().split(":");
					sHour = aValue[0];
					sMinute = aValue[1];
					sSecond = aValue[2];
				}

				// Añadimos al padre las clases
				dmElement.parent().addClass("row").addClass("ax");

				// Encapsulamos
				var dmParent = dmElement.wrap('<div class="column a12 row ax aflex xfhour"></div>').parent();

				// Si tenemos opciones
				if (dmElement.data("option")){
					let options = dmElement.data("option").split(",");

					$(options).each(function (index, value) {
						value = value.split(":");
						sHtmlHour += '<option' + (sHour == value[0] ? ' selected="selected"' : '') + ' value="' + value[0] + '">' + value[1] + '</option>';
						sHtmlMinutes += '<option' + (sMinute == value[0] ? ' selected="selected"' : '') + ' value="' + value[0] + '">' + value[1] + '</option>';
						sHtmlSeconds += '<option' + (sSecond == value[0] ? ' selected="selected"' : '') + ' value="' + value[0] + '">' + value[1] + '</option>';
					})
				}

				// Hora
				for( var nCont = 0; nCont <= 23; nCont++ )
				{
					nCont = nCont < 10 ? '0' + nCont : nCont;
					sHtmlHour += '<option' + (sHour == nCont ? ' selected="selected"' : '') + ' value="' + nCont + '">' + nCont + '</option>';
				}

				// Minuto y segundos
				for( var nCont = 0; nCont <= 59; nCont++ )
				{
					nCont = nCont < 10 ? '0' + nCont : nCont;
					sHtmlMinutes += '<option' + (sMinute == nCont ? ' selected="selected"' : '') + ' value="' + nCont + '">' + nCont + '</option>';
					sHtmlSeconds += '<option' + (sSecond == nCont ? ' selected="selected"' : '') + ' value="' + nCont + '">' + nCont + '</option>';
				}

				// Selects
				sHtmlHour = $('<select data-id="hour" />').html( sHtmlHour );
				sHtmlMinutes = $('<select data-id="minute" />').html( sHtmlMinutes );
				sHtmlSeconds = $('<select data-id="second" />').html( sHtmlSeconds );
				dmParent.append( '<div class="column afluid" style="width: 70px;">' + $("<div/>").append( sHtmlHour.clone() ).html() + '</div><div class="column afluid xfsepa">:</div>' );
				dmParent.append( '<div class="column afluid" style="width: 70px;">' + $("<div/>").append( sHtmlMinutes.clone() ).html() + '</div><div class="column afluid xfsepa">:</div>' );
				dmParent.append( '<div class="column afluid" style="width: 70px;">' + $("<div/>").append( sHtmlSeconds.clone() ).html() + '</div>' );

				// Eventos
				dmParent.find("select").change(function()
				{
					var dmHour = dmParent.find("select[data-id=hour]");
					var dmMinute = dmParent.find("select[data-id=minute]");
					var dmSecond = dmParent.find("select[data-id=second]");

					dmParent.find("input").val( dmHour.val() + ":" + dmMinute.val() + ":" + dmSecond.val() );
				});

				// Style select
				dmParent.find("select").form();
			}

			// Dia de la semanas
			if( dmElement.hasClass("form-datetime-days-of-week") )
			{
				let selected = dmElement.val();
				let htmlDays = "";
				let days = ["Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado"];

				// Si tenemos opciones
				if (dmElement.data("option")){
					let options = dmElement.data("option").split(",");

					$(options).each(function (index, value) {
						value = value.split(":");
						htmlDays += '<option' + (selected == value[0] ? ' selected="selected"' : '') + ' value="' + value[0] + '">' + value[1] + '</option>';
					})
				}

				// Dias de la semana
				for( let nCont = 0; nCont <= 6; nCont++ )
				{
					htmlDays += '<option' + (selected == nCont ? ' selected="selected"' : '') + ' value="' + nCont + '">' + days[nCont] + '</option>';
				}
				// Encapsulamos
				var dmParent = dmElement.wrap('<div class="column a12 row ax aflex xfhour"></div>').parent();

				// Select
				htmlDays = $('<select data-id="days-of-week" />').html( htmlDays );
				dmParent.append(htmlDays.clone());
				dmParent.find("select").form();

				// Eventos
				dmParent.find("select").change(function()
				{
					dmParent.find("input").val( $(this).val() );
				});
			}

			// Mes
			if( dmElement.hasClass("form-datetime-month") )
			{
				let selected = dmElement.val();
				let htmlMonths = "";
				let months = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

				// Si tenemos opciones
				if (dmElement.data("option")){
					let options = dmElement.data("option").split(",");

					$(options).each(function (index, value) {
						value = value.split(":");
						htmlMonths += '<option' + (selected == value[0] ? ' selected="selected"' : '') + ' value="' + value[0] + '">' + value[1] + '</option>';
					})
				}

				// Dias de la semana
				for( let nCont = 1; nCont <= 12; nCont++ )
				{
					htmlMonths += '<option' + (selected == nCont ? ' selected="selected"' : '') + ' value="' + nCont + '">' + months[nCont] + '</option>';
				}
				// Encapsulamos
				var dmParent = dmElement.wrap('<div class="column a12 row ax aflex xfhour"></div>').parent();

				// Select
				htmlMonths = $('<select data-id="month" />').html( htmlMonths );
				dmParent.append(htmlMonths.clone());
				dmParent.find("select").form();

				// Eventos
				dmParent.find("select").change(function()
				{
					dmParent.find("input").val( $(this).val() );
				});
			}

			// Dia
			if( dmElement.hasClass("form-datetime-day") )
			{
				let selected = dmElement.val();
				let htmlDays = "";

				// Si tenemos opciones
				if (dmElement.data("option")){
					let options = dmElement.data("option").split(",");

					$(options).each(function (index, value) {
						value = value.split(":");
						htmlDays += '<option' + (selected == value[0] ? ' selected="selected"' : '') + ' value="' + value[0] + '">' + value[1] + '</option>';
					})
				}

				// Dias
				for( let nCont = 1; nCont <= 31; nCont++ )
				{
					htmlDays += '<option' + (selected == nCont ? ' selected="selected"' : '') + ' value="' + nCont + '">' + nCont + '</option>';
				}

				// Encapsulamos
				var dmParent = dmElement.wrap('<div class="column a12 row ax aflex xfhour"></div>').parent();

				// Select
				htmlDays = $('<select data-id="day" />').html( htmlDays );
				dmParent.append(htmlDays.clone());
				dmParent.find("select").form();

				// Eventos
				dmParent.find("select").change(function()
				{
					dmParent.find("input").val( $(this).val() );
				});
			}

			// Muestra un calendario con hora simple
			if( dmElement.hasClass("form-datetime-simple") )
			{
				// Si tenemos especificado que no se ponga la fecha automaticamente al cargar
				if( dmElement.data("autoupdate") )
					dateTimeSimpleOptions.autoUpdateInput = false;

				// DateRange
				dmElement.daterangepicker( dateTimeSimpleOptions );

				// Si tenemos especificado que no se ponga la fecha automaticamente al seleccionar una fecha no se actualizara así que lo obligamos
				if( dmElement.data("autoupdate") )
					dmElement.on('apply.daterangepicker', function(ev, picker){ dmElement.val( picker.startDate.format( dateTimeSimpleOptions.locale.format ) ); } );
			}

			// Mostramos dos calendarios con hora para seleccionar un rango
			if( dmElement.hasClass("form-datetime-range") )
			{
				// Si tenemos especificado que no se ponga la fecha automaticamente al cargar
				if( dmElement.data("autoupdate") )
					dateTimeRangeOptions.autoUpdateInput = false;

				// DateRange
				dmElement.daterangepicker( dateTimeRangeOptions );

				// Si tenemos especificado que no se ponga la fecha automaticamente al seleccionar una fecha no se actualizara así que lo obligamos
				if( dmElement.data("autoupdate") )
					dmElement.on('apply.daterangepicker', function(ev, picker){ dmElement.val( picker.startDate.format( dateTimeRangeOptions.locale.format ) + ' - ' + picker.endDate.format( dateTimeRangeOptions.locale.format ) ); } );
			}

			// Muestra un calendario simple
			if( dmElement.hasClass("form-date-simple") )
			{
				// Si tenemos especificado que no se ponga la fecha automaticamente al cargar
				if( dmElement.data("autoupdate") )
					dateSimpleOptions.autoUpdateInput = false;

				// DateRange
				dmElement.daterangepicker( dateSimpleOptions );

				// Si tenemos especificado que no se ponga la fecha automaticamente al seleccionar una fecha no se actualizara así que lo obligamos
				if( dmElement.data("autoupdate") )
					dmElement.on('apply.daterangepicker', function(ev, picker){ dmElement.val( picker.startDate.format( dateSimpleOptions.locale.format ) ); } );
			}

			// Mostramos dos calendarios para seleccionar un rango
			if( dmElement.hasClass("form-date-range") )
			{
				// Si tenemos especificado que no se ponga la fecha automaticamente al cargar
				if( dmElement.data("autoupdate") )
					dateRangeOptions.autoUpdateInput = false;

				// DateRange
				dmElement.daterangepicker( dateRangeOptions );

				// Si tenemos especificado que no se ponga la fecha automaticamente al seleccionar una fecha no se actualizara así que lo obligamos
				if( dmElement.data("autoupdate") )
					dmElement.on('apply.daterangepicker', function(ev, picker){ dmElement.val( picker.startDate.format( dateRangeOptions.locale.format ) + ' - ' + picker.endDate.format( dateRangeOptions.locale.format ) ); } );
			}

			// Magnificpopup funcione los select de hora, minuto y segundos del dateranger
			$.magnificPopup.instance._onFocusIn = function(e)
			{
				if( $(e.target).hasClass('hourselect') || $(e.target).hasClass('minuteselect') || $(e.target).hasClass("secondselect") )
					return true;

				$.magnificPopup.proto._onFocusIn.call(this,e);
			};
		}

		// Select mostrar texto seleccionado del combobox
		this.selectShowText = function(dmElement)
		{
			dmElement.parent().find("div").text( dmElement.find("option:selected").text() );
		}

		// Resetear select
		this.selectReset = function()
		{
			self.selectShowText( dmElement );
		}

		// Select Crear
		this.selectCreate = function()
		{
			// Padre que encapsula el select
			var dmParent = $('<div class="xfselect"></div>');

			// Añadimos el select y el div que sera el fake
			dmElement.wrap(dmParent).parent().append( $("<div/>").text("") );

			// Evento
			dmElement.on("change.form", function()
			{
				self.selectShowText( dmElement )
			});

			// Mostrar texto en el select por defecto
			self.selectShowText( dmElement );
		}

		// Comprueba el elemento que sea y lo mando a crear
		this.create = function()
		{
			var sElementClass = dmElement.attr( "class" );

			var bDateTime = $.type(sElementClass) != "undefined" && $.type( sElementClass.match(/^form-date/) ) != "null" ? true : false;
			var bInputLanguage = $.type(sElementClass) != "undefined" && $.type( sElementClass.match(/input-language/) ) != "null" ? true : false;
			var bCkEditorLanguage = $.type(sElementClass) != "undefined" && $(dmElement).attr("wrap") == "tinymce" ? true : false;

			switch(true)
			{
				case bCkEditorLanguage:
				case bInputLanguage:
					this.inputLanguage();
				break;

				case bDateTime:
					this.dateTime();
				break;

				case dmElement.is("input"):
					switch( dmElement.attr("type") )
					{
						case "file":
							this.inputFileCreate();
						break;

						case "color":
							this.inputColorCreate();
						break;
					}
				break;

				case dmElement.is("select"):
					if(!dmElement.hasClass("select2_multiple") && dmElement.attr("skip_jquery") === undefined) {
						this.selectCreate();
					}
				break;
			}
		}

		// Crear elemento
		this.create();

		// Añadimos la instancia de la clase al elemento
		dmElement.data( "form", this );
	}

	$.fn.form = function(sMethod, aArguments)
	{
		// Si no nos envian un metodo sera los argumentos
		if( typeof sMethod !== 'string' )
		{
		    aArguments = sMethod;
		    sMethod = 'init';
		}

		// Argumentos
		aArguments = $.type( aArguments ) != "undefined" ? aArguments : {};

		// Retorno
		var objReturn = this;

		// Recorremos los elementos
		this.each( function()
		{
			// Instancia si ha sido creado anteriormente
			var instance = $(this).data("form");

			// Si tenemos instancia o si vamos a empezar
			if( instance || sMethod === 'init' )
			{
				// Si no tenemos instancia creamos el elemento
			    if( !instance )
			        instance = new formClass( $(this), aArguments );

			    // Si tenemos instancia y existe el metodo
			    if( instance[sMethod] )
			    {
			    	var returnAux = instance[sMethod].apply( instance, [instance, aArguments] );

			    	if( returnAux != "undefined" )
			    		objReturn = returnAux;
			    }
			}
		});

		// Retornamos
		return objReturn;
	}
}(jQuery);

$(document).ready(function() {
  // Solo ejecutar para selectores que realmente controlan idiomas
  $(".xfselect, .drop.xfselect").each(function() {
    var $selectContainer = $(this);
    var $parentRow = $selectContainer.closest(".column.a10, .column.a12, .row");

    // VERIFICAR: ¿Este selector tiene elementos .input-language cerca?
    if ($parentRow.find(".input-language").length > 0) {
      // Solo entonces asignar el evento
      $selectContainer.find("ul.down a").unbind("click.form_input_language").bind("click.form_input_language", function(e) {
        e.preventDefault();
        var idSeleccionado = $(this).data("id");

        $parentRow.find(".input-language").css({
          "display": "none",
          "visibility": "hidden",
          "position": "absolute",
          "left": "-9999px"
        });

        $parentRow.find('.input-language[data-id="' + idSeleccionado + '"]').css({
          "display": "block",
          "visibility": "visible",
          "position": "static",
          "left": "auto"
        });
      });
    }
  });
});
