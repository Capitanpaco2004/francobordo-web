<?php
/*
$Id: export.php, 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2007 Vaisonet

Released under the GNU General Public License
*/ 
  $comp = array("Ciao");
  
  $header = 'Content-Type: text/xml';

  $head = '<?xml version="1.0" encoding="ISO-8859-1"?>' .chr(10) . '<catalogue lang="SP" date="'.  date('Y-m-d H:i'). '" GMT="+1" version="2.0">'.chr(10); 
  
  $output .= ' <offer place="'. $products['products_id'] .'">'."\n";
  $output .= '  <Product_Name><![CDATA['. $products['products_name'] .']]></Product_Name>'.chr(10);
  $output .= '  <Brand><![CDATA['.  $products['manufacturers_name'] .']]></Brand>'.chr(10);
  $output .= '  <Category><![CDATA['. $products['categories_name'] .']]></Category>'.chr(10);
  $output .= '  <Description><![CDATA['. substr(strip_tags(str_replace(array('<BR>','<br>'), "</P>\n<P>",html_entity_decode($products['products_description'] ?? ''))),0,245) .'...]]></Description>'.chr(10);
  $output .= '  <Prices>'. $regular_price .'</Prices>'.chr(10);
  $output .= '  <Deeplink><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']).']]></Deeplink>'.chr(10);
  $output .= '  <ImageURL><![CDATA['. HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .']]></ImageURL>'.chr(10);
  $output .= '  <Shipping_cost>'. $products['shipping'].'</Shipping_cost>'.chr(10);
  $output .= '  <Model_number>'.$products['products_model'].'</Model_number>'.chr(10);
  $output .= '  <ean13>'.$products['products_barcode'].'</ean13>'.chr(10);
  $output .= '  <availability>'.$products['availabity'].'</availability>'.chr(10);
  $output .= '</offer>';
  
  $foot = '</catalogue>';
?>
