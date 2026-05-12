<?php

use util\event;

$auxProducts = array();
$sResult     = '';

if (!isset($_GET['type']) || $_GET['type'] != 'json') {
	// Imagen / vídeo / descripción de la landing (lo de arriba lo dejo igual que lo tenías)
	echo '<img src="' . DIR_WS_IMAGES . 'landings/' . $aLanding['landing_image'] . '" alt="' . $aLanding['landing_title'] . '" width="100%" />';

	if ($aLanding['landing_video'] != '') {
		echo '<iframe width="100%" height="315" src="' . preg_replace('/(watch\?v\=)/i', 'embed/', $aLanding['landing_video']) . '?rel=0&autoplay=1" frameborder="0" allowfullscreen style="margin-top: 10px; margin-bottom: 10px;"></iframe>';
	}

	echo '<div style="color: rgb(119, 119, 119); font-size: 13px; text-align: justify; margin-bottom: 40px; margin-top: 20px;">' . $aLanding['landing_description'] . '</div>';
}
if ($nProductosTotal == 0) {

	if (!isset($_GET['type']) || $_GET['type'] != 'json') {
		echo '<div class="mensaje">No existen productos que correspondan con el filtro seleccionado.</div>';
	} else {
		// Para JSON sin productos devolvemos algo mínimo
		echo json_encode(array(
							 'next_data_url' => '',
							 'prev_data_url' => '',
							 'current_url'   => tep_href_link(basename($PHP_SELF), tep_get_all_get_params(array('type', 'info', 'x', 'y'))),
							 'next_url'      => '',
							 'prev_url'      => '',
							 'response'      => ''
						 ), JSON_FORCE_OBJECT);
	}

} else {

	// Solo metemos filtros y contenedor cuando NO es JSON
	if (!isset($_GET['type']) || $_GET['type'] != 'json') {
		echo _getFiltro();
		include(DIR_WS_MODULES . 'products_filter.php');

		echo '<div class="web-cntd prdt-cntd">';
	}

	// Igual que en categories.php, construimos $sResult
	$sResult .= '<div class="contentScroll ax rows" data-url="'
		. tep_href_link(basename($PHP_SELF), '' . tep_get_all_get_params(array('type', 'info', 'x', 'y')))
		. '" data-pagination="'
		. htmlentities($aPaginador->display_links(99999, tep_get_all_get_params(array('page', 'type', 'info', 'x', 'y'))))
		. '">';

	while ($aProducto = eachProducts()) {
		$auxProducts[] = $aProducto;
		$sResult      .= _product();
	}

	$sResult .= '</div>';

	if (!isset($_GET['type']) || $_GET['type'] != 'json') {

		// Versión HTML normal
		echo $sResult . '
            <div class="PageNav" style="display: none;">
                <div class="BX Row HdSm">
                    <div class="NumPro">
                        <strong>' . $aPaginador->number_of_rows . '</strong> ' . TEXT_RESULTADOS . ' <strong>' . $sTitular . '</strong>
                    </div>
                </div>
                <div class="Nav">'
			. $aPaginador->display_links(MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params(array('page', 'info', 'x', 'y')))
			. '</div>
            </div>';

		echo '</div>'; // cierre .web-cntd prdt-cntd

	} else {

		// Versión JSON para scroll infinito (igual que categories.php)
		echo json_encode(array(
							 'next_data_url' => ($sNextUrl != '' ? preg_replace('/https\:\/\/www\.francobordo\.com/i', '', $sNextUrl) : ''),
							 'prev_data_url' => ($sPrevUrl != '' ? preg_replace('/https\:\/\/www\.francobordo\.com/i', '', $sPrevUrl) : ''),
							 'current_url'   => tep_href_link(basename($PHP_SELF), '' . tep_get_all_get_params(array('type', 'info', 'x', 'y'))),
							 'next_url'      => preg_replace('/\&type\=json/i', '', $sNextUrl),
							 'prev_url'      => preg_replace('/\&type\=json/i', '', $sPrevUrl),
							 'response'      => preg_replace('/https\:\/\/www\.francobordo\.com/i', '', $sResult)
						 ), JSON_FORCE_OBJECT);
	}
}

if (!isset($_GET['type']) || $_GET['type'] != 'json') {
	event::getInstance()->execute('after_products_listing', array($auxProducts));
}
?>
