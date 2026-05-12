<?php
require 'includes/application_top.php';
require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const CRESSI_XLSM         = '/home/francobordo/public_html/import/Cressi/tarifa_cressi_completa_con_ean_y_pvp_actual-270520.xlsm';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_ID     = 1717;
const TAX_CLASS_IVA21     = 1;
const IVA_ES              = 1.21; // IVA general España; PVP recomendado del xlsm viene CON IVA → dividir antes de guardar.
const VAT_RATE            = 0.21; // Para roundToNickel (mismo que IVA_ES-1, pero como rate decimal)
const LANG_ID_ES          = 3;
const LANG_ID_EN          = 1;
const G1_GROUP_ID         = 1;
const G1_FLOOR_FACTOR     = 1.10;
const PRODUCT_NAME_MAX    = 80;
const IMG_HTTP_TIMEOUT    = 12;
const SCRAPE_TIMEOUT      = 12;
const ORIGIN_FLAG         = 'cressi';
const EAN_INTERNAL_PREFIX = 23; // si surgiese alguno con EAN inválido (no debería: filtramos sin EAN)

// LLM ES→EN para descripciones largas + maquetado HTML
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'You are a professional Spanish→English translator specialised in scuba/diving/snorkeling/freediving gear. Use precise English diving terminology. The input is HTML — preserve all tags (<p>, <ul>, <li>, <strong>, <br>) untouched, only translate the text content. Reply ONLY with the translated HTML, no comments.';
const LLM_FORMAT_PROMPT = "Eres un experto en maquetar fichas de producto de equipo de buceo Cressi siguiendo el estilo editorial de cressi.es.\n\nRecibes una descripción comercial en español como un bloque continuo. Tu tarea: estructurarla en HTML limpio y legible siguiendo el estilo Cressi.\n\nREGLAS ESTRICTAS:\n\n1. SECCIONES: si el producto se compone de varias partes (ej. \"2ª ETAPA GALAXY\" + \"1ª ETAPA T10sc\", o un kit con varios componentes), abre cada bloque con un <h3> que contenga el nombre de la sección. Detecta el inicio de cada sección por palabras clave del texto (\"2ª etapa\", \"1ª etapa\", nombre de modelo en mayúsculas, etc.). Si el producto es uno solo, NO uses <h3>.\n\n2. PÁRRAFOS: separa el cuerpo en varios <p> de 1-3 frases agrupando ideas relacionadas. Cambio de tema = nuevo párrafo. NO metas <br> dentro de un párrafo: las frases deben fluir naturalmente.\n\n3. BOLD ESTILO CRESSI: usa <strong> generosamente para destacar partes de frase con valor descriptivo, no solo nombres puntuales. Ejemplos del estilo Cressi:\n   - \"Mecanismo compensado neumáticamente que garantiza una <strong>relación suavidad-caudal excepcional y una entrega de aire abundante y calibrada</strong> sin necesidad…\"\n   - \"Sección elíptica que permite un <strong>brazo de palanca incluso mayor al de reguladores mucho más grandes</strong>, con un efecto óptico…\"\n   - \"Membrana de admisión sobredimensionada para una <strong>mayor suavidad inspiratoria</strong>\"\n   - \"<strong>Deflector Dive-Predive</strong> para gestionar el efecto Venturi.\"\n   Destaca características diferenciadoras, ventajas competitivas y nombres de tecnología. NO destaques cifras sueltas ni frases triviales.\n\n4. ESPECIFICACIONES TÉCNICAS: si encuentras una enumeración tipo \"Peso: X g, Caudal: Y l/min, Salidas: …\" o \"+Info Especificaciones técnicas:\", conviértela en <ul><li> con la etiqueta en <strong>. Ejemplo: <li><strong>Peso:</strong> 185 g</li>.\n\n5. NO resumas, NO parafrasees. Conserva TODO el texto original; solo añades estructura HTML y bold.\n\n6. Etiquetas permitidas: <h3>, <p>, <ul>, <li>, <strong>. NO uses <h1>, <h2>, <br>, <div>, <span> ni nada más.\n\n7. Salida: SOLO el HTML resultante, sin markdown, sin comentarios, sin tres-back-ticks ni explicaciones.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$onlyCodesRaw = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') $onlyCodes[$c] = true;
	}
}
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }
$skipScrape       = isset($_POST['skip_scrape']) || isset($_GET['skip_scrape']);
$skipTranslation  = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);

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

function generateInternalEan13($pid, $prefix) {
	$pp = (int) $prefix;
	if ($pp < 20 || $pp > 28) return '';
	if ($pid <= 0 || $pid > 9999999999) return '';
	$payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $pid, 10, '0', STR_PAD_LEFT);
	$check = ean13Checksum($payload);
	return $check < 0 ? '' : ($payload . $check);
}

function cressiSlugify($text, $maxLen = 50) {
	$t = trim((string) $text);
	if (function_exists('iconv')) {
		$conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
		if ($conv !== false && $conv !== '') $t = $conv;
	}
	$t = strtolower($t);
	$t = preg_replace('/[^a-z0-9]+/', '-', $t);
	$t = trim($t, '-');
	if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
	return $t === '' ? 'producto' : $t;
}

/**
 * Convierte el nombre de la sección (en plural mayúsculas) a singular para el título de producto.
 * Mapping manual para los casos del catálogo Cressi; fallback heurístico para el resto.
 */
