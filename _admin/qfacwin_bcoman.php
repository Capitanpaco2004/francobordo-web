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
	 } //francobordo
   die;
}//ve processat

//carpeta per crear temporals, si no ve, a images
if ( empty( $strdircreatemporals ) ){ $strdircreatemporals = DIR_FS_CATALOG_IMAGES; }

$aclients = array();
//-----------------------------------------------------
// comandes
//-----------------------------------------------------

//echo $crlf. idioma("Migració de dades existents a la botiga d'osCommerce a QFACWIN:",
//       "Migración de los datos existentes en osCommerce:").$crlf ; 
echo $crlf. idioma("Processant comandes a baixar:","Procesando pedidos a bajar:","Processing orders to download").$crlf ; 


$filecoman = $strdircreatemporals  . "wcoman.txt"; 
if(!$fpcoman = fopen ($filecoman ,"w+"))
{
     print $filecoman. idioma("Impossible crear l''arxiu (doneu permisos 777 al directori d'imatges).Procés cancel.lat", "Imposible crear archivo(otorgue permisos 777 a la carpeta de imagenes). Proceso cancelado","Impossible to create the file (give 777 permissions to the images directory). Process cancelled");
     die;
}

$filecomanlin = $strdircreatemporals  . "wcomanlin.txt"; 
if(!$fpcomanlin = fopen ($filecomanlin ,"w+"))
{
     print $filecomanlin. idioma("Impossible crear l''arxiu (doneu permisos 777 al directori d'imatges).Procés cancel.lat","Imposible crear archivo(otorgue permisos 777 a la carpeta de imagenes). Proceso cancelado","Impossible to create the file (give 777 permissions to the images directory). Process cancelled");
     die;
}

$fileclients = $strdircreatemporals  . "wclients.txt"; 
if(!$fpclients = fopen ($fileclients ,"w+"))
{
     print $fileclients. idioma("Impossible crear l''arxiu (doneu permisos 777 al directori d'imatges).Procés cancel.lat","Imposible crear archivo(otorgue permisos 777 a la carpeta de imagenes). Proceso cancelado","Impossible to create the file (give 777 permissions to the images directory). Process cancelled");
     die;
}

//primera fila: camps
$strlin ='"CFACTUR";"NNUMFRA";"DATFRA";"NCODICL";"CNOMCLI";"XIVA1";"XIVA2";"XIVA3";"LREQ";"XREQ1";"XREQ2";"XREQ3";"NBASE1";"NBASE2";"NBASE3";"NIVA1";"NIVA2";"NIVA3";"NREQ1";"NREQ2";"NREQ3";"XIRPF";"NIRPF";"NTOTAL";"XDTE";"NBASEDTE";"NIMPDTE";"XDTEPP";"NBASEDTPP";"NIMPDTPP";"NTARIFA";"NCODIVEN";"NNUMFP";"NSUPLI";"MNOTES";"NPES";"NPAQ";"LEXIVA";"CPORTS";"NCODITR";"CFPAG";"DATAEN";'."\r\n";
fwrite($fpcoman, $strlin); 

$strlin ='"DATAL";"NCODICL";"NNUMFRA";"NNUMLIN";"QUANT";"CCODIART";"CODIG";"CDESCRI";"NPREU";"XDTEA";"NBASEDTEA";"NIMPDTEA";"LDTE";"NTIPIVA";"NIMPORT";"DVT";"NMESOSVT";"LSUPLI";"NPAQ";"NPES";"NPEST";"CLOT";"CPROP1";"CPROP2";"CPROP3";"CCODIVAL1";"CCODIVAL2";"CCODIVAL3";"CCODIPROP1";"CCODIPROP2";"CCODIPROP3";"XIVA";'."\r\n";
fwrite($fpcomanlin, $strlin);

