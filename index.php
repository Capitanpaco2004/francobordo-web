<?php
include( 'includes/application_top.php' );

// Movidas estas funciones por Victor (estaban en application_top.php y petó con el cron de ebay) //
tep_expire_featured();
tep_expire_featured_products();

include( DIR_WS_LANGUAGES . $language . '/' . FILENAME_DEFAULT );
include(DIR_THEME. 'html/header.php');
include(DIR_THEME. 'html/column_left.php');

// Theme
include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

include( DIR_THEME. 'html/column_right.php' );
include( DIR_THEME. 'html/footer.php' );
include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>