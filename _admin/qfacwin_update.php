<?php /* 
 QSOFT 
 http://www.qsoftnet.com
 http://www.qinvoicing.com
 
 Català:
 Traspàs automàtic de dades del programa de facturació QFACWIN a osCommerce 2.2-MS2.
  
 Español:
 Traspaso automático de datos del programa de facturación QFACWIN a osCommerce 2.2-MS2.
 
 English:
 QINVOICING integration with osCommerce 2.2-MS2.
 
 (c) Autor: Quim Herrera Joancomarti 
 qhe@mailqs.com
 
 */
// error_reporting (E_ALL); 
 
 //set_time_limit(1800); //maxim 30 minuts
$acategoriesid = array();
$aproductesid = array();
$strtarifa = array();
 
include('qfacwin_cfg.php'); 
include('includes/configure.php');


if ( ! isset($_GET['nav']) ) { die (idioma('Error en la crida: nav','Error en la llamada: nav','Parameter error:: nav')) ;}
$nav = $_GET['nav'];


if ( ! isset($_GET['cop']) ) { $cop= "N";}
else { $cop= $_GET['cop'];}


if ( ! isset($_GET['descri']) ) { $descri= "S";}
else { $descri = $_GET['descri'];}

if ( ! isset($_GET['nomespreus']) ) { $nomespreus= "N";}
else { $nomespreus = $_GET['nomespreus'];}

if ( ! isset($_GET['idioma']) ) { $idioma= "A";}
else { $idioma = $_GET['idioma'];}

if ( ! isset($_GET['tip']) ) { $tip= "";}
else { $tip = $_GET['tip'];}

$ini = time();
 
$link = mysqli_connect( DB_SERVER , DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE); 


$crlf = "\r\n"; // $crlf ; //
if ($nav == 1) { $crlf =  "<br>" ; }

//fem còpies
$datacopia  = date( "YmdHi", mktime(date("H"),date("i"), date("s"),date("m")  ,date("d"),date("Y")) );

set_time_limit(15000); //segons 15000 = 250 minuts

$strnomdb = "";



//llegim idiomes 
$strsql = "select * from " . $strnomdb . $strprefixtaules . "languages order by languages_id ";
$result = mysqli_query( $link, $strsql );
if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql;	die; } 
$aidiomes = array();
$i = 0;
while ( $rowatri = mysqli_fetch_array($result)) {
   $aidiomes[$i] = $rowatri["languages_id"];
   $i ++;
 } //while idiomes

$kexisteixproductsgroups = existeixtaula('products_groups');
$kexisteixproductsimages = existeixtaula('products_images');
$kexisteixfeatured = existeixtaula('featured');
$kexisteixattributesgroups = existeixtaula('products_attributes_groups');
$kexisteixproducts_stock = existeixtaula('products_stock');
 $kexisteixproducts_grid = existeixtaula('products_grid');
  
//-----------------------------------------------------
// mira si esta bloquejat (anterior actualitzacio)
//-----------------------------------------------------

  //mira que no estigui bloquejat
   $strsql = "select * from qfacwin_ctl  where CTIPUS = 'TRASPAS'";
   $result = mysqli_query( $link, $strsql);
   if ($result==FALSE)	{	echo $crlf .idioma("Error lectura","Error lectura","Read error"). " = " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql;	die; } 
   $rowctl = mysqli_fetch_array($result);
   if ($rowctl["BLOQ"] == "S") {
    	echo $crlf. idioma("Procés anterior bloquejat. Restaurant còpies.","Proceso anterior bloqueado. Restaurando copias","Previous process locked. Restoring copies.").$crlf ; 
		//recuperar copies 
		 $datarestaura = $rowctl["DATACOPIA"]; 
		echo $crlf. idioma("Restaurant arxiu de categories:","Restaurando archivos de categorias:","Restoring category file:") ; 
		
		restauracopia ('categories');
		restauracopia ('categories_description'); 
		
   	    echo $crlf. idioma("Restaurant arxiu de fabricants:","Restaurando archivos de fabricantes:","Restoring manufacturer file:") ; 
			
		restauracopia ('manufacturers');
        restauracopia ('manufacturers_info'); 
    
		echo  $crlf . idioma("Restaurant arxius de productes:","Restaurando archivos de  productos","Restoring products file:")  ; 
		
		restauracopia ('products');
		restauracopia ('products_description'); 
		restauracopia ('products_to_categories'); 
		restauracopia ('specials'); 
		restauracopia ('products_attributes'); 
		restauracopia ('products_notifications'); //copia notificacions dels productes
		if (($contribmorepics == TRUE) && ($kexisteixproductsimages == TRUE)) { restauracopia ('products_images');} //MorePics
		restauracopia ('reviews'); //copia reviews productes
		restauracopia ('reviews_description'); 
        if ($kexisteixproductsgroups == TRUE){ restauracopia ('products_groups');}
		if (($contribfeatured == TRUE) && ($kexisteixfeatured== TRUE)){ restauracopia ('featured');}
		
		if ($traspassaratributs == TRUE) {
  			restauracopia ('products_options');
  			restauracopia ('products_options_values'); 
  			restauracopia ('products_options_values_to_products_options');
   			//restauracopia ('products_attributes'); 
  			if (($traspassaratributs == TRUE) && ($kexisteixattributesgroups == TRUE)){ restauracopia  ('products_attributes_groups'); }
			if (($estocatributs == TRUE) && ($kexisteixproducts_stock == TRUE)){ restauracopia  ('products_stock'); }
		}
 
		if (($contribgridattributes == TRUE) && ($kexisteixproducts_grid == TRUE)){
		  restauracopia ('products_grid_row_col');
			restauracopia ('products_grid');
		}			
		
		//desbloquejem el control
		$strsql = "update qfacwin_ctl  set "; 
   		$strsql .=  " BLOQ = 'N'  where CTIPUS = 'TRASPAS'";
   		$result = mysqli_query( $link, $strsql );
   		if ($result==FALSE)	{	echo $crlf ."Error update qfacwin_ctl = " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql;	die; } 
   
       	echo  $crlf.$crlf. idioma("Còpies restaurades correctament. Torneu a executar el traspàs","Copias restauradas correctamente. Vuelva a ejecutar el traspaso.","Copies successfully restored. Run the transfer again").$crlf ; 
	    echo  $crlf."restaura=ok" ; 
		die;
   } //bloquejat
 
   //bloquejem
 /*  $strsql = "update qfacwin_ctl  set "; 
   $strsql .=  "DATACOPIA = '". $datacopia ."',  BLOQ = 'S'  where CTIPUS = 'TRASPAS' ";
   $result = mysql_query( $strsql, $link);
   if ($result==FALSE)	{	echo $crlf ."Error update qfacwin_ctl " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
  
*/



