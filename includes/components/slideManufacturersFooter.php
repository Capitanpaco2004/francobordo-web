<?php
	// Variables
	$nLimit = 30;

	// Consultamos los fabricantes
	$aDatos = tep_db_query( 'SELECT manufacturers_id, manufacturers_name, manufacturers_image
							 FROM manufacturers 
							 WHERE manufacturers_status = 1 AND
							 manufacturers_image != ""
							 ORDER BY rand(), orden' . ($nLimit > 0 ? ' limit ' . $nLimit : '') );

	if( tep_db_num_rows( $aDatos ) > 0 )
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>