<?php
/**
 * Rellena la descripción de los productos Osculati STANDALONE (products_import_origin='osculati-altas',
 * sin sufijo -sXXX) que se importaron con products_description vacía.
 *
 * El bug: la rama standalone del importador insertaba products_description = '' siempre. La rama
 * de series sí traía desc_html del ser_export. Corregido en el importador 2026-05-13.
 *
 * STRICT: solo rellena productos cuya descripción ES (lang=3) esté vacía o NULL. NO pisa descripciones
 * añadidas manualmente.
 *
 * Fuente: Code2Ser.txt (BaseCode → IDSerie) + ENG/ser_export.txt (IDSerie → desc_html en inglés).
 * Traduce IT/EN→ES con el LLM local. lang=1 (EN) recibe el desc_html original; lang=3 (ES) la traducción.
 *
 * Modos:
 *   php osculati_fill_standalone_desc.php          # DRY (lista)
 *   php osculati_fill_standalone_desc.php EXECUTE   # aplica
 *   php osculati_fill_standalone_desc.php EXECUTE LIMIT=5
 */
include '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

$dry = !in_array('EXECUTE', $argv ?? [], true);
$limit = 0;
foreach (($argv ?? []) as $a) if (preg_match('/^LIMIT=(\d+)$/i', $a, $mm)) $limit = (int) $mm[1];

const OSC_USER = 'C54293';
const OSC_PASS = '0XxBkWSb';
const OSC_FTP_BASE = 'ftp://fw.osculati.it/';
require_once __DIR__ . '/osculati_gateway.inc.php';
const LLM_URL   = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'Eres un traductor profesional de italiano/inglés a español especializado en productos náuticos y de pesca. Traduce con terminología técnica náutica precisa en español de España. Mantén las etiquetas HTML si las hay. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

function ftpDownload($remote, $local) {
	return osculatiGw($remote, $local, 1000);
}

function readUtf16($path) {
	$raw = file_get_contents($path);
	return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
}

function translateIT($text) {
	$text = trim((string) $text);
	if ($text === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => LLM_PROMPT],
			['role' => 'user',   'content' => 'Traduce al español: ' . $text],
		],
		'chat_template_kwargs' => ['enable_thinking' => false],
		'max_tokens'  => 2000,
		'temperature' => 0.3, 'top_p' => 0.8, 'top_k' => 20,
	], JSON_UNESCAPED_UNICODE);
	$ch = curl_init(LLM_URL);
	curl_setopt_array($ch, [
		CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
	]);
	$resp = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	// sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
	if ($httpCode !== 200 || $resp === false) return '';
	$data = json_decode($resp, true);
	return trim((string) ($data['choices'][0]['message']['content'] ?? ''));
}

// 1) Descargar Code2Ser + ser_export
$codeFile = '/tmp/Code2Ser.txt';
$serFile  = '/tmp/ser_export.txt';
if (!file_exists($codeFile) || filesize($codeFile) < 1000000) {
	echo "Descargando ENG/Code2Ser.txt...\n";
	if (!ftpDownload('ENG/Code2Ser.txt', $codeFile)) { echo "ERROR: no se pudo descargar Code2Ser.txt\n"; exit(1); }
}
if (!file_exists($serFile) || filesize($serFile) < 100000) {
	echo "Descargando ENG/ser_export.txt...\n";
	if (!ftpDownload('ENG/ser_export.txt', $serFile)) { echo "ERROR: no se pudo descargar ser_export.txt\n"; exit(1); }
}

// 2) Parsear: codeMap[baseCode] = id_serie
$codeMap = [];
foreach (preg_split("/\r?\n/", readUtf16($codeFile)) as $line) {
	$cols = explode("\t", $line);
	if (count($cols) < 2) continue;
	$base = trim($cols[0]);
	if ($base === '') continue;
	$codeMap[$base] = (int) $cols[1];
}
echo "Code2Ser: " . count($codeMap) . " entradas\n";

// serMap[id_serie] = desc_html (col 7, 0-indexed 6)
$serMap = [];
foreach (preg_split("/\r?\n/", readUtf16($serFile)) as $line) {
	$cols = explode("\t", $line);
	if (count($cols) < 7) continue;
	$id = (int) $cols[0];
	if ($id <= 0) continue;
	$serMap[$id] = trim($cols[6] ?? '');
}
echo "ser_export: " . count($serMap) . " series\n";

// 3) Productos osculati-altas (standalone) con descripción ES vacía
$r = $m->query("
SELECT p.products_id, p.products_model
FROM products p
JOIN products_description pds ON pds.products_id = p.products_id AND pds.language_id = 3
WHERE p.products_import_origin = 'osculati-altas'
  AND (pds.products_description IS NULL OR pds.products_description = '')
ORDER BY p.products_id");
$candidates = [];
while ($row = $r->fetch_assoc()) {
	$base = $row['products_model'];
	$descEn = '';
	if (isset($codeMap[$base]) && $codeMap[$base] > 0 && isset($serMap[$codeMap[$base]])) {
		$descEn = $serMap[$codeMap[$base]];
	}
	$candidates[] = $row + ['_serie' => $codeMap[$base] ?? 0, '_desc_en' => $descEn];
}

$withSrc = array_filter($candidates, fn($c) => $c['_desc_en'] !== '');
$noSrc   = array_filter($candidates, fn($c) => $c['_desc_en'] === '');
echo "\nStandalone osculati-altas con descripción vacía: " . count($candidates) . "\n";
echo "  Con desc_html en ser_export: " . count($withSrc) . "\n";
echo "  Sin fuente de descripción : " . count($noSrc) . "\n\n";
foreach ($candidates as $c) {
	$tag = $c['_desc_en'] !== '' ? 'OK src (' . strlen($c['_desc_en']) . ' chars)' : 'SIN FUENTE';
	echo "  pid={$c['products_id']} sku={$c['products_model']} serie={$c['_serie']} → $tag\n";
}

$toProcess = array_values($withSrc);
if ($limit > 0 && count($toProcess) > $limit) {
	$toProcess = array_slice($toProcess, 0, $limit);
	echo "\n  Limitando a $limit (LIMIT=$limit)\n";
}

if ($dry) { echo "\n[DRY] no se aplica nada. Pasa EXECUTE para rellenar.\n"; exit; }
if (empty($toProcess)) { echo "\nNada que rellenar.\n"; exit; }

echo "\nRellenando…\n";
$ok = 0; $fail = 0;
foreach ($toProcess as $c) {
	$pid = (int) $c['products_id'];
	$descEn = $c['_desc_en'];
	$descEs = translateIT($descEn);
	if ($descEs === '') { $descEs = '[TRADUCIR]<br>' . $descEn; }
	$qEs = $m->real_escape_string($descEs);
	$qEn = $m->real_escape_string($descEn);
	$ok1 = $m->query("UPDATE products_description SET products_description='$qEs' WHERE products_id=$pid AND language_id=3");
	$ok2 = $m->query("UPDATE products_description SET products_description='$qEn' WHERE products_id=$pid AND language_id=1");
	if ($ok1 && $ok2) {
		$ok++;
		echo "  OK pid=$pid sku={$c['products_model']} (ES " . strlen($descEs) . " chars / EN " . strlen($descEn) . " chars)\n";
	} else {
		$fail++;
		echo "  ERR pid=$pid: " . $m->error . "\n";
	}
}
echo "\n=== RESUMEN: ok=$ok fail=$fail ===\n";