function cressiSingular($section) {
	$map = [
		'REGULADORES' => 'Regulador',
		'CHALECOS' => 'Chaleco',
		'CHALECO SNORKEL' => 'Chaleco snorkel',
		'ORDENADORES' => 'Ordenador',
		'APARATOS DE CONTROL' => 'Aparato de control',
		'RELOJES' => 'Reloj',
		'BOTELLAS' => 'Botella',
		'TRAJES DE BUCEO' => 'Traje de buceo',
		'CAPUCHAS' => 'Capucha',
		'TRAJES APNEA' => 'Traje apnea',
		'MONOSHORTS' => 'Monoshort',
		'COMPLEMENTOS TERMICOS' => 'Complemento térmico',
		'COMPLEMENTOS TÉRMICOS' => 'Complemento térmico',
		'CAMISETAS RASH GUARD' => 'Camiseta Rash Guard',
		'ALETAS REGULABLES' => 'Aleta regulable',
		'ALETAS CALZANTES' => 'Aleta calzante',
		'ALETAS APNEA' => 'Aleta apnea',
		'ALETAS SNORKELING Y NATACIÓN' => 'Aleta',
		'MÁSCARAS SILICONA ALTA GAMA' => 'Máscara',
		'MÁSCARAS SILICONA' => 'Máscara',
		'CRISTALES GRADUADOS' => 'Cristal graduado',
		'MÁSCARAS FACIALES' => 'Máscara facial',
		'KITS MÁSCARA Y TUBO ALTA GAMA' => 'Kit máscara y tubo',
		'KITS MÁSCARA Y TUBO' => 'Kit máscara y tubo',
		'KITS GAFA + TUBO + ALETAS' => 'Kit',
		'TUBOS RESPIRADORES' => 'Tubo respirador',
		'LINTERNAS' => 'Linterna',
		'CINTURONES' => 'Cinturón',
		'LASTRE' => 'Lastre',
		'CUCHILLOS' => 'Cuchillo',
		'RECAMBIOS ORDENADORES Y APARATOS DE CONTROL' => 'Recambio',
		'RECAMBIOS TABLAS PADDLE SURF' => 'Recambio',
		'MATERIAL PUNTO DE VENTA' => 'Material',
	];
	$key = mb_strtoupper(trim($section), 'UTF-8');
	if (isset($map[$key])) return $map[$key];
	// Fallback heurístico: capitalizar y quitar plural común (-ES / -S)
	$lower = mb_strtolower($key, 'UTF-8');
	$word = preg_split('/\s+/u', $lower)[0] ?? $lower;
	if (mb_substr($word, -2, 2, 'UTF-8') === 'es') $word = mb_substr($word, 0, mb_strlen($word, 'UTF-8') - 2, 'UTF-8');
	elseif (mb_substr($word, -1, 1, 'UTF-8') === 's') $word = mb_substr($word, 0, mb_strlen($word, 'UTF-8') - 1, 'UTF-8');
	return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
}

/** Quita patrones residuales de talla del nombre o etiqueta (T/, T/  XS, etc.) */
function cleanCressiSizeMarker($s) {
	$s = (string) $s;
	// "T/ " seguido de una talla → quitar solo "T/ " (mantener la talla)
	$s = preg_replace('/\bT\/\s+/u', '', $s);
	// "T/" al final, sin nada después → quitarlo entero
	$s = preg_replace('/\s*T\/\s*$/u', '', $s);
	// Doble espacio → uno
	$s = preg_replace('/\s+/u', ' ', $s);
	return trim($s);
}

/** Busca duplicado de un producto Cressi (fix 2026-05-11).
 *  SKU sólo cuenta como duplicado si pertenece a manufacturer Cressi (138) o tiene
 *  origin cressi%. Un mismo SKU en otra marca distinta NO bloquea el alta.
 *  EAN sigue siendo GLOBAL (identificador único GS1). */