//ver 21 no fem copia actualitzem directament if (($estocatributs == TRUE) && ($kexisteixproducts_stock == TRUE)){ creacopia  ('products_stock'); }

//-----------------------------------------------------
// articles: //traspasem stock
//-----------------------------------------------------

$procesarwartic = true;
// francobordo
if ( ($intcltrac == 116) and ($tip == 'PROP') ) {$procesarwartic = false;} 

if ($procesarwartic){
	$fila = 1;
	$numproduc = 0;
	//$numoferta = 1;
	$filename = DIR_FS_CATALOG_IMAGES . "wartic.txt"; 
	//$fp = fopen ($filename ,"r");
	if(!$fp = fopen ($filename ,"r"))
	{
			 print $filename. idioma(' Arxiu no trobat. Procés cancel.lat',' Archivo no encontrado. Proceso cancelado','File not found. Process cancelled');
			 die;
	}
	
	while ($data = fgetcsv($fp, 0, ";", '"', "\\")) {  // canviat  antic: while ($data = fgetcsv ($fp, filesize($filename) , ";")) per warnings  php8
			//$num = count ($data);
			//print "<p> $num fields in line $row: <br>";
		if ($fila == 1){
			$nomcamps = $data;
		}else{
			 foreach($data as $key => $value) 
						 $row[$nomcamps[$key]] = addslashes($value); 
				 
			 if (! empty($row["CCODIART"])	){ 		
			 
				 if ($nomespreus == "N") {	
						echo idioma('Actualitzant estoc: ','Actualizando stock: ','Updating stock: ').$row["CCODIART"]	.  $crlf ;
				 } else {
						echo idioma('Actualitzant estoc/preus: ','Actualizando stock/precios: ','Updating stock: ').$row["CCODIART"]	.  $crlf ; 
				 }
				// echo  "CACTUESTOC: ". $row["CACTUESTOC"] . " CACTUPREUS: ". $row["CACTUPREUS"] ." NESTOC: ". $row["NESTOC"]. $crlf ; 
				 //modifiquem
				 $strsql = "update " . $strnomdb . $strprefixtaules . "products  set " ;
				 if ($intcltrac == 116) // francobordo
						{   $strsql .=  " products_cost = " . $row["NPCOM"] . " , ";}
						
				 if ($nomespreus == "S") { 
					 $strcoma = "";	
					 // si actualitzar estoc, fer-ho
					 if ( $row["CACTUESTOC"] == "S" ){  
						 $strsql .=  " products_quantity = " . $row["NESTOC"] . "  ";
						 $strcoma = ', ';
					 }	
					 //si actualitzar preus
					 if ( $row["CACTUPREUS"] == "S" ){
					 
						 if ($intcltrac == 139)  {   // planetaelectronico
								$strsql .=  $strcoma. "  products_cost = " . $row["NPCOM"] . " , "; //DENOX
							} //planetaelectronico 
		
							//parafarmaweb actualitzar també model, ntipiva i proveidor
							if ($intcltrac == 141)  {   // parafarmaweb DUSNIC
								$strsql .=  $strcoma. "  products_model = '" . $row["CMODEL"] . "' , "; 
								$nimpost = 0;
								if ( isset( $tipo_imp_QFACWIN[ $row["NTIPIVA"] ] ) ) {$nimpost = $tipo_imp_QFACWIN[ $row["NTIPIVA"] ];}
								else { echo  "*** ERROR **** ".idioma("Error: correspondencia tipus iva no definida: ", "Error: correspondencia tipo de impuesto no definida: ","Error: undefined tax class match: "). $row["NTIPIVA"]. $crlf ;} 
								$strsql .=  " products_tax_class_id = " . $nimpost . " , ";							 
								 //buscar per cod_proveedor que es el codi que ells posen igual al QFACWIN. Si existeix proveidor actualitzem
								$strsql2 = "select proveedor_id from " . $strnomdb . $strprefixtaules . "proveedores where cod_proveedor = '".$row["NCODIPR"] ."'" ;
								$result2 = mysqli_query( $link, $strsql2 );
								if ($result2==FALSE)	{	echo $crlf .idioma("Error lectura proveidors ","Error lectura proveedores ","Read error in providers"). " = " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql2;	die; } 
								if( $rowprove = mysqli_fetch_array( $result2 )){
								 // $idproveid =  $rowprove["proveedor_id"];
									$strsql .=  "  proveedor_id = " . $row["NCODIPR"] . " , "; //DUSNIC
								} 
							} //parafarmaweb 
								
							$strsql .=  " products_price = " .  $row["NPV".$ntarifaqfac]  . "  ";		
							
							//busquem id producte per acvtualitzar tarifes SPPC per grups
							$idproducte = 0;
							$strsql2 = "select products_id from " . $strnomdb . $strprefixtaules . "products where CCODIART = '".$row["CCODIART"] ."'" ;
							$result2 = mysqli_query( $link, $strsql2);
							if ($result2==FALSE)	{	echo $crlf .idioma("Error lectura productes ","Error lectura productos ","Read error in products"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql2;	die; } 
							if( $rowartic = mysqli_fetch_array($result2)){
								$idproducte =  $rowartic["products_id"];
							} 
							$pr = 0;
						 $strmensa = "";
							while ($pr < count($strgrupcli) ) {
								//no gravar si grups en blanc o grup client 0 que es posa a product_price, 
								if (! empty($strgrupcli[$pr])){ //aixi permet definir tarifes saltejades
									 //llegim grups antics  per agafar valors anterior
										$strsql2  = "update " . $strnomdb . $strprefixtaules . "products_groups  set " ;
										$strsql2 .=  " customers_group_price = " .  $row["NPV".$pr]   ;
										$strsql2 .=  " where products_id = " . $idproducte .  " and customers_group_id = " . $strgrupcli[$pr];
										$result2 = mysqli_query( $link, $strsql2)  or die ("update  products_groups " . mysqli_errno( $link ).": ".mysqli_error( $link ). $crlf .$strsql2);
										$strmensa .= 'NPV'.$pr.": ". $row["NPV".$pr]. ", ";
								} //grup no buit 
								$pr ++;
							}//while  	
							if (! empty($strmensa)) {	echo $strmensa . $crlf; }
					 } //$row["CACTUPREUS"] ='S' actualitzar preus
					 
				 } else { //no nomes preus
					 $strsql .=  " products_quantity = " . $row["NESTOC"] . "  ";
				 }	 
				 
				 $strsql .= " where CCODIART = '".$row["CCODIART"] ."'" ;
				// echo $strsql;
				 $result = mysqli_query( $link, $strsql )  or die ("update products " . mysqli_errno($link).": ".mysqli_error($link). $crlf .$strsql);
				 
				//no funciona. si es igual no actualitza i mysql_affected_rows retorna 0 if (mysql_affected_rows() == 0) {
				 //   echo idioma('Article no trobat: ','Artículo no encontrado: ','Product not found: ' ). $row["CCODIART"] ;/ }
				 $numproduc++;
			// echo $strsql;
			 } //hi ha dades CCODIART no buit
				
		} //no capcelera
		 $fila++;
		 
	}
	fclose ($fp);
	
	// francobordo tallem i sortim. qfacwin torna a cridar per propietats
  if ($intcltrac == 116)  {
	   $temps = time()- $ini;
     echo  idioma(" Articles: "," Articulos: ", " Products: "). $numproduc . $crlf . $crlf;
     echo  " Archivo wartic.txt procesado. Se finaliza el proceso ".  $crlf . $crlf;

	   die;
	} //francobordo
	
} //procesar wartic


//-----------------------------------------------------
// propietats
//-----------------------------------------------------

if ($traspassaratributs == TRUE) {


//--------------------------------------------------
// stock per atributs QTPRO
// els canvis aqui s'han de fer a qfacwin_insert
//--------------------------------------------------------	
if (($estocatributs == TRUE) && ($kexisteixproducts_stock == TRUE)){	

	
//------------copiar tot aixo de  qfacwin_insert  
echo $crlf. idioma("Copiant estoc per atributs QTPRO","Copiando stock por atributos QTPRO","Copying  QTPRO attributes").$crlf ; 

$fila = 1;
$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
$nnumvalp = 0;  
//$fp = fopen ($filename ,"r");
if(!$fp = fopen ($filename ,"r"))
{
     print $filename. idioma(' Arxiu no trobat. Procés cancel.lat',' Archivo no encontrado. Proceso cancelado','File not found. Process cancelled');
     die;
}

while ($data = fgetcsv ($fp, 0, ";", '"', "\\")) {  // canviat  antic: while ($data = fgetcsv ($fp, filesize($filename) , ";")) per warnings  php8$fp, filesize($filename) , ";")) {
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
			 $strsql = "select products_id from " . $strnomdb .  $strprefixtaules . "products where CCODIART = '".$row["CCODIART"] ."'" ;
					 $result = mysqli_query( $link, $strsql );
					if ($result==FALSE)	{	echo $crlf .idioma("Error lectura productes","Error lectura productos","Read error in products"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
					if( $rowartic = mysqli_fetch_array($result)){
				 $idproducte =  $rowartic["products_id"];
			 } else { 
					echo idioma("Article no trobat","Artículo no encontrado","Product not found"). ': '.$row["CCODIART"] .$crlf; 
					 $strerrorlectura = "S"; } 
	
				if ( $strerrorlectura <> "S") {		  
	 
					 $stratributs = '';
	
			 if (! empty($row["CCODIVAL1"])	){ 	
				//busquem id de la propietat
				$strsql = "select products_options_id from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP1"] ."'" ;
						$result = mysqli_query( $link, $strsql  );
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){ $idopcio =  $rowartic["products_options_id"];	} 		  
						else { 
							$idopcio = 0;
							$strerrorlectura = "S"; 
							echo $crlf .idioma("Propietat no trobada","Propiedad no encontrada","Propertie not found"). ': '.$row["CCODIPROP1"];
							}
				//busquem id del valor de la propietat
				 $strsql = "select products_options_values_id from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL1"] ."'" ;
						$result = mysqli_query( $link, $strsql);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){
					$idvalprop =  $rowartic["products_options_values_id"];	
					} else {
					echo "ERROR: " . idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL1"].  $crlf;
					$strerrorlectura = "S"; }
				$stratributs = $idopcio . '-' .	$idvalprop;
			} //hi ha codival1	  
	
			 if (! empty($row["CCODIVAL2"])	){ 	
				//busquem id de la propietat
				$strsql = "select products_options_id from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP2"] ."'" ;
						$result = mysqli_query( $link, $strsql);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){$idopcio =  $rowartic["products_options_id"];
				}  else { 
							$idopcio = 0;
							$strerrorlectura = "S"; 
							echo $crlf .idioma("Propietat no trobada","Propiedad no encontrada","Propertie not found"). ': '.$row["CCODIPROP2"];
							}		  
	
				//busquem id del valor de la propietat
				 $strsql = "select products_options_values_id from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL2"] ."'" ;
						$result = mysqli_query( $link, $strsql );
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){
						$idvalprop =  $rowartic["products_options_values_id"];
				} else {
				 echo "ERROR: " .idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL2"] .$crlf ;
					$strerrorlectura = "S"; } 
				 $stratributs .= ','. $idopcio . '-' .	$idvalprop;
			} //hi ha codival2	  
	
			 if (! empty($row["CCODIVAL3"])	){ 	
				//busquem id de la propietat
				$strsql = "select products_options_id from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP3"] ."'" ;
						$result = mysqli_query( $link, $strsql);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){		$idopcio =  $rowartic["products_options_id"];
				}  else { 
							$idopcio = 0;
							$strerrorlectura = "S"; 
							echo $crlf .idioma("Propietat no trobada","Propiedad no encontrada","Propertie not found"). ': '.$row["CCODIPROP3"];
							}		  
	
				//busquem id del valor de la propietat
				 $strsql = "select products_options_values_id from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL3"] ."'" ;
						$result = mysqli_query( $link, $strsql);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){
						$idvalprop =  $rowartic["products_options_values_id"];
				}else {
				 echo "ERROR: "  .idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL3"] . $crlf;
					$strerrorlectura = "S"; } 
				 $stratributs .= ','. $idopcio . '-' .	$idvalprop;
			} //hi ha codival3	
			
			if ( $strerrorlectura <> "S") { //no hi ha errors de lectura	
						
				//llegim antiga per article, i propietats stock valor per agafar valors anterior
			/* no hi ha còpia sempre modifiquem	
			$strsql = "select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_stock" .$datacopia. " where products_id = ". $idproducte ."  and products_stock_attributes = '". $stratributs . "'" ; 
						$result = mysqli_query( $link, $strsql);
						if ($result==FALSE)	{	echo $crlf . idioma("Error lectura a estoc atributs anterior","Error lectura en stock atributos anterior","Read error in previous stock attributes"). "  = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
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
					 if ($intcltrac == 116) // francobordo
			   			 {   $strsql .=  " products_stock_cost = " . $row["NPCOM"] . " , ";}
					$strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;
					$result = mysqli_query( $link, $strsql)  or die ("INS products_stock " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
			
				} else {
						//afegim estoc per atribut existent
					$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_stock  " ;
					$strsql .= " select * from " . $strnomdb . "zqcop_" . $strprefixtaules . "products_stock" .$datacopia;
					$strsql .= " where products_stock_id = ". $idantig ." ";
					$result = mysqli_query($link,  $strsql)  or die ("INS from products_stock  " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
					//modifiquem les dades que pujen de QFACWIN
							 $strsql  = "update " . $strnomdb . $strprefixtaules . "products_stock  set " ;
					 if ($intcltrac == 116) // francobordo
			   			 {   $strsql .=  " products_stock_cost = " . $row["NPCOM"] . " , ";}
    				 $strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;
					 $strsql .=  " where products_stock_id = ".$idantig  ; 
					 $result = mysqli_query( $link, $strsql)  or die ("update 1 products_stock " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
											 
				} //existeix
				*/		
				
				//modifiquem les dades que pujen de QFACWIN
				 $strsql  = "update " . $strnomdb . $strprefixtaules . "products_stock  set " ;
					 if ($intcltrac == 116) // francobordo
			   			 {   $strsql .=  " products_stock_cost = " . $row["NPCOM"] . " , ";}
    				 $strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;
					// $strsql .=  " where products_stock_id = ".$idantig  ; 
					 $strsql .=  " where products_id = ". $idproducte ."  and products_stock_attributes = '". $stratributs . "'" ; 
					 $result = mysqli_query( $link, $strsql)  or die ("update 1 products_stock " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
											 
									
				 } ////no hi ha errors de lectura
				} //trobat article	
			} //hi ha registres: CCODIART ple 
	} //no capcelera
   $fila++;
} //while
fclose ($fp);
} //estoc QTPRO

