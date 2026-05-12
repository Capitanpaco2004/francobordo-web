<?php
// Tools
use util\tools as tools;

if ($sPostAction === 'history') {
    // Variables
    $sSubtitle = CONFIGURATION_HISTORY_TITLE;
    $aButtons = [
			[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
		];
    // Variables
    $aFiler = [ 'search' => '' ];
    $aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
    $sWhere = '';
    // Limpiamos variables get filter
    array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );
    // Where
    if ($aFiler['search'] !== '') {
        $sWhere .= ' where (LOWER(previous_setting) LIKE "%' . strtolower( $aFiler['search'] ) . '%") or (LOWER(new_setting) LIKE "%' . strtolower( $aFiler['search'] ) . '%") or (LOWER(change_title) LIKE "%' . strtolower( $aFiler['search'] ) . '%") or (LOWER(change_description) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
    }
    // Order by
    $sOrderby = $sGetOrderby == 'change_date' ? 'change_date ' . $sGetSort : '`change_date` DESC';
    // Sql
    $sSql = 'SELECT change_id, previous_setting, new_setting, change_date, change_title, change_description
					 FROM configuration_changes
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;
    // Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
    $sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );
    // Sql para el count
    $sSqlCount = 'SELECT COUNT(table_aux.change_id) as total FROM (' . $sSql . ') as table_aux';
    // Datos y paginacion
    $aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
    $aRows= tep_db_query( $sSql );
    // Modulo
    $sHtmlModule = includeTemplate( $sPathTemplate . '/history.php' );
}
?>