function findExistingProductId($mysqli, $sku, $ean) {
	$skuRaw = trim($sku);
	$variants = array_unique(array_filter([$skuRaw, str_replace(' ', '', $skuRaw)]));
	$esc = array_map(fn($v) => '"' . $mysqli->real_escape_string($v) . '"', $variants);
	$in = implode(',', $esc);
	$mfgFilter = "(manufacturers_id = 138 OR products_import_origin LIKE 'cressi%')";
	$r = $mysqli->query("SELECT products_id FROM products WHERE (products_model IN ($in) OR reference_prov IN ($in)) AND $mfgFilter LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) return (int) $row['products_id'];
	if (!empty($ean)) {
		$qean = $mysqli->real_escape_string(trim($ean));
		$r = $mysqli->query("SELECT products_id FROM products WHERE product_ean='$qean' LIMIT 1");
		if ($r && $row = $r->fetch_assoc()) return (int) $row['products_id'];
	}
	$r = $mysqli->query("SELECT pa.products_id FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE (pa.reference IN ($in) OR pa.reference_prov IN ($in)) AND (p.manufacturers_id = 138 OR p.products_import_origin LIKE 'cressi%') LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) return (int) $row['products_id'];
	return 0;
}

function llmCall($systemPrompt, $userText, $maxRetries = 2, $maxTokens = 2000) {
	if (trim((string) $userText) === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $systemPrompt],
			['role' => 'user',   'content' => $userText],
		],
		'temperature' => 0.2,
		'max_tokens' => $maxTokens,
		'chat_template_kwargs' => ['enable_thinking' => false],
	], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_TIMEOUT => 120,
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

function llmFormatHtml($descRaw) { return llmCall(LLM_FORMAT_PROMPT, $descRaw, 2, 2500); }
function llmTranslate($text)     { return llmCall(LLM_PROMPT, $text, 2, 2500); }

/**
 * Fuerza que las <ul> se rendericen en 1 columna (anular CSS multi-column del template).
 * Si el <ul> ya trae style, no lo sobrescribe.
 */
function forceUlSingleColumn($html) {
	if ($html === '') return '';
	return preg_replace(
		'/<ul(?![^>]*\bstyle=)([^>]*)>/i',
		'<ul$1 style="column-count:1;-moz-column-count:1;-webkit-column-count:1;list-style:disc inside;padding-left:0;">',
		$html
	);
}

/**
 * Elimina líneas boilerplate de Cressi que no aportan al cliente final.
 * Aplicar tanto al texto crudo (antes del LLM) como al HTML final (por si el LLM lo regenera al traducir).
 */
function stripCressiBoilerplate($text) {
	if ($text === '') return '';
	// "+Info Línea Atelier ..." y variantes ES/EN
	$text = preg_replace('#\+\s*Info\s+(L[íi]nea\s+)?Atelier[^<\n\r]*#iu', '', $text);
	$text = preg_replace('#\+\s*Info\s+Atelier\s+line[^<\n\r]*#iu', '', $text);
	// Limpiar <p> que quede vacío tras el strip
	$text = preg_replace('#<p>\s*</p>#i', '', $text);
	// Limpiar <br> huérfanos al inicio o final de cualquier párrafo
	$text = preg_replace('#<p>(\s*<br\s*/?>\s*)+#i', '<p>', $text);
	$text = preg_replace('#(\s*<br\s*/?>\s*)+</p>#i', '</p>', $text);
	return $text;
}

/**
 * Inserta <br> tras cada punto que termina una frase (punto + espacio + mayúscula/¿/¡).
 * No afecta a decimales (sin espacio), abreviaturas internas ni unidades como "182 g. y".
 * Después colapsa "<br><br>" duplicados para no doblar saltos cuando el LLM ya separó párrafos.
 */
function breakAfterSentenceDots($html) {
	if ($html === '') return '';
	$html = preg_replace('/\.\s+(?=[A-ZÁÉÍÓÚÑ¡¿])/u', ".<br>\n", $html);
	$html = preg_replace('#(<br>\s*){2,}#i', '<br>', $html);
	return $html;
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

/**
 * Scrapea una página de cressi.es. Devuelve ['name', 'description', 'image_url'] o array vacío.
 * Extrae datos del bloque ld+json (Schema.org Product) y de meta og:.
 */
function scrapeCressiPage($url) {
	if (empty($url)) return [];
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => SCRAPE_TIMEOUT,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FrancobordoImporter/1.0)',
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	$html = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$effUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	unset($ch);
	if ($html === false || $code !== 200) return ['_status' => 'error_http_' . $code];
	// Cressi redirige fichas no publicadas a /en-construccion
	if (stripos($effUrl, '/en-construccion') !== false) return ['_status' => 'pending'];

	$out = ['name' => '', 'description' => '', 'image_url' => '', 'image_urls' => [], '_status' => 'ok'];

	// 1) ld+json (más completo)
	if (preg_match('#<script[^>]+type="application/ld\+json"[^>]*>(.+?)</script>#is', $html, $m)) {
		$jsonRaw = trim($m[1]);
		// Cressi mete espacios extra dentro del JSON; limpieza ligera
		$jsonClean = preg_replace('/[\x00-\x1F]+/', ' ', $jsonRaw);
		$data = json_decode($jsonClean, true);
		if (is_array($data) && (($data['@type'] ?? '') === 'Product')) {
			if (!empty($data['name'])) $out['name'] = trim($data['name']);
			if (!empty($data['description'])) $out['description'] = trim($data['description']);
			if (!empty($data['image'])) {
				$img = $data['image'];
				$imgs = is_array($img) ? array_map('trim', $img) : [trim($img)];
				$imgs = array_values(array_unique(array_filter($imgs)));
				$out['image_urls'] = $imgs;
				$out['image_url'] = $imgs[0] ?? '';
			}
		}
	}

	// 2) Fallback meta og:
	if ($out['image_url'] === '' && preg_match('#<meta[^>]+property="og:image"[^>]+content="([^"]+)"#i', $html, $m)) {
		$out['image_url'] = trim($m[1]);
		$out['image_urls'] = [$out['image_url']];
	}

	// 3) Fallback páginas legacy Cressi (sin ld+json): buscar otras imágenes que compartan el id numérico de la principal.
	//    En cressi.es las URLs son tipo /archivos/<carpeta>/<id>_<descriptor>.jpg en distintas resoluciones (min, med).
	//    Si la imagen principal no tiene id numérico (ej. "ac2provisional.jpg"), buscar el id más frecuente en TODAS las URLs del HTML.
	if (count($out['image_urls']) <= 1 && $out['image_url'] !== '') {
		$cressiId = '';
		if (preg_match('#/archivos/[^/]+/(\d+)_#', $out['image_url'], $m)) {
			$cressiId = $m[1];
		} elseif (preg_match_all('#/archivos/[a-z_]+/(\d+)_#i', $html, $allIds)) {
			$counts = array_count_values($allIds[1]);
			arsort($counts);
			$cressiId = (string) array_key_first($counts);
		}
		if ($cressiId !== '') {
			if (preg_match_all('#(?:https?://www\.cressi\.es)?/archivos/[a-z_]+/' . preg_quote($cressiId, '#') . '_[^"\s\?]+?\.(?:jpe?g|png)#i', $html, $matches)) {
				$abs = [];
				foreach (array_unique($matches[0]) as $u) {
					$abs[] = (strpos($u, 'http') === 0) ? $u : ('https://www.cressi.es' . $u);
				}
				// Dedup por nombre de archivo (basename), prefiriendo carpeta _med (mejor resolución) sobre _min/_thumb
				$byBase = [];
				$rank = function ($url) {
					if (strpos($url, '/galerias_med/') !== false || strpos($url, '/imagenes_med/') !== false) return 3;
					if (strpos($url, '/detalles_fotos_med/') !== false) return 2;
					if (strpos($url, '/_med/') !== false || strpos($url, '_med/') !== false) return 2;
					return 1;
				};
				foreach ($abs as $u) {
					$base = basename($u);
					if (!isset($byBase[$base]) || $rank($u) > $rank($byBase[$base])) {
						$byBase[$base] = $u;
					}
				}
				$dedup = array_values($byBase);
				// Conservar la principal (og:image) la primera
				$rest = array_values(array_filter($dedup, fn($u) => basename($u) !== basename($out['image_url'])));
				// Si encontramos _med de la principal, usarla como principal
				$mainBase = basename($out['image_url']);
				if (isset($byBase[$mainBase]) && $rank($byBase[$mainBase]) > $rank($out['image_url'])) {
					$out['image_url'] = $byBase[$mainBase];
				}
				$out['image_urls'] = array_merge([$out['image_url']], $rest);
			}
		}
	}
	if ($out['description'] === '' && preg_match('#<meta[^>]+property="og:description"[^>]+content="([^"]+)"#i', $html, $m)) {
		$out['description'] = trim($m[1]);
	}
	if ($out['name'] === '' && preg_match('#<title>([^<]+)</title>#is', $html, $m)) {
		$out['name'] = trim(preg_replace('/\s*-\s*Material de buceo.*$/i', '', $m[1]));
	}
	return $out;
}

/** Prefijo común máximo (para extraer etiqueta de variante a partir de nombres). */
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

/** Busca o crea un products_options_values asociado a la opción "Modelo" (id=3). Schema sin AUTO_INCREMENT → MAX+1. */
function findOrCreateOptionValue($mysqli, $label) {
	$qLabel = $mysqli->real_escape_string($label);
	$r = $mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov
		JOIN products_options_values_to_products_options pv2po ON pv2po.products_options_values_id = pov.products_options_values_id
		WHERE pov.products_options_values_name = '$qLabel' AND pov.language_id = " . LANG_ID_ES . "
		  AND pv2po.products_options_id = 3 LIMIT 1");
	if ($row = $r->fetch_assoc()) return (int) $row['products_options_values_id'];
	$rid = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
	$newId = (int) ($rid->fetch_assoc()['nid'] ?? 1);
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$qLabel', '')");
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$qLabel', '')");
	$mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (3, $newId)");
	return $newId;
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA; ése es el que debe acabar en .X0/.X5. */
function roundToNickel($net) {
	$wi = ((float) $net) * (1 + VAT_RATE);
	$r  = round($wi * 20) / 20;
	return round($r / (1 + VAT_RATE), 4);
}

function calcG1Price($price, $cost) {
	$price = (float) $price; $cost = (float) $cost;
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

/** Devuelve id de la categoría sub bajo NEW_CATEGORY_ID con ese nombre, creándola si no existe. */
function findOrCreateSubCategory($mysqli, $name, $dryRun, &$cache) {
	$key = strtoupper(trim($name));
	if ($key === '') return null;
	if (isset($cache[$key])) return $cache[$key];
	$qkey = $mysqli->real_escape_string($name);
	$r = $mysqli->query("SELECT cd.categories_id FROM categories_description cd
		JOIN categories c ON c.categories_id=cd.categories_id
		WHERE cd.language_id=" . LANG_ID_ES . " AND UPPER(TRIM(cd.categories_name))='" . strtoupper($qkey) . "'
		  AND c.parent_id=" . NEW_CATEGORY_ID . " LIMIT 1");
	if ($row = $r->fetch_assoc()) { $cache[$key] = (int) $row['categories_id']; return $cache[$key]; }
	if ($dryRun) { $cache[$key] = 0; return 0; }
	// Tabla categories tiene varias columnas NOT NULL sin DEFAULT (id_ebay, incremento_ebay, categories_ebay, customer_group_ebay, CODIG) y la de status se llama "categories_status".
	// categories_status = 0 (desactivada) — el usuario revisa y activa manualmente cuando esté contenta.
	$ok = $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, categories_status, CODIG, id_ebay, incremento_ebay, categories_ebay, customer_group_ebay) VALUES (" . NEW_CATEGORY_ID . ", 0, NOW(), 0, 0, 0, 0, 0, 0)");
	if (!$ok) { logMsg("  ERROR creando sub-categoría '$name': " . $mysqli->error); $cache[$key] = 0; return 0; }
	$catId = (int) $mysqli->insert_id;
	$mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($catId, " . LANG_ID_ES . ", '$qkey')");
	$mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($catId, " . LANG_ID_EN . ", '$qkey')");
	$cache[$key] = $catId;
	logMsg("  Sub-categoría creada: '$name' (id=$catId)");
	return $catId;
}

/**
 * Lee la hoja "Gestor de condiciones" y devuelve mapa de celdas calculables.
 * Solo necesitamos D34 según el sample. Si las fórmulas de J cambian fila a fila, leemos varias.
 */
function loadDiscountMap($ws) {
	$map = [];
	for ($r = 1; $r <= $ws->getHighestRow(); $r++) {
		for ($col = 1; $col <= 5; $col++) {
			$letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
			$v = $ws->getCell($letter . $r)->getCalculatedValue();
			if (is_numeric($v)) $map[$letter . $r] = (float) $v;
		}
	}
	return $map;
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
<table style="width:100%;"><tr><td><div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Importador Cressi — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p><a href="<?php echo tep_href_link('import-cressi-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
	. ($skipScrape ? " (sin scraping)" : "")
	. ($skipTranslation ? " (sin traducción LLM)" : "")
	. ($max > 0 ? ", max=$max inserts" : ""));

if (!file_exists(CRESSI_XLSM)) { logMsg("ERROR: xlsm no encontrado: " . CRESSI_XLSM); goto end_action; }

logMsg("Cargando xlsm…");
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(CRESSI_XLSM);
$reader->setReadDataOnly(true);
$wb = $reader->load(CRESSI_XLSM);
$ws = $wb->getSheetByName('LISTA DE PRECIOS');
if (!$ws) { logMsg("ERROR: hoja 'LISTA DE PRECIOS' no encontrada"); goto end_action; }
$wsCond = $wb->getSheetByName('Gestor de condiciones');
$discountMap = $wsCond ? loadDiscountMap($wsCond) : [];
logMsg("Hoja 'LISTA DE PRECIOS' cargada: " . $ws->getHighestRow() . " filas. Gestor: " . count($discountMap) . " celdas numéricas.");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$mysqli->set_charset('utf8');
if (!$dryRun && !is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

// Resolver/crear manufacturer Cressi
$cressiMfgId = 0;
$r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))='CRESSI' LIMIT 1");
if ($row = $r->fetch_assoc()) {
	$cressiMfgId = (int) $row['manufacturers_id'];
} else {
	if (!$dryRun) {
		$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('Cressi', NOW())");
		$cressiMfgId = (int) $mysqli->insert_id;
		$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($cressiMfgId, " . LANG_ID_ES . ", '')");
		$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($cressiMfgId, " . LANG_ID_EN . ", '')");
		logMsg("Manufacturer 'Cressi' creado (id=$cressiMfgId)");
	} else {
		logMsg("Manufacturer 'Cressi' se crearía (no existe).");
	}
}

$catCache = [];
$inserted = 0; $skippedExisting = 0; $skippedNoEan = 0; $skippedAtelier = 0;
$skippedNoSku = 0; $skippedNoName = 0; $skippedBadPrice = 0; $skippedNoSection = 0;
$skippedPending = 0; $skippedNoImg = 0;
$imgOk = 0; $imgFail = 0; $imgNoUrl = 0;
$scrapeOk = 0; $scrapeFail = 0; $scrapePending = 0;
$formatOk = 0; $formatFail = 0;
$translateFail = 0; $errors = 0;
$variantsCreated = 0; $groupsAsVariants = 0;

// Pasada 1: leer todas las filas válidas a memoria con su sección.
logMsg("Pasada 1: leyendo filas y aplicando filtros…");
$rows = [];
$currentSection = '';
$highestRow = $ws->getHighestRow();
for ($rowIdx = 4; $rowIdx <= $highestRow; $rowIdx++) {
	$ref       = trim((string) $ws->getCell("A$rowIdx")->getValue());
	$ean       = trim((string) $ws->getCell("B$rowIdx")->getValue());
	$atelier   = trim((string) $ws->getCell("E$rowIdx")->getValue());
	$nombre    = trim((string) $ws->getCell("F$rowIdx")->getValue());
	$pvmayorRaw= $ws->getCell("I$rowIdx")->getCalculatedValue();
	$pvpRaw    = $ws->getCell("M$rowIdx")->getCalculatedValue();
	$urlFicha  = trim((string) $ws->getCell("O$rowIdx")->getValue());
	if ($ref === '') continue;

	$pvmayorNum = is_numeric($pvmayorRaw) ? (float) $pvmayorRaw : null;
	$pvpConIva  = is_numeric($pvpRaw)     ? (float) $pvpRaw     : null;
	$pvpSinIva  = $pvpConIva !== null ? round($pvpConIva / IVA_ES, 4) : null;
	$looksLikeSection = ($ean === '' && $pvmayorNum === null && $pvpSinIva === null && $nombre === '');
	if ($looksLikeSection) { $currentSection = $ref; continue; }
	if ($ref === 'Referencia') continue;

	$atelierUpper = mb_strtoupper(trim($atelier), 'UTF-8');
	if (!in_array($atelierUpper, ['OPEN', 'TÉCNICO', 'TECNICO', 'SPECIALIZED'], true)) { $skippedAtelier++; continue; }
	if ($ean === '')     { $skippedNoEan++; continue; }
	if ($nombre === '')  { $skippedNoName++; continue; }
	if ($pvpSinIva === null || $pvpSinIva < 0) { $skippedBadPrice++; logMsg("  SKIP precio inválido: ref=$ref"); continue; }

	$costCalc = $ws->getCell("K$rowIdx")->getCalculatedValue();
	$cost = is_numeric($costCalc) ? (float) $costCalc : null;
	if ($cost === null && $pvmayorNum !== null) {
		$jFormula = (string) $ws->getCell("J$rowIdx")->getValue();
		if (preg_match("/'Gestor de condiciones'!\\\$?([A-Z]+)\\\$?(\d+)/", $jFormula, $m)) {
			$key = $m[1] . $m[2];
			if (isset($discountMap[$key])) $cost = $pvmayorNum * (100 - $discountMap[$key]) / 100;
		}
	}
	if ($cost === null || $cost <= 0) $cost = $pvmayorNum ?? 0;

	$rows[] = [
		'ref' => $ref, 'ean' => $ean, 'name' => $nombre, 'section' => $currentSection,
		'pvp_sin_iva' => $pvpSinIva, 'pvp_con_iva' => $pvpConIva, 'cost' => $cost, 'url' => $urlFicha,
	];
}
logMsg("Filas válidas leídas: " . count($rows));

// Pasada 2: agrupar por URL Cressi (productos con misma URL = variantes del mismo producto).
$groups = [];
$singles = [];
foreach ($rows as $row) {
	if ($row['url'] !== '') $groups[$row['url']][] = $row;
	else $singles[] = $row;
}
$multiVariantGroups = array_filter($groups, fn($g) => count($g) >= 2);
$singleProductGroups = array_filter($groups, fn($g) => count($g) === 1);
logMsg("Agrupado: " . count($multiVariantGroups) . " grupos con variantes, " . (count($singleProductGroups) + count($singles)) . " productos sueltos.");

// Filtro por codes específicos: solo grupos/sueltos que contienen al menos un ref pedido
if (!empty($onlyCodes)) {
	$multiF = [];
	foreach ($multiVariantGroups as $url => $variants) {
		foreach ($variants as $v) {
			if (isset($onlyCodes[$v['ref']])) { $multiF[$url] = $variants; break; }
		}
	}
	$singleF = [];
	foreach ($singleProductGroups as $url => $arr) {
		if (isset($onlyCodes[$arr[0]['ref']])) $singleF[$url] = $arr;
	}
	$singlesF = [];
	foreach ($singles as $row) {
		if (isset($onlyCodes[$row['ref']])) $singlesF[] = $row;
	}
	$multiVariantGroups = $multiF;
	$singleProductGroups = $singleF;
	$singles = $singlesF;
	logMsg("Filtro por codes específicos: " . count($multiVariantGroups) . " grupos-variante + " . (count($singleProductGroups) + count($singles)) . " sueltos");
}

// Helper para procesar un producto SUELTO (1 fila) o el "principal" de un grupo
$processProduct = function ($row, $isVariantParent, $variantsList) use (
	$mysqli, $cressiMfgId, $skipScrape, $skipTranslation, $dryRun,
	&$catCache, &$inserted, &$skippedExisting, &$skippedPending, &$skippedNoImg, &$skippedNoSection,
	&$imgOk, &$imgFail, &$imgNoUrl, &$scrapeOk, &$scrapeFail, &$scrapePending,
	&$formatOk, &$formatFail, &$translateFail, &$errors, &$variantsCreated, &$groupsAsVariants
) {
	$ref = $row['ref']; $ean = $row['ean']; $nombre = $row['name'];
	$section = $row['section']; $url = $row['url'];
	$priceMain = roundToNickel($row['pvp_sin_iva']); $costMain = $row['cost'];

	// SKIP si CUALQUIER ref del grupo ya existe
	$refsToCheck = $isVariantParent ? array_column($variantsList, 'ref') : [$ref];
	$eansToCheck = $isVariantParent ? array_filter(array_column($variantsList, 'ean')) : array_filter([$ean]);
	foreach ($refsToCheck as $r) {
		$exId = findExistingProductId($mysqli, $r, $eansToCheck[array_search($r, $refsToCheck)] ?? '');
		if ($exId) {
			$skippedExisting++;
			if ($skippedExisting <= 25) logMsg("  SKIP existente: ref=$r → pid=$exId" . ($isVariantParent ? " (en grupo $url)" : ""));
			return;
		}
	}

	$subCatId = $section !== '' ? findOrCreateSubCategory($mysqli, $section, $dryRun, $catCache) : null;
	if ($section === '') $skippedNoSection++;

	// Scraping (una sola vez)
	$scrape = ['name'=>'', 'description'=>'', 'image_url'=>'', 'image_urls'=>[], '_status'=>'skipped'];
	if (!$skipScrape && $url !== '') {
		$scrape = scrapeCressiPage($url);
		$status = $scrape['_status'] ?? 'unknown';
		if ($status === 'pending') {
			$skippedPending++; $scrapePending++;
			if ($skippedPending <= 25) logMsg("  SKIP pending: ref=$ref url=$url");
			return;
		}
		if ($status === 'ok' && (!empty($scrape['description']) || !empty($scrape['image_url']))) $scrapeOk++;
		else $scrapeFail++;
	}
	$descEs = $scrape['description'] ?? '';
	$imageUrl = $scrape['image_url'] ?? '';
	$imageUrls = $scrape['image_urls'] ?? [];

	if (!$skipScrape && $imageUrl === '') {
		$skippedNoImg++;
		if ($skippedNoImg <= 25) logMsg("  SKIP sin imagen: ref=$ref" . ($url === '' ? " (sin URL)" : ""));
		return;
	}

	$descEs = stripCressiBoilerplate($descEs);
	if (!$skipTranslation && !$dryRun && $descEs !== '') {
		$f = llmFormatHtml($descEs);
		if ($f !== '') { $descEs = $f; $formatOk++; } else $formatFail++;
	}

	// Nombre del producto principal:
	//   - Si grupo de variantes: nombre del scraping (ld+json) si existe; sino prefijo común; sino primer nombre.
	//   - Si suelto: el nombre tal cual del xlsm.
	if ($isVariantParent) {
		$variantNames = array_column($variantsList, 'name');
		$prefix = trim(longestCommonPrefix($variantNames), " -–·,");
		if (mb_strlen($prefix, "UTF-8") >= 4) {
			$mainName = $prefix;
		} else {
			$mainName = trim($scrape['name'] ?? '') ?: $variantNames[0];
		}
	} else {
		$mainName = $nombre;
	}
	// Componer nombre final: "Cressi {SingularSección} {nombreLimpio}" — quitando "T/" residual
	$mainName = cleanCressiSizeMarker($mainName);
	$singular = $section !== '' ? cressiSingular($section) : '';
	$mainName = trim('Cressi ' . ($singular !== '' ? $singular . ' ' : '') . $mainName);
	$nameEs = mb_substr($mainName, 0, PRODUCT_NAME_MAX, 'UTF-8');
	$nameEn = $nameEs;
	$descEn = $descEs;

	if (!$skipTranslation && !$dryRun && $descEs !== '') {
		$td = llmTranslate($descEs);
		if ($td !== '') $descEn = $td; else $translateFail++;
	}

	if ($descEs !== '') $descEs = forceUlSingleColumn(stripCressiBoilerplate($descEs));
	if ($descEn !== '') $descEn = forceUlSingleColumn(stripCressiBoilerplate($descEn));

	$g1Main = roundToNickel(calcG1Price($priceMain, $costMain));
	$weight = 1.0;

	if ($dryRun) {
		$inserted++;
		if ($isVariantParent) {
			$groupsAsVariants++;
			$variantsCreated += count($variantsList);
			if ($inserted <= 12) logMsg("  WOULD INSERT GROUP " . count($variantsList) . " variantes | section='$section' name='" . mb_substr($nameEs, 0, 50, 'UTF-8') . "' url=$url");
		} else {
			if ($inserted <= 12) logMsg("  WOULD INSERT solo ref=$ref name='" . mb_substr($nameEs, 0, 50, 'UTF-8') . "' price=$priceMain cost=" . round($costMain, 2));
		}
		return;
	}

	// Descargar imágenes (una sola vez)
	$imgFilenameMain = ''; $subImgTmpFiles = [];
	if ($imageUrl !== '') {
		$slug = cressiSlugify($nameEs);
		$tmpMain = IMG_ABS_DIR . $slug . '-tmp-' . uniqid() . '.jpg';
		if (downloadImage($imageUrl, $tmpMain)) { $imgOk++; $imgFilenameMain = $tmpMain; }
		else { $imgFail++; logMsg("  IMG fail: ref=$ref"); }
		$rest = array_slice(array_filter($imageUrls, fn($u) => $u !== $imageUrl), 0, 7);
		foreach ($rest as $i => $u) {
			$tmpSub = IMG_ABS_DIR . $slug . '-tmp-' . uniqid() . '-' . ($i + 2) . '.jpg';
			if (downloadImage($u, $tmpSub)) $subImgTmpFiles[] = $tmpSub;
		}
	} else { $imgNoUrl++; }

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($ref);
		$qean = $mysqli->real_escape_string(isValidEan13($ean) ? $ean : '');
		$qmfg = (int) $cressiMfgId;
		$mysqli->query("INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, '$qmodel', '', " . number_format($priceMain, 4, '.', '') . ", " . number_format($costMain, 4, '.', '') . ", NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", $qmfg, '$qean', '$qmodel', '" . ORIGIN_FLAG . "')");
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($nameEn);
		$qDescEn = $mysqli->real_escape_string($descEn);
		$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", '$qNameEs', '$qDescEs', 0)");
		$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", '$qNameEn', '$qDescEn', 0)");

		$assignCat = $subCatId ?: NEW_CATEGORY_ID;
		$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, $assignCat)");

		$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($g1Main, 4, '.', '') . ", 1, 1)");

		// Variantes: insertar todas como products_attributes (incluida la "más barata" con delta=0)
		if ($isVariantParent) {
			// De-dup por ref: el xlsx de Cressi a veces repite la misma fila SKU
			$seenRefs = [];
			$variantsList = array_values(array_filter($variantsList, function($v) use (&$seenRefs) {
				$k = strtolower(trim($v['ref']));
				if ($k === '' || isset($seenRefs[$k])) return false;
				$seenRefs[$k] = true;
				return true;
			}));
			$variantNames = array_column($variantsList, 'name');
			$prefix = trim(longestCommonPrefix($variantNames), " -–·,");
			// Track de OVs ya consumidos por este producto (guardia anti-colisión de labels)
			$ovsUsados = [];
			foreach ($variantsList as $v) {
				$labelRaw = trim(mb_substr($v['name'], mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
				$labelRaw = trim($labelRaw, " -–·,");
				if ($labelRaw === '') $labelRaw = $v['name'];
				$labelRaw = cleanCressiSizeMarker($labelRaw);
				$labelRaw = mb_substr($labelRaw, 0, 64, 'UTF-8');
				$ovId = findOrCreateOptionValue($mysqli, $labelRaw);
				if (isset($ovsUsados[$ovId])) {
					$labelDis = mb_substr($labelRaw . ' (' . trim($v['ref']) . ')', 0, 64, 'UTF-8');
					$ovId = findOrCreateOptionValue($mysqli, $labelDis);
				}
				$ovsUsados[$ovId] = true;

				// Precio variante absoluto redondeado a .05 con IVA, delta sobre el padre redondeado
				$varPriceR = roundToNickel($v['pvp_sin_iva']);
				$delta = round($varPriceR - $priceMain, 4);
				$pricePrefix = $delta < 0 ? '-' : '+';
				$qVRef = $mysqli->real_escape_string($v['ref']);
				$qVEan = $mysqli->real_escape_string(isValidEan13($v['ean']) ? $v['ean'] : '');
				$mysqli->query("INSERT INTO products_attributes SET
					products_id=$pid, options_id=3, options_values_id=$ovId,
					options_values_price=" . number_format(abs($delta), 4, '.', '') . ",
					price_prefix='$pricePrefix',
					reference='$qVRef', reference_prov='$qVRef',
					products_attributes_ean='$qVEan',
					options_values_weight=0, weight_prefix='+'");
				$paId = (int) $mysqli->insert_id;

				// G1 atributo: redondeado y delta sobre G1 principal
				$g1Var = roundToNickel(calcG1Price($varPriceR, $v['cost']));
				$deltaG1 = round($g1Var - $g1Main, 4);
				$g1Prefix = $deltaG1 < 0 ? '-' : '+';
				$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($deltaG1), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')");
				$variantsCreated++;
			}
			$groupsAsVariants++;
		}

		// Imagen principal
		if ($imgFilenameMain !== '' && file_exists($imgFilenameMain)) {
			$slug = cressiSlugify($nameEs);
			$finalName = $slug . '-' . $pid . '.jpg';
			if (@rename($imgFilenameMain, IMG_ABS_DIR . $finalName)) {
				$mysqli->query("UPDATE products SET products_image='" . $mysqli->real_escape_string($finalName) . "' WHERE products_id=$pid");
			} else { @unlink($imgFilenameMain); }
		}
		// Subimágenes: guardar como JSON array en products_subimages (que es lo que admin/frontend leen)
		$subFiles = [];
		foreach ($subImgTmpFiles as $i => $tmp) {
			if (!file_exists($tmp)) continue;
			$idx = $i + 2; // numeración -2, -3, -4...
			$slug = cressiSlugify($nameEs);
			$finalName = $slug . '-' . $pid . '-' . $idx . '.jpg';
			if (@rename($tmp, IMG_ABS_DIR . $finalName)) {
				$subFiles[] = $finalName;
			} else { @unlink($tmp); }
		}
		if (!empty($subFiles)) {
			$json = $mysqli->real_escape_string(json_encode($subFiles, JSON_UNESCAPED_UNICODE));
			$mysqli->query("UPDATE products SET products_subimages='$json' WHERE products_id=$pid");
		}

		// EAN interno solo si el principal no lo tenía válido
		if ($qean === '') {
			$gen = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($gen !== '') $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($gen) . "' WHERE products_id=$pid");
		}

		$mysqli->commit();
		$inserted++;
		if ($isVariantParent) {
			logMsg("  INSERT pid=$pid GROUP " . count($variantsList) . " variantes name='" . mb_substr($nameEs, 0, 50, 'UTF-8') . "'");
		} else {
			logMsg("  INSERT pid=$pid ref=$ref name='" . mb_substr($nameEs, 0, 50, 'UTF-8') . "' price=$priceMain g1=$g1Main");
		}
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("  ERROR ref=$ref → " . $e->getMessage());
	}
};

