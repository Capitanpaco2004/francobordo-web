<?php
	require_once ('includes/application_top.php');
	require_once( DIR_WS_FUNCTIONS . 'products_specifications.php' );
	require_once (DIR_WS_LANGUAGES . $language . '/' . FILENAME_COMPARISON);

	$sGetIds = tep_db_prepare_input( $_GET['ids'] ?? '' );
	$sGetIds = substr( (string)$sGetIds, 0, -1 );

	if( $sGetIds != '' )
		$_GET['comp'] = explode( '_', $sGetIds );
  
	if( $current_category_id == 0 )
	{
		tep_redirect( tep_href_link( FILENAME_DEFAULT ) );
		exit();
	}

    //Get the name for this category
    $title_query_raw = "select  categories_name
						from categories_description
						where categories_id = '" . ( int )$current_category_id . "' and  language_id = '" . (int)$languages_id . "'";

    $title_query = tep_db_query( $title_query_raw );
    $title_array = tep_db_fetch_array( $title_query );
    $heading_title = sprintf( HEADING_TITLE, $title_array['categories_name'] );
  
	// Set up the array of product IDs that the customer has selected (if any)
	$comp_array = array();
	if( isset ($_GET['comp']) && $_GET['comp'] != '') 
	{
		// Decode the URL-encoded names, including arrays
		$comp_array = tep_decode_recursive ($_GET['comp']);

		// Sanitize variables to prevent hacking
		$comp_array = tep_clean_get__recursive ($comp_array);
	}
  
	include(DIR_THEME. 'html/header.php');
	include(DIR_THEME. 'html/column_left.php');

	echo '<div class="pageHeading">' . $heading_title . '</div>';
    include( DIR_WS_MODULES . FILENAME_COMPARISON ); 

	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>