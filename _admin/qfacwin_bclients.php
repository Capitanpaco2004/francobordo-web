<?php /*
 QSOFT
 http://www.qsoftnet.com
 http://www.qinvoicing.com

 Catal�:
 Baixa clients sense comandes d'una botiga d'osCommerce 2.2-MS2 i posterior a  QFACWIN

 Espa�ol:
 Traspaso autom�tico de clientes sin pedidos de osCommerce 2.2-MS2 y posterior a QFACWIN.

 English:
 QINVOICING integration with osCommerce 2.2-MS2.

 (c) Autor: Quim Herrera Joancomarti
 qhe@mailqs.com

 */
$acategoriesid = [];
$aproductesid  = [];

require('qfacwin_cfg.php');
include('includes/configure.php');


//if (empty($idioma)) { $idioma= "E";}
if (!isset($_GET['idioma'])) {
	$idioma = "A";
} else {
	$idioma = $_GET['idioma'];
}

if (!isset($_GET['nav'])) {
	die (idioma('Error en la crida: nav', 'Error en la llamada: nav', 'Parameter error: nav'));
} else {
	$nav = $_GET['nav'];
}


$ini = time();

$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

//if (empty($descri)) { $descri= "S";}
if (!isset($_GET['descri'])) {
	$descri = "S";
} else {
	$dat = $_GET['descri'];
}


$crlf = "\r\n"; // sortida qfacwin
if ($nav == 1) {
	$crlf = "<br>";
} //sortida navegador

