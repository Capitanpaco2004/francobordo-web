<?php
include( 'includes/application_top.php' );

include( DIR_WS_LANGUAGES . $language . '/' . FILENAME_DEFAULT );
include(DIR_THEME. 'html/header.php');
include(DIR_THEME. 'html/column_left.php');

// Theme
include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

include( DIR_THEME. 'html/column_right.php' );
include( DIR_THEME. 'html/footer.php' );
include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>