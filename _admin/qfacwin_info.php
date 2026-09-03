<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<kkmeta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta charset="utf-8">
<title>Información para el traspaso de datos de osCommerce al programa de gestion Qfacwin </title>
<style type="text/css">   
   <!--
      
   .texte  {
   	font-family : Verdana, Arial, Helvetica, sans-serif;
   	font-size : 11px;
     }
 
   -->
   </style>
</head>
<body class = "texte">
<?php  //compara bases de dades
// hauria de mostrar la versio a /includes/aplication.php hi ha la linia   define('PROJECT_VERSION', 'osCommerce 2.2-MS2'); 
include('includes/configure.php');
$crlf = "\r\n"; 
$g_datadia = date( "YmdHis", mktime(date("H"),date("i"), date("s"),date("m")  ,date("d"),date("Y")) );
$g_strdataavui =   substr( $g_datadia,6,2) . "/" . substr( $g_datadia,4,2) ."/" . substr( $g_datadia,0,4);

//antic amb les taules qualificades.
//$strnomdb = DB_DATABASE .'.'; dona error amb bases de dades que contene guions al nom: sonimax-bcn
$strnomdb = "";

//if ( ! isset($_GET['idioma']) ) { $idioma= "A";}
//else { $idioma = $_GET['idioma'];}
$idioma = isset( $_GET['idioma'] ) ? $_GET['idioma'] : 'A' ; 


//if ( ! isset($_GET['tip']) ) { $tip= "";}
//else { $tip = $_GET['tip'];}
$tip = isset( $_GET['tip'] ) ? $_GET['tip'] : '' ; 

if ( ! isset($_GET['verq']) ) { $verq= "";}
else { $verq = $_GET['verq'];}

$strprefixtaules = isset( $_GET['prefix'] ) ? $_GET['prefix'] : '' ; 

$ataulesoc = array("address_book" , "address_format" , "banners" , "banners_history" , "categories" , 
"categories_description" ,  "configuration" , "configuration_group" , "counter" , "counter_history" , 
"countries" , "currencies" , "customers" , "customers_basket" , "customers_basket_attributes" , 
"customers_info" , "geo_zones" , "languages" , "manufacturers" , "manufacturers_info" , 
"newsletters" , "orders" , "orders_products" , "orders_products_attributes" , 
"orders_products_download" , "orders_status" , "orders_status_history" , "orders_total" , 
"products" , "products_attributes" , "products_attributes_download" ,  
"products_description" ,  "products_notifications" , 
"products_options" , "products_options_values" , "products_options_values_to_products_options" , 
 "products_to_categories" ,  "reviews" , "reviews_description" , "sessions" , "specials" , 
  "tax_class" , "tax_rates" , "whos_online" , "zones" , "zones_to_geo_zones" ); 

  //posem prefixe si n'hi ha
  $qi = 0;
	while ($qi < count($ataulesoc)) {
       $ataulesoc[$qi] = $strprefixtaules.$ataulesoc[$qi] ;
       $qi++;
	}//while 	
  
$aproductesid = array(); // taules amb products_id
$acategoriesid = array(); // taules amb categories_id
$numcategoriesid = 0;
$numproductesid = 0;

$link = mysql_connect( DB_SERVER , DB_SERVER_USERNAME, DB_SERVER_PASSWORD); 
mysql_select_db(DB_DATABASE, $link); 
echo "<br>";
echo  "<b>". idioma("Informe sobre la base de dades d'oscomerce a: ", "Informe sobre la base de datos de osCommerce a: ","Report on the osComerce database to: "). $g_strdataavui; 
echo "<br>URL admin: ".str_replace ("qfacwin_info.php", "", qualified_me())  .'<br>';
echo idioma("Base de dades: ","Base de datos: ","Database: ") . DB_DATABASE.'<br>';
echo idioma("Elaborat per QFACWIN ver. ","Elaborado por QFACWIN ver. ","Prepared with QINVOICING ver. ") . $verq .'&nbsp;<a href="http://www.qsoftnet.com" >www.qsoftnet.com</a><br>';
?>
<hr>
<p><?php echo  idioma("Informació dels arxius d'osCommerce per configurar el traspàs de dades al programa de facturació QFACWIN","Información de los archivos de osCommerce para configurar el traspaso de datos del programa de facturación QFACWIN","Information on osCommerce files to set up the data transfer to the QINVOICING invoicing program")?></p>
<table class = "texte" >
<tr>
<td colspan="2"><?php echo  idioma("Idiomes definits a osCommerce","Idiomas definidos en osCommerce:","Languages defined in osCommerce:")?></td></tr>
<tr>
<td width="10%" align="right"><strong><?php echo  idioma("Codi","Código","Code")?></strong></td>
<td><strong><?php echo  idioma("Idioma","Idioma","Language")?></strong></td>
</tr>
<?php 
 
$link = mysql_connect( DB_SERVER , DB_SERVER_USERNAME, DB_SERVER_PASSWORD); 
mysql_select_db(DB_DATABASE, $link); 

