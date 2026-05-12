<?php
/*
$Id: export.php, version 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2007 Vaisonet

Released under the GNU General Public License
*/

  $comp = array("Pricerunner");
  
  $header = 'Content-type: text/plain';
  $header2 = 'Content-Disposition: "inline; filename=pricerunner.txt"';

  $head = "Prix TTC\tFabricant\tSKU du Fabricant\tSKU\tEAN\tNom du produit\tCatégorie\tURL du produit\tCoût de livraison\tNiveau du stock\tVente départ\tVente fin\tAutre SKU\tURL de l’image produit\tDescription\n";

  $output .= $regular_price ."\t";
  $output .= $products['manufacturers_name'] ."\t";
  $output .= "N/A\t";
  $output .= $products['products_id'] ."\t";
  $output .= $products['products_barcode'] . "\t"; 
  $output .= netoyage_html($products['products_name'], 80) ."\t";
  $output .= $cat_info[$products['categories_id']]['name'] ."\t";
  $output .= tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) ."$libre\t";
  $output .= "N/A\t";
  $output .= "\t";
  $output .= "\t";
  $output .= "\t";
  $output .= "\t";  
  $output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] ."\t";
  $output .= netoyage_html($products['products_description'], 160) ."\n";
    
  $foot = '';
?>
