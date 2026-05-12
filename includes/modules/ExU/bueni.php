<?php
/*
$Id: export.php, 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2007 Vaisonet

Released under the GNU General Public License
*/ 
  $comp = array("bueni");
  
  $header = 'Content-Type: text/xml';

  $head = '<?xml version="1.0" encoding="ISO-8859-1"?>' .chr(10) . '<catalogo lang="es" date="'.  date('Y-m-d H:i'). '" GMT="+1" version="2.0">'.chr(10); 
  $output .= ' <ARTICULO>'."\n";
  $output .= '  <Nombre_Producto><![CDATA['. $products['products_name'] .']]></Nombre_Producto>'.chr(10);
  $output .= '  <Producto_URL><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) .']]></Producto_URL>'.chr(10);

  $output .= '<Precio>'. $regular_price .'</Precio>'.chr(10);
  $output .= '<Precio_Anterior><![CDATA['. ($products['precio_inicial'])*1.18 .']]></Precio_Anterior>'.chr(10);
   $output .= '  <ImageURL><![CDATA['. HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .']]></ImageURL>'.chr(10);
   $output .= '<Descripcion><![CDATA['. substr(strip_tags(str_replace(array('<BR>','<br>'), "</P>\n<P>",html_entity_decode($products['products_description'] ?? ''))),0,4000) .'...]]></Descripcion>'.chr(10);
  $output .= '  <Categoria><![CDATA['. $products['categories_name'] .']]></Categoria>'.chr(10);
  $output .= '  <Shipping_cost>'. $products['shipping'].'</Shipping_cost>'.chr(10);
    $output .= '</ARTICULO>';
  $foot = '</catalogo>';
?>
