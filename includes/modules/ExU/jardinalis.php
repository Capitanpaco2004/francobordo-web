<?php
/*
$Id: export.php, 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2007 Vaisonet

Released under the GNU General Public License
*/

  $comp = array("Jardinalis");
  
  $header = "Content-type: application/vnd.ms-excel\nContent-Disposition: Attachment; filename=\"jardinalis.csv\"";

  $head = "";

  $output .= $cat_info[$products['categories_id']]['name'] .";";
  $output .= netoyage_html($products['products_name'], 80) .";";
  $output .= netoyage_html($products['products_description'], 255) .";";
  $output .= $regular_price .";";
  $output .= tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) ."$libre;";
  $output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .";";
  $output .= "0\n";
      
  $foot = '';
?>
