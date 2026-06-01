<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const AZIMUT_CSV       = '/home/francobordo/public_html/import/feed/Azimut/datos_nautica_francobordo.csv';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const IMG_REL_PATH      = 'productos/';
const NEW_CATEGORY_ID   = 1701;
const TAX_CLASS_IVA21   = 1;
const VAT_RATE          = 0.21; // IVA 21% — usado para redondeo PVP a múltiplos de 0.05 con IVA
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const PRODUCT_NAME_MAX  = 80;
const IMG_HTTP_TIMEOUT  = 12;
const EAN_INTERNAL_PREFIX = 22; // prefijo GS1 in-store para EANs generados (origen=azimut)

// Traducción ES→EN vía LLM (mismo endpoint que Osculati)
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'You are a professional translator from Spanish to English specialised in nautical, marine and fishing products. Use precise English nautical terminology. Nautical reference glossary (ES↔EN): cabo=rope/line, grillete=shackle, cornamusa=cleat, pasacable=fairlead/chock, winche=winch, molinete=windlass, hélice de maniobra=thruster, sentina=bilge, escotilla=hatch, defensa=fender, amarre=mooring, ancla=anchor, cadena=chain, casco=hull, cubierta=deck, timón=rudder, pasacascos=through-hull, grifo de fondo=seacock, acero inoxidable=stainless steel, galvanizado=galvanized, fueraborda=outboard. Always use the nautical meaning; do not translate as terms from other domains. Plain text only — preserve <br> tags as line breaks if present. Reply ONLY with the translation, no comments or explanations.';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes       = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') {
			$onlyCodes[$c] = true;
			// Azimut usa ProductCode con espacios ("0 000 448") — aceptamos también versión sin espacios
			$noSpace = str_replace(' ', '', $c);
			if ($noSpace !== $c) $onlyCodes[$noSpace] = true;
		}
	}
}
// Si hay codes específicos, ignoramos $max (el usuario ya es explícito)
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }
$brandFilter = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? ''));

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA; ése es el que debe acabar en .X0/.X5. */
function roundToNickel($net) {
	$wi = ((float) $net) * (1 + VAT_RATE);
	$r  = round($wi * 20) / 20;
	return round($r / (1 + VAT_RATE), 4);
}

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

/**
 * Limpieza HTML agresiva → texto plano con <br> en saltos de línea/párrafo.
 * Quita el page-builder de Magento (data-pb-style, data-content-type, <style>, etc.)
 */
function cleanHtmlAggressive($html) {
	if ($html === null || $html === '') return '';
	$html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
	$html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
	$html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
	$html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section)\s*>#i', "\n", $html);
	$html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$lines = preg_split("/\r\n|\r|\n/", $text);
	$out = [];
	$emptyStreak = 0;
	foreach ($lines as $l) {
		$l = trim(preg_replace('/[ \t\xC2\xA0]+/', ' ', $l));
		if ($l === '') {
			if ($emptyStreak < 1 && !empty($out)) { $out[] = ''; }
			$emptyStreak++;
			continue;
		}
		$out[] = $l;
		$emptyStreak = 0;
	}
	$plain = trim(implode("\n", $out));
	return nl2br($plain, false);
}

function azSlugify($text, $maxLen = 50) {
	$t = trim((string) $text);
	if (function_exists('iconv')) {
		$conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
		if ($conv !== false && $conv !== '') $t = $conv;
	}
	$t = strtolower($t);
	$t = preg_replace('/[^a-z0-9]+/', '-', $t);
	$t = trim($t, '-');
	if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
	$t = trim($t, '-');
	return $t === '' ? 'producto' : $t;
}

