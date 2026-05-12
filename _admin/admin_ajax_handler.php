<?php
require_once ( 'includes/application_top.php' );

if ( !$_REQUEST ) {
	echo 'Huh. ';
	exit();
}

switch ( $_REQUEST['action'] ) {

	case 'sort_categories':
		if ( is_array($_REQUEST['table-cat']) ) {
			$i = 0;
			foreach ( $_REQUEST['table-cat'] as $id=>$cat_id ) {
				if($cat_id != '') {
					$update_categories_sql = "
						UPDATE categories
						SET sort_order = '$i'
						WHERE categories_id = '$cat_id'
						LIMIT 1";
					tep_db_query($update_categories_sql);
					$i++;
				}

			}
			echo "<strong>Saved....</strong>";
		}
	break;
}
?>