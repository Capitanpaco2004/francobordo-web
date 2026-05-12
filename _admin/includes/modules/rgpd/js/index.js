(function($)
{
	$(document).ready(function()
	{
		$(".rgpd-confirm").click(function(e)
		{
			e.stopPropagation();
			
			if( confirm( "¿Estas seguro de ejecutar la herramienta?" ) )
			{
				loaddingShow_oe();
				return true;
			}
			
			return false;
		});
	});
})(jQuery);