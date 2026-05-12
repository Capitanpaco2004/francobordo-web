<?php
	function getInfoGroupTitleById( $gID )
	{
		//Obtenemos el título del grupos de información
		$aTitleGroup = tep_db_query( 'SELECT information_group_title FROM ' . TABLE_INFORMATION_GROUP . ' WHERE information_group_id = ' . (int)$gID );
		$aTitleGroup = tep_db_fetch_array( $aTitleGroup );

		return $aTitleGroup['information_group_title'];
	}
?>