function azNormalizeManufacturer($name) {
	$name = trim(preg_replace('/\s+/', ' ', (string) $name));
	if ($name === '') return '';
	$words = explode(' ', $name);
	$out = [];
	foreach ($words as $w) {
		if ($w === '') continue;
		$alpha = preg_replace('/[^A-Za-z]/', '', $w);
		if (strlen($alpha) > 0 && strtoupper($alpha) === $alpha && strlen($alpha) <= 4) {
			$out[] = $w;
		} else {
			$out[] = mb_convert_case(mb_strtolower($w, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		}
	}
	return implode(' ', $out);
}

/**
 * Escaneo ligero del CSV: devuelve la lista de fabricantes distintos (normalizados,
 * ordenados) para poblar el desplegable del formulario. Solo lee la columna Fabricante.
 */
function azDistinctManufacturers($path) {
	$f = @fopen($path, 'r');
	if (!$f) return [];
	stream_filter_append($f, 'convert.iconv.ISO-8859-1/UTF-8');
	$header = fgetcsv($f, 0, ',', '"', '');
	if (!$header) { fclose($f); return []; }
	$idx = array_flip($header);
	$col = $idx['Fabricante'] ?? null;
	if ($col === null) { fclose($f); return []; }
	$seen = [];
	while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
		$norm = azNormalizeManufacturer($r[$col] ?? '');
		if ($norm !== '') $seen[$norm] = true;
	}
	fclose($f);
	$list = array_keys($seen);
	natcasesort($list);
	return array_values($list);
}

function resolveManufacturer($mysqli, $rawName, &$cache, &$createdLog, $dryRun) {
	$key = strtoupper(trim($rawName));
	if ($key === '') return null;
	if (isset($cache[$key])) return $cache[$key];
	$qkey = $mysqli->real_escape_string($key);
	$r = $mysqli->query("SELECT manufacturers_id, manufacturers_name FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=\"$qkey\" LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) {
		$cache[$key] = (int) $row['manufacturers_id'];
		return $cache[$key];
	}
	$display = azNormalizeManufacturer($rawName);
	if ($dryRun) {
		$cache[$key] = 0;
		$createdLog[$key] = $display;
		return 0;
	}
	$qd = $mysqli->real_escape_string($display);
	$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES (\"$qd\", NOW())");
	$id = (int) $mysqli->insert_id;
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", \"\")");
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", \"\")");
	$cache[$key] = $id;
	$createdLog[$key] = $display . " (id=$id)";
	return $id;
}

/** Busca duplicado de un producto Azimut (fix 2026-05-11).
 *  SKU sólo cuenta como duplicado si pertenece a manufacturer Fischer Panda (505) o tiene
 *  origin azimut%. Un mismo SKU en otra marca distinta NO bloquea el alta.
 *  EAN sigue siendo GLOBAL (identificador único GS1). */
function findExistingProductId($mysqli, $productCode, $ean) {
	// Lista negra de reimportación: si el código/ref o el EAN está vetado, trátalo como duplicado (saltar el alta).
	require_once dirname(__FILE__) . '/includes/import_blacklist.php';
	if (fb_blacklist_has($productCode) || fb_blacklist_has($ean)) return -1;
	$pcRaw = trim($productCode);
	$pcNoSpace = str_replace(' ', '', $pcRaw);
	$variants = array_unique(array_filter([$pcRaw, $pcNoSpace]));
	$esc = array_map(fn($v) => '"' . $mysqli->real_escape_string($v) . '"', $variants);
	$in = implode(',', $esc);
	$mfgFilter = "(manufacturers_id = 505 OR products_import_origin LIKE 'azimut%')";
	$sql = "SELECT products_id FROM products WHERE (products_model IN ($in) OR reference_prov IN ($in)) AND $mfgFilter LIMIT 1";
	$r = $mysqli->query($sql);
	if ($r && $row = $r->fetch_assoc()) return (int) $row['products_id'];
	if (!empty($ean)) {
		$qean = $mysqli->real_escape_string(trim($ean));
		$r = $mysqli->query("SELECT products_id FROM products WHERE product_ean=\"$qean\" LIMIT 1");
		if ($r && $row = $r->fetch_assoc()) return (int) $row['products_id'];
	}
	$r = $mysqli->query("SELECT pa.products_id FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE (pa.reference IN ($in) OR pa.reference_prov IN ($in)) AND (p.manufacturers_id = 505 OR p.products_import_origin LIKE 'azimut%') LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) return (int) $row['products_id'];
	return 0;
}

function llmTranslate($text, $maxRetries = 2) {
	if (trim((string) $text) === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => LLM_PROMPT],
			['role' => 'user',   'content' => $text],
		],
		'temperature' => 0.2,
		'max_tokens' => 1500,
		'chat_template_kwargs' => ['enable_thinking' => false],
	], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_CONNECTTIMEOUT => 10,
		]);
		$resp = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		unset($ch);
		if ($resp !== false && $code === 200) {
			$j = json_decode($resp, true);
			$content = $j['choices'][0]['message']['content'] ?? null;
			if (is_string($content) && trim($content) !== '') return trim($content);
		}
		usleep(500000);
	}
	return '';
}

