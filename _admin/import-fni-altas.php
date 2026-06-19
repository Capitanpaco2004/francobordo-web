<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
use phpseclib3\Net\SFTP;

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const FNI_CSV          = '/home/francobordo/public_html/import/feed/FNI/Tracciato_master_10.csv';
const FNI_SFTP_HOST    = 'sftpclienti.fni.it';
const FNI_SFTP_PORT    = 2222;
const FNI_SFTP_USER    = 'maxstore';
const FNI_SFTP_PASS    = 'zoopa9Ai';
const FNI_SFTP_REMOTE  = 'Tracciato_master_10.csv';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_ID   = 1700;
const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const G1_GROUP_ID       = 1;
const G1_FLOOR_FACTOR   = 1.10; // piso: G1 ≥ cost × 1.10
const PRODUCT_NAME_MAX  = 80;
const IMG_HTTP_TIMEOUT  = 12;
const ORIGIN_FLAG       = 'fni';
const EAN_INTERNAL_PREFIX = 21; // prefijo GS1 in-store (origen=fni)
const VAT_RATE          = 0.21; // IVA 21% — usado para redondeo PVP a múltiplos de 0.05 con IVA
const VARIANT_OPTION_ID = 3;    // "Modelo" (mismo que Osculati/Trem)
const LCP_VARIANT_RATIO = 0.30; // umbral LCP/min(name) para considerar variantes legítimas
const DEFAULT_BRAND     = 'Generico'; // BRAND vacío → asignar este fabricante (id=29 en BD)

// Marcas para las que NO se prefija el nombre del fabricante al título (UPPERCASE):
// - GENERICO: ruido sin valor
// - FORNITURE NAUTICHE ITALIANE: la propia FNI, redundante
const BRAND_NO_PREFIX = ['GENERICO', 'FORNITURE NAUTICHE ITALIANE'];

// Brands a EXCLUIR del importador (case-insensitive). Productos con estos fabricantes
// no se importan de FNI (ya se gestionan por otra vía o no interesan).
const BRAND_BLACKLIST = [
	'ISTITUTO IDROGRAFICO', 'VIADANA', 'ANAF', 'BARTON MARINE', 'C-MAP', 'WINDEX',
	'KONG', 'CAMPINGAZ', 'HOSES TECHNOLOGY', 'JOBE', 'NAVIONICS', 'NGK', 'FUSION',
	'GARMIN', 'DOUGLAS MARINE', 'LALIZAS', 'SIMRAD', 'LOWRANCE', 'B&G', 'LOFRANS',
	'OCEAN SIGNAL', 'VETUS',
];

// Traducción IT→ES vía LLM (mismo endpoint que Osculati/Trem)
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'Eres un traductor profesional de italiano o inglés a español especializado en productos náuticos, marinos y de pesca. Usa terminología técnica náutica precisa en español de España. Glosario náutico de referencia (IT/EN↔ES): rope/line/cima=cabo, shackle/grillo=grillete, cleat/galloccia=cornamusa, fairlead/passacavo=pasacable, winch/verricello=molinete o winche, thruster=hélice de maniobra, bilge/sentina=sentina, hatch/boccaporto=escotilla, fender/parabordo=defensa, mooring/ormeggio=amarre, anchor/ancora=ancla, chain/catena=cadena, hull/scafo=casco, deck/coperta=cubierta, rudder/timone=timón, through-hull/passascafo=pasacascos, seacock=grifo de fondo, stainless steel/acciaio inox=acero inoxidable, galvanized/zincato=galvanizado, outboard/fuoribordo=fueraborda. Usa siempre el sentido náutico; no lo traduzcas como términos de otros dominios. Texto plano, conserva <br> si los hay como saltos de línea. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$skipVariants    = isset($_POST['skip_variants'])    || isset($_GET['skip_variants']);
$skipDownload    = isset($_POST['skip_download'])    || isset($_GET['skip_download']);
$selectedBrand   = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? 'all'));
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes       = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') $onlyCodes[$c] = true;
	}
}
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

/** Convierte "1,234" → 1.234 (decimal europeo → punto). Devuelve null si no es numérico. */
function fniParseNum($v) {
	$v = trim((string) $v);
	if ($v === '') return null;
	$v = str_replace(['.', ','], ['', '.'], $v);
	return is_numeric($v) ? (float) $v : null;
}

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
	return nl2br(trim(implode("\n", $out)), false);
}

function fniSlugify($text, $maxLen = 50) {
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

/** Si la marca tiene valor y no está en BRAND_NO_PREFIX, devuelve "<Brand normalizado> ", si no, ''. */
function fniBrandPrefix($rawBrand) {
	$brand = trim((string) $rawBrand);
	if ($brand === '') return '';
	if (in_array(strtoupper($brand), BRAND_NO_PREFIX, true)) return '';
	return fniNormalizeManufacturer($brand) . ' ';
}

/** Antepone el prefijo de marca a $name y trunca al máximo de varchar(80). */
function fniApplyBrandPrefix($prefix, $name) {
	if ($name === null || $name === '') return $prefix !== '' ? rtrim($prefix) : '';
	return mb_substr($prefix . $name, 0, PRODUCT_NAME_MAX, 'UTF-8');
}

function fniNormalizeManufacturer($name) {
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

function resolveManufacturer($mysqli, $rawName, &$cache, &$createdLog, $dryRun) {
	$key = strtoupper(trim($rawName));
	if ($key === '') return null;
	if (isset($cache[$key])) return $cache[$key];
	$qkey = $mysqli->real_escape_string($key);
	$r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=\"$qkey\" LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['manufacturers_id']; return $cache[$key]; }
	$display = fniNormalizeManufacturer($rawName);
	if ($dryRun) { $cache[$key] = 0; $createdLog[$key] = $display; return 0; }
	$qd = $mysqli->real_escape_string($display);
	$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES (\"$qd\", NOW())");
	$id = (int) $mysqli->insert_id;
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", \"\")");
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", \"\")");
	$cache[$key] = $id;
	$createdLog[$key] = $display . " (id=$id)";
	return $id;
}