/*
echo $crlf. idioma("Copiant estoc per atributs QTPRO","Copiando stock por atributos QTPRO","Copying QTPRO attributes").$crlf ; 

$fila = 1;
$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
$nnumvalp = 0;  
//$fp = fopen ($filename ,"r");
if(!$fp = fopen ($filename ,"r"))
{
     print $filename. idioma(' Arxiu no trobat. Procés cancel.lat',' Archivo no encontrado. Proceso cancelado','File not found. Process cancelled');
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
			 $result = mysql_query( $strsql,  $link);
			 if ($result==FALSE)	{	echo $crlf .idioma("Error lectura productes","Error lectura productos","Read error in products"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
			 if( $rowartic = mysqli_fetch_array($result)){
				 $idproducte =  $rowartic["products_id"];
			 } 
	 
			 $stratributs = '';
	
			 if (! empty($row["CCODIVAL1"])	){ 	
				//busquem id de la propietat
				$strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP1"] ."'" ;
						$result = mysql_query( $strsql,  $link);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if ( $rowartic = mysqli_fetch_array($result)){ $idopcio =  $rowartic["products_options_id"];	
						} else {
				   	echo "ERROR: " . idioma("Propietat no trobada","Propiedad no encontrada","Property not found").": ". $row["CCODIPROP1"].  $crlf;
					$strerrorlectura = "S"; } 		  
	
				//busquem id del valor de la propietat
				 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL1"] ."'" ;
						$result = mysql_query( $strsql,  $link);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
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
						$result = mysql_query( $strsql,  $link);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){
						$idopcio =  $rowartic["products_options_id"];
						} else {
				   	echo "ERROR: " . idioma("Propietat no trobada","Propiedad no encontrada","Property not found").": ". $row["CCODIPROP2"].  $crlf;
					$strerrorlectura = "S"; } 
	
				//busquem id del valor de la propietat
				 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL2"] ."'" ;
						$result = mysql_query( $strsql,  $link);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
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
						$result = mysql_query( $strsql,  $link);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){
						$idopcio =  $rowartic["products_options_id"];
						} else {
				   	echo "ERROR: " . idioma("Propietat no trobada","Propiedad no encontrada","Property not found").": ". $row["CCODIPROP3"].  $crlf;
					$strerrorlectura = "S"; } 
	
				//busquem id del valor de la propietat
				 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL3"] ."'" ;
						$result = mysql_query( $strsql,  $link);
						if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
						if( $rowartic = mysqli_fetch_array($result)){
						$idvalprop =  $rowartic["products_options_values_id"];
				}else {
				 echo "ERROR: "  .idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL3"] . $crlf;
					$strerrorlectura = "S"; } 
				 $stratributs .= ','. $idopcio . '-' .	$idvalprop;
			} //hi ha codival3	
			
			if ( $strerrorlectura <> "S") { //no hi ha errors de lectura	

				//llegim per article, i propietats stock  per agafar valors anterior
				$strsql = "select * from " . $strnomdb . $strprefixtaules . "products_stock where products_id = ". $idproducte ."  and products_stock_attributes = '". $stratributs . "'" ; 
				$result = mysql_query( $strsql,  $link);
					if ($result==FALSE)	{	echo $crlf . idioma("Error lectura products_stock","Error lectura products_stock","Read error products_stock"). "  = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
				$boolnou = "S";
					if( $rowst = mysqli_fetch_array($result)){	$boolnou = "N";}
			
			 
			//Afegim l' estoc per atribut
			if ( $boolnou == "S"){
        		$strsql = "insert into " . $strnomdb . $strprefixtaules . "products_stock  set " ;
						$strsql .=  " products_id = " .   $idproducte . " , ";			
						$strsql .=  " products_stock_attributes = '" .  $stratributs . "' , ";
						$strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;
						$result = mysql_query( $strsql,  $link)  or die ("INS products_stock " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
			} else {			
						//modifiquem les dades que pujen de QFACWIN
					 $strsql  = "update " . $strnomdb . $strprefixtaules . "products_stock  set " ;
					 $strsql .=  " products_stock_quantity = " .  $row["NSTOCAC"] ;
					 $strsql .=  " where products_id = ". $idproducte ."  and products_stock_attributes = '". $stratributs . "'" ;
					 $result = mysql_query( $strsql,  $link)  or die ("update  products_stock " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
					 //si no existeix afegir  
					 // ull no funciona perque si el registre es igual, mysql no fa update, retorna 0 i per tant intenta afegir i casca per clau  duplicada
				  //if (mysql_affected_rows() == 0) //{	fer insert} 
							
					}	
						
				 } ////no hi ha errors de lectura
					
			} //hi ha registres: CCODIART ple 
	} //no capcelera
   $fila++;
} //while
fclose ($fp);
} //estoc QTPRO

antic */

