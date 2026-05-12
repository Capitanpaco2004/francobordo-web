<?php

  $comp = array("HTP2P");
  
  $header = 'Content-Disposition: attachment; filename="htp2p.csv"';

  $head = 'Ref Produit|Ref Unique|Designation|Fabricant|Categorie|Prix de vente|Quantité|URL Photo|Description courte|Description Longue' . "\n";

  $output .= $products['products_model'] . '|'; 
  $output .= $products['products_id'] . '|';
  $output .= $products['products_name'] . '|';
  $output .= $products['manufacturers_name'] . '|';
  $output .= $cat_info[$products['categories_id']]['name'] .'|';
  $output .= $regular_price . '|';
  $output .= $products['products_quantity'] . '|';
  $output .= HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .'|';
  $output .= netoyage_html($products['products_name'], 80) . ' : ' . netoyage_html($products['products_description'], 160) . '|';
  $output .= $products['products_description'] . '|';
  $output .= "\n";
      
  $foot = '';
?>
