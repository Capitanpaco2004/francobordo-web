<?php
require('includes/application_top.php');
function opp($paID) {
	$sql_pre = "select products_id from products_attributes where products_attributes_id = $paID";
	$act_pre = tep_db_query($sql_pre) or die($sql_pre);
	$pID = tep_db_fetch_array($act_pre);
	$pID = $pID[0];
	$sql = "select products_price from products where products_id = $pID";
	$act = tep_db_query($sql) or die($sql);
	$val = tep_db_fetch_array($act);
	return $val[0];
}
function opp_2($paID) {
	$sql_pre = "select products_id from products_attributes_groups where products_attributes_id = $paID";
	$act_pre = tep_db_query($sql_pre) or die($sql_pre);
	$pID = tep_db_fetch_array($act_pre);
	$pID = $pID[0];
	$sql = "select customers_group_price from products_groups where products_id = $pID and customers_group_id=1";
	$act = tep_db_query($sql) or die($sql);
	$val = tep_db_fetch_array($act);
	return $val[0];
}
function opp_3($paID) {
	$sql_pre = "select products_id from products_attributes_groups where products_attributes_id = $paID";
	$act_pre = tep_db_query($sql_pre) or die($sql_pre);
	$pID = tep_db_fetch_array($act_pre);
	$pID = $pID[0];
	$sql = "select customers_group_price from products_groups where products_id = $pID and customers_group_id=2";
	$act = tep_db_query($sql) or die($sql);
	$val = tep_db_fetch_array($act);
	return $val[0];
}

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
				<form name="imp" method="post" action="<?php echo tep_href_link('importador_antiguo.php'); ?>" enctype="multipart/form-data">
					Archivo productos.csv: <input type="file" name="productos"> <input type="submit" name="sub" value="Importar datos">
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
			if ($campo[0] == 'P') {

				// producto
				$sql = "update products set products_price = " . $campo[5]/$iva ." where products_id = ".$campo[2];
				$act = tep_db_query($sql) or die($sql);
				$sql_8 = "update products set products_status = " . $campo[12] ." where products_id = ".$campo[2];
				$act_8 = tep_db_query($sql_8) or die($sql_8);
				$sql_10 = "update products set products_weight = " . $campo[15] ." where products_id = ".$campo[2];
				$act_10 = tep_db_query($sql_10) or die($sql_10);
				$sql_11 = "update products set products_ship_free = " . $campo[16] ." where products_id = ".$campo[2];
				$act_11 = tep_db_query($sql_11) or die($sql_11);
				$sql_12 = "update products set manufacturers_id = " . $campo[17] ." where products_id = ".$campo[2];
				$act_12 = tep_db_query($sql_12) or die($sql_12);
				$sql_13 = "update products set products_model = ". "'". $campo[3] . "'" ." where products_id = ".$campo[2];
				$act_13 = tep_db_query($sql_13) or die($sql_13);
				$sql_15 = "update products set amazon_status = " . $campo[18] ." where products_id = ".$campo[2];
				$act_15 = tep_db_query($sql_15) or die($sql_15);
				// modificacion para actualizar ofertas
				if ($campo[6] !='' && $campo[6] !=0 ) {
				$sql_4 = "delete from specials where products_id = ". $campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
				$sql_2 ="insert into specials (products_id, specials_new_products_price) values ('".$campo[2]. "'," .$campo[6]/$iva.")";
				$act_2 = tep_db_query($sql_2) or die($sql_2);			 
				} else {
				$sql_4 = "delete from specials where products_id = ". $campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
			}
				// Modificacion para actualizar precios grupo
				$sql_3 = "update products_groups set customers_group_price = " .$campo[7]/$iva. " where customers_group_id =1 and products_id = ".$campo[2];
				$act_3 = tep_db_query($sql_3) or die($sql_3);
				$sql_14 = "update products_groups set customers_group_price = " .$campo[9]/$iva. " where customers_group_id =2 and products_id = ".$campo[2];
				$act_14 = tep_db_query($sql_14) or die($sql_14);
				// modificacion para actualizar ofertas grupo
				if ($campo[8] !=0 && $campo[8] !='' ) {
				$sql_5 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=1";
				$act_5 = tep_db_query($sql_5) or die($sql_5);
				$sql_6 ="insert into specials (products_id, specials_new_products_price, customers_group_id) values ('".$campo[2]. "'," .$campo[8]/$iva.",1)";
				$act_6 = tep_db_query($sql_6) or die($sql_6);			 
				} else {
				$sql_7 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=1";
				$act_7 = tep_db_query($sql_7) or die($sql_7);
			}
				if ($campo[10] !=0 && $campo[10] !='' ) {
				$sql_16 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=2";
				$act_16 = tep_db_query($sql_16) or die($sql_16);
				$sql_17 ="insert into specials (products_id, specials_new_products_price, customers_group_id) values ('".$campo[2]. "'," .$campo[10]/$iva.",2)";
				$act_17 = tep_db_query($sql_17) or die($sql_17);			 
				} else {
				$sql_18 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=2";
				$act_18 = tep_db_query($sql_18) or die($sql_18);
			}
				// Modificacion para actualizar EAN Producto
				
				if ($campo[13] !=0 && $campo[13] !='' ) {
				$sql_9 = "update products set product_ean = " . $campo[13] ." where (product_ean ='' or product_ean=0) and products_id = ".$campo[2];
				$act_9 = tep_db_query($sql_9) or die($sql_9);
					}

			}
			if ($campo[0] == 'A') {

				// atributo
				$precioatributo = $campo[5] - opp($campo[2])*$iva;
				$sql = "update products_attributes set options_values_price = ".$precioatributo/$iva." where products_attributes_id = ".$campo[2];
				$act = tep_db_query($sql) or die($sql);
				$precioatributo_2 = $campo[7] - opp_2($campo[2])*$iva;
				$sql_2 = "update products_attributes_groups set options_values_price = ".$precioatributo_2/$iva." where customers_group_id=1 and products_attributes_id = ".$campo[2];
				$act_2 = tep_db_query($sql_2) or die($sql_2);
				$precioatributo_3 = $campo[9] - opp_3($campo[2])*$iva;
				$sql_4 = "update products_attributes_groups set options_values_price = ".$precioatributo_3/$iva." where customers_group_id=2 and products_attributes_id = ".$campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
				$sql_3 = "update products_attributes set reference = ". "'". $campo[3] . "'" ." where products_attributes_id = ".$campo[2];
				$act_3 = tep_db_query($sql_3) or die($sql_3);
					// Modificacion para actualizar EAN Atributos
				
				if ($campo[13] !=0 && $campo[13] !='' ) {
				$sql_5 = "update products_attributes set products_attributes_ean = " . $campo[13] ." where (products_attributes_ean ='' or products_attributes_ean=0) and products_attributes_id = ".$campo[2];
				$act_5 = tep_db_query($sql_5) or die($sql_5);
					}

			}
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