<?php
/*
$Id: export.php, 1.2 Vendredi 5 Octobre 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright � 2007 Vaisonet

Released under the GNU General Public License
*/ 
  $comp = array("cdbarcos");
  
  $header = 'Content-Type: text/xml';

  $head = '<?xml version="1.0" encoding="ISO-8859-1"?>'.chr(10). '<BROKER>'.chr(10).'<ID_EMPRESA>'. '61783030091170545266695668704565'.'</ID_EMPRESA>'.chr(10). '<ACCESORIOS>'.chr(10);
  $output .= ' <ARTICULO>'."\n";
  $output .= ' <ID_ORIGINAL>'. $products['products_id'] .'</ID_ORIGINAL>'.chr(10);
  $output .= '<REFERENCIA>'.$products['products_model'].'</REFERENCIA>'.chr(10);
  $output .= '<TITULO><![CDATA['. $products['products_name'] .']]></TITULO>'.chr(10);
  $output .= '<DESCRIPCION><![CDATA['. substr(strip_tags(str_replace(array('<BR>','<br>'), "</P>\n<P>",html_entity_decode($products['products_description'] ?? ''))),0,4000) .'...]]></DESCRIPCION>'.chr(10);
  $output .= '<ACCION>'. '4' .'</ACCION>'.chr(10);
  $output .= '<CATEGORIA>'. $products['categories_cdbarcos'] .'</CATEGORIA>'.chr(10);
  $output .= '<ESTADO>'. '1' .'</ESTADO>'.chr(10);
  $output .= '<ANYO>'. '2011' .'</ANYO>'.chr(10);
  $output .= '<PRECIO>'. $regular_price .'</PRECIO>'.chr(10);
  $output .= '<WEB><![CDATA['.  tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products['products_id']) .']]></WEB>'.chr(10);
  $output .= '<ID_PROVINCIA>'. '31' .'</ID_PROVINCIA>'.chr(10);
  $output .= '<FOTOS>'.chr(10).'<URL><![CDATA['. HTTP_SERVER . DIR_WS_HTTP_CATALOG . DIR_WS_IMAGES . $products['products_image'] .']]></URL>'.chr(10).'</FOTOS>'.chr(10);
  $output .= '</ARTICULO>';
  $foot ='</ACCESORIOS>'. chr(10). '</BROKER>';
  ?>