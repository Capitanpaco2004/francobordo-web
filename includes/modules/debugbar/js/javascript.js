// Redimensiona las ventanas
var debugBarSize = function()
{
	var nHeight = $(window).height();
	$("#debug_bar .debug_bar_cntd").css( "height", nHeight - 40 );
	$("#debug_bar .debug_bar_ovfl").css( "height", nHeight - 120 );
};

// Cerrar o abrir la barra de depuracion
$("#debug_bar .debug_bar_close").click(function()
{
	// Cerramos todo
	$("#debug_bar .debug_bar_tab").removeClass("open");
	$("body").removeClass( "debug_bar_scroll" );

	// Abrimos/cerramos debug
	$("#debug_bar").toggleClass("open");
});

// Cunado pulsamos algun boton de la barra de depuracion
$("#debug_bar .debug_bar_tab .debug_bar_bton").click(function()
{
	// Si esta abierto o cerrado el que estamos pulsando
	var bOpen = $(this).closest(".debug_bar_tab").hasClass("open");

	// Cerramos todo
	$("#debug_bar .debug_bar_tab").removeClass("open");
	$("body").removeClass( "debug_bar_scroll" );

	// Si estaba abierto no hacemos nada para mantenerlo cerrado
	if( !bOpen )
	{
		// Abrimos
		$(this).closest(".debug_bar_tab").toggleClass("open");

		// Ponemos el body sin scroll
		$("body").addClass( "debug_bar_scroll" );
	}
});

// Si hacemos doble click mostramos mas
$("#debug_bar .degub_bar_togg").dblclick(function()
{
	$(this).toggleClass("open");
});

// Como el phpinfo nos da problemas ya que muestra los tags html, body etc, hemos creado un iframe y en el pintamos el html de otra capa que tenemos oculta
$("#phpinfo-iframe").contents().find('html').html( $("#phpinfo").text() );

// Redimensionamos
debugBarSize();
$(window).resize( debugBarSize );

// Pintamos los codigos en colores
hljs.initHighlightingOnLoad();