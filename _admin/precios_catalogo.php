<?php
require 'includes/application_top.php';

$s = '	';
$f = "\n";

$html = '';
$scripts = '';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

$fichero="csv/Precios_Catalogo.txt";

// Límites de memoria y tiempo
set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

switch ($action) {
	case 'execute':

		if (file_exists($fichero)) {
			unlink($fichero);
		}

		// Llamada a la función para obtener el array de products_id con atributos
		$productsWithAttributes = getProductsAttributes();

		// Llamada a la función para obtener el resultado de la consulta
		$manufacturerId = isset($_GET['fabricante']) ? intval($_GET['fabricante']) : 0;
		$aProductsSql = getProductsByManufacturer($manufacturerId);

		if (tep_db_num_rows($aProductsSql) == 0) {
			$html = '<p>No hay productos que coincidan con dicho fabricante</p>';
		} else {

			$script = [];
			$header = 'ID' . $s . 'Modelo' . $s . 'Nombre' . $s . 'PVP con IVA';
			$data[] = $header;

			while ($row = tep_db_fetch_array($aProductsSql)) {
				$precio = number_format(tep_add_tax((($row['special_price'] <= 0) ? $row['products_price'] : $row['specials_new_products_price']), $row['tax_rate']),2,',','.');

				$line = 'P-'.$row['products_id'] . $s . $row['products_model'] . $s . mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8') . $s . $precio . $f;
				$line = trim($line);

				$hasAttributes = in_array($row['products_id'], $productsWithAttributes);

				$data[] = $line;

				$script[] = [
					'products_id' => $row['products_id'],
					'tax_rate' => $row['tax_rate'],
					'products_price' => $precio,
					'products_name' => $row['products_name'],
					'products_has_attributes' => $hasAttributes,
				];
			}

			$all_data = implode("\n", $data);
			file_put_contents($fichero, $all_data . "\n");
		}

		$scripts = '<script>
						var products = ' . json_encode($script) . ';
						console.log(products);
					</script>';
		$scripts .= '<script src="theme/web/js/ajaxq.js" type="text/javascript"></script>';

		$html = '<div id="Percent" style="height: 30px;position: relative;background-color: #fff;margin: 10px 0 0 0;border-radius: 3px;box-shadow: 0 0 3px rgba(0,0,0,.1);overflow: hidden;">
					<p style="min-width: 140px; text-align: left; position: absolute;top: 0px;left: 0px;bottom: 0px;background-color: rgb(0, 119, 171);width: 100%;color: #fff;box-sizing: border-box;padding: 5px 10px;"></p>
				</div>
				';

		$html .= '<p id="link-download" style="display: none; margin: 10px 0 0 0;"><a href="' . $fichero . '?v=' . time() . '" class="xbutton small hv9 verde"><i class="fa-solid fa-download"></i> Descargar Fichero Precios Catálogo</a> <a href="' . tep_href_link('precios_catalogo.php') . '" class="xbutton small hv9"><i class="fa-solid fa-plus"></i> Crear Nuevo Fichero</a></p>';

		break;

	case 'attributes':
		$tax_rate = $_POST['tax_rate'];
		$products_price = $_POST['products_price'];
		$products_id = $_POST['products_id'];
		$products_name = $_POST['products_name'];

		$sql_2 = "SELECT patrib.reference, popt.products_options_id, patrib.options_values_id , patrib.products_attributes_id, patrib.options_values_price, pov.products_options_values_name
			FROM products_options popt
			LEFT JOIN products_attributes patrib ON patrib.options_id = popt.products_options_id
			LEFT JOIN products_options_values pov ON pov.products_options_values_id = patrib.options_values_id AND pov.language_id = 3
			LEFT JOIN products_attributes_groups pag ON pag.products_attribu tes_id = patrib.products_attributes_id
			WHERE patrib.products_id='" . $products_id . "' AND popt.language_id = 3 GROUP BY options_values_id ORDER BY options_values_price asc";

		$act_2 = tep_db_query($sql_2);
		$line = '';

		if (tep_db_num_rows($act_2) > 0) {
			while ($row_2 = tep_db_fetch_array($act_2)) {

				echo 'Precio . '.$products_price . ' y option price ' . $row_2['options_values_price'];
				// Verifica si options_values_price es mayor que 0
				if ($row_2['options_values_price'] > 0.0000) {
					// Calcula el precio con impuestos
					$products_price_sum = str_replace(',', '.', $products_price); // Reemplaza la coma por un punto
					$precio = number_format($products_price_sum + tep_add_tax($row_2['options_values_price'], $tax_rate), 2, ',', '.');
				} else {
					// Si options_values_price es 0 o menor, no se agrega impuesto
					$precio = $products_price;
				}

				$line = 'A-'.$row_2['products_attributes_id'] . $s . $row_2['reference'] . $s . mb_convert_encoding('~'.$row_2['products_options_values_name'], 'ISO-8859-1', 'UTF-8') . $s . $precio . $f;
				$line = trim($line);
				$data .= $line . PHP_EOL;
			}
			file_put_contents($fichero, $data, FILE_APPEND);
		}

		die();

		break;
}

