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

 
 
  error_reporting (E_ALL);
 
if ( ! isset($_GET['idioma']) ) { $idioma= "A";}
else { $idioma = $_GET['idioma'];}
  
include('includes/configure.php');
//include('../includes/configure.php');

 $dirimatges = HTTP_SERVER ;
// if (  DIR_WS_HTTPS_CATALOG <> '' ) { $dirimatges .= DIR_WS_HTTPS_CATALOG ;}
// else { $dirimatges .= DIR_WS_HTTP_CATALOG ;}
//zencart te posat el host a dir_ws_catalog_images amb lo que no funciona
 $dirimatges .= DIR_WS_CATALOG_IMAGES;
 //zencart te posat el host a dir_ws_catalog_images amb lo que no funciona 
 $pos = strpos (strtoupper(DIR_WS_CATALOG_IMAGES), "HTTP://");
 if ( ! ($pos === false)) { $dirimatges = DIR_WS_CATALOG_IMAGES;}
 $pos = strpos (strtoupper(DIR_WS_CATALOG_IMAGES), "HTTPS://");
 if ( ! ($pos === false)) { $dirimatges = DIR_WS_CATALOG_IMAGES;}

 ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<?php   if ($idioma == "A") : ?>
  <title>QINVOICING Test</title>
<?php  else :?>
	<title>QFACWIN Test</title>    
<?php  endif ?>	
</head>

<body>
<br><br><br>
<h3>
 <?php echo  idioma("Connexió QFACWIN - osCommerce realitzada correctament.","Conexión QFACWIN - osCommerce realizada correctamente.","QINVOICING - osCommerce connection successfully completed")?>
</h3>
<br>
<strong>URL images:</strong> <?php echo  $dirimatges ?><br><br>
 <?php echo  idioma("Si no podeu veure l'imatge, el directori ftp d'imatges que heu posat no es correcte.","Si no puede ver la imagen, el directorio ftp de imagenes que ha puesto no es correcto.","If you cannot see the image, the ftp image directory you have entered is not correct. ")?>
<br><br>
<?php   if ($idioma == "A") : ?>
  <img src="<?php echo $dirimatges ?>qinvoicing150x30.gif" alt="Logo QINVOICING" width="150" height="27" border="0">
<?php  else :?>
  <img src="<?php echo $dirimatges ?>qfacwin55x55.gif" alt="Logo QFACWIN" width="55" height="55" border="0">  
<?php  endif ?>	

<?php $filename = DIR_FS_CATALOG_IMAGES . "qfacwin_test.txt"; 

if(!$fp = fopen ($filename ,"w+"))
{
     print '<br><br><h3>'.$filename.'<br>'. idioma("ERROR: el directori ".DIR_FS_CATALOG_IMAGES." ha de tenir permisos 777 per poder crear arxius temporals de les dades a traspasar.<br>Doneu permisos 777 al directori", "ERROR: la carpeta ".DIR_FS_CATALOG_IMAGES." debe tener permisos 777 para poder crear archivos temporales de los datos a traspasar.<br>De permisos 777 al directorio.","Impossible to create the file (give 777 permissions to the images directory)").'</h3>';
     die;
}
fclose ($fp);
unlink ($filename);?>
<br>
<?php echo  idioma("Carpeta images: permisos 777 OK.","Carpeta images: permisos 777 OK.","Carpeta images: permissions 777 OK. ")?>


</body>
</html>
<?php 
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

