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
// error_reporting (E_ALL);

$acategoriesid = [];
$aproductesid  = [];
$strtarifa     = [];
/*
$apreus[1]= 0;
$apreus[2]= 0;
$apreus[3]= 0;
$apreus[4]= 0;
$apreus[5]= 0;
$apreus[6]= 0;
$apreus[7]= 0;
$apreus[8]= 0;
$apreus[9]= 0;
*/
$nomfabricantsegonsidioma = "N"; //"N"= normal oscommerce  S= per els que li han tret el camp manufacturers_name i l'han posat a la taula manufacturers_info en funci� de l'idioma

include('qfacwin_cfg.php');
include('includes/configure.php');


if (!isset($_GET['nav'])) {
	die (idioma('Error en la crida: nav', 'Error en la llamada: nav', 'Parameter error:: nav'));
}
$nav = $_GET['nav'];


if (!isset($_GET['cop'])) {
	$cop = "N";
} else {
	$cop = $_GET['cop'];
}


if (!isset($_GET['descri'])) {
	$descri = "S";
} else {
	$descri = $_GET['descri'];
}


if (!isset($_GET['idioma'])) {
	$idioma = "A";
} else {
	$idioma = $_GET['idioma'];
}

$ini = time();

$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
//mysqli_select_db(DB_DATABASE, $link);
//alguns servers tenen mysql en strict mode i peta al inserir si no especifiques els valors per cada camp
//http://www.mysqlfaqs.net/mysql-client-server-commands/what-is-sql-mode-in-mysql-and-how-can-we-set-it
mysqli_query($link, "SET SESSION sql_mode = ''");//non-strict mode:


$crlf = "\r\n"; // $crlf ; //
if ($nav == 1) {
	$crlf = "<br>";
}

//fem c�pies
$datacopia = date("YmdHi", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));

set_time_limit(1500); //segons = 25 minuts

//$strnomdb = DB_DATABASE .'.'; dona error amb bases de dades que contene guions al nom: sonimax-bcn
$strnomdb = "";


//llegim idiomes
$strsql = "select * from " . $strnomdb . $strprefixtaules . "languages order by languages_id ";
$result = mysqli_query($link, $strsql);
if ($result == false) {
	echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error() . $crlf . $strsql;
	die;
}
$aidiomes = [];
$i        = 0;
while ($rowatri = mysqli_fetch_array($result)) {
	$aidiomes[$i] = $rowatri["languages_id"];
	$i++;
} //while idiomes

$kexisteixproductsgroups   = existeixtaula('products_groups');
$kexisteixproductsimages   = existeixtaula('products_images');
$fotos2_3_1                = existeixcamp('products_images', 'htmlcontent');
$kexisteixfeatured         = existeixtaula('featured');
$kexisteixattributesgroups = existeixtaula('products_attributes_groups');
$kexisteixproducts_stock   = existeixtaula('products_stock');
$kexisteixproducts_grid    = existeixtaula('products_grid');
$nuve                      = existeixcamp('products', 'uve');

if ($fotos2_3_1 == true) {
	echo $crlf . 'oscommerce 2.3.1';
}
//-----------------------------------------------------
// mira si esta bloquejat
//-----------------------------------------------------

//mira que no estigui bloquejat
$strsql = "select * from qfacwin_ctl  where CTIPUS = 'TRASPAS'";
$result = mysqli_query($link, $strsql);
if ($result == false) {
	echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
	die;
}
$rowctl = mysqli_fetch_array($result);
if ($rowctl["BLOQ"] == "S") {
	echo $crlf . idioma("Proc�s anterior bloquejat. Restaurant c�pies.", "Proceso anterior bloqueado. Restaurando copias", "Previous process locked. Restoring copies.") . $crlf;
	//recuperar copies
	$datarestaura = $rowctl["DATACOPIA"];
	echo $crlf . idioma("Restaurant arxiu de categories:", "Restaurando archivos de categorias:", "Restoring category file:");

	restauracopia('categories');
	restauracopia('categories_description');

	echo $crlf . idioma("Restaurant arxiu de fabricants:", "Restaurando archivos de fabricantes:", "Restoring manufacturer file:");

	restauracopia('manufacturers');
	restauracopia('manufacturers_info');

	echo $crlf . idioma("Restaurant arxius de productes:", "Restaurando archivos de  productos", "Restoring products file:");

	restauracopia('products');
	restauracopia('products_description');
	restauracopia('products_to_categories');
	restauracopia('specials');
	restauracopia('products_attributes');
	restauracopia('products_notifications'); //copia notificacions dels productes
	if ((($contribmorepics == true) or ($fotos2_3_1 == true)) && ($kexisteixproductsimages == true) && ($traspassarfotos == true)) {
		restauracopia('products_images');
	} //MorePics
	restauracopia('reviews'); //copia reviews productes
	restauracopia('reviews_description');
	if ($kexisteixproductsgroups == true) {
		restauracopia('products_groups');
	}
	if (($contribfeatured == true) && ($kexisteixfeatured == true)) {
		restauracopia('featured');
	}

	if ($traspassaratributs == true) {
		restauracopia('products_options');
		restauracopia('products_options_values');
		restauracopia('products_options_values_to_products_options');
		//restauracopia ('products_attributes');
		if (($traspassaratributs == true) && ($kexisteixattributesgroups == true)) {
			restauracopia('products_attributes_groups');
		}
		if (($estocatributs == true) && ($kexisteixproducts_stock == true)) {
			restauracopia('products_stock');
		}
	}

	if (($contribgridattributes == true) && ($kexisteixproducts_grid == true)) {
		restauracopia('products_grid_row_col');
		restauracopia('products_grid');
	}
	//desbloquejem el control
	$strsql = "update qfacwin_ctl  set ";
	$strsql .= " BLOQ = 'N'  where CTIPUS = 'TRASPAS'";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error update qfacwin_ctl = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}

	echo $crlf . $crlf . idioma("C�pies restaurades correctament. Torneu a executar el trasp�s", "Copias restauradas correctamente. Vuelva a ejecutar el traspaso.", "Copies successfully restored. Run the transfer again") . $crlf;
	echo $crlf . "restaura=ok";
	die;
} //bloquejat

//bloquejem
$strsql = "update qfacwin_ctl  set ";
$strsql .= "DATACOPIA = '" . $datacopia . "',  BLOQ = 'S'  where CTIPUS = 'TRASPAS' ";
$result = mysqli_query($link, $strsql);
if ($result == false) {
	echo $crlf . "Error update qfacwin_ctl " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
	die;
}


//-----------------------------------------------------
// categories
//-----------------------------------------------------

echo $crlf . idioma("Integraci� de les dades a osCommerce:", "Integraci�n de los datos en osCommerce:", "Data transfer to osCommerce:") . $crlf;
echo $crlf . idioma("Copiant arxiu de categories", "Copiando archivos de categorias", "Copying category file") . $crlf;

creacopia('categories');
creacopia('categories_description');

//copies d'arxius del client amb id de categoria
$qi = 0;
while ($qi < count($acategoriesid)) {
	creacopia($acategoriesid[$qi]);
	$qi++;
}


$fila           = 1;
$filename       = DIR_FS_CATALOG_IMAGES . "wgrart.txt";
$nnumcategories = 0;
//$fp = fopen ($filename ,"r");
if (!$fp = fopen($filename, "r")) {
	print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', ' File not found. Process cancelled');
	die;
}

