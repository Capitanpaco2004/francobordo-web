<?php
require 'includes/application_top.php';

const TAX_ZONE_ID = 31;
const LANGUAGE_ID = 3;
const CSV_SEPARATOR = ';';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

$fichero = isset($_GET['fichero']) || isset($_POST['fichero']) ? (isset($_GET['fichero']) ? $_GET['fichero'] : $_POST['fichero']) : '';
$fichero = !empty($fichero) ? $fichero : sprintf("csv/%s.csv", date("YmdHis"));

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

$html = '';

if ($action === 'execute') {

	if (file_exists($fichero)) {
		unlink($fichero);
	}

	$manufacturerId = isset($_GET['fabricante']) ? intval($_GET['fabricante']) : 0;

	// Mapas pequeños cargados una sola vez para evitar JOINs caros en la query principal
	$taxRates = getTaxRatesMap(TAX_ZONE_ID);
	$specials = getSpecialsMap();

	$aProductsSql = getProductsByManufacturer($manufacturerId);

	if (tep_db_num_rows($aProductsSql) == 0) {
		$html = '<p>No hay productos que coincidan con dicho fabricante</p>';
	} else {

		// Recoge IDs para query bulk de atributos. Mantenemos productos en memoria
		// porque hay que escribir base + atributos juntos por producto.
		$productsData = [];
		$productIds = [];
		while ($row = tep_db_fetch_array($aProductsSql)) {
			$productsData[] = $row;
			$productIds[] = (int) $row['products_id'];
		}

		$attributesByProduct = getAttributesForProducts($productIds);

		$fp = fopen($fichero, 'w');
		$total = 0;

		foreach ($productsData as $row) {
			$productId = (int) $row['products_id'];
			$taxClassId = (int) $row['products_tax_class_id'];
			$taxRate = isset($taxRates[$taxClassId]) ? $taxRates[$taxClassId] : 0;
			$taxMultiplier = 1 + ($taxRate / 100);

			$basePrice = (float) $row['products_price'];
			$specialPrice = isset($specials[$productId]) ? (float) $specials[$productId] : 0;

			$stock = ($row['products_quantity'] < 0) ? 0 : $row['products_quantity'];
			$priceTax = round($basePrice * $taxMultiplier, 2);
			$specialTax = round($specialPrice * $taxMultiplier, 2);
			$descuento = ($specialPrice <= 0) ? 0 : number_format((1 - $specialPrice / $basePrice) * 100, 0);

			$nombreISO = mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8');

			$baseLine = [
				$productId,
				$nombreISO,
				$priceTax,
				$specialTax,
				$stock,
				$row['product_ean'],
				$descuento,
				'',
			];
			fputcsv($fp, $baseLine, CSV_SEPARATOR, '"', '');
			$total++;

			if (!empty($attributesByProduct[$productId])) {
				foreach ($attributesByProduct[$productId] as $attr) {
					$stockAttr = ($attr['products_stock_quantity'] < 0) ? 0 : $attr['products_stock_quantity'];
					$attrValue = (float) $attr['options_values_price'];
					if ($attr['price_prefix'] === '-') {
						$attrValue *= -1;
					}
					$patrib = round(($basePrice + $attrValue) * $taxMultiplier, 2);

					$attrNombreISO = mb_convert_encoding($attr['products_options_values_name'] ?? '', 'ISO-8859-1', 'UTF-8');

					$attrLine = [
						$productId . '-A' . $attr['products_attributes_id'],
						substr($nombreISO, 0, 40) . substr($attrNombreISO, 0, 35),
						$patrib,
						'0',
						$stockAttr,
						$attr['products_attributes_ean'],
						'0',
					];

					if (!empty(trim(implode('', $attrLine)))) {
						fputcsv($fp, $attrLine, CSV_SEPARATOR, '"', '');
						$total++;
					}
				}
			}
		}

		fclose($fp);

		$html = '<div id="Percent" style="height: 30px;position: relative;background-color: #fff;margin: 10px 0 0 0;border-radius: 3px;box-shadow: 0 0 3px rgba(0,0,0,.1);overflow: hidden;">
					<p style="min-width: 140px; text-align: left; position: absolute;top: 0px;left: 0px;bottom: 0px;background-color: rgb(0, 119, 171);width: 100%;color: #fff;box-sizing: border-box;padding: 5px 10px;">100% (' . $total . ' líneas exportadas)</p>
				</div>';

		$html .= '<p id="link-download" style="margin: 10px 0 0 0;"><a href="' . $fichero . '?v=' . time() . '" class="xbutton small hv9 verde"><i class="fa-solid fa-download"></i> Descargar Fichero Etiquetas</a> <a href="' . tep_href_link('precios_etiquetas.php') . '" class="xbutton small hv9"><i class="fa-solid fa-plus"></i> Crear Nuevo Fichero</a></p>';
	}
}

