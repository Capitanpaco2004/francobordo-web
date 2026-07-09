<?php
require('includes/application_top.php');

define('HEADING_TITLE', 'Importador productos en CSV');
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
				<form name="imp" method="post" action="<?php echo tep_href_link('importador_better_together.php'); ?>" enctype="multipart/form-data">
					Archivo productos_juntos.csv: <input type="file" name="productos"> <input type="submit" name="sub" value="Importar datos">
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
			$iva=1+($campo[1]/100);
		

				// producto (columna price eliminada 2026-07-08: solo se remapea products_id)
				$sql_2 = "update products_together set products_id = " . $campo[3] ." where products_together_id = ".$campo[0];
				$act_2 = tep_db_query($sql_2) or die($sql_2);
				
				
				
		
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