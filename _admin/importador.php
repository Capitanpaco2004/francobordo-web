<?php
require('includes/application_top.php');
function opp($paID) {
	$sql_pre = "select products_id from products_attributes where products_attributes_id = " . (int)$paID;
	$act_pre = tep_db_query($sql_pre) or die($sql_pre);
	$pID = tep_db_fetch_array($act_pre);
	$pID = (int)($pID['products_id'] ?? 0);
	if ($pID === 0) return 0;
	$sql = "select products_price from products where products_id = $pID";
	$act = tep_db_query($sql) or die($sql);
	$val = tep_db_fetch_array($act);
	return $val['products_price'] ?? 0;
}
function opp_2($paIDG2) {
	$sql_preG2 = "select products_id from products_attributes_groups where products_attributes_id = $paIDG2 and customers_group_id = 1";
	$act_preG2 = tep_db_query($sql_preG2) or die($sql_preG2);
	$pIDG2 = tep_db_fetch_array($act_preG2);
	$pIDG2 = $pIDG2['products_id'];
	if (empty($pIDG2)) return 0;
	$sqlG2 = "select customers_group_price from products_groups where products_id = $pIDG2 and customers_group_id=1";
	$actG2 = tep_db_query($sqlG2) or die($sqlG2);
	$valG2= tep_db_fetch_array($actG2);
	return $valG2['customers_group_price'];
}
function opp_3($paIDG3) {
	$sql_preG3 = "select products_id from products_attributes_groups where products_attributes_id = $paIDG3 and customers_group_id = 2";
	$act_preG3 = tep_db_query($sql_preG3) or die($sql_preG3);
	$pIDG3 = tep_db_fetch_array($act_preG3);
	$pIDG3 = $pIDG3['products_id'];
	if (empty($pIDG3)) return 0;
	$sqlG3 = "select customers_group_price from products_groups where products_id = $pIDG3 and customers_group_id=2";
	$actG3 = tep_db_query($sqlG3) or die($sqlG3);
	$valG3 = tep_db_fetch_array($actG3);
	return $valG3['customers_group_price'];
}