// $strsql = "select * from " . DB_DATABASE . "." . $strprefixtaules . "languages order by languages_id ";
$strsql = "select * from " . $strnomdb . $strprefixtaules . "languages order by languages_id ";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo '<tr><td align="right">'.$rowatri["languages_id"]."</td><td>".$rowatri["name"]."</td></tr>";
		   } //while atributtes
	  
?>
</table>
<br>
<table class = "texte" >
<tr>
<td colspan="3"><?php echo  idioma("Tipus d'impost definits a osCommerce:","Tipos de Impuesto definidos en osCommerce:","Tax class defined in osCommerce:")?></td></tr>
<tr>
<td width="10%" align="right"><strong><?php echo  idioma("Codi","Código","Code")?></strong></td>
<td><strong><?php echo  idioma("Tipus d'impost","Tipo de Impuesto","Tax class")?></strong></td>
<td><strong><?php echo  idioma("Descripció","Descripción","Description")?></strong></td>
</tr>
<?php 

 //$strsql = "select * from " . DB_DATABASE . "." . $strprefixtaules . "tax_class order by tax_class_id ";
$strsql = "select * from " . $strnomdb . $strprefixtaules . "tax_class order by tax_class_id ";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo '<tr><td align="right">'.$rowatri["tax_class_id"]."</td><td>".$rowatri["tax_class_title"]."</td><td>".$rowatri["tax_class_description"]."</td></tr>";
		   } //while atributtes
	  
?>
</table>
<br>
<table class = "texte" >
<tr>
<td colspan="2"><?php echo  idioma("Tipus d'impost definits a productes:","Tipos de Impuesto definidos en productos:","Tax class defined in products:")?></td></tr>
<tr>
<td align="right"><strong><?php echo  idioma("Tipus d'impost","Tipo de Impuesto","Tax class")?></strong></td>
<td align="right"><strong><?php echo  idioma("Productes","Productos","Products")?></strong></td>
</tr>
<?php

 //$strsql = "select products_tax_class_id, count(*) as num from " . DB_DATABASE . "." . $strprefixtaules . "products group by products_tax_class_id order by products_tax_class_id";
$strsql = "select products_tax_class_id, count(*) as num from " . $strprefixtaules . "products group by products_tax_class_id order by products_tax_class_id";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo '<tr><td align="right">'.$rowatri["products_tax_class_id"].'</td><td align="right">'.$rowatri["num"]."</td></tr>";
		   } //while atributtes
	  
?>
</table>


<?php 
//grups de clients per SPPC
//hi ha alguna contribució amb una taula customers-groups que te el id que es  customers_groups_id (amb s) i petava
$kgrups = "S";
 $strsql = "select * from "  . $strnomdb  . $strprefixtaules . "customers_groups order by customers_group_id ";
$result = mysql_query( $strsql,  $link);
if ($result==FALSE){	
   if ( (mysql_errno()== 1146) or (mysql_errno()== 1054) ){ $kgrups = "N";} //no existeix la taula o els camp
   else{ echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
}
if ($kgrups == "S"){
?><table class = "texte" >
<tr>
<td colspan="2">SPPC <?php echo  idioma("Grups de clients:","Grupos de clientes:","Customer groups:")?></td></tr>
<tr>
<td width="10%" align="right"><strong><?php echo  idioma("Codi","Código","Code")?></strong></td>
<td><strong><?php echo  idioma("Grup","Grupo","Group")?></strong></td>
</tr>
<?php 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo '<tr><td align="right">'.$rowatri["customers_group_id"]."</td><td>". $rowatri["customers_group_name"]."</td></tr>";
		   } //while atributtes
 echo '</table>	';
} //existeix taula customer_groups	  
?>
  


<br>
<table class = "texte" >
<tr>
<td ><strong><?php echo  idioma("Clases de totals a comandes (orders_total):","Clases de totales en pedidos (orders_total):", "Order total types (orders_total):")?></strong></td></tr>
<?php 

 $strsql = "select class from "  . $strnomdb . $strprefixtaules . "orders_total GROUP BY class";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo '<tr><td >'.$rowatri["class"]."</td></tr>";
		   } //while atributtes
	  
?>
</table>

<?php 
 $strsql = "select count(*) from "  . $strnomdb . $strprefixtaules . "categories ";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf ." categories = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo '<br>'.idioma('Total registres en categories:','Total registros en categorias: ',"Total records in categories: ") .'' .$rowatri["count(*)"]. '<br>';
		   } //while atributtes

 $strsql = "select count(*) from "  . $strnomdb . $strprefixtaules . "products ";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo ''.idioma('Total registres en productes:','Total registros en productos: ',"Total records in products: ") .''.$rowatri["count(*)"]. '<br>';
		   } //while atributtes
			 
 $strsql = "select count(*) from "  . $strnomdb . $strprefixtaules . "products where products_model <> ''";
 $result = mysql_query( $strsql,  $link);
 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; } 
 while ( $rowatri = mysql_fetch_array($result)) {
   echo ''.idioma('Total productes amb model:','Total productos con modelo: ',"Total products with model: ") .''.$rowatri["count(*)"]. '<br>';
		   } //while atributtes	  
echo '<br><br>';

