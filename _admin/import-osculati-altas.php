<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const OSC_USER          = 'C54293';
const OSC_PASS          = '0XxBkWSb';
const OSC_FTP_BASE      = 'ftp://fw.osculati.it/';
const OSC_IMG_FOLDER    = 'IMG/800/';
define('OSC_TMP_DIR',     dirname(__FILE__) . '/import-osculati/');
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
define('IMG_ATTR_DIR',    dirname(dirname(__FILE__)) . '/images/atributos/');
const NEW_CATEGORY_ID   = 1699;
const OSCULATI_MFG_ID   = 259;
const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const VARIANT_OPTION_ID = 3;
const IMG_REL_PATH      = 'productos/';
const EAN_INTERNAL_PREFIX = 20; // prefijo GS1 in-store (origen=osculati)
const VAT_RATE          = 0.21; // IVA 21% — usado para redondeo PVP a múltiplos de 0.05 con IVA

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA; ése es el que debe acabar en .X0/.X5. */
function roundToNickel($net) {
    $wi = ((float) $net) * (1 + VAT_RATE);
    $r  = round($wi * 20) / 20;
    return round($r / (1 + VAT_RATE), 4);
}

// Traducción IT→ES vía LLM local (expuesto vía port-forward)
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'Eres un traductor profesional de italiano a español especializado en productos náuticos y de pesca. Traduce con terminología técnica náutica precisa en español de España. Glosario náutico de referencia (IT/EN↔ES): rope/line/cima=cabo, shackle/grillo=grillete, cleat/galloccia=cornamusa, fairlead/passacavo=pasacable, winch/verricello=molinete o winche, thruster=hélice de maniobra, bilge/sentina=sentina, hatch/boccaporto=escotilla, fender/parabordo=defensa, mooring/ormeggio=amarre, anchor/ancora=ancla, chain/catena=cadena, hull/scafo=casco, deck/coperta=cubierta, rudder/timone=timón, through-hull/passascafo=pasacascos, seacock=grifo de fondo, stainless steel/acciaio inox=acero inoxidable, galvanized/zincato=galvanizado, outboard/fuoribordo=fueraborda. Usa siempre el sentido náutico; no lo traduzcas como términos de otros dominios. Mantén etiquetas HTML si las hay. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']);
$skipFormat = isset($_POST['skip_format']) || isset($_GET['skip_format']);
$onlyCodesRaw = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes    = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') {
			$onlyCodes[$c] = true;
			// Aceptar también la forma sin sufijo (#PZ/#CF)
			$noSuffix = preg_replace('/#.*$/', '', $c);
			if ($noSuffix !== $c) $onlyCodes[$noSuffix] = true;
		}
	}
}
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }

$log = '';

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
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


function osculatiDownload($remotePath, $localPath) {
	$fp = fopen($localPath, 'wb');
	$ch = curl_init(OSC_FTP_BASE . $remotePath);
	curl_setopt($ch, CURLOPT_USERPWD, OSC_USER . ':' . OSC_PASS);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 600);
	$ok = curl_exec($ch);
	fclose($fp);
	$ok = $ok && filesize($localPath) > 0;
	if (!$ok) @unlink($localPath);
	return $ok;
}

function readUtf16File($path) {
	$raw = file_get_contents($path);
	if ($raw === false) return [];
	$utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
	$utf8 = ltrim($utf8, "\xEF\xBB\xBF\xFF\xFE");
	$lines = preg_split("/\r\n|\r|\n/", $utf8);
	$rows = [];
	foreach ($lines as $line) {
		if ($line === '') continue;
		$rows[] = explode("\t", $line);
	}
	return $rows;
}

function parseItemPrice4Web($rows) {
	// Cols (1-indexed in docs): 1 OrderCode, 2 BaseCode, 3 BaseQty, 4 BaseUM, 5 Dispo, 6 VAT, 7 EAN,
	// 9 Brand, 10 DescIT, 11 DescEN, 12 DescFR, 13 DescDE, 14 SuggestedStreetPrice*100,
	// 15 ListPrice*100, 16 WeightG, 17 VolumeCC, 18 MinQty
	$byBase = [];
	foreach ($rows as $r) {
		if (count($r) < 14) continue;
		$baseCode = trim($r[1]);
		if ($baseCode === '') continue;
		$byBase[$baseCode][] = [
			'order_code'      => trim($r[0]),
			'base_code'       => $baseCode,
			'base_qty'        => (int) $r[2],
			'base_um'         => trim($r[3]),
			'dispo'           => (int) $r[4],
			'vat'             => (int) $r[5],
			'ean'             => trim($r[6]),
			'brand'           => trim($r[8] ?? ''),
			'desc_it'         => trim($r[9] ?? ''),
			'desc_en'         => trim($r[10] ?? ''),
			'desc_fr'         => trim($r[11] ?? ''),
			'desc_de'         => trim($r[12] ?? ''),
			'street_price'    => (int) ($r[13] ?? 0),
			'list_price'      => (int) ($r[14] ?? 0),
			'weight_g'        => (int) ($r[15] ?? 0),
			'volume_cc'       => (int) ($r[16] ?? 0),
			'min_qty'         => (int) ($r[17] ?? 1),
		];
	}
	return $byBase;
}

function parseCode2Ser($rows) {
	// Cols: 1 BaseCode, 2 IDSerie, 3 SuggestedImg, 4 img, 5 UrlSerie, 6 UrlIframe
	$byBase = [];
	foreach ($rows as $r) {
		if (count($r) < 4) continue;
		$baseCode = trim($r[0]);
		if ($baseCode === '') continue;
		$byBase[$baseCode] = [
			'id_serie'      => (int) $r[1],
			'suggested_img' => trim($r[2]),
			'img'           => trim($r[3]),
		];
	}
	return $byBase;
}

function parseSerExport($rows) {
	// Cols: 1 IDSerie, 2 ID_Scat, 3 Nome_Scat, 4 ID_Cat, 5 Nome_Cat, 6 TitoloSerie, 7 DescrizioneSerie, 8 FotoSerie
	$bySerie = [];
	foreach ($rows as $r) {
		if (count($r) < 6) continue;
		$id = (int) $r[0];
		if ($id <= 0) continue;
		$bySerie[$id] = [
			'id_scat'    => (int) $r[1],
			'nome_scat'  => trim($r[2]),
			'id_cat'     => (int) $r[3],
			'nome_cat'   => trim($r[4]),
			'titulo'     => trim($r[5]),
			'desc_html'  => trim($r[6] ?? ''),
			'foto_serie' => trim($r[7] ?? ''),
		];
	}
	return $bySerie;
}

/** Mapa de duplicados — FILTRADO por Osculati (fix 2026-05-11).
 *  SKUs sólo cuentan si pertenecen a manufacturer Osculati (259) o tienen origin osculati%.
 *  Un mismo SKU en otra marca NO bloquea el alta. */