//----------------------------------------------------
//estoc grid_attributes
// els canvis aqui s'han de fer a qfacwin_insert
//--------------------------------------------------------------
if (($contribgridattributes == TRUE) && ($kexisteixproducts_grid == TRUE)){ echo $crlf. idioma("Copiant estoc per atributs","Copiando stock por atributos","Copying  attributes stock").$crlf ; 

$fila = 1;
$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
$nnumvalp = 0;  
//$fp = fopen ($filename ,"r");
if(!$fp = fopen ($filename ,"r"))
{
     print $filename. idioma(' Arxiu no trobat. Procés cancel.lat',' Archivo no encontrado. Proceso cancelado','File not found. Process cancelled');
     die;
}

while ($data = fgetcsv ($fp, 0, ";", '"', "\\")) {  // canviat  antic: while ($data = fgetcsv ($fp, filesize($filename) , ";")) per warnings  php8$fp, filesize($filename) , ";")) {
    if ($fila == 1){
	  $nomcamps = $data;
	}else{
	   foreach($data as $key => $value) 
         //  $row[$nomcamps[$key]] = addslashes($value); 
		   $row[$nomcamps[$key]] = $value;  //ja ve escapat
		   
	   if (! empty($row["CCODIART"])	){ 	
			 $strerrorlectura = "N";
		 
		 echo idioma('Traspassant estoc per propietats: ','Traspasando stock por propiedades: ','Transferring attributes stock').$row["CCODIART"] .' '. $row["CCODIVAL1"] .' '. ($row["CCODIVAL2"] <> "_VACIO"  ?  $row["CCODIVAL2"] : "" ) .' '. $row["CCODIVAL3"].  $crlf; 
	 
				 
  	      //busquem id de l'article
		 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products where CCODIART = '".$row["CCODIART"] ."'" ;
         $result = mysqli_query( $link, $strsql);
        if ($result==FALSE)	{	echo $crlf .idioma("Error lectura productes","Error lectura productos","Read error in products"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
        if( $rowartic = mysqli_fetch_array($result)){
		   $idproducte =  $rowartic["products_id"];
		 } 
 
		 if (! empty($row["CCODIVAL1"])	){ 	
		 	//busquem id del valor de la propietat
			 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL1"] ."'" ;
         	$result = mysqli_query( $link, $strsql);
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
        	if( $rowartic = mysqli_fetch_array($result)){
			  $idvalprop1 =  $rowartic["products_options_values_id"];	
			  } else {
			  echo "ERROR: " . idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL1"].  $crlf;
			  $strerrorlectura = "S"; }
		} //hi ha codival1	  

		 if (! empty($row["CCODIVAL2"])	){ 	
		 	//busquem id del valor de la propietat
			 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL2"] ."'" ;
         	$result = mysqli_query($link, $strsql);
        	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
        	if( $rowartic = mysqli_fetch_array($result)){
		   		$idvalprop2 =  $rowartic["products_options_values_id"];
		 	} else {
			 echo "ERROR: " .idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL2"] .$crlf ;
			  $strerrorlectura = "S"; } 
				} //hi ha codival2	  

		 if (! empty($row["CCODIVAL3"])	){ 	
				echo $crlf .idioma("Error la botiga només adment 2 propietats","Error la tienda sólo admite 2 propiedades","Error store only supports 2 properties"). "  " .$row["CCODIART"] .' '. $row["CCODIVAL1"] .' '. $row["CCODIVAL2"] .' '. $row["CCODIVAL3"];	die; 
	 	
		} //hi ha codival3	
		
		if ( $strerrorlectura <> "S") { //no hi ha errors de lectura	
					
			//llegim products_grid per article, i propietats 
			$strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_grid where products_id = ". $idproducte ."  and row_options_value_id = ". $idvalprop1 ."  and col_options_value_id = ". $idvalprop2; 
			$result = mysqli_query($link, $strsql);
    	if ($result==FALSE)	{	echo $crlf . idioma("Error lectura a grid atributs ","Error lectura en grids anterior","Read error in grid stock attributes"). "  = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
			$boolnou = "S";
			if( $rowantiga = mysqli_fetch_array($result)){
				$boolnou = "N";
				$idantig =  $rowantiga["products_grid_id"];
			 } 

			//Afegim l' estoc per atribut
			if ( $boolnou == "S"){
				echo ( $crlf. "products_grid not exist "  .$row["CCODIART"] .' '. $row["CCODIVAL1"] .' '. $row["CCODIVAL2"] );
			} else {
						//afegim estoc per atribut existent(ja esta fet)
						//modifiquem les dades que pujen de QFACWIN
						$strstatus = "1";
						if (($row["NSTOCAC"] ) == "0" ) { $strstatus = "0";}
					 $strsql  = "update " . $strnomdb . $strprefixtaules . "products_grid  set " ;
				   $strsql .=  " grid_quantity = " .  $row["NSTOCAC"]. " , " ;
						$strsql .=  " grid_status = " . $strstatus;
					 $strsql .=  " where products_grid_id = ".$idantig  ; 
				 $result = mysqli_query( $link, $strsql)  or die ("update 1 products_stock " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
										 
					} //existeix
			
			 } ////no hi ha errors de lectura
				
	  } //hi ha registres: CCODIART ple 
	} //no capcelera
   $fila++;
} //while
fclose ($fp);
} //estoc grid attributes


//-----------------------------------------------------------------
//motorraiz l'estoc esta a product_atributes i nomes es pujen el que tenen 1 sola propietat
//-----------------------------------------------------------------
if ($intcltrac == 138) {

echo $crlf. idioma("Copiant estoc per atributs","Copiando stock por atributos de los artículos con 1 propiedad","Copying attributes").$crlf ; 

$fila = 1;
$filename = DIR_FS_CATALOG_IMAGES . "wstocp.txt";
$nnumvalp = 0;  
//$fp = fopen ($filename ,"r");
if(!$fp = fopen ($filename ,"r"))
{
     print $filename. idioma(' Arxiu no trobat. Procés cancel.lat',' Archivo no encontrado. Proceso cancelado','File not found. Process cancelled');
     die;
}

while ($data = fgetcsv ($fp, 0, ";", '"', "\\")) {  // canviat  antic: while ($data = fgetcsv ($fp, filesize($filename) , ";")) per warnings  php8
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
				 $result = mysqli_query( $link, $strsql);
			   if ($result==FALSE)	{	echo $crlf .idioma("Error lectura productes","Error lectura productos","Read error in products"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
				 if( $rowartic = mysqli_fetch_array($result)){
					  $idproducte =  $rowartic["products_id"];
				 } else { 
						echo idioma("Article no trobat","Artículo no encontrado","Product not found"). ': '.$row["CCODIART"] .$crlf; 
						$strerrorlectura = "S";
				 } 
	
				if ( $strerrorlectura <> "S") {		  
	 
					 $stratributs = '';
	
				 if (! empty($row["CCODIVAL1"])	){ 	
					 //busquem id de la propietat
					 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options where CCODIPROP = '".$row["CCODIPROP1"] ."'" ;
					 $result = mysqli_query($link, $strsql);
				  	if ($result==FALSE)	{	echo $crlf .idioma("Error lectura propietats","Error lectura propiedades","Read error in properties"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
							if( $rowartic = mysqli_fetch_array($result)){ $idopcio =  $rowartic["products_options_id"];	} 		  
							else { 
								$idopcio = 0;
								$strerrorlectura = "S"; 
								echo $crlf .idioma("Propietat no trobada","Propiedad no encontrada","Propertie not found"). ': '.$row["CCODIPROP1"];
								}
					//busquem id del valor de la propietat
					 $strsql = "select * from " . $strnomdb .  $strprefixtaules . "products_options_values where CCODIVAL = '".$row["CCODIVAL1"] ."'" ;
							$result = mysqli_query( $link,$strsql);
							if ($result==FALSE)	{	echo $crlf .idioma("Error lectura valors propietats","Error lectura valores propiedades","Read error in propertie values"). " = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
							if( $rowartic = mysqli_fetch_array($result)){
						$idvalprop =  $rowartic["products_options_values_id"];	
						} else {
						echo "ERROR: " . idioma("Valor Propietat no trobada","Valor Propiedad no encontrada","Property value not found").": ". $row["CCODIVAL1"].  $crlf;
						$strerrorlectura = "S"; }
					
					} //hi ha codival1	  
		
			 } //no hi ha errors de lectura
			 
		 	 if ( $strerrorlectura <> "S") { //no hi ha errors de lectura	
					//actualitzem estoc
					$strsql  = "update " . $strnomdb . $strprefixtaules . "products_attributes  set " ;
					$strsql .=  " products_quantity = " .  $row["NSTOCAC"] ;
					$strsql .=  " where products_id = ". $idproducte . " and  options_id = " .  $idopcio. " and options_values_id = " . $idvalprop ; 
					$result = mysqli_query( $link, $strsql)  or die ("update 1 products_attributes " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
			 } ////no hi ha errors de lectura
	
			} //hi ha registres: CCODIART ple 
	  } //no capcelera
   $fila++;
  } //while
fclose ($fp);
} //motorraiz


} //traspassar atributs

/* ver 21 ja no
if ($traspassaratributs == TRUE) {
  if (($estocatributs == TRUE) && ($kexisteixproducts_stock == TRUE)){esborracopies('products_stock'); }
}	
*/

$temps = time()- $ini;
if ($tip <> 'PROP') {  echo  idioma(" Articles: "," Articulos: ", " Products: "). $numproduc. $crlf; }

echo $crlf . idioma("Finalitzat correctament en ","Finalizado correctamente en ","Successfully completed in "). $temps . idioma(" segons"," segundos"," seconds"). $crlf ;
 echo "codret=ok". $crlf;
 
 die;
  
// --------------------------------------------------------------- 
// Renombra la taula i en crea una de buida amb el nom original 
// --------------------------------------------------------------- 
function creacopia($taula) {
global $link, $datacopia, $strprefixtaules, $strnomdb, $crlf; 

$taula = $strprefixtaules . $taula;

$strsql = "show create table `". $taula."` ";
$result = mysqli_query( $link, $strsql);
if ($result==FALSE)	{	echo $crlf ."Error SQL = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
$row = mysqli_fetch_array($result);
//agafem el create table 
$strsql = $row["Create Table"];
// agafem a partir del paretesis
 if ($commapos = strpos($strsql,  '(')) {
   $strcreate =  substr($strsql,  $commapos);
   //$strsql = "create table " . $strnomdb . $taula."_tmp" .$datacopia. "  ". $strcreate; 
   }
 //$result = mysql_query( $strsql,  $link);
 //if ($result==FALSE)	{echo $crlf . "Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link).$crlf.$strsql; die; }

 //recuperem auto-increment
 $strsql = "SHOW TABLE STATUS LIKE '" . $taula . "' ";
 $result = mysqli_query( $link, $strsql);
 if ($result==FALSE)	{	echo $crlf.">Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; }
 $rowstatus = mysqli_fetch_array($result);
 //echo $rowtaula["Auto_increment"]."<br>";
 
	
//renombrem taula
$strsql = "ALTER TABLE `". $taula ."` RENAME zqcop_". $taula .$datacopia ; 
$result = mysqli_query( $link, $strsql);
if ($result==FALSE)	{echo $crlf."Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; }

// creem la taula
 $strsql = "create table " . $strnomdb . $taula ." ". $strcreate; 
 $result = mysqli_query( $link, $strsql);
 if ($result==FALSE) {echo $crlf . "Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link).$crlf.$strsql; die; }

 //posem auto-increment
 $nauto = 0;
 if ( ! empty ($rowstatus["Auto_increment"])) {
   $strsql = "alter table `". $taula. "` AUTO_INCREMENT= " . $rowstatus["Auto_increment"];
   $result = mysqli_query( $link, $strsql);
   if ($result==FALSE)	{	echo $crlf.">Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; }

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
if ( $cop <> "S"){
  $strsql = "DROP table "  . $strnomdb . "zqcop_" . $taula . $datacopia   ;
  $result = mysqli_query( $link, $strsql);
  if ($result==FALSE)	{	echo $crlf . "Error mysql "  . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 
}


return;
 } //fi de esborracopia


 // ---------------------------------------------------------- 
//  afegeix dades amb l'id
// ---------------------------------------------------------- 
function afegeixdades($taula, $campid, $valorid) {
global $link, $datacopia,$strprefixtaules, $strnomdb;  
 
$taula = $strprefixtaules . $taula;

  //afegim tots els registres amb id de categoria antic
  $strsql = "insert into " .  $strnomdb . $taula .  "  " ;
  $strsql .= "select * from " .  $strnomdb . " zqcop_" . $taula . $datacopia;
  $strsql .= " where ". $campid . " = ". $valorid ." ";
  $result = mysqli_query( $link, $strsql )  or die ("INS from " . $taula . "_tmp " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql);
	        
  
return;
 } //fi de afegeixdades

// ---------------------------------------------------------- 
//  mira si existeix una taula retorna TRUE o FALSE
// ---------------------------------------------------------- 
/* function existeixtaula($taula) {
global $link, $strprefixtaules; 

$kexist = TRUE;
$taula = $strprefixtaules . $taula;
$strsql = "show create table `". $taula."` ";
$result = mysqli_query( $link, $strsql );
if ($result==FALSE)	{
  if (mysqli_errno( $link )== 1146){ $kexist = FALSE;} //no existeix la taula
  else{ echo $crlf ."Error SQL = " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql; die; } 
}
return $kexist;
} */
 
 // ---------------------------------------------------------- 
//  mira si existeix una taula retorna TRUE o FALSE
// ---------------------------------------------------------- 
function existeixtaula($taula) {
global $link, $strprefixtaules; 

$kexist = TRUE;
$taula = $strprefixtaules . $taula;
$strsql = "show create table `". $taula."` ";
$strerror =	executeQuery(
    $link,
    $strsql,
    '',
    [1146 => '']
  );

$result = mysqli_query( $link, $strsql);
if ($strerror == 1)	{ $kexist = FALSE;} //no existeix la taula
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
global $link, $datarestaura, $crlf,$strprefixtaules;
 
$taula = $strprefixtaules . $taula;

//mirm si existeix la copia
$strsql = "select * from zqcop_". $taula .$datarestaura;
$result = mysqli_query( $link, $strsql );
if ($result==FALSE)	{
//si no existeix la copia sortir
  if (mysqli_errno( $link )== 1146){ 
    echo  $crlf. $taula. idioma(" Recuperació correcte."," Recuperación correcta."," Successful recovery."); 
    return;}
	
  echo $crlf."Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; 
  }

 
//renombrem taula com zzqres_xxxxxx
$strsql = "ALTER TABLE `". $taula ."` RENAME zzqres_". $taula .$datarestaura ; 
$result = mysqli_query( $link, $strsql);
if ($result==FALSE)	{

  echo $crlf."Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; 
  }

 
	
//renombrem la copia de la taula com taula 
$strsql = "ALTER TABLE zqcop_". $taula .$datarestaura. " RENAME ". $taula  ; 
$result = mysqli_query( $link, $strsql );
if ($result==FALSE)	{echo $crlf."Error mysql= " . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; }

echo  $crlf. $taula. idioma(" Recuperació correcte a l'estat anterior ."," Recuperación correcta al estado anterior."," Database recovery to previous status done successfully"); 

//esborrem la zzqres_
 $strsql = "DROP table " .  $strnomdb . "zzqres_". $taula .$datarestaura  ;
  $result = mysqli_query( $link, $strsql );
  if ($result==FALSE)	{	echo $crlf . "Error drop table: "  . mysqli_errno( $link ).": ".mysqli_error($link). $crlf .$strsql;	die; } 

    
 return;
 } //fi de restauracopia

// ---------------------------------------------------------- 
//  canvia #salt#
// ---------------------------------------------------------- 
function canviasalts($a) {
$a= stripslashes( $a); //treiem les barres 
$a= str_replace ( "#salt#", "\r\n", $a);

return $a;
}  


// ---------------------------------------------------------- 
//  Executa una consulta i gestiona errors
// ---------------------------------------------------------- 
function executeQuery($link, $query, $successMessage, $errorMessages = []) {
global $crlf;
$strerror = 0;
   // Gestionar errors de manera compatible amb totes les versions de PHP
    if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
        // Per PHP 7.0 i posteriors
        mysqli_report(MYSQLI_REPORT_OFF); // Desactivar informes d'errors per evitar excepcions automàtiques
    }

    $result = mysqli_query($link, $query);

    if ($result === FALSE) {
        $errorCode = mysqli_errno($link);
        $errorMessage = mysqli_error($link);

        if (array_key_exists($errorCode, $errorMessages)) {
    				$strerror = 1;
            echo $errorMessages[$errorCode] . "$crlf";
        } else {
				    $strerror = 2;
            echo "Error ($errorCode): $errorMessage $crlf Query: $query $crlf";
            die;
        }
    } else {
        echo $successMessage . " $crlf";
    }
return $strerror;		
}

?>