while ($data = fgetcsv($fp, filesize($filename), ";")) {
	//$num = count ($data);
	//print "<p> $num fields in line $row: <br>";
	if ($fila == 1) {
		$nomcamps = $data;
	} else {

		foreach ($data as $key => $value)
			$row[$nomcamps[$key]] = addslashes($value);

		if (!empty($row["CODIG"])) {

			echo idioma('Traspassant categories: ', 'Traspasando categorias: ', 'Transferring categories: ') . $row["CODIG"] . $crlf;
			// aixo per carregar tot l'arxiu en una taula amb noms de camp:
			//$table[] = $row; /* put each line into */
			//echo $table[0]["NOMGRUP"]	.  $crlf ;
			//echo $table[1]["NOMGRUP"]	.  $crlf ;

			$idnou = 0;
			//llegim antiga per CODIG per agafar valors anterior
			$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "categories" . $datacopia . " where CODIG = " . $row["CODIG"];
			$result = mysqli_query($link, $strsql);
			if ($result == false) {
				echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
				die;
			}
			$boolnou = "S";
			if ($rowantiga = mysqli_fetch_array($result)) {
				$boolnou = "N";
				$idantig = $rowantiga["categories_id"];
				//$strcategories_image = $rowantiga["categories_image"];
				//$strdate_added = $rowantiga["date_added"];;
			}

			//Afegim categoria nova
			if ($boolnou == "S") {
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "categories  set ";
				//$strsql .=  " categories_id = " . $row["CODIG"] . " , ";
				if ($traspassarfotos == true) {
					$strsql .= " categories_image = '" . $row["CFOTO1"] . "' , ";
				}
				$strsql .= " parent_id = " . $row["NPARE"] . " , "; //despres es carreguen els pares quan estan tots d'alta
				$strsql .= " sort_order = " . $row["NORDREIN"] . " , ";
				//if ( $boolnou == "S") {  $strsql .=  " date_added = now() , ";}
				//else {$strsql .=  " date_added = '" . $strdate_added . "' , ";}
				$strsql .= " date_added = now() , ";
				$strsql .= " CODIG = " . $row["CODIG"] . " , ";
				$strsql .= " last_modified = now()  ";
				$result = mysqli_query($link, $strsql) or die ("insert categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				$idnou = mysqli_insert_id($link);
			} else {
				//afegim categoria existent mantenint el seu id intern
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "categories  ";
				$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "categories" . $datacopia;
				$strsql .= " where categories_id = " . $idantig . " ";
				$result = mysqli_query($link, $strsql) or die ("INS from categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				//modifiquem
				$strsql = "update " . $strnomdb . $strprefixtaules . "categories  set ";
				//$strsql .=  " categories_id = " . $row["CODIG"] . " , ";
				$strsql .= " parent_id = " . $row["NPARE"] . " , ";
				$strsql .= " sort_order = " . $row["NORDREIN"] . " , ";
				if ($traspassarfotos == true) {
					// imatge si no ve buida (abans no hi era al qfacwin i esborraria)
					if (!empty($row["CFOTO1"])) {
						$strsql .= " categories_image = '" . $row["CFOTO1"] . "' , ";
					}
				}
				$strsql .= " CODIG = " . $row["CODIG"] . " , ";
				$strsql .= " last_modified = now()  ";
				$strsql .= " where categories_id = " . $idantig . " ";
				$result = mysqli_query($link, $strsql) or die ("update categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

			}


			// descripcions de categories
			if ($boolnou == "S") {
				//afegim una descripcio per cada idioma encara que estigui a blanc
				$qi = 0;
				while ($qi < count($aidiomes)) {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "categories_description  set ";
					$strsql .= " categories_id = " . $idnou . " , ";
					if ($aidiomes[$qi] == $idioma1) {
						$strsql .= " categories_name = '" . stripslashes($row["NOMGRUP"]) . "' , ";
					}
					if ($numidiomas > 1) {
						if ($aidiomes[$qi] == $idioma2) {
							$strsql .= " categories_name = '" . stripslashes($row["NOMGRUP2"]) . "' , ";
						}
					}
					$strsql .= " language_id = " . $aidiomes[$qi] . "  ";
					$result = mysqli_query($link, $strsql) or die ("INS categories_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					$qi++;
				}//while idiomes


			} else { //categoria ja existeix
				//afegim descripcion de tots els idiomes que hi hagin
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "categories_description  ";
				$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "categories_description" . $datacopia;
				$strsql .= " where categories_id = " . $idantig . " ";
				$result = mysqli_query($link, $strsql) or die ("INS from categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				//modifiquem les que pujen de QFACWIN
				$strsql = "update " . $strnomdb . $strprefixtaules . "categories_description  set ";
				$strsql .= " categories_name = '" . stripslashes($row["NOMGRUP"]) . "' ";
				$strsql .= " where categories_id = " . $idantig . " and language_id = " . $idioma1;
				$result = mysqli_query($link, $strsql) or die ("update 1 categories_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				if ($numidiomas > 1) {
					//busquem per segon idioma, si existeix update si no, afegim segon idioma
					$strsql = "select categories_id, language_id from  " . $strnomdb . $strprefixtaules . "categories_description  ";
					$strsql .= " where categories_id = " . $idantig . " and language_id = " . $idioma2;
					$result = mysqli_query($link, $strsql) or die ("select 2 categories_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					if ($rowc2 = mysqli_fetch_array($result)) {
						$strsql = "update " . $strnomdb . $strprefixtaules . "categories_description set ";
						$strsql .= " categories_name = '" . stripslashes($row["NOMGRUP2"]) . "' ";
						$strsql .= " where categories_id = " . $idantig . " and language_id = " . $idioma2;
						$result = mysqli_query($link, $strsql) or die ("update 2 categories_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					} else {
						//afegim
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "categories_description  set ";
						$strsql .= " categories_id = " . $idantig . " , ";
						$strsql .= " categories_name = '" . stripslashes($row["NOMGRUP2"]) . "' , ";
						$strsql .= " language_id = " . $idioma2 . "  ";
						$result = mysqli_query($link, $strsql) or die ("INS categories_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					}//existeix segon idioma
				}// mes d'un idioma

			}//categoria existeix


			//  Si categoria ja existeix afegim les dades amb aquesta categioria als arxius
			// amb id de categoria del client

			if ($boolnou <> "N") {
				$qi = 0;
				while ($qi < count($acategoriesid)) {
					//afegeixdades($taula, $campid, $valorid)
					afegeixdades($acategoriesid[$qi], "categories_id", $idantig);
					$qi++;
				} //while acategoriesid
			} //categoria existeix

			$nnumcategories++;

		} //hi ha dades: CODIG no buit
	} //no capcelera
	$fila++;
}
fclose($fp);

//actualitzem els codis dels pares (tenen el codi de qfacwin com a pare):
$strsql  = "select * from " . $strnomdb . $strprefixtaules . "categories";
$resulta = mysqli_query($link, $strsql);
if ($resulta == false) {
	echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
	die;
}
while ($rowpares = mysqli_fetch_array($resulta)) {
	//busquem per codiG el codi del pare
	$idcatpare = 0;
	if ($rowpares["parent_id"] > 0) {
		$strsql = "select * from " . $strnomdb . $strprefixtaules . "categories where CODIG = " . $rowpares["parent_id"];
		$result = mysqli_query($link, $strsql);
		if ($result == false) {
			echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
			die;
		}
		if ($rowcatp = mysqli_fetch_array($result)) {
			$idcatpare = $rowcatp["categories_id"];
		}
	}
	$strsql = "update " . $strnomdb . $strprefixtaules . "categories  set ";
	$strsql .= "  parent_id = " . $idcatpare;
	$strsql .= "  where categories_id = " . $rowpares["categories_id"];
	$result = mysqli_query($link, $strsql) or die ($crlf . "Update categories pares " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

} //end while

//copies d'arxius del client amb id de categoria
$qi = 0;
while ($qi < count($acategoriesid)) {
	esborracopies($acategoriesid[$qi]);
	$qi++;
}


//-----------------------------------------------------
// Fabricants
//-----------------------------------------------------

echo $crlf . idioma("Copiant arxiu de fabricants", "Copiando archivos de fabricantes", "Copying manufacturer file") . $crlf;

creacopia('manufacturers');
creacopia('manufacturers_info');

//copies d'arxius del client amb id de categoria
//$qi = 0;
//while ($qi < count($acategoriesid)) {
//   creacopia ( $acategoriesid[$qi] );
//   $qi++;
//}


$fila       = 1;
$filename   = DIR_FS_CATALOG_IMAGES . "wfabric.txt";
$nnumfabric = 0;
//$fp = fopen ($filename ,"r");
if (!$fp = fopen($filename, "r")) {
	print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
	die;
}

while ($data = fgetcsv($fp, filesize($filename), ";")) {
	if ($fila == 1) {
		$nomcamps = $data;
	} else {
		foreach ($data as $key => $value)
			$row[$nomcamps[$key]] = addslashes($value);

		if (!empty($row["NCODIFAB"])) {

			echo idioma('Traspassant fabricants: ', 'Traspasando fabricantes: ', 'Transferring manufacturers ') . $row["NCODIFAB"] . $crlf;

			$idnou = 0;
			//llegim antiga per NCODIFAB per agafar valors anterior
			$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "manufacturers" . $datacopia . " where NCODIFAB = " . $row["NCODIFAB"];
			$result = mysqli_query($link, $strsql);
			if ($result == false) {
				echo $crlf . idioma("Error lectura a manufacturers anterior", "Error lectura en manufacturers anterior", "Read error in previous manufacturers") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
				die;
			}
			$boolnou = "S";
			if ($rowantiga = mysqli_fetch_array($result)) {
				$boolnou = "N";
				$idantig = $rowantiga["manufacturers_id"];
			}

			//Afegim fabricant nou
			if ($boolnou == "S") {
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "manufacturers  set ";
				if ($nomfabricantsegonsidioma <> "S") {
					$strsql .= " manufacturers_name = '" . $row["CNOMFAB"] . "' , ";
				}
				$strsql .= " date_added = now() , ";
				$strsql .= " NCODIFAB = " . $row["NCODIFAB"] . " , ";
				$strsql .= " last_modified = now()  ";
				$result = mysqli_query($link, $strsql) or die ("insert manufacturers " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				$idnou = mysqli_insert_id($link);
			} else {
				//afegim fabricant existent
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "manufacturers  ";
				$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "manufacturers" . $datacopia;
				$strsql .= " where manufacturers_id = " . $idantig . " ";
				$result = mysqli_query($link, $strsql) or die ("INS from manufacturers " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				//modifiquem
				$strsql = "update " . $strnomdb . $strprefixtaules . "manufacturers  set ";
				if ($nomfabricantsegonsidioma <> "S") {
					$strsql .= " manufacturers_name = '" . stripslashes($row["CNOMFAB"]) . "' , ";
				}
				$strsql .= " date_added = now() , ";
				$strsql .= " NCODIFAB = " . $row["NCODIFAB"] . " , ";
				$strsql .= " last_modified = now()  ";
				$strsql .= " where manufacturers_id = " . $idantig . " ";
				$result = mysqli_query($link, $strsql) or die ("update manufacturers " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
			}

			// urls de fabricants
			if ($boolnou == "S") {
				//afegim una descripcio per cada idioma encara que estigui a blanc
				$qi = 0;
				while ($qi < count($aidiomes)) {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "manufacturers_info  set ";
					$strsql .= " manufacturers_id = " . $idnou . " , ";
					if ($nomfabricantsegonsidioma == "S") {
						$strsql .= " manufacturers_name = '" . stripslashes($row["CNOMFAB"]) . "' , ";
					}
					if ($aidiomes[$qi] == $idioma1) {
						$strsql .= " manufacturers_url = '" . $row["CURLFAB"] . "' , ";
					}
					if ($numidiomas > 1) {
						if ($aidiomes[$qi] == $idioma2) {
							$strsql .= " manufacturers_url = '" . $row["CURLFAB2"] . "' , ";
						}
					}
					$strsql .= " languages_id = " . $aidiomes[$qi] . "  ";
					$result = mysqli_query($link, $strsql) or die ("INS manufacturers_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					$qi++;
				}//while idiomes


			} else { //fabricant ja existeix
				//afegim descripcion de tots els idiomes que hi hagin
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "manufacturers_info  ";
				$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "manufacturers_info" . $datacopia;
				$strsql .= " where manufacturers_id = " . $idantig . " ";
				$result = mysqli_query($link, $strsql) or die ("INS from manufacturers " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				//modifiquem els que pujen de QFACWIN
				$strsql = "update " . $strnomdb . $strprefixtaules . "manufacturers_info  set ";
				$strsql .= " manufacturers_url = '" . $row["CURLFAB"] . "'  ";
				$strsql .= " where manufacturers_id = " . $idantig . " and languages_id = " . $idioma1;
				$result = mysqli_query($link, $strsql) or die ("update 1 manufacturers_info " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				if ($numidiomas > 1) {
					$strsql = "update " . $strnomdb . $strprefixtaules . "manufacturers_info set ";
					$strsql .= " manufacturers_url = '" . $row["CURLFAB2"] . "'  ";
					$strsql .= " where manufacturers_id = " . $idantig . " and languages_id = " . $idioma2;
					$result = mysqli_query($link, $strsql) or die ("update 2 manufacturers_info " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				}// mes d'un idioma

			}//fabricant existeix

			//  Si fabricant ja existeix afegim les dades amb aquesta categioria als arxius
			// amb id de categoria del client
			/*
	   if ( $boolnou <> "N"){
		   $qi = 0;
		   while ($qi < count($acategoriesid)) {
			  //afegeixdades($taula, $campid, $valorid)
			  afegeixdades($acategoriesid[$qi], "categories_id", $idantig);
	          $qi++;
	       } //while acategoriesid
	   } //categoria existeix
       */

			$nnumfabric++;

		} //hi ha registres: NCODIFAB ple
	} //no capcelera
	$fila++;
}
fclose($fp);


//copies d'arxius del client amb id de fabricant
//$qi = 0;
//while ($qi < count($acategoriesid)) {
//   esborracopies ( $acategoriesid[$qi] );
//   $qi++;
//}

//-----------------------------------------------------
// articles
//-----------------------------------------------------

//copia articles

echo $crlf . idioma("Copiant arxiu de productes", "Copiando archivos de  productos", "Copying product file") . $crlf;


creacopia('products');
creacopia('products_description');
creacopia('products_to_categories');
creacopia('specials');
creacopia('products_attributes');
creacopia('products_notifications'); //copia notificacions dels productes
creacopia('reviews'); //copia reviews productes
creacopia('reviews_description');
//if ($kexisteixproductsgroups == TRUE){ creacopia ('products_groups');} //SPPC
if (($contribsppc == true) && ($kexisteixproductsgroups == true)) {
	creacopia('products_groups');
	//afegim grups existents que no pugin del qfacwin
	$strwhere = '';
	$strand   = "";
	$pr       = 0;
	while ($pr < count($strgrupcli)) {
		if (!empty($strgrupcli[$pr])) {
			$strwhere .= $strand . " customers_group_id <> " . $strgrupcli[$pr];
			$strand   = ' and ';
		}
		$pr++;
	} //while
	if (!empty($strwhere)) {
		$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_groups  ";
		$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_groups" . $datacopia;
		$strsql .= " where " . $strwhere;
		$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_groups " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
	} //where
} //SPPC
if ((($contribmorepics == true) or ($fotos2_3_1 == true)) && ($kexisteixproductsimages == true) && ($traspassarfotos == true)) {
	creacopia('products_images');
} //MorePics, oscommerce 2.3.1
if (($contribfeatured == true) && ($kexisteixfeatured == true)) {
	creacopia('featured');
} //featured
if (($contribgridattributes == true) && ($kexisteixproducts_grid == true)) {
	creacopia('products_grid_row_col');
	creacopia('products_grid');
}

//copies d'arxius del client amb id de producte
$qi = 0;
while ($qi < count($aproductesid)) {
	creacopia($aproductesid[$qi]);
	$qi++;
}


$fila      = 1;
$numproduc = 0;
//$numoferta = 1;
$filename = DIR_FS_CATALOG_IMAGES . "wartic.txt";
//$fp = fopen ($filename ,"r");
if (!$fp = fopen($filename, "r")) {
	print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
	die;
}

while ($data = fgetcsv($fp, filesize($filename), ";")) {
	//$num = count ($data);
	//print "<p> $num fields in line $row: <br>";
	if ($fila == 1) {
		$nomcamps = $data;
	} else {
		foreach ($data as $key => $value)
			$row[$nomcamps[$key]] = addslashes($value);

		if (!empty($row["CCODIART"])) {

			echo idioma('Traspassant articles: ', 'Traspasando art�culos: ', 'Transferring products: ') . $row["CCODIART"] . $crlf;

			$idproducte = 0;
			//llegim productes antics per CCODIART per agafar valors anterior
			$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products" . $datacopia . " where CCODIART = '" . $row["CCODIART"] . "'";
			$result = mysqli_query($link, $strsql);
			if ($result == false) {
				echo $crlf . idioma("Error lectura productes anterior", "Error lectura productos anterior", "Read error in previous products") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
				die;
			}
			$boolnou = "S";
			if ($rowartic = mysqli_fetch_array($result)) {
				$boolnou    = "N";
				$idproducte = $rowartic["products_id"];
			}
			//busquem l'id de categoria oscommerce amb el codi qfacwin
			$idcategoria = 0;
			$strsqlc     = "select * from " . $strnomdb . $strprefixtaules . "categories where CODIG = " . $row["CODIG"];
			$resultc     = mysqli_query($link, $strsqlc);
			if ($resultc == false) {
				echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlc;
				die;
			}
			if ($rowcat = mysqli_fetch_array($resultc)) {
				$idcategoria = $rowcat["categories_id"];
			}


			//busquem fabricant amb el codi qfacwin
			$idfabri = 0;
			if (!empty ($row["NCODIFAB"])) {
				$strsqlf = "select * from " . $strnomdb . $strprefixtaules . "manufacturers where NCODIFAB = " . $row["NCODIFAB"];
				$resultf = mysqli_query($link, $strsqlf);
				if ($resultf == false) {
					echo $crlf . idioma("Error lectura fabricant del producte = ", "Error lectura fabricante del producto", "Read error in product manufacturer") . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlf;
					die;
				}
				if ($rowfab = mysqli_fetch_array($resultf)) {
					$idfabri = $rowfab["manufacturers_id"];
				}
			} //hi ha fabricant

			//Afegim producte
			if ($boolnou == "S") {

				$strsql = "insert into " . $strnomdb . $strprefixtaules . "products  set ";
				//$strsql .=  " products_id = " . $numproduc . " , ";
				if ($pujarstock == true) {
					$strsql .= " products_quantity = " . $row["NESTOC"] . " , ";
				}
				$strsql .= " products_model = '" . stripslashes($row["CMODEL"]) . "' , ";
				if ($nuve == true) {
					$strsql .= " uve = " . $row["NUVE"] . " , ";
				}
				if ($traspassarfotos == true) {
					$strsql .= " products_image = '" . $row["CFOTO1"] . "' , ";
					if ($strfoto2 == '*DENOX') {
						$strdenox = '';
						if (!empty($row["CFOTO2"])) {
							$strdenox .= '\"' . $row["CFOTO2"] . '\"';
						}
						if (!empty($row["CFOTO3"])) {
							$strdenox .= ',\"' . $row["CFOTO3"] . '\"';
						}
						if (!empty($row["CFOTO4"])) {
							$strdenox .= ',\"' . $row["CFOTO4"] . '\"';
						}
						if (!empty($row["CFOTO5"])) {
							$strdenox .= ',\"' . $row["CFOTO5"] . '\"';
						}
						if (!empty($row["CFOTO6"])) {
							$strdenox .= ',\"' . $row["CFOTO6"] . '\"';
						}
						if (!empty($strdenox)) {
							$strdenox = "[" . $strdenox . "]";
						}
						$strsql .= "products_subimages = '" . $strdenox . "' , ";
					} else {
						if (!empty($strfoto2)) {
							$strsql .= " " . $strfoto2 . " = '" . $row["CFOTO2"] . "' , ";
						}
					}
					if (!empty($strfoto3)) {
						$strsql .= " " . $strfoto3 . " = '" . $row["CFOTO3"] . "' , ";
					}
					if (!empty($strfoto4)) {
						$strsql .= " " . $strfoto4 . " = '" . $row["CFOTO4"] . "' , ";
					}
					if (!empty($strfoto5)) {
						$strsql .= " " . $strfoto5 . " = '" . $row["CFOTO5"] . "' , ";
					}
					if (!empty($strfoto6)) {
						$strsql .= " " . $strfoto6 . " = '" . $row["CFOTO6"] . "' , ";
					}

					if ($contribultrapics == true) {
						$strsql .= " products_image_med = '" . $row["CFOTO1"] . "' , ";
						$strsql .= " products_image_lrg = '" . $row["CFOTO1"] . "' , ";
						$strsql .= " products_image_xl_1 = '" . $row["CFOTO2"] . "' , ";
						$strsql .= " products_image_xl_2 = '" . $row["CFOTO3"] . "' , ";
						$strsql .= " products_image_xl_3 = '" . $row["CFOTO4"] . "' , ";
						$strsql .= " products_image_xl_4 = '" . $row["CFOTO5"] . "' , ";
						$strsql .= " products_image_xl_5 = '" . $row["CFOTO6"] . "' , ";
					}//ultrapics

				} //traspassar fotos
				// falsejar estoc si sobre comanda
				if ($strfoto2 == '*DENOX') {
					if ($row["LSOBRECOM"] == "S") {
						$strfals = "1";
					} else {
						$strfals = "0";
					}
					$strsql .= " products_stock_falsed = " . $strfals . " , ";
					//alfil pujar codi de barres
					$strsql .= " product_ean = '" . $row["CCODBAR1"] . "' , ";
				}
				if (!empty($strrefprov)) {
					$strsql .= " " . $strrefprov . " = '" . $row["CREFPROV"] . "' , ";
				}
				if (!empty($stracumuds)) {
					$strsql .= " " . $stracumuds . " = '" . $row["NACUMVEN"] . "' , ";
				}
				if (!empty($strnvendamin)) {
					$strsql .= " " . $strnvendamin . " = " . $row["NVENDAMIN"] . " , ";
				}
				if (!empty($strnudsdemanades)) {
					$strsql .= " " . $strnudsdemanades . " = " . $row["NUDSDEMA"] . " , ";
				}
				if (!empty($strdataentradaprevista)) {
					$strsql .= " " . $strdataentradaprevista . " = " . $row["DENTRADAP"] . " , ";
				}

				// $strsql .=  " products_price = " .  $row["NPV0"]  . " , ";
				$strsql .= " products_price = " . $row["NPV" . $ntarifaqfac] . " , ";

				//tarifes
				$pr = 1;
				while ($pr < count($strtarifa) + 1) {
					if (!empty($strtarifa[$pr])) { //aixi permet definir tarifes saltejades
						$strsql .= " " . $strtarifa[$pr] . " = " . $row["NPV" . $pr] . " , ";
					}
					$pr++;
				}//while

				$strdata = ' now()';
				//si tagin = 1 posar a novetats (agafa per ordre descendent de date_added)
				if ($row["NTAGIN"] == "1") {
					$strdata = "'" . date("Y-m-d") . " 23:59:59'";
				}
				$strsql .= " products_date_available = " . $strdata . " , "; //per proximamente
				$strsql .= " products_date_added = " . $strdata . " , "; //per novetats
				$strsql .= " products_weight = " . $row["NPES"] . " , ";
				// PATCH 2026-05-11: productos nuevos entran como borrador (status=2). Se activan a mano.
				// Comportamiento original: CESTAT=D->0, else->1. Conservar referencia abajo.
				$strsql .= " products_status = 2 , ";
				//if ($row["CESTAT"] == "D") { $strsql .= " products_status = 0 , "; } else { $strsql .= " products_status = 1 , "; }
				$nimpost = 0;
				if (isset($tipo_imp_QFACWIN[$row["NTIPIVA"]])) {
					$nimpost = $tipo_imp_QFACWIN[$row["NTIPIVA"]];
				} else {
					echo "*** ERROR **** " . idioma("Error: correspondencia tipus iva no definida: ", "Error: correspondencia tipo de impuesto no definida: ", "Error: undefined tax class match: ") . $row["NTIPIVA"] . $crlf;
				}
				$strsql .= " products_tax_class_id = " . $nimpost . " , ";
				$strsql .= " manufacturers_id = " . $idfabri . " , ";
				//son productes en comanda no ordenats
				// $strsql .=  " products_ordered = " .  $row["NORDREIN"]  . " , ";

				if ($ntip_oscom == 2) { //ZenCart te camp prosucts_sort_order
					$strsql .= " products_sort_order = " . $row["NORDREIN"] . " , ";
					$strsql .= " master_categories_id = " . $idcategoria . " , ";
				}
				$strsql .= " CCODIART = '" . $row["CCODIART"] . "' , ";
				$strsql .= " products_last_modified = now()  ";

				$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_tmp " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				$idproducte = mysqli_insert_id($link);

				//afegim relacio productes i categories
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_to_categories  set ";
				$strsql .= " products_id = " . $idproducte . " , ";
				$strsql .= " categories_id = " . $idcategoria;
				$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_to_categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);


				// afegim correspondencia id i ccodiart a products_to_qfacwin. Per recuperar posteriorment
				// el ccodiart en comandes
				/**** no cal
				 * $strsql = "insert into " . $strnomdb . "products_to_qfacwin  set " ;
				 * $strsql .=  " products_id = " . $idproducte . " , ";
				 * $strsql .=  " CCODIART = '" . $row["CCODIART"] . "'  ";
				 * $result = mysqli_query(  $link,  $strsql )  or die ("INS products_to_qfacwin " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql);
				 ****/


			} else { //existeix
				//afegim el producte
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "products  ";
				$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products" . $datacopia;
				$strsql .= " where products_id = " . $idproducte . " ";
				$result = mysqli_query($link, $strsql) or die ("INS from products " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				//modifiquem
				$strsql = "update " . $strnomdb . $strprefixtaules . "products  set ";
				//31/08/06 ver 9.003 afegit model. es traspasa si ve ple del QFAC,
				//si no es deixa ja que si la migraci� es de versions anteriors no esta a qfac i l'esborraria
				if (!empty($row["CMODEL"])) {
					$strsql .= " products_model = '" . stripslashes($row["CMODEL"]) . "' , ";
				}
				if ($nuve == true) {
					$strsql .= " uve = " . $row["NUVE"] . " , ";
				}
				//06/12/06 ver 9.03 afegit fabricant. es traspasa si ve ple del QFAC,
				//si no es deixa ja que si la migraci� es de versions anteriors no esta a qfac i l'esborraria
				if (!empty ($row["NCODIFAB"])) {
					$strsql .= " manufacturers_id = " . $idfabri . " , ";
				}
				//si tagin = 1 posar a novetats (agafa per ordre descendent de date_added)
				if ($row["NTAGIN"] == "1") {
					$strdata = "'" . date("Y-m-d") . " 23:59:59'";
					$strsql  .= " products_date_added = " . $strdata . " , ";
				}

				if ($pujarstock == true) {
					$strsql .= " products_quantity = " . $row["NESTOC"] . " , ";
				}
				if ($traspassarfotos == true) {
					$strsql .= " products_image = '" . $row["CFOTO1"] . "' , ";
					if ($strfoto2 == '*DENOX') {
						$strdenox = '';
						if (!empty($row["CFOTO2"])) {
							$strdenox .= '\"' . $row["CFOTO2"] . '\"';
						}
						if (!empty($row["CFOTO3"])) {
							$strdenox .= ',\"' . $row["CFOTO3"] . '\"';
						}
						if (!empty($row["CFOTO4"])) {
							$strdenox .= ',\"' . $row["CFOTO4"] . '\"';
						}
						if (!empty($row["CFOTO5"])) {
							$strdenox .= ',\"' . $row["CFOTO5"] . '\"';
						}
						if (!empty($row["CFOTO6"])) {
							$strdenox .= ',\"' . $row["CFOTO6"] . '\"';
						}
						if (!empty($strdenox)) {
							$strdenox = "[" . $strdenox . "]";
						}
						$strsql .= "products_subimages = '" . $strdenox . "' , ";
					} else {
						if (!empty($strfoto2)) {
							$strsql .= " " . $strfoto2 . " = '" . $row["CFOTO2"] . "' , ";
						}
					}
					if (!empty($strfoto3)) {
						$strsql .= " " . $strfoto3 . " = '" . $row["CFOTO3"] . "' , ";
					}
					if (!empty($strfoto4)) {
						$strsql .= " " . $strfoto4 . " = '" . $row["CFOTO4"] . "' , ";
					}
					if (!empty($strfoto5)) {
						$strsql .= " " . $strfoto5 . " = '" . $row["CFOTO5"] . "' , ";
					}
					if (!empty($strfoto6)) {
						$strsql .= " " . $strfoto6 . " = '" . $row["CFOTO6"] . "' , ";
					}
					if ($contribultrapics == true) {
						$strsql .= " products_image_med = '" . $row["CFOTO1"] . "' , ";
						$strsql .= " products_image_lrg = '" . $row["CFOTO1"] . "' , ";
						$strsql .= " products_image_xl_1 = '" . $row["CFOTO2"] . "' , ";
						$strsql .= " products_image_xl_2 = '" . $row["CFOTO3"] . "' , ";
						$strsql .= " products_image_xl_3 = '" . $row["CFOTO4"] . "' , ";
						$strsql .= " products_image_xl_4 = '" . $row["CFOTO5"] . "' , ";
						$strsql .= " products_image_xl_5 = '" . $row["CFOTO6"] . "' , ";
					}//ultrapics
				} //traspassar fotos

				// falsejar estoc si sobre comanda
				if ($strfoto2 == '*DENOX') {
					if ($row["LSOBRECOM"] == "S") {
						$strfals = "1";
					} else {
						$strfals = "0";
					}
					$strsql .= " products_stock_falsed = " . $strfals . " , ";
				}
				if (!empty($strrefprov)) {
					$strsql .= " " . $strrefprov . " = '" . $row["CREFPROV"] . "' , ";
				}
				if (!empty($stracumuds)) {
					$strsql .= " " . $stracumuds . " = '" . $row["NACUMVEN"] . "' , ";
				}
				if (!empty($strnvendamin)) {
					$strsql .= " " . $strnvendamin . " = " . $row["NVENDAMIN"] . " , ";
				}
				if (!empty($strnudsdemanades)) {
					$strsql .= " " . $strnudsdemanades . " = " . $row["NUDSDEMA"] . " , ";
				}
				if (!empty($strdataentradaprevista)) {
					$strsql .= " " . $strdataentradaprevista . " = " . $row["DENTRADAP"] . " , ";
				}


				// $strsql .=  " products_price = " .  $row["NPV0"]  . " , ";
				$strsql .= " products_price = " . $row["NPV" . $ntarifaqfac] . " , ";


				//tarifes en camps de la taula products
				$pr = 1;
				while ($pr < count($strtarifa) + 1) {
					if (!empty($strtarifa[$pr])) { //aixi permet definir tarifes saltejades
						$strsql .= " " . $strtarifa[$pr] . " = " . $row["NPV" . $pr] . " , ";
					}
					$pr++;
				}//while


				$strsql .= " products_weight = " . $row["NPES"] . " , ";
				// PATCH 2026-05-11: NUNCA tocar products_status en UPDATE. Se controla a mano desde el admin.
				// Comportamiento original deshabilitado: CESTAT=D->0, else->1.
				//if ($row["CESTAT"] == "D") { $strsql .= " products_status = 0 , "; } else { $strsql .= " products_status = 1 , "; }
				$nimpost = 0;
				if (isset($tipo_imp_QFACWIN[$row["NTIPIVA"]])) {
					$nimpost = $tipo_imp_QFACWIN[$row["NTIPIVA"]];
				} else {
					echo "*** ERROR **** " . idioma("Error: correspondencia tipus iva no definida: ", "Error: correspondencia tipo de impuesto no definida: ", "Error: undefined tax class match: ") . $row["NTIPIVA"] . $crlf;
				}
				$strsql .= " products_tax_class_id = " . $nimpost . " , ";
				// $strsql .=  " products_ordered = " .  $row["NORDREIN"]  . " , ";

				if ($ntip_oscom == 2) { //ZenCart te camp products_sort_order
					$strsql .= " products_sort_order = " . $row["NORDREIN"] . " , ";
					$strsql .= " master_categories_id = " . $idcategoria . " , ";
				}

				$strsql .= " CCODIART = '" . $row["CCODIART"] . "' , ";
				$strsql .= " products_last_modified = now()  ";
				$strsql .= " where  products_id = " . $idproducte . " ";
				$result = mysqli_query($link, $strsql) or die ("update products_tmp " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				//afegim relacio productes i categories
				//L'agafem dels existents ja que un producte pot estar a m�s d'una categoria
				if ($evitarvariescategories == false) {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_to_categories  ";
					$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_to_categories" . $datacopia;
					$strsql .= " where products_id = " . $idproducte . " ";
					$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_to_categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				} //mantenir pertenen�a existent a categories

				// si han canviat el grup del producte a qfacwin, no existira la relacio amb el nou grup. Afegirla
				//busquem l'id de categoria oscommerce amb el codi qfacwin
				$idcategoria = 0;
				$strsql      = "select * from " . $strnomdb . $strprefixtaules . "categories where CODIG = " . $row["CODIG"];
				$result      = mysqli_query($link, $strsql);
				if ($result == false) {
					echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
					die;
				}
				if ($rowcat = mysqli_fetch_array($result)) {
					$idcategoria = $rowcat["categories_id"];

					//mirem si existeix
					$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_to_categories  where ";
					$strsql .= " products_id = " . $idproducte . " and ";
					$strsql .= " categories_id = " . $idcategoria;
					$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_to_categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					$result = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura", "Error lectura", "Read error") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if (!$rowcat = mysqli_fetch_array($result)) {
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_to_categories  set ";
						$strsql .= " products_id = " . $idproducte . " , ";
						$strsql .= " categories_id = " . $idcategoria;
						$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_to_categories " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					}
				} //categoria trobada

			} //existeix producte


			//inserta tarifes per grups SPPC a products_groups
			//afegim un registre per cada grup que no sigui el 0
			if (($contribsppc == true) && ($kexisteixproductsgroups == true)) {

				$pr = 0;
				while ($pr < count($strgrupcli)) {
					//no gravar si grups en blanc o grup client 0 que es posa a product_price,
					if (!empty($strgrupcli[$pr])) { //aixi permet definir tarifes saltejades
						//llegim grups antics  per agafar valors anterior
						$strsqlgr = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_groups" . $datacopia . " where products_id = " . $idproducte . " and customers_group_id = " . $strgrupcli[$pr];
						//echo $strsqlgr.'<br>';
						$resultgr = mysqli_query($link, $strsqlgr);
						if ($resultgr == false) {
							echo $crlf . idioma("Error lectura products_groups anterior", "Error lectura products_groups anterior", "Read error in previous products_groups") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlgr;
							die;
						}
						$boolnougr = "S";
						if ($rowgr = mysqli_fetch_array($resultgr)) {
							$boolnougr = "N";
						}

						if ($boolnougr == "S") {
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_groups set ";
							$strsql .= " customers_group_id = " . $strgrupcli[$pr] . " , ";
							$strsql .= " products_id = " . $idproducte . " , ";
							$strsql .= " customers_group_price = " . $row["NPV" . $pr];
							$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_groups " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
							//echo $strsql.'<br>';
						} else { //grup ja existeix l'afegim imodifiquem el preu

							//afegim
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_groups  ";
							$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_groups" . $datacopia;
							$strsql .= " where products_id = " . $idproducte . " and customers_group_id = " . $strgrupcli[$pr];
							$result = mysqli_query($link, $strsql) or die ("INS from products_groups " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

							$strsql = "update " . $strnomdb . $strprefixtaules . "products_groups  set ";
							$strsql .= " customers_group_price = " . $row["NPV" . $pr];
							$strsql .= " where products_id = " . $idproducte . " and customers_group_id = " . $strgrupcli[$pr];
							$result = mysqli_query($link, $strsql) or die ("update  products_groups " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						}//grup ja existeix
					} //grup no buit
					$pr++;
				}//while

			} //preus per SPPC


			//afegim a products_images per more pics
			if (($traspassarfotos == true) && ($contribmorepics == true) && ($kexisteixproductsimages == true)) {
				$numimage = 0;
				while ($numimage <= 5) {
					//si hi ha foto
					//echo "CFOTO". ($numimage+1) .' '.$row["CFOTO". ($numimage+1)];
					if (!empty($row["CFOTO" . ($numimage + 1)])) {
						//llegim imatges antics  per agafar valors anterior
						$strsqlimg = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_images" . $datacopia . " where products_id = " . $idproducte . " and images_order = " . $numimage;
						//echo $strsqlimg.'<br>';
						$resultimg = mysqli_query($link, $strsqlimg);
						if ($resultimg == false) {
							echo $crlf . idioma("Error lectura products_images anterior", "Error lectura products_images anterior", "Read error in previous products_images") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlimg;
							die;
						}
						$boolnovaimg = "S";
						if ($rowimg = mysqli_fetch_array($resultimg)) {
							$boolnovaimg = "N";
						}
						$numcat_page  = 0;
						$numprod_page = 0;
						$numpop_page  = 0;
						if ($numimage == 0) {
							$numcat_page  = 1;
							$numprod_page = 1;
							$numpop_page  = 1;
						}
						if ($boolnovaimg == "S") {
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_images set ";
							$strsql .= " products_id = " . $idproducte . " , ";
							$strsql .= " image_filename = '" . $row["CFOTO" . ($numimage + 1)] . "' , ";
							$strsql .= " images_order = " . $numimage . " , ";
							$strsql .= " category_page = " . $numcat_page . " , ";
							$strsql .= " product_page = " . $numprod_page . " , ";
							$strsql .= " popup_page = " . $numpop_page . " , ";
							$strsql .= " last_modified = now() ";
							$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
							//echo $strsql.'<br>';
						} else { //imatge ja existeix l'afegim i modifiquem

							//afegim
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_images  ";
							$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_images" . $datacopia;
							$strsql .= " where products_id = " . $idproducte . " and images_order = " . $numimage;
							$result = mysqli_query($link, $strsql) or die ("INS from products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

							$strsql = "update " . $strnomdb . $strprefixtaules . "products_images  set ";
							$strsql .= " image_filename = '" . $row["CFOTO" . ($numimage + 1)] . "' , ";
							$strsql .= " category_page = " . $numcat_page . " , ";
							$strsql .= " product_page = " . $numprod_page . " , ";
							$strsql .= " popup_page = " . $numpop_page . " , ";
							$strsql .= " last_modified = now() ";
							$strsql .= " where products_id = " . $idproducte . " and images_order = " . $numimage;
							$result = mysqli_query($link, $strsql) or die ("update  products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						}//imatge ja existeix
					} //imatge no buit
					$numimage++;
				}//while

			} //MorePics


			//afegim a products_images per oscommerce 2.3.1
			if (($traspassarfotos == true) && ($fotos2_3_1 == true) && ($kexisteixproductsimages == true)) {
				//afegim les imatges que no tenen NNUMFOTO (manuals)
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_images  ";
				$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_images" . $datacopia;
				$strsql .= " where products_id = " . $idproducte . "  and NNUMFOTO = 0";
				$result = mysqli_query($link, $strsql) or die ("INS from products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				$numimage = 1; //la foto 1 va a l'article pero la repetim per a que surti tamb� al detall
				while ($numimage <= 6) {
					//si hi ha foto
					//echo "CFOTO". ($numimage+1) .' '.$row["CFOTO". ($numimage+1)];
					if (!empty($row["CFOTO" . ($numimage)])) {
						//llegim imatges antics  per agafar valors anterior
						$strsqlimg = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_images" . $datacopia . " where products_id = " . $idproducte . " and NNUMFOTO = " . $numimage;
						//echo $strsqlimg.'<br>';
						$resultimg = mysqli_query($link, $strsqlimg);
						if ($resultimg == false) {
							echo $crlf . idioma("Error lectura products_images anterior", "Error lectura products_images anterior", "Read error in previous products_images") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlimg;
							die;
						}
						$boolnovaimg = "S";
						if ($rowimg = mysqli_fetch_array($resultimg)) {
							$boolnovaimg = "N";
						}
						if ($boolnovaimg == "S") {
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_images set ";
							$strsql .= " products_id = " . $idproducte . " , ";
							$strsql .= " image = '" . $row["CFOTO" . ($numimage)] . "' , ";
							$strsql .= " sort_order = " . $numimage . " , ";
							$strsql .= " NNUMFOTO = " . $numimage . "  ";
							$result = mysqli_query($link, $strsql) or die ($crlf . "INS products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
							//echo $strsql.'<br>';
						} else { //imatge ja existeix afegim  modifiquem
							//afegim
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_images  ";
							$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_images" . $datacopia;
							$strsql .= " where products_id = " . $idproducte . "  and NNUMFOTO = " . $numimage;
							$result = mysqli_query($link, $strsql) or die ("INS from products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

							$strsql = "update " . $strnomdb . $strprefixtaules . "products_images  set ";
							$strsql .= " image = '" . $row["CFOTO" . ($numimage)] . "'  ";
							$strsql .= " where products_id = " . $idproducte . " and NNUMFOTO = " . $numimage;
							$result = mysqli_query($link, $strsql) or die ("update  products_images " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						}//imatge ja existeix
					} //imatge no buida
					$numimage++;
				}//while

			} //oscom 2.3.1


			//afegim descripcions dels productes
			if ($boolnou == "S") {
				//afegim una descripcio per cada idioma encara que estigui a blanc
				$qi = 0;
				while ($qi < count($aidiomes)) {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_description  set ";
					$strsql .= " products_id = " . $idproducte . " , ";
					if ($aidiomes[$qi] == $idioma1) {
						$strsql .= " products_name = '" . stripslashes($row["CNOMART"]) . "' , ";
						if ($descri == "S") {
							$strsql .= " products_description = '" . canviasalts($row["MDESCRI1"]) . "' , ";
						}
						$strsql .= " products_url = '" . $row["CURLFAB"] . "' , ";
					} //idioma 1
					if ($numidiomas > 1) {
						if ($aidiomes[$qi] == $idioma2) {
							$strsql .= " products_name = '" . stripslashes($row["CNOMART2"]) . "' , ";
							if ($descri == "S") {
								$strsql .= " products_description = '" . canviasalts($row["MDESCRI2"]) . "' , ";
							}
							$strsql .= " products_url = '" . $row["CURLFAB2"] . "' , ";
						}// idioma 2
					} //m�s d'un idioma
					$strsql .= " language_id = " . $aidiomes[$qi] . "  ";
					$result = mysqli_query($link, $strsql) or die ("INS products_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					$qi++;
				}//while idiomes

			} else { //producte ja existeix

				//afegim descripcion de tots els idiomes que hi hagin
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_description  ";
				$strsql .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_description" . $datacopia;
				$strsql .= " where products_id = " . $idproducte . " ";
				$result = mysqli_query($link, $strsql) or die ("INS from products " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);


				//modifiquem les que pujen de QFACWIN
				$strsql = "update " . $strnomdb . $strprefixtaules . "products_description  set ";
				$strsql .= " products_name = '" . stripslashes($row["CNOMART"]) . "'  ";
				if ($descri == "S") {
					$strsql .= " , products_description = '" . canviasalts($row["MDESCRI1"]) . "'  ";
				}
				//06/12/06 ver 9.03 afegit fabricant. es traspasa si ve ple del QFAC,
				//si no es deixa ja que si la migraci� es de versions anteriors no esta a qfac i l'esborraria
				if (!empty ($row["NCODIFAB"])) {
					$strsql .= " , products_url = '" . $row["CURLFAB"] . "'  ";
				}
				$strsql .= " where products_id = " . $idproducte . " and language_id = " . $idioma1 . "  ";
				$result = mysqli_query($link, $strsql) or die ("update 1 products_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

				if ($numidiomas > 1) {
					//busquem per segon idioma, si existeix update si no, afegim segon idioma
					$strsql = "select products_id, language_id from  " . $strnomdb . $strprefixtaules . "products_description  ";
					$strsql .= " where products_id = " . $idproducte . " and language_id = " . $idioma2 . "  ";
					$result = mysqli_query($link, $strsql) or die ("select 2 products_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					if ($rowc2 = mysqli_fetch_array($result)) {
						$strsql = "update " . $strnomdb . $strprefixtaules . "products_description  set ";
						$strsql .= " products_name = '" . stripslashes($row["CNOMART2"]) . "' ";
						if ($descri == "S") {
							$strsql .= " , products_description = '" . canviasalts($row["MDESCRI2"]) . "'  ";
						}
						//06/12/06 ver 9.03 afegit fabricant. es traspasa si ve ple del QFAC,
						//si no, es deixa, ja que si la migraci� es de versions anteriors no esta a qfac i l'esborraria
						if (!empty ($row["NCODIFAB"])) {
							$strsql .= " , products_url = '" . $row["CURLFAB2"] . "'   ";
						}
						$strsql .= " where products_id = " . $idproducte . " and language_id = " . $idioma2 . "  ";
						$result = mysqli_query($link, $strsql) or die ("update 2 products_description " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					} else {
						//afegim
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_description  set ";
						$strsql .= " products_id = " . $idproducte . " , ";
						$strsql .= " products_name = '" . stripslashes($row["CNOMART2"]) . "' , ";
						if ($descri == "S") {
							$strsql .= " products_description = '" . canviasalts($row["MDESCRI2"]) . "' , ";
						}
						$strsql .= " products_url = '" . $row["CURLFAB2"] . "' , ";
						$strsql .= " language_id = " . $idioma2 . "  ";
						$result = mysqli_query($link, $strsql) or die ("INS products_description 2 " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					}//existeix segon idioma
				}// mes d'un idioma

			}//producte existeix


			// afegim ofertes
			if ($row["NPVOFERIN" . $ntarifaqfac] <> "0") {
				/***** hi poden haver diferentes ofertes per al mateix producte
				 * $strproducts_url= '';
				 * $nproducts_viewed = 0;
				 *
				 * //llegim productes_descripcions antics utilitzant el codi antic per agafar valors anterior
				 * $strsql = "select * from " . $strnomdb . "specials" .$datacopia. " where products_id = ".$rowartic["products_id"]. " and language_id = " .  $idioma2;
				 * $result = mysqli_query(  $link,  $strsql );
				 * if ($result==FALSE)    {    echo "<br>Error lectura products_description anterior = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;    die; }
				 * if( $rowdescri = mysqli_fetch_array($result)){
				 * $strproducts_url= $rowdescri["products_url"];;
				 * $nproducts_viewed = $rowdescri["products_viewed"];;
				 * }
				 */

				//si el preu d'oferta es menor que el de la tarifa corresponent el posem en oferta si no no
				if ($row["NPVOFERIN" . $ntarifaqfac] < $row["NPV" . $ntarifaqfac]) {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "specials  set ";
					//$strsql .=  " specials_id = " . $numoferta . " , ";
					$strsql .= " products_id = " . $idproducte . " , ";
					$strsql .= " specials_new_products_price = " . $row["NPVOFERIN" . $ntarifaqfac] . "  ";
					$result = mysqli_query($link, $strsql) or die ($crlf . "INS specials " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				}

				//inserta ofertes per les altres tarifes per grups SPPC
				//afegim un registre per cada grup que no sigui el 0
				if (($contribsppc == true) && ($kexisteixproductsgroups == true)) {

					$pr = 0;
					while ($pr < count($strgrupcli)) {
						//no gravar si grups en blanc o grup client 0 que es posa a product_price,
						if (!empty($strgrupcli[$pr])) { //aixi permet definir tarifes saltejades
							//si el preu d'oferta es menor que el de la tarifa corresponent el posem en oferta si no no
							if ($row["NPVOFERIN" . $ntarifaqfac] < $row["NPV" . $pr]) {
								$strsql = "insert into " . $strnomdb . $strprefixtaules . "specials  set ";
								$strsql .= " products_id = " . $idproducte . " , ";
								$strsql .= " customers_group_id = " . $strgrupcli[$pr] . " , ";
								$strsql .= " specials_new_products_price = " . $row["NPVOFERIN" . $ntarifaqfac] . "  ";
								$result = mysqli_query($link, $strsql) or die ($crlf . "INS specials SPPC" . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

							} // preu oferta menor
						} //grup no buit
						$pr++;
					}//while

				} //ofertes per SPPC


				//$numoferta++;

			}//producte en oferta


			//destacts: featured
			if (($contribfeatured == true) && ($kexisteixfeatured == true)) {
				if ($row["NTAGIN"] == "2") {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "featured  set ";
					$strsql .= " products_id = " . $idproducte . " , ";
					$strsql .= " featured_date_added = now() , ";
					$strsql .= " status = 1  ";
					$result = mysqli_query($link, $strsql) or die ($crlf . "INS featured" . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
				} // TAG = 2 posar a destacts
			}//destacats


			//afegim les dades dels arxius que nomes tenen id de producte
			if ($boolnou == "N") {
				//afegeixdades($taula, $campid, $valorid)
				//si no traspas d'atributs afegim les dades anteriors
				if ($traspassaratributs == false) {
					afegeixdades("products_attributes", "products_id", $idproducte);
				}

				afegeixdades("products_notifications", "products_id", $idproducte);

				//afegim reviews antics del producte canviant el codi del producte
				//llegim reviews antics utilitzant el codi antic per agafar valors anterior
				$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "reviews" . $datacopia;
				$strsql .= " where products_id = " . $idproducte;
				$result = mysqli_query($link, $strsql);
				if ($result == false) {
					echo $crlf . idioma("Error lectura reviews anterior", "Error lectura reviews anterior", "Read error previous reviews") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
					die;
				}
				while ($rowatri = mysqli_fetch_array($result)) {
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "reviews   ";
					$strsql .= " select * from zqcop_" . $strprefixtaules . "reviews" . $datacopia;
					$strsql .= " where reviews_id = " . $rowatri["reviews_id"] . "  ";
					$result2 = mysqli_query($link, $strsql) or die ("INS reviews " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					//afegim el texte de les revisions
					$strsql  = "insert into " . $strnomdb . $strprefixtaules . "reviews_description  ";
					$strsql  .= "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "reviews_description" . $datacopia;
					$strsql  .= " where reviews_id = " . $rowatri["reviews_id"];
					$result3 = mysqli_query($link, $strsql);
					if ($result3 == false) {
						echo $crlf . "Error insert reviews_description anterior = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}

				} //while reviews


				//afegeix dades d'arxius del client amb id de producte
				$qi = 0;
				while ($qi < count($aproductesid)) {
					//afegeixdades($taula, $campid, $valorid)
					afegeixdades($productesid[$qi], "products_id", $idproducte);
					$qi++;
				} //while aproductesid

				//afegim products_grid_row_col
				if (($traspassaratributs == true) && ($contribgridattributes == true) && ($kexisteixproducts_grid == true)) {
					if (!empty($row["CCODIPROP1"]) or !empty($row["CCODIPROP2"])) {

						//busquem id de la propietat1
						$idopcio1 = 0;
						$strsql   = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP1"] . "'";
						$result   = mysqli_query($link, $strsql);
						if ($result == false) {
							echo $crlf . idioma("Error lectura propietats", "Error lectura propiedades", "Read error in properties") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
							die;
						}
						if ($rowartic = mysqli_fetch_array($result)) {
							$idopcio1 = $rowartic["products_options_id"];
						} else {
							echo $crlf . idioma("Propietat no trobada", "Propiedad no encontrada", "Property not found") . ": " . $crlf . $strsql;
							die;
						}

						$idopcio2 = 0;
						$strsql   = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP2"] . "'";
						$result   = mysqli_query($link, $strsql);
						if ($result == false) {
							echo $crlf . idioma("Error lectura propietats", "Error lectura propiedades", "Read error in properties") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
							die;
						}
						if ($rowartic = mysqli_fetch_array($result)) {
							$idopcio2 = $rowartic["products_options_id"];
						} else {
							echo $crlf . idioma("Propietat no trobada", "Propiedad no encontrada", "Property not found") . ": " . $crlf . $strsql;
							die;
						}


						//llegim antiga per article, propietat1 i propietat2 per agafar valors anterior
						$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_grid_row_col" . $datacopia . " where products_id = " . $idproducte . "  and row_head_id = " . $idopcio1 . "  and col_head_id = " . $idopcio2;
						$result = mysqli_query($link, $strsql);
						if ($result == false) {
							echo $crlf . idioma("Error lectura a grid_row_col anterior", "Error lectura en grid_row_col anterior", "Read error in previous grid_row_col") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
							die;
						}
						$boolnou = "S";
						if ($rowantiga = mysqli_fetch_array($result)) {
							$boolnou = "N";
							$idantig = $rowantiga["grid_rowcol_id"];
						}

						//Afegim l'atribut
						if ($boolnou == "S") {
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_grid_row_col  set ";
							$strsql .= " products_id = " . $idproducte . " , ";
							$strsql .= " row_head_id = " . $idopcio1 . " , ";
							$strsql .= " col_head_id = " . $idopcio2 . "  ";
							$result = mysqli_query($link, $strsql) or die ("INS products_attributes " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);
						} else {
							//afegim atribut existent
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_grid_row_col  ";
							$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_grid_row_col" . $datacopia;
							$strsql .= " where grid_rowcol_id = " . $idantig . " ";
							$result = mysqli_query($link, $strsql) or die ("INS from products_grid_row_col  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
							//modifiquem les dades que pujen de QFACWIN
							/* no hi ha res mes    $strsql  = "update " . $strnomdb . $strprefixtaules . "grid_row_col  set " ;
			   $strsql .=  " where products_attributes_id = ".$idantig  ;
		   $result = mysqli_query(  $link,  $strsql )  or die ("update 1 products_attributes " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql); */

						} //existeix


					} //hi ha registres: CCODIPRO1 ple

				} // contribgridattributes


			} //article existent

			$numproduc++;

		} //hi ha dades CCODIART no buit

	} //no capcelera
	$fila++;

}
fclose($fp);


//-----------------------------------------------------
// propietats
//-----------------------------------------------------

if ($traspassaratributs == true) {

	echo $crlf . idioma("Copiant arxiu de propietats", "Copiando archivos de propiedades", "Copying properties file") . $crlf;

	creacopia('products_options');
	creacopia('products_options_values');
	creacopia('products_options_values_to_products_options');
	// es crea a articles sempre creacopia ('products_attributes');
	if (($traspassaratributs == true) && ($kexisteixattributesgroups == true)) {
		creacopia('products_attributes_groups');
	}
	if (($estocatributs == true) && ($kexisteixproducts_stock == true)) {
		creacopia('products_stock');
	}


	$fila     = 1;
	$filename = DIR_FS_CATALOG_IMAGES . "wartprop.txt";
	$nnumprop = 0;
	//$fp = fopen ($filename ,"r");
	if (!$fp = fopen($filename, "r")) {
		print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
		die;
	}

	//llegim antiga per agafar ultim id
	$nproperid = 1;
	$strsql    = "select max(products_options_id) + 1 as next_id from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_options" . $datacopia;
	$result    = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . idioma("Error lectura a products_options anterior", "Error lectura en products_options anterior", "Read error in previous products_options") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	if ($rowantiga = mysqli_fetch_array($result)) {
		$nproperid = $rowantiga["next_id"];
		//ull si la taula esta buida retorna blanc i peta
		if (empty($nproperid)) {
			$nproperid = 1;
		}
	}

	while ($data = fgetcsv($fp, filesize($filename), ";")) {
		if ($fila == 1) {
			$nomcamps = $data;
		} else {
			foreach ($data as $key => $value)
				$row[$nomcamps[$key]] = addslashes($value);

			if (!empty($row["CCODIPROP"])) {

				echo idioma('Traspassant propietats: ', 'Traspasando propiedades: ', 'Transferring atributtes ') . $row["CCODIPROP"] . $crlf;

				//llegim antiga per CCODIPROP i idioma 1 per agafar valors anterior
				$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_options" . $datacopia . " where CCODIPROP = '" . $row["CCODIPROP"] . "'  and language_id = " . $idioma1;
				$result = mysqli_query($link, $strsql);
				if ($result == false) {
					echo $crlf . idioma("Error lectura a products_options anterior", "Error lectura en products_options anterior", "Read error in previous products_options") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
					die;
				}
				$boolnou = "S";
				if ($rowantiga = mysqli_fetch_array($result)) {
					$boolnou = "N";
					$idantig = $rowantiga["products_options_id"];
				}

				//Afegim propietat nou
				if ($boolnou == "S") {

					//afegim una propietat per cada idioma encara que estigui a blanc
					$qi = 0;
					while ($qi < count($aidiomes)) {
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options  set ";
						$strsql .= " products_options_id = " . $nproperid . " , ";
						$strsql .= " CCODIPROP = '" . $row["CCODIPROP"] . "' , ";
						if ($aidiomes[$qi] == $idioma1) {
							$strsql .= " products_options_name = '" . $row["CNOMPROP"] . "' , ";
						}
						if ($numidiomas > 1) {
							if ($aidiomes[$qi] == $idioma2) {
								$strsql .= " products_options_name = '" . $row["CNOMPROP2"] . "' , ";
							}
						}
						$strsql .= " language_id = " . $aidiomes[$qi] . "  ";
						$result = mysqli_query($link, $strsql) or die ("INS products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						$qi++;
					} //while idiomes

					$nproperid++;

				} else {
					//afegim propietats existents
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options  ";
					$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_options" . $datacopia;
					$strsql .= " where products_options_id = " . $idantig . " ";
					$result = mysqli_query($link, $strsql) or die ("INS from products_options  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

					//modifiquem les que pujen de QFACWIN
					$strsql = "update " . $strnomdb . $strprefixtaules . "products_options  set ";
					$strsql .= " products_options_name = '" . stripslashes($row["CNOMPROP"]) . "'  ";
					$strsql .= " where products_options_id = " . $idantig . " and language_id = " . $idioma1;
					$result = mysqli_query($link, $strsql) or die ("update 1 products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

					if ($numidiomas > 1) {
						//busquem per segon idioma, si existeix update si no, afegim segon idioma
						$strsql = "select products_options_id, language_id from  " . $strnomdb . $strprefixtaules . "products_options  ";
						$strsql .= " where products_options_id = " . $idantig . " and language_id = " . $idioma2;
						$result = mysqli_query($link, $strsql) or die ("select 2 products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
						if ($rowp2 = mysqli_fetch_array($result)) {
							$strsql = "update " . $strnomdb . $strprefixtaules . "products_options  set  ";
							$strsql .= " products_options_name = '" . stripslashes($row["CNOMPROP2"]) . "'  ";
							$strsql .= " where products_options_id = " . $idantig . " and language_id = " . $idioma2;
							$result = mysqli_query($link, $strsql) or die ("update 2 products_options " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
						} else {
							//afegim
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options  set ";
							$strsql .= " products_options_id = " . $idantig . " , ";
							$strsql .= " CCODIPROP = '" . $row["CCODIPROP"] . "' , ";
							$strsql .= " products_options_name = '" . $row["CNOMPROP2"] . "' , ";
							$strsql .= " language_id = " . $idioma2 . "  ";
							$result = mysqli_query($link, $strsql) or die ("INS products_options 2 " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
						}    //existeix segon idioma
					}// mes d'un idioma

				} //existeix


				$nnumprop++;

			} //hi ha registres: CCODIPROP ple
		} //no capcelera
		$fila++;
	} //while
	fclose($fp);


	//----------------------------
	// valors de les propietats
	//----------------------------
	echo $crlf . idioma("Copiant arxiu de valors de propietats", "Copiando archivos de valores de propiedades", "Copying properties values  file") . $crlf;

	$fila     = 1;
	$filename = DIR_FS_CATALOG_IMAGES . "wartvalp.txt";
	$nnumvalp = 0;
	//$fp = fopen ($filename ,"r");
	if (!$fp = fopen($filename, "r")) {
		print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
		die;
	}

	//llegim antiga per agafar ultim id
	$nproperid = 1;
	$strsql    = "select max(products_options_values_id) + 1 as next_id from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_options_values" . $datacopia;
	$result    = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . idioma("Error lectura a products_option_values anterior", "Error lectura en products_options_values anterior", "Read error in previous products_options_values") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	if ($rowantiga = mysqli_fetch_array($result)) {
		$nproperid = $rowantiga["next_id"];
		//ull si la taula esta buida retorna blanc i peta
		if (empty($nproperid)) {
			$nproperid = 1;
		}
	}


	while ($data = fgetcsv($fp, filesize($filename), ";")) {
		if ($fila == 1) {
			$nomcamps = $data;
		} else {
			foreach ($data as $key => $value)
				// $row[$nomcamps[$key]] = addslashes($value);
				$row[$nomcamps[$key]] = $value;  //ja ve escapat

			if (!empty($row["CCODIVAL"])) {

				echo idioma('Traspassant valors de propietats: ', 'Traspasando valores de propiedades: ', 'Transferring properties values ') . $row["CCODIVAL"] . $crlf;


				//llegim antiga per CCODIVAL i idioma 1 per agafar valors anterior
				$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_options_values" . $datacopia . " where CCODIVAL = '" . $row["CCODIVAL"] . "'  and language_id = " . $idioma1;
				$result = mysqli_query($link, $strsql);
				if ($result == false) {
					echo $crlf . idioma("Error lectura a products_options_values anterior", "Error lectura en products_options_values anterior", "Read error in previous products_options_values") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
					die;
				}
				$boolnou = "S";
				if ($rowantiga = mysqli_fetch_array($result)) {
					$boolnou = "N";
					$idantig = $rowantiga["products_options_values_id"];
				}

				//Afegim valor de la propietat nou
				if ($boolnou == "S") {
					// echo "afegit : " . $row["CCODIVAL"]	.  $crlf;
					//afegim un valor de la propietat per cada idioma encara que estigui a blanc
					$qi = 0;
					while ($qi < count($aidiomes)) {
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options_values  set ";
						$strsql .= " products_options_values_id = " . $nproperid . " , ";
						$strsql .= " CCODIVAL = '" . $row["CCODIVAL"] . "' , ";
						if ($aidiomes[$qi] == $idioma1) {
							$strsql .= " products_options_values_name = '" . $row["CVALPROP"] . "' , ";
						}
						if ($numidiomas > 1) {
							if ($aidiomes[$qi] == $idioma2) {
								$strsql .= " products_options_values_name = '" . $row["CVALPROP2"] . "' , ";
							}
						}
						$strsql .= " language_id = " . $aidiomes[$qi] . "  ";
						$result = mysqli_query($link, $strsql) or die ("INS products_options_values " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						$qi++;
					}//while idiomes

					$nproperid++;

				} else {
					//afegim propietats existents
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options_values  ";
					$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_options_values" . $datacopia;
					$strsql .= " where products_options_values_id = " . $idantig . " ";
					$result = mysqli_query($link, $strsql) or die ("INS from products_options_values  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
					//modifiquem les que pujen de QFACWIN
					$strsql = "update " . $strnomdb . $strprefixtaules . "products_options_values  set ";
					$strsql .= " products_options_values_name = '" . stripslashes($row["CVALPROP"]) . "'  ";
					$strsql .= " where products_options_values_id = " . $idantig . " and language_id = " . $idioma1;
					$result = mysqli_query($link, $strsql) or die ("update 1 products_options_values " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

					if ($numidiomas > 1) {
						//busquem per segon idioma, si existeix update si no, afegim segon idioma
						$strsql = "select products_options_values_id, language_id from " . $strnomdb . $strprefixtaules . "products_options_values  ";
						$strsql .= " where products_options_values_id = " . $idantig . " and language_id = " . $idioma2;
						$result = mysqli_query($link, $strsql) or die ("update 2 products_options_values " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
						if ($rowval2 = mysqli_fetch_array($result)) {
							$strsql = "update " . $strnomdb . $strprefixtaules . "products_options_values  set  ";
							$strsql .= " products_options_values_name = '" . stripslashes($row["CVALPROP2"]) . "'  ";
							$strsql .= " where products_options_values_id = " . $idantig . " and language_id = " . $idioma2;
							$result = mysqli_query($link, $strsql) or die ("update 2 products_options_values " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
						} else {
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options_values  set ";
							$strsql .= " products_options_values_id = " . $idantig . " , ";
							$strsql .= " CCODIVAL = '" . $row["CCODIVAL"] . "' , ";
							$strsql .= " products_options_values_name = '" . $row["CVALPROP2"] . "' , ";
							$strsql .= " language_id = " . $idioma2 . "  ";
							$result = mysqli_query($link, $strsql) or die ("INS products_options_values " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						} //existeix idioma 2
					}// mes d'un idioma

				} //existeix

				// afegim relacio entre propietats i valors de les propietats. No conservem les relacions anteriors

				//busquem el id de la propietat amb el CCODIPROP
				$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP"] . "'  and language_id = " . $idioma1;
				$result = mysqli_query($link, $strsql);
				if ($result == false) {
					echo $crlf . idioma("Error lectura a products_options ", "Error lectura en products_options ", "Read error in  products_options") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
					die;
				}
				if ($rowprop = mysqli_fetch_array($result)) {
					$idpropietat = $rowprop["products_options_id"];
				}
				$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_options_values_to_products_options  set ";
				$strsql .= " products_options_id = " . $idpropietat . " , ";
				if ($boolnou == "S") {
					$strsql .= " products_options_values_id = " . ($nproperid - 1) . "  ";
				} else {
					$strsql .= " products_options_values_id = " . $idantig . "  ";
				}
				$result = mysqli_query($link, $strsql) or die ("INS from products_options_values  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);


				$nnumvalp++;

			} //hi ha registres: CCODIVAL ple
		} //no capcelera
		$fila++;
	} //while
	fclose($fp);


	//----------------------------
	// increments de les propietats
	//----------------------------
	echo $crlf . idioma("Copiant arxiu de increments de propietats", "Copiando archivos de incrementos de propiedades", "Copying attributes  file") . $crlf;
	//contribucio grid attributes maxim 2 propietats (fila i columna) omplim products_grid
	if (($contribgridattributes == true) && ($kexisteixproducts_grid == true)) {
		$fila     = 1;
		$filename = DIR_FS_CATALOG_IMAGES . "wartincp.txt";
		$nnumvalp = 0;
		if (!$fp = fopen($filename, "r")) {
			print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
			die;
		}

		while ($data = fgetcsv($fp, filesize($filename), ";")) {
			if ($fila == 1) {
				$nomcamps = $data;
			} else {
				foreach ($data as $key => $value)
					// $row[$nomcamps[$key]] = addslashes($value);
					$row[$nomcamps[$key]] = $value;  //ja ve escapat

				if (!empty($row["CCODIART"])) {

					echo idioma('Traspassant increments de propietats: ', 'Traspasando incrementos de propiedades: ', 'Transferring attributes ') . $row["CCODIART"] . ' ' . $row["CCODIVAL1"] . ' ' . ($row["CCODIVAL2"] <> "_VACIO" ? $row["CCODIVAL2"] : "") . $crlf;

					//busquem id de l'article
					$idproducte = 0;
					$strsql     = "select * from " . $strnomdb . $strprefixtaules . "products where CCODIART = '" . $row["CCODIART"] . "'";
					$result     = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura productes", "Error lectura productos", "Read error in products") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idproducte = $rowartic["products_id"];
					} else {
						echo $crlf . idioma("Producte no trobat", "Producto no encontrada", "Product not found") . ": " . $crlf . $strsql;
						die;
					}

					//busquem id del valor de la propietat1
					$idvalprop1 = 0;
					$strsql     = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL1"] . "'";
					$result     = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idvalprop1 = $rowartic["products_options_values_id"];
					} else {
						echo $crlf . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $crlf . $strsql;
						die;
					}

					//busquem id del valor de la propietat2
					$idvalprop2 = 0;
					$strsql     = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL2"] . "'";
					$result     = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idvalprop2 = $rowartic["products_options_values_id"];
					} else {
						echo $crlf . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $crlf . $strsql;
						die;
					}

					//llegim antiga per article,valor 1 i valor 2 per agafar valors anterior
					$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_grid" . $datacopia . " where products_id = " . $idproducte . "  and row_options_value_id = " . $idvalprop1 . "  and col_options_value_id = " . $idvalprop2;
					$result = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura a atributs anterior", "Error lectura en atributos anterior", "Read error in previous attributes") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					$boolnou = "S";
					if ($rowantiga = mysqli_fetch_array($result)) {
						$boolnou = "N";
						$idantig = $rowantiga["products_grid_id"];
					}

					//Afegim l'atribut
					if ($boolnou == "S") {

						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_grid  set ";
						$strsql .= " products_id = " . $idproducte . " , ";
						$strsql .= " row_options_value_id = " . $idvalprop1 . " , ";
						$strsql .= " col_options_value_id = " . $idvalprop2 . " , ";
						$strsql .= " grid_values_price = " . $row["NINCRE" . $ntarifaqfac] . " , ";
						$strsql .= " grid_status = 1  ";
						$result = mysqli_query($link, $strsql) or die ("INS products_grid " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);

					} else {
						//afegim atribut existent
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_grid  ";
						$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_grid" . $datacopia;
						$strsql .= " where products_grid_id = " . $idantig . " ";
						$result = mysqli_query($link, $strsql) or die ("INS from products_attributes  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
						//modifiquem les dades que pujen de QFACWIN
						$strsql = "update " . $strnomdb . $strprefixtaules . "products_grid  set ";
						//  $strsql .=  " grid_status = 1 , ";	el deixem com estava depen de l'stock ?�
						$strsql .= " grid_values_price = " . $row["NINCRE" . $ntarifaqfac] . " ";
						$strsql .= " where products_grid_id = " . $idantig;
						$result = mysqli_query($link, $strsql) or die ("update 1 products_attributes " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

					} //existeix


				} //hi ha registres: CCODIART ple
			} //no capcelera
			$fila++;
		} //while
		fclose($fp);

	} else {
		//oscommerce original
		$fila     = 1;
		$filename = DIR_FS_CATALOG_IMAGES . "wartincp.txt";
		$nnumvalp = 0;
		//$fp = fopen ($filename ,"r");
		if (!$fp = fopen($filename, "r")) {
			print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
			die;
		}

		while ($data = fgetcsv($fp, filesize($filename), ";")) {
			if ($fila == 1) {
				$nomcamps = $data;
			} else {
				foreach ($data as $key => $value)
					// $row[$nomcamps[$key]] = addslashes($value);
					$row[$nomcamps[$key]] = $value;  //ja ve escapat

				if (!empty($row["CCODIART"])) {

					echo idioma('Traspassant increments de propietats: ', 'Traspasando incrementos de propiedades: ', 'Transferring attributes ') . $row["CCODIART"] . ' ' . $row["CCODIVAL"] . $crlf;

					//busquem id de l'article
					$idproducte = 0;
					$strsql     = "select * from " . $strnomdb . $strprefixtaules . "products where CCODIART = '" . $row["CCODIART"] . "'";
					$result     = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura productes", "Error lectura productos", "Read error in products") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idproducte = $rowartic["products_id"];
					} else {
						echo $crlf . idioma("Producte no trobat", "Producto no encontrada", "Product not found") . ": " . $crlf . $strsql;
						die;
					}

					//busquem id de la propietat
					$idopcio = 0;
					$strsql  = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP"] . "'";
					$result  = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura propietats", "Error lectura propiedades", "Read error in properties") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idopcio = $rowartic["products_options_id"];
					} else {
						echo $crlf . idioma("Propietat no trobada", "Propiedad no encontrada", "Property not found") . ": " . $crlf . $strsql;
						die;
					}


					//busquem id del valor de la propietat
					$idvalprop = 0;
					$strsql    = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL"] . "'";
					$result    = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idvalprop = $rowartic["products_options_values_id"];
					} else {
						echo $crlf . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $crlf . $strsql;
						die;
					}

					//llegim antiga per article, propietat i valor per agafar valors anterior
					$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_attributes" . $datacopia . " where products_id = " . $idproducte . "  and options_id = " . $idopcio . "  and options_values_id = " . $idvalprop;
					$result = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura a atributs anterior", "Error lectura en atributos anterior", "Read error in previous attributes") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					$boolnou = "S";
					if ($rowantiga = mysqli_fetch_array($result)) {
						$boolnou   = "N";
						$idantig   = $rowantiga["products_attributes_id"];
						$idatribut = $idantig;
					}

					//a products_attributes hi van els de la tarifa 0. Les altres tarifes van a products_attributs_groups
					if ($row["NTARIFA"] == '0') {
						//Afegim l'atribut
						if ($boolnou == "S") {
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_attributes  set ";
							$strsql .= " products_id = " . $idproducte . " , ";
							$strsql .= " options_id = " . $idopcio . " , ";
							$strsql .= " options_values_id = " . $idvalprop . " , ";
							if (!empty($strordreatrib)) { //si hi ha camp ordre a atributs
								$strsql .= " " . $strordreatrib . " = " . $row["NORDRE"] . " , ";
							}
							if ($row["NINCRE"] >= 0) {
								$strsql .= " options_values_price = " . $row["NINCRE"] . " , ";
								$strsql .= " price_prefix = '+'  ";
							} else {
								$strsql .= " options_values_price = " . ($row["NINCRE"] * -1) . " , ";
								$strsql .= " price_prefix = '-'  ";
							}
							$result = mysqli_query($link, $strsql) or die ("INS products_attributes " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strSQL);
							$idatribut = mysqli_insert_id($link);
						} else {
							//afegim atribut existent
							$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_attributes  ";
							$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_attributes" . $datacopia;
							$strsql .= " where products_attributes_id = " . $idantig . " ";
							$result = mysqli_query($link, $strsql) or die ("INS from products_attributes  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
							//modifiquem les dades que pujen de QFACWIN
							$strsql = "update " . $strnomdb . $strprefixtaules . "products_attributes  set ";
							if (!empty($strordreatrib)) { //si hi ha camp ordre a atributs
								$strsql .= " " . $strordreatrib . " = " . $row["NORDRE"] . " , ";
							}
							if ($row["NINCRE"] >= 0) {
								$strsql .= " options_values_price = " . $row["NINCRE"] . " , ";
								$strsql .= " price_prefix = '+'  ";
							} else {
								$strsql .= " options_values_price = " . ($row["NINCRE"] * -1) . " , ";
								$strsql .= " price_prefix = '-'  ";
							}
							$strsql .= " where products_attributes_id = " . $idantig;
							$result = mysqli_query($link, $strsql) or die ("update 1 products_attributes " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
							$idatribut = $idantig;
						} //existeix

					} else {  //no tarifa 0
						//altres tarifes si hi ha sppc van a products_attributes_groups
						if (($contribsppc == true) && ($kexisteixattributesgroups == true)) { //SPPC

							if ($strgrupcli[$row["NTARIFA"]] == "") {
								echo $crlf . idioma("Error: la tarifa " . $row["NTARIFA"] . " no te assignat cap grup de client SPPC. Reviseu la configuraci� al QFACWIN", "Error: la tarifa " . $row["NTARIFA"] . " no tiene asignado ningun grupo de cliente SPPC. Revise la configuraci�n en QFACWIN", "Missing SPPC group for rate " . $row["NTARIFA"]);
								die;
							}

							//llegim antiga per article, propietat i valor per agafar valors anterior
							$strsqlatg = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_attributes_groups" . $datacopia . " where products_attributes_id = " . $idatribut . "  and customers_group_id  = " . $strgrupcli[$row["NTARIFA"]];
							$resultatg = mysqli_query($link, $strsqlatg);
							if ($resultatg == false) {
								echo $crlf . idioma("Error lectura a atributs anterior", "Error lectura en atributos anterior", "Read error in previous attributes") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlatg;
								die;
							}
							$boolnouatg = "S";
							if ($rowantigaatg = mysqli_fetch_array($resultatg)) {
								$boolnouatg = "N";
							}

							//Afegim l'atribut
							if ($boolnouatg == "S") {
								$strsqlatg = "insert into " . $strnomdb . $strprefixtaules . "products_attributes_groups  set ";
								$strsqlatg .= " products_attributes_id = " . $idatribut . " , ";
								$strsqlatg .= " products_id = " . $idproducte . " , ";
								$strsqlatg .= "  customers_group_id = " . $strgrupcli[$row["NTARIFA"]] . " , ";
								if ($row["NINCRE"] >= 0) {
									$strsqlatg .= " options_values_price = " . $row["NINCRE"] . " , ";
									$strsqlatg .= " price_prefix = '+'  ";
								} else {
									$strsqlatg .= " options_values_price = " . ($row["NINCRE"] * -1) . " , ";
									$strsqlatg .= " price_prefix = '-'  ";
								}
								$result = mysqli_query($link, $strsqlatg) or die ("INS products_attributes_groups " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlatg);

							} else {
								//afegim atribut existent
								$strsqlatg = "insert into " . $strnomdb . $strprefixtaules . "products_attributes_groups  ";
								$strsqlatg .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_attributes_groups" . $datacopia;
								$strsqlatg .= " where products_attributes_id = " . $idatribut . "  and customers_group_id  = " . $strgrupcli[$row["NTARIFA"]];
								$resultatg = mysqli_query($link, $strsqlatg) or die ("INS from products_attributes  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlatg);
								//modifiquem les dades que pujen de QFACWIN
								$strsqlatg = "update " . $strnomdb . $strprefixtaules . "products_attributes_groups  set ";
								if ($row["NINCRE"] >= 0) {
									$strsqlatg .= " options_values_price = " . $row["NINCRE"] . " , ";
									$strsqlatg .= " price_prefix = '+'  ";
								} else {
									$strsqlatg .= " options_values_price = " . ($row["NINCRE"] * -1) . " , ";
									$strsqlatg .= " price_prefix = '-'  ";
								}
								$strsqlatg .= " where products_attributes_id = " . $idatribut . "  and customers_group_id  = " . $strgrupcli[$row["NTARIFA"]];

								$resultatg = mysqli_query($link, $strsqlatg) or die ("update 1 products_attributes_groups " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsqlatg);

							} //existeix

						} //sppc
					} //altres tarifes

				} //hi ha registres: CCODIART ple
			} //no capcelera
			$fila++;
		} //while
		fclose($fp);
	} //oscommerce original


	//--------------------------------------------------
	// stock per atributs QTPRO
	// els canvis aqui s'han de fer a qfacwin_update
	//------------------------------------------------
	if (($estocatributs == true) && ($kexisteixproducts_stock == true)) {


		//------------copiar tot aixo de  qfacwin_insert
		echo $crlf . idioma("Copiant estoc per atributs QTPRO", "Copiando stock por atributos QTPRO", "Copying  QTPRO attributes") . $crlf;

		$fila     = 1;
		$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
		$nnumvalp = 0;
		//$fp = fopen ($filename ,"r");
		if (!$fp = fopen($filename, "r")) {
			print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
			die;
		}

		while ($data = fgetcsv($fp, filesize($filename), ";")) {
			if ($fila == 1) {
				$nomcamps = $data;
			} else {
				foreach ($data as $key => $value)
					//  $row[$nomcamps[$key]] = addslashes($value);
					$row[$nomcamps[$key]] = $value;  //ja ve escapat

				if (!empty($row["CCODIART"])) {

					$strerrorlectura = "N";

					echo idioma('Traspassant estoc per propietats: ', 'Traspasando stock por propiedades: ', 'Transferring attributes stock') . $row["CCODIART"] . ' ' . $row["CCODIVAL1"] . ' ' . $row["CCODIVAL2"] . ' ' . $row["CCODIVAL3"] . ' -> ' . $row["NSTOCAC"] . $crlf;


					//busquem id de l'article
					$strsql = "select * from " . $strnomdb . $strprefixtaules . "products where CCODIART = '" . $row["CCODIART"] . "'";
					$result = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura productes", "Error lectura productos", "Read error in products") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idproducte = $rowartic["products_id"];
					} else {
						echo idioma("Article no trobat", "Art�culo no encontrado", "Product not found") . ': ' . $row["CCODIART"] . $crlf;
						$strerrorlectura = "S";
					}

					if ($strerrorlectura <> "S") {

						$stratributs = '';

						if (!empty($row["CCODIVAL1"])) {
							//busquem id de la propietat
							$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP1"] . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura propietats", "Error lectura propiedades", "Read error in properties") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							if ($rowartic = mysqli_fetch_array($result)) {
								$idopcio = $rowartic["products_options_id"];
							} else {
								$idopcio         = 0;
								$strerrorlectura = "S";
								echo $crlf . idioma("Propietat no trobada", "Propiedad no encontrada", "Propertie not found") . ': ' . $row["CCODIPROP1"];
							}
							//busquem id del valor de la propietat
							$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL1"] . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							if ($rowartic = mysqli_fetch_array($result)) {
								$idvalprop = $rowartic["products_options_values_id"];
							} else {
								echo "ERROR: " . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $row["CCODIVAL1"] . $crlf;
								$strerrorlectura = "S";
							}
							$stratributs = $idopcio . '-' . $idvalprop;
						} //hi ha codival1

						if (!empty($row["CCODIVAL2"])) {
							//busquem id de la propietat
							$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP2"] . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura propietats", "Error lectura propiedades", "Read error in properties") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							if ($rowartic = mysqli_fetch_array($result)) {
								$idopcio = $rowartic["products_options_id"];
							} else {
								$idopcio         = 0;
								$strerrorlectura = "S";
								echo $crlf . idioma("Propietat no trobada", "Propiedad no encontrada", "Propertie not found") . ': ' . $row["CCODIPROP2"];
							}

							//busquem id del valor de la propietat
							$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL2"] . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							if ($rowartic = mysqli_fetch_array($result)) {
								$idvalprop = $rowartic["products_options_values_id"];
							} else {
								echo "ERROR: " . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $row["CCODIVAL2"] . $crlf;
								$strerrorlectura = "S";
							}
							$stratributs .= ',' . $idopcio . '-' . $idvalprop;
						} //hi ha codival2

						if (!empty($row["CCODIVAL3"])) {
							//busquem id de la propietat
							$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options where CCODIPROP = '" . $row["CCODIPROP3"] . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura propietats", "Error lectura propiedades", "Read error in properties") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							if ($rowartic = mysqli_fetch_array($result)) {
								$idopcio = $rowartic["products_options_id"];
							} else {
								$idopcio         = 0;
								$strerrorlectura = "S";
								echo $crlf . idioma("Propietat no trobada", "Propiedad no encontrada", "Propertie not found") . ': ' . $row["CCODIPROP3"];
							}

							//busquem id del valor de la propietat
							$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL3"] . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							if ($rowartic = mysqli_fetch_array($result)) {
								$idvalprop = $rowartic["products_options_values_id"];
							} else {
								echo "ERROR: " . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $row["CCODIVAL3"] . $crlf;
								$strerrorlectura = "S";
							}
							$stratributs .= ',' . $idopcio . '-' . $idvalprop;
						} //hi ha codival3

						if ($strerrorlectura <> "S") { //no hi ha errors de lectura

							//llegim antiga per article, i propietats stock valor per agafar valors anterior
							$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_stock" . $datacopia . " where products_id = " . $idproducte . "  and products_stock_attributes = '" . $stratributs . "'";
							$result = mysqli_query($link, $strsql);
							if ($result == false) {
								echo $crlf . idioma("Error lectura a estoc atributs anterior", "Error lectura en stock atributos anterior", "Read error in previous stock attributes") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
								die;
							}
							$boolnou = "S";
							if ($rowantiga = mysqli_fetch_array($result)) {
								$boolnou = "N";
								$idantig = $rowantiga["products_stock_id"];
							}


							//Afegim l' estoc per atribut
							if ($boolnou == "S") {

								$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_stock  set ";
								$strsql .= " products_id = " . $idproducte . " , ";
								$strsql .= " products_stock_attributes = '" . $stratributs . "' , ";
								$strsql .= " products_stock_quantity = " . $row["NSTOCAC"];

								$result = mysqli_query($link, $strsql) or die ("INS products_stock " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

							} else {
								//afegim estoc per atribut existent
								$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_stock  ";
								$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_stock" . $datacopia;
								$strsql .= " where products_stock_id = " . $idantig . " ";
								$result = mysqli_query($link, $strsql) or die ("INS from products_stock  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);
								//modifiquem les dades que pujen de QFACWIN
								$strsql = "update " . $strnomdb . $strprefixtaules . "products_stock  set ";
								$strsql .= " products_stock_quantity = " . $row["NSTOCAC"];
								$strsql .= " where products_stock_id = " . $idantig;
								$result = mysqli_query($link, $strsql) or die ("update 1 products_stock " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

							} //existeix

						} ////no hi ha errors de lectura
					} //trobat article
				} //hi ha registres: CCODIART ple
			} //no capcelera
			$fila++;
		} //while
		fclose($fp);
	} //estoc QTPRO

	/*
echo $crlf. idioma("Copiant estoc per atributs QTPRO","Copiando stock por atributos QTPRO","Copying  QTPRO attributes").$crlf ;

$fila = 1;
$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
$nnumvalp = 0;
//$fp = fopen ($filename ,"r");
if(!$fp = fopen ($filename ,"r"))
{
     print $filename. idioma(' Arxiu no trobat. Proc�s cancel.lat',' Archivo no encontrado. Proceso cancelado','File not found. Process cancelled');
     die;
}

while ($data = fgetcsv ($fp, filesize($filename) , ";")) {
    if ($fila == 1){
	  $nomcamps = $data;
	}else{
	   foreach($data as $key => $value)
         //  $row[$nomcamps[$key]] = addslashes($value);
		   $row[$nomcamps[$key]] = $value;  //ja ve escapat

	   if (! empty($row["CCODIART"])	){

		 $strerrorlectura = "N";

		 echo idioma('Traspassant estoc per propietats: ','Traspasando stock por propiedades: ','Transferring attributes stock').$row["CCODIART"] .' '. $row["CCODIVAL1"] .' '. $row["CCODIVAL2"] .' '. $row["CCODIVAL3"].  $crlf;


  	      //busquem id de l'article
		 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products where CCODIART = '".$row["CCODIART"] ."'" ;
         $result = mysqli_query(  $link,  $strsql );
        if ($result==FALSE)	{	echo $crlf .idioma("Error lectura productes","Error lectura productos","Read error in products"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        if( $rowartic = mysqli_fetch_array($result)){
		   $idproducte =  $rowartic["products_id"];
		 }

         $stratributs = '';

		 if (! empty($row["CCODIVAL1"])	){
		 	//busquem id de la propietat
		 	$strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP1"] ."'" ;
         	$result = mysqli_query(  $link,  $strsql );
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        	if( $rowartic = mysqli_fetch_array($result)){ $idopcio =  $rowartic["products_options_id"];	}

		 	//busquem id del valor de la propietat
			 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL1"] ."'" ;
         	$result = mysqli_query(  $link,  $strsql );
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        	if( $rowartic = mysqli_fetch_array($result)){
			  $idvalprop =  $rowartic["products_options_values_id"];
			  } else {
			  echo "ERROR: " . idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL1"].  $crlf;
			  $strerrorlectura = "S"; }
			$stratributs = $idopcio . '-' .	$idvalprop;
		} //hi ha codival1

		 if (! empty($row["CCODIVAL2"])	){
		 	//busquem id de la propietat
		 	$strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP2"] ."'" ;
         	$result = mysqli_query(  $link,  $strsql );
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        	if( $rowartic = mysqli_fetch_array($result)){
		   		$idopcio =  $rowartic["products_options_id"];
		 	}

		 	//busquem id del valor de la propietat
			 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL2"] ."'" ;
         	$result = mysqli_query(  $link,  $strsql );
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        	if( $rowartic = mysqli_fetch_array($result)){
		   		$idvalprop =  $rowartic["products_options_values_id"];
		 	} else {
			 echo "ERROR: " .idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL2"] .$crlf ;
			  $strerrorlectura = "S"; }
			 $stratributs .= ','. $idopcio . '-' .	$idvalprop;
		} //hi ha codival2

		 if (! empty($row["CCODIVAL3"])	){
		 	//busquem id de la propietat
		 	$strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP3"] ."'" ;
         	$result = mysqli_query(  $link,  $strsql );
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        	if( $rowartic = mysqli_fetch_array($result)){
		   		$idopcio =  $rowartic["products_options_id"];
		 	}

		 	//busquem id del valor de la propietat
			 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL3"] ."'" ;
         	$result = mysqli_query(  $link,  $strsql );
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
        	if( $rowartic = mysqli_fetch_array($result)){
		   		$idvalprop =  $rowartic["products_options_values_id"];
		 	}else {
			 echo "ERROR: "  .idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL3"] . $crlf;
			  $strerrorlectura = "S"; }
			 $stratributs .= ','. $idopcio . '-' .	$idvalprop;
		} //hi ha codival3

		if ( $strerrorlectura <> "S") { //no hi ha errors de lectura

			//llegim antiga per article, i propietats stock valor per agafar valors anterior
			$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_stock" .$datacopia. " where products_id = ". $idproducte ."  and products_stock_attributes = '". $stratributs . "'" ;
					$result = mysqli_query(  $link,  $strsql );
					if ($result==FALSE)	{	echo $crlf . idioma("Error lectura a estoc atributs anterior","Error lectura en stock atributos anterior","Read error in previous stock attributes"). "  = " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql;	die; }
			$boolnou = "S";
			if( $rowantiga = mysqli_fetch_array($result)){
				$boolnou = "N";
				$idantig =  $rowantiga["products_stock_id"];
			 }


			//Afegim l' estoc per atribut
			if ( $boolnou == "S"){

					$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_stock  set " ;
					$strsql .=  " products_id = " .   $idproducte . " , ";
					$strsql .=  " products_stock_attributes = '" .  $stratributs . "' , ";
		  		$strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;

					$result = mysqli_query(  $link,  $strsql )  or die ("INS products_stock " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql);

						} else {
						//afegim estoc per atribut existent
						$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_stock  " ;
						$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_stock" .$datacopia;
						$strsql .= " where products_stock_id = ". $idantig ." ";
						$result = mysqli_query(  $link,  $strsql )  or die ("INS from products_stock  " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql);
					//modifiquem les dades que pujen de QFACWIN
						 $strsql  = "update " . $strnomdb . $strprefixtaules . "products_stock  set " ;
				 $strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;
				 $strsql .=  " where products_stock_id = ".$idantig  ;
				 $result = mysqli_query(  $link,  $strsql )  or die ("update 1 products_stock " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql);

					} //existeix

			 } ////no hi ha errors de lectura

	  } //hi ha registres: CCODIART ple
	} //no capcelera
   $fila++;
} //while
fclose ($fp);
} //estoc QTPRO
*/

	//---------------------------------------
	//estoc grid_attributes
	// els canvis aqui s'han de fer a qfacwin_update
	//---------------------------------------
	if (($contribgridattributes == true) && ($kexisteixproducts_grid == true)) {
		echo $crlf . idioma("Copiant estoc per atributs", "Copiando stock por atributos", "Copying  attributes stock") . $crlf;

		$fila     = 1;
		$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
		$nnumvalp = 0;
		//$fp = fopen ($filename ,"r");
		if (!$fp = fopen($filename, "r")) {
			print $filename . idioma(' Arxiu no trobat. Proc�s cancel.lat', ' Archivo no encontrado. Proceso cancelado', 'File not found. Process cancelled');
			die;
		}

		while ($data = fgetcsv($fp, filesize($filename), ";")) {
			if ($fila == 1) {
				$nomcamps = $data;
			} else {
				foreach ($data as $key => $value)
					//  $row[$nomcamps[$key]] = addslashes($value);
					$row[$nomcamps[$key]] = $value;  //ja ve escapat

				if (!empty($row["CCODIART"])) {
					$strerrorlectura = "N";

					echo idioma('Traspassant estoc per propietats: ', 'Traspasando stock por propiedades: ', 'Transferring attributes stock') . $row["CCODIART"] . ' ' . $row["CCODIVAL1"] . ' ' . ($row["CCODIVAL2"] <> "_VACIO" ? $row["CCODIVAL2"] : "") . ' ' . $row["CCODIVAL3"] . $crlf;


					//busquem id de l'article
					$strsql = "select * from " . $strnomdb . $strprefixtaules . "products where CCODIART = '" . $row["CCODIART"] . "'";
					$result = mysqli_query($link, $strsql);
					if ($result == false) {
						echo $crlf . idioma("Error lectura productes", "Error lectura productos", "Read error in products") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
						die;
					}
					if ($rowartic = mysqli_fetch_array($result)) {
						$idproducte = $rowartic["products_id"];
					}

					if (!empty($row["CCODIVAL1"])) {
						//busquem id del valor de la propietat
						$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL1"] . "'";
						$result = mysqli_query($link, $strsql);
						if ($result == false) {
							echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
							die;
						}
						if ($rowartic = mysqli_fetch_array($result)) {
							$idvalprop1 = $rowartic["products_options_values_id"];
						} else {
							echo "ERROR: " . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $row["CCODIVAL1"] . $crlf;
							$strerrorlectura = "S";
						}
					} //hi ha codival1

					if (!empty($row["CCODIVAL2"])) {
						//busquem id del valor de la propietat
						$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_options_values where CCODIVAL = '" . $row["CCODIVAL2"] . "'";
						$result = mysqli_query($link, $strsql);
						if ($result == false) {
							echo $crlf . idioma("Error lectura valors propietats", "Error lectura valores propiedades", "Read error in propertie values") . " = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
							die;
						}
						if ($rowartic = mysqli_fetch_array($result)) {
							$idvalprop2 = $rowartic["products_options_values_id"];
						} else {
							echo "ERROR: " . idioma("Valor Propietat no trobada", "Valor Propiedad no encontrada", "Property value not found") . ": " . $row["CCODIVAL2"] . $crlf;
							$strerrorlectura = "S";
						}
					} //hi ha codival2

					if (!empty($row["CCODIVAL3"])) {
						echo $crlf . idioma("Error la botiga nom�s adment 2 propietats", "Error la tienda s�lo admite 2 propiedades", "Error store only supports 2 properties") . "  " . $row["CCODIART"] . ' ' . $row["CCODIVAL1"] . ' ' . $row["CCODIVAL2"] . ' ' . $row["CCODIVAL3"];
						die;

					} //hi ha codival3

					if ($strerrorlectura <> "S") { //no hi ha errors de lectura

						//llegim products_grid per article, i propietats
						$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_grid where products_id = " . $idproducte . "  and row_options_value_id = " . $idvalprop1 . "  and col_options_value_id = " . $idvalprop2;
						$result = mysqli_query($link, $strsql);
						if ($result == false) {
							echo $crlf . idioma("Error lectura a grid atributs ", "Error lectura en grids anterior", "Read error in grid stock attributes") . "  = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
							die;
						}
						$boolnou = "S";
						if ($rowantiga = mysqli_fetch_array($result)) {
							$boolnou = "N";
							$idantig = $rowantiga["products_grid_id"];
						}

						//Afegim l' estoc per atribut
						if ($boolnou == "S") {
							echo($crlf . "products_grid not exist " . $row["CCODIART"] . ' ' . $row["CCODIVAL1"] . ' ' . $row["CCODIVAL2"]);
						} else {
							//afegim estoc per atribut existent(ja esta fet)
							//modifiquem les dades que pujen de QFACWIN
							$strstatus = "1";
							if (($row["NSTOCAC"]) == "0") {
								$strstatus = "0";
							}
							$strsql = "update " . $strnomdb . $strprefixtaules . "products_grid  set ";
							$strsql .= " grid_quantity = " . $row["NSTOCAC"] . " , ";
							$strsql .= " grid_status = " . $strstatus;
							$strsql .= " where products_grid_id = " . $idantig;
							$result = mysqli_query($link, $strsql) or die ("update 1 products_stock " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);

						} //existeix

					} ////no hi ha errors de lectura

				} //hi ha registres: CCODIART ple
			} //no capcelera
			$fila++;
		} //while
		fclose($fp);
	} //estoc grid attributes

} //traspassar atributs


//esborrem copies de categories:
esborracopies('categories');
esborracopies('categories_description');

//esborrem copies de fabricants:
esborracopies('manufacturers');
esborracopies('manufacturers_info');

//esborrem les c�pies de productes
esborracopies('products');
esborracopies('products_description');
if (($contribsppc == true) && ($kexisteixproductsgroups == true)) {
	esborracopies('products_groups');
}
//if (($contribmorepics == TRUE) && ($kexisteixproductsimages == TRUE)) {
if ((($contribmorepics == true) or ($fotos2_3_1 == true)) && ($kexisteixproductsimages == true) && ($traspassarfotos == true)) {
	esborracopies('products_images');
} //MorePics
if (($contribfeatured == true) && ($kexisteixfeatured == true)) {
	esborracopies('featured');
}
esborracopies('products_to_categories');
esborracopies('specials');
esborracopies('products_attributes');
esborracopies('products_notifications'); //copia notificacions dels productes
esborracopies('reviews'); //copia reviews productes
esborracopies('reviews_description');
if ($traspassaratributs == true) {
	esborracopies('products_options');
	esborracopies('products_options_values');
	esborracopies('products_options_values_to_products_options');
	// esborracopies ('products_attributes');
	if (($traspassaratributs == true) && ($kexisteixattributesgroups == true)) {
		esborracopies('products_attributes_groups');
	}
	if (($estocatributs == true) && ($kexisteixproducts_stock == true)) {
		esborracopies('products_stock');
	}
}

if (($contribgridattributes == true) && ($kexisteixproducts_grid == true)) {
	esborracopies('products_grid_row_col');
	esborracopies('products_grid');
}

//copies d'arxius del client amb id de producte
$qi = 0;
while ($qi < count($aproductesid)) {
	esborracopiacopia($aproductesid[$qi]);
	$qi++;
}

//desbloquejem control
$strsql = "update qfacwin_ctl  set ";
$strsql .= " BLOQ = 'N'  where CTIPUS = 'TRASPAS'";
$result = mysqli_query($link, $strsql);
if ($result == false) {
	echo $crlf . "Error update qfacwin_ctl = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
	die;
}

$temps = time() - $ini;
echo idioma("Categories: ", "Categor�as: ", "Categories: ") . $nnumcategories . idioma(" Articles: ", " Art�culos: ", " Products: ") . $numproduc . $crlf;
echo $crlf . idioma("Finalitzat correctament en ", "Finalizado correctamente en ", "Successfully completed in ") . $temps . idioma(" segons", " segundos", " seconds") . $crlf;
echo "codret=ok" . $crlf;


// ---------------------------------------------------------------
// Renombra la taula i en crea una de buida amb el nom original
// ---------------------------------------------------------------
function creacopia($taula) {
	global $link, $datacopia, $strprefixtaules, $strnomdb, $crlf;

	$taula = $strprefixtaules . $taula;

	$strsql = "show create table `" . $taula . "` ";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error SQL = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	$row = mysqli_fetch_array($result);
	//agafem el create table
	$strsql = $row["Create Table"];
	// agafem a partir del paretesis
	if ($commapos = strpos($strsql, '(')) {
		$strcreate = substr($strsql, $commapos);
		//$strsql = "create table " . $strnomdb . $taula."_tmp" .$datacopia. "  ". $strcreate;
	}
	//$result = mysqli_query(  $link,  $strsql );
	//if ($result==FALSE)	{echo $crlf . "Error mysql= " . mysqli_errno( $link ).": ".mysqli_error( $link ).$crlf.$strsql; die; }

	//recuperem auto-increment
	$strsql = "SHOW TABLE STATUS LIKE '" . $taula . "' ";
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . ">Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}
	$rowstatus = mysqli_fetch_array($result);
	//echo $rowtaula["Auto_increment"]."<br>";


	//renombrem taula
	$strsql = "ALTER TABLE `" . $taula . "` RENAME zqcop_" . $taula . $datacopia;
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}

	// creem la taula
	$strsql = "create table " . $strnomdb . $taula . " " . $strcreate;
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}

	//posem auto-increment
	$nauto = 0;
	if (!empty ($rowstatus["Auto_increment"])) {
		$strsql = "alter table `" . $taula . "` AUTO_INCREMENT= " . $rowstatus["Auto_increment"];
		$result = mysqli_query($link, $strsql);
		if ($result == false) {
			echo $crlf . ">Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
			die;
		}

	}
	return;
} //fi de creacopia


// ----------------------------------------------------------
//  esborra les copies d'una taula
// ----------------------------------------------------------
function esborracopies($taula) {
	global $link, $datacopia, $cop, $strprefixtaules, $strnomdb;

	$taula = $strprefixtaules . $taula;

	//si mantenir copies no esborrem
	if ($cop <> "S") {
		$strsql = "DROP table " . $strnomdb . "zqcop_" . $taula . $datacopia;
		$result = mysqli_query($link, $strsql);
		if ($result == false) {
			echo $crlf . "Error mysql " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
			die;
		}
	}


	return;
} //fi de esborracopia


// ----------------------------------------------------------
//  afegeix dades amb l'id
// ----------------------------------------------------------
function afegeixdades($taula, $campid, $valorid) {
	global $link, $datacopia, $strprefixtaules, $strnomdb;

	$taula = $strprefixtaules . $taula;

	//afegim tots els registres amb id de categoria antic
	$strsql = "insert into " . $strnomdb . $taula . "  ";
	$strsql .= "select * from " . $strnomdb . " zqcop_" . $taula . $datacopia;
	$strsql .= " where " . $campid . " = " . $valorid . " ";
	$result = mysqli_query($link, $strsql) or die ("INS from " . $taula . "_tmp " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql);


	return;
} //fi de afegeixdades

// ----------------------------------------------------------
//  mira si existeix una taula retorna TRUE o FALSE
// ----------------------------------------------------------
function existeixtaula($taula) {
	global $link, $strprefixtaules;

	$kexist = true;
	$taula  = $strprefixtaules . $taula;
	$strsql = "show create table `" . $taula . "` ";
	try {
		$result = mysqli_query($link, $strsql);
	} catch (mysqli_sql_exception $e) {
		if ($e->getCode() == 1146) {
			return false;
		}
		throw $e;
	}
	if ($result == false) {
		if (mysqli_errno($link) == 1146) {
			$kexist = false;
		} //no existeix la taula
		else {
			echo $crlf . "Error SQL = " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
			die;
		}
	}
	return $kexist;
}

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
//  restaura les copies d'una taula si existeixen
// ----------------------------------------------------------
function restauracopia($taula) {
	global $link, $datarestaura, $crlf, $strprefixtaules;

	$taula = $strprefixtaules . $taula;

	//mirm si existeix la copia
	$strsql = "select * from zqcop_" . $taula . $datarestaura;
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		//si no existeix la copia sortir
		if (mysqli_errno($link) == 1146) {
			echo $crlf . $taula . idioma(" Recuperaci� correcte.", " Recuperaci�n correcta.", " Successful recovery.");
			return;
		}

		echo $crlf . "Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}


	//renombrem taula com zzqres_xxxxxx
	$strsql = "ALTER TABLE `" . $taula . "` RENAME zzqres_" . $taula . $datarestaura;
	$result = mysqli_query($link, $strsql);
	if ($result == false) {

		echo $crlf . "Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}


	//renombrem la copia de la taula com taula
	$strsql = "ALTER TABLE zqcop_" . $taula . $datarestaura . " RENAME " . $taula;
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error mysql= " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}

	echo $crlf . $taula . idioma(" Recuperaci� correcte a l'estat anterior .", " Recuperaci�n correcta al estado anterior.", " Database recovery to previous status done successfully");

	//esborrem la zzqres_
	$strsql = "DROP table zzqres_" . $taula . $datarestaura;
	$result = mysqli_query($link, $strsql);
	if ($result == false) {
		echo $crlf . "Error drop table: " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . $strsql;
		die;
	}


	return;
} //fi de restauracopia

// ----------------------------------------------------------
//  canvia #salt#
// ----------------------------------------------------------
function canviasalts($a) {
	$a = stripslashes($a); //treiem les barres
	$a = str_replace("#salt#", "\r\n", $a);

	return $a;
}

// ----------------------------------------------------------
//  mira si existeix un camp en una taula retorna TRUE o FALSE
// ----------------------------------------------------------
function existeixcamp($taula, $camp) {
	global $link, $strprefixtaules;

	$kexist = false;
	if (existeixtaula($taula) == true) {
		$taula  = $strprefixtaules . $taula;
		$strsql = "SELECT * FROM " . $taula . ' LIMIT 0 , 1';
		$result = mysqli_query($link, $strsql);
		$fields = mysqli_num_fields($result);
		$i      = 0;
		while ($i < $fields) {
			if (mysqli_field_name($result, $i) == $camp) {
				$kexist = true;
			}
			$i++;
		}//while
	} ///existeix taula

	return $kexist;
}

function mysqli_field_name($result, $field_offset) {
	$properties = mysqli_fetch_field_direct($result, $field_offset);
	return is_object($properties) ? $properties->name : false;
}

?>