?>
<?php require THEME . 'html/header.php'; ?>
	<!-- body //-->
	<table id="solenopsis" class="" style="width: 100%;">
		<tbody style="background: transparent;">
		<tr>
			<td>
				<div class="oeHead column a12 row ax amiddle">
					<div class="oeTitu column a05 logo" style="padding-left: 65px;"><b><i class="fas fa-file-lines"></i>Exportar Precios Catálogo</b><small>Exporta un TXT con las etiquetas de Precios de la Tienda</small></div>
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
								<form name="exp" method="get" action="<?php echo tep_href_link('precios_catalogo.php'); ?>">
									Seleccione Fabricante a Exportar:

									<?php
									// Llamada a la función
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
<?php echo $scripts; ?>
<?php if (($_GET['action'] ?? '') == 'execute'): ?>
	<script>
		$(document).ready(function () {
			var batchSize = 25; // Tamaño del lote
			var n = 0;
			var total = 0; // Inicializa el total en 0

			// Función para calcular el total de productos, incluyendo los productos base y sus atributos
			function calculateTotal() {
				total = products.length; // Inicializa el total con la cantidad de productos base

				// Itera sobre los productos y suma la cantidad de atributos para cada producto
				$.each(products, function (key, value) {
					if (value.attributes && value.attributes.length > 0) {
						total += value.attributes.length;
					}
				});
			}

			// Llama a la función para calcular el total
			calculateTotal();

			function processBatch(startIndex) {
				var endIndex = Math.min(startIndex + batchSize, total);

				for (var i = startIndex; i < endIndex; i++) {
					var value = products[i];
					var isAttribute = i > products.length - 1; // Comprueba si es un atributo

					// Solo realiza la petición si el producto tiene atributos (has_attributes == 1)
					if (value.products_has_attributes == 1) {
						$.ajaxq('products', {
							type: "POST",
							url: "precios_catalogo.php",
							data: {
								action: "attributes",
								products_id: isAttribute ? value.products_id : value.products_id,
								tax_rate: isAttribute ? value.tax_rate : value.tax_rate,
								products_name: isAttribute ? value.products_name : value.products_name,
								products_price: isAttribute ? value.products_price : value.products_price,
								fichero: "<?php echo $fichero; ?>"
							},
							success: function (salida) {
								++n;

								percent = (n * 100) / total;
								$('#Percent p').css('width', percent + '%');
								$('#Percent p').text(parseInt(percent) + '% (' + n + ' de ' + total + ' productos procesados)');

								if (n == total) {
									$('#link-download').slideDown(100);
								}
							}
						});
					} else {
						// Si no tiene atributos, simplemente incrementa n y actualiza el progreso
						++n;
						percent = (n * 100) / total;
						$('#Percent p').css('width', percent + '%');
						$('#Percent p').text(parseInt(percent) + '% (' + n + ' de ' + total + ' productos procesados)');

						if (n == total) {
							$('#link-download').slideDown(100);
						}
					}
				}

				if (endIndex < total) {
					// Si hay más productos y atributos por procesar, llama a processBatch nuevamente después de un breve retraso
					setTimeout(function () {
						processBatch(endIndex);
					}, 100);
				}
			}

			console.log(products);

			// Comienza el procesamiento en lotes después de calcular el total
			processBatch(0);
		});
	</script>


<?php endif; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
<?php

function precio_grupo($pID)
{
	$sql = "select customers_group_price from products_groups where customers_group_id=1 and products_id = $pID";
	$act = tep_db_query($sql);
	$val = tep_db_fetch_array($act);
	return $val['customers_group_price'];
}

function getProductsAttributes() {
	$productsWithAttributes = [];

	// Consulta para obtener todos los products_id de la tabla products_attributes
	$sqlAttributes = "SELECT DISTINCT products_id FROM products_attributes";
	$attributesResult = tep_db_query($sqlAttributes);

	while ($attributeRow = tep_db_fetch_array($attributesResult)) {
		$productsWithAttributes[] = $attributeRow['products_id'];
	}

	return $productsWithAttributes;
}

function getProductsByManufacturer($manufacturerId = 0) {
	$sql = "SELECT p.products_id, pd.products_name, p.products_model, p.products_price, tr.tax_rate, p.product_ean, s.specials_new_products_price
            FROM products p
            LEFT JOIN products_description pd ON p.products_id = pd.products_id
            LEFT JOIN specials s ON p.products_id = s.products_id
            LEFT JOIN tax_rates tr ON (p.products_tax_class_id = tr.tax_class_id)
            WHERE p.products_status = 1 AND pd.language_id = 3 AND tr.tax_zone_id = 31";

	if ($manufacturerId > 0) {
		$sql .= " AND p.manufacturers_id = " . intval($manufacturerId) . " ";
	}

	$sql .= " GROUP BY p.products_id";
	$sql .= " ORDER BY p.products_id ASC";

	$act = tep_db_query($sql);

	return $act;
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