function downloadImage($url, $destAbs) {
	if (empty($url)) return false;
	$ch = curl_init($url);
	$fp = fopen($destAbs, 'wb');
	if (!$fp) return false;
	curl_setopt_array($ch, [
		CURLOPT_FILE => $fp,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FrancobordoImporter/1.0)',
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	$ok = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	unset($ch);
	fclose($fp);
	$ok = $ok && $code === 200 && filesize($destAbs) > 0;
	if (!$ok) @unlink($destAbs);
	return $ok;
}

function ean13Checksum($payload12) {
	if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
	$sum = 0;
	for ($i = 0; $i < 12; $i++) {
		$d = (int) $payload12[$i];
		$sum += ($i % 2 === 0) ? $d : $d * 3;
	}
	return (10 - ($sum % 10)) % 10;
}

function isValidEan13($ean) {
	$ean = trim((string) $ean);
	if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
	return ean13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}

function generateInternalEan13($productId, $providerPrefix) {
	$pp = (int) $providerPrefix;
	if ($pp < 20 || $pp > 28) return '';
	if ($productId <= 0 || $productId > 9999999999) return '';
	$payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
	$check = ean13Checksum($payload);
	return $check < 0 ? '' : ($payload . $check);
}

function loadCsvRows($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	stream_filter_append($f, 'convert.iconv.ISO-8859-1/UTF-8');
	$header = fgetcsv($f, 0, ',', '"', '');
	if (!$header) { fclose($f); return []; }
	$idx = array_flip($header);
	$rows = [];
	while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
		if (count($r) < count($header)) continue;
		$rows[] = [
			'StockLevel'       => $r[$idx['StockLevel']] ?? '',
			'Category'         => $r[$idx['Category']] ?? '',
			'ProductCode'      => trim($r[$idx['ProductCode']] ?? ''),
			'Ean'              => trim($r[$idx['Ean']] ?? ''),
			'Fabricante'       => trim($r[$idx['Fabricante']] ?? ''),
			'ProductName'      => trim($r[$idx['ProductName']] ?? ''),
			'Price'            => trim($r[$idx['Price']] ?? ''),
			'Weight'           => trim($r[$idx['Weight']] ?? ''),
			'OfferPrice'       => trim($r[$idx['OfferPrice']] ?? ''),
			'ImageUrl'         => trim($r[$idx['ImageUrl']] ?? ''),
			'ShortDescription' => $r[$idx['ShortDescription']] ?? '',
			'Description'      => $r[$idx['Description']] ?? '',
		];
	}
	fclose($f);
	return $rows;
}

$isAction = ($action === 'execute' || $action === 'dry_run');

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
	<h2>Importador Azimut — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('import-azimut-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE") . ($skipTranslation ? " (sin traducción LLM)" : "") . ($brandFilter !== '' ? " | filtro marca='$brandFilter'" : "") . ($max > 0 ? ", max=$max" : ""));

if (!file_exists(AZIMUT_CSV)) { logMsg("ERROR: CSV no encontrado: " . AZIMUT_CSV); goto end_action; }
logMsg("Leyendo CSV…");
$rows = loadCsvRows(AZIMUT_CSV);
logMsg("Filas leídas: " . count($rows));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }

$mfgCache = [];
$mfgCreated = [];
$inserted = 0;
$skippedExisting = 0;
$skippedNoCode = 0;
$skippedNoName = 0;
$skippedBadPrice = 0;
$skippedNoImg = 0;
$skippedBrand = 0;
$imgOk = 0;
$imgFail = 0;
$imgEmpty = 0;
$translateFail = 0;
$processed = 0;
$errors = 0;

