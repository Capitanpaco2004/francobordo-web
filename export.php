<?php
/*
$Id: export.php, version 1.3 Mardi 18 mars 2008 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2007 Vaisonet

Released under the GNU General Public License
*/

//////////////////////////
///    Configuration   ///
///  see lisezmoi.html ///
//////////////////////////
$verif = true;
$pass = 'Ciao0908';
define ('DISPLAY_PRICE_WITH_TAX', true);
$ean = true;
$ecotax = false;

// fonction de nettoyage des donn�es si pr�sence d'un �diteur html
function netoyage_html($CatList, $length) 
{
  $CatList = html_entity_decode ($CatList);
  $CatList = strip_tags ($CatList);
  $CatList = trim ($CatList);
  $CatList = strtolower ($CatList);
  $CatList = str_replace(chr(9),"",$CatList); 
  $CatList = str_replace(chr(10),"",$CatList);
  $CatList = str_replace(chr(13),"",$CatList);
  $CatList = preg_replace("[<(.*?)>]","",$CatList);
  if (strlen($CatList) > $length) {
    $CatList = substr($CatList, 0, $length-3) . "...";
  }
  return $CatList;  
}

// temps d'ex�cution infini
// ne fonctionne pas sur tous les serveurs, dans ce cas il y aura des timeouts
// et les fichiers seront incomplets. Mieux vaut alors opter pour un h�bergement plus performant.
set_time_limit(0);
require('includes/application_top.php');
$output = '';

// s�curisation des variables et v�rification

//La ligne ci-apr�s permet de passer des frais de port fixe dans l'url
//$port = (isset($_GET['port']) && tep_not_null($_GET['port'])) ? tep_db_prepare_input($_GET['port']) : "-1";

$language_code = (isset($_GET['language']) && tep_not_null($_GET['language'])) ? tep_db_prepare_input($_GET['language']) : DEFAULT_LANGUAGE;
$p = tep_db_prepare_input($_GET['p']);
$format = basename(tep_db_prepare_input($_GET['format']));
$cache = tep_db_prepare_input($_GET['cache']);
$fichier = tep_db_prepare_input($_GET['fichier']);
$libre = tep_db_prepare_input($_GET['libre']);
if ($_GET['rep'] == "1") $rep = 'export/secure/';
else $rep = 'export/';
 
