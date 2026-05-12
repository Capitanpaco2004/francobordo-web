<?php
/*
$Id: export.php, version 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2007 Vaisonet

Released under the GNU General Public License

Added by Emmanuel Pays 12/11/2008
Email: emmanuel@keldelice.com

File format export for Keldelice.com

*/

/*
A MODIFIER
*/

// Saisissez le nom de votre société saisi sur Keldelice (apparait sur votre fiche professionnel Keldelice)
	$ProKeldelice = "votre-societe";

/*
Ajouter vos catégories et faite le rapprochement comme ci-dessous.
Vous trouverez la table de rapprochement des catégories à l'adresse suivante : http://blog.keldelice.com/categories_keldelice.pdf
*/

	$kelCat["Les Plats Cuisinés"] = "C0610";
	$kelCat["Les Confitures"] = "C022";
	$kelCat["Les Fromages"] = "C0136";
	$kelCat["Les Plateaux de Fromages"] = "C0136";
	$kelCat["Munster"] = "C1362";
	$kelCat["Fromage Hansi"] = "C1362";

/*
A NE PAS MODIFIER
*/
	
	$shipping_query = tep_db_query('SELECT `configuration_value`
	FROM '.TABLE_CONFIGURATION.'
	WHERE `configuration_title` LIKE \'Shipping Cost\'');
	$shipping_cost = tep_db_fetch_array($shipping_query);

  $comp = array("Keldelice version XML");
  
  $header = 'Content-Type: text/xml';

  $head = '<?xml version="1.0" encoding="ISO-8859-1"?>';
	$head .= '<catalog>';
	$head .= '<organization>'.mb_convert_encoding($ProKeldelice ?? '', 'ISO-8859-1', 'UTF-8').'</organization>'."\n";
	
	$category = $cat_info[$products['categories_id']]['name'];
	if(empty($category)) {
		$category = "Empty-cat";
	} else {
		if(isset($kelCat[$category])) {
			$category = $kelCat[$category];
		}
	}

  $output .= '<product>'."\n";
  	$output .= '<title><![CDATA['. $products['products_name'] .']]></title>'.chr(10);
	  $output .= '<description><![CDATA['. netoyage_html($products['products_description'], 245) .']]></description>'.chr(10);
		$output .= '<category>'.$category.'</category>'.chr(10);
		$output .= '<price>'.$regular_price.'</price>'.chr(10);
	  $output .= '<product_url><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) . $libre .']]></product_url>'.chr(10);
	  $output .= '<image_url_1><![CDATA['. HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .']]></image_url_1>'.chr(10);
	  $output .= '<image_url_2></image_url_2>'.chr(10);
	  $output .= '<image_url_3></image_url_3>'.chr(10);
	  $output .= '<image_url_4></image_url_4>'.chr(10);
	  $output .= '<image_url_5></image_url_5>'.chr(10);
		$output .= '<sku>'.$product_num.'</sku>'.chr(10);
		$output .= '<manufacturer><![CDATA['.$products['manufacturers_name'].']]></manufacturer>'.chr(10);
	  $output .= '<ean13>'.$products['products_barcode'].'</ean13>'.chr(10);
	  $output .= '<weight>'.$products['products_weight'].'</weight>'.chr(10);
	  $output .= '<shipping_cost>'.$shipping_cost['configuration_value'].'</shipping_cost>'.chr(10);
  $output .= '</product>'."\n";
  
  $foot = '</catalog>';
?>