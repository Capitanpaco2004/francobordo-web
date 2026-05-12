<?php require('includes/application_top.php'); ?>
<?php require(THEME . 'html/header.php'); ?>
<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo 'Exportar Precios Etiquetas en csv'; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td class="smallText">
<?php if (!$_POST) { ?>
        <form name="exp" method="post" action="<?php echo tep_href_link('precios_etiquetas_antiguo.php'); ?>">
        	Seleccione el fabricante a exportar:
        	<select name="fabricante">
        		<option value="0">Todos</option>
<?php
$sql = "select * from manufacturers order by manufacturers_name";
$act = tep_db_query($sql) or die($sql);
while ($row = tep_db_fetch_array($act)) {
	echo '<option value="'.$row['manufacturers_id'].'">'.$row['manufacturers_name'].'</option>';
}
?>
        	</select>
        	<input type="submit" name="sub" value="Exportar">
        </form>
<?php
} else {
	$sql = "select * from products p, products_description pd, tax_rates tr where p.products_id = pd.products_id and pd.language_id = 3 and p.products_tax_class_id=tr.tax_class_id and tr.tax_zone_id=31 and p.products_status=1";
	if ($_POST['fabricante'] > 0) $sql .= " and p.manufacturers_id = ".$_POST['fabricante']." ";
	$sql .= " order by p.products_id asc";
	$act = tep_db_query($sql) or die($sql);

	if (tep_db_num_rows($act) == 0) {
		echo 'No hay productos que coincidan con dicho fabricante';
	} else {
		$s = ';';
		$f = "\n";
		$fichero="csv/etiquetas.csv";
		$ficheroabierto = fopen($fichero,"w") or die ("El fichero no se ha podido abrir");
		while ($row = tep_db_fetch_array($act)) {
if (sprice($row['products_id'])<=0){
$descuento=0;
}else{
$descuento= number_format((1-sprice($row['products_id'])/$row['products_price'])*100,0);
}
if ($row['products_quantity']<0) {
$stock=0;
}else{
$stock=$row['products_quantity'];
}

			fwrite ($ficheroabierto,$row['products_id'].$s.mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8').$s.round($row['products_price']*(1+($row['tax_rate']/100)),2).$s.round(sprice($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.$stock.$s.$row['product_ean'].$s.$descuento.$s.$f);
			// atributos
			$sql_2 = "select * from products_options popt, products_attributes patrib where patrib.products_id='" . $row['products_id'] . "' and patrib.options_id = popt.products_options_id and language_id=3 order by options_values_price asc";
			$act_2 = tep_db_query($sql_2) or die($sql_2);

			if (tep_db_num_rows($act_2) > 0) {
				while ($row_2 = tep_db_fetch_array($act_2)) {
if (stock_en_atributos($row_2['products_options_id'],$row_2['options_values_id'],$row['products_id'])<0) {
$stock_atributos=0;
}else{
$stock_atributos=stock_en_atributos($row_2['products_options_id'],$row_2['options_values_id'],$row['products_id']);
}

					$patrib = round(($row['products_price'] + $row_2['options_values_price'])*(1+($row['tax_rate']/100)),2);
				        $patrib_2 = round((precio_grupo($row['products_id']) + precio_atributo_distribucion($row_2['products_attributes_id'])*(1+($row['tax_rate']/100))),2);

					fwrite ($ficheroabierto,$row['products_id'].'-A'.$row_2['products_attributes_id'].$s.substr(mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8'),0,40).substr(povname($row_2['options_values_id']),0,35).$s.$patrib.$s.'0'.$s. $stock_atributos.$s.$row_2['products_attributes_ean'].$s.'0'.$s.$f);
				}
			}
		}
		fclose($ficheroabierto);
	}
	echo '<a href="'.$fichero.'">Descargar fichero etiquetas.csv</a>';

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
<?php
	function sprice($pID) {
	$sql = "select specials_new_products_price from specials where products_id = $pID";
	$act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['specials_new_products_price'];
}
function sprice_distibucion ($pdID) {
	$sql = "select specials_new_products_price from specials where products_id = $pdID and customers_group_id=1";
	$act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['specials_new_products_price'];
}
function povname($povID) {
	$sql = "select products_options_values_name from products_options_values where products_options_values_id = $povID and language_id = 3";
	$act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return '~'.mb_convert_encoding($val['products_options_values_name'] ?? '', 'ISO-8859-1', 'UTF-8');
}
function precio_grupo($pID) {
 	$sql = "select customers_group_price from products_groups where customers_group_id=1 and products_id = $pID";
        $act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['customers_group_price'];
}
function precio_atributo_distribucion ($paID) {
 	$sql = "select options_values_price from products_attributes_groups where products_attributes_id = $paID order by options_values_price asc";
        $act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['options_values_price'];
}

// función cálculo del coste por atributo.
function coste_en_atributos($opcion, $valor, $pID) {
	$sql = "select products_stock_cost from products_stock where products_stock_attributes = '".$opcion."-".$valor."' and products_id = $pID";
	$act = tep_db_query($sql) or die($sql);
	$val = tep_db_fetch_array($act);
	return $val['products_stock_cost'];
}
?>