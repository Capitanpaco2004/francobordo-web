<?php
/*
$Id: export.php, version 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2007 Vaisonet

Released under the GNU General Public License
*/

  $comp = array("LeGuide normalisé");
  
  $header = "Content-type: text/plain";

  $head = "categorie\tidentifiant_unique\ttitre\tprix\tURL_produit\tURL_image\tdescription\treference_modele\tlivraison\tD3E\tmarque\tprix_barre\tdevise\toccasion\n";

  $output .= $cat_info[$products['categories_id']]['name'] ."\t";
  $output .= $product_num ."\t";
  $output .= netoyage_html($products['products_name'], 80) . "\t";
  $output .= $regular_price ."\t";
  $output .= tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) . $libre . "\t"; 
  $output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] ."\t";
  $output .= netoyage_html($products['products_description'], 250) ."\t";
  $output .= $products['products_model'] . "\t";
  $output .= "Voir site\t";
  $output .= "0\t";
  $output .= $products['manufacturers_name'] . "\t";
  $output .= $discount_price . "\t";  
  $output .= "EUR\t";
  $output .= "0\n";
    
  $foot = '';

?>
