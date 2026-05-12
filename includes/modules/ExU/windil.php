<?php
/*
$Id: export.php, version 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel module Windil par REGNE

Copyright © 2007 Vaisonet

Released under the GNU General Public License
*/

$comp = array("Windill");

$header = "Content-type: text/plain";

$head = "code_client\tidentifiant_unique\tcategorie\tean\tisbn\ttitre\tdescription\tURL_image\tURL_image2\tURL_image3\tURL_image4\tURL_image5\tURL_image6\tquantite\tpoids\tprix_barre\tprixttc\n";

$output .= "XXXXXX\t"; //remplacer les XXXX par votre code client windil
$output .= $product_num ."\t";
$output .= $products['windill_cat'] . "\t";
$output .= "\t";
$output .= "\t";
$output .= netoyage_html($products['products_name'], 60) . "\t";
$output .= netoyage_html($products['products_description'], 3000) ."\t";
$output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] ."\t";
$output .= "\t";
$output .= "\t";
$output .= "\t";
$output .= "\t";
$output .= "\t";
$output .= $products['products_quantity']."\t";
$output .= $products['products_weight']."\t";
if(!empty($discount_price)){$discount_price=round($discount_price, 2);}
$output .= $discount_price . "\t";
$output .= number_format($regular_price, 2,'.','') ."\n";

$foot = '';

?>