if ($tip== "") {?>
    <?php echo  idioma("Comparativa amb base de dades d'osCommerce 2.2-MS2 original","Comparativa con la base de datos de osCommerce 2.2-MS2 original","Comparative with original osCommerce 2.2-MS2 database")?>
	 </b><br>
	<table border="1" class = "texte" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 10px;">
	<tr><td><strong><?php echo  idioma("TAULA","TABLA","TABLE") ?></strong></td><td>&nbsp;</td></tr>
	<?php 
	$strsql = "show tables "; //from ". DB_DATABASE;
	$result = mysql_query( $strsql,  $link);
	if ($result==FALSE)	{	echo $crlf.">Error mysql= " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; }
	
	$taules = '';
	$coma = '';
	while ( $rowtaula = mysql_fetch_array($result)) {
	    echo "<tr>";
		comparaestructura($rowtaula["Tables_in_". DB_DATABASE]);
	    echo "</tr>";	
		
	 } //while 
	
	 echo "</table><BR>";
	echo '<br>';
	echo '<strong>//'.idioma("Arxius amb el camp categories_id","Archivos con el campo categories_id","Files with categories_id field") .'</strong><br>';
	$qi = 0;
	while ($qi < count($acategoriesid)) {
   		echo '$acategoriesid['. $qi . '] = "'.$acategoriesid[$qi].'";<br>'; 
		$qi++;
	}//while 
	echo '<strong>//'.idioma("Arxius amb el camp products_id","Archivos con el campo products_id","Files wit products_id field") .' </strong><br>';
	$qi = 0;
	while ($qi < count($aproductesid)) {
   		echo '$aproductesid['. $qi . '] = "'.$aproductesid[$qi].'";<br>'; 
		$qi++;
	}//while 
	
	echo '</body></html>';
	
} //tip en blanc


if ($tip== "L") {?>
<?php echo idioma("Llistat taules afegides a osCommerce a data:","Listado de tablas añadidas en osCommerce a fecha:", "Report of tables added in osCommerce by:") ?>	 <?php echo $g_strdataavui ?></b><br>
	<table border="1" class = "texte" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 10px;">
	<tr><td><strong><?php echo idioma("TAULES AFEGIDES","TABLAS AÑADIDAS","TABLES ADDED") ?></strong></td><td><strong><?php echo idioma("DESCRIPCIÓ","DESCRIPCIÓN","DESCRIPTION") ?></strong></td></tr>
	<?php 
	$strsql = "show tables from ". DB_DATABASE;
	$result = mysql_query( $strsql,  $link);
	if ($result==FALSE)	{	echo $crlf.">Error mysql= " . mysql_errno().": ".mysql_error(). $crlf .$strsql;	die; }
	
	$taules = '';
	$coma = '';
	while ( $rowtaula = mysql_fetch_array($result)) {
	    echo "<tr>";
		llistataules($rowtaula["Tables_in_". DB_DATABASE]);
		echo "</tr>";	
		
	 } //while 
	
	 echo "</table><BR>";
	echo '<br>';
	  echo '</body></html>';
	
} //llistar taules afegides

//--------------------------------------------------------------------------
//llistat de taules originals. Ha d'estar a una instalacio original
if ($tip== "LO") {?>
	Llistat taules d' osCommerce a data: <?php echo $g_strdataavui ?></b><br>
	<table border="1" class = "texte" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 10px;">
	<tr><td><strong>TAULES ORIGINALS</strong></td><td><strong>DESCRIPCIÓ</strong></td></tr>
	<?php 
	 
	
	$qi = 0;
	while ($qi < count($ataulesoc)) {
   		echo "<tr>";
		$taula = $ataulesoc[$qi];
		echo '<td valign="top">'. $taula. '</td><td><table class=texte><tr><td><strong>Camp</strong></td><td><strong>Tipus</strong></td><td align="right"><strong>Long.</strong></td><td><strong>Flag</strong></td></tr>';
      //busquem camps products_id i categories_id
	    $result2 = mysql_query("SELECT * FROM " .$taula);
		$fields = mysql_num_fields($result2);
		$i = 0;
		while ($i < $fields) {
		    $type  = mysql_field_type  ($result2, $i);
		    $name  = mysql_field_name  ($result2, $i);
		    $len   = mysql_field_len   ($result2, $i);
		    $flags = mysql_field_flags ($result2, $i);
			
			echo "<tr><td>". $name . "</td><td>". $type. '</td><td align="right">'.$len. "</td><td >". $flags . "</td></tr>";
			
		    $i++;
		 }//while
	 echo "</table></td> ";
		
		
	    echo "</tr>";	
		$qi++;
	}//while 
	
	 echo "</table><BR>";
	echo '<br>';
	  echo '</body></html>';
	
} //llistar taules originals


?>


<?php 	


// ---------------------------------------------------------- 
//  compara db
// ---------------------------------------------------------- 
		   
