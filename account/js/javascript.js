jQuery// Validador
jQuery(".ccFormValid").parsley();

// Ajax states
jQuery("[data-ajax-states]").change(function()
{
	jQuery('.button.verde').attr("disabled", true);
	var dmElement =  jQuery( "#" + jQuery(this).data("ajax-states") );

	jQuery.ajax( {
		"url": "account/address_book_process.php",
		"type": "post",
		"data": {"action": "getStates", "country": jQuery(this).val()},
		"success": function( sHtml )
		{
			dmElement.html(sHtml);
			jQuery("select, input").form();
			setTimeout( function() { jQuery('.button.verde').removeAttr("disabled"); }, 1000 );
		}
	});
});

// Activamos los tipos diferentes de datos que deseamos importar
jQuery("#ccDelete .ccRow").click(function()
{
	if( jQuery(this).hasClass("actv") ) 
		jQuery(this).removeClass("actv").find("input").prop("checked", false);
	else
		jQuery(this).addClass("actv").find("input").prop("checked", true);
	
});

// Menu fake
jQuery(".ccMenuFakeLink").click(function()
{
	jQuery(".ccMenuFakeContent").slideToggle();
});


var noAjaxCall = false;

// Carga de ciudades a partir de Provincia
jQuery( ".column.getCitiesFromZone" ).on("change", "select", function(e) 
{
	if( noAjaxCall == false )
	{
		zone = jQuery(this).val();
		country = jQuery('select[name=country]').val();

		jQuery.post('information.php', 
		{
			action: 'getCities',
			zone: zone,
			country: country
		}, function(data) 
		{
			data = jQuery.parseJSON(data);
			jQuery('.column.city').html(data.cities);
			jQuery("select, input").form();
		});
	}

	noAjaxCall = false
});

// Carga de ciudades a partir de codigo postal
jQuery(".column.getCitiesFromCP").on("change", "#postcode", function() 
{
	postcode = jQuery(this).val();
	country = jQuery('select[name=country]').val();
	
	jQuery.post('information.php', 
	{
		action: 'getCities',
		cp: postcode,
		country: country
	}, function(data) 
	{
		data = jQuery.parseJSON(data)

		if( jQuery('select[name=country]').val() == data.id_country )
		{
			jQuery('select[name=zone_id] option[value="' + data.id_zone + '"]').prop('selected', true);
			noAjaxCall = true;
			jQuery('select[name=zone_id]').change();
		}

		jQuery('.column.city').html(data.cities);
		jQuery("select, input").form();
	})
});

// Obtenemos el cp seleccionado para autorrelenarlo
jQuery(".column.city").on("change", "select", function()
{
	city = jQuery(this).find('option:selected').text();
	postcode = city.match( /\[(.*?)\]/ );
	jQuery('input[name=postcode]').val( postcode[1] )
}); 