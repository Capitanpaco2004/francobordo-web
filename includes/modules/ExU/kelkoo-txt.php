<?php
/*
$Id: export.php, version 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2007 Vaisonet

Released under the GNU General Public License
*/
  
  $comp = array("Kelkoo version texte");
  
  $header = 'Content-type: text/plain';
  $header2 = 'Content-Disposition: "inline; filename=kelkoo.txt"';

  $head = "url\ttitle\tdescription\tprice\tofferid\timage\tcategory\n"; 
  
  $output .= tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) . $libre ."\t";
  $output .= netoyage_html($products['products_name'], 80) ."\t";
  $output .= netoyage_html($products['products_description'], 160) ."\t";
  $output .= $regular_price ."\t";
  $output .= $products['products_id'] ."\t";
  $output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] ."\t";
  $output .= $cat_info[$products['categories_id']]['name'] ."\n";
    
  $foot = '';
?>