function getExistingBaseCodes() {
	$existing = [];
	$f = "(p.manufacturers_id = " . OSCULATI_MFG_ID . " OR p.products_import_origin LIKE 'osculati%')";
	$q = tep_db_query("SELECT LOWER(p.products_model) AS m FROM products p WHERE p.products_model IS NOT NULL AND p.products_model != '' AND $f");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;

	$q = tep_db_query("SELECT LOWER(pa.reference) AS r FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference IS NOT NULL AND pa.reference != '' AND $f");
	while ($row = tep_db_fetch_array($q)) $existing[$row['r']] = true;

	$q = tep_db_query("SELECT LOWER(pa.reference_prov) AS r FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov IS NOT NULL AND pa.reference_prov != '' AND $f");
	while ($row = tep_db_fetch_array($q)) $existing[$row['r']] = true;

	$q = tep_db_query("SELECT LOWER(p.reference_prov) AS r FROM products p WHERE p.reference_prov IS NOT NULL AND p.reference_prov != '' AND $f");
	while ($row = tep_db_fetch_array($q)) $existing[$row['r']] = true;

	// EAN global (identificador único GS1) — sin scope por marca, comparte namespace con SKUs/refs
	$q = tep_db_query("SELECT LOWER(product_ean) AS m FROM products WHERE product_ean IS NOT NULL AND product_ean != ''");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;
	$q = tep_db_query("SELECT LOWER(products_attributes_ean) AS m FROM products_attributes WHERE products_attributes_ean IS NOT NULL AND products_attributes_ean != ''");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;

	// GLOBAL OSC BC scan (sin scope): products legacy bajo OTRAS marcas que usan la convención
	// Osculati NN.NNN.NN como products_model o reference_prov. Captura el caso del pid 362548
	// donde MZ Electronic tenía model=02.352.12 y el importador no lo veía por el scope filter.
	// Es seguro globalmente porque el formato OSC BC es distintivo: NN.NNN.NN[A-Z0-9]{0,5}.
	$oscBcRegex = "'^[0-9]{2}\\.[0-9]{3}\\.[0-9]{2}'";
	$q = tep_db_query("SELECT LOWER(products_model) AS m FROM products WHERE products_model IS NOT NULL AND products_model<>'' AND products_model RLIKE $oscBcRegex");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;
	$q = tep_db_query("SELECT LOWER(reference_prov) AS m FROM products WHERE reference_prov IS NOT NULL AND reference_prov<>'' AND reference_prov RLIKE $oscBcRegex");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;
	$q = tep_db_query("SELECT LOWER(reference) AS m FROM products_attributes WHERE reference IS NOT NULL AND reference<>'' AND reference RLIKE $oscBcRegex");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;
	$q = tep_db_query("SELECT LOWER(reference_prov) AS m FROM products_attributes WHERE reference_prov IS NOT NULL AND reference_prov<>'' AND reference_prov RLIKE $oscBcRegex");
	while ($row = tep_db_fetch_array($q)) $existing[$row['m']] = true;

	// Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
	require_once dirname(__FILE__) . '/includes/import_blacklist.php';
	$existing += fb_blacklist_keys();
	return $existing;
}

function calcUnitPriceNoVat($streetPrice100, $baseQty) {
	$baseQty = max(1, $baseQty);
	$priceWithVat = ($streetPrice100 / 100) / $baseQty;
	return $priceWithVat / 1.21;
}

function findOrCreateOptionValue($nameEs, $nameEn = null) {
	if ($nameEn === null || $nameEn === '') $nameEn = $nameEs;
	$nameEsSafe = tep_db_input($nameEs);
	$nameEnSafe = tep_db_input($nameEn);

	$q = tep_db_query("SELECT pov.products_options_values_id
		FROM products_options_values pov
		INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
		WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . "
		  AND pov.language_id = " . LANG_ID_ES . "
		  AND pov.products_options_values_name = '" . $nameEsSafe . "'
		LIMIT 1");
	if ($row = tep_db_fetch_array($q)) {
		return (int) $row['products_options_values_id'];
	}

	$nextIdQ = tep_db_query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
	$nextRow = tep_db_fetch_array($nextIdQ);
	$newId = (int) $nextRow['nid'];

	tep_db_query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameEsSafe', '')");
	tep_db_query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
	tep_db_query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");

	return $newId;
}

function downloadImage($imgName, $targetPath) {
	if ($imgName === '') return false;
	$remotePath = OSC_IMG_FOLDER . rawurlencode($imgName);
	$ch = curl_init(OSC_FTP_BASE . $remotePath);
	$fp = fopen($targetPath, 'wb');
	curl_setopt($ch, CURLOPT_USERPWD, OSC_USER . ':' . OSC_PASS);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$ok = curl_exec($ch);
	fclose($fp);
	if (!$ok || filesize($targetPath) < 100) {
		@unlink($targetPath);
		return false;
	}
	return true;
}

/**
 * Construye lista de candidatos para descarga de imagen a partir de la entrada Code2Ser.
 * Code2Ser a veces trae 'img' con espacios y 'suggested_img' con guiones bajos. El FTP
 * puede tener solo una variante (e.g. 06.448.12 → '06.448.18_macro_New_13.jpg' con _).
 * Devuelve array de filenames únicos a probar en orden.
 */
function osculatiImageCandidates($codeEntry) {
	$candidates = [];
	$add = function($name) use (&$candidates) {
		$name = trim((string) $name);
		if ($name === '') return;
		if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $name)) $name .= '.jpg';
		if (!in_array($name, $candidates, true)) $candidates[] = $name;
	};
	$img = $codeEntry['img']           ?? '';
	$sug = $codeEntry['suggested_img'] ?? '';
	$add($img);                                  // "06.448.18_macro New 13"
	$add($sug);                                  // "06.448.18_macro_New_13"
	$add(str_replace(' ', '_', $img));           // "06.448.18_macro_New_13" (img con espacios → _)
	$add(str_replace('_', ' ', $sug));           // "06.448.18 macro New 13" (suggested con _ → espacios)
	return $candidates;
}

/**
 * Intenta descargar probando cada candidato. Devuelve el filename remoto que funcionó
 * o '' si ninguno descargó. El targetPath queda con la imagen del primer candidato OK.
 */
function downloadImageWithFallbacks(array $candidates, $targetPath) {
	foreach ($candidates as $cand) {
		if (downloadImage($cand, $targetPath)) return $cand;
	}
	return '';
}

function translateIT($text) {
	return translateITto($text, 'es');
}

const LLM_FORMAT_PROMPT = "Eres un experto en maquetar fichas de producto náutico (Osculati). Recibes una descripción YA EN ESPAÑOL y la conviertes en HTML limpio y legible, SIN inventar ni añadir información.\n\nREGLAS OBLIGATORIAS:\n1. NO inventes, NO añadas datos, NO parafrasees ni amplíes. Usa EXACTAMENTE la información del texto original. Si el texto es corto, la salida es corta.\n2. Si el texto es UNA sola idea o 1-2 frases: devuélvelo como un único <p>, resaltando con <strong> los materiales y datos técnicos clave (ej. \"AISI 316\", \"polímero reforzado con fibra de vidrio\", medidas y cargas). NO crees listas.\n3. Si el texto enumera 3 o más componentes, funciones o características separadas (normalmente frases cortas seguidas): OBLIGATORIO un <p> introductorio breve + <ul><li>...</li></ul>, con <strong> al inicio de cada <li> marcando el concepto clave seguido de \":\". Una frase = un <li>; no repartas una sola frase en varios <li>. Ejemplo de entrada: \"Chaleco inflable automático de 150 N. Tela de nylon resistente. Hebilla de acero inoxidable. Incluye silbato y bandas reflectantes.\" → salida: <p>Chaleco salvavidas inflable automático.</p><ul><li><strong>Flotabilidad:</strong> 150 N.</li><li><strong>Material:</strong> tela de nylon resistente.</li><li><strong>Hebilla:</strong> de acero inoxidable.</li><li><strong>Visibilidad:</strong> incluye silbato y bandas reflectantes.</li></ul>\n4. Conserva el sentido náutico y la terminología técnica. Mantén cifras y unidades.\n5. Etiquetas permitidas: <p>, <ul>, <li>, <strong>. Prohibidas: <h1>, <h2>, <h3>, <h4>, <br>, <div>, <span>, <table>.\n6. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

/**
 * Maqueta una descripción ES (prosa de Osculati) a HTML limpio vía LLM. Devuelve '' si falla.
 */
function llmFormatOsculatiHtml($html) {
	$html = trim((string) $html);
	if ($html === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => LLM_FORMAT_PROMPT],
			['role' => 'user',   'content' => $html],
		],
		'chat_template_kwargs' => ['enable_thinking' => false],
		'max_tokens' => 1500,
		'temperature' => 0.2,
	], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= 2; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 90,
		]);
		$resp = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		unset($ch);
		if ($code === 200 && $resp !== false) {
			$d = json_decode($resp, true);
			$out = trim((string) ($d['choices'][0]['message']['content'] ?? ''));
			$out = preg_replace('/^```[a-z]*\\s*|\\s*```$/i', '', $out);
			if (trim($out) !== '') return trim($out);
		}
		usleep(400000);
	}
	return '';
}

