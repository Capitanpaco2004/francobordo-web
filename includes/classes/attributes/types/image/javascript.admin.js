(function($)
{
	// Load Jquery
	$(document).ready(function()
	{
		// Evento para el input de subir imagen
		$("#attr-upld-imge-inpt").change(function(e)
		{
			// Archivo
			var file = e.target.files[0];
			
			// Solo imagenes
			if( file.type.match('image.*') )
			{
				// Leer archivo
				var reader = new FileReader();

				// Cargamos la informacion
				reader.onload = (function(theFile) 
				{		
					return function(e)
					{
						var sHtml = "";

						// Pintamos los elementos
						$("#attr-upld-imge").html( ['<img width="35" height="35" src="', e.target.result, '" title="', escape(theFile.name), '"/>'].join('') );
						$("#attr-upld-imge-base").val(e.target.result);
						$("#attr-upld-imge-name").html(escape(theFile.name));
					};
				})(file);

				// Read in the image file as a data URL.
				reader.readAsDataURL(file);
			}
			else
				alert("Solo se permiten imagenes");
		});
		
		// Click eliminar imagen
		$("#attr-upld-imge-dlte").click(function(e)
		{
			if( confirm( "¿Deseas eliminar la imagen?" ) )
			{
				$("#attr-upld-imge").html("");
				$("#attr-upld-imge-base").val("");
				$("#attr-upld-imge-name").html("");
			}
			
			return false;
		});
			
		// Click a subir imagen
		$("#attr-upld-imge-bton").click(function(e)
		{
			// Detenemos evento
			e.stopPropagation();
			
			// Lanzamos evento para subir imagen
			$("#attr-upld-imge-inpt").trigger("click");			
			
			// Retornamos
			return false;
		});
	});
})(jQuery);