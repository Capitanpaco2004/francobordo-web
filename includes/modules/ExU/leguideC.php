<?php
/*
$Id: export.php, version 1.3 Mardi 18 Mars 2008 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2008 Vaisonet

Released under the GNU General Public License
*/

  $comp = array("LeGuide commentaires produits");
  
  $header = "Content-type: text/plain";

  $head = "category\ttitle\treview_date\tgeneral_comment\treference_model\toffer_img\tbrand\tprice\tauthor_nickname\tmark\tmark_max\n";

  if ($products['reviews_rating'] > 0 AND $products['lngr'] == $languages_id){
  $output .= $cat_info[$products['categories_id']]['name'] ."\t";
  $output .= netoyage_html($products['products_name'], 80) . "\t";
  $output .= $products['review_date'] ."\t";
  $output .= '"' .netoyage_html($products['reviews_text'], 400) . '"' . "\t";
  $output .= $product_num ."\t";
  $output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] ."\t";
  $output .= $products['manufacturers_name'] . "\t";
  $output .= $regular_price ."\t";
  $output .= $products['customers_name'] ."\t";
  $output .= $products['reviews_rating'] ."\t";
  $output .= "5\n";}
      
  $foot = '';

?>
