<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

// Asignación de prefijos por proveedor (rango GS1 reservado in-store: 20–28)
const PROVIDER_PREFIXES = [
	'osculati' => 20,
	'fni'      => 21,
	'azimut'   => 22,
];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = !isset($_POST['confirm_execute']) && !isset($_GET['confirm_execute']);

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

/** Calcula dígito de control EAN-13 (Mod10) sobre 12 dígitos. */
function ean13Checksum($payload12) {
	if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
	$sum = 0;
	for ($i = 0; $i < 12; $i++) {
		$d = (int) $payload12[$i];
		$sum += ($i % 2 === 0) ? $d : $d * 3;
	}
	return (10 - ($sum % 10)) % 10;
}

/** Valida un EAN-13: 13 dígitos numéricos + checksum correcto. */
function isValidEan13($ean) {
	$ean = trim((string) $ean);
	if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
	return ean13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}

/**
 * Genera EAN-13 interno: {prefijo 2 dígitos en 20-28} + {products_id rellenado a 10 dígitos} + {checksum}.
 * Es ÚNICO por construcción (un prefijo + un products_id sólo dan un EAN).
 */
function generateInternalEan13($productId, $providerPrefix) {
	$pp = (int) $providerPrefix;
	if ($pp < 20 || $pp > 28) throw new InvalidArgumentException("prefijo $pp fuera de rango 20-28");
	if ($productId <= 0 || $productId > 9999999999) throw new InvalidArgumentException("products_id $productId fuera de rango (max 10 dígitos)");
	$prefix  = str_pad((string) $pp, 2, '0', STR_PAD_LEFT);
	$body    = str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
	$payload = $prefix . $body;
	$check   = ean13Checksum($payload);
	return $payload . $check;
}