/** Valida la salida del maquetado: debe tener estructura y no perder/inventar texto. */
function osculatiFmtValid($fmt, $orig) {
	if (stripos($fmt, '<p') === false && stripos($fmt, '<li') === false) return false;
	$lf = mb_strlen(strip_tags($fmt),  'UTF-8');
	$lo = mb_strlen(strip_tags($orig), 'UTF-8');
	if ($lo > 0 && $lf < $lo * 0.5) return false;  // perdió >50% del texto
	if ($lo > 0 && $lf > $lo * 2.2) return false;  // inventó >120% de texto extra
	return true;
}

/**
 * Maqueta $descEs si procede. $status devuelve 'none'|'ok'|'fail' para los contadores.
 * No toca descripciones muy cortas (<25 chars de texto) ni el fallback [TRADUCIR].
 */
function osculatiMaquetar($descEs, $skipFormat, &$status) {
	$status = 'none';
	if ($skipFormat) return $descEs;
	if ($descEs === '' || strpos($descEs, '[TRADUCIR]') === 0) return $descEs;
	if (mb_strlen(strip_tags($descEs), 'UTF-8') < 25) return $descEs;
	$fmt = llmFormatOsculatiHtml($descEs);
	if ($fmt !== '' && osculatiFmtValid($fmt, $descEs)) { $status = 'ok'; return $fmt; }
	$status = 'fail';
	return $descEs;
}

/* ============================================================
 * Tabla de especificaciones Osculati (Code2SerXml) — 2026-05-29
 * Parseo determinista de <attributi> (specs EXACTAS, nunca tocadas por LLM)
 * + traducción ES solo de etiquetas/valores textuales (números/códigos pasan).
 * ============================================================ */

/** Un string que es solo números, unidades o medidas: no se traduce. */
function oscIsNumericish($s) {
	$s = trim((string) $s);
	if ($s === '') return true;
	return preg_match('/^[\d.,\/×x°\s\-]+$/u', $s) === 1;
}

/** Limpia el caption: recorta prefijo meta-grupo (tras doble espacio). */
function oscCleanCap($c) {
	$c = trim((string) $c);
	if (preg_match('/\s{2,}/u', $c)) { $p = preg_split('/\s{2,}/u', $c); $c = trim(end($p)); }
	return $c;
}

/** Limpia el attribVal: decodifica HTML, quita swatch COLORE_*, quita tags. */
function oscCleanVal($v) {
	$v = html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$v = preg_replace('/^\s*COLORE_\S+\s+/u', '', $v);
	$v = strip_tags($v);
	return trim(preg_replace('/\s{2,}/u', ' ', $v));
}

/** Parsea un xmlItem → [ ['cap'=>, 'val'=>, 'pos'=>], ... ] ordenado por posiz. */
function oscParseAttribs($xml) {
	$out = [];
	if (preg_match_all('#<attributo><caption>(.*?)</caption><attribVal>(.*?)</attribVal><UM>(.*?)</UM><MetaType>(.*?)</MetaType><posiz>(.*?)</posiz></attributo>#s', (string) $xml, $mm, PREG_SET_ORDER)) {
		foreach ($mm as $a) {
			$cap = oscCleanCap($a[1]); $val = oscCleanVal($a[2]);
			if ($cap === '' && $val === '') continue;
			$out[] = ['cap' => $cap, 'val' => $val, 'pos' => (int) $a[5]];
		}
	}
	usort($out, fn($x, $y) => $x['pos'] <=> $y['pos']);
	return $out;
}

/** Extrae el primer código de imagen del bloque <images> de un xmlItem de Code2SerXml. */
function oscImageCodeFromXml($xml) {
	if (preg_match('#<img[^>]*>(.*?)</img>#', (string) $xml, $mm)) return trim($mm[1]);
	return '';
}

/** Traduce un lote de términos EN→ES vía LLM (JSON in/out). Devuelve mapa original→es. */
function oscLlmTranslateSpecTerms(array $terms) {
	if (!$terms) return [];
	$sys = 'Eres traductor técnico náutico EN→ES (España). Recibes un array JSON de términos cortos de especificaciones de producto náutico (etiquetas de columna y valores). Devuelve SOLO un objeto JSON {"original":"traducción"} con la traducción al español de CADA término del array. Usa terminología náutica. MANTÉN intactos números, códigos (ej. 14.200.00), dimensiones (192x65) y unidades (mm, V, W, kg). Ejemplos: "Light colour"->"Color de la luz", "Body"->"Cuerpo", "white"->"blanco", "Black"->"negro", "Bulb included"->"Bombilla incluida", "outside mm"->"medidas exteriores mm", "left"->"izquierda", "right"->"derecha", "Breaking load kg"->"Carga de rotura kg", "Material"->"Material", "Thread"->"Rosca", "Length mm"->"Longitud mm". Responde SOLO el JSON, sin comentarios ni ```.';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $sys],
			['role' => 'user',   'content' => json_encode(array_values($terms), JSON_UNESCAPED_UNICODE)],
		],
		'chat_template_kwargs' => ['enable_thinking' => false],
		'max_tokens' => 2000, 'temperature' => 0.1,
	], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= 2; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>90]);
		$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
		if ($code === 200 && $resp !== false) {
			$d = json_decode($resp, true);
			$txt = trim((string) ($d['choices'][0]['message']['content'] ?? ''));
			$txt = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $txt);
			$map = json_decode($txt, true);
			if (is_array($map)) return $map;
		}
		usleep(400000);
	}
	return [];
}

/** Traduce términos con cache de ejecución (amortiza colores/captions repetidos). */
function oscTranslateTerms(array $terms) {
	static $cache = [];
	$need = [];
	foreach ($terms as $t) {
		if ($t === '' || oscIsNumericish($t)) continue;
		if (!array_key_exists($t, $cache)) $need[$t] = true;
	}
	if ($need) {
		$map = oscLlmTranslateSpecTerms(array_keys($need));
		foreach (array_keys($need) as $t) $cache[$t] = $map[$t] ?? $t; // fallback al original
	}
	return $cache;
}

/** Renderiza la tabla. $multi=true → matriz variantes×captions; false → 2 columnas. */
function oscRenderSpecTable(array $rows, array $cols, $multi, $translate, array $tr) {
	$T = function($s) use ($translate, $tr) {
		if (!$translate || oscIsNumericish($s)) return $s;
		return $tr[$s] ?? $s;
	};
	$FONT  = 'font-family: tahoma, arial, helvetica, sans-serif; font-size: 10pt;';
	$open  = '<table class="osc-spec-table" style="border-collapse: collapse; border: 1px solid rgb(206, 212, 217);" border="1" cellspacing="3" cellpadding="3"><tbody>';
	$hCell = fn($txt) => '<td style="background-color: #008cc6; text-align: center; padding: 2px;"><span style="' . $FONT . ' color: #ffffff;">' . htmlspecialchars($txt) . '</span></td>';
	$dCell = fn($txt) => '<td style="text-align: center; padding: 2px;"><span style="' . $FONT . '">' . htmlspecialchars($txt) . '</span></td>';
	$zebra = ' style="background-color: #e2f2f9;"';
	if ($multi) {
		$codeHdr = $translate ? 'Referencia' : 'Reference';
		$h = $open . '<tr>' . $hCell($codeHdr);
		foreach ($cols as $cap) $h .= $hCell($T($cap));
		$h .= '</tr>';
		$i = 0;
		foreach ($rows as $code => $byCap) {
			$i++;
			$h .= '<tr' . ($i % 2 === 0 ? $zebra : '') . '>' . $dCell($code);
			foreach ($cols as $cap) $h .= $dCell($T($byCap[$cap] ?? ''));
			$h .= '</tr>';
		}
		return $h . '</tbody></table>';
	}
	// 2 columnas (suelto): característica | valor del único code
	$byCap = reset($rows) ?: [];
	$hc = $translate ? 'Característica' : 'Feature';
	$hv = $translate ? 'Valor' : 'Value';
	$h = $open . '<tr>' . $hCell($hc) . $hCell($hv) . '</tr>';
	$i = 0;
	foreach ($cols as $cap) {
		$v = $byCap[$cap] ?? '';
		if ($v === '') continue;
		$i++;
		$h .= '<tr' . ($i % 2 === 0 ? $zebra : '') . '>' . $dCell($T($cap)) . $dCell($T($v)) . '</tr>';
	}
	return $h . '</tbody></table>';
}

