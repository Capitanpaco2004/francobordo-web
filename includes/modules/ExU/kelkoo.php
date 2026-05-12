<?php
/*
$Id: export.php, version 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2007 Vaisonet

Released under the GNU General Public License
*/

// les promotions sont elles 1->solde,2->autre promotions
  
  $comp = array("Kelkoo version XML");
  
  $header = 'Content-Type: text/xml';

  $head = '<?xml version="1.0" encoding="ISO-8859-1"?>' .chr(10) . '<catalogue lang="SP" date="'.  date('Y-m-d H:i'). '" GMT="+1" version="2.0">'.chr(10); 
  
  $output .= '<product place="'.$product_num.'">'."\n";
  $output .= '<merchant_category><![CDATA['.$cat_info[$products['categories_id']]['name'] . ']]></merchant_category>'.chr(10);
  $output .= '<offer_id><![CDATA['. $products['products_id'] .']]></offer_id>'.chr(10);
  $output .= '<name><![CDATA['. $products['products_name'] .']]></name>'.chr(10);
  $output .= '<description><![CDATA['. netoyage_html($products['products_description'], 245) .']]></description>'.chr(10);
  $output .= '<regular_price currency="EUR">'. $regular_price .'</regular_price>'.chr(10);
  //$output .= '<product_url><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) .']]></product_url>'.chr(10);
  $output .= '<product_url><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) . $libre .']]></product_url>'.chr(10);
  $output .= '<image_url><![CDATA['. HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .']]></image_url>'.chr(10);
  $output .= '<discount_price currency="EUR">'. $discount_price .'</discount_price>'.chr(10);
  $output .= '<price_discounted_from><![CDATA['.substr($special_result['specials_date_added'] ?? '',0,16).']]></price_discounted_from>'.chr(10);
  $output .= '<price_discounted_until><![CDATA['.substr($special_result['expires_date'] ?? '',0,16).']]></price_discounted_until>'.chr(10);
  $output .= '<delivery currency="EUR">FR;-1;</delivery>'.chr(10);;
  $output .= '<brand><![CDATA['.$products['manufacturers_name'].']]></brand>'.chr(10);
  $output .= '<model_number><![CDATA['. $products['products_model'] .']]></model_number>'.chr(10);
  $output .= '<ean13><![CDATA['. $products['products_barcode'] .']]></ean13>'.chr(10);
  $output .= '<used_condition><![CDATA[]]></used_condition>'.chr(10);//ne doit pas d�passer 25 caract�res et doit �tre dans la langue du catalogue
  $output .= '<update_date><![CDATA['.substr($products['products_last_modified'] ?? '',0,16).']]></update_date>'.chr(10);
  $output .= '<offer_valid_from><![CDATA['.substr($products['products_date_added'] ?? '',0,16).']]></offer_valid_from>'.chr(10);
  $output .= '<offer_valid_until><![CDATA['.substr($products['products_date_available'] ?? '',0,16).']]></offer_valid_until>'.chr(10);
  $output .= '<weight unit="kg">'.$products['products_weight'].'</weight>'.chr(10);
  $output .= '<D3E>'. $ecotax_montant .'</D3E>'.chr(10);
  $output .= '</product>';
  
  $foot = '</catalogue>';
?>
