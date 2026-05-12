<?php
	//Declaramos la variable vacía
	$info_groups = '';

	// Query para consultar los grupos de información generados
	$information_groups_query = tep_db_query("select information_group_id as igID, information_group_title as igTitle from " . TABLE_INFORMATION_GROUP . " where visible = '1' order by sort_order");

	//Creamos los enlaces de todos los grupos
	while( $information_groups = tep_db_fetch_array($information_groups_query) )
		echo '<a href="' . tep_href_link(FILENAME_INFORMATION_MANAGER, 'gID=' . $information_groups['igID']) . '"><i class="bullet"></i> ' . $information_groups['igTitle'] . '</a>';
?>