/**
 * Construye el bloque de especificaciones [esHtml, enHtml] para una lista de OrderCodes.
 * $xtMap: orderCode(lower) => xmlItem crudo. Devuelve ['',''] si ningún code trae specs.
 */
function oscSpecBlock(array $orderCodes, array $xtMap) {
	$rows = []; $cols = []; $anyAttr = false;
	foreach ($orderCodes as $code) {
		$disp = preg_replace('/#.*$/', '', trim((string) $code));
		$key = strtolower($disp);
		$attrs = isset($xtMap[$key]) ? oscParseAttribs($xtMap[$key]) : [];
		$byCap = [];
		foreach ($attrs as $a) {
			if ($a['cap'] === '') continue;
			$anyAttr = true;
			if (!in_array($a['cap'], $cols, true)) $cols[] = $a['cap'];
			$byCap[$a['cap']] = $a['val'];
		}
		$rows[$disp] = $byCap;
	}
	if (!$anyAttr || !$cols) return ['', ''];

	$terms = [];
	foreach ($cols as $c) $terms[$c] = true;
	foreach ($rows as $byCap) foreach ($byCap as $v) if ($v !== '') $terms[$v] = true;
	$tr = oscTranslateTerms(array_keys($terms));

	$multi = count($orderCodes) > 1;
	$es = '<p><strong>Especificaciones</strong></p>' . oscRenderSpecTable($rows, $cols, $multi, true, $tr);
	$en = '<p><strong>Specifications</strong></p>' . oscRenderSpecTable($rows, $cols, $multi, false, $tr);
	return [$es, $en];
}


/**
 * Traduce IT → ES o IT → EN. Devuelve '' si la llamada al LLM falla.
 */
function translateITto($text, $targetLang = 'es') {
	$text = trim((string) $text);
	if ($text === '') return '';

	if ($targetLang === 'en') {
		$sysPrompt  = 'You are a professional Italian-to-English translator specialized in marine and fishing products. Use precise nautical technical English terminology. Preserve HTML tags if any. Reply ONLY with the translation, no comments or explanations.';
		$userPrefix = 'Translate to English: ';
	} else {
		$sysPrompt  = LLM_PROMPT;
		$userPrefix = 'Traduce al español: ';
	}

	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $sysPrompt],
			['role' => 'user',   'content' => $userPrefix . $text],
		],
		'chat_template_kwargs' => ['enable_thinking' => false],
		'max_tokens' => 2000,
		'temperature' => 0.3,
	], JSON_UNESCAPED_UNICODE);

	$ch = curl_init(LLM_URL);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$resp = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if ($httpCode !== 200 || $resp === false) return '';

	$data = json_decode($resp, true);
	$out = $data['choices'][0]['message']['content'] ?? '';
	return trim($out);
}

/**
 * Devuelve true si el string es solo una medida o cifra ('4 mm', '12,5 kg', '500 W - 12 V', '8/16 mm')
 * sin texto descriptivo en italiano. En ese caso NO hace falta traducir.
 */
function isJustMeasure($s) {
	$s = trim((string) $s);
	if ($s === '') return true;
	// Permitir: dígitos, separadores, espacios, slash, ×, x, *, paréntesis, guion, signos +/-, comas, puntos
	// y unidades comunes náuticas. Si contiene alguna letra fuera de las unidades → no es solo medida.
	return (bool) preg_match('/^[\d\s.,\/\\\\\-+×x*()]*(\s*(mm|cm|m|km|kg|g|mg|°|kn|w|kw|v|hp|cv|hz|khz|mhz|ghz|ah|mah|l|ml|m³|m\^3|m2|m\^2|n|nm|ø|rpm)\b)?[\d\s.,\/\\\\\-+×x*()]*$/iu', $s);
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

/**
 * Extrae una medida del título IT del item (ej. "3,2 kg", "685 mm", "12 V").
 * Útil como label de variante cuando el algoritmo de prefijo común no produce nada útil.
 */
function extractMeasureFromTitle($title) {
	$title = (string) $title;
	// Rango "X-Y unidad"
	if (preg_match('/(\d+(?:[.,]\d+)?\s*-\s*\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|HP|W|V|A|Hz|°)\b/iu', $title, $m)) {
		return trim($m[1]) . ' ' . $m[2];
	}
	// Valor único + unidad
	if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|in|inch|"|HP|W|V|A|Hz|°|fl\s*oz|oz)\b/iu', $title, $m)) {
		return $m[1] . ' ' . $m[2];
	}
	return '';
}

function slugify($s) {
	$s = mb_strtolower($s, 'UTF-8');
	$s = strtr($s, [
		'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
		'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ç'=>'c',
	]);
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	$s = trim($s, '-');
	return substr($s, 0, 80);
}

$isAction = ($action === 'execute');

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<h2>Importar nuevos productos de Osculati</h2>
<p>Detecta BaseCodes de Osculati que no están en tu BD (ni en <code>products_model</code> ni en <code>products_attributes.reference/reference_prov</code>) y los da de alta en la categoría <strong>Osculati Nuevos</strong> (id <?php echo NEW_CATEGORY_ID; ?>) con <strong>status = 2</strong>.</p>

<?php if (!$isAction): ?>
<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
	<p>
		<strong>BaseCodes específicos</strong> (opcional, ignora "Límite"; si el BaseCode es variante, importa toda la serie):<br>
		<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios BaseCodes (ej. 01.018.04) separados por coma, espacio o salto de línea."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
	</p>
	<p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (solo lista los nuevos, no inserta nada)</label></p>
	<p><label><input type="checkbox" name="skip_format" value="1"> No maquetar descripción (solo traducir, más rápido)</label></p>
	<p>Límite por ejecución (0 = sin límite): <input type="number" name="max" value="50" min="0" style="width:80px;"></p>
	<input type="hidden" name="action" value="execute">
	<button type="submit" class="xbutton small hv9 verde">Lanzar</button>
</form>
<p style="margin-top:20px;color:#888;font-size:12px;">
	<strong>Notas:</strong><br>
	- La descripción se guarda en italiano con prefijo <code>[ORIGEN: Osculati IT, traducir]</code>.<br>
	- Los productos se crean con status=2 en categoría <?php echo NEW_CATEGORY_ID; ?>.<br>
	- Variantes (BaseCodes con varios OrderCodes) se crean como atributos sobre opción <?php echo VARIANT_OPTION_ID; ?> ("Modelo").<br>
	- Imágenes se descargan de <code><?php echo OSC_FTP_BASE . OSC_IMG_FOLDER; ?></code>.<br>
	- <strong>Output en streaming en tiempo real</strong> durante la ejecución (sin 504 timeout).
</p>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
<?php exit; endif; ?>
<p><a href="<?php echo tep_href_link('import-osculati-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

