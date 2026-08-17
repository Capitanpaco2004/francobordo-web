<?php /* 
 QSOFT 
 http://www.qsoftnet.com
 http://www.qinvoicing.com
 
 Català:
 Baixa les comandes d'una botiga d'osCommerce 2.2-MS2 i posterior a  QFACWIN 
  
 Español:
 Traspaso automático de pedidos de osCommerce 2.2-MS2 y posterior a QFACWIN.
 
 English:
 QINVOICING integration with osCommerce 2.2-MS2.
 
 (c) Autor: Quim Herrera Joancomarti 
 qhe@mailqs.com
 
 */
 
$acategoriesid = array();
$aproductesid = array();
 
require('qfacwin_cfg.php'); 
include('includes/configure.php');



//if (empty($idioma)) { $idioma= "E";}
if ( ! isset($_GET['idioma']) ) { $idioma= "A";}
else { $idioma = $_GET['idioma'];}

if ( ! isset($_GET['nav']) ) { die (idioma('Error en la crida: nav','Error en la llamada: nav','Parameter error: nav')) ;}
else {$nav = $_GET['nav'];}


//if (empty($dat)) { $dat= "";}
if ( ! isset($_GET['dat']) ) { $dat= "";}
else { $dat = $_GET['dat'];}

if ( ! isset($_GET['baixanumcoman']) ) { $baixanumcoman= "";}
else { $baixanumcoman = $_GET['baixanumcoman'];}

$ini = time();
 
$link = mysqli_connect( DB_SERVER , DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE); 
//mysqli_select_db(DB_DATABASE, $link); 

//if (empty($descri)) { $descri= "S";}
if ( ! isset($_GET['descri']) ) { $descri= "S";}
else { $dat = $_GET['descri'];}


$crlf = "\r\n"; // sortida qfacwin
if ($nav == 1) { $crlf =  "<br>" ; } //sortida navegador

//fem còpies
$datacopia  = date( "YmdHi", mktime(date("H"),date("i"), date("s"),date("m")  ,date("d"),date("Y")) );
$cbuit = "";

//$strnomdb = DB_DATABASE .'.'; dona error amb bases de dades que contene guions al nom: sonimax-bcn
$strnomdb = "";

//-----------------------------------------------------------
// marcar processats
//-----------------------------------------------------------
if ( ! isset($_GET['fets']) ) { $fets= "";}
else { $fets = $_GET['fets'];}

if (! empty($fets)) { 
   $strfets  = '';
	 $strfetsrma  = '';
   include('qfacwin_fets.php');//te la variable strfets i la rma
   $strsql = "update " . $strnomdb . $strprefixtaules . "orders  set CFACTUR = 'S' where orders_id in ( " .$strfets ." ) ";
   $result = mysqli_query( $link, $strsql )  or die ("update orders " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
   echo $crlf. idioma("Comandes marcades com a processades","Pedidos marcados como procesados","Orders checked as processed").$crlf ;	
	 //francobordo rma
   if ($intcltrac == 116) {	
	  if( ! empty( $strfetsrma ) ) {
	    $strsql = "update " . $strnomdb . $strprefixtaules . "rma  set CFACTUR = 'S' where id_rma in ( " .$strfetsrma ." ) ";
      $result = mysqli_query( $link, $strsql )  or die ("update rma " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
      echo $crlf. "RMA marcados como procesados".$crlf ;	
		} //hi ha fets
	 } //franc