if (!empty($onlyCodes)) {
	logMsg("Filtro por codes específicos activo (" . count($onlyCodes) . " entradas). 'max' ignorado.");
}
foreach ($rows as $row) {
	if ($max > 0 && $inserted >= $max) break;
	$processed++;
	$pc = $row['ProductCode'];
	$name = $row['ProductName'];
	if ($pc === '') { $skippedNoCode++; continue; }
	if ($name === '') { $skippedNoName++; continue; }
	if ($brandFilter !== '' && azNormalizeManufacturer($row['Fabricante']) !== $brandFilter) { $skippedBrand++; continue; }
	// Filtro por codes específicos (acepta versión con y sin espacios)
	if (!empty($onlyCodes)) {
		if (!isset($onlyCodes[$pc]) && !isset($onlyCodes[str_replace(' ', '', $pc)])) continue;
	}

	$existingId = findExistingProductId($mysqli, $pc, $row['Ean']);
	if ($existingId) {
		$skippedExisting++;
		if ($skippedExisting <= 25) logMsg("SKIP existente: pc=$pc → products_id=$existingId");
		continue;
	}

	$priceRaw = $row['Price'];
	$price = is_numeric($priceRaw) ? (float) $priceRaw : null;
	if ($price === null || $price < 0) { $skippedBadPrice++; logMsg("SKIP precio inválido: pc=$pc price='$priceRaw'"); continue; }
	$price = roundToNickel($price);

	// SKIP si no hay imagen disponible en el feed (decisión usuario: no dar de alta productos sin imagen)
	if (empty($row['ImageUrl'])) {
		$skippedNoImg++;
		if ($skippedNoImg <= 25) logMsg("SKIP sin imagen: pc=$pc (URL vacía en CSV)");
		continue;
	}

	$weight = is_numeric($row['Weight']) && (float) $row['Weight'] > 0 ? (float) $row['Weight'] : 1.0;
	$mfgId = resolveManufacturer($mysqli, $row['Fabricante'], $mfgCache, $mfgCreated, $dryRun);

	// Componer nombre: "{Fabricante} {nombre original}". Si el nombre ya empieza por el fabricante, no duplicar.
	$mfgDisplay = azNormalizeManufacturer($row['Fabricante']);
	$nameComposed = $name;
	if ($mfgDisplay !== '' && stripos($name, $mfgDisplay) !== 0) {
		$nameComposed = $mfgDisplay . ' ' . $name;
	}
	$nameEs = mb_substr($nameComposed, 0, PRODUCT_NAME_MAX, 'UTF-8');
	$shortEs = cleanHtmlAggressive($row['ShortDescription']);
	$descEs = cleanHtmlAggressive($row['Description']);
	$fullEs = $descEs;
	if ($shortEs !== '' && stripos($descEs, strip_tags($shortEs)) === false) {
		$fullEs = $shortEs . ($descEs !== '' ? "<br><br>" . $descEs : '');
	} elseif ($descEs === '') {
		$fullEs = $shortEs;
	}

	$nameEn = $nameEs;
	$fullEn = $fullEs;
	if (!$skipTranslation && !$dryRun) {
		$tn = llmTranslate($nameEs);
		if ($tn !== '') $nameEn = mb_substr($tn, 0, PRODUCT_NAME_MAX, 'UTF-8'); else $translateFail++;
		if ($fullEs !== '') {
			$td = llmTranslate($fullEs);
			if ($td !== '') $fullEn = $td; else $translateFail++;
		}
	}

	$imgFilename = '';
	if ($dryRun) {
		// En dry-run no podemos validar la descarga real → asumimos que la URL servirá
		$imgOk++;
	} else {
		$slug = azSlugify($name);
		$tmpAbs = IMG_ABS_DIR . $slug . '-tmp-' . uniqid() . '.jpg';
		if (downloadImage($row['ImageUrl'], $tmpAbs)) {
			$imgOk++;
			$imgFilename = $tmpAbs;
		} else {
			// Descarga fallida → SKIP el producto entero (no insertamos sin imagen)
			$imgFail++;
			$skippedNoImg++;
			logMsg("SKIP sin imagen: pc=$pc descarga falló url=" . $row['ImageUrl']);
			continue;
		}
	}

	if ($dryRun) {
		$inserted++;
		if ($inserted <= 10) logMsg("WOULD INSERT pc=$pc | mfg='" . $row['Fabricante'] . "' | price=$price | wt=$weight | name='" . mb_substr($nameEs, 0, 60, 'UTF-8') . "'");
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($pc);
		$qref   = $mysqli->real_escape_string($pc);
		$qean   = $mysqli->real_escape_string($row['Ean']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", $price, 0, NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qref\", \"azimut\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($fullEs);
		$qNameEn = $mysqli->real_escape_string($nameEn);
		$qDescEn = $mysqli->real_escape_string($fullEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)"))
			throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)"))
			throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")"))
			throw new Exception("p2c: " . $mysqli->error);

		// G1 (Profesionales) = products_price × 0.9. Redondeado a .05 con IVA.
		$g1Price = roundToNickel($price * 0.9);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (1, $pid, " . number_format($g1Price, 4, '.', '') . ", 1, 1)"))
			throw new Exception("g1: " . $mysqli->error);

		if ($imgFilename !== '' && file_exists($imgFilename)) {
			$slug = azSlugify($name);
			$finalName = $slug . '-' . $pid . '.jpg';
			$finalAbs = IMG_ABS_DIR . $finalName;
			if (@rename($imgFilename, $finalAbs)) {
				$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($finalName) . "\" WHERE products_id=$pid");
			} else {
				@unlink($imgFilename);
			}
		}

		$mysqli->commit();

		// Generar EAN interno si el feed no traía EAN válido (vacío, basura, slug…)
		if (!isValidEan13($row['Ean'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid");
			}
		}

		$inserted++;
		logMsg("INSERT pid=$pid pc=$pc name='" . mb_substr($nameEs, 0, 60, 'UTF-8') . "'");
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR pc=$pc → " . $e->getMessage());
	}
}

