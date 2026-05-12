<?php
require('includes/application_top.php');
require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

//Sacamos valores de filtros
$sGetManufacturers = tep_db_prepare_input( $_POST['manufacturers_id'] );
if ($_POST['cPath']!=0) {
    $sGetCategories = tep_db_prepare_input( $_POST['cPath'] );
}

$aManufacturers = [ [ 'id' => '', 'text' => STATS_STOCK_ALL_STOCKS ] ];
$aCategories = [ [ 'id' => '', 'text' => STATS_STOCK_ALL_CATEGORIES ] ];
?>

<?php require(THEME . 'html/header.php');?>

<!-- body //-->
<div>
	<div class="toolbarHead">
		<div class="hdr-tlbr">
			<h1 class="pageHeading"><?php echo STATS_STOCK_TITLE; ?></h1>
				<div class="forms">
					<form id="date-range-form" method="post" action="<?php echo tep_href_link( FILENAME_STATS_STOCK ); ?>">
						<ul>
							<li>
								<?php echo categoriesList(); ?>
							</li>
							<li>
								<?php echo manufacturersList(); ?>
							</li>
						</ul>
					</form>
				</div>
	</div>
</div>


<div class="clear"></div>
<div class="box-tbl" style="width: 100%">
	<div class="box-head">
		<h6><?php echo STATS_STOCK_REPORT_DATA; ?>: <?php echo date(DATE_FORMAT_SHORT_NEW); ?></h6>
		<div class="clear"></div>
	</div>
        <ul class="tToolbar">
                <li><a href="<?php echo tep_href_link(FILENAME_STATS_STOCK, tep_get_all_get_params(['filter']).'', 'NONSSL');?>" title="<?php echo STATS_STOCK_SHOW_ALL_PRODUCTS; ?>"><span class="icos-refresh2"></span><?php echo STATS_STOCK_SHOW_ALL_PRODUCTS; ?></a></li>
                <li><a class="OutStock" href="<?php echo tep_href_link(FILENAME_STATS_STOCK, tep_get_all_get_params(['filter']).'&filter=reponer', 'NONSSL');?>" title="<?php echo STATS_STOCK_SHOW_PENDANT_PRODUCTS; ?>"><span class="icos-alert"></span><?php echo STATS_STOCK_SHOW_PENDANT_PRODUCTS; ?></a></li>
                <li><a class="OutRestock" href="<?php echo tep_href_link(FILENAME_STATS_STOCK, tep_get_all_get_params(['filter']).'&filter=restock', 'NONSSL');?>" title="<?php echo STATS_STOCK_SHOW_NO_RESTOCKING; ?>"><span class="icos-cross"></span><?php echo STATS_STOCK_SHOW_NO_RESTOCKING; ?></a></li>
                <li><a target="_blank" href="<?php echo tep_href_link(FILENAME_STATS_STOCK_PRINT, tep_get_all_get_params(['filter']) . (isset( $_GET['filter'] ) ? '&filter=' . $_GET['filter'] : '') . (isset( $_POST['manufacturers_id'] ) ? '&manufacturers_id=' . $_POST['manufacturers_id'] : '') . (isset( $_POST['cPath'] ) ? '&cPath=' . $_POST['cPath'] : ''), 'NONSSL');?>" title="<?php echo STATS_STOCK_PRINT_CURRENT_REPORT; ?>"><span class="icos-printer"></span><?php echo STATS_STOCK_PRINT_CURRENT_REPORT; ?></a></li>
            </ul>
	<table cellpadding="0" cellspacing="0" width="100%" class="tAlt wGeneral">
		<thead>
			<tr>
				<td width="100px"><?php echo STATS_STOCK_ACCIONES; ?></td>
				<td width="491px"><?php echo STATS_STOCK_PRODUCTS; ?></td>
				<td width="90px"><?php echo STATS_STOCK_MODEL; ?></td>
				<td width="80px"><?php echo STATS_STOCK_QUANTITY; ?></td>
				<td width="80px"><?php echo STATS_STOCK_IDEAL_QUANTITY; ?></td>
				<td><?php echo STATS_STOCK_PRICE; ?></td>
				<td><?php echo STATS_STOCK_PRICE_COSTE; ?></td>
				<td><?php echo STATS_STOCK_IDEAL_INVERSION; ?></td>
				<td><?php echo STATS_STOCK_IDEAL_BENEFICIO; ?></td>
			</tr>
		</thead>

		<?php