// Pasada 3: procesar cada grupo (con variantes) y cada producto suelto
foreach ($multiVariantGroups as $url => $variants) {
	if ($max > 0 && $inserted >= $max) break;
	usort($variants, fn($a, $b) => $a['pvp_sin_iva'] <=> $b['pvp_sin_iva']);
	$processProduct($variants[0], true, $variants);
}
foreach ($singleProductGroups as $url => $arr) {
	if ($max > 0 && $inserted >= $max) break;
	$processProduct($arr[0], false, []);
}
foreach ($singles as $row) {
	if ($max > 0 && $inserted >= $max) break;
	$processProduct($row, false, []);
}


logMsg("");
logMsg("==================== RESUMEN ====================");
logMsg("Insertadas: $inserted");
logMsg("SKIP existentes: $skippedExisting");
logMsg("SKIP Atelier (col E fuera de Open/Técnico/Specialized): $skippedAtelier");
logMsg("SKIP sin EAN: $skippedNoEan");
logMsg("SKIP ficha pendiente (URL→/en-construccion): $skippedPending");
logMsg("SKIP sin imagen disponible: $skippedNoImg");
logMsg("SKIP sin nombre: $skippedNoName");
logMsg("SKIP precio inválido: $skippedBadPrice");
logMsg("AVISO sin sección activa: $skippedNoSection");
logMsg("Imágenes OK: $imgOk | fail: $imgFail | sin URL: $imgNoUrl");
logMsg("Scraping OK: $scrapeOk | fail: $scrapeFail (en-construccion ya contado en SKIP pending)");
logMsg("LLM maquetado OK: $formatOk | fail: $formatFail");
logMsg("LLM traducciones fallidas: $translateFail");
logMsg("Errores INSERT: $errors");