function opp_4($paIDG4) {
	$sql_preG4 = "select products_id from products_attributes_groups where products_attributes_id = $paIDG4 and customers_group_id = 3";
	$act_preG4 = tep_db_query($sql_preG4) or die($sql_preG4);
	$pIDG4 = tep_db_fetch_array($act_preG4);
	$pIDG4 = $pIDG4['products_id'];
	if (empty($pIDG4)) return 0;
	$sqlG4 = "select customers_group_price from products_groups where products_id = $pIDG4 and customers_group_id=3";
	$actG4 = tep_db_query($sqlG4) or die($sqlG4);
	$valG4 = tep_db_fetch_array($actG4);
	return $valG4['customers_group_price'];
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
				<form name="imp" method="post" action="<?php echo tep_href_link('importador.php'); ?>" enctype="multipart/form-data">
					Archivo productos.csv: <input type="file" name="productos"> <input type="submit" name="sub" value="Importar datos">
				</form>
<?php
} else {
	// Obtenemos la fecha del último lanzamiento
	$aDate = tep_db_query( 'select configuration_value from configuration where configuration_key = "IMPORT_DATE_LAUNCH"' );
	$aDate = tep_db_fetch_array( $aDate );
	$aDate = $aDate['configuration_value'];

	$ext = substr(strrchr($_FILES['productos']['name'],"."),1);
	$nombre_archivo = date('dmyhms').".".$ext;
	if ((move_uploaded_file($_FILES['productos']['tmp_name'], "csv/".$nombre_archivo)) and (strtolower($ext) == 'csv')) {
		$archivo = file('csv/'.$nombre_archivo);
		$lineas = count($archivo);
		for ($i=1;$i<$lineas;$i++) {
			$campo = explode(';', $archivo[$i]);
			$iva=1+($campo[1]/100);
			if ($campo[0] == 'P') {
				// Comprobamos si el producto se ha modificado
				//$aAux = tep_db_query( 'select products_id from products where products_id = "' . $campo[2] . '" and (products_last_modified is null or products_last_modified > "' . $aDate . '")' );

				// Si el producto no se ha modificado desde la ultima importacion, pasamos
				//if( tep_db_num_rows( $aAux ) == 0 )
					//continue;

				// producto
				$sql = "update products set products_price = " . $campo[6]/$iva ." where products_id = ".$campo[2];
				$act = tep_db_query($sql) or die($sql);
				echo 'Producto '. $campo[2] .' - '. $campo[3] . ' - ' . $campo[5] . ' - ' . $campo[16] . ' - ' .  $campo[6] . '€ - actualizado<br>';
				$sql_8 = "update products set products_status = " . $campo[15] ." where products_id = ".$campo[2];
				$act_8 = tep_db_query($sql_8) or die($sql_8);
				$sql_10 = "update products set products_weight = " . $campo[18] ." where products_id = ".$campo[2];
				$act_10 = tep_db_query($sql_10) or die($sql_10);
				$sql_11 = "update products set products_ship_free = " . $campo[19] ." where products_id = ".$campo[2];
				$act_11 = tep_db_query($sql_11) or die($sql_11);
				$sql_12 = "update products set manufacturers_id = " . $campo[20] ." where products_id = ".$campo[2];
				$act_12 = tep_db_query($sql_12) or die($sql_12);
				$sql_13 = "update products set products_model = ". "'". $campo[3] . "'" ." where products_id = ".$campo[2];
				$act_13 = tep_db_query($sql_13) or die($sql_13);
				$sql_15 = "update products set amazon_status = " . $campo[21] ." where products_id = ".$campo[2];
				$act_15 = tep_db_query($sql_15) or die($sql_15);
				$sql_16 = "update products set check_stock = " . $campo[22] ." where products_id = ".$campo[2];
				$act_16 = tep_db_query($sql_16) or die($sql_16);
				// modificacion para actualizar ofertas
				if ($campo[7] !='' && $campo[7] !=0 ) {
				$sql_4 = "delete from specials where products_id = ". $campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
				$sql_2 ="insert into specials (products_id, specials_new_products_price, specials_min_price) values ('".$campo[2]. "'," .$campo[7]/$iva."," . $campo[26]/$iva . " )";
				$act_2 = tep_db_query($sql_2) or die($sql_2);			 
				} else {
				$sql_4 = "delete from specials where products_id = ". $campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
			}
				// Modificacion para crear oferta si está en liquidacion
				if (($campo[25]==1 && $campo[7] =='') || ($campo[25]==1 && $campo[7] ==0)) {
				$sql_4 = "delete from specials where products_id = ". $campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
				$sql_26 ="insert into specials (products_id, specials_new_products_price, specials_min_price) values ('".$campo[2]. "'," .$campo[6]*0.95/$iva."," . $campo[26]/$iva . " )";
				$act_26 = tep_db_query($sql_26) or die($sql_26);
}
				// Modificacion para actualizar precios grupo
				$sql_3 = "update products_groups set customers_group_price = " .$campo[8]/$iva. " where customers_group_id =1 and products_id = ".$campo[2];
				$act_3 = tep_db_query($sql_3) or die($sql_3);
				$sql_14 = "update products_groups set customers_group_price = " .$campo[10]/$iva. " where customers_group_id =2 and products_id = ".$campo[2];
				$act_14 = tep_db_query($sql_14) or die($sql_14);
				$sql_22 = "update products_groups set customers_group_price = " .$campo[12]/$iva. " where customers_group_id =3 and products_id = ".$campo[2];
				$act_22 = tep_db_query($sql_22) or die($sql_22);
				// modificacion para actualizar ofertas grupo
				if ($campo[9] !=0 && $campo[9] !='' ) {
				$sql_5 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=1";
				$act_5 = tep_db_query($sql_5) or die($sql_5);
				$sql_6 ="insert into specials (products_id, specials_new_products_price, customers_group_id) values ('".$campo[2]. "'," .$campo[9]/$iva.",1)";
				$act_6 = tep_db_query($sql_6) or die($sql_6);			 
				} else {
				$sql_7 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=1";
				$act_7 = tep_db_query($sql_7) or die($sql_7);
			}
				if ($campo[11] !=0 && $campo[11] !='' ) {
				$sql_16 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=2";
				$act_16 = tep_db_query($sql_16) or die($sql_16);
				$sql_17 ="insert into specials (products_id, specials_new_products_price, customers_group_id) values ('".$campo[2]. "'," .$campo[11]/$iva.",2)";
				$act_17 = tep_db_query($sql_17) or die($sql_17);			 
				} else {
				$sql_18 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=2";
				$act_18 = tep_db_query($sql_18) or die($sql_18);
			}
				if ($campo[13] !=0 && $campo[13] !='' ) {
				$sql_23 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=3";
				$act_23 = tep_db_query($sql_23) or die($sql_23);
				$sql_24 ="insert into specials (products_id, specials_new_products_price, customers_group_id) values ('".$campo[2]. "'," .$campo[13]/$iva.",2)";
				$act_24 = tep_db_query($sql_24) or die($sql_24);			 
				} else {
				$sql_25 = "delete from specials where products_id = ". $campo[2] . " and customers_group_id=3";
				$act_25 = tep_db_query($sql_25) or die($sql_25);
			}
				// Modificacion para actualizar EAN Producto
				
				if ($campo[16] !=0 && $campo[16] !='' ) {
				$sql_9 = "update products set product_ean =  ". "'". $campo[16] . "'" ." where products_id = ".$campo[2];
				$act_9 = tep_db_query($sql_9) or die($sql_9);
					}
				//Actualizo el feedmachine
				$sql_20 = "update products set exclude_feedmachine = " . $campo[23] ." where products_id = ".$campo[2];
				$act_20 = tep_db_query($sql_20) or die($sql_20);
				//Actualizo productos que sincronizan ebay
				$sql_21 = "update products set sincronizar_ebay = " . $campo[24] ." where products_id = ".$campo[2];
				$act_21 = tep_db_query($sql_21) or die($sql_21);
				// Actualizamos la fecha de última modificacion
				$sql_update = 'update products set products_last_modified = NOW() where products_id = "' . $campo[2] . '"';
				tep_db_query($sql_update) or die($sql_update);
				$sql_27 = "update products set products_liquidacion = " . $campo[25] ." where products_id = ".$campo[2];
				$act_27 = tep_db_query($sql_27) or die($sql_27);
				$sql_28 = "update products set reference_prov = ". "'". $campo[4] . "'" ." where products_id = ".$campo[2];
				$act_28 = tep_db_query($sql_28) or die($sql_28);
			}
			if ($campo[0] == 'A') {
				// El atributo puede haber sido borrado de la web pero seguir en el CSV de QFac.
				// Si ya no existe, lo saltamos para no romper opp()/opp_2/3/4 con un products_id vacio.
				$aChk = tep_db_query("select products_attributes_id from products_attributes where products_attributes_id = ".(int)$campo[2]);
				if (tep_db_num_rows($aChk) == 0) {
					echo 'Atributo '. (int)$campo[2] .' - '. $campo[3] .' - ya no existe en la web - saltado<br>';
					continue;
				}
				// Comprobamos si el atributo se ha modificado
				//$aAux = tep_db_query( 'select products_attributes_id from products_attributes where products_attributes_id = "' . $campo[2] . '" and (attributes_last_modified is null or attributes_last_modified > "' . $aDate . '")' );

				// Si el atributo no se ha modificado desde la ultima importacion, pasamos
				//if( tep_db_num_rows( $aAux ) == 0 )
					//continue;

				// atributo
				$precioatributo = $campo[6] - opp($campo[2])*$iva;
				$sql = "update products_attributes set options_values_price = '". abs( $precioatributo/$iva ) ."', price_prefix = '" . ($precioatributo/$iva < 0 ? "-" : "+") . "' where products_attributes_id = ".$campo[2];
				$act = tep_db_query($sql) or die($sql);
				echo 'Atributo '. $campo[2] . ' - ' . $campo[3] . ' - ' . $campo[5] . ' - ' . $campo[16] . ' - ' .  $campo[6] . '€ - actualizado<br>';
				$precioatributo_2 = $campo[8] - opp_2($campo[2])*$iva;
				$sql_2 = "update products_attributes_groups set options_values_price = '". abs( $precioatributo_2/$iva ) ."', price_prefix = '" . ($precioatributo_2/$iva < 0 ? "-" : "+") . "' where customers_group_id=1 and products_attributes_id = ".$campo[2];
				$act_2 = tep_db_query($sql_2) or die($sql_2);
				$precioatributo_3 = $campo[10] - opp_3($campo[2])*$iva;
				$sql_4 = "update products_attributes_groups set options_values_price = '". abs( $precioatributo_3/$iva ) ."', price_prefix = '" . ($precioatributo_3/$iva < 0 ? "-" : "+") . "' where customers_group_id=2 and products_attributes_id = ".$campo[2];
				$act_4 = tep_db_query($sql_4) or die($sql_4);
				$sql_6 = "update products_attributes_groups set options_values_price = '". abs( $precioatributo_4/$iva ) ."', price_prefix = '" . ($precioatributo_4/$iva < 0 ? "-" : "+") . "' where customers_group_id=3 and products_attributes_id = ".$campo[2];
				$act_6 = tep_db_query($sql_6) or die($sql_6);
				$precioatributo_4 = $campo[12] - opp_4($campo[2])*$iva;
				$sql_3 = "update products_attributes set reference = ". "'". $campo[3] . "'" ." where products_attributes_id = ".$campo[2];
				$act_3 = tep_db_query($sql_3) or die($sql_3);
					// Modificacion para actualizar EAN Atributos
				
				if ($campo[16] !=0 && $campo[16] !='' ) {
				$sql_5 = "update products_attributes set products_attributes_ean = ". "'". $campo[16] . "'" ." where  products_attributes_id = ".$campo[2];
				$act_5 = tep_db_query($sql_5) or die($sql_5);
				$sql_7 = "update products_attributes set reference_prov = ". "'". $campo[4] . "'" ." where products_attributes_id = ".$campo[2];
				$act_7 = tep_db_query($sql_7) or die($sql_7);
					}

				// Actualizamos la fecha de última modificacion
				$sql_update = 'update products_attributes set attributes_last_modified = NOW() where products_attributes_id = "' . $campo[2] . '"';
				tep_db_query($sql_update) or die($sql_update);
				$sql_update = 'update products set products_last_modified = NOW() where products_id IN (SELECT products_id FROM products_attributes where products_attributes_id = "' . $campo[2] . '")';
				tep_db_query($sql_update) or die($sql_update);
			}
		}

		// Actualizamos la fecha de lanzamiento del importador
		tep_db_query( 'update configuration set configuration_value = NOW() WHERE configuration_key = "IMPORT_DATE_LAUNCH"' );

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