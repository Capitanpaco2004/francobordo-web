var bFormEdit = false;

// Funcion que se llamada cuando se va a editar
var editForm = function()
{
	// El estado cambia a edicion
	bFormEdit = true;
	
	// Añadimos el texto a editar
	$("#form-text #text").val( $(dmElementToJs).find("[data-theme-edit='true']").html() );
};

// Lanzar formulario
$("#form-text .bton").click(function()
{
	// Variables
	var dmForm = $("#form-text");
	
	dmForm.find("#text").val( tinyMCE.get('text').getContent() );

	// Si el texto esta vacio no hacemos nada
	if( dmForm.find("#text").val() == "" )
	{
		closeWindow();
		return false;
	}
	
	// Especificamos que se ha editado el boletin
	bEditado = true;
	
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
				// Ocultamos
				$("#lgbox-load").fadeOut();
				$("#lgbox-bg").fadeOut();

				// Si estamos editando
				if( bFormEdit )
				{
					$(dmElementToJs).find("[data-theme-edit='true']").html( sHtml );
				}
				else // Nuevo texto
				{
					var dmElmentBeforeInsert = $("#web").find("[data-theme='insert']");
					$( sHtml ).insertBefore( dmElmentBeforeInsert );
				}

				// Recargamos eventos
				initBotonesControl();
			}
		});
	});

    return false;
});

// Editor
setTimeout(function()
{
	tinymce.init({
		mode : "textareas",
		plugins: "table code fullscreen visualblocks",
		tools: "inserttable",
		height : 300
	});
}, 100);