// Sql inicial
$sSql = 'SELECT DISTINCT p.products_id, pd.products_name, p.products_model, p.products_price, p.products_cost, p.products_quantity, p.products_quantity_deseada
					 FROM products p
					 INNER JOIN products_description pd on (p.products_id = pd.products_id)
					 INNER JOIN products_to_categories ptc on (p.products_id = ptc.products_id)
					 LEFT JOIN products_stock ps on (p.products_id = ps.products_id)
					 LEFT JOIN manufacturers m on (p.manufacturers_id = m.manufacturers_id)
					 WHERE pd.language_id = ' . (int)$languages_id;

// Si tenemos una categoria, añadimos filtro a consulta
if ($sGetCategories != '') {
    $sSql .= ' and ptc.categories_id = ' . $sGetCategories;
}

// Si tenemos una categoria, añadimos filtro a consulta
if ($sGetManufacturers != '') {
    $sSql .= ' and m.manufacturers_id = ' . $sGetManufacturers;
}

if( $_GET['filter'] == 'reponer'){
	$sSql .= ' and (((p.products_quantity <= ' . STOCK_REORDER_LEVEL . ') || (p.products_quantity < p.products_quantity_deseada))';
	$sSql .= ' or (ps.products_stock_quantity <= ' . STOCK_REORDER_LEVEL . '))';
}


$sSql .= ' ORDER BY ' . ($sOrder != '' ? $sOrder . ' DESC, ' : '') . ' pd.products_name, p.products_model';

// Declaro los totales vacios
$products_quantity_total=0;
$inversion_total=0;
$beneficio_total=0;

// Consultamos y creamos el array de productos
$products_query = tep_db_query($sSql);
while ($products = tep_db_fetch_array($products_query)) {
	$products_id = $products['products_id'];

	// check for product or attributes below reorder level
	$sStockSql = 'SELECT products_stock_attributes, products_stock_quantity FROM ' . TABLE_PRODUCTS_STOCK . ' WHERE products_id = ' . $products['products_id'];

	if ($_GET['filter'] == 'reponer') {
        $sStockSql .= ' and (products_stock_quantity <= ' . STOCK_REORDER_LEVEL . ')';
    }

	$sStockSql .= ' ORDER BY ' . ($sOrder != '' ? $sOrder . ' ASC, ' : '') . ' products_stock_attributes';

	$products_stock_query = tep_db_query($sStockSql);

	$products_stock_rows=tep_db_num_rows($products_stock_query);

	// Classes para las filas de producto según su stock y reposición
	$class = '';
	if (($products['products_quantity'] <= STOCK_REORDER_LEVEL) || ($products['products_quantity'] < $products['products_quantity_deseada'])) {
        $class=' class="OutStock"';
    }

	$products_quantity= $products['products_quantity'];
	$products_price=($products_stock_rows > 0) ? '&nbsp;' : $currencies->format($products['products_price']);
	$precio_coste=$currencies->format($products['products_cost']);

	if($products_stock_rows<0){
		if($products_quantity>'0'){
			$inversion=$currencies->format($products_quantity*$products['products_cost']);
			$beneficio=$currencies->format($products_quantity*$products['products_price']);
		}else{
			$inversion=$currencies->format(0);
			$beneficio=$currencies->format(0);
		}
	}

	$category_query = tep_db_query("select categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . $products['products_id'] . "'");
	$category_data = tep_db_fetch_array($category_query);
?>
			<?php
	// SQL para sacar el Stock de los Atributos
	if ($products_stock_rows > 0) {

		$products_options_name_query = tep_db_query("SELECT distinct popt.products_options_id, popt.products_options_name
					                                       FROM " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_ATTRIBUTES . " patrib
					                                       WHERE patrib.products_id='" . $products['products_id'] . "'
					                                       AND patrib.options_id = popt.products_options_id
					                                       AND popt.products_options_track_stock = '1'
					                                       AND popt.language_id = '" . (int)$languages_id . "'
					                                       ORDER BY popt.products_options_id");

		$products_options_rows=tep_db_num_rows($products_options_name_query);
?>
		<tbody>
			<tr>
				<td align="center" <?php echo $class; ?>>
					<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
		                <a class="buttonS bDefault" data-toggle="dropdown" href="#"><?php echo STATS_STOCK_ACCIONES; ?><span class="caret"></span></a>
		                <ul class="dropdown-menu">
		                	<li><a href="<?php echo tep_href_link(FILENAME_CATEGORIES, 'pID=' . $products['products_id'] . '&action=new_product'); ?>" target="_blank"><span class="icos-pencil"></span><?php echo STATS_STOCK_TEXT_EDIT; ?></a></li>
		                	<li><a href="../product_info.php?products_id=<?php echo $products['products_id']; ?>" target="_blank"><span class="icos-preview"></span><?php echo STATS_STOCK_TEXT_VIEW_IN_STORE; ?></a></li>
		                </ul>
		            </div>
				 </td>
				<td align="center" <?php echo $class; ?>><?php echo $products['products_name']; ?></td>
				<td align="center" <?php echo $class; ?>><?php echo $products['products_model']; ?></td>
				<td align="center" <?php echo $class; ?>><?php echo $products_quantity; ?></td>
				<td align="center" <?php echo $class; ?>><?php echo $products['products_quantity_deseada']; ?></td>
				<td align="center" <?php echo $class; ?>></td>
				<td align="center" <?php echo $class; ?>></td>
				<td align="center" <?php echo $class; ?>></td>
				<td align="center" <?php echo $class; ?>></td>
			</tr>
		</tbody>

		<thead>
			<tr>
				<td align="center"></td>
				<td align="center"><?php echo STATS_STOCK_PRODUCTS_OPTIONS; ?></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
			</tr>
		</thead>
		<?php
		$pricesAttributes = [];

		// Obtenemos el precio que tiene el atributo
		$aDatos = tep_db_query( 'SELECT attributes, price, specials
									 FROM products_price_attributes
									 WHERE products_id = "' . (int)$products_id . '"' );

		// Guardamos precio
		while( $aDato = tep_db_fetch_array( $aDatos ) )
			$pricesAttributes[$aDato['attributes']] = $aDato['specials'] > 0 ? $aDato['specials'] : $aDato['price'];

?>
		<tbody>
			<tr>
				<td align="center"></td>
				<td style="padding: 0; border-bottom: 0;">
					<table style="width: 100%">
							<tr>
								<?php
		// Construir los nombres de las Opciones de Atributos
		while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {
			echo '<td style="width: '. (100/$products_options_rows) . '%" align="center"><u>' . $products_options_name['products_options_name'] . '</u></td>';
		}
?>
							</tr>
					</table>
				</td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
			</tr>
		</tbody>
		<?php

		while($products_stock_values=tep_db_fetch_array($products_stock_query)) {

			$attributes=explode(",",(string) $products_stock_values['products_stock_attributes']);

			$total_price=$products['products_price'];

			// Classes para las filas de producto según su stock y reposición
			$class = '';
			if ($products_stock_values['products_stock_quantity'] <= STOCK_REORDER_LEVEL) {
                $class=' class="OutStock"';
            }

?>

		<tbody>
			<tr>
				<td align="center" <?php echo $class; ?>></td>
				<td style="padding: 0; border-bottom: 0;" <?php echo $class; ?>>
					<table style="width: 100%">
					<?php
					foreach($attributes as $attribute) {
						$attr=explode("-",$attribute);
						if ($products_options_rows > 0) {
							echo '<td align="center" style="width: ' . (100 / $products_options_rows) . '%; height: 10px" ' . $class . '>' . tep_values_name($attr[1]) . '</td>';
						} else {
							echo '<td align="center" style="width: 0%; height: 10px" ' . $class . '>' . tep_values_name($attr[1]) . '</td>';
						}

						$total_price = $pricesAttributes[$products_stock_values['products_stock_attributes']] ?? $total_price;
					}
					?>
					</table>
				</td>
				<td align="center" <?php echo $class; ?>></td>
				<?php

			/* Total Inversion/Beneficio */
			$total_price=$currencies->format($total_price);
			$products_quantity=$products_stock_values['products_stock_quantity'];

			if($products_quantity > 0){
				$inversion=$products_quantity*$products['products_cost'];
				$beneficio=$products_quantity*$products['products_price'];
			}else{
				$inversion=0;
				$beneficio=0;
			}

			$products_quantity_total+=$products_quantity;

			if($products_quantity > 0)
			{
				$inversion_total+=$inversion;
				$beneficio_total+=$beneficio;
			}
			/* Total Inversion/Beneficio */
?>
				<td align="center" <?php echo $class; ?>><?php echo $products_stock_values['products_stock_quantity']; ?></td>
				<td align="center" <?php echo $class; ?>>&nbsp;</td>
				<td align="center" <?php echo $class; ?>><?php echo $total_price; ?></td>
				<td align="center" <?php echo $class; ?>><?php echo $precio_coste; ?></td>
				<td align="center" <?php echo $class; ?>><?php echo $currencies->format($inversion); ?></td>
				<td align="center" <?php echo $class; ?>><?php echo $currencies->format(($beneficio)); ?></td>
			</tr>
		</tbody>
		<?php
		}

	}else {
		/* Total Inversion/Beneficio */
		if($products_quantity > 0){
			$inversion=$products_quantity*$products['products_cost'];
			$beneficio=$products_quantity*$products['products_price'];
		}else{
			$inversion=0;
			$beneficio=0;
		}

		$products_quantity_total+=$products_quantity;

		if($products_quantity > 0)
		{
			$inversion_total+=$inversion;
			$beneficio_total+=$beneficio;
		}
		/* Total Inversion/Beneficio */
?>
			<tbody>
				<tr>
					<td align="center" <?php echo $class; ?>>
						<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
		                <a class="buttonS bDefault" data-toggle="dropdown" href="#"><?php echo STATS_STOCK_ACCIONES; ?><span class="caret"></span></a>
		                <ul class="dropdown-menu">
		                	<li><a href="<?php echo tep_href_link(FILENAME_CATEGORIES, 'pID=' . $products['products_id'] . '&action=new_product'); ?>" target="_blank"><span class="icos-pencil"></span><?php echo STATS_STOCK_TEXT_EDIT; ?></a></li>
		                	<li><a href="../product_info.php?products_id=<?php echo $products['products_id']; ?>" target="_blank"><span class="icos-preview"></span><?php echo STATS_STOCK_TEXT_VIEW_IN_STORE; ?></a></li>
		                </ul>
		            </div>
					</td>
					<td align="center" <?php echo $class; ?>><?php echo $products['products_name']; ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $products['products_model']; ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $products_quantity; ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $products['products_quantity_deseada']; ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $products_price; ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $precio_coste; ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $currencies->format($inversion); ?></td>
					<td align="center" <?php echo $class; ?>><?php echo $currencies->format($beneficio); ?></td>
				</tr>
			</tbody>


		<?php
	}
}

$inversion_total=$currencies->format($inversion_total);
$beneficio_total=$currencies->format($beneficio_total);
?>

		<tbody>
			<tr>
				<td align="right" colspan="3"><strong><?php echo STATS_STOCK_TOTAL_RESUME; ?>:</strong></td>
				<td align="center"><strong><?php echo $products_quantity_total;?> <?php echo STATS_STOCK_UNITS; ?></strong></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"></td>
				<td align="center"><strong><?php echo $inversion_total; ?></strong></td>
				<td align="center"><strong><?php echo $beneficio_total; ?></strong></td>
			</tr>
		</tbody>
	</table>
</div>

<?php require( THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