function comparaestructura( $taula ) {

//estructures osCommerce originals:
global $ataulesoc, $aproductesid, $numproductesid, $acategoriesid, $numcategoriesid, $strprefixtaules ;

  echo '<td valign="top">'. $taula. "</td>";
  if ( ! in_array ($taula, $ataulesoc)){
     echo "<td>".idioma("Taula afegida. No existeix a osCommerce original", "Tabla añadida. No existe en osCommerce original","Table added. Does not exist in the original osCommerce");
	 //busquem camps products_id i categories_id
	    $result2 = mysql_query("SELECT * FROM " .$taula);
		$fields = mysql_num_fields($result2);
		$i = 0;
		while ($i < $fields) {
		    //$type  = mysql_field_type  ($result2, $i);
		    $name  = mysql_field_name  ($result2, $i);
		    //$len   = mysql_field_len   ($result2, $i);
		    //$flags = mysql_field_flags ($result2, $i);
			
			if  ( $name == 'categories_id')  {
		        echo "<br>&nbsp;&nbsp;". idioma("Conté","Contiene","Content")." ". $name ;
				$acategoriesid [ $numcategoriesid ] = $taula;
				$numcategoriesid ++;
			};
			if  ( $name == 'products_id')  {
		        echo "<br>&nbsp;&nbsp;". idioma("Conté","Contiene","Content")." ". $name ;
				$aproductesid [ $numproductesid ] = $taula;
				$numproductesid ++;
			};
			
			
		    $i++;
		 }//while
	 echo "</td> ";
	 return;
  }//taula nova

  
  echo "<td>";
 
 $taula = substr ($taula, strlen( $strprefixtaules )); //treiem prefix
 // address_book 
if ($taula == "address_book" ){ 
$acampsori = array( "address_book_id" , "customers_id" , "entry_gender" , "entry_company" , "entry_firstname" , "entry_lastname" , "entry_street_address" , "entry_suburb" , "entry_postcode" , "entry_city" , "entry_state" , "entry_country_id" , "entry_zone_id" ); 
$atipusori = array( "int" , "int" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "int" , "int" ); 
$alongori = array(11 , 11 , 1 , 32 , 32 , 32 , 64 , 32 , 10 , 32 , 32 , 11 , 11 ); 
} 
// address_format 
if ($taula == "address_format" ){ 
$acampsori = array( "address_format_id" , "address_format" , "address_summary" ); 
$atipusori = array( "int" , "string" , "string" ); 
$alongori = array(11 , 128 , 48 ); 
} 
// banners 
if ($taula == "banners" ){ 
$acampsori = array( "banners_id" , "banners_title" , "banners_url" , "banners_image" , "banners_group" , "banners_html_text" , "expires_impressions" , "expires_date" , "date_scheduled" , "date_added" , "date_status_change" , "status" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "string" , "blob" , "int" , "datetime" , "datetime" , "datetime" , "datetime" , "int" ); 
$alongori = array(11 , 64 , 255 , 64 , 10 , 65535 , 7 , 19 , 19 , 19 , 19 , 1 ); 
} 
// banners_history 
if ($taula == "banners_history" ){ 
$acampsori = array( "banners_history_id" , "banners_id" , "banners_shown" , "banners_clicked" , "banners_history_date" ); 
$atipusori = array( "int" , "int" , "int" , "int" , "datetime" ); 
$alongori = array(11 , 11 , 5 , 5 , 19 ); 
} 
// categories 
if ($taula == "categories" ){ 
$acampsori = array( "categories_id" , "categories_image" , "parent_id" , "sort_order" , "date_added" , "last_modified" , "CODIG" ); 
$atipusori = array( "int" , "string" , "int" , "int" , "datetime" , "datetime" , "int" ); 
$alongori = array(11 , 64 , 11 , 3 , 19 , 19 , 5 ); 
} 
// categories_description 
if ($taula == "categories_description" ){ 
$acampsori = array( "categories_id" , "language_id" , "categories_name" ); 
$atipusori = array( "int" , "int" , "string" ); 
$alongori = array(11 , 11 , 32 ); 
} 
// configuration 
if ($taula == "configuration" ){ 
$acampsori = array( "configuration_id" , "configuration_title" , "configuration_key" , "configuration_value" , "configuration_description" , "configuration_group_id" , "sort_order" , "last_modified" , "date_added" , "use_function" , "set_function" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "string" , "int" , "int" , "datetime" , "datetime" , "string" , "string" ); 
$alongori = array(11 , 64 , 64 , 255 , 255 , 11 , 5 , 19 , 19 , 255 , 255 ); 
} 
// configuration_group 
if ($taula == "configuration_group" ){ 
$acampsori = array( "configuration_group_id" , "configuration_group_title" , "configuration_group_description" , "sort_order" , "visible" ); 
$atipusori = array( "int" , "string" , "string" , "int" , "int" ); 
$alongori = array(11 , 64 , 255 , 5 , 1 ); 
} 
// counter 
if ($taula == "counter" ){ 
$acampsori = array( "startdate" , "counter" ); 
$atipusori = array( "string" , "int" ); 
$alongori = array(8 , 12 ); 
} 
// counter_history 
if ($taula == "counter_history" ){ 
$acampsori = array( "month" , "counter" ); 
$atipusori = array( "string" , "int" ); 
$alongori = array(8 , 12 ); 
} 
// countries 
if ($taula == "countries" ){ 
$acampsori = array( "countries_id" , "countries_name" , "countries_iso_code_2" , "countries_iso_code_3" , "address_format_id" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "int" ); 
$alongori = array(11 , 64 , 2 , 3 , 11 ); 
} 
// currencies 
if ($taula == "currencies" ){ 
$acampsori = array( "currencies_id" , "title" , "code" , "symbol_left" , "symbol_right" , "decimal_point" , "thousands_point" , "decimal_places" , "value" , "last_updated" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "real" , "datetime" ); 
$alongori = array(11 , 32 , 3 , 12 , 12 , 1 , 1 , 1 , 13 , 19 ); 
} 
// customers 
if ($taula == "customers" ){ 
$acampsori = array( "customers_id" , "customers_gender" , "customers_firstname" , "customers_lastname" , "customers_dob" , "customers_email_address" , "customers_default_address_id" , "customers_telephone" , "customers_fax" , "customers_password" , "customers_newsletter" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "datetime" , "string" , "int" , "string" , "string" , "string" , "string" ); 
$alongori = array(11 , 1 , 32 , 32 , 19 , 96 , 11 , 32 , 32 , 40 , 1 ); 
} 
// customers_basket 
if ($taula == "customers_basket" ){ 
$acampsori = array( "customers_basket_id" , "customers_id" , "products_id" , "customers_basket_quantity" , "final_price" , "customers_basket_date_added" ); 
$atipusori = array( "int" , "int" , "blob" , "int" , "real" , "string" ); 
$alongori = array(11 , 11 , 255 , 2 , 17 , 8 ); 
} 
// customers_basket_attributes 
if ($taula == "customers_basket_attributes" ){ 
$acampsori = array( "customers_basket_attributes_id" , "customers_id" , "products_id" , "products_options_id" , "products_options_value_id" ); 
$atipusori = array( "int" , "int" , "blob" , "int" , "int" ); 
$alongori = array(11 , 11 , 255 , 11 , 11 ); 
} 
// customers_info 
if ($taula == "customers_info" ){ 
$acampsori = array( "customers_info_id" , "customers_info_date_of_last_logon" , "customers_info_number_of_logons" , "customers_info_date_account_created" , "customers_info_date_account_last_modified" , "global_product_notifications" ); 
$atipusori = array( "int" , "datetime" , "int" , "datetime" , "datetime" , "int" ); 
$alongori = array(11 , 19 , 5 , 19 , 19 , 1 ); 
} 
// geo_zones 
if ($taula == "geo_zones" ){ 
$acampsori = array( "geo_zone_id" , "geo_zone_name" , "geo_zone_description" , "last_modified" , "date_added" ); 
$atipusori = array( "int" , "string" , "string" , "datetime" , "datetime" ); 
$alongori = array(11 , 32 , 255 , 19 , 19 ); 
} 
// languages 
if ($taula == "languages" ){ 
$acampsori = array( "languages_id" , "name" , "code" , "image" , "directory" , "sort_order" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "string" , "int" ); 
$alongori = array(11 , 32 , 2 , 64 , 32 , 3 ); 
} 
// manufacturers 
if ($taula == "manufacturers" ){ 
$acampsori = array( "manufacturers_id" , "manufacturers_name" , "manufacturers_image" , "date_added" , "last_modified" ); 
$atipusori = array( "int" , "string" , "string" , "datetime" , "datetime" ); 
$alongori = array(11 , 32 , 64 , 19 , 19 ); 
} 
// manufacturers_info 
if ($taula == "manufacturers_info" ){ 
$acampsori = array( "manufacturers_id" , "languages_id" , "manufacturers_url" , "url_clicked" , "date_last_click" ); 
$atipusori = array( "int" , "int" , "string" , "int" , "datetime" ); 
$alongori = array(11 , 11 , 255 , 5 , 19 ); 
} 
// newsletters 
if ($taula == "newsletters" ){ 
$acampsori = array( "newsletters_id" , "title" , "content" , "module" , "date_added" , "date_sent" , "status" , "locked" ); 
$atipusori = array( "int" , "string" , "blob" , "string" , "datetime" , "datetime" , "int" , "int" ); 
$alongori = array(11 , 255 , 65535 , 255 , 19 , 19 , 1 , 1 ); 
} 
// orders 
if ($taula == "orders" ){ 
$acampsori = array( "orders_id" , "customers_id" , "customers_name" , "customers_company" , "customers_street_address" , "customers_suburb" , "customers_city" , "customers_postcode" , "customers_state" , "customers_country" , "customers_telephone" , "customers_email_address" , "customers_address_format_id" , "delivery_name" , "delivery_company" , "delivery_street_address" , "delivery_suburb" , "delivery_city" , "delivery_postcode" , "delivery_state" , "delivery_country" , "delivery_address_format_id" , "billing_name" , "billing_company" , "billing_street_address" , "billing_suburb" , "billing_city" , "billing_postcode" , "billing_state" , "billing_country" , "billing_address_format_id" , "payment_method" , "cc_type" , "cc_owner" , "cc_number" , "cc_expires" , "last_modified" , "date_purchased" , "orders_status" , "orders_date_finished" , "currency" , "currency_value" ); 
$atipusori = array( "int" , "int" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "int" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "int" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "string" , "int" , "string" , "string" , "string" , "string" , "string" , "datetime" , "datetime" , "int" , "datetime" , "string" , "real" ); 
$alongori = array(11 , 11 , 64 , 32 , 64 , 32 , 32 , 10 , 32 , 32 , 32 , 96 , 5 , 64 , 32 , 64 , 32 , 32 , 10 , 32 , 32 , 5 , 64 , 32 , 64 , 32 , 32 , 10 , 32 , 32 , 5 , 32 , 20 , 64 , 32 , 4 , 19 , 19 , 5 , 19 , 3 , 16 ); 
} 
// orders_products 
if ($taula == "orders_products" ){ 
$acampsori = array( "orders_products_id" , "orders_id" , "products_id" , "products_model" , "products_name" , "products_price" , "final_price" , "products_tax" , "products_quantity" ); 
$atipusori = array( "int" , "int" , "int" , "string" , "string" , "real" , "real" , "real" , "int" ); 
$alongori = array(11 , 11 , 11 , 12 , 64 , 17 , 17 , 9 , 2 ); 
} 
// orders_products_attributes 
if ($taula == "orders_products_attributes" ){ 
$acampsori = array( "orders_products_attributes_id" , "orders_id" , "orders_products_id" , "products_options" , "products_options_values" , "options_values_price" , "price_prefix" ); 
$atipusori = array( "int" , "int" , "int" , "string" , "string" , "real" , "string" ); 
$alongori = array(11 , 11 , 11 , 32 , 32 , 17 , 1 ); 
} 
// orders_products_download 
if ($taula == "orders_products_download" ){ 
$acampsori = array( "orders_products_download_id" , "orders_id" , "orders_products_id" , "orders_products_filename" , "download_maxdays" , "download_count" ); 
$atipusori = array( "int" , "int" , "int" , "string" , "int" , "int" ); 
$alongori = array(11 , 11 , 11 , 255 , 2 , 2 ); 
} 
// orders_status 
if ($taula == "orders_status" ){ 
$acampsori = array( "orders_status_id" , "language_id" , "orders_status_name" ); 
$atipusori = array( "int" , "int" , "string" ); 
$alongori = array(11 , 11 , 32 ); 
} 
// orders_status_history 
if ($taula == "orders_status_history" ){ 
$acampsori = array( "orders_status_history_id" , "orders_id" , "orders_status_id" , "date_added" , "customer_notified" , "comments" ); 
$atipusori = array( "int" , "int" , "int" , "datetime" , "int" , "blob" ); 
$alongori = array(11 , 11 , 5 , 19 , 1 , 65535 ); 
} 
// orders_total 
if ($taula == "orders_total" ){ 
$acampsori = array( "orders_total_id" , "orders_id" , "title" , "text" , "value" , "class" , "sort_order" ); 
$atipusori = array( "int" , "int" , "string" , "string" , "real" , "string" , "int" ); 
$alongori = array(10 , 11 , 255 , 255 , 17 , 32 , 11 ); 
} 
// products 
if ($taula == "products" ){ 
$acampsori = array( "products_id" , "products_quantity" , "products_model" , "products_image" , "products_price" , "products_date_added" , "products_last_modified" , "products_date_available" , "products_weight" , "products_status" , "products_tax_class_id" , "manufacturers_id" , "products_ordered" , "CCODIART" ); 
$atipusori = array( "int" , "int" , "string" , "string" , "real" , "datetime" , "datetime" , "datetime" , "real" , "int" , "int" , "int" , "int" , "string" ); 
$alongori = array(11 , 4 , 12 , 64 , 17 , 19 , 19 , 19 , 7 , 1 , 11 , 11 , 11 , 20 ); 
} 
// products_attributes 
if ($taula == "products_attributes" ){ 
$acampsori = array( "products_attributes_id" , "products_id" , "options_id" , "options_values_id" , "options_values_price" , "price_prefix" ); 
$atipusori = array( "int" , "int" , "int" , "int" , "real" , "string" ); 
$alongori = array(11 , 11 , 11 , 11 , 17 , 1 ); 
} 
// products_attributes_download 
if ($taula == "products_attributes_download" ){ 
$acampsori = array( "products_attributes_id" , "products_attributes_filename" , "products_attributes_maxdays" , "products_attributes_maxcount" ); 
$atipusori = array( "int" , "string" , "int" , "int" ); 
$alongori = array(11 , 255 , 2 , 2 ); 
} 
 
// products_description 
if ($taula == "products_description" ){ 
$acampsori = array( "products_id" , "language_id" , "products_name" , "products_description" , "products_url" , "products_viewed" ); 
$atipusori = array( "int" , "int" , "string" , "blob" , "string" , "int" ); 
$alongori = array(11 , 11 , 64 , 65535 , 255 , 5 ); 
} 
// products_notifications 
if ($taula == "products_notifications" ){ 
$acampsori = array( "products_id" , "customers_id" , "date_added" ); 
$atipusori = array( "int" , "int" , "datetime" ); 
$alongori = array(11 , 11 , 19 ); 
} 
// products_options 
if ($taula == "products_options" ){ 
$acampsori = array( "products_options_id" , "language_id" , "products_options_name" ); 
$atipusori = array( "int" , "int" , "string" ); 
$alongori = array(11 , 11 , 32 ); 
} 
// products_options_values 
if ($taula == "products_options_values" ){ 
$acampsori = array( "products_options_values_id" , "language_id" , "products_options_values_name" ); 
$atipusori = array( "int" , "int" , "string" ); 
$alongori = array(11 , 11 , 64 ); 
} 
// products_options_values_to_products_options 
if ($taula == "products_options_values_to_products_options" ){ 
$acampsori = array( "products_options_values_to_products_options_id" , "products_options_id" , "products_options_values_id" ); 
$atipusori = array( "int" , "int" , "int" ); 
$alongori = array(11 , 11 , 11 ); 
} 
// products_to_categories 
if ($taula == "products_to_categories" ){ 
$acampsori = array( "products_id" , "categories_id" ); 
$atipusori = array( "int" , "int" ); 
$alongori = array(11 , 11 ); 
} 
// reviews 
if ($taula == "reviews" ){ 
$acampsori = array( "reviews_id" , "products_id" , "customers_id" , "customers_name" , "reviews_rating" , "date_added" , "last_modified" , "reviews_read" ); 
$atipusori = array( "int" , "int" , "int" , "string" , "int" , "datetime" , "datetime" , "int" ); 
$alongori = array(11 , 11 , 11 , 64 , 1 , 19 , 19 , 5 ); 
} 
// reviews_description 
if ($taula == "reviews_description" ){ 
$acampsori = array( "reviews_id" , "languages_id" , "reviews_text" ); 
$atipusori = array( "int" , "int" , "blob" ); 
$alongori = array(11 , 11 , 65535 ); 
} 
// sessions 
if ($taula == "sessions" ){ 
$acampsori = array( "sesskey" , "expiry" , "value" ); 
$atipusori = array( "string" , "int" , "blob" ); 
$alongori = array(32 , 11 , 65535 ); 
} 
// specials 
if ($taula == "specials" ){ 
$acampsori = array( "specials_id" , "products_id" , "specials_new_products_price" , "specials_date_added" , "specials_last_modified" , "expires_date" , "date_status_change" , "status" ); 
$atipusori = array( "int" , "int" , "real" , "datetime" , "datetime" , "datetime" , "datetime" , "int" ); 
$alongori = array(11 , 11 , 17 , 19 , 19 , 19 , 19 , 1 ); 
} 
// tax_class 
if ($taula == "tax_class" ){ 
$acampsori = array( "tax_class_id" , "tax_class_title" , "tax_class_description" , "last_modified" , "date_added" ); 
$atipusori = array( "int" , "string" , "string" , "datetime" , "datetime" ); 
$alongori = array(11 , 32 , 255 , 19 , 19 ); 
} 
// tax_rates 
if ($taula == "tax_rates" ){ 
$acampsori = array( "tax_rates_id" , "tax_zone_id" , "tax_class_id" , "tax_priority" , "tax_rate" , "tax_description" , "last_modified" , "date_added" ); 
$atipusori = array( "int" , "int" , "int" , "int" , "real" , "string" , "datetime" , "datetime" ); 
$alongori = array(11 , 11 , 11 , 5 , 9 , 255 , 19 , 19 ); 
} 
// whos_online 
if ($taula == "whos_online" ){ 
$acampsori = array( "customer_id" , "full_name" , "session_id" , "ip_address" , "time_entry" , "time_last_click" , "last_page_url" ); 
$atipusori = array( "int" , "string" , "string" , "string" , "string" , "string" , "string" ); 
$alongori = array(11 , 64 , 128 , 15 , 14 , 14 , 64 ); 
} 
// zones 
if ($taula == "zones" ){ 
$acampsori = array( "zone_id" , "zone_country_id" , "zone_code" , "zone_name" ); 
$atipusori = array( "int" , "int" , "string" , "string" ); 
$alongori = array(11 , 11 , 32 , 32 ); 
} 
// zones_to_geo_zones 
if ($taula == "zones_to_geo_zones" ){ 
$acampsori = array( "association_id" , "zone_country_id" , "zone_id" , "geo_zone_id" , "last_modified" , "date_added" ); 
$atipusori = array( "int" , "int" , "int" , "int" , "datetime" , "datetime" ); 
$alongori = array(11 , 11 , 11 , 11 , 19 , 19 ); 
} 

//----------
// compara

//veure si estan tots els camps

//veure si hi ha més camps
   
$result2 = mysql_query("SELECT * FROM " .$strprefixtaules. $taula);
$fields = mysql_num_fields($result2);
$rows   = mysql_num_rows($result2);
$i = 0;
$table = mysql_field_table($result2, $i);
//echo "Your '".$table."' table has ".$fields." fields and ".$rows." records <BR>";
//echo "The table has the following fields <BR>";
$campsafegits = "";
$campscanviats = "";
$acampsnou = array();
while ($i < $fields) {
    $type  = mysql_field_type  ($result2, $i);
    $name  = mysql_field_name  ($result2, $i);
    $len   = mysql_field_len   ($result2, $i);
    $flags = mysql_field_flags ($result2, $i);
	
	$acampsnou[$i] = $name;  
	if ( ! in_array ($name, $acampsori)){
       $campsafegits .= "<tr><td>". $name . "</td><td>". $type. '</td><td align="right">'.$len. "</td></tr>";
	} else {
       $j = 0;
       while ($j <= count($acampsori)-1):
          if ($acampsori[$j] == $name){
	          //tipus dades
			  if ( ($atipusori[$j] <> $type) or ($alongori[$j] <> $len)) {
			      $campscanviats .= "<tr><td>".idioma("canviat: ","cambiado: ","changed: "). $name . "</td><td>".idioma("era ","era ","was ").$atipusori[$j].idioma(" es "," es "," is "). $type. '</td><td align="right">'. idioma("era ","era ","was "). $alongori[$j] .idioma(" es "," es "," is ").$len. "</td></tr>";
			  }
			  break;
	       }
          $j++;
       endwhile;

    }
   	
    $i++;
}

//mirem si falten camps
$falten = "";
 $j = 0;
  while ($j <= count($acampsori)-1):
      if ( ! in_array ($acampsori[$j], $acampsnou)){
       $falten .= idioma( "Falta: ", "Falta: ","Missing : "). $acampsori[$j] . "</br>";
	  }
      $j++;
   endwhile;

if ( $campsafegits <> ""){$campsafegits = '<table style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 10px;"><tr><td colspan=3><strong>'.idioma("Nous camps:","Nuevos campos:", "New fields:").'</strong></td></tr>'.$campsafegits.'</table>';}
 if ( $campscanviats <> ""){$campscanviats = '<table style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 10px;"><tr><td colspan=3><strong>'.idioma("Camps canviats:","Campos cambiados:","Changed fields:").'</strong></td></tr>'.$campscanviats.'</table>';}
 if ( $falten <> ""){$falten = '<br>'.idioma("Camps que falten:","Campos que faltan:","Missing fields:").'<br>'.$falten;}
 echo $campsafegits. $campscanviats.$falten. "&nbsp;</td>";
}  //comparaestructura