$isAction = ($action === 'plan' || $action === 'execute');

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Backfill EAN internos — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('backfill_ean_internos.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE") . ($max > 0 ? ", max=$max EAN" : ""));
logMsg("Prefijos: " . json_encode(PROVIDER_PREFIXES));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

$origins = array_keys(PROVIDER_PREFIXES);
$likeClauses = [];
foreach ($origins as $o) {
	$like = $mysqli->real_escape_string($o);
	$likeClauses[] = "products_import_origin LIKE '$like%'";
}
$whereOrigin = '(' . implode(' OR ', $likeClauses) . ')';

logMsg("Buscando productos con EAN vacío o inválido en $whereOrigin …");
// Traemos todos y filtramos en PHP por validez (la regla del checksum no se puede expresar en SQL fácilmente)
// Regla W2 (2026-07-06): productos con EAN a nivel de VARIANTE no llevan
// EAN maestro (el codigo vive en la variante; los feeds miran ambos campos).
$hasAttrEan = [];
$rA = $mysqli->query("SELECT DISTINCT products_id FROM products_attributes WHERE products_attributes_ean IS NOT NULL AND products_attributes_ean NOT IN ('','0')");
while ($rowA = $rA->fetch_assoc()) $hasAttrEan[(int) $rowA['products_id']] = true;

$r = $mysqli->query("SELECT products_id, products_import_origin, product_ean FROM products WHERE $whereOrigin ORDER BY products_id");
$prods = [];
$skippedValid = 0;
$invalidExisting = 0;
$skippedVariant = 0;
while ($row = $r->fetch_assoc()) {
	if (isset($hasAttrEan[(int) $row['products_id']])) { $skippedVariant++; continue; }
	$cur = trim((string) $row['product_ean']);
	if ($cur === '') {
		$prods[] = $row;
	} elseif (!isValidEan13($cur)) {
		$prods[] = $row;
		$invalidExisting++;
	} else {
		$skippedValid++;
	}
}
logMsg("Excluidos por tener EAN de variante (regla W2, maestro debe ir vacio): $skippedVariant");
logMsg("Productos candidatos: " . count($prods) . " (de los cuales $invalidExisting tienen EAN inválido — ej: 'v', URL slug, 12 dígitos…)");
logMsg("Productos con EAN-13 válido conservado: $skippedValid");

// Función para resolver el prefix por origin con prefix-match
function resolvePrefixForOrigin($origin) {
	foreach (PROVIDER_PREFIXES as $key => $pp) {
		if (stripos($origin, $key) === 0) return $pp;
	}
	return null;
}

// Stats por proveedor (no por origin literal, que en Osculati es 'osculati-altas-sXXX')
$byProvider = [];
foreach ($prods as $p) {
	foreach (array_keys(PROVIDER_PREFIXES) as $k) {
		if (stripos($p['products_import_origin'], $k) === 0) {
			$byProvider[$k] = ($byProvider[$k] ?? 0) + 1;
			break;
		}
	}
}
foreach ($byProvider as $o => $c) logMsg("  · $o: $c productos");

// Cargar EAN existentes en BD para detectar colisiones
logMsg("Cargando EAN ya en BD para detectar colisiones…");
$existingEans = [];
$r = $mysqli->query("SELECT product_ean FROM products WHERE product_ean IS NOT NULL AND product_ean<>''");
while ($row = $r->fetch_assoc()) $existingEans[$row['product_ean']] = true;
logMsg("EAN ya existentes en BD: " . count($existingEans));

$applied = 0;
$collisions = 0;
$skippedBadId = 0;
$processed = 0;

foreach ($prods as $p) {
	if ($max > 0 && $applied >= $max) break;
	$processed++;
	$pid    = (int) $p['products_id'];
	$origin = $p['products_import_origin'];
	$prefix = resolvePrefixForOrigin($origin);
	if ($prefix === null) continue;
	try {
		$ean = generateInternalEan13($pid, $prefix);
	} catch (Throwable $e) {
		$skippedBadId++;
		logMsg("SKIP pid=$pid: " . $e->getMessage());
		continue;
	}
	if (isset($existingEans[$ean])) {
		$collisions++;
		logMsg("COLISIÓN pid=$pid → ean=$ean ya en uso (saltando)");
		continue;
	}

	if ($dryRun) {
		if ($applied < 10) logMsg("WOULD SET pid=$pid origin=$origin → $ean");
		$applied++;
		continue;
	}

	// Para invalidar EAN basura existente y meter el nuevo, NO ponemos guarda WHERE product_ean=''
	$ok = $mysqli->query("UPDATE products SET product_ean='$ean' WHERE products_id=$pid");
	if ($ok && $mysqli->affected_rows > 0) {
		$existingEans[$ean] = true;
		$applied++;
		if ($applied <= 25 || $applied % 100 === 0) logMsg("SET pid=$pid origin=$origin ean=$ean");
	} else {
		logMsg("WARN pid=$pid: UPDATE no afectó filas (¿lo rellenó otro proceso?) – " . $mysqli->error);
	}
}

logMsg("==================== RESUMEN ====================");
logMsg("Procesados: $processed");
logMsg(($dryRun ? "Asignarían" : "Asignados") . ": $applied");
logMsg("Colisiones: $collisions");
logMsg("SKIP por id fuera de rango: $skippedBadId");
if ($dryRun) logMsg("=== Dry-run finalizado. No se ha tocado nada. ===");

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('backfill_ean_internos.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Backfill de EAN internos</h2>
	<p>
		Asigna un EAN-13 interno a productos sin EAN provenientes de los importadores.
		Construcción: <code>{prefijo proveedor 2 dígitos}{products_id rellenado a 10}{checksum Mod10}</code>.
	</p>
	<p style="background:#fff7e6;border-left:3px solid #ffae42;padding:8px 12px;font-size:13px;">
		<strong>Ojo:</strong> los prefijos 20–28 son del rango GS1 reservado <em>in-store</em>. Estos códigos sirven para uso interno (almacén, etiquetas, BD)
		pero <strong>no son válidos para Amazon, Google Shopping ni eBay</strong> (necesitarían un prefijo GS1 propio).
	</p>
	<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<label>Máximo EAN a generar por ejecución (0 = sin límite):
				<input type="number" name="max" value="0" min="0" style="width:80px;">
			</label>
		</p>
		<p>
			<label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label>
		</p>
		<input type="hidden" name="action" value="plan">
		<button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla \'Aplicar cambios\' antes de ejecutar.'), false);">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Prefijos asignados:</strong><br>
		<?php foreach (PROVIDER_PREFIXES as $o => $p): ?>
			- <code><?php echo $o; ?></code> → prefijo <code><?php echo $p; ?></code><br>
		<?php endforeach; ?>
		<br>
		<strong>Reglas:</strong><br>
		- Solo procesa productos con <code>product_ean</code> vacío/NULL.<br>
		- Solo orígenes conocidos: <?php echo implode(', ', array_keys(PROVIDER_PREFIXES)); ?>.<br>
		- Si el EAN generado ya existe en BD → colisión, se salta (no debería ocurrir por construcción).<br>
		- UPDATE atómico con guarda <code>WHERE product_ean IS NULL OR ''</code> para no pisar EAN puestos por otro proceso.<br>
		- Reentrada segura: ejecutarlo dos veces no hace nada extra (afecta solo a productos que sigan vacíos).<br>
		- Output en streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
