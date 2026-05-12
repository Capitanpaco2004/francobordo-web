<?php

// No indexar
header( 'X-Robots-Tag: noindex,nofollow' );

// Includes
require('includes/application_top.php');
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_MY_POINTS_HELP);

// Variables
$nPrice = tep_db_prepare_input( $_GET['price'] );

// Reemplazamos la coma
$nPrice = str_replace( ',', '.', $nPrice );
// Quitamos IVA
$nPrice = $nPrice / 1.21;

// Puntos obtenidos
$nPoints = (int)( $nPrice );

echo '<div id="ltbx-cnsl">';
$puntos =$currencies->format(tep_calc_shopping_pvalue(100 * POINTS_PER_AMOUNT_PURCHASE));
$valor_puntos=$currencies->format($nPoints * REDEEM_POINT_VALUE);
// Texto de puntos
echo preg_replace( '/\<em\>/i', '<em style="font-size: 16px; color: rgb(0, 135, 183); font-weight: bold;">',preg_replace( '/('.$puntos.')/i', $valor_puntos, preg_replace( '/(100)/i', $nPoints, preg_replace( '/(100\,00)/i', number_format( $nPrice, 2, ',', '.' ), TEXT_FAQ_3 ) ) ) );
echo '</div>';

?>