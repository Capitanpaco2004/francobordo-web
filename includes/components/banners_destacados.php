<?php
	$aBanners = tep_db_query( 'SELECT * FROM banners_destacados where estado = 1 ORDER BY orden asc' );

	if( tep_db_num_rows( $aBanners ) > 0 )
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>