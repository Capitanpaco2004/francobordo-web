<?php
    require('includes/application_top.php');

	if (isset( $_POST['action'] ) && $_POST['action'] == 'getCities' && (isset( $_POST['zone'] ) || isset( $_POST['cp'] )))
	{
		if( (int)$_POST['zone'] > 0 )
			ajax_get_cities_html( (int)$_POST['country'], tep_db_prepare_input( $_POST['zone'] ) );

		if( (int)$_POST['cp'] > 0 )
			ajax_get_cities_html( (int)$_POST['country'], false, tep_db_prepare_input( $_POST['cp'] ) );

		die();
	}

	include( DIR_WS_INCLUDES . 'functions/shortcodes.php');
	include( DIR_THEME_ROOT . 'functions/shortcodes.php' );

    // Added for information pages
    if( !isset($_GET['info_id']) || !tep_not_null($_GET['info_id']) || !is_numeric($_GET['info_id']) )
    {
        $title = ($languages_id == 3 ? 'Nuestra información' : 'Information');
		$classid = '';
        $breadcrumb->add($title, tep_href_link(FILENAME_INFORMATION, 'NONSSL'));
        $page_description = showInformationPages( array( 'SUBPAGES' => true, 'ALL' => true, 'HTML' => true, 'PADRE' => 0 ) );
    }
    else
    {
        $info_id = intval($_GET['info_id']);
        $information_query = tep_db_query("SELECT information_id, information_title, information_description FROM " . TABLE_INFORMATION . " WHERE visible='1' AND information_id='" . $info_id . "' and language_id='" . (int)$languages_id ."'");
        $information = tep_db_fetch_array($information_query);
		
		// Mostrar terminos generales
		$rgpd->showInformationTermsGeneral();
		
        $title = stripslashes((string)$information['information_title']);
		$classid = $information['information_id'];
        $page_description = stripslashes((string)$information['information_description']);

        // Added as noticed by infopages module
        if (!preg_match("/([\<])([^\>]{1,})*([\>])/i", $page_description))
            $page_description = str_replace("\r\n", "<br />\r\n", $page_description);

		$page_description = do_shortcode( $page_description );

        $breadcrumb->add($title, tep_href_link(FILENAME_INFORMATION, 'info_id=' . $_GET['info_id'], 'NONSSL'));
    }

    require(DIR_THEME. 'html/header.php');
    require(DIR_THEME. 'html/column_left.php');
    include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );
    require(DIR_THEME. 'html/column_right.php');
    require(DIR_THEME. 'html/footer.php');
    require(DIR_WS_INCLUDES . 'application_bottom.php');   
?>