end_action:
?>
	</div>
	<p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-cressi-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
	<h2>Importador Cressi (altas)</h2>
	<p>Lee <code><?php echo CRESSI_XLSM; ?></code> e inserta en <strong><?php echo NEW_CATEGORY_ID; ?> (Cressi Nuevos)</strong> + sub-categorías por sección. Productos en <em>pendiente revisión</em> (status=2, check_stock=0).</p>
	<form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Refs específicas</strong> (opcional, ignora "Inserts máximos"; si la ref es variante, importa todo el grupo):<br>
			<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Una o varias refs Cressi separadas por coma, espacio o salto de línea."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
		</p>
		<p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
		<p><label><input type="checkbox" name="skip_scrape" value="1"> Saltar scraping de cressi.es (sin descripción ni imagen)</label></p>
		<p><label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción LLM ES→EN</label></p>
		<p>Inserts máximos por ejecución (0 = sin límite): <input type="number" name="max" value="50" min="0" style="width:80px;"></p>
		<button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad?');">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Filtros aplicados (decisión usuario):</strong><br>
		- <strong>Skip Atelier</strong>: filas con columna E (Atelier Specialized) NO vacía se omiten.<br>
		- <strong>Skip sin EAN</strong>: filas sin código EAN en columna B se omiten.<br>
		<br>
		<strong>Reglas:</strong><br>
		- <code>products_cost</code> ← Precio neto (col K calculado o I × (1−Dto/100)).<br>
		- <code>products_price</code> ← P.V.P. Recomendado España (col M, viene con IVA) <strong>÷ 1.21</strong> (BD guarda sin IVA; el sistema añade el IVA al cliente).<br>
		- G1 con tiers + piso <code>cost × <?php echo G1_FLOOR_FACTOR; ?></code>.<br>
		- Categoría: 1717 + sub-categoría por sección del xlsm (REGULADORES, ALETAS, etc.).<br>
		- Scraping de cressi.es para descripción (Schema.org/Product en ld+json) e imagen principal (og:image).<br>
		- Traducción ES→EN del descriptivo vía LLM. Nombre del modelo se mantiene igual en ambos idiomas.<br>
		- EAN del feed: si válido, se conserva. Si no (raro), se genera interno con prefijo 23.<br>
		- Output streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
