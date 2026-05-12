<?php
/*
$Id: export.php, version 1.0 Mercredi 12 F�vrier 2008 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2008 Vaisonet

Released under the GNU General Public License
*/

  $comp = array("Priceminister");
  
  $header = 'Content-Disposition: attachment; filename="priceminister.csv"';

  $head = '"R�f�rence Produit";	"Votre r�f�rence";	"Prix de vente";	"Quantit�";	"Qualit�";	"Commentaire annonce";	"Commentaire priv� annonce";	"Fabricant";' . "\n";

  $products = $products ?? [];
  $output = $output ?? '';
  $regular_price = $regular_price ?? 0;

  $output .= '"' . ($products['products_model'] ?? '') . '";';
  $output .= '"' . ($products['products_id'] ?? '') . '";';
  $output .= $regular_price . ';';
  $output .= ($products['products_quantity'] ?? '') . ';';
  $output .= '"n";';
  $output .= '"' . netoyage_html($products['products_name'] ?? '', 80) . ' : ' . netoyage_html($products['products_description'] ?? '', 160) . '";';
  $output .= '"";';
  $output .= '"' . ($products['manufacturers_name'] ?? '') . '";' . "\n";
      
  $foot = '';
?>
