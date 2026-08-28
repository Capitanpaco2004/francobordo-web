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
		 $roworder["delivery_country"] = ""; */
		   }
    $stradrfac = 'S'; 
	if ( ( $roworder["billing_street_address"]. $roworder["billing_suburb"] . $roworder["billing_postcode"] .$roworder["billing_city"] .
	       $roworder["billing_state"] .   $roworder["billing_country"] ) == 
		 ( $roworder["customers_street_address"]. $roworder["customers_suburb"] . $roworder["customers_postcode"] .$roworder["customers_city"] .
	       $roworder["customers_state"] .   $roworder["customers_country"] ) ){
		 $stradrfac = 'N'; 
		  /* no perque si ja existeixen a QFACWIN, s'han de comparar  
     $roworder["billing_company"] = "";
		 $roworder["billing_name"] = "";
     $roworder["billing_street_address"] = "";
		 $roworder["billing_suburb"] = "";
		 $roworder["billing_postcode"] = "";
		 $roworder["billing_city"] = "";
     $roworder["billing_state"] = "";
		 $roworder["billing_country"] = ""; */
	       }
  
   
   $strsql = " select * from " . $strnomdb . $strprefixtaules . "customers" ;
   $strsql .= " where customers_id = ".$roworder["customers_id"] . "  ";
   $result = mysqli_query( $link, $strsql )  or die ("Lectura customers " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
   if( $rowcli = mysqli_fetch_array($result)){ 
	 
	  //franquicies controlem que el client sigui de la franquicia
		if ( $strNFRANQ <> "N" ){
		  if ( $rowcli["NFRANQ"] <> (int)$strNFRANQ ) {$strbaixarcomanda = "N";}
		}
	  $cgenere = "H";
	  if ( $rowcli["customers_gender"] == "f") {$cgenere = "D";} 
	  $cnompropi = $rowcli["customers_firstname"];
	  $cnom = $rowcli["customers_lastname"];
	  $cnomfi = $cnompropi . ' '. $cnom;
	  //si empresa, empresa ??
	  //veure condicions per empreses si hi ha empresa i nif comença amb lletra, etc
	  if ( ! empty($roworder["customers_company"])) {
		   $cgenere = "E";
		   $ccontacte = $cnomfi;
		   $cnom = $roworder["customers_company"]; 
		   $cnompropi = "";
		   $cnomfi =  $cnom;
	  } 
	  //nif si esta a clients (SPPC)
    if ( ($strtaulanif == 'customers') && (! empty($strNIF) ) ) {$cnif = $rowcli[$strNIF];}		
      //mirem tarifa del client segons grup
   	if ( ($contribsppc == TRUE) && ( isset ( $rowcli["customers_group_id"]) ) ){
		    $ntarifa = array_search ( $rowcli["customers_group_id"], $strgrupcli);
				if ($ntarifa == ''){  $ntarifa = $ntarifaqfac;}
	  }
	

		
	  //llegim adreça principal
	  /*** les adreces les agafem de la comanda
	   $strsql = " select * from " . $strnomdb . ".address_book" ;
       $strsql .= " where address_book_id = ".$roworder["customers_default_address_id"] . "  ";
       $result = mysqli_query( $link, $strsql )  or die ("Lectura address_book " . mysqli_errno( $link).": ".mysqli_error($link). $crlf .$strsql);
       if( $rowadr = mysqli_fetch_array($result)){ 
         //si empresa, empresa ??
		 //veure condicions per empreses si hi ha empresa i nif comença amb lletra
		 if ( not empty($rowadr["enter_company"])) {
		   $cgenere = "E";
		   $ccontacte = $cnomfi;
		   $cnom = $rowcli["enter_company"]; 
		   $cnompropi = "";
		   $cnomfi =  $cnom;
		   } 
		  //busquem provincia
		  $strsql = " select * from " . $strnomdb . ".zones" ;
          $strsql .= " where zone_id = ".$roworder["entry_zone_id"] . "  ";
          $result = mysqli_query( $link, $strsql )  or die ("Lectura zone " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
          if( $rowprv = mysqli_fetch_array($result)){$cprovin = $rowprv["zone_name"]; }
		  //busquem pais
		  $strsql = " select * from " . $strnomdb . ".countries" ;
          $strsql .= " where countries_id = ".$roworder["entry_countrY_id"] . "  ";
          $result = mysqli_query( $link, $strsql )  or die ("Lectura zone " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
          if( $rowpais = mysqli_fetch_array($result)){$cpais = $rowpais["countries_name"]; }
       } //adreça principal
	   *******************/
	}// client

	if ($strbaixarcomanda == "S"){
			
		//si el nom esta buit l'agafem de la comanda
		 if ( empty($cnom) ) {
				if ( ! empty( $roworder["customers_company"] ) ) {$cnom = $roworder["customers_company"];}
			else { $cnom = $roworder["customers_name"]; }
				if ( empty($cnomfi) ) { $cnomfi = $cnom; }
		 }
		 
			//gravem client si no l'hem gravat ja
		/*** el baixem sempre amb codi = 
		 $cgravar = "S";
		 $qi = 0;
		 while ($qi < count($aclients)) {
				if ( $aclients[$qi] == $rowcli["customers_id"]){$cgravar = "N";} 
			$qi++;
		 }//while 
		 **/
		 // el baixem sempre amb codicl = $ncodicl
		 $cgravar = "S";
		 if ( $cgravar == "S") {
			//"NCODICL";"CGENERE";"CNOM";
			 $strlin = '"'. $ncodicl . '";"' . $cgenere . '";"' . canviaespecials($cnom);
			//"CNOMPROPI";"CNOMFI";"CCONTACTE";
			 $strlin .= '";"' . canviaespecials($cnompropi) . '";"' . canviaespecials($cnomfi) . '";"' . canviaespecials($ccontacte) ;
			//"CADRECA";"CADRECA2";"CPOSTAL";"CPOBLA";
				 $strlin .= '";"' . canviaespecials( $roworder["customers_street_address"]) .'";"'. canviaespecials($roworder["customers_suburb"]) . '";"' .  canviaespecials($roworder["customers_postcode"]) . '";"' . canviaespecials($roworder["customers_city"]) ;
			//"CCOMARCA";"CPAIS";"CTELF1"
			 $strlin .= '";"' .  canviaespecials($roworder["customers_state"]) . '";"' .  canviaespecials($roworder["customers_country"]) . '";"' . canviaespecials($rowcli["customers_telephone"]);
				//"CTELF2";"CFAX";"CMOBIL1";"CMOBIL2";
			 $strlin .= '";"' . $cbuit . '";"' . canviaespecials($rowcli["customers_fax"]) . '";"' . $cbuit . '";"' . $cbuit;
				// "CEMAIL";"CNIF";"DNEIX";
			 $strlin .= '";"' . canviaespecials($rowcli["customers_email_address"]) . '";"' . canviaespecials($cnif) . '";"' . data10($rowcli["customers_dob"]);
				//"CODIG";"CIDIOMA";"NCODIVEN";"XDTECL";"XDTEPP";
			 $strlin .= '";"0";"' . $cbuit . '";"0";"0";"0' ;
			//"NTARIFA";"LEXIVA";"LREQ";"LIRPF";
			$strlin .= '";"' . $ntarifa.'";"' . "N" . '";"N";"N' ;
				//"CWEB";"CFPAG";
			$strlin .= '";"' . $cbuit . '";"' . canviaespecials($roworder["payment_method"]) ;
			//"CDBENT";"CDBSUC";"CDBCTL";"CDBCTE";"CIBAN";
			$strlin .= '";"' . $cbuit. '";"' . $cbuit . '";"' . $cbuit .'";"' . $cbuit. '";"' . $cbuit;
				//"CTIPTAR";"CNUMTAR";"CCADU";
			 $strlin .= '";"' . $roworder["cc_type"] . '";"' .$roworder["cc_number"] . '";"' . $roworder["cc_expires"];  
				//"CSECTOR";"CPORTS";"NCODITR";"DALTA";"
			$strlin .= '";"' . $cbuit. '";"' . $cbuit . '";"0";"' . $cbuit;
			
				//Adreça d'enviament
			/* es fa abans
			$stradrenv = 'N';
			if ( $roworder["customers_name"] . $roworder["customers_company"]. $roworder["customers_street_address"]. $roworder["customers_suburb"].$roworder["customers_postcode"].$roworder["customers_company"].$roworder["customers_city"].$roworder["customers_state"].$roworder["customers_country"] <>
					 $roworder["delivery_name"] . $roworder["delivery_company"]. $roworder["delivery_street_address"]. $roworder["delivery_suburb"].$roworder["delivery_postcode"].$roworder["delivery_company"].$roworder["delivery_city"].$roworder["delivery_state"].$roworder["delivery_country"]) 
			 {  $stradrenv = 'S';	  }*/
		 $strcodPaisenv = '';
		 $strsql = " select countries_iso_code_2 from " . $strnomdb . $strprefixtaules . "countries" ;
     $strsql .= " where countries_name = '".$roworder["delivery_country"] . "'  ";
	   $resultpais = mysqli_query( $link, $strsql )  or die ("Lectura countries " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
 	   if( $rowpais = mysqli_fetch_array($resultpais)){ 
				$strcodPaisenv = $rowpais["countries_iso_code_2"];
    }
			 
       $strtelf1env = '';
			 // francobordo baixar telefon enviament
			 if ($intcltrac == 116) {	
				  $strtelf1env =  $roworder ["delivery_telephone"];
				  //si hi ha adreça d'enviament agafem el telf de la d'enviament
			/*	  if ( $stradrenv == 'S '){
				     $strsql = " select * from " . $strnomdb . ".address_book where address_book_id = ".$roworder["customers_default_address_id"] . "  ";
					   $result = mysqli_query( $link, $strsql )  or die ("Lectura address_book " . mysqli_errno( $link).": ".mysqli_error($link). $crlf .$strsql);
					   if( $rowadr = mysqli_fetch_array($result)){
					    }
	   			  }
				  */
			 }//francobordo
			 
				// CENV; CNOMENV";
			 $strlin .= '";"' . $stradrenv . '";"' .canviaespecials( trim ( $roworder["delivery_company"]. ' '. $roworder["delivery_name"])) ;
				//"CADRENV";"CADRENV2";"CPENV";"CPOBENV";
				 $strlin .= '";"' . canviaespecials($roworder["delivery_street_address"]) . '";"'. canviaespecials($roworder["delivery_suburb"] ) . '";"' .  canviaespecials($roworder["delivery_postcode"]) . '";"' . canviaespecials($roworder["delivery_city"]) ;
				// "CCOMENV";"CCODPAISENV";"CPAISENV";"CTELF1ENV"
				 $strlin .= '";"' .  canviaespecials($roworder["delivery_state"]) . '";"' . $strcodPaisenv.  '";"'.  canviaespecials($roworder["delivery_country"]) . '";"' . $strtelf1env ;
				 
			 //Adreça de facturacio
			 /* es fa abans
			 $stradrfac = 'N';
			 if ( $roworder["customers_name"] . $roworder["customers_company"]. $roworder["customers_street_address"]. $roworder["customers_suburb"].$roworder["customers_postcode"].$roworder["customers_company"].$roworder["customers_city"].$roworder["customers_state"].$roworder["customers_country"] <>
					 $roworder["billing_name"] . $roworder["billing_company"]. $roworder["billing_street_address"]. $roworder["billing_suburb"].$roworder["billing_postcode"].$roworder["billing_company"].$roworder["billing_city"].$roworder["billing_state"].$roworder["billing_country"]) 
			 {   $stradrfac = 'S';	  } */
			 
				// CFAC;"CNOMFAC";
				 $strlin .=  '";"' . $stradrfac . '";"' . canviaespecials( trim ( $roworder["billing_company"]. ' '. $roworder["billing_name"]) ) ;
				//"CADRFAC";"CADRFAC2";"CPFAC";"CPOBFAC";
					$strlin .= '";"' . canviaespecials( $roworder["billing_street_address"]) .'";"'. canviaespecials($roworder["billing_suburb"]) . '";"' .  canviaespecials($roworder["billing_postcode"]) . '";"' . canviaespecials($roworder["billing_city"]) ;
				 //"CCOMFAC";"CPAISFAC";" CALIAS";	 	
				 $strlin .= '";"' .  canviaespecials($roworder["billing_state"]) . '";"' .  canviaespecials($roworder["billing_country"]) . '";"' . $caliascli; 
				 $strlin .= '";'."\r\n";
			 
			 fwrite($fpclients, $strlin);
		
			 $aclients []= $rowcli["customers_id"];//sense res s'afegeix a l'array
		} //gravar client
		 
		 //------------------------------------
		//  llegim detall de la comanda
		//------------------------------------
		 $excentiva = "N";
		 $strsql = " select * from " . $strnomdb . $strprefixtaules . "orders_products " ;
		 $strsql .= " where orders_id = ".$roworder["orders_id"]."  order by orders_products_id  ";
		 $resultd = mysqli_query( $link, $strsql )  or die ("Lectura orders_products " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
		 $nnumlin = 10;
		 while ( $rowdet = mysqli_fetch_array($resultd)) {
				//busquem codiart del qfac
			/* no cal ?
			$strsql = " select * from " . $strnomdb . "products_to_qfacwin" ;
					$strsql .= " where products_id = ".$rowdet["products_id"] . "  ";
					$result = mysqli_query( $link, $strsql )  or die ("products_to_qfacwin " . mysqli_errno($link).": ".mysqli_error(). $crlf .$strsql);
					if( $rowart = mysqli_fetch_array($result)){ $ccodiart = $rowart[ "CCODIART"]; }
				*/
  		 $ccodiart = "";
			 //tipus d'IVA es determina però s'agafa de l'article.
			 $ntipiva = 0;

			 $strsql = " select * from " . $strnomdb . $strprefixtaules . "products" ;
			 $strsql .= " where products_id = ".$rowdet["products_id"] . "  ";
			 $result = mysqli_query($link, $strsql )  or die ("products " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
			 if ( $rowart = mysqli_fetch_array($result)){ 
				 $ccodiart = $rowart[ "CCODIART"];
				 //tipus iva del qfac
				 foreach( $tipo_imp_QFACWIN as $key => $valor ) {
					//echo $valor;
							if ($valor == $rowart["products_tax_class_id"]){ $ntipiva = $key;}
				 } //foreach	
				 //podem determinar la tarifa comparant els preus
			 } //producte trobat
				
					 //si IVA = 0 considerem la comanda excenta d'IVA
				 if (($marcarExcent0 == "S") and ($rowdet[ "products_tax"] == 0)) { $excentiva = "S";}
			
			 $npreu =  $rowdet["final_price"] ;
				 $nbasedte = $rowdet["products_quantity"] * $rowdet["final_price"];
			
			 if  ($ntip_oscom == 3){ //xt:Commerce guarda a final_price l'import * unitats
				 $npreu =  $rowdet["final_price"] / $rowdet["products_quantity"];
				 $nbasedte = $rowdet["final_price"] ;
				 }
	
				$cprop[0] = "";
				$cprop[1] = "";
				$cprop[2] = "";
				$ccodival[0] = "";
				$ccodival[1] = "";
				$ccodival[2] = "";
				$ccodiprop[0] = "";
				$ccodiprop[1] = "";
				$ccodiprop[2] = "";
						 
			 // si traspassar atributs, busquem els atributs de l'article
			if ($baixaratributscomandes == TRUE) {
			
				//contribucio grid attributes ja esta carregat 
				if ($contribgridattributes == TRUE) {
					$ccodival[ 0 ] = $rowdet [ "CCODIVAL1" ]; 
					$ccodiprop[0] = $rowdet [ "CCODIPROP1" ]; 
					$cprop[0]  = $rowdet [ "CPROP1" ]; 
					$ccodival[ 1 ] = $rowdet [ "CCODIVAL2" ]; 
					$ccodiprop[1] = $rowdet [ "CCODIPROP2" ]; 
					$cprop[1]  = $rowdet [ "CPROP2" ]; 
					if ( $ccodival[ 1 ] == '_VACIO') { 
						$ccodival[ 1 ] = "";
						$ccodiprop[1] = "";
						$cprop[1] = "";
					}  
					
				} else {
					//llegir orders_products_attributes i afegir linies en blanc amb les descripcions
					$strsqlat = " select * from " . $strnomdb . $strprefixtaules . "orders_products_attributes " ;
						$strsqlat .= " where orders_id = ".$roworder["orders_id"]." and orders_products_id = ".$rowdet["orders_products_id"]."  order by orders_products_attributes_id  ";
						$resultat = mysqli_query(  $link, $strsqlat)  or die ("Lectura orders_products_attributes " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsqlat);
		
						$atrib = 0;
						while ( $rowatrib = mysqli_fetch_array($resultat)) {
						if ($atrib <= 2){
							$cprop[ $atrib ] = trim ( $rowatrib["products_options"]) . ': '.  trim ( $rowatrib["products_options_values"]);
						if (! empty( $rowatrib["NIDATRIB"]) ){
							 //busquem l'atribut per id
							 $strsqlatr2 = "select products_id,options_id,v.CCODIVAL, CCODIPROP  from ". $strnomdb . $strprefixtaules ."products_attributes";
								$strsqlatr2 .= " left join ". $strnomdb . $strprefixtaules ."products_options p on options_id = products_options_id and p.language_id = ". $idioma1; 
								$strsqlatr2 .= " left join ". $strnomdb . $strprefixtaules ."products_options_values v on options_values_id = products_options_values_id and v.language_id = ". $idioma1. " where products_attributes_id = ". $rowatrib["NIDATRIB"];
							$resultatr2 = mysqli_query(  $link,$strsqlatr2)  or die ("Lectura orders_products_attributes " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsqlatr2);
							$rowatr2 = mysqli_fetch_array($resultatr2);
							$ccodival[ $atrib ] =  $rowatr2 [ "CCODIVAL" ]; 
							$ccodiprop[ $atrib ] =  $rowatr2 [ "CCODIPROP" ]; 
							
						} //existeix NIDATRIB
							$atrib ++;
					} //tres atributs  	
					 }//while atributs
					}//no grid attrivutes 
			} // traspassar atributs	
			 
				 //"DATAL";"NCODICL";"NNUMFRA";
				$strlin = '"'. data10( $roworder["date_purchased"]). '";"' .$ncodicl . '";"' . $rowdet["orders_id"];
				
				$cdescri = canviaespecials($rowdet["products_name"]); 
				if ( ! empty($cdescri) ) { //treiem html
					 $cdescri = preg_replace('/<(.+?)>/i', " ", $cdescri);
				}		 			
				//"NNUMLIN";"QUANT";"CCODIART";"CODIG";"CDESCRI";
				 $strlin .=  '";"'. $nnumlin . '";"'. $rowdet["products_quantity"] . '";"'. $ccodiart.'";"0";"'. $cdescri ;
					// "NPREU";"XDTEA";"NBASEDTEA";"NIMPDTEA";"LDTE";
				//23/11/2006 s'agafa final_price que conte el producte + els atributs
				// $strlin .=  '";"'. $rowdet["products_price"] . '";"0";"'. ($rowdet["products_quantity"] * $rowdet["products_price"]) . '";"0";"'.'S'; ;
					 $strlin .=  '";"'. $npreu . '";"0";"'. $nbasedte  . '";"0";"'.'S'; ;
				//"NTIPIVA";"NIMPORT";
				//23/11/2006 corregit 
				//$strlin .=  '";"'. $ntipIVA . '";"'. $rowdet["final_price"];
				$strlin .=  '";"'. $ntipiva . '";"'. ($rowdet["products_quantity"] * $rowdet["final_price"]);
			
				//"DVT";"NMESOSVT";"LSUPLI";"NPAQ";"NPES";"NPEST";"CLOT";	
				$strlin .=  '";"";"0";"N";"0";"0";"0";""';
				//"CPROP1";"CPROP2";"CPROP3";"CCODIVAL1";"CCODIVAL2";"CCODIVAL3";"CCODIPROP1";"CCODIPROP2";"CCODIPROP3";"XIVA";
				$strlin .=  ';"'. $cprop[0] .'";"' .$cprop[1] .'";"' .$cprop[2] .'";"' .  $ccodival[0] . '";"' .  $ccodival[1] . '";"' .  $ccodival[2] . '";"' .  $ccodiprop[0] . '";"' .  $ccodiprop[1] . '";"' .  $ccodiprop[2]  .'";"' .  $rowdet["products_tax"] . '"';
				$strlin .= ';' . "\r\n";	 			
				fwrite($fpcomanlin, $strlin);
			
				$nnumlin = $nnumlin + 10;
			
				if ($baixaratributscomandes == FALSE) {
				//llegir orders_products_attributes i afegir linies en blanc amb les descripcions
				$strsqlat = " select * from " . $strnomdb . $strprefixtaules . "orders_products_attributes " ;
					$strsqlat .= " where orders_id = ".$roworder["orders_id"]." and orders_products_id = ".$rowdet["orders_products_id"]."  order by orders_products_attributes_id  ";
					$resultat = mysqli_query(  $link, $strsqlat)  or die ("Lectura orders_products_attributes " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsqlat);
				// echo $strsqlat;
			
					while ( $rowatrib = mysqli_fetch_array($resultat)) {
					 $strconcepteatr = ' - '. trim ( $rowatrib["products_options"]) . ': '.  trim ( $rowatrib["products_options_values"]);
					//"DATAL";"NCODICL";"NNUMFRA";
				$strlin = '"'. data10( $roworder["date_purchased"]). '";"' .$ncodicl . '";"' . $rowdet["orders_id"];
				//"NNUMLIN";"QUANT";"CCODIART";"CODIG";"CDESCRI";
					$strlin .=  '";"'. $nnumlin . '";"0";"";"0";"'. canviaespecials( $strconcepteatr) ;
						// "NPREU";"XDTEA";"NBASEDTEA";"NIMPDTEA";"LDTE";
				 $strlin .=  '";"0";"0";"0";"0";"'.'N'; ;
				//"NTIPIVA";"NIMPORT";
				$strlin .=  '";"0";"0';
				
				//"DVT";"NMESOSVT";"LSUPLI";"NPAQ";"NPES";"NPEST";"CLOT";	
				$strlin .=  '";"";"0";"N";"0";"0";"0";""';
				//"CPROP1";"CPROP2";"CPROP3";"CCODIVAL1";"CCODIVAL2";"CCODIVAL3";"CCODIPROP1";"CCODIPROP2";"CCODIPROP3";"XIVA";
				$strlin .=  ';"";"";"";"";"";"";"";"";"";"0"';
				$strlin .= ';' . "\r\n";	 			
				fwrite($fpcomanlin, $strlin);
				
						$nnumlin = $nnumlin + 10;
				
				}//while atributs
		} //no traspassar atributs	
			
			}//while detall
		
		 //------------------------------------
			// llegim els totals de la comanda
		//------------------------------------
		
		$xivasubtotal = $xivatrans;
		//francobordo busquem tipus iva transport altres paisos  a ot_tax  que es on te el iva general. si tipus 10% te ot_tax_1
		if ( ($intcltrac == 116) and ( $strcodPaisenv <> 'ES') ){
			$strsql = " select title from " . $strnomdb . $strprefixtaules . "orders_total where class = 'ot_tax' and orders_id = ".$roworder["orders_id"];
			$result = mysqli_query( $link, $strsql )  or die ("Lectura orders_total ot_tax" . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
			if ( $rowtot = mysqli_fetch_array($result)) {
			  $strivatitol = str_replace ( $rowtot["title"], "IVA " , "");
				$strivatitol = str_replace ( $strivatitol, "%:" , "");
				$strivatitol = str_replace ( $strivatitol, "," , ".");
				if ( empty( $strivatitol) ) {$strivatitol = 0;}
				if (is_numeric($strivatitol)) { $xivasubtotal = $strivatitol; }
	  	}
		} //francobordo no esp
		
		$nsuplides = 0;
			$strsql = " select * from " . $strnomdb . $strprefixtaules . "orders_total " ;
			$strsql .= " where orders_id = ".$roworder["orders_id"];
			$result = mysqli_query( $link, $strsql )  or die ("Lectura orders_total " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
			while ( $rowtot = mysqli_fetch_array($result)) {
				if ( $rowtot["class"]== "ot_total"){ $ntotal = $rowtot["value"];}
			// base sii hi ha 2 ives esta barrejada i a mes te ports
			if ( $rowtot["class"]== "ot_subtotal"){ $nbase = $rowtot["value"];}
			// si hi ha 2 ives hi ha 2 registres i no es pot saber a quin correspon
			//if ( $rowtot["class"]== "ot_tax"){ $niva := $rowtot["value"] };
			
			//Gravem com suplides tot lo que no sigui ot_subtotal o ot_tax o ot_total
			$gravarsubtotal = "S";
			if ( ($rowtot["class"]== "ot_subtotal") or ($rowtot["class"]== "ot_tax") or ($rowtot["class"]== "ot_total") or
					($rowtot["class"]== "ot_bi") or ($rowtot["class"]== "ot_recargo") or
					 ( (($rowtot["class"]== "ot_custom")  or ($rowtot["class"]== "ot_distribuitors_iva")) and ( substr($rowtot["title"],0,3)== "IVA") ) // detall iva 4frags 
					){$gravarsubtotal = "N";} 
			if ($gravarsubtotal == "S"){
					$strsupli = "N";
				$strivasubtotal = $strivaenvio;
				//afegim ports com a suplides
				//si diferent de 0 o si ot_shipping baixem encara que sigui 0 (la central del perfume)
			if ( ($rowtot["value"] <> 0) or ( ($rowtot["value"] == 0 ) and ($rowtot["class"]== "ot_shipping") ) ) {
						 //4frags descompte combos
				 if ( ($rowtot["class"]== "ot_combos") and ( $rowtot["value"] > 0)) { $rowtot["value"] = $rowtot["value"] * -1;}
				 //zencart els descomptes estan en positiu
				 if ( ($ntip_oscom == 2) and ($rowtot["class"]== "ot_group_pricing") ) { $rowtot["value"] = $rowtot["value"] * -1;}
				 //contribucio vouchers i cupons posar en negatiu
				 if ($rowtot["class"]== "ot_coupon" or $rowtot["class"]== "ot_gv" or  $rowtot["class"] == "ot_lev_discount" or  $rowtot["class"] == "ot_xmembers" ) { $rowtot["value"] = $rowtot["value"] * -1;}
         // ull lwebdelperfume DUSNIC ot_disount_coupon ja ve ennegatiu
				 //if  ($rowtot["class"] == "ot_discount_coupon" ) { $rowtot["value"] = $rowtot["value"] * -1;} 
				 //CRE  descomptes per punts
				 if ($rowtot["class"]== "ot_redemptions" ) { $rowtot["value"] = $rowtot["value"] * -1;}
				 //aprendechino
			  if ($rowtot["class"]== "ot_discount_coupon4" ) { $strivasubtotal = "1"; }
			  if ($rowtot["class"]== "ot_discount_coupon21" ) { $strivasubtotal = "2"; }
					
				 if (($baixarcomsuplides == "S") and ($rowtot["class"]<> "ot_group_pricing") ){
						$nsuplides = $nsuplides + $rowtot["value"];
					 $strsupli = "S";
					 $strivasubtotal = "0";
				 }	
				 $nimport = $rowtot["value"];
				 //recalcular : treure l'iva a les despeses d'enviament 
				 //ot_surcharge son gastos de reembolso (CRE)
				 //ot_fixed_payment_chg i ot_loworderfee (reembolso i comanda minima) dracotienda
				 //ot_shipsurcharge i ot_shipsurchargemrw (rembolso) aprendechino.com
				 //ot_coupon i ot_gv
				 if (($recalenviament == "S") and ( ( $rowtot["class"]== "ot_shipping") or ( $rowtot["class"]== "ot_surcharge")  or ( $rowtot["class"]== "ot_fixed_payment_chg")   or ( $rowtot["class"]== "ot_loworderfee")   or ( $rowtot["class"]== "ot_shipsurcharge")  or ( $rowtot["class"]== "ot_shipsurchargemrw") or ( $rowtot["class"]== "ot_coupon") or ( $rowtot["class"]== "ot_gv") ) ) {
					$nimport = $nimport / (1+ ( $xivasubtotal /100) ); 
				}		
				$cdescri = $rowtot["title"];
				//algunes botigues posen una imatge a la descripció de les formes d'enviament
				// la treiem
				if ( ! empty($cdescri) and  ($rowtot["class"]== "ot_shipping") ) { 
							 //php 5.3 no va 
						// $cdescri = eregi_replace("<img([^>]+)>", "", $cdescri); 
					 $cdescri = preg_replace('/<img (.+?)>/i', "", $cdescri);
				}		 
					 
				//patafarmaweb DUSNIC ot_discount_coupon  camp tax conte json amb %iva: base, %iva2: base2, ...
				if ($intcltrac == 141) { 
					if  ($rowtot["class"] == "ot_discount_coupon" ) {
						$abases = json_decode( $rowtot["tax"] ,true);
						// a lo bestia: tipiva 1 = 10%, tipiva 2= 21% tipiva3 = 4%
						$i = 1;
						foreach($abases as $xiva=>$nbase) {
						//aixo serveix per tots els tipus de subtotals
							if ($xiva == 10) {$strivasubtotal = "1";}
							if ($xiva == 21) {$strivasubtotal = "2";}
							if ($xiva == 4) {$strivasubtotal = "3";}
							$nimport = $nbase;
							//desglossar ot_discount_coupon
							$nimport = $nimport * -1; //ull el desglos esta en positiu
							// gravem totes menys la ultima
							if (count($abases) >= $i +1){
								//"DATAL";"NCODICL";"NNUMFRA";
								$strlin = '"'.data10( $roworder["date_purchased"]). '";"' .$ncodicl . '";"' . $roworder["orders_id"];
								//"NNUMLIN";"QUANT";"CCODIART";"CODIG";"CDESCRI";
								$strlin .=  '";"'. $nnumlin . '";"0";'. '"";' . '"0";"'. canviaespecials( $cdescri) ;
								// "NPREU";"XDTEA";"NBASEDTEA";"NIMPDTEA";"LDTE";
								$strlin .=  '";"'. '0' . '";"0";"'. $nimport. '";"0";"'.'N'; ;
								//"NTIPIVA";"NIMPORT";
								$strlin .=  '";"'. $strivasubtotal. '";"'. $nimport;
											
								//"DVT";"NMESOSVT";"LSUPLI";"NPAQ";"NPES";"NPEST";"CLOT";	
								$strlin .=  '";"";"0";"'.$strsupli.'";"0";"0";"0";""';
								//"CPROP1";"CPROP2";"CPROP3";"CCODIVAL1";"CCODIVAL2";"CCODIVAL3";"CCODIPROP1";"CCODIPROP2";"CCODIPROP3";"XIVA";
								$strlin .=  ';"";"";"";"";"";"";"";"";"";"'. $xiva .'"';
								$strlin .= ';' . "\r\n";					
								fwrite($fpcomanlin, $strlin);						
							} //mes d'una base
							$i ++;
						} //for
				  } //ot_discount_coupon
					
				} //parafarmaweb
				
				//"DATAL";"NCODICL";"NNUMFRA";
				$strlin = '"'.data10( $roworder["date_purchased"]). '";"' .$ncodicl . '";"' . $roworder["orders_id"];
				//"NNUMLIN";"QUANT";"CCODIART";"CODIG";"CDESCRI";
					$strlin .=  '";"'. $nnumlin . '";"0";'. '"";' . '"0";"'. canviaespecials( $cdescri) ;
						// "NPREU";"XDTEA";"NBASEDTEA";"NIMPDTEA";"LDTE";
					$strlin .=  '";"'. '0' . '";"0";"'. $nimport. '";"0";"'.'N'; ;
				//"NTIPIVA";"NIMPORT";
				$strlin .=  '";"'. $strivasubtotal. '";"'. $nimport;
							
				//"DVT";"NMESOSVT";"LSUPLI";"NPAQ";"NPES";"NPEST";"CLOT";	
				$strlin .=  '";"";"0";"'.$strsupli.'";"0";"0";"0";""';
				//"CPROP1";"CPROP2";"CPROP3";"CCODIVAL1";"CCODIVAL2";"CCODIVAL3";"CCODIPROP1";"CCODIPROP2";"CCODIPROP3";"XIVA";
				$strlin .=  ';"";"";"";"";"";"";"";"";"";"'. $xivasubtotal .'"';
				$strlin .= ';' . "\r\n";					
				fwrite($fpcomanlin, $strlin);
			 }// value <> 0
			 }//gravarsubtotal
			
		 } //while totals
		 
		 //busquem notes de la comanda
		 $ncodistatus = 1;
		 if ($intcltrac == 138) {  $ncodistatus = 14;}// motorraiz baixem codi 14
		 $mnotes = '';
		 $strwherestatus = " and orders_status_id = ". $ncodistatus ;
		 if ($intcltrac == 116) { $strwherestatus = "  order by orders_status_id";} //francobordo agafa el primer
		 $strsql = " select * from " . $strnomdb . $strprefixtaules . "orders_status_history " ;
		 $strsql .= " where orders_id = ".$roworder["orders_id"] . $strwherestatus. "  ";
		 $result = mysqli_query($link, $strsql )  or die ("orders_status_hitory " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
		 if( $rownot = mysqli_fetch_array($result)){ $mnotes = canviaespecials($rownot[ "comments"]); }
	
	 	 $strports = "";
		 $strnnumfp = "0";
		 //francobordo baixem customer_service_id (venedor) a CPORTS
		 if ($intcltrac == 116) {
		   $strports = $roworder["customer_service_id"];
			 $strnnumfp = $roworder["orders_status"]; //baixem status de la comanda al camp nnumfp
		 }
		 //------------------------------------
		// gravem comanda
		//------------------------------------
		//CFACTUR; "NNUMFRA";"DATFRA";"NCODICL";
		 $strlin = '"'.$roworder["CFACTUR"]. '";"'.$roworder["orders_id"]. '";"' .data10( $roworder["date_purchased"]) . '";"' .$ncodicl;
		// "CNOMCLI";"XIVA1";"XIVA2";"XIVA3";"LREQ";"XREQ1";"XREQ2";"XREQ3";
		 $strlin .=  '";"'. canviaespecials($cnomfi) . '";"0";"0";"0";"N";"0";"0";"0';
		//	"NBASE1";"NBASE2";"NBASE3";"NIVA1";"NIVA2";"NIVA3";"NREQ1";"NREQ2";"NREQ3";"XIRPF";"NIRPF"
			 $strlin .=  '";"0";"0";"0";"0";"0";"0";"0";"0";"0";"0";"0';
		//;"NTOTAL";"XDTE";"NBASEDTE";"NIMPDTE";"XDTEPP";"NBASEDTPP";"NIMPDTPP";
		 $strlin .=  '";"'. $ntotal . '";"0";"0";"0";"0";"0";"0';
		//"NTARIFA";"NCODIVEN";"NNUMFP";"NSUPLI";
		 $strlin .=  '";"'. $ntarifa . '";"0";"'. $strnnumfp .'";"'. $nsuplides ; //ports com suplides
		 
		 $cdataen = "";
		 //dataentrega es un char que te una data en format numeric
			 if (! empty($strDATAEN)){$cdataen = date( "d/m/Y",$roworder[$strDATAEN]);}
		 
		//"MNOTES";"NPES";"NPAQ";"LEXIVA";"CPORTS";"NCODITR";"CFPAG";"DATAEN";
		 $strlin .=  '";"'. $mnotes. '";"0";"0";"'. $excentiva.'";"'. $strports . '";"0";"'. canviaespecials($roworder["payment_method"]). '";"'.  $cdataen ; 
			$strlin .= '";' ."\r\n";	
		fwrite($fpcoman, $strlin);  	 
	
		//numerador de clients
		$ncodicl++;
		$ncomandes++;	
	} //baixar la comanda
	
 } // while comandes
		 
	

//-----------------------------------------------------
// francobordo baixar rma
//-----------------------------------------------------
if ($intcltrac == 116) {	
	$filerma = $strdircreatemporals  . "wrma.txt"; 
	if(!$fprma = fopen ($filerma ,"w+"))	{
		 print $filerma.  "Imposible crear archivo para RMA(otorgue permisos 777 a la carpeta de imagenes). Proceso cancelado";
		 die;
	}
	$strlin = '"IDRMA";"IDCUSTOM";"IDCOMAN";"DATA";"CNIF";"CEMAIL";"QUANT";"CCODIART";"CMOTDEV";"MNOTES";'."\r\n";
	fwrite($fprma, $strlin);
	
	$nrma = 0;

  $strsql = "select r.*, d.text,p.CCODIART from rma r left join rma_options_returns_description d on d.id = r.option_return left join products p on  p.products_id = r.products_id where CFACTUR <> 'S' order by id_rma " ;
 
	$resultc = mysqli_query($link, $strsql );
	if ($resultc==FALSE)	{	echo $crlf . "Error lectura rma  = " . mysqli_errno( $link).": ".mysqli_error($link). $crlf .$strsql;	die; } 
	while ( $rowrma = mysqli_fetch_array($resultc)) {
		 
		//'"IDRMA";"IDCUSTOM";"IDCOMAN";"DATA";
		$strlin = '"'.$rowrma["id_rma"]. '";"'.$rowrma["customers_id"]. '";"'.$rowrma["orders_id"]. '";"' .data10( $rowrma["date_added"])  ;
		// "CNIF";"CEMAIL";
	  $strlin .=  '";"'. canviaespecials($rowrma["billing_nif"]) . '";"' .  canviaespecials($rowrma["customers_email_address"]) ;
		// "QUANT";"CCODIART";
	  $strlin .=  '";"'. $rowrma["quantity"] . '";"' .  canviaespecials($rowrma["CCODIART"]) ;	
		// "CMOTIU";"MOBSER"
	  $strlin .=  '";"'. canviaespecials($rowrma["text"]) . '";"' .  canviaespecials($rowrma["comments"]) ;
		$strlin .= '";' ."\r\n";	
		fwrite($fprma, $strlin);  	 
	
		//numerador de clients
		$nrma++;
			
	} //while rma
  fclose ($fprma);
	chmod ($filerma, 0777);
	
} //francobordo rma

fclose ($fpcoman);
fclose ($fpcomanlin);
fclose ($fpclients);		

//canviem privilegis si no queden com a propietari nobody y en alguns hosts no deixa baixar amb ftp
chmod ($filecoman, 0777);
chmod ($filecomanlin, 0777);
chmod ($fileclients, 0777);
		 

 
 $temps = time()- $ini;
echo idioma("Selecció de Comandes per baixar: ","Selección de pedidos para bajar: ","Order Selection for downloading "). $ncomandes;
echo  ". " . idioma("Finalitzat correctament en ","Finalizado correctamente en ","Successfully completed in " ). $temps . idioma(" segons"," segundos", " seconds"). $crlf ;
echo 'codret=0';

 
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
 
 // ---------------------------------------------------------- 
//  retorna literal en funcio de l'idioma  de la web
// ---------------------------------------------------------- 
function canviaespecials($a) {

$a= str_replace ( "\r\n", "\crlf" , (string)$a);  //canviat per treure warnings de str_replace a php 8: $a= str_replace ( "\r\n", "\crlf" , $a);
$a= str_replace ( "\r", "\crlf" , (string)$a);
$a= str_replace ( "\n", "\crlf" , (string)$a);
// ull si hi ha cometes escapades \" es transformen en \\"  i al pujar tornen amb \\" i peta
// les convertim primer:
$a= str_replace ( '\"', '"' , (string)$a);

$a= str_replace ( '"', '#2cometa#' , (string)$a);
$a= str_replace ( "'", '#1cometa#' , (string)$a);

$a= addslashes ( $a ); //sustitueix " ' \ i null per \' \" \\ i\nul? 

 //reemplaçem &nbsp; per espai i accents ja que algunes contribucions d'enviament tenen els accents posats
$a= str_replace ( "&nbsp;", " " , (string)$a);
$a= str_replace ( "&aacute;", "á" , (string)$a); 
$a= str_replace ( "&agrave;", "à" , (string)$a); 
$a= str_replace ( "&eacute;", "é" , (string)$a); 
$a= str_replace ( "&egrave;", "è" , (string)$a); 
$a= str_replace ( "&iacute;", "í" , (string)$a); 
$a= str_replace ( "&oacute;", "ó" , (string)$a); 
$a= str_replace ( "&ograve;", "ò" , (string)$a); 
$a= str_replace ( "&uacute;", "ú" , (string)$a); 


return $a;
} 


// ---------------------------------------------------------- 
//  posa un datetime (aaaa-mm-dd hh:mm:ss) en format dd/mm/aaaa
// ---------------------------------------------------------- 
function data10($dat) {
 if ( ! empty($dat)) {
    $dat =   substr( $dat,8,2). "/".  substr( $dat,5,2) . "/". substr( $dat,0,4);}

return $dat;
}  
?>