?>
<?php require THEME . 'html/header.php'; ?>
	<!-- body //-->
	<table id="solenopsis" class="" style="width: 100%;">
		<tbody style="background: transparent;">
		<tr>
			<td>
				<div class="oeHead column a12 row ax amiddle">
					<div class="oeTitu column a05 logo" style="padding-left: 65px;"><b><i style="top: 23px;left: 0px;font-size: 40px;line-height: 40px;color: #4FABED;" class="fas fa-file-csv"></i>Exportar Etiquetas Precios</b><small>Exporta un CSV con las etiquetas de Precios de la Tienda</small></div>
				</div>
			</td>
		</tr>
		</tbody>
	</table>

	<table border="0" width="100%" cellspacing="2" cellpadding="2">
		<tr>
			<td width="100%" valign="top">
				<table border="0" width="100%" cellspacing="0" cellpadding="0" id="solenopsis">
					<tr>
						<td>

							<?php if (!isset($_GET['action'])): ?>
								<form name="exp" method="get" action="<?php echo tep_href_link('precios_etiquetas.php'); ?>">
									Seleccione Fabricante a Exportar:

									<?php
									$selectedManufacturerId = isset($_GET['fabricante']) ? intval($_GET['fabricante']) : 0;
									echo getManufacturersFilterDropdown($selectedManufacturerId);
									?>
									</select>
									<input type="hidden" name="action" value="execute">
									<input type="hidden" name="fichero" value="<?php echo $fichero; ?>">
									<input type="submit" name="sub" value="Exportar" class="xbutton small hv9 verde">
								</form>
							<?php endif; ?>

							<?php echo $html; ?>

						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<!-- body_eof //-->
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
<?php

function getTaxRatesMap($zoneId) {
	$map = [];
	$sql = "SELECT tax_class_id, tax_rate FROM tax_rates WHERE tax_zone_id = " . intval($zoneId);
	$result = tep_db_query($sql);
	while ($row = tep_db_fetch_array($result)) {
		$map[(int) $row['tax_class_id']] = (float) $row['tax_rate'];
	}
	return $map;
}

function getSpecialsMap() {
	$map = [];
	$sql = "SELECT products_id, specials_new_products_price FROM specials";
	$result = tep_db_query($sql);
	while ($row = tep_db_fetch_array($result)) {
		$map[(int) $row['products_id']] = (float) $row['specials_new_products_price'];
	}
	return $map;
}

function getProductsByManufacturer($manufacturerId = 0) {
	$sql = "SELECT p.products_id, p.products_price, p.products_tax_class_id, p.product_ean, p.products_quantity, pd.products_name
            FROM products p
            LEFT JOIN products_description pd ON p.products_id = pd.products_id AND pd.language_id = " . LANGUAGE_ID . "
            WHERE p.products_status = 1";

	if ($manufacturerId > 0) {
		$sql .= " AND p.manufacturers_id = " . intval($manufacturerId);
	}

	$sql .= " ORDER BY p.products_id ASC";

	return tep_db_query($sql);
}

function getAttributesForProducts(array $productIds) {
	$attributesByProduct = [];

	if (empty($productIds)) {
		return $attributesByProduct;
	}

	$idList = implode(',', array_map('intval', $productIds));

	$sql = "SELECT patrib.products_id, patrib.price_prefix, popt.products_options_id, patrib.options_values_id, patrib.products_attributes_id, patrib.products_attributes_ean, ps.products_stock_quantity, patrib.options_values_price, pov.products_options_values_name
		FROM products_options popt
		INNER JOIN products_attributes patrib ON patrib.options_id = popt.products_options_id
		LEFT JOIN products_options_values pov ON pov.products_options_values_id = patrib.options_values_id AND pov.language_id = " . LANGUAGE_ID . "
		LEFT JOIN products_stock ps ON ps.products_stock_attributes = CONCAT(popt.products_options_id, '-', patrib.options_values_id) AND ps.products_id = patrib.products_id
		WHERE patrib.products_id IN ($idList) AND popt.language_id = " . LANGUAGE_ID . "
		GROUP BY patrib.products_id, patrib.options_values_id
		ORDER BY patrib.products_id ASC, patrib.options_values_price ASC";

	$result = tep_db_query($sql);

	while ($row = tep_db_fetch_array($result)) {
		$attributesByProduct[$row['products_id']][] = $row;
	}

	return $attributesByProduct;
}

function getManufacturersFilterDropdown($selectedManufacturerId = 0) {
	$manufacturers = [];
	$manufacturers[] = [
		'id' => 0,
		'text' => 'Todos',
	];

	$sql = "SELECT * FROM manufacturers ORDER BY manufacturers_name";
	$result = tep_db_query($sql);

	while ($row = tep_db_fetch_array($result)) {
		$manufacturers[] = [
			'id' => $row['manufacturers_id'],
			'text' => $row['manufacturers_name'],
		];
	}

	return tep_draw_pull_down_menu('fabricante', $manufacturers, $selectedManufacturerId);
}
