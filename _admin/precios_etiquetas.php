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

	require_once DIR_FS_CATALOG . 'includes/etiquetas_export.php';

	// Generacion COMPARTIDA con api-etiquetas.php. NO reimplementar aqui la logica
	// de precios: si las dos vias divergen, el cron de las etiquetas se queda con
	// precios viejos SIN dar ningun error visible.
	$pdo = fb_etq_pdo();
	$nProductos = count(fb_etq_products($pdo, $manufacturerId));

	if ($nProductos == 0) {
		$html = '<p>No hay productos que coincidan con dicho fabricante</p>';
	} else {

		$fp = fopen($fichero, 'w');
		$total = fb_etq_generar($pdo, $manufacturerId, $fp);
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