//On v�rifie le code avant de lancer les requ�tes
if( ($verif == true and $p==$pass) OR $verif == false ) 
  {
  $included_categories_query = tep_db_query("SELECT c.categories_id, c.parent_id, cd.categories_name FROM " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd WHERE c.categories_id = cd.categories_id AND cd.language_id = FLOOR($languages_id)");
  $inc_cat = array();

  // Identification du nom de la cat�gorie, et l'id de la cat�gorie parent
  while ($included_categories = tep_db_fetch_array($included_categories_query)) {
  $inc_cat[] = array (
     'id' => $included_categories['categories_id'],
     'parent' => $included_categories['parent_id'],
     'name' => $included_categories['categories_name']);
  }

  $cat_info = array();
  for ($i=0; $i<sizeof($inc_cat); $i++)
    $cat_info[$inc_cat[$i]['id']] = array (
    'parent'=> $inc_cat[$i]['parent'],
    'name'  => $inc_cat[$i]['name'],
    'path'  => $inc_cat[$i]['id'],
    'link'  => '' );

  for ($i=0; $i<sizeof($inc_cat); $i++) {
  $cat_id = $inc_cat[$i]['id'];
  while ($cat_info[$cat_id]['parent'] != 0){
    $cat_info[$inc_cat[$i]['id']]['path'] = $cat_info[$cat_id]['parent'] . '_' . $cat_info[$inc_cat[$i]['id']]['path'];
    $cat_id = $cat_info[$cat_id]['parent'];
    }
  $link_array = preg_split('/_/', $cat_info[$inc_cat[$i]['id']] ['path']);
  for ($j=0; $j<sizeof($link_array); $j++) {
    $cat_info[$inc_cat[$i]['id']]['link'] .= '&nbsp;<a href="' . tep_href_link(FILENAME_DEFAULT, 'cPath=' . $cat_info[$link_array[$j]]['path']) . '"><nobr>' . $cat_info[$link_array[$j]]['name'] . '</nobr></a>&nbsp;&raquo;&nbsp;';
    }
  }

  // Requ�te identifiant les produits disponibles dans le catalogue
  $products_query = tep_db_query("SELECT p.*, 
   pd.products_name, pd.products_description,
   pc.categories_id, 
   pr.date_added as review_date, pr.customers_name, pr.reviews_rating,
   pt.reviews_text, pt.languages_id as lngr 
   FROM (" . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " pc)
   LEFT JOIN reviews as pr ON (p.products_id = pr.products_id)
   LEFT JOIN reviews_description as pt ON (pr.reviews_id = pt.reviews_id)
   WHERE p.products_id = pd.products_id 
   AND p.products_id = pc.products_id 
   AND p.products_status = 1 
   AND pd.language_id = FLOOR($languages_id)
   AND pc.categories_id IN (34, 64, 67, 117, 118, 119, 120, 121, 122, 123, 140, 141, 175, 388, 389, 421, 429, 463, 484, 486, 487, 488, 497, 498, 499, 504, 505, 506, 507, 509, 510, 511, 512, 514, 516, 517, 518, 519, 521, 522, 523, 524, 525, 526, 527, 529, 530, 531, 538, 539, 543, 560, 561, 562, 573, 587, 601, 645, 647, 648, 649, 650, 651, 656, 657, 658, 660, 661, 683, 684, 685, 686, 739, 740, 746, 750, 784, 785, 786, 787, 800, 801, 802, 803, 812)
   ORDER BY pc.categories_id, pd.products_name");

  $product_num = 0;

  while($products = tep_db_fetch_array($products_query)) {

  if (intval($products['manufacturers_id']) > 0) {
          $manufacturers_query = tep_db_query("SELECT manufacturers_name FROM " . TABLE_MANUFACTURERS . " WHERE manufacturers_id = " . $products['manufacturers_id']);
          $manufacturers_result = tep_db_fetch_array($manufacturers_query);
          $products['manufacturers_name'] = $manufacturers_result['manufacturers_name'];
 }
 if (intval($products['categories_id']) > 0) {
          $categories_query = tep_db_query("SELECT categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = " . $products['categories_id'] . " AND language_id= FLOOR($languages_id)" );
          $categories_result = tep_db_fetch_array($categories_query);
          $products['categories_name'] = $categories_result['categories_name'];
 }
 if ($products['products_quantity'] > 0) {
           $products['availabity'] = 'En Stock';
		   $products['in_stock'] = 'Y';
 } else {
          $products['availabity'] = 'De 3 a 10 D�as';
		  $products['in_stock'] = 'N';
		  }
  $special_query = tep_db_query("SELECT specials_new_products_price , expires_date , specials_date_added FROM " . TABLE_SPECIALS . " WHERE products_id = " . $products['products_id'] . " AND status = '1' limit 1");
  $special_result = tep_db_fetch_array($special_query);
  if ($special_result['specials_new_products_price'] > 0) $products['products_price'] = $special_result['specials_new_products_price'];

  $product_num++;

  //calcul des prix
  // la varaible $reduc permet de tester s'il y a une promo
  $price = tep_add_tax($products['products_price'], tep_get_tax_rate($products['products_tax_class_id']));
  if($special_result['specials_new_products_price'] == '' )   {
        $discount_price = '' ;
        $regular_price = $price;
        $reduc = false;
  }   else   {
        $discount_price = $special_result['specials_new_products_price'];
        $regular_price = $price;
        $reduc = true;
  }
   if ($products['products_weight'] < 50 and $regular_price > 250) {
           $products['shipping'] = 'Envio Gratis';
 } else {
        if  ($products['products_weight'] > 50) {  
		$products['shipping'] = 'Desde 12,90 Euros';
		  } else {
		  
  $products['shipping'] = 'Desde 4,90 Euros';
  }
  }
  // Test barcod mod
  if (!$ean) $products['products_barcode'] == "";
  // Test ecotax
  if ($ecotax)   $ecotax_montant = tep_get_ecotax_price_value($products['ecotax_rates_id']);
  else $ecotax_montant = 0;
   
  // On appelle le "plugin" d�finissant le format du fichier 
  include(DIR_WS_MODULES . 'ExU/' . $format);

  }

$content =   $head . $output . $foot;
//Soit on met en cache, soit on affiche le r�sulat
if ($cache != "true")
  {
  Header( $header );
  if ($header2) Header( $header2 );
  echo $content;
  }
else
  {
  $fp= fopen(DIR_FS_CATALOG . $rep . $fichier,"w");
  fputs($fp,"$content");
  fclose($fp);
  }  
}
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