$strlin ='"NCODICL";"CGENERE";"CNOM";"CNOMPROPI";"CNOMFI";"CCONTACTE";"CADRECA";"CADRECA2";"CPOSTAL";"CPOBLA";"CCOMARCA";"CPAIS";"CTELF1";"CTELF2";"CFAX";"CMOBIL1";"CMOBIL2";';
$strlin .= '"CEMAIL";"CNIF";"DNEIX";"CODIG";"CIDIOMA";"NCODIVEN";"XDTECL";"XDTEPP";"NTARIFA";"LEXIVA";"LREQ";"LIRPF";"CWEB";"CFPAG";"CDBENT";"CDBSUC";"CDBCTL";"CDBCTE";"CIBAN";';
$strlin .= '"CTIPTAR";"CNUMTAR";"CCADU";"CSECTOR";"CPORTS";"NCODITR";"DALTA";"CENV";"CNOMENV";"CADRENV";"CADRENV2";"CPENV";"CPOBENV";"CCOMENV";"CCODPAISENV";"CPAISENV";"CTELF1ENV";"CFAC";"CNOMFAC";"CADRFAC";"CADRFAC2";"CPFAC";"CPOBFAC";"CCOMFAC";"CPAISFAC";"CALIAS";'."\r\n";
fwrite($fpclients, $strlin);
		
$ncodicl = 1; 
$ncomandes = 0;

//llegim comandes no baixades 
if (empty($dat)){
  $strsql = "select * from " . $strnomdb . $strprefixtaules . "orders where CFACTUR <> 'S' ". $strwherebcoman." order by orders_id " ;
} else{
  $strsql = "select * from " . $strnomdb . $strprefixtaules . "orders where date_purchased >= '". $dat. "' ". $strwherebcoman." order by orders_id " ;
} 

//llegim una sola comanda per proves encara que ja s'hagi baixat 
//$baixanumcoman = 202;
if (! empty($baixanumcoman)){
  $strsql = "select * from " . $strnomdb . $strprefixtaules . "orders where orders_id = ". $baixanumcoman ." order by orders_id " ;
}
 
$resultc = mysqli_query($link, $strsql );
if ($resultc==FALSE)	{	echo $crlf . idioma("Error lectura orders","Error lectura orders","Read error in orders"). "  = " . mysqli_errno( $link).": ".mysqli_error($link). $crlf .$strsql;	die; } 
while ( $roworder = mysqli_fetch_array($resultc)) {
   $ntarifa = $ntarifaqfac;
   $cnif = "";
   if ( ($strtaulanif == 'orders') && (! empty($strNIF) ) ){$cnif = $roworder[$strNIF];}
	 //calias del client esta a comandes 
	 $caliascli = "";
	  if ( ! empty($strcalias) ) {$caliascli = $roworder[$strcalias];}	
  //agafem client
   $cnompropi = "";
   $cnom = "";
   $cnomfi ="";
   $ccontacte ="";
   $cprovin = "";
   $cpais = "";
   $strbaixarcomanda = "S";
    //mira si hi ha adreces diferents: Si no, ho posem a blancs
   $stradrenv = 'S'; 
 
  	if ( ( $roworder["delivery_street_address"]. $roworder["delivery_suburb"] . $roworder["delivery_postcode"] .$roworder["delivery_city"] .
	       $roworder["delivery_state"] .   $roworder["delivery_country"] ) == 
		 ( $roworder["customers_street_address"]. $roworder["customers_suburb"] . $roworder["customers_postcode"] .$roworder["customers_city"] .
	       $roworder["customers_state"] .   $roworder["customers_country"] ) ){		
		 $stradrenv = 'N'; 
		 /* no perque si ja existeixen a QFACWIN, s'han de comparar  
         $roworder["delivery_company"] = "";
		 $roworder["delivery_name"] = "";
         $roworder["delivery_street_address"] = "";
		 $roworder["delivery_suburb"] = "";
		 $roworder["delivery_postcode"] = "";
		 $roworder["delivery_city"] = "";
         $roworder["delivery_state"] = "";
		 $roworder["delivery_country"] = ""