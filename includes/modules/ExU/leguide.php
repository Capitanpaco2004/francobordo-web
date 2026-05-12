<?php
/*
$Id: export.php, version 1.3 Mardi 18 Mars 2008 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2008 Vaisonet

Released under the GNU General Public License
*/

  $comp = array("LeGuide XML", "Twenga", "Icomparateur", "C-cher.com");
  
// les promotions sont elles 1->solde,2->autre promotions
  
  $header = 'Content-Type: text/xml';

  $head = '<?xml version="1.0" encoding="ISO-8859-1"?>' .chr(10) . '<catalogue lang="'.$lang_export.'" date="'.  date('Y-m-d H:i'). '" GMT="+1" version="2.0">'.chr(10); 
  
  $output .= '<product>'."\n";
  $output .= '<product_url><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) . $libre .']]></product_url>'.chr(10);
  $output .= '<designation><![CDATA['. $products['products_name'] .']]></designation>'.chr(10);
  $output .= '<price>'. $regular_price .'</price>'.chr(10);
  $output .= '<category><![CDATA['.$cat_info[$products['categories_id']]['name'] . ']]></category>'.chr(10);
  $output .= '<image_url><![CDATA['. HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .']]></image_url>'.chr(10);
  $output .= '<description><![CDATA['. substr(strip_tags(str_replace(array('<BR>','<br>'), "</P>\n<P>",html_entity_decode($products['products_description'] ?? ''))),0,245) .'...]]></description>'.chr(10);
  $output .= '<brand><![CDATA['.$products['manufacturers_name'].']]></brand>'.chr(10);
  $output .= '<merchant_id><![CDATA['. $products['products_id'] .']]></merchant_id>'.chr(10);
  $output .= '<manufacturer_id>'.$products['products_model'] . '</manufacturer_id>'.chr(10);
  $output .= '<shipping_cost>'. $products['shipping'] .'</shipping_cost>'.chr(10);
  $output .= '<in_stock>'. $products['in_stock'] .'</in_stock>'.chr(10);
  $output .= '<stock_detail>'. $products['products_quantity'] .'</stock_detail>'.chr(10);
  $output .= '<condition>'. 0 .'</condition>'.chr(10);
  $output .= '<isbn>'. $products['isbn'] .'</isbn>'.chr(10);
  $output .= '<upc_ean>' . $products['products_barcode'] .'</upc_ean>'.chr(10);
  $output .= '<product_type>'. 1 .'</product_type>'.chr(10);
  $output .= '</product>';
  
  $foot = '</catalogue>';
?>