logMsg("==================== RESUMEN ====================");
logMsg("Procesadas: $processed");
logMsg("Insertadas: $inserted");
logMsg("SKIP existentes: $skippedExisting");
logMsg("SKIP sin código: $skippedNoCode");
logMsg("SKIP sin nombre: $skippedNoName");
logMsg("SKIP precio inválido: $skippedBadPrice");
logMsg("SKIP sin imagen: $skippedNoImg");
if ($brandFilter !== '') logMsg("SKIP por filtro de marca ('$brandFilter'): $skippedBrand");
logMsg("Imágenes OK: $imgOk | fail: $imgFail | sin URL: $imgEmpty");
logMsg("Traducciones fallidas: $translateFail");
logMsg("Errores INSERT: $errors");
if (!empty($mfgCreated)) {
	logMsg("Manufacturers " . ($dryRun ? "que se crearían" : "creados") . " (" . count($mfgCreated) . "):");
	foreach ($mfgCreated as $k => $v) logMsg("  · $v");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('import-azimut-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Importador Azimut (altas)</h2>
	<p>
		Lee <code><?php echo AZIMUT_CSV; ?></code> e inserta en categoría <strong><?php echo NEW_CATEGORY_ID; ?> (Azimut Nuevos)</strong> los productos cuyo <code>ProductCode</code> no existe en BD.
		Productos creados como <em>pendiente de revisión</em> (status=2, check_stock=0).
	</p>
	<form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Codes específicos</strong> (opcional, ignora el límite "Inserts máximos"):<br>
			<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios ProductCode (con o sin espacios) separados por coma, espacio o salto de línea."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción LLM (mucho más rápido)</label>
		</p>
		<p>
			<label>Filtrar por fabricante:
			<select name="brand" style="padding:4px;max-width:100%;">
				<option value="">Todos</option>
<?php foreach (azDistinctManufacturers(AZIMUT_CSV) as $__b): ?>
				<option value="<?php echo htmlspecialchars($__b); ?>"<?php echo (($_GET['brand'] ?? '') === $__b) ? ' selected' : ''; ?>><?php echo htmlspecialchars($__b); ?></option>
<?php endforeach; ?>
			</select>
			</label>
		</p>
		<p>
			Inserts máximos por ejecución (0 = sin límite):
			<input type="number" name="max" value="50" min="0" style="width:80px;">
		</p>
		<button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Notas:</strong><br>
		- Productos creados con <code>products_status=2</code> en categoría <?php echo NEW_CATEGORY_ID; ?>.<br>
		- Limpieza HTML agresiva (texto plano + <code>&lt;br&gt;</code>) sobre la <code>Description</code> del page-builder Magento.<br>
		- Manufacturer: match case-insensitive contra <code>manufacturers</code>; si no existe, se crea con casing normalizado.<br>
		- Imagen: descarga HTTPS desde <code>ImageUrl</code> a <code>images/productos/</code>.<br>
		- Traducción ES→EN del nombre y descripción vía LLM <code><?php echo LLM_URL; ?></code>.<br>
		- <strong>Output en streaming en tiempo real</strong>: ya no hay 504 timeout en batches largos.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