/** Carga set de identificadores ya existentes (lowercase). Misma lógica que Osculati/Trem. */
/** Mapa de duplicados — FILTRADO por origin FNI (fix 2026-05-11).
 *  FNI es multi-marca (Uniteck, Max Power, Furuno, Energizer, etc.) así que el filtro va por
 *  products_import_origin LIKE 'fni%'. SKUs en BD con otros origin no bloquean el alta.
 *  EAN sigue siendo GLOBAL (identificador único GS1). */
function buildExistingMap($mysqli) {
	$existing = [];
	$f = "p.products_import_origin LIKE 'fni%'";
	$r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	// EAN global (GS1)
	$r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	// Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
	require_once dirname(__FILE__) . '/includes/import_blacklist.php';
	$existing += fb_blacklist_keys();
	return $existing;
}

/** Detecta respuestas degeneradas del LLM: bucles de repetición ("R R R R…") o un
 *  mismo carácter/palabra dominando el texto. El modelo NVFP4 lo produce de forma
 *  intermitente; sin este filtro se almacenaba basura como descripción. */
function llmLooksDegenerate($s) {
	$s = trim((string) $s);
	if ($s === '') return true;
	// Un único carácter alfanumérico repetido domina la cadena (p.ej. "R R R …").
	$alnum = preg_replace('/[^\p{L}\p{N}]/u', '', $s);
	if (mb_strlen($alnum, 'UTF-8') >= 20) {
		$chars = preg_split('//u', mb_strtolower($alnum, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
		$freq = array_count_values($chars);
		if (count($chars) && (max($freq) / count($chars)) > 0.6) return true;
	}
	// Una única palabra (token) repetida domina el texto.
	$tokens = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
	if (count($tokens) >= 8) {
		$tf = array_count_values(array_map(fn($t) => mb_strtolower($t, 'UTF-8'), $tokens));
		if ((max($tf) / count($tokens)) > 0.45) return true;
	}
	return false;
}

function llmTranslate($text, $maxRetries = 2, $maxOutChars = 0) {
	if (trim((string) $text) === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => LLM_PROMPT],
			['role' => 'user',   'content' => $text],
		],
		'temperature' => 0.2,
		'repetition_penalty' => 1.15, // frena los bucles degenerados del NVFP4
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
			// Rechaza vacío, degenerado o desproporcionadamente largo (alucinación: un título
			// terso de 35 chars no se traduce a 200+) → reintenta; tras agotar reintentos, ''.
			if (is_string($content) && trim($content) !== '' && !llmLooksDegenerate($content)
				&& ($maxOutChars <= 0 || mb_strlen(trim($content), 'UTF-8') <= $maxOutChars)) {
				return trim($content);
			}
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

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA; ése es el que debe acabar en .X0/.X5. */
function roundToNickel($net) {
	$wi = ((float) $net) * (1 + VAT_RATE);
	$r  = round($wi * 20) / 20;
	return round($r / (1 + VAT_RATE), 4);
}

function calcG1Price($price, $cost) {
	$price = (float) $price;
	$cost = (float) $cost;
	if ($price <= 0) return 0.0;
	$mult = 0.90;
	if ($cost > 0) {
		$margin = ($price - $cost) / $price;
		if      ($margin >= 0.45) $mult = 0.75;
		elseif  ($margin >= 0.40) $mult = 0.80;
		elseif  ($margin >= 0.35) $mult = 0.82;
		elseif  ($margin >= 0.30) $mult = 0.85;
	}
	$tier  = $price * $mult;
	$floor = $cost * G1_FLOOR_FACTOR;
	return round(max($tier, $floor), 4);
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

function longestCommonPrefix(array $strs) {
	if (empty($strs)) return '';
	$strs = array_values($strs);
	$prefix = $strs[0];
	$prefixLen = mb_strlen($prefix, 'UTF-8');
	foreach ($strs as $s) {
		while ($prefixLen > 0 && mb_substr($s, 0, $prefixLen, 'UTF-8') !== $prefix) {
			$prefixLen--;
			$prefix = mb_substr($prefix, 0, $prefixLen, 'UTF-8');
		}
		if ($prefixLen === 0) return '';
	}
	return $prefix;
}

function fniExtractMeasure($title) {
	$title = (string) $title;
	// Diámetro: Ø 8 / Ø8 / diameter 8 / diam. 8 mm / diametro 8
	if (preg_match('/(?:Ø|diameter|diametro|diam\.?)\s*(\d+(?:[.,]\d+)?)\s*(mm|cm)?/iu', $title, $m)) {
		return 'Ø ' . $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? $m[2] : 'mm');
	}
	// Tamaño tornillería: 4,8X32 / 5,5X16 / M6X20
	if (preg_match('/(M?\d+(?:[.,]\d+)?)\s*[Xx]\s*(\d+(?:[.,]\d+)?)\b/u', $title, $m)) {
		return $m[1] . 'x' . $m[2];
	}
	// Rango "X-Y unidad"
	if (preg_match('/(\d+(?:[.,]\d+)?\s*-\s*\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|HP|W|V|A|Ah|AMP|Hz|°)\b/iu', $title, $m)) {
		return trim($m[1]) . ' ' . $m[2];
	}
	// Valor + unidad explícita (Ah/AMP/V para baterías; LT/L para volúmenes; kg/g para pesos)
	if (preg_match('/(\d+(?:[.,]\d+)?)\s*(Ah|AMP|kg|g|mm|cm|mt|m|lt|l|ml|in|inch|"|HP|W|V|A|Hz|°|fl\s*oz|oz)\b/iu', $title, $m)) {
		return $m[1] . ' ' . strtolower($m[2]);
	}
	return '';
}

function findOrCreateOptionValue($mysqli, $name) {
	$nameSafe = $mysqli->real_escape_string($name);
	$q = $mysqli->query("SELECT pov.products_options_values_id
		FROM products_options_values pov
		INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
		WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . "
		  AND pov.language_id = " . LANG_ID_ES . "
		  AND pov.products_options_values_name = '$nameSafe'
		LIMIT 1");
	if ($row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];
	$nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
	$nrow = $nq->fetch_assoc();
	$newId = (int) $nrow['nid'];
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameSafe', '')");
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameSafe', '')");
	$mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
	return $newId;
}

/** Descarga Tracciato_master_10.csv vía SFTP. Devuelve true si éxito. */
function downloadTracciatoFromSftp($localPath) {
	try {
		$sftp = new SFTP(FNI_SFTP_HOST, FNI_SFTP_PORT);
		if (!$sftp->login(FNI_SFTP_USER, FNI_SFTP_PASS)) return ['ok' => false, 'err' => 'login fallido'];
		$tmp = $localPath . '.tmp.' . uniqid();
		if (!$sftp->get(FNI_SFTP_REMOTE, $tmp)) return ['ok' => false, 'err' => 'get fallido'];
		if (!file_exists($tmp) || filesize($tmp) < 1000) {
			@unlink($tmp);
			return ['ok' => false, 'err' => 'fichero descargado vacío/inválido'];
		}
		if (!@rename($tmp, $localPath)) { @unlink($tmp); return ['ok' => false, 'err' => 'rename fallido']; }
		return ['ok' => true, 'size' => filesize($localPath)];
	} catch (Exception $e) {
		return ['ok' => false, 'err' => $e->getMessage()];
	}
}

function loadCsvRows($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	stream_filter_append($f, 'convert.iconv.CP1252/UTF-8');
	$rows = [];
	while (($r = fgetcsv($f, 0, ';', chr(34), '')) !== false) {
		if (count($r) < 30) continue;
		$rows[] = [
			'EAN'        => trim($r[0]  ?? ''),
			'SKU'        => trim($r[1]  ?? ''),
			'PARENT'     => trim($r[2]  ?? ''),
			'NAME_IT'    => trim($r[3]  ?? ''),
			'SUBDESC_IT' => trim($r[4]  ?? ''),
			'NAME_EN'    => trim($r[5]  ?? ''),
			'SUBDESC_EN' => trim($r[6]  ?? ''),
			'BRAND'      => trim($r[7]  ?? ''),
			'WEIGHT'     => trim($r[11] ?? ''),
			'PIC1'       => trim($r[15] ?? ''),
			'PIC2'       => trim($r[16] ?? ''),
			'PIC3'       => trim($r[17] ?? ''),
			'NET_PRICE'  => trim($r[18] ?? ''),
			'PUBLIC'     => trim($r[19] ?? ''),
			'STATUS'     => trim($r[30] ?? ''),
		];
	}
	fclose($f);
	return $rows;
}

/** Lista de marcas presentes en el CSV (post-blacklist, post-status), con conteo,
 *  para alimentar el desplegable del formulario. Vacíos → DEFAULT_BRAND. */
function listBrandsFromCsv($path) {
	$rows = loadCsvRows($path);
	$brands = [];
	foreach ($rows as $r) {
		$st = strtoupper($r['STATUS']);
		if ($st !== '' && in_array($st, ['D', 'X', '0', 'N'], true)) continue;
		$bUp = strtoupper(trim($r['BRAND']));
		if ($bUp !== '' && in_array($bUp, BRAND_BLACKLIST, true)) continue;
		$b = $r['BRAND'] !== '' ? $r['BRAND'] : DEFAULT_BRAND;
		$brands[$b] = ($brands[$b] ?? 0) + 1;
	}
	arsort($brands);
	return $brands;
}

/**
 * Decide si un grupo PARENT con N rows es una familia legítima de variantes.
 * Criterio: LCP del nombre IT ≥ LCP_VARIANT_RATIO del nombre más corto.
 *
 * NOTA: la "misma imagen" NO se usa como señal porque FNI le pone la misma imagen
 * genérica a productos no relacionados (cartas náuticas IIM, p.ej.: parent 0100001
 * con 13 SKUs de zonas geográficas distintas, todos con la misma foto de carta).
 * El LCP discrimina bien: cuerdas/tornillería ≥ 30% (mismo prefijo de modelo + medida),
 * cartas < 15% (nombres totalmente distintos).
 */
function isVariantFamily(array $rows) {
	if (count($rows) < 2) return false;
	$names = array_map(fn($r) => $r['NAME_IT'] !== '' ? $r['NAME_IT'] : $r['NAME_EN'], $rows);
	$lcp = longestCommonPrefix($names);
	$lcpLen = mb_strlen($lcp, 'UTF-8');
	$minLen = min(array_map(fn($s) => mb_strlen($s, 'UTF-8'), $names));
	if ($minLen <= 0) return false;
	$ratio = $lcpLen / $minLen;
	if ($ratio < LCP_VARIANT_RATIO) return false;
	// Si LCP ≥ 50% del nombre más corto → confianza alta, es variante.
	if ($ratio >= 0.50) return true;
	// Zona gris (30-50%): exigimos que el sufijo de CADA SKU contenga al menos un dígito (medida).
	// Esto descarta cartas náuticas (sufijo "STILO A CAPO S.MARIA LEUCA" sin dígitos) y similares,
	// donde el LCP es accidental (preposiciones italianas comunes como "DA ").
	foreach ($names as $name) {
		$suffix = mb_substr($name, $lcpLen, null, 'UTF-8');
		if (!preg_match('/\d/u', $suffix)) return false;
	}
	return true;
}

$isAction = ($action === 'execute' || $action === 'dry_run');

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
	// Liberar la conexión PDO del handler de sesión: si el script tarda > wait_timeout MySQL,
	// el session_write_close() automático al final del request falla con "MySQL server has gone away".
	if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Importador FNI — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('import-fni-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
	. ($skipTranslation ? " (sin traducción LLM IT→ES)" : "")
	. ($skipVariants    ? " (sin agrupar variantes)" : "")
	. ($skipDownload    ? " (sin descargar Tracciato del SFTP)" : "")
	. " | brand=" . ($selectedBrand === 'all' ? 'TODAS' : $selectedBrand)
	. ($max > 0 ? ", max=$max" : ""));

if (!$skipDownload) {
	$mtime = file_exists(FNI_CSV) ? filemtime(FNI_CSV) : 0;
	logMsg("Descargando " . FNI_SFTP_REMOTE . " desde SFTP " . FNI_SFTP_HOST . ":" . FNI_SFTP_PORT . " …" . ($mtime ? " (local actual: " . date('Y-m-d H:i', $mtime) . ", " . round(filesize(FNI_CSV)/1024) . " KB)" : ""));
	$dl = downloadTracciatoFromSftp(FNI_CSV);
	if ($dl['ok']) {
		logMsg("  ✓ descargado: " . round($dl['size']/1024) . " KB (mtime " . date('Y-m-d H:i', filemtime(FNI_CSV)) . ")");
	} else {
		logMsg("  ✗ descarga fallida: " . $dl['err'] . " — uso copia local existente si la hay");
		if (!file_exists(FNI_CSV)) { logMsg("ERROR: no hay copia local y SFTP falló"); goto end_action; }
	}
}

if (!file_exists(FNI_CSV)) { logMsg("ERROR: CSV no encontrado: " . FNI_CSV); goto end_action; }
logMsg("Leyendo CSV…");
$rows = loadCsvRows(FNI_CSV);
logMsg("Filas leídas: " . count($rows));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD");

$candidates = [];
$skippedExisting = $skippedNoSku = $skippedNoName = $skippedBadPrice = $skippedStatus = $skippedBrand = $skippedBrandFilter = 0;
// Si hay codes específicos, derivamos los PARENT a los que pertenecen para incluir toda la familia.
$wantedParents = [];
if (!empty($onlyCodes)) {
	foreach ($rows as $row) {
		if (isset($onlyCodes[$row['SKU']])) {
			if (!empty($row['PARENT'])) $wantedParents[$row['PARENT']] = true;
		}
	}
}
foreach ($rows as $row) {
	$sku = $row['SKU'];
	if ($sku === '') { $skippedNoSku++; continue; }
	$inOnlyCodes = !empty($onlyCodes) && (isset($onlyCodes[$sku]) || (!empty($row['PARENT']) && isset($wantedParents[$row['PARENT']])));
	// Si onlyCodes está activo y este row no es de los pedidos ni hermano, skipear
	if (!empty($onlyCodes) && !$inOnlyCodes) continue;

	if ($row['NAME_IT'] === '' && $row['NAME_EN'] === '') { $skippedNoName++; continue; }
	$st = strtoupper($row['STATUS']);
	// Si el usuario pide explícitamente el code, ignoramos filtro de status
	if (!$inOnlyCodes && $st !== '' && in_array($st, ['D', 'X', '0', 'N'], true)) { $skippedStatus++; continue; }

	// Filtro de marca: skip si está en blacklist; asignar "Generico" si está vacía.
	$brandUp = strtoupper(trim($row['BRAND']));
	// Si el usuario pide explícitamente el code, ignoramos blacklist
	if (!$inOnlyCodes && $brandUp !== '' && in_array($brandUp, BRAND_BLACKLIST, true)) { $skippedBrand++; continue; }
	if ($row['BRAND'] === '') $row['BRAND'] = DEFAULT_BRAND;

	// Filtro por marca seleccionada en el desplegable (solo si no hay onlyCodes)
	if (empty($onlyCodes) && $selectedBrand !== 'all' && strcasecmp($row['BRAND'], $selectedBrand) !== 0) { $skippedBrandFilter++; continue; }

	if (isset($existing[strtolower($sku)])) { $skippedExisting++; continue; }
	if (!empty($row['EAN']) && isset($existing[strtolower($row['EAN'])])) { $skippedExisting++; continue; }
	$net = fniParseNum($row['NET_PRICE']);
	if ($net === null || $net < 0) { $skippedBadPrice++; continue; }
	$pub = fniParseNum($row['PUBLIC']);
	$cost = $net;
	$price = ($pub !== null && $pub > $cost) ? $pub : $cost * 2.0;
	$row['_NET']    = $net;
	$row['_COST']   = $cost;
	$row['_PRICE']  = roundToNickel($price);
	$row['_G1']     = roundToNickel(calcG1Price($row['_PRICE'], $cost));
	$weight = fniParseNum($row['WEIGHT']);
	$row['_WEIGHT'] = ($weight === null || $weight <= 0) ? 1.0 : $weight;
	$candidates[$sku] = $row;
}
logMsg("Candidatos tras pre-filtro: " . count($candidates));
logMsg("  pre-skip: existentes=$skippedExisting | sin SKU=$skippedNoSku | sin nombre=$skippedNoName | precio=$skippedBadPrice | status=$skippedStatus | brand-blacklist=$skippedBrand | brand-filter=$skippedBrandFilter");

// Agrupación por PARENT
$families = [];
$standalone = [];
if ($skipVariants) {
	$standalone = $candidates;
	logMsg("Agrupación por PARENT desactivada — todos los candidatos como sueltos.");
} else {
	$byParent = [];
	foreach ($candidates as $sku => $row) {
		$p = $row['PARENT'];
		if ($p === '') { $standalone[$sku] = $row; continue; }
		$byParent[$p][$sku] = $row;
	}

	$famVariant = $famSplit = 0;
	foreach ($byParent as $p => $items) {
		if (count($items) === 1) {
			$sku = array_key_first($items);
			$standalone[$sku] = $items[$sku];
			continue;
		}
		if (isVariantFamily($items)) {
			$families[$p] = $items;
			$famVariant++;
		} else {
			foreach ($items as $sku => $r) $standalone[$sku] = $r;
			$famSplit++;
		}
	}
	logMsg("PARENTs con ≥2 SKUs: variantes=$famVariant | divididos en sueltos (no comparten imagen ni LCP)=$famSplit");
}
// Filtro final por codes específicos: solo familias/sueltos que contienen algún code pedido
if (!empty($onlyCodes)) {
	$famF = [];
	foreach ($families as $p => $items) {
		if (!empty(array_intersect_key($items, $onlyCodes))) $famF[$p] = $items;
	}
	$stdF = [];
	foreach ($standalone as $sku => $r) {
		if (isset($onlyCodes[$sku])) $stdF[$sku] = $r;
	}
	$families = $famF;
	$standalone = $stdF;
	logMsg("Filtro por codes específicos: quedan " . count($families) . " familias + " . count($standalone) . " sueltos");
}
logMsg("Tras consolidar: " . count($families) . " familias multi-variante + " . count($standalone) . " sueltos");

$mfgCache = [];
$mfgCreated = [];
$nInserted = $nFamiliesIns = $nStandaloneIns = 0;
$nWithVar = $nWithImg = $imgFail = $imgEmpty = $translateFail = $errors = 0;

// ---- 1) Familias con variantes ----
foreach ($families as $parent => $items) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

	uasort($items, fn($a, $b) => $a['_NET'] <=> $b['_NET']);
	$cheapestSku = array_key_first($items);
	$cheap = $items[$cheapestSku];
	$brand = $cheap['BRAND'];
	$mfgId = resolveManufacturer($mysqli, $brand, $mfgCache, $mfgCreated, $dryRun);
	$brandPrefix = fniBrandPrefix($brand);

	// Nombres "raw" (sin prefijo de marca) para alimentar el LLM y el cálculo de medida
	$rawNameIt = $cheap['NAME_IT'];
	$rawNameEn = $cheap['NAME_EN'] !== '' ? $cheap['NAME_EN'] : $cheap['NAME_IT'];
	$titleIt = fniApplyBrandPrefix($brandPrefix, $rawNameIt);
	$titleEn = fniApplyBrandPrefix($brandPrefix, $rawNameEn);
	$descItFull = trim($cheap['NAME_IT'] . (trim($cheap['SUBDESC_IT']) !== '' ? "<br><br>" . $cheap['SUBDESC_IT'] : ''));
	$descIt = cleanHtmlAggressive($descItFull);
	$descEnFull = trim(($cheap['NAME_EN'] !== '' ? $cheap['NAME_EN'] : $cheap['NAME_IT']) . (trim($cheap['SUBDESC_EN']) !== '' ? "<br><br>" . $cheap['SUBDESC_EN'] : ''));
	$descEn = cleanHtmlAggressive($descEnFull);
	// Cuerpo de la descripción para ES: el italiano si lo trae; si no, el inglés (FNI deja
	// vacío SUBDESC_IT en p.ej. la serie Quick radiomandos y solo rellena el inglés).
	$bodySrcEs = cleanHtmlAggressive(trim($cheap['SUBDESC_IT']) !== '' ? $cheap['SUBDESC_IT'] : $cheap['SUBDESC_EN']);

	$nameEs = $titleIt;
	$descEs = $descIt; // fallback (texto IT) si no se traduce (dry-run / skip)
	if (!$skipTranslation && !$dryRun) {
		// Traducimos el "raw" sin prefijo (la marca no se traduce); luego prefijamos al resultado.
		$tn = llmTranslate($rawNameIt, 2, max(60, mb_strlen($rawNameIt, 'UTF-8') * 3));
		if ($tn !== '') $nameEs = fniApplyBrandPrefix($brandPrefix, $tn); else $translateFail++;
		// Encabezado de la descripción = título ya traducido (reutilizamos $tn para no volver a
		// traducir el título terso, que el LLM degenera); el cuerpo se traduce por separado.
		$titleForDesc = $tn !== '' ? $tn : $rawNameIt;
		$bodyEs = '';
		if ($bodySrcEs !== '') {
			$tb = llmTranslate($bodySrcEs);
			$bodyEs = $tb !== '' ? $tb : $bodySrcEs; // a falta de traducción, conserva el texto fuente
			if ($tb === '') $translateFail++;
		}
		$descEs = $bodyEs !== '' ? ($titleForDesc . "<br><br>" . $bodyEs) : $titleForDesc;
	}

	if ($dryRun) {
		$nInserted++; $nFamiliesIns++;
		if ($nFamiliesIns <= 12) {
			$lcp = longestCommonPrefix(array_map(fn($r) => $r['NAME_IT'] !== '' ? $r['NAME_IT'] : $r['NAME_EN'], $items));
			logMsg(sprintf("  WOULD INSERT FAMILIA parent=%s (%d variantes) cheap=%s %.2f€ [lcp=%d] name=\"%s\"",
				$parent, count($items), $cheapestSku, $cheap['_COST'],
				mb_strlen($lcp, 'UTF-8'), mb_substr($titleIt, 0, 50, 'UTF-8')));
		}
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($cheapestSku);
		$qean   = $mysqli->real_escape_string($cheap['EAN']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($cheap['_PRICE'], 4, '.', '') . ", " . number_format($cheap['_COST'], 4, '.', '') . ", NOW(), {$cheap['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($titleEn);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		$imgUrls = array_filter([$cheap['PIC1'], $cheap['PIC2'], $cheap['PIC3']]);
		if ($imgUrls) {
			$slug = fniSlugify($nameEs ?: $titleEn);
			$finalName = $slug . '-' . $pid . '.jpg';
			$finalAbs = IMG_ABS_DIR . $finalName;
			$ok = false;
			foreach ($imgUrls as $url) { if (downloadImage($url, $finalAbs)) { $ok = true; break; } }
			if ($ok) {
				$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($finalName) . "\" WHERE products_id=$pid");
				$nWithImg++;
			} else { $imgFail++; }
		} else {
			$imgEmpty++;
		}

		// Etiquetas de variante: probamos señales en orden y elegimos la primera donde
		// TODOS los SKUs tienen valor no-vacío y único. Si ninguna funciona → SKU como fallback.
		$itTitlesAll = array_map(fn($it) => $it['NAME_IT'] !== '' ? $it['NAME_IT'] : $it['NAME_EN'], $items);
		$enTitlesAll = array_map(fn($it) => $it['NAME_EN'] !== '' ? $it['NAME_EN'] : $it['NAME_IT'], $items);
		$commonIt = rtrim(longestCommonPrefix($itTitlesAll), " -–·,");
		$commonEn = rtrim(longestCommonPrefix($enTitlesAll), " -–·,");
		$lcpStrip = function ($name, $common) {
			if ($common === '' || mb_strpos($name, $common) !== 0) return '';
			$rest = trim(mb_substr($name, mb_strlen($common, 'UTF-8'), null, 'UTF-8'));
			$rest = ltrim($rest, " -–·,;");
			return (mb_strlen($rest, 'UTF-8') <= 64) ? $rest : '';
		};
		$candidates = [];
		foreach ($items as $sku => $it) {
			$candidates[$sku] = [
				'lcp_it'     => $lcpStrip($itTitlesAll[$sku], $commonIt),
				'lcp_en'     => $lcpStrip($enTitlesAll[$sku], $commonEn),
				'measure_it' => fniExtractMeasure($itTitlesAll[$sku]),
				'measure_en' => fniExtractMeasure($enTitlesAll[$sku]),
			];
		}
		$labels = [];
		foreach (['lcp_it', 'lcp_en', 'measure_it', 'measure_en'] as $signal) {
			$vals = array_map(fn($c) => $c[$signal], $candidates);
			$nonEmpty = array_filter($vals, fn($v) => $v !== '');
			if (count($nonEmpty) === count($vals) && count(array_unique($nonEmpty)) === count($vals)) {
				$labels = $vals;
				break;
			}
		}
		// Fallback: SKU como label en cualquier SKU que no tenga label asignado
		foreach ($items as $sku => $it) {
			if (empty($labels[$sku])) $labels[$sku] = $sku;
			$labels[$sku] = mb_substr($labels[$sku], 0, 64, 'UTF-8');
		}

		$variantsCreated = 0;
		// Track de OVs ya consumidos por este producto (guardia anti-colisión de labels)
		$ovsUsados = [];
		foreach ($items as $sku => $it) {
			$delta   = round($it['_PRICE'] - $cheap['_PRICE'], 4);
			$prefix  = $delta < 0 ? '-' : '+';
			$valueId = findOrCreateOptionValue($mysqli, $labels[$sku]);
			if (isset($ovsUsados[$valueId])) {
				$labelDis = mb_substr($labels[$sku] . ' (' . $sku . ')', 0, 64, 'UTF-8');
				$valueId = findOrCreateOptionValue($mysqli, $labelDis);
			}
			$ovsUsados[$valueId] = true;
			$qref    = $mysqli->real_escape_string($sku);
			$qvean   = $mysqli->real_escape_string($it['EAN']);
			// Peso variante = DELTA sobre el padre (shopping_cart.php suma con prefix +/-)
			$weightDelta  = round($it['_WEIGHT'] - $cheap['_WEIGHT'], 3);
			$weightPrefix = $weightDelta < 0 ? '-' : '+';
			$weightAbs    = abs($weightDelta);
			if (!$mysqli->query("INSERT INTO products_attributes SET
				products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
				options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
				reference='$qref', reference_prov='$qref', products_attributes_ean='$qvean',
				options_values_weight=" . number_format($weightAbs, 3, '.', '') . ", weight_prefix='$weightPrefix'"))
				throw new Exception("attr: " . $mysqli->error);
			$paId = (int) $mysqli->insert_id;
			$variantsCreated++;

			$g1Delta = round($it['_G1'] - $cheap['_G1'], 4);
			$g1Prefix = $g1Delta < 0 ? '-' : '+';
			if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
				throw new Exception("attr_groups: " . $mysqli->error);
		}
		if ($variantsCreated > 0) $nWithVar++;

		$mysqli->commit();

		if (!isValidEan13($cheap['EAN'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++; $nFamiliesIns++;
		logMsg(sprintf("OK FAMILIA parent=%s pid=%d cheap=%s [%d variantes] price=%.2f cost=%.2f g1=%.2f", $parent, $pid, $cheapestSku, $variantsCreated, $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1']));
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR familia parent=$parent: " . $e->getMessage());
	}
}

// ---- 2) Sueltos ----
foreach ($standalone as $sku => $row) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

	$mfgId = resolveManufacturer($mysqli, $row['BRAND'], $mfgCache, $mfgCreated, $dryRun);
	$brandPrefix = fniBrandPrefix($row['BRAND']);
	$rawNameIt = $row['NAME_IT'];
	$rawNameEn = $row['NAME_EN'] !== '' ? $row['NAME_EN'] : $row['NAME_IT'];
	$nameIt    = fniApplyBrandPrefix($brandPrefix, $rawNameIt);
	$nameEnRaw = fniApplyBrandPrefix($brandPrefix, $rawNameEn);
	$descItFull = trim($row['NAME_IT'] . (trim($row['SUBDESC_IT']) !== '' ? "<br><br>" . $row['SUBDESC_IT'] : ''));
	$descIt = cleanHtmlAggressive($descItFull);
	$descEnFull = trim(($row['NAME_EN'] !== '' ? $row['NAME_EN'] : $row['NAME_IT']) . (trim($row['SUBDESC_EN']) !== '' ? "<br><br>" . $row['SUBDESC_EN'] : ''));
	$descEn = cleanHtmlAggressive($descEnFull);
	// Cuerpo de la descripción para ES: el italiano si lo trae; si no, el inglés (FNI deja
	// vacío SUBDESC_IT en p.ej. la serie Quick radiomandos y solo rellena el inglés).
	$bodySrcEs = cleanHtmlAggressive(trim($row['SUBDESC_IT']) !== '' ? $row['SUBDESC_IT'] : $row['SUBDESC_EN']);

	$nameEs = $nameIt;
	$descEs = $descIt; // fallback (texto IT) si no se traduce (dry-run / skip)
	if (!$skipTranslation && !$dryRun) {
		$tn = llmTranslate($rawNameIt, 2, max(60, mb_strlen($rawNameIt, 'UTF-8') * 3));
		if ($tn !== '') $nameEs = fniApplyBrandPrefix($brandPrefix, $tn); else $translateFail++;
		// Encabezado de la descripción = título ya traducido (reutilizamos $tn para no volver a
		// traducir el título terso, que el LLM degenera); el cuerpo se traduce por separado.
		$titleForDesc = $tn !== '' ? $tn : $rawNameIt;
		$bodyEs = '';
		if ($bodySrcEs !== '') {
			$tb = llmTranslate($bodySrcEs);
			$bodyEs = $tb !== '' ? $tb : $bodySrcEs; // a falta de traducción, conserva el texto fuente
			if ($tb === '') $translateFail++;
		}
		$descEs = $bodyEs !== '' ? ($titleForDesc . "<br><br>" . $bodyEs) : $titleForDesc;
	}

	$imgUrls = array_filter([$row['PIC1'], $row['PIC2'], $row['PIC3']]);

	if ($dryRun) {
		$nInserted++; $nStandaloneIns++;
		if ($nStandaloneIns <= 8) logMsg("  WOULD INSERT SUELTO sku=$sku name='" . mb_substr($nameIt, 0, 60, 'UTF-8') . "'");
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($sku);
		$qean   = $mysqli->real_escape_string($row['EAN']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($row['_PRICE'], 4, '.', '') . ", " . number_format($row['_COST'], 4, '.', '') . ", NOW(), {$row['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($nameEnRaw);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($row['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		if ($imgUrls) {
			$slug = fniSlugify($nameEs ?: $nameEnRaw);
			$finalName = $slug . '-' . $pid . '.jpg';
			$finalAbs = IMG_ABS_DIR . $finalName;
			$ok = false;
			foreach ($imgUrls as $url) { if (downloadImage($url, $finalAbs)) { $ok = true; break; } }
			if ($ok) {
				$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($finalName) . "\" WHERE products_id=$pid");
				$nWithImg++;
			} else { $imgFail++; }
		} else {
			$imgEmpty++;
		}

		$mysqli->commit();

		if (!isValidEan13($row['EAN'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++; $nStandaloneIns++;
		logMsg("OK SUELTO pid=$pid sku=$sku price={$row['_PRICE']} cost={$row['_COST']} g1={$row['_G1']}");
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR suelto sku=$sku: " . $e->getMessage());
	}
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nInserted (familias=$nFamiliesIns sueltos=$nStandaloneIns)");
logMsg("Con imagen: $nWithImg | sin URL: $imgEmpty | fallos descarga: $imgFail");
logMsg("Familias con variantes creadas: $nWithVar");
logMsg("Traducciones IT→ES fallidas: $translateFail");
logMsg("Errores INSERT: $errors");
if (!empty($mfgCreated)) {
	logMsg("Manufacturers " . ($dryRun ? "que se crearían" : "creados") . " (" . count($mfgCreated) . "):");
	foreach ($mfgCreated as $k => $v) logMsg("  · $v");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('import-fni-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Importador FNI (altas) — con detección de variantes por PARENT</h2>
	<p>
		Lee <code><?php echo FNI_CSV; ?></code>, agrupa SKUs por columna PARENT (col 2 del CSV) y aplica heurística para diferenciar
		<em>familias de variantes legítimas</em> (mismo producto en distintas medidas/colores) de productos que casualmente comparten parent (cartas náuticas, etc.).
		Inserta en categoría <strong><?php echo NEW_CATEGORY_ID; ?> (FNI Nuevos)</strong> los SKUs que no existan en BD.
	</p>
	<p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
		<strong>Heurística de variantes</strong>: un grupo PARENT con 2+ SKUs se trata como variantes solo si
		el <strong>prefijo común de los nombres IT ≥ <?php echo (int)(LCP_VARIANT_RATIO*100); ?>%</strong>
		del nombre más corto. Si los nombres son totalmente distintos (cartas náuticas, p.ej.) → productos sueltos.
		<br><em>La imagen del feed no se usa: FNI pone la misma imagen genérica a SKUs no relacionados.</em>
	</p>
	<form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Marca a importar</strong>:
			<select name="brand" style="min-width:300px;">
				<option value="all">— Todas las marcas —</option>
				<?php
				$brandList = file_exists(FNI_CSV) ? listBrandsFromCsv(FNI_CSV) : [];
				foreach ($brandList as $b => $cnt) {
					$sel = ($selectedBrand === $b) ? ' selected' : '';
					echo '<option value="' . htmlspecialchars($b) . '"' . $sel . '>'
						. htmlspecialchars($b) . ' (' . $cnt . ')</option>';
				}
				?>
			</select>
			<?php if (!file_exists(FNI_CSV)): ?>
				<em style="color:#a00;">— CSV no descargado todavía; ejecuta una vez para popular el desplegable.</em>
			<?php endif; ?>
		</p>
		<p>
			<strong>SKUs específicos</strong> (opcional, ignora marca y "Inserts máximos"; si el SKU es variante, importa toda la familia):<br>
			<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios SKUs separados por coma, espacio o salto de línea."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción LLM IT→ES (mucho más rápido)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_variants" value="1"> Modo legacy: ignorar PARENT (cada SKU como suelto)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_download" value="1"> No descargar Tracciato del SFTP (usar copia local)</label>
		</p>
		<p>
			Inserts máximos por ejecución (0 = sin límite):
			<input type="number" name="max" value="50" min="0" style="width:80px;">
		</p>
		<button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Reglas aplicadas:</strong><br>
		- <code>products_cost</code> = NET PRICE (col 18, sin IVA).<br>
		- <code>products_price</code> = PUBLIC PRICE si &gt; cost; si no, cost × 2 (margen 50%).<br>
		- G1 con tiers según margen real, piso <code>cost × <?php echo G1_FLOOR_FACTOR; ?></code>.<br>
		- Variantes en <code>products_attributes</code> con <code>options_id=<?php echo VARIANT_OPTION_ID; ?></code> (Modelo). Padre = SKU más barato.<br>
		- Etiqueta variante: medida del nombre (Ø/diámetro, AxB, mm/cm/kg…) → resto del título tras prefijo común → SKU como último recurso.<br>
		- Idiomas: ES traducido del IT vía LLM; EN del CSV (col 5/6) cuando viene, sino fallback al IT.<br>
		- Imagen: descarga primera URL no vacía de PIC1/PIC2/PIC3.<br>
		- EAN: si el del feed no pasa checksum → genera EAN interno con prefijo 21 (FNI).<br>
		- Stock NO se toca. STATUS 'D'/'X'/'0'/'N' → skip.<br>
		- <strong>Brands excluidos</strong> (skip): <?php echo implode(', ', BRAND_BLACKLIST); ?>.<br>
		- BRAND vacío → fabricante "<strong><?php echo DEFAULT_BRAND; ?></strong>".<br>
		- <strong>Prefijo de marca</strong> en el título: se antepone el nombre del fabricante (ej. "<em>Marine Town</em> CAVALLOTTO DELUXE…"), excepto para <?php echo implode(', ', BRAND_NO_PREFIX); ?>.<br>
		- Output en streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
