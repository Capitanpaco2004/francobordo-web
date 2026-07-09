<?php
require('includes/application_top.php');
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
function sprice_amazon ($pdID) {
	$sql = "select specials_new_products_price from specials where products_id = $pdID and customers_group_id=2";
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
function precio_amazon($pID) {
 	$sql = "select customers_group_price from products_groups where customers_group_id=2 and products_id = $pID";
    $act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['customers_group_price'];
}
function precio_atributo_distribucion ($paID) {
 	$sql = "select options_values_price from products_attributes_groups where customers_group_id=1 and products_attributes_id = $paID order by options_values_price asc";
    $act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['options_values_price'];
}
function precio_atributo_amazon ($paID) {
 	$sql = "select options_values_price from products_attributes_groups where customers_group_id=2 and products_attributes_id = $paID order by options_values_price asc";
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
<?php require(THEME . 'html/header.php'); ?>

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo 'Exportar productos en csv'; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td class="smallText">
<?php if (!$_POST) { ?>
        <form name="exp" method="post" action="<?php echo tep_href_link('exportador_better_together.php'); ?>">
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
	$sql = "select * from products p, products_together pt, products_description pd, tax_rates tr where  pt.products_id =p.products_id and pt.products_id = pd.products_id and pd.language_id =  3 and p.products_tax_class_id=tr.tax_class_id and tr.tax_zone_id=31 and p.products_status=1";
	
	if ($_POST['fabricante'] > 0) $sql .= " and p.manufacturers_id = ".$_POST['fabricante']." ";
	$sql .= " order by p.products_id asc";
	$act = tep_db_query($sql) or die($sql);
	if (tep_db_num_rows($act) == 0) {
		echo 'No hay productos que coincidan con dicho fabricante';
	} else {
		$s = ';';
		$f = "\n";
		$fichero="csv/productos.csv";
		$ficheroabierto = fopen($fichero,"w") or die ("El fichero no se ha podido abrir");
		fwrite($ficheroabierto, 'PT ID'.$s.'IVA'.$s.'ID Principal'.$s.'ID Relacionado'.$s.'Modelo'.$s.'P.Relacionado'.$s.'PVP+IVA'.$s.'PVP Of.+IVA'.$s.'Pcompra+Iva*'.$f);
		while ($row = tep_db_fetch_array($act)) {

			fwrite ($ficheroabierto, $row['products_together_id'].$s.$row['tax_rate'].$s.$row['parent_id'].$s.$row['products_id'].$s.$row['products_model'].$s.mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8').$s.round($row['products_price']*(1+($row['tax_rate']/100)),2).$s.round(($row['price'] ?? 0)*(1+($row['tax_rate']/100)),2).$s.round(($row['products_cost'])*(1+($row['tax_rate']/100)),2).$f);
		}
		fclose($ficheroabierto);
	}
	echo '<a href="'.$fichero.'">Descargar fichero productos_juntos.csv</a>';
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