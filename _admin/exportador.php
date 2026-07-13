<?php
require('includes/application_top.php');

ini_set('memory_limit', -1);

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
function sprice_ebay ($pdID) {
	$sql = "select specials_new_products_price from specials where products_id = $pdID and customers_group_id=3";
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
function precio_ebay($pID) {
 	$sql = "select customers_group_price from products_groups where customers_group_id=3 and products_id = $pID";
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
function precio_atributo_ebay ($paID) {
 	$sql = "select options_values_price from products_attributes_groups where customers_group_id=3 and products_attributes_id = $paID order by options_values_price asc";
    $act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['options_values_price'];
}

// funci�n c�lculo del coste por atributo.
function coste_en_atributos($opcion, $valor, $pID) {
	$sql = "select products_stock_cost from products_stock where products_stock_attributes = '".$opcion."-".$valor."' and products_id = $pID";
	$act = tep_db_query($sql) or die($sql);
	$val = tep_db_fetch_array($act);
	return $val['products_stock_cost'];	
}
function specials_min_price($pmnID) {
	$sql = "select specials_min_price from specials where products_id = $pmnID";
	$act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['specials_min_price'];
}

// Oferta de variante activa (motor auto_specials) para (pid, ovid, cgid). NULL si no hay.
function attr_offer($pID, $ovID, $cgID) {
	$sql = "select pvp_oferta, ovp_orig, prefix_orig, fecha_fin, auto_renew from auto_specials_active
	        where products_id = " . (int)$pID . " and options_values_id = " . (int)$ovID . "
	          and customers_group_id = " . (int)$cgID . " and estado = 'active'
	        order by id desc limit 1";
	$act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val ?: null;
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
        <form name="exp" method="post" action="<?php echo tep_href_link('exportador.php'); ?>">
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
	$sql = "select * from products p, products_description pd, tax_rates tr where p.products_id = pd.products_id and pd.language_id = 3 and p.products_tax_class_id=tr.tax_class_id and p.products_status = 1 and tr.tax_zone_id = 37";
	
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
		fwrite($ficheroabierto, 'Tipo'.$s.'IVA'.$s.'ID de Producto'.$s.'Modelo'.$s.'R.Proveedor'.$s.'Nombre'.$s.'PVP+IVA'.$s.'PVP Of.+IVA'.$s.'PVD+IVA'.$s.'PVD Of.+IVA'.$s.'Amazon+IVA'.$s.'Amazon Of+IVA'.$s.'ebay+IVA'.$s.'ebay Of+IVA'.$s.'Stock'.$s.'estado'.$s.'EAN'.$s.'Pcompra+Iva*'.$s.'Peso.'.$s.'Env.Gratis'.$s.'Fabricante'.$s.'Amazon Status'.$s.'C-Stock'.$s.'Excl.G.Shop.'.$s.'ebay'.$s.'Liquid.'.$s.'P.min'.$s.'Dias Of.'.$s.'Repetir Of.'.$f);
		while ($row = tep_db_fetch_array($act)) {
			fwrite ($ficheroabierto,'P'.$s.$row['tax_rate'].$s.$row['products_id'].$s.$row['products_model'].$s.$row['reference_prov'].$s.mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8').$s.round($row['products_price']*(1+($row['tax_rate']/100)),2).$s.round(sprice($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.round(precio_grupo($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.round(sprice_distibucion($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.round(precio_amazon($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.round(sprice_amazon($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.round(precio_ebay($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.round(sprice_ebay($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.$row['products_quantity'].$s.$row['products_status'].$s.$row['product_ean'].$s.round(($row['products_cost'])*(1+($row['tax_rate']/100)),2).$s.$row['products_weight'].$s.$row['products_ship_free'].$s.$row['manufacturers_id'].$s.$row['amazon_status'].$s.$row['check_stock'].$s.$row['exclude_feedmachine'].$s.$row['sincronizar_ebay'].$s.$row['products_liquidacion'].$s.round(specials_min_price($row['products_id'])*(1+($row['tax_rate']/100)),2).$s.''.$s.''.$f);
			// atributos
			$sql_2 = "select * from products_options popt, products_attributes patrib where patrib.products_id='" . $row['products_id'] . "' and patrib.options_id = popt.products_options_id and language_id=3 order by options_values_price asc";
			$act_2 = tep_db_query($sql_2) or die($sql_2);

			if (tep_db_num_rows($act_2) > 0) {
				while ($row_2 = tep_db_fetch_array($act_2)) {
					$mult_iva = 1 + ($row['tax_rate'] / 100);
					$sign0 = ($row_2['price_prefix'] === '-') ? -1 : 1;

					// Oferta de variante activa (Retail): campo 6 = precio ORIGINAL (snapshot), campo 7 = oferta
					$ofr0 = attr_offer($row['products_id'], $row_2['options_values_id'], 0);
					if ($ofr0 !== null && $ofr0['ovp_orig'] !== null) {
						$sg = ($ofr0['prefix_orig'] === '-') ? -1 : 1;
						$patrib = round(($row['products_price'] + $sg * $ofr0['ovp_orig']) * $mult_iva, 2);
						$of_retail = round($ofr0['pvp_oferta'] * $mult_iva, 2);
						$dias_of = max(1, (int)ceil((strtotime($ofr0['fecha_fin']) - time()) / 86400));
						$repetir_of = (int)$ofr0['auto_renew'];
					} else {
						$patrib = round(($row['products_price'] + $sign0 * $row_2['options_values_price']) * $mult_iva, 2);
						$of_retail = '';
						$dias_of = '';
						$repetir_of = '';
					}

					// G1: base grupo + delta G1 (con signo); si hay oferta G1 activa, original + oferta
					$g1_base = precio_grupo($row['products_id']);
					$g1q = tep_db_query("select options_values_price, price_prefix from products_attributes_groups where customers_group_id=1 and products_attributes_id = " . (int)$row_2['products_attributes_id']);
					$g1r = tep_db_fetch_array($g1q);
					$ofr1 = attr_offer($row['products_id'], $row_2['options_values_id'], 1);
					if ($ofr1 !== null && $ofr1['ovp_orig'] !== null) {
						$sg1 = ($ofr1['prefix_orig'] === '-') ? -1 : 1;
						$patrib_2 = round(($g1_base + $sg1 * $ofr1['ovp_orig']) * $mult_iva, 2);
						$of_g1 = round($ofr1['pvp_oferta'] * $mult_iva, 2);
						if ($dias_of === '') $dias_of = max(1, (int)ceil((strtotime($ofr1['fecha_fin']) - time()) / 86400));
						if ($repetir_of === '') $repetir_of = (int)$ofr1['auto_renew'];
					} else {
						$sg1 = ($g1r && $g1r['price_prefix'] === '-') ? -1 : 1;
						$delta_g1 = $g1r ? $g1r['options_values_price'] : ($sign0 * $row_2['options_values_price']);
						$patrib_2 = round(($g1_base + $sg1 * $delta_g1) * $mult_iva, 2);
						$of_g1 = '';
					}

					$patrib_3 = round((precio_amazon($row['products_id']) + precio_atributo_amazon($row_2['products_attributes_id']))*$mult_iva,2);
					$patrib_4 = round((precio_ebay($row['products_id']) + precio_atributo_ebay($row_2['products_attributes_id']))*$mult_iva,2);
					fwrite ($ficheroabierto,'A'.$s.$row['tax_rate'].$s.$row_2['products_attributes_id'].$s.$row_2['reference'].$s.$row_2['reference_prov'].$s.mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8').povname($row_2['options_values_id']).$s.$patrib.$s.$of_retail.$s.$patrib_2.$s.$of_g1.$s.$patrib_3.$s.''.$s.$patrib_4.$s.''.$s.stock_en_atributos($row_2['products_options_id'],$row_2['options_values_id'],$row['products_id']).$s.''.$s.$row_2['products_attributes_ean'].$s.round(coste_en_atributos($row_2['products_options_id'],$row_2['options_values_id'],$row['products_id'])*$mult_iva,2).$s.''.$s.''.$s.''.$s.''.$s.(int)$row_2['check_stock'].$s.''.$s.''.$s.''.$s.''.$s.$dias_of.$s.$repetir_of.$f);
				}
			}
		}
		fclose($ficheroabierto);
	}
	echo '<a href="'.$fichero.'">Descargar fichero productos.csv</a>';
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