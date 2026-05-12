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

error_reporting(E_ALL);

//per defecte
//if ( ! isset($_GET['nav']) ) {  $nav= 1;}
//else { $nav = $_GET['nav'];}
$nav = isset($_GET['nav']) ? $_GET['nav'] : 1;

//if ( ! isset($_GET['idioma']) ) { $idioma= "E";}
//else { $idioma = $_GET['idioma'];}
$idioma = isset($_GET['idioma']) ? $_GET['idioma'] : 'E';

$strprefixtaules = isset($_GET['prefix']) ? $_GET['prefix'] : '';

$crlf = "\r\n"; // $crlf ; //
if ($nav == 1) {
	$crlf = "<br>";
}

include('includes/configure.php');

echo idioma("Executant instal.laci� per osCommerce:", "Ejecutando instalaci�n para OsCommerce:", "Running osCommerce installation:") . $crlf;
$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);


$strsql = "ALTER TABLE `" . $strprefixtaules . "categories` ADD `CODIG` INT( 11 ) DEFAULT '0' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Categories: la instal.laci� ja estava feta.", "Categorias: instalaci�n ya estaba hecha.", "Categories: the installation has already been done.") . $crlf;
	} else {
		echo "Alter categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
} else {
	echo idioma("Categories: instal.lat correctament.", "Categorias: instalaci�n correcta", "Categories: successfully installed.") . $crlf;
}

$strsql = "ALTER TABLE `" . $strprefixtaules . "manufacturers` ADD `NCODIFAB` INT( 11 ) DEFAULT '0' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Fabricants: la instal.laci� ja estava feta.", "Fabricantes: instalaci�n ya estaba hecha.", "Manufacturers: the installation has already been done.") . $crlf;
	} else {
		echo "Alter categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
} else {
	echo idioma("Fabricants: instal.lat correctament.", "Fabricantes: instalaci�n correcta.", "Manufacturers: successfully installed.") . $crlf;
}


$strsql = "ALTER TABLE `" . $strprefixtaules . "products` ADD `CCODIART` VARCHAR( 20 ) DEFAULT '' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Productes la instal.laci� ja estava feta.", "Productos: instalaci�n ya estaba hecha.", "Products: the installation has already been done.") . $crlf;
	} else {
		echo "Alter products " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Productes: instal.lat correctament.", "Productos: instalaci�n correcta", "Products: successfully installed.") . $crlf;
}

$strsql = "ALTER TABLE `" . $strprefixtaules . "products_options` ADD `CCODIPROP` VARCHAR( 15 ) DEFAULT '' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Propietats la instal.laci� ja estava feta.", "Propiedades: instalaci�n ya estaba hecha.", "Properties: the installation has already been done.") . $crlf;
	} else {
		echo "Alter products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Propietats: instal.lat correctament.", "Propiedades: instalaci�n correcta", "Properties: successfully installed.") . $crlf;
}

$strsql = "ALTER TABLE `" . $strprefixtaules . "products_options_values` ADD `CCODIVAL` VARCHAR( 15 ) DEFAULT '' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Valors de propietats la instal.laci� ja estava feta.", "Valores de propiedades: instalaci�n ya estaba hecha.", "Properties values: the installation has already been done.") . $crlf;
	} else {
		echo "Alter products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Valors Propietats: instal.lat correctament.", "Valores Propiedades: instalaci�n correcta", "Properties values: successfully installed.") . $crlf;
}

// modificaci� per propietats amb Grid attributes (maxim 2 propietats)
$strsql = "ALTER TABLE `" . $strprefixtaules . "orders_products` ADD `CCODIVAL1` VARCHAR( 15 ) DEFAULT '' NOT NULL ,  ADD `CCODIVAL2` VARCHAR( 15 ) DEFAULT '' NOT NULL, ADD `CCODIPROP1`  VARCHAR( 15 ) DEFAULT '' NOT NULL , ADD `CCODIPROP2`  VARCHAR( 15 ) DEFAULT '' NOT NULL, ADD `CPROP1` VARCHAR( 64 ) DEFAULT '' NOT NULL ,  ADD `CPROP2` VARCHAR( 64 ) DEFAULT '' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Productes de comandes la instal.laci� ja estava feta.", "Productos de pedidos: instalaci�n ya estaba hecha.", "Order products: the installation has already been done.") . $crlf;
	} else {
		echo "Alter order_products " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Productes de comandes: instal.lat correctament.", "Productos de pedidos: instalaci�n correcta", "Order products: successfully installed.") . $crlf;
}

//modificacio per propietats a oscommerce original
$strsql = "ALTER TABLE `" . $strprefixtaules . "orders_products_attributes` ADD `NIDATRIB` INT( 11 ) DEFAULT 0 NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Propietats de comandes la instal.laci� ja estava feta.", "Propiedades de pedidos: instalaci�n ya estaba hecha.", "Order Attributtes: the installation has already been done.") . $crlf;
	} else {
		echo "Alter products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Propietats en Comandes: instal.lat correctament.", "Propiedades de pedidos: instalaci�n correcta", "Order Attributtes: successfully installed.") . $crlf;
}


$strsql = "ALTER TABLE `" . $strprefixtaules . "customers` ADD `CFACTUR` CHAR( 1 ) DEFAULT 'N' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Clients la instal.laci� ja estava feta.", "Clientes: instalaci�n ya estaba hecha.", "Customers: successfully installed.") . $crlf;
	} else {
		echo "Alter customers " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Clients: instal.lat correctament.", "Clientes: instalaci�n correcta", "Customers: the installation has already been done.") . $crlf;
} //canviat customers


$strsql = "ALTER TABLE `" . $strprefixtaules . "orders` ADD `CFACTUR` CHAR( 1 ) DEFAULT 'N' NOT NULL";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1060) {
		echo idioma("Comandes la instal.laci� ja estava feta.", "Pedidos: instalaci�n ya estaba hecha.", "Orders: successfully installed.") . $crlf;
	} else {
		echo "Alter orders " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {

	echo idioma("Comandes: instal.lat correctament.", "Pedidos: instalaci�n correcta", "Orders: the installation has already been done.") . $crlf;
	//actualitzem per a que no baixin totes
	$strsql = " UPDATE `" . $strprefixtaules . "orders` set CFACTUR = 'S' ";
	$kalter = mysqli_query($link, $strsql);
	if ($kalter == false) {
		echo "update orders " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	} else {
		echo idioma("Comandes: actualitzat correctament.", "Pedidos: actualizado correctamente.", "Orders: successfully updated") . $crlf;
	}
} //canviat orders


if (existeixtaula('products_images') == true) {
	$strsql = "ALTER TABLE `" . $strprefixtaules . "products_images` ADD `NNUMFOTO` INT( 11 ) DEFAULT 0 NOT NULL";
	$kalter = mysqli_query($link, $strsql);
	if ($kalter == false) {
		if (mysqli_errno($link) == 1060) {
			echo idioma("Fotos la instal.laci� ja estava feta.", "Fotos: instalaci�n ya estaba hecha.", "Images: successfully installed.") . $crlf;
		} else {
			echo "Alter products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
			die;
		}
	} else {
		echo idioma("Fotos: instal.lat correctament.", "Fotos: instalaci�n correcta", "Images: the installation has already been done.") . $crlf;
	} //canviat
} //products images


$strsql = " CREATE TABLE `qfacwin_ctl` (
  `CTIPUS` varchar(10) NOT NULL default '',
  `DATACOPIA` varchar(50) NOT NULL default '',
  `BLOQ` char(1) NOT NULL default 'N'
 )"; //fora petava en alguns servers TYPE=MyISAM";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	if (mysqli_errno($link) == 1050) {
		echo idioma("Arxiu de control: la instal.laci� ja estava feta.", "Archivo de control: instalaci�n ya estaba hecha.", "Control file: the installation has already been done.") . $crlf;
	} else {
		echo "create qfacwin_ctl " . mysqli_errno($link) . ": " . mysqli_error($link) . "<BR>" . $strsql;
		die;
	}
} else {
	echo idioma("Arxiu de control: instal.lat correctament.", "Archivo de control: instalado correctamente.", "Control file: successfully installed.") . $crlf;
}

$strsql = "select * from qfacwin_ctl  where CTIPUS = 'TRASPAS'";
$kalter = mysqli_query($link, $strsql);
if ($kalter == false) {
	echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " qfacwin_ctl " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
	die;
}
$rowctl = mysqli_fetch_array($kalter);
if ($rowctl == false) {
	$strsql = "insert into qfacwin_ctl set ";
	$strsql .= "DATACOPIA = '000000',  BLOQ = 'N', CTIPUS = 'TRASPAS' ";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error insert qfacwin_ctl " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	} else {
		echo idioma("Registre de control: instal.lat correctament.", "Registro de control: instalado correctamente.", "Control record: successfully installed.") . $crlf;
	}
}


/*
$strsql = " CREATE TABLE `products_to_qfacwin` (
       `products_id` int(11) NOT NULL default '0',
       `CCODIART` varchar(20) NOT NULL default '0',
       KEY `id_CCODIART` (`products_id`,`CCODIART`)
    ) TYPE=MyISAM";
$kalter = mysql_query( $strsql, $link);
if ($kalter==FALSE){
  if (mysqli_errno($link)== 1050){ echo "products_to_qfacwin: ". idioma("la instal.laci� ja estava feta.","instalaci�n ya estaba hecha."). $crlf; }
  else{echo "create products_to_qfacwin " . mysqli_errno($link).": ".mysqli_error($link)."<BR>".$strsql;}
}else {echo "Products_to_qfacwin: ".idioma("instal.lat correctament.","instalado correctamente.") . $crlf;}

*/

echo "codret=ok" . $crlf;
die;

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
//  mira si existeix una taula retorna TRUE o FALSE
// ----------------------------------------------------------
function existeixtaula($taula) {
	global $link, $strprefixtaules, $crlf;

	$kexist = true;
	$taula  = $strprefixtaules . $taula;
	$strsql = "show create table `" . $taula . "` ";
	try {
		$result = mysqli_query($link, $strsql);
		if ($result == false) {
			if (mysqli_errno($link) == 1146) {
				$kexist = false;
			} //no existeix la taula
			else {
				echo $crlf . "Error SQL = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
				die;
			}
		}
	} catch (\mysqli_sql_exception $e) {
		if ($e->getCode() == 1146) {
			$kexist = false;
		} else {
			echo $crlf . "Error SQL = " . $e->getCode() . ": " . $e->getMessage() . $crlf . $strsql;
			die;
		}
	}
	return $kexist;
}


?>

