<?php
	include( 'includes/application_top.php' );
	include( DIR_WS_LANGUAGES . $language . '/' . basename(__FILE__) );

	// Variables
	$nPrecioMax = getMaxPriceProduct(true);
	
	// Breadcrumb
	$breadcrumb->add( ADVANCED_SEARCH_BREADCRUMB, tep_href_link( basename(__FILE__) ) );

	// Cabecera y columna
	require(DIR_THEME. 'html/header.php');
	require(DIR_THEME. 'html/column_left.php');

	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );
?>
<script language="javascript" src="includes/general.js"></script>
<script language="javascript"><!--
	function check_form()
	{
		var error_message = "<?php echo JS_ERROR; ?>";
		var error_found = false;
		var error_field;
		var keywords = document.advanced_search.buscar.value;
		var pfrom = document.advanced_search.precio_desde.value;
		var pto = document.advanced_search.precio_hasta.value;
		var pfrom_float = 0;
		var pto_float = 0;
	
		if( ((keywords == '') || (keywords.length < 4)) && ((pfrom == '') || (pfrom.length < 1)) && ((pto == '') || (pto.length < 1)) )
		{
			error_message = error_message + "* <?php echo ERROR_AT_LEAST_ONE_INPUT; ?>\n";
			error_field = document.advanced_search.buscar;
			error_found = true;
		}

		if( pfrom.length > 0 )
		{
			pfrom_float = parseFloat(pfrom);
			if( isNaN(pfrom_float) )
			{
				error_message = error_message + "* <?php echo ERROR_PRICE_FROM_MUST_BE_NUM; ?>\n";
				error_field = document.advanced_search.precio_desde;
				error_found = true;
			}
		}

		if( pto.length > 0 )
		{
			pto_float = parseFloat(pto);
			
			if( isNaN( pto_float ) )
			{
				error_message = error_message + "* <?php echo ERROR_PRICE_TO_MUST_BE_NUM; ?>\n";
				error_field = document.advanced_search.precio_hasta;
				error_found = true;
			}
		}

		if( (pfrom.length > 0) && (pto.length > 0) )
		{
			if( (!isNaN(pfrom_float)) && (!isNaN(pto_float)) && (pto_float < pfrom_float) )
			{
				error_message = error_message + "* <?php echo ERROR_PRICE_TO_LESS_THAN_PRICE_FROM; ?>\n";
				error_field = document.advanced_search.precio_hasta;
				error_found = true;
			}
		}

		if( error_found == true )
		{
			alert(error_message);
			error_field.focus();
			return false;
		}
	  
		return true;
	}
//--></script>

<?php
	// Pie y columna
	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>