var sImagen64 = "";

$("#form-image .bton-vrde").click(function()
{
	// Si contenemos imagen
	if( sImagen64 != "" )
	{
		var sHtmlImagen = '<img style="display:block border: 0;" border="0" data-theme-64="true" src="' + sImagen64 + '"/>';
		
		// Comprobamo si contiene enlace
		if( $("#enlace").val() != "" )
			sHtmlImagen = '<a href="' + $("#enlace").val() + '">' + sHtmlImagen + '</a>';

		var sHtml = '<tr data-theme-url-delete="true" data-theme="control"><td valign="center" colspan="2" bgcolor="#FFFFFF" align="center">' + sHtmlImagen + '</td></tr>';

		var dmElmentBeforeInsert = $("#web").find("[data-theme='insert']");
		$( sHtml ).insertBefore( dmElmentBeforeInsert );
		
		// Recargamos eventos
		initBotonesControl();
		
		// Ocultamos
		$("#lgbox").fadeOut(400);
		$("#lgbox-load").fadeOut();
		$("#lgbox-bg").fadeOut();
	}
});

$("#file").change(function()
{
	readImage( this );
});

function readImage(input) 
{
    if ( input.files && input.files[0] ) 
	{
		if( input.files[0].type != "image/png" )
		{
			alert("La imagen introducida no es una imagen valida PNG");
			return;
		}
		else
		{
			var FR= new FileReader();
			FR.onload = function(e)
			{
				 sImagen64 = e.target.result;
			};

			FR.readAsDataURL( input.files[0] );
		}	
    }
}