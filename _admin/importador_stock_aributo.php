<?php
require('includes/application_top.php');

define('HEADING_TITLE', 'Insertar stock en atribtos en CSV');
?>
<?php require(THEME . 'html/header.php'); ?>

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td class="smallText">
<?php if (!$_POST) { ?>
				<form name="imp" method="post" action="<?php echo tep_href_link('importador_stock_aributo.php'); ?>" enctype="multipart/form-data">
					Archivo productos-stock.csv: <input type="file" name="productos"> <input type="submit" name="sub" value="Importar datos">
				</form>
<?php
} else {

	$ext = substr(strrchr($_FILES['productos']['name'],"."),1);
	$nombre_archivo = date('dmyhms').".".$ext;
	if ((move_uploaded_file($_FILES['productos']['tmp_name'], "csv/".$nombre_archivo)) and (strtolower($ext) == 'csv')) {
		$archivo = file('csv/'.$nombre_archivo);
		$lineas = count($archivo);
		for ($i=1;$i<$lineas;$i++) {
			$campo = explode(';', $archivo[$i]);
				

				// stock
				$sql = "insert into products_stock values(DEFAULT, " . $campo[8].", '".$campo[7]."', ".$campo[3]. ", 0.0000)";
				$act = tep_db_query($sql) or die($sql);
			

		}


		echo 'Importación finalizada.';
	} else {
		echo 'error al subir archivo, inténtelo de nuevo.';
	}
}
?>
        </td>
      </tr>
    </table></td>
  </tr>
</table>
<!-- body_eof //-->
<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>