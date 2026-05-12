// Lanzar formulario
$("#form-theme").submit(function()
{
	// Variables
	var dmForm = $(this);

	// Comprobamos si existe boletin a cargar
	if( $("#form-theme #boletin").val() == null )
	{
		alert( "No existe boletin para cargar" );

		// Ocultamos
		$("#lgbox").fadeOut();
		$("#lgbox-load").fadeOut();
		$("#lgbox-bg").fadeOut();

		return false;
	}
	
	// Ocultamos y lanzamos ajax
	$("#lgbox").fadeOut(400, function()
	{
		$.ajax(
		{
			type: "POST",
			url: dmForm.attr("action"),
			data: dmForm.serialize(),
			success: function(sHtml)
			{
				// Obtenemos json
				var aData = $.parseJSON(sHtml);

				// Html del boletin
				var sHtml = aData["html"];
				
				// Configuracion
				var aConfig = $.parseJSON(aData["config"]);

				bEmpezado = true;
				bNuevo = false;
				bEditado = false;
				
				sThemeBoletin = aConfig["theme"];
				sNombreBoletin = aConfig["nombre_boletin"];				
				
				// Ocultamos
				$("#lgbox-load").fadeOut();
				$("#lgbox-bg").fadeOut();

				var dmAux = $("<div>").html(sHtml);

				// Recargamos cache image
				$(dmAux).find("img").each(function(nIndex, dmImg)
				{
					if( !$(dmImg).attr("data-theme-64") )
						$(dmImg).attr("src", $(dmImg).attr("src") + '?r=' + uniqid() );
				});
				
				// Pintamos html
				$("#web").html( '<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">' + $(dmAux.find("table")[0]).html() + '</table>' );

				// Recargamos eventos
				initBotonesControl();
				eventSortableControl();
				eventSortableProductos();
			}
		});
	});

    return false;
});

function uniqid (prefix, more_entropy) {
  // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +    revised by: Kankrelune (http://www.webfaktory.info/)
  // %        note 1: Uses an internal counter (in php_js global) to avoid collision
  // *     example 1: uniqid();
  // *     returns 1: 'a30285b160c14'
  // *     example 2: uniqid('foo');
  // *     returns 2: 'fooa30285b1cd361'
  // *     example 3: uniqid('bar', true);
  // *     returns 3: 'bara20285b23dfd1.31879087'
  if (typeof prefix === 'undefined') {
    prefix = "";
  }

  var retId;
  var formatSeed = function (seed, reqWidth) {
    seed = parseInt(seed, 10).toString(16); // to hex str
    if (reqWidth < seed.length) { // so long we split
      return seed.slice(seed.length - reqWidth);
    }
    if (reqWidth > seed.length) { // so short we pad
      return Array(1 + (reqWidth - seed.length)).join('0') + seed;
    }
    return seed;
  };

  // BEGIN REDUNDANT
  if (!this.php_js) {
    this.php_js = {};
  }
  // END REDUNDANT
  if (!this.php_js.uniqidSeed) { // init seed with big random int
    this.php_js.uniqidSeed = Math.floor(Math.random() * 0x75bcd15);
  }
  this.php_js.uniqidSeed++;

  retId = prefix; // start with prefix, add current milliseconds hex string
  retId += formatSeed(parseInt(new Date().getTime() / 1000, 10), 8);
  retId += formatSeed(this.php_js.uniqidSeed, 5); // add seed hex string
  if (more_entropy) {
    // for more entropy we add a float lower to 10
    retId += (Math.random() * 10).toFixed(8).toString();
  }

  return retId;
}
