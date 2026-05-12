<?php
$storeId = tep_db_prepare_input($_GET['sId']);
// Variables
$sSubtitle = 'La informacion de los pedidos son los que estaban registrados en el momento que se hizo el cambio, teniendo en cuenta un periodo desde la fecha hasta el año anterior.';




// Sql
$sSql = 'SELECT cp.*, c.customers_firstname, c.customers_lastname FROM customers_profesionals_removed_log cp LEFT JOIN customers c ON cp.customer_id = c.customers_id ';

// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

// Sql para el count
$sSqlCount = 'SELECT COUNT(table_aux.log_id) as total FROM (' . $sSql . ') as table_aux';

// Datos y paginacion
$aRowsSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
$aRows = tep_db_query($sSql);


// Modulo
$sHtmlModule = includeTemplate($sPathTemplate . '/log.php');
