<?php /*
 QSOFT
 http://www.qsoftnet.com
 http://www.qinvoicing.com

 Catal�:
 Trasp�s autom�tic de dades del programa de facturaci� QFACWIN a osCommerce 2.2-MS2.

 Espa�ol:
 Traspaso autom�tico de datos del programa de facturaci�n QFACWIN a osCommerce 2.2-MS2.

 English:
 QINVOICING integration with osCommerce 2.2-MS2.

 (c) Autor: Quim Herrera Joancomarti
 qhe@mailqs.com

 */

$tip = $_GET['tip'];

if (!isset($_GET['idioma'])) {
	$idioma = "A";
} else {
	$idioma = $_GET['idioma'];
}

$strprefixtaules = isset($_GET['prefix']) ? $_GET['prefix'] : '';

//include('qfacwin_cfg.php');
include('includes/configure.php');

//$strnomdb = DB_DATABASE .'.'; dona error amb bases de dades que contene guions al nom: sonimax-bcn
$strnomdb = "";

// busca articles
if ($tip == "A") {
	if (!isset($_GET['ccodiart'])) {
		echo "qfacwin_edit: error(1)";
		die;
	} else {
		$ccodiart = $_GET['ccodiart'];
	}

	$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

	//llegim producte per codiart
	$idproducte = 0;
	$strsql     = "select * from " . $strnomdb . $strprefixtaules . "products where CCODIART = '" . $ccodiart . "'";
	$result     = mysqli_query($link, $strsql);
	if ($result == false) {
		echo idioma("Error lectura products", "Error lectura products", "Read error in products") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	if ($rowartic = mysqli_fetch_array($result)) {
		$idproducte = $rowartic["products_id"];
	} else {
		echo idioma('Error: producte no trobat a osCommerce:', 'Error producto no encontrado en osCommerce:', "Error: product not found in osCommerce:") . ' ' . $ccodiart;
		die;
	}
	//llegim categoria del producte
	$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_to_categories where products_id = " . $idproducte . " ";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}

	$strpath = '';
	if ($rowcater = mysqli_fetch_array($result)) {
		$strpath = $rowcater["categories_id"];
	}


	//llegim la categoria
	$strsql = "select * from " . $strnomdb . $strprefixtaules . "categories where categories_id = " . $rowcater["categories_id"] . " ";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	$rowcater = mysqli_fetch_array($result);
	$pare     = $rowcater["parent_id"];
	//llegim els pares per determinar la ruta
	while ($pare > 0) {
		//$strpath .= "_".$pare;
		$strpath = $pare . "_" . $strpath;
		$strsql  = "select * from " . $strnomdb . $strprefixtaules . "categories where categories_id = " . $pare . " ";
		$result  = mysqli_query($link, $strsql);
		if ($result == false) {
			echo idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
			die;
		}
		$rowcater = mysqli_fetch_array($result);
		$pare     = $rowcater["parent_id"];
	} //while pare

	$strurl = "categories.php?cPath=" . $strpath . "&pID=" . $idproducte . "&action=new_product";
	print Header("Location: " . $strurl);
	die;
} // article


// busca categories
if ($tip == "G") {
	if (!isset($_GET['codig'])) {
		echo "qfacwin_edit: error(2)";
		die;
	} else {
		$codig = $_GET['codig'];
	}

	$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

	//llegim producte per codiart
	$idgrup = 0;
	$strsql = "select * from " . $strnomdb . $strprefixtaules . "categories where CODIG = '" . $codig . "'";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo idioma("Error lectura", "Error lectura", "Read error") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	if ($rowartic = mysqli_fetch_array($result)) {
		$idgrup = $rowartic["categories_id"];
	} else {
		echo idioma('Error: categoria no trobada a osCommerce:', 'Error categoria no encontrada en osCommerce:', "Error: category not found in osCommerce:") . ' ' . $codig;
		die;
	}


	$strurl = "categories.php?cPath=&cID=" . $idgrup . "&action=edit_category";
	print Header("Location: " . $strurl);
	die;
} // categories


// busca fabricants
if ($tip == "F") {
	if (!isset($_GET['codifab'])) {
		echo "qfacwin_edit: error(3)";
		die;
	} else {
		$codifab = $_GET['codifab'];
	}

	$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

	//llegim producte per codiart
	$idgrup = 0;
	$strsql = "select * from " . $strnomdb . $strprefixtaules . "manufacturers where NCODIFAB = '" . $codifab . "'";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo idioma("Error lectura", "Error lectura", "Read error") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	if ($rowartic = mysqli_fetch_array($result)) {
		$idfabri   = $rowartic["manufacturers_id"];
		$strnomfab = $rowartic["manufacturers_name"];
	} else {
		echo idioma('Error: fabricant no trobat a osCommerce:', 'Error fabricante no encontrado en osCommerce:', "Error: manufacturer not found in osCommerce:") . ' ' . $codifab;
		die;
	}

	//busquem la pagina
	$strsql = "select * from " . $strnomdb . $strprefixtaules . "manufacturers where manufacturers_name <= '" . $strnomfab . "' order by manufacturers_name";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo idioma("Error lectura", "Error lectura", "Read error") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	$nrows = mysqli_num_rows($result);
	$npag  = intval($nrows / 20) + 1; //cada pagina es de 20

	$strurl = "manufacturers.php?page=" . $npag . "&mID=" . $idfabri . "&action=edit" . '&kk' . $nrows;
	print Header("Location: " . $strurl);
	die;
} // fabricants


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


?>