// ---------------------------------------------------------- 
//  llista
// ---------------------------------------------------------- 
		   
function llistataules( $taula ) {
global $ataulesoc;
  
  if ( ! in_array ($taula, $ataulesoc)){
  
     echo '<td valign="top">'. $taula. '</td><td><table class=texte><tr><td><strong>'.idioma("Camp","Campo","Field").'</strong></td><td><strong>'.idioma("Tipus","Tipo","Type").'</strong></td><td align="right"><strong>'.idioma("Long.","Long.","Length").'</strong></td><td><strong>Flag</strong></td></tr>';
      //busquem camps products_id i categories_id
	    $result2 = mysql_query("SELECT * FROM " .$taula);
		$fields = mysql_num_fields($result2);
		$i = 0;
		while ($i < $fields) {
		    $type  = mysql_field_type  ($result2, $i);
		    $name  = mysql_field_name  ($result2, $i);
		    $len   = mysql_field_len   ($result2, $i);
		    $flags = mysql_field_flags ($result2, $i);
			
			echo "<tr><td>". $name . "</td><td>". $type. '</td><td align="right">'.$len. "</td><td >". $flags . "</td></tr>";
			
		    $i++;
		 }//while
	 echo "</table></td> ";
	 
  }//taula nova


  return;
  
} //llistataules 


 
// ---------------------------------------------------------- 
//  retorna literal en funcio de l'idioma  de la web
// ---------------------------------------------------------- 
function idioma($cat, $esp, $ang) {
global $idioma;
  if ($idioma == "C") {
		$cadena = $cat;
  } else { 
       if ($idioma == "A") {
	    	$cadena = $ang;
        } else { 
            $cadena = $esp;
	    }
	 
	}
	return $cadena;
}