//fem c�pies
$datacopia = date("YmdHi", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
$cbuit     = "";

//$strnomdb = DB_DATABASE .'.'; dona error amb bases de dades que contene guions al nom: sonimax-bcn
$strnomdb = "";

//-----------------------------------------------------------
// marcar processats
//-----------------------------------------------------------
if (!isset($_GET['fets'])) {
	$fets = "";
} else {
	$fets = $_GET['fets'];
}

if (!empty($fets)) {
	$strclientsfets = '';
	include('qfacwin_clifets.php');//te la variable $strclientsfets
	$strsql = "update " . $strnomdb . $strprefixtaules . "customers set CFACTUR = 'S' where customers_id in ( " . $strclientsfets . " ) ";
	$result = mysqli_query($link, $strsql) or die ("update customers " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
	echo $crlf . idioma("Clients marcats com a processades", "Clientes marcados como procesados", "Customers checked as processed") . $crlf;
	die;
}//ve processat

//carpeta per crear temporals, si no ve, a images
if (empty($strdircreatemporals)) {
	$strdircreatemporals = DIR_FS_CATALOG_IMAGES;
}

$aclients = [];
//-----------------------------------------------------
// comandes
//-----------------------------------------------------

//echo $crlf. idioma("Migraci� de dades existents a la botiga d'osCommerce a QFACWIN:",
//       "Migraci�n de los datos existentes en osCommerce:").$crlf ;
echo $crlf . idioma("Processant clients a baixar:", "Procesando clientes a bajar:", "Processing customers to download") . $crlf;


$fileclients = $strdircreatemporals . "wclients.txt";
if (!$fpclients = fopen($fileclients, "w+")) {
	print $fileclients . idioma("Impossible crear l''arxiu (doneu permisos 777 al directori d'imatges).Proc�s cancel.lat", "Imposible crear archivo(otorgue permisos 777 a la carpeta de imagenes). Proceso cancelado", "Impossible to create the file (give 777 permissions to the images directory). Process cancelled");
	die;
}


$strlin = '"NCODICL";"CGENERE";"CNOM";"CNOMPROPI";"CNOMFI";"CCONTACTE";"CADRECA";"CPOSTAL";"CPOBLA";"CCOMARCA";"CPAIS";"CTELF1";"CTELF2";"CFAX";"CMOBIL1";"CMOBIL2";';
$strlin .= '"CEMAIL";"CNIF";"DNEIX";"CODIG";"CIDIOMA";"NCODIVEN";"XDTECL";"XDTEPP";"NTARIFA";"LEXIVA";"LREQ";"LIRPF";"CWEB";"CFPAG";"CDBENT";"CDBSUC";"CDBCTL";"CDBCTE";"CIBAN";';
$strlin .= '"CTIPTAR";"CNUMTAR";"CCADU";"CSECTOR";"CPORTS";"NCODITR";"DALTA";"CENV";"CNOMENV";"CADRENV";"CPENV";"CPOBENV";"CCOMENV";"CPAISENV";"CFAC";"CNOMFAC";"CADRFAC";"CPFAC";"CPOBFAC";"CCOMFAC";"CPAISFAC";"CALIAS";' . "\r\n";
fwrite($fpclients, $strlin);

$ncodicl  = 1;
$nclients = 0;
//llegim clients sense comandes no baixats

$strsql = "select * from " . $strnomdb . $strprefixtaules . "customers where CFACTUR <> 'S' and customers_id not in (select customers_id from orders group by customers_id)";
//franquicies controlem que el client sigui de la franquicia
if ($strNFRANQ <> "N") {
	$strsql .= ' and NFRANQ = ' . $strNFRANQ;
}
$strsql .= " order by customers_id ";

$resultc = mysqli_query($link, $strsql);
if ($resultc == false) {
	echo $crlf . idioma("Error lectura customers", "Error lectura customers", "Read error in customers") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
	die;
}
while ($rowcli = mysqli_fetch_array($resultc)) {
	$cnif = "";
	//  if ( ($strtaulanif == 'orders') && (! empty($strNIF) ) ){$cnif = $roworder[$strNIF];}
	//agafem client
	$cnompropi = "";
	$cnom      = "";
	$cnomfi    = "";
	$ccontacte = "";
	$cprovin   = "";
	$cpais     = "";

	//mira si hi ha adreces diferents: Si no, ho posem a blancs
	$stradrenv = 'N';
	$stradrfac = 'N';
	$ncodicl   = $rowcli["customers_id"]; //agafem l'id per marcar-lo despres com a baixat
	$cgenere   = "H";
	if ($rowcli["customers_gender"] == "f") {
		$cgenere = "D";
	}
	$cnompropi = $rowcli["customers_firstname"];
	$cnom      = $rowcli["customers_lastname"];
	$cnomfi    = $cnompropi . ' ' . $cnom;

	//nif si esta a clients (SPPC)
	if (($strtaulanif == 'customers') && (!empty($strNIF))) {
		$cnif = $rowcli[$strNIF];
	}
	//mirem tarifa del client segons grup
	if (($contribsppc == true) && (isset ($rowcli["customers_group_id"]))) {
		$ntarifa = array_search($rowcli["customers_group_id"], $strgrupcli);
	}


	//llegim adre�a principal
	if (!empty($rowcli["customers_default_address_id"])) {
		$strsql = " select * from " . $strnomdb . $strprefixtaules . "address_book";
		$strsql .= " where address_book_id = " . $rowcli["customers_default_address_id"] . "  ";
		$result = mysqli_query($link, $strsql) or die ("Lectura address_book " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
		if ($rowadr = mysqli_fetch_array($result)) {
			//si empresa, empresa
			//veure condicions per empreses si hi ha empresa i nif comen�a amb lletra
			if (!empty($rowadr["entry_company"])) {
				$cgenere   = "E";
				$ccontacte = $cnomfi;
				$cnom      = $rowadr["entry_company"];
				$cnompropi = "";
				$cnomfi    = $cnom;
			}
			//busquem provincia
			$strsql = " select * from " . $strnomdb . ".zones";
			$strsql .= " where zone_id = " . $rowadr["entry_zone_id"] . "  ";
			$result = mysqli_query($link, $strsql) or die ("Lectura zone " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
			if ($rowprv = mysqli_fetch_array($result)) {
				$cprovin = $rowprv["zone_name"];
			}
			//busquem pais
			$strsql = " select * from " . $strnomdb . ".countries";
			$strsql .= " where countries_id = " . $rowadr["entry_country_id"] . "  ";
			$result = mysqli_query($link, $strsql) or die ("Lectura zone " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
			if ($rowpais = mysqli_fetch_array($result)) {
				$cpais = $rowpais["countries_name"];
			}
		} //adre�a principal trobada
	} //no buida


	// el baixem sempre amb codicl = $ncodicl

	//"NCODICL";"CGENERE";"CNOM";
	$strlin = '"' . $ncodicl . '";"' . $cgenere . '";"' . canviaespecials($cnom);
	//"CNOMPROPI";"CNOMFI";"CCONTACTE";
	$strlin .= '";"' . canviaespecials($cnompropi) . '";"' . canviaespecials($cnomfi) . '";"' . canviaespecials($ccontacte);
	//"CADRECA";"CPOSTAL";"CPOBLA";
	$strlin .= '";"' . canviaespecials($rowadr["entry_street_address"]) . ' ' . canviaespecials($rowadr["entry_suburb"]) . '";"' . canviaespecials($rowadr["entry_postcode"]) . '";"' . canviaespecials($rowadr["entry_city"]);
	//"CCOMARCA";"CPAIS";"CTELF1"
	$strlin .= '";"' . $cprovin . '";"' . $cpais . '";"' . canviaespecials($rowcli["customers_telephone"]);
	//"CTELF2";"CFAX";"CMOBIL1";"CMOBIL2";
	$strlin .= '";"' . $cbuit . '";"' . canviaespecials($rowcli["customers_fax"]) . '";"' . $cbuit . '";"' . $cbuit;
	// "CEMAIL";"CNIF";"DNEIX";
	$strlin .= '";"' . canviaespecials($rowcli["customers_email_address"]) . '";"' . canviaespecials($cnif) . '";"' . data10($rowcli["customers_dob"]);
	//"CODIG";"CIDIOMA";"NCODIVEN";"XDTECL";"XDTEPP";
	$strlin .= '";"0";"' . $cbuit . '";"0";"0";"0';
	//"NTARIFA";"LEXIVA";"LREQ";"LIRPF";
	$strlin .= '";"' . $ntarifa . '";"' . "false" . '";"false";"false';
	//"CWEB";"CFPAG";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit;
	//"CDBENT";"CDBSUC";"CDBCTL";"CDBCTE";"CIBAN";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit . '";"' . $cbuit . '";"' . $cbuit . '";"' . $cbuit;
	//"CTIPTAR";"CNUMTAR";"CCADU";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit . '";"' . $cbuit;
	//"CSECTOR";"CPORTS";"NCODITR";"DALTA";"
	$strlin .= '";"' . $cbuit . '";"' . $cbuit . '";"0";"' . $cbuit;

	//Adre�a d'enviament

	// CENV; CNOMENV";
	$strlin .= '";"' . $stradrenv . '";"' . $cbuit;
	//"CADRENV";"CPENV";"CPOBENV";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit . '";"';
	// "CCOMENV";"CPAISENV";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit;

	//Adre�a de facturacio

	// CFAC;"CNOMFAC";
	$strlin .= '";"' . $stradrfac . '";"' . $cbuit;
	//"CADRFAC";"CPFAC";"CPOBFAC";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit . '";"' . $cbuit;

	//calias del client esta a comandes
	$caliascli = "";

	//"CCOMFAC";"CPAISFAC";" CALIAS";
	$strlin .= '";"' . $cbuit . '";"' . $cbuit . '";"' . $caliascli;
	$strlin .= '";' . "\r\n";

	fwrite($fpclients, $strlin);

	$nclients = $nclients + 1;


} // while clients


//-----------------------------------------------------
// dades actualitzades de clients sense comanda
//-----------------------------------------------------

fclose($fpclients);

//canviem privilegis si no queden com a propietari nobody y en alguns hosts no deixa baixar amb ftp
chmod($fileclients, 0777);


$temps = time() - $ini;
echo idioma("Selecci� de Clients sense Comandes per baixar: ", "Selecci�n de clientes sin pedidos para bajar: ", "Customers Selection for downloading ") . $nclients;
echo ". " . idioma("Finalitzat correctament en ", "Finalizado correctamente en ", "Successfully completed in ") . $temps . idioma(" segons", " segundos", " seconds") . $crlf;
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

	$a = str_replace("\r\n", "\crlf", $a);
	$a = str_replace("\r", "\crlf", $a);
	$a = str_replace("\n", "\crlf", $a);
	// ull si hi ha cometes escapades \" es transformen en \\"  i al pujar tornen amb \\" i peta
	// les convertim primer:
	$a = str_replace('\"', '"', $a);

	$a = str_replace('"', '#2cometa#', $a);
	$a = str_replace("'", '#1cometa#', $a);

	$a = addslashes($a); //sustitueix " ' \ i null per \' \" \\ i\nul?

	//reempla�em &nbsp; per espai i accents ja que algunes contribucions d'enviament tenen els accents posats
	$a = str_replace("&nbsp;", " ", $a);
	$a = str_replace("&aacute;", "�", $a);
	$a = str_replace("&agrave;", "�", $a);
	$a = str_replace("&eacute;", "�", $a);
	$a = str_replace("&egrave;", "�", $a);
	$a = str_replace("&iacute;", "�", $a);
	$a = str_replace("&oacute;", "�", $a);
	$a = str_replace("&ograve;", "�", $a);
	$a = str_replace("&uacute;", "�", $a);


	return $a;
}


// ----------------------------------------------------------
//  posa un datetime (aaaa-mm-dd hh:mm:ss) en format dd/mm/aaaa
// ----------------------------------------------------------
function data10($dat) {
	if (!empty($dat)) {
		$dat = substr($dat, 8, 2) . "/" . substr($dat, 5, 2) . "/" . substr($dat, 0, 4);
	}

	return $dat;
}

?>