if ($isAction) {
	$tmpDir = OSC_TMP_DIR;
	if (!is_dir($tmpDir)) mkdir($tmpDir, 0775, true);

	logMsg('=== Inicio import-osculati-altas (' . ($dryRun ? 'DRY-RUN' : 'REAL') . ') ===');
	if ($max > 0) logMsg("Límite por ejecución: $max productos");

	$itemFile = $tmpDir . 'ItemPrice4Web.txt';
	$serFile  = $tmpDir . 'ser_export.txt';
	$codeFile = $tmpDir . 'Code2Ser.txt';

	logMsg('Descargando ItemPrice4Web.txt vía FTP...');
	if (!osculatiDownload('ItemPrice4Web.txt', $itemFile)) { logMsg('ERROR descargando ItemPrice4Web.txt'); goto end; }
	logMsg('  → ' . number_format(filesize($itemFile)) . ' bytes');

	logMsg('Descargando ENG/Code2Ser.txt vía FTP...');
	if (!osculatiDownload('ENG/Code2Ser.txt', $codeFile)) { logMsg('AVISO: Code2Ser.txt no descargado (sin imágenes)'); }
	else { logMsg('  → ' . number_format(filesize($codeFile)) . ' bytes'); }

	logMsg('Descargando ENG/ser_export.txt vía FTP...');
	if (!osculatiDownload('ENG/ser_export.txt', $serFile)) { logMsg('AVISO: ser_export.txt no descargado (sin descripción larga)'); }
	else { logMsg('  → ' . number_format(filesize($serFile)) . ' bytes'); }

	logMsg('Parseando ItemPrice4Web...');
	$itemRows = readUtf16File($itemFile);
	$itemsByBase = parseItemPrice4Web($itemRows);
	unset($itemRows);
	logMsg('  → ' . count($itemsByBase) . ' BaseCodes únicos');

	$codeMap = [];
	if (file_exists($codeFile)) {
		$codeRows = readUtf16File($codeFile);
		$codeMap = parseCode2Ser($codeRows);
		unset($codeRows);
		logMsg('  → Code2Ser: ' . count($codeMap) . ' entradas');
	}

	$serMap = [];
	if (file_exists($serFile)) {
		$serRows = readUtf16File($serFile);
		$serMap = parseSerExport($serRows);
		unset($serRows);
		logMsg('  → ser_export: ' . count($serMap) . ' series');
	}

	$xtFile = $tmpDir . 'Code2SerXml.txt';
	logMsg('Descargando ENG/Code2SerXml.txt vía FTP (especificaciones)...');
	$xtMap = [];
	if (!osculatiDownload('ENG/Code2SerXml.txt', $xtFile)) {
		logMsg('AVISO: Code2SerXml.txt no descargado (sin tabla de especificaciones)');
	} else {
		logMsg('  → ' . number_format(filesize($xtFile)) . ' bytes');
		$xtRows = readUtf16File($xtFile);
		foreach ($xtRows as $r) {
			if (count($r) < 5) continue;
			$oc = strtolower(preg_replace('/#.*$/', '', trim($r[0])));
			if ($oc !== '' && strpos($r[4], '<attributo>') !== false) $xtMap[$oc] = $r[4];
		}
		unset($xtRows);
		logMsg('  → Code2SerXml: ' . count($xtMap) . ' ítems con especificaciones');
	}

	logMsg('Cargando BaseCodes existentes en BD...');
	$existing = getExistingBaseCodes();
	logMsg('  → ' . count($existing) . ' códigos ya en BD');

	// Agrupar BaseCodes por IDSerie
	$basesBySerie = [];        // id_serie => [base_code => orders]
	$basesNoSerie = [];        // base_code => orders (sin info de serie)
	foreach ($itemsByBase as $baseCode => $orders) {
		if (isset($codeMap[$baseCode]) && $codeMap[$baseCode]['id_serie'] > 0) {
			$basesBySerie[$codeMap[$baseCode]['id_serie']][$baseCode] = $orders;
		} else {
			$basesNoSerie[$baseCode] = $orders;
		}
	}

	// Detectar series totalmente nuevas (ninguno de sus BaseCodes está en BD).
	// Series con solo 1 BaseCode pasan a "standalone" (sin variantes).
	// Si una serie tiene >1 marca distinta → split por marca (cada marca = sub-serie virtual).
	$newSeries = [];
	$skippedPartial = 0;
	$splitByBrand = 0;
	foreach ($basesBySerie as $serieId => $bases) {
		$anyExists = false;
		foreach (array_keys($bases) as $bc) {
			if (isset($existing[strtolower($bc)])) { $anyExists = true; break; }
		}
		// Check EAN global: si algún OrderCode de la serie tiene EAN ya en BD, saltar la serie entera
		if (!$anyExists) {
			foreach ($bases as $bc => $orders) {
				foreach ($orders as $it) {
					$_ean = strtolower(trim($it['ean'] ?? ''));
					if ($_ean !== '' && isset($existing[$_ean])) { $anyExists = true; break 2; }
				}
			}
		}
		if ($anyExists) { $skippedPartial++; continue; }

		// Agrupar por marca para detectar series mixtas (ej. anclas Fortress + bolsas Osculati en serie 175).
		$byBrand = [];
		foreach ($bases as $bc => $orders) {
			$brandKey = strtoupper(trim($orders[0]['brand'] ?? ''));
			if ($brandKey === '') $brandKey = '_NOBRAND_';
			$byBrand[$brandKey][$bc] = $orders;
		}

		if (count($byBrand) > 1) {
			// Serie mixta: cada marca se trata como su propia (sub)serie.
			$splitByBrand++;
			foreach ($byBrand as $brandKey => $subBases) {
				if (count($subBases) === 1) {
					$bc = array_key_first($subBases);
					$basesNoSerie[$bc] = $subBases[$bc];
				} else {
					// id virtual "<serieId>-<brandKey>" para distinguir; el código usa intval() para resolver el real
					$virtualId = $serieId . '-' . preg_replace('/[^A-Z0-9]/i', '', $brandKey);
					$newSeries[$virtualId] = $subBases;
				}
			}
		} else {
			if (count($bases) === 1) {
				$bc = array_key_first($bases);
				$basesNoSerie[$bc] = $bases[$bc];
			} else {
				$newSeries[$serieId] = $bases;
			}
		}
	}
	if ($splitByBrand > 0) logMsg("Series mixtas (>1 marca) splitteadas: $splitByBrand");

	// Standalone (sin serie o serie con 1 solo BaseCode) — 1 BaseCode = 1 producto sin variantes
	$newStandalone = [];
	$skippedStandaloneEan = 0;
	foreach ($basesNoSerie as $bc => $orders) {
		if (isset($existing[strtolower($bc)])) continue;
		// Check EAN global de cualquier OrderCode del producto suelto
		$_eanHit = false;
		foreach ($orders as $it) {
			$_ean = strtolower(trim($it['ean'] ?? ''));
			if ($_ean !== '' && isset($existing[$_ean])) { $_eanHit = true; break; }
		}
		if ($_eanHit) { $skippedStandaloneEan++; continue; }
		$newStandalone[$bc] = $orders;
	}
	if ($skippedStandaloneEan > 0) logMsg("Sueltos saltados por EAN ya en BD: $skippedStandaloneEan");

	// Filtro por codes específicos: solo series/sueltos que contienen al menos un BaseCode pedido
	if (!empty($onlyCodes)) {
		$seriesF = [];
		foreach ($newSeries as $sid => $bases) {
			// Una serie incluye si alguno de sus BaseCodes (key) está en onlyCodes
			if (!empty(array_intersect_key($bases, $onlyCodes))) {
				$seriesF[$sid] = $bases;
			}
		}
		$standF = [];
		foreach ($newStandalone as $bc => $orders) {
			if (isset($onlyCodes[$bc])) $standF[$bc] = $orders;
		}
		$newSeries = $seriesF;
		$newStandalone = $standF;
		logMsg("Filtro por codes específicos: " . count($newSeries) . " series + " . count($newStandalone) . " sueltos");
	}

	$totalNuevos = count($newSeries) + count($newStandalone);
	logMsg('=== ' . $totalNuevos . ' productos NUEVOS: ' . count($newSeries) . ' series + ' . count($newStandalone) . ' sueltos ===');
	logMsg("Series parciales saltadas (ya tienen algún BaseCode en BD): $skippedPartial");

	if ($dryRun) {
		$cnt = 0;
		foreach ($newSeries as $serieId => $bases) {
			$titleSer = $serMap[(int) $serieId]['titulo'] ?? '(sin título)';
			$variantTitles = array_map(function($o) { return $o[0]['desc_it']; }, $bases);
			logMsg(sprintf('  SERIE %d: "%s" → %d variantes (%s ...)',
				$serieId, $titleSer, count($bases),
				implode(', ', array_slice(array_keys($bases), 0, 5))));
			if (++$cnt >= 15) break;
		}
		$cnt = 0;
		foreach ($newStandalone as $bc => $orders) {
			logMsg(sprintf('  SUELTO: %s | %s', $bc, $orders[0]['desc_it']));
			if (++$cnt >= 5) break;
		}
		logMsg('--- DRY-RUN, no se inserta nada. ---');
		goto end;
	}

	$nInserted = 0;
	$nWithImg  = 0;
	$nWithVar  = 0;
	$nErrors   = 0;
	$processed = 0;
	$nFormatted = 0;
	$nFormatFail = 0;
	$nWithSpec = 0;
	$nVarImg = 0;

	// 1) Series con N BaseCodes → 1 producto + N variantes
	foreach ($newSeries as $serieId => $bases) {
		if ($max > 0 && $processed >= $max) {
			logMsg("Alcanzado límite de $max, parando.");
			break;
		}
		$processed++;

		try {
			$serieInfo = $serMap[(int) $serieId] ?? null;

			// Recoger items: aplanar (cada base tiene 1+ orderCodes; usamos primero por simplicidad)
			$items = [];   // base_code => order data
			foreach ($bases as $bc => $orders) {
				usort($orders, function($a, $b) { return $a['base_qty'] - $b['base_qty']; });
				$items[$bc] = $orders[0];
			}

			// Ordenar por precio para encontrar el más barato (será la base)
			uasort($items, function($a, $b) {
				$pa = calcUnitPriceNoVat($a['street_price'], $a['base_qty']);
				$pb = calcUnitPriceNoVat($b['street_price'], $b['base_qty']);
				return $pa <=> $pb;
			});

			$cheapestBase = array_key_first($items);
			$cheapestItem = $items[$cheapestBase];
			$baseUnitPrice = roundToNickel(calcUnitPriceNoVat($cheapestItem['street_price'], $cheapestItem['base_qty']));
			if ($baseUnitPrice <= 0) {
				logMsg("[serie $serieId] precio inválido, saltando");
				$nErrors++;
				continue;
			}

			// Imagen: probar varios candidatos (img, suggested_img, espacios↔guiones bajos).
			// Decisión usuario 2026-05-05: NO importar productos sin imagen → si todos los
			// candidatos fallan en FTP, SKIP la serie entera antes de hacer INSERT.
			$imgCandidates = [];
			if (isset($codeMap[$cheapestBase])) {
				$imgCandidates = osculatiImageCandidates($codeMap[$cheapestBase]);
			}
			// Probar también la imagen común de la serie (foto_serie de ser_export)
			if (!empty($serieInfo['foto_serie'])) {
				$serieEntry = ['img' => $serieInfo['foto_serie'], 'suggested_img' => ''];
				foreach (osculatiImageCandidates($serieEntry) as $c) {
					if (!in_array($c, $imgCandidates, true)) $imgCandidates[] = $c;
				}
			}

			if (empty($imgCandidates) || !is_dir(IMG_ABS_DIR)) {
				logMsg("[serie $serieId] SKIP: sin candidatos de imagen (Code2Ser sin entrada o IMG_ABS_DIR no existe)");
				$nSkippedNoImg = ($nSkippedNoImg ?? 0) + 1;
				continue;
			}

			// Descarga ANTES del INSERT a un fichero temporal. Si todos los candidatos
			// fallan, no insertamos nada.
			$tmpImgPath = IMG_ABS_DIR . 'osc-tmp-' . uniqid() . '.jpg';
			$winningCandidate = downloadImageWithFallbacks($imgCandidates, $tmpImgPath);
			if ($winningCandidate === '') {
				logMsg("[serie $serieId] SKIP: imagen no descargable. Candidatos probados: " . implode(', ', $imgCandidates));
				$nSkippedNoImg = ($nSkippedNoImg ?? 0) + 1;
				continue;
			}

			// Título y descripción de la serie (TitoloSerie en EN porque /ENG/, traducimos a ES)
			$titleEn = $serieInfo['titulo'] ?? '';
			if ($titleEn === '') {
				// Fallback: prefijo común de los items IT
				$itTitles = array_map(function($it) { return $it['desc_it']; }, $items);
				$titleEn = trim(longestCommonPrefix($itTitles));
				if ($titleEn === '') $titleEn = $cheapestItem['desc_en'] ?: $cheapestItem['desc_it'];
			}
			$descEn = $serieInfo['desc_html'] ?? '';

			$titleEs = translateIT($titleEn);
			if ($titleEs === '') $titleEs = $titleEn;
			$titleEs = mb_substr($titleEs, 0, 80, 'UTF-8');

			$descEs = '';
			if ($descEn !== '') {
				$descEs = translateIT($descEn);
				if ($descEs === '') $descEs = '[TRADUCIR]<br>' . $descEn;
				$_fs = 'none';
				$descEs = osculatiMaquetar($descEs, $skipFormat, $_fs);
				if ($_fs === 'ok') $nFormatted++; elseif ($_fs === 'fail') $nFormatFail++;
			}

			// Tabla de especificaciones (matriz variantes×captions) desde Code2SerXml.
			if (!empty($xtMap)) {
				$_ocodes = [];
				foreach ($items as $_bc => $_it) { $_oc = $_it['order_code'] ?? $_bc; if ($_oc !== '') $_ocodes[] = $_oc; }
				list($_specEs, $_specEn) = oscSpecBlock($_ocodes, $xtMap);
				if ($_specEs !== '') {
					$descEs = ($descEs !== '' ? $descEs . "\n" : '') . $_specEs;
					$descEn = ($descEn !== '' ? $descEn . "\n" : '') . $_specEn;
					$nWithSpec++;
				}
			}

			$weightKg = $cheapestItem['weight_g'] / max(1, $cheapestItem['base_qty']) / 1000;
			$nameForSeo = slugify($titleEs) . '-s' . $serieId;

			tep_db_query('START TRANSACTION');

			tep_db_query("INSERT INTO products SET
				products_quantity      = 0,
				check_stock            = 0,
				products_model         = '" . tep_db_input($cheapestBase) . "',
				products_image         = '',
				products_price         = " . number_format($baseUnitPrice, 4, '.', '') . ",
				products_cost          = " . number_format($cheapestItem['list_price'] / 100 / max(1,$cheapestItem['base_qty']), 4, '.', '') . ",
				products_date_added    = NOW(),
				products_weight        = " . number_format(max(0, $weightKg), 3, '.', '') . ",
				products_status        = 2,
				products_tax_class_id  = " . TAX_CLASS_IVA21 . ",
				manufacturers_id       = " . OSCULATI_MFG_ID . ",
				product_ean            = '" . tep_db_input($cheapestItem['ean']) . "',
				reference_prov         = '" . tep_db_input($cheapestItem['order_code']) . "',
				products_import_origin = 'osculati-altas-s" . $serieId . "'");
			$productsId = (int) tep_db_insert_id();

			// EAN interno si product_ean quedó vacío o inválido
			if ($productsId > 0) {
				$_check = tep_db_query("SELECT product_ean FROM products WHERE products_id = $productsId");
				$_cur = tep_db_fetch_array($_check);
				if (!isValidEan13($_cur['product_ean'] ?? '')) {
					$_gen = generateInternalEan13($productsId, EAN_INTERNAL_PREFIX);
					if ($_gen !== '') {
						tep_db_query("UPDATE products SET product_ean = '" . tep_db_input($_gen) . "' WHERE products_id = $productsId");
					}
				}
			}

			$nameEsSafe = tep_db_input($titleEs);
			$descEsSafe = tep_db_input($descEs);
			$nameEnSafe = tep_db_input(mb_substr($titleEn, 0, 80, 'UTF-8'));
			$descEnSafe = tep_db_input($descEn);
			tep_db_query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_seo_name, products_seo_url) VALUES
				($productsId, " . LANG_ID_ES . ", '$nameEsSafe', '$descEsSafe', '" . tep_db_input($nameForSeo) . "', ''),
				($productsId, " . LANG_ID_EN . ", '$nameEnSafe', '$descEnSafe', '" . tep_db_input($nameForSeo) . "', '')");

			tep_db_query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($productsId, " . NEW_CATEGORY_ID . ")");

			// Mover la imagen ya descargada al nombre definitivo y actualizar products_image.
			// (La descarga ya se validó antes del INSERT — aquí solo renombramos.)
			$imgInserted = false;
			$ext = strtolower(pathinfo($winningCandidate, PATHINFO_EXTENSION)) ?: 'jpg';
			$localImgName = $nameForSeo . '-' . $productsId . '.' . $ext;
			$localImgPath = IMG_ABS_DIR . $localImgName;
			if (@rename($tmpImgPath, $localImgPath)) {
				tep_db_query("UPDATE products SET products_image = '" . tep_db_input($localImgName) . "' WHERE products_id = $productsId");
				$imgInserted = true;
				$nWithImg++;
			} else {
				logMsg("[serie $serieId] AVISO: rename de tmp imagen falló, queda en $tmpImgPath");
			}

			// Calcular el prefijo común IT para extraer el sufijo de variante (ej. "4 mm")
			$itTitles = array_map(function($it) { return trim($it['desc_it']); }, $items);
			$commonItPrefix = longestCommonPrefix($itTitles);
			$commonItPrefix = rtrim($commonItPrefix, " -–·"); // limpiar separadores finales

			$variantsCreated = 0;
			// Track de OVs ya consumidos por este producto (guardia anti-colisión de labels)
			$ovsUsados = [];
			$variantImgSrc = [];
			foreach ($items as $bc => $it) {
				$varUnitPrice = roundToNickel(calcUnitPriceNoVat($it['street_price'], $it['base_qty']));
				$delta = round($varUnitPrice - $baseUnitPrice, 4);
				$prefix = $delta < 0 ? '-' : '+';

				// Etiqueta de la variante: parte del título IT que no está en el prefijo común
				$variantLabel = '';
				if ($commonItPrefix !== '' && mb_strpos($it['desc_it'], $commonItPrefix) === 0) {
					$variantLabel = trim(mb_substr($it['desc_it'], mb_strlen($commonItPrefix, 'UTF-8'), null, 'UTF-8'));
				}
				// Si label vacío o parece un código Osculati (XX.YYY.ZZ) → buscar medida en el título
				if ($variantLabel === '' || preg_match('/^\d+\.\d+\.\d+/', $variantLabel)) {
					$measure = extractMeasureFromTitle($it['desc_it']);
					if ($measure !== '') $variantLabel = $measure;
				}
				if ($variantLabel === '') $variantLabel = $bc;
				$variantLabel = mb_substr($variantLabel, 0, 64, 'UTF-8');

				// Traducir IT→ES e IT→EN si la etiqueta tiene texto descriptivo (no solo medidas).
				// Los valores que son solo medidas (4 mm, 12 kg, 500 W - 12 V, 8/16 mm) no necesitan traducir.
				if (isJustMeasure($variantLabel)) {
					$variantLabelEs = $variantLabel;
					$variantLabelEn = $variantLabel;
				} else {
					$tEs = translateITto($variantLabel, 'es');
					$tEn = translateITto($variantLabel, 'en');
					$variantLabelEs = $tEs !== '' ? mb_substr($tEs, 0, 64, 'UTF-8') : $variantLabel;
					$variantLabelEn = $tEn !== '' ? mb_substr($tEn, 0, 64, 'UTF-8') : $variantLabel;
				}

				$valueId = findOrCreateOptionValue($variantLabelEs, $variantLabelEn);
				if (isset($ovsUsados[$valueId])) {
					$disRef = $it['order_code'] ?? $bc;
					$labelEsDis = mb_substr($variantLabelEs . ' (' . $disRef . ')', 0, 64, 'UTF-8');
					$labelEnDis = mb_substr($variantLabelEn . ' (' . $disRef . ')', 0, 64, 'UTF-8');
					$valueId = findOrCreateOptionValue($labelEsDis, $labelEnDis);
				}
				$ovsUsados[$valueId] = true;
				$variantWeightKg = $it['weight_g'] / max(1, $it['base_qty']) / 1000;
				// Peso variante = DELTA sobre el padre (shopping_cart.php suma con prefix +/-)
				$wDelta  = round($variantWeightKg - $weightKg, 3);
				$wPrefix = $wDelta < 0 ? '-' : '+';
				$wAbs    = abs($wDelta);

				tep_db_query("INSERT INTO products_attributes SET
					products_id           = $productsId,
					options_id            = " . VARIANT_OPTION_ID . ",
					options_values_id     = $valueId,
					options_values_price  = " . number_format(abs($delta), 4, '.', '') . ",
					price_prefix          = '$prefix',
					reference             = '" . tep_db_input($it['base_code']) . "',
					reference_prov        = '" . tep_db_input($it['order_code']) . "',
					products_attributes_ean = '" . tep_db_input($it['ean']) . "',
					options_values_weight = " . number_format($wAbs, 3, '.', '') . ",
					weight_prefix         = '$wPrefix'");
				$variantsCreated++;

				// Imagen por variante (change_image) desde Code2SerXml <images>
				if (!empty($xtMap)) {
					$vKey = strtolower(preg_replace('/#.*$/', '', (string) $it['order_code']));
					$vImgCode = isset($xtMap[$vKey]) ? oscImageCodeFromXml($xtMap[$vKey]) : '';
					if ($vImgCode !== '' && is_dir(IMG_ATTR_DIR)) {
						$vImgName = preg_match('/\\.(jpg|jpeg|png|gif|webp)$/i', $vImgCode) ? $vImgCode : $vImgCode . '.jpg';
						$aiFile = 'ai_' . $productsId . '-' . VARIANT_OPTION_ID . '-' . $valueId . '.jpg';
						if (downloadImage($vImgName, IMG_ATTR_DIR . $aiFile)) {
							tep_db_query("DELETE FROM products_attributes_actions WHERE products_id=$productsId AND products_attributes='" . VARIANT_OPTION_ID . "-$valueId' AND action='change_image'");
							tep_db_query("INSERT INTO products_attributes_actions (products_id, products_attributes, value, action) VALUES ($productsId, '" . VARIANT_OPTION_ID . "-$valueId', '" . tep_db_input($aiFile) . "', 'change_image')");
							if (!isset($variantImgSrc[$vImgCode])) $variantImgSrc[$vImgCode] = IMG_ATTR_DIR . $aiFile;
							$nVarImg++;
						}
					}
				}
			}
			if ($variantsCreated > 0) $nWithVar++;

			// Galería del padre: añadir imágenes de variante DISTINTAS (dedup por md5 vs principal)
			if (!empty($variantImgSrc) && !empty($imgInserted)) {
				$mainPath = IMG_ABS_DIR . $localImgName;
				$seenH = [];
				if (file_exists($mainPath)) $seenH[md5_file($mainPath)] = true;
				$subs = []; $gi = 1;
				foreach ($variantImgSrc as $vc => $srcPath) {
					if (!file_exists($srcPath)) continue;
					$h = md5_file($srcPath);
					if (isset($seenH[$h])) continue;
					$seenH[$h] = true; $gi++;
					$gName = preg_replace('/\\.(jpg|jpeg|png|gif|webp)$/i', '', $localImgName) . '-v' . $gi . '.jpg';
					if (@copy($srcPath, IMG_ABS_DIR . $gName)) $subs[] = $gName;
				}
				if ($subs) tep_db_query("UPDATE products SET products_subimages='" . tep_db_input(json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "' WHERE products_id=$productsId");
			}

			// G1 (Profesionales) = products_price × 0.85 sobre PVP sin IVA. Redondeado a .05 con IVA.
			$g1Main = roundToNickel($baseUnitPrice * 0.85);
			tep_db_query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (1, $productsId, " . number_format($g1Main, 4, '.', '') . ", 1, 1)");

			// G1 por variante: delta sobre G1 padre, también redondeado a .05 con IVA
			$rPa = tep_db_query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes WHERE products_id = $productsId");
			$pasMap = [];
			while ($pa = tep_db_fetch_array($rPa)) $pasMap[(int)$pa['products_attributes_id']] = $pa;
			foreach ($pasMap as $paId => $pa) {
				$absDelta = (float) $pa['options_values_price'];
				$prefix = $pa['price_prefix'] === '-' ? -1 : 1;
				$varUnit = $baseUnitPrice + $prefix * $absDelta;
				$g1Var = roundToNickel($varUnit * 0.85);
				$g1Delta = round($g1Var - $g1Main, 4);
				$g1Prefix = $g1Delta < 0 ? '-' : '+';
				tep_db_query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, 1, " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $productsId, 0, '+')");
			}

			tep_db_query('COMMIT');
			$nInserted++;

			logMsg(sprintf('OK [%d] serie %d id=%d "%s" %s [%d variantes]',
				$processed, $serieId, $productsId, $titleEs,
				$imgInserted ? '[img]' : '[sin-img]', $variantsCreated));

		} catch (Exception $e) {
			tep_db_query('ROLLBACK');
			$nErrors++;
			logMsg("ERROR serie $serieId: " . $e->getMessage());
		}
	}

	// 2) Standalone (sin serie) — 1 BaseCode = 1 producto sin variantes
	foreach ($newStandalone as $baseCode => $orders) {
		if ($max > 0 && $processed >= $max) {
			logMsg("Alcanzado límite de $max, parando.");
			break;
		}
		$processed++;

		try {
			usort($orders, function($a, $b) { return $a['base_qty'] - $b['base_qty']; });
			$mainOrder = $orders[0];

			$baseUnitPrice = roundToNickel(calcUnitPriceNoVat($mainOrder['street_price'], $mainOrder['base_qty']));
			if ($baseUnitPrice <= 0) { $nErrors++; continue; }

			$weightKg = $mainOrder['weight_g'] / max(1, $mainOrder['base_qty']) / 1000;
			$titleIt = $mainOrder['desc_it'] !== '' ? $mainOrder['desc_it'] : $baseCode;
			$titleIt = mb_substr($titleIt, 0, 200, 'UTF-8');

			$titleEs = translateIT($titleIt);
			if ($titleEs === '') $titleEs = $titleIt;
			$titleEs = mb_substr($titleEs, 0, 80, 'UTF-8');

			$nameForSeo = slugify($titleEs) . '-' . strtolower($baseCode);

			// Imagen: probar candidatos antes del INSERT (decisión usuario 2026-05-05).
			// Si no hay imagen descargable → SKIP la alta entera.
			$imgCandidates = [];
			if (isset($codeMap[$baseCode])) {
				$imgCandidates = osculatiImageCandidates($codeMap[$baseCode]);
			}
			if (empty($imgCandidates) || !is_dir(IMG_ABS_DIR)) {
				logMsg("[suelto $baseCode] SKIP: sin candidatos de imagen");
				$nSkippedNoImg = ($nSkippedNoImg ?? 0) + 1;
				continue;
			}
			$tmpImgPath = IMG_ABS_DIR . 'osc-tmp-' . uniqid() . '.jpg';
			$winningCandidate = downloadImageWithFallbacks($imgCandidates, $tmpImgPath);
			if ($winningCandidate === '') {
				logMsg("[suelto $baseCode] SKIP: imagen no descargable. Candidatos probados: " . implode(', ', $imgCandidates));
				$nSkippedNoImg = ($nSkippedNoImg ?? 0) + 1;
				continue;
			}

			tep_db_query('START TRANSACTION');

			tep_db_query("INSERT INTO products SET
				products_quantity      = 0,
				check_stock            = 0,
				products_model         = '" . tep_db_input($baseCode) . "',
				products_image         = '',
				products_price         = " . number_format($baseUnitPrice, 4, '.', '') . ",
				products_cost          = " . number_format($mainOrder['list_price'] / 100 / max(1,$mainOrder['base_qty']), 4, '.', '') . ",
				products_date_added    = NOW(),
				products_weight        = " . number_format(max(0, $weightKg), 3, '.', '') . ",
				products_status        = 2,
				products_tax_class_id  = " . TAX_CLASS_IVA21 . ",
				manufacturers_id       = " . OSCULATI_MFG_ID . ",
				product_ean            = '" . tep_db_input($mainOrder['ean']) . "',
				reference_prov         = '" . tep_db_input($mainOrder['order_code']) . "',
				products_import_origin = 'osculati-altas'");
			$productsId = (int) tep_db_insert_id();

			// EAN interno si product_ean quedó vacío o inválido
			if ($productsId > 0) {
				$_check = tep_db_query("SELECT product_ean FROM products WHERE products_id = $productsId");
				$_cur = tep_db_fetch_array($_check);
				if (!isValidEan13($_cur['product_ean'] ?? '')) {
					$_gen = generateInternalEan13($productsId, EAN_INTERNAL_PREFIX);
					if ($_gen !== '') {
						tep_db_query("UPDATE products SET product_ean = '" . tep_db_input($_gen) . "' WHERE products_id = $productsId");
					}
				}
			}

			// Descripción larga: del ser_export vía la IDSerie del BaseCode (igual que la rama de series).
			// Fix 2026-05-13: antes los standalone insertaban products_description SIEMPRE vacía.
			$descEn = '';
			$descEs = '';
			if (isset($codeMap[$baseCode]) && (int) $codeMap[$baseCode]['id_serie'] > 0) {
				$sid = (int) $codeMap[$baseCode]['id_serie'];
				if (isset($serMap[$sid])) {
					$descEn = $serMap[$sid]['desc_html'] ?? '';
					if ($descEn !== '') {
						$descEs = translateIT($descEn);
						if ($descEs === '') $descEs = '[TRADUCIR]<br>' . $descEn;
						$_fs = 'none';
						$descEs = osculatiMaquetar($descEs, $skipFormat, $_fs);
						if ($_fs === 'ok') $nFormatted++; elseif ($_fs === 'fail') $nFormatFail++;
					}
				}
			}

			// Tabla de especificaciones (2 columnas) desde Code2SerXml para el OrderCode del suelto.
			if (!empty($xtMap)) {
				$_oc = $mainOrder['order_code'] ?? $baseCode;
				list($_specEs, $_specEn) = oscSpecBlock([$_oc], $xtMap);
				if ($_specEs !== '') {
					$descEs = ($descEs !== '' ? $descEs . "\n" : '') . $_specEs;
					$descEn = ($descEn !== '' ? $descEn . "\n" : '') . $_specEn;
					$nWithSpec++;
				}
			}

			$nameEsSafe = tep_db_input($titleEs);
			$nameEnSafe = tep_db_input(mb_substr($mainOrder['desc_en'] ?: $titleIt, 0, 80, 'UTF-8'));
			$descEsSafe = tep_db_input($descEs);
			$descEnSafe = tep_db_input($descEn);
			tep_db_query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_seo_name, products_seo_url) VALUES
				($productsId, " . LANG_ID_ES . ", '$nameEsSafe', '$descEsSafe', '" . tep_db_input($nameForSeo) . "', ''),
				($productsId, " . LANG_ID_EN . ", '$nameEnSafe', '$descEnSafe', '" . tep_db_input($nameForSeo) . "', '')");

			tep_db_query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($productsId, " . NEW_CATEGORY_ID . ")");

			// Mover imagen tmp al nombre definitivo y registrar en BD
			$ext = strtolower(pathinfo($winningCandidate, PATHINFO_EXTENSION)) ?: 'jpg';
			$localImgName = $nameForSeo . '-' . $productsId . '.' . $ext;
			$localImgPath = IMG_ABS_DIR . $localImgName;
			if (@rename($tmpImgPath, $localImgPath)) {
				tep_db_query("UPDATE products SET products_image = '" . tep_db_input($localImgName) . "' WHERE products_id = $productsId");
				$nWithImg++;
			} else {
				logMsg("[suelto $baseCode] AVISO: rename de tmp imagen falló, queda en $tmpImgPath");
			}

			// G1 (Profesionales) = products_price × 0.85 sobre PVP sin IVA. Redondeado a .05 con IVA.
			$g1Main = roundToNickel($baseUnitPrice * 0.85);
			tep_db_query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (1, $productsId, " . number_format($g1Main, 4, '.', '') . ", 1, 1)");

			tep_db_query('COMMIT');
			$nInserted++;
			logMsg(sprintf('OK [%d] suelto %s id=%d "%s"', $processed, $baseCode, $productsId, $titleEs));

		} catch (Exception $e) {
			tep_db_query('ROLLBACK');
			$nErrors++;
			logMsg("ERROR [$baseCode]: " . $e->getMessage());
		}
	}

	logMsg('=== RESUMEN ===');
	logMsg("Procesados : $processed");
	logMsg("Insertados : $nInserted");
	logMsg("Con imagen : $nWithImg");
	logMsg("Con variantes: $nWithVar");
	logMsg("Maquetadas : $nFormatted (fallidas: $nFormatFail)" . ($skipFormat ? " [maquetado DESACTIVADO]" : ""));
	logMsg("Con tabla specs: $nWithSpec");
	logMsg("Imágenes por variante: $nVarImg");
	logMsg("SKIP sin imagen: " . ($nSkippedNoImg ?? 0));
	logMsg("Errores    : $nErrors");

	end:;
}
?>
</div>
<p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-osculati-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