function me() {
/* returns the name of the current script, without the querystring portion.
 * this function is necessary because PHP_SELF and REQUEST_URI and PATH_INFO
 * return different things depending on a lot of things like your OS, Web
 * server, and the way PHP is compiled (ie. as a CGI, module, ISAPI, etc.) */

	if (getenv("REQUEST_URI")) {
		$me = getenv("REQUEST_URI");

	} elseif (getenv("PATH_INFO")) {
		$me = getenv("PATH_INFO");

	} elseif ($GLOBALS["PHP_SELF"]) {
		$me = $GLOBALS["PHP_SELF"];
	}

	return strip_querystring($me);
}



function qualified_me() {
/* retorna el nom i l'ubicaci&oacute; d'aquesta pagina */

	$HTTPS = getenv("HTTPS");
	$SERVER_PROTOCOL = getenv("SERVER_PROTOCOL");
	$HTTP_HOST = getenv("HTTP_HOST");

	$protocol = (isset($HTTPS) && $HTTPS == "on") ? "https://" : "http://";
	$url_prefix = "$protocol$HTTP_HOST";
	return $url_prefix . me() ;

}

function strip_querystring($url) {
/* takes a URL and returns it without the querystring portion */

	if ($commapos = strpos($url, '?')) {
		return substr($url, 0, $commapos);
	} else {
		return $url;
	}
}

/* retorna la url de la pagina sense barra al final*/
function url_prefix(){
  $kurl = qualified_me();
  if ($commapos = strrpos($kurl, '/')) {
		return substr($kurl, 0, $commapos);
	} else {
		return $kurl;
	}
	
}



?>