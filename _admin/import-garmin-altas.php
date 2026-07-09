<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

// ─── Configuración ──────────────────────────────────────────────────────────
const GARMIN_CSV          = '/home/francobordo/public_html/import/Garmin/priceList.csv';
const GARMIN_DEALER_ID    = '18723608';
const GARMIN_USER         = 'f.rodriguez@francobordo.com';
const GARMIN_PASS         = 'Garmin0908';
const GARMIN_SSO_LOGIN    = 'https://sso.garmin.com/sso/signin?service=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F&source=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F';
const GARMIN_CSV_URL      = 'https://dealers.garmin.com/drc/priceList/download/csv?selectedDealerId=' . GARMIN_DEALER_ID;
const GARMIN_COOKIE_FILE  = '/home/francobordo/public_html/import/Garmin/cookies.txt';
const GARMIN_CACHE_DIR    = '/home/francobordo/public_html/import/Garmin/cache/';
const GARMIN_PORTAL_URL   = 'https://dealers.garmin.com/es-ES/partner-portal/product/';
const GARMIN_TABS_URL     = 'https://dealers.garmin.com/partner-portal/product/api/tabs/es_ES/ES/';
const GARMIN_LOCALE       = 'es_ES';
const GARMIN_DEALER_COUNTRY = 'ES';
const SCRAPER_HTTP_TIMEOUT = 15;
const IMG_HTTP_TIMEOUT    = 12;

define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_ID     = 1740;     // "Garmin Nuevos"
const TAX_CLASS_IVA21     = 1;
const TAX_RATE            = 1.21;     // IVA 21% para convertir RSP con IVA → sin IVA
const LANG_ID_ES          = 3;
const LANG_ID_EN          = 1;
const G1_GROUP_ID         = 1;
const G1_FACTOR           = 0.75;     // G1 = price × 0.75 base
const G1_FLOOR_FACTOR     = 1.12;     // G1 piso = cost × 1.12 (margen mínimo 12% sobre coste)
const PRODUCT_NAME_MAX    = 80;
const ORIGIN_FLAG         = 'garmin';
const EAN_INTERNAL_PREFIX = 24;

// LLM (mismo endpoint que Cressi/Trem/FNI)
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT_EN_ES = 'Eres un traductor profesional inglés→español especializado en electrónica náutica, GPS, smartwatches, autopilots, plotters y audio marino (Garmin, Fusion, JL Audio, Clarion). Usa terminología técnica precisa en español de España. La entrada es HTML — preserva todas las etiquetas (<p>, <ul>, <li>, <strong>, <h3>, <h4>) sin tocarlas, traduce SOLO el contenido textual. Mantén nombres de marca, modelos y unidades sin traducir (Garmin, Fusion, GPS, BLUETOOTH, AMOLED, mm, kg, mAh, etc.). Responde SOLO con el HTML traducido, sin comentarios.';
const LLM_PROMPT_ES_EN = 'You are a professional Spanish→English translator specialised in marine electronics, GPS, smartwatches, autopilots, plotters and marine audio (Garmin, Fusion, JL Audio, Clarion). Use precise English technical terminology. The input is HTML — preserve all tags (<p>, <ul>, <li>, <strong>, <h3>, <h4>) untouched, only translate the text content. Keep brand names, model names and units untranslated (Garmin, Fusion, GPS, BLUETOOTH, AMOLED, mm, kg, mAh, etc.). Reply ONLY with the translated HTML, no comments.';
const LLM_PROMPT_EN_ES_TITLE = "Traduces títulos de productos de electrónica náutica y deportiva (Garmin, Fusion, JL Audio, Clarion) de inglés a español de España.\n\nREGLAS:\n1) Mantén SIN TRADUCIR los nombres de marca (Garmin, Fusion, JL Audio, Clarion) y de modelo (Approach, fenix, Instinct, Quatix, Forerunner, Vivoactive, ECHOMAP, GPSMAP, Edge, Tread, Striker, Panoptix, Signature, Tactix, Venu, Lily, Descent, etc.) y los códigos alfanuméricos (G82, UHD2, 75cv, PS22, etc.).\n2) Traduce SOLO las palabras descriptivas genéricas: Watch Bands→Correas, Mount Fixture→Soporte, Mounting→Montaje, Protective Cover→Funda protectora, Flush Mount Kit→Kit de montaje empotrado, Speaker→Altavoz, Antenna→Antena, Cable→Cable, Charger→Cargador, Bracket→Soporte, Bundle→Pack, Holder→Soporte, Plate→Placa, Arm→Brazo, Ball→Bola, Base→Base, Switch→Conmutador, Adapter→Adaptador, Sensor→Sensor, Transducer→Transductor, Replacement→Repuesto, Accessory/Acc→Accesorio, Enclosed→Cerrado, Tower→Torre, Outdoor→Exterior, Grille→Rejilla, White→Blanco, Black→Negro, Thru-hull→Paso de casco, Fitting→Accesorio.\n3) Mantén unidades (mm, kg, GHz, W, etc.) y las comillas de pulgada (\").\n4) Quita los símbolos ®/™/© del resultado.\n5) Responde SOLO con el título traducido, sin comentarios, sin comillas envolventes.";
const LLM_PROMPT_ES_EN_TITLE = "You translate product titles for marine and sports electronics (Garmin, Fusion, JL Audio, Clarion) from Spanish to English.\n\nRULES:\n1) Keep brand names (Garmin, Fusion, JL Audio, Clarion) and model names (Approach, fenix, Instinct, Quatix, Forerunner, Vivoactive, ECHOMAP, GPSMAP, Edge, Tread, Striker, Panoptix, Signature, Tactix, Venu, Lily, Descent, etc.) and alphanumeric codes (G82, UHD2, 75cv, PS22, etc.) UNTRANSLATED.\n2) Translate ONLY generic descriptive words to English (Correas→Watch Bands, Soporte→Mount, Funda protectora→Protective Cover, Kit de montaje empotrado→Flush Mount Kit, Altavoz→Speaker, Antena→Antenna, Cable→Cable, Cargador→Charger, Placa→Plate, Brazo→Arm, Bola→Ball, Conmutador→Switch, Accesorio→Accessory, Cerrado→Enclosed, Torre→Tower, Exterior→Outdoor, Rejilla→Grille, Blanco→White, Negro→Black, Paso de casco→Thru-hull).\n3) Keep units (mm, kg, GHz, W, etc.) and inch marks (\").\n4) Strip ®/™/© symbols from the output.\n5) Reply ONLY with the translated title, no comments, no surrounding quotes.";
const LLM_FORMAT_PROMPT = "Eres un experto en maquetar fichas de producto de electrónica náutica/deportiva (Garmin, Fusion, JL Audio, Clarion). Recibes una descripción comercial y la transformas en HTML legible y atractivo.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto.\n\n2. AGRUPACIÓN POR SECCIONES (OBLIGATORIO si el producto tiene >6 features): clasifica las features en secciones temáticas con <h3>. Ejemplos de secciones típicas:\n   - \"Características principales\"\n   - \"Funciones náuticas\" / \"Funciones golf\" / \"Funciones de fitness\" (según el tipo de producto)\n   - \"Pantalla y autonomía\"\n   - \"Conectividad\"\n   - \"Mapas y navegación\"\n   - \"Salud y forma física\"\n   Cada sección abre con <h3>...</h3> y debajo un <ul><li>...</li></ul>. Si el producto tiene <6 features, una sola lista sin h3.\n\n3. ÉNFASIS CON <strong>: en CADA <li>, identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>. Sigue al concepto con dos puntos \":\" + el resto de la frase. Ejemplo: <li><strong>Distancia precisa hasta la bandera:</strong> vincula el dispositivo con telémetros compatibles para...</li>. NO inventes texto: identifica las palabras clave que ya están en la frase.\n\n4. SECCIÓN \"EN LA CAJA\": si hay contenido de \"En la caja\", maquétalo al final con <h4>En la caja</h4><ul><li>...</li></ul>.\n\n5. PRESERVA enlaces <a href> y <sup> existentes sin tocarlos.\n\n6. NO resumas, NO parafrasees: conserva TODO el texto original. Solo añades estructura HTML, secciones, bold y dos puntos.\n\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

// Reglas de oferta (specials)
const SPECIAL_PVP_THRESHOLD = 100.0;  // PVP CON IVA mínimo para aplicar oferta
const SPECIAL_TIER_30_DISC  = 0.15;   // -15% si margen ≥ 30%
const SPECIAL_TIER_24_DISC  = 0.10;   // -10% si margen > 24% (y < 30% por orden de evaluación)
const SPECIAL_DURATION_DAYS = 30;     // duración por defecto

// Detección de fabricante (orden de match: primer hit gana)
const MFG_GARMIN_ID  = 5;
const MFG_FUSION_ID  = 449;
// JL Audio (id=589) y Clarion (id=590) creados al setup; resolveBy('JL Audio') / resolveBy('Clarion')
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipDownload = isset($_POST['skip_download']) || isset($_GET['skip_download']);
$skipScrape   = isset($_POST['skip_scrape'])   || isset($_GET['skip_scrape']);
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$onlySku = trim($_POST['only_sku'] ?? $_GET['only_sku'] ?? ''); // legacy: un solo SKU
$onlyCodesRaw = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') $onlyCodes[$c] = true;
	}
}
if ($onlySku !== '') $onlyCodes[$onlySku] = true; // unión con el legacy
// Si hay codes específicos, ignoramos $max
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }
$brandFilter = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? ''));
$BRAND_ALLOWED = ['Garmin', 'JL Audio', 'Fusion', 'Clarion', 'Airmar'];
if ($brandFilter !== '' && !in_array($brandFilter, $BRAND_ALLOWED, true)) $brandFilter = '';

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

// ─── Descarga CSV vía SSO ────────────────────────────────────────────────────
function garminCurl($url, $opts = []) {
	$ch = curl_init($url);
	$base = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIEJAR      => GARMIN_COOKIE_FILE,
		CURLOPT_COOKIEFILE     => GARMIN_COOKIE_FILE,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_POSTREDIR      => 7, // mantener POST tras 301/302/303
	];
	curl_setopt_array($ch, $base + $opts);
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	unset($ch);
	return [$body, $code, $err];
}

/** Comprueba si DRC_JWT del cookie file todavía es válido (no expira en los próximos 5 min). */
function garminSessionIsValid() {
	if (!file_exists(GARMIN_COOKIE_FILE)) return false;
	$content = @file_get_contents(GARMIN_COOKIE_FILE);
	if (!preg_match('/DRC_JWT\s+(\S+)/', $content, $m)) return false;
	$jwt = $m[1];
	$parts = explode('.', $jwt);
	if (count($parts) < 2) return false;
	$payload = base64_decode(strtr($parts[1], '-_', '+/'));
	$data = json_decode($payload, true);
	if (!is_array($data) || !isset($data['exp'])) return false;
	return ((int) $data['exp']) > (time() + 300);
}

function downloadGarminCsv($logger = null) {
	if (!is_dir(dirname(GARMIN_CSV))) @mkdir(dirname(GARMIN_CSV), 0775, true);
	// Si la sesión ya es válida, intentar descargar el CSV sin re-login (evita rate-limit 429).
	if (garminSessionIsValid()) {
		if ($logger) $logger("Sesión SSO ya válida, intento descarga directa…");
		[$csv, $code,] = garminCurl(GARMIN_CSV_URL, [CURLOPT_TIMEOUT => 120]);
		if ($code === 200 && is_string($csv) && strpos($csv, '<!doctype html') === false && strpos($csv, '<html') !== 0) {
			$tmp = GARMIN_CSV . '.tmp.' . uniqid();
			file_put_contents($tmp, $csv);
			if (filesize($tmp) > 10000) {
				@rename($tmp, GARMIN_CSV);
				return ['ok' => true, 'size' => filesize(GARMIN_CSV)];
			}
			@unlink($tmp);
		}
		if ($logger) $logger("Descarga directa falló (sesión inválida en server), forzando relogin…");
	}
	@unlink(GARMIN_COOKIE_FILE);

	if ($logger) $logger("SSO 1/3: GET signin (CSRF)…");
	[$html, $code, $err] = garminCurl(GARMIN_SSO_LOGIN);
	if ($code !== 200 || !$html) return ['ok' => false, 'err' => "signin GET: code=$code err=$err"];
	if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) return ['ok' => false, 'err' => 'no _csrf en login form'];
	$csrf = $m[1];

	if ($logger) $logger("SSO 2/3: POST credenciales…");
	[$resp, $code, $err] = garminCurl(GARMIN_SSO_LOGIN, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query([
			'username' => GARMIN_USER,
			'password' => GARMIN_PASS,
			'embed'    => 'false',
			'_csrf'    => $csrf,
		]),
		CURLOPT_HTTPHEADER => [
			'Origin: https://sso.garmin.com',
			'Referer: ' . GARMIN_SSO_LOGIN,
		],
	]);
	if ($code !== 200 || !$resp) return ['ok' => false, 'err' => "credentials POST: code=$code err=$err"];
	if (stripos($resp, '<title>Success</title>') === false) {
		// detectar errores comunes
		if (preg_match('/<div[^>]+class="[^"]*error[^"]*"[^>]*>([^<]+)</i', $resp, $em)) return ['ok' => false, 'err' => 'login fallido: ' . trim($em[1])];
		return ['ok' => false, 'err' => 'login fallido (sin Success)'];
	}
	if (!preg_match('/response_url\s*=\s*"([^"]+)"/', $resp, $tm)) return ['ok' => false, 'err' => 'no response_url en Success'];
	$ticketUrl = stripslashes($tm[1]);

	if ($logger) $logger("SSO 3/3: consumiendo ticket…");
	[, $code, $err] = garminCurl($ticketUrl);
	// código 200 o 404 con cookies establecidas son OK (cookie JWT está lo importante)
	$cookieOk = false;
	if (file_exists(GARMIN_COOKIE_FILE)) {
		$cookieContent = file_get_contents(GARMIN_COOKIE_FILE);
		$cookieOk = strpos($cookieContent, 'DRC_JWT') !== false;
	}
	if (!$cookieOk) return ['ok' => false, 'err' => "ticket no devolvió DRC_JWT (code=$code err=$err)"];

	if ($logger) $logger("Descargando priceList CSV…");
	[$csv, $code, $err] = garminCurl(GARMIN_CSV_URL, [CURLOPT_TIMEOUT => 120]);
	if ($code !== 200 || !$csv) return ['ok' => false, 'err' => "CSV download: code=$code err=$err"];
	if (strpos($csv, '<!doctype html') !== false || strpos($csv, '<html') === 0) return ['ok' => false, 'err' => 'CSV download devolvió HTML (auth perdida)'];

	$tmp = GARMIN_CSV . '.tmp.' . uniqid();
	file_put_contents($tmp, $csv);
	if (filesize($tmp) < 10000) { @unlink($tmp); return ['ok' => false, 'err' => 'CSV demasiado pequeño (' . filesize($tmp) . ' bytes)']; }
	@rename($tmp, GARMIN_CSV);
	return ['ok' => true, 'size' => filesize(GARMIN_CSV)];
}

// ─── Scraping del portal: window.AppData.bootstrap + tabs API ────────────────

/** Descarga (cached) el HTML de la página de producto del portal Garmin.
 *  Validación: el HTML debe contener "var AppData" o "window.AppData"; si no, se descarta el cache
 *  (probablemente sesión SSO expirada cuando se guardó → HTML genérico de login sin datos).
 */
function garminFetchProductHtml($sku) {
	if (!is_dir(GARMIN_CACHE_DIR)) @mkdir(GARMIN_CACHE_DIR, 0775, true);
	$safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $sku);
	$cacheFile = GARMIN_CACHE_DIR . $safe . '.html';
	if (file_exists($cacheFile) && filesize($cacheFile) > 5000) {
		$html = @file_get_contents($cacheFile);
		if (is_string($html) && (strpos($html, 'var AppData') !== false || strpos($html, 'window.AppData') !== false)) return $html;
		@unlink($cacheFile); // cache corrupto, refetch
	}
	[$html, $code,] = garminCurl(GARMIN_PORTAL_URL . urlencode($sku), [CURLOPT_TIMEOUT => SCRAPER_HTTP_TIMEOUT]);
	if ($code !== 200 || !is_string($html) || strlen($html) < 5000) return false;
	if (strpos($html, 'var AppData') === false && strpos($html, 'window.AppData') === false) return false; // login redirect
	@file_put_contents($cacheFile, $html);
	return $html;
}

/** Descarga (cached) el JSON de tabs (overview + en-la-caja). */
function garminFetchTabsJson($sku) {
	$safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $sku);
	$cacheFile = GARMIN_CACHE_DIR . $safe . '.tabs.json';
	if (file_exists($cacheFile) && filesize($cacheFile) > 50) {
		$j = json_decode(@file_get_contents($cacheFile), true);
		if (is_array($j)) return $j;
	}
	[$body, $code,] = garminCurl(GARMIN_TABS_URL . urlencode($sku), [
		CURLOPT_TIMEOUT => SCRAPER_HTTP_TIMEOUT,
		CURLOPT_HTTPHEADER => ['Accept: application/json', 'Referer: ' . GARMIN_PORTAL_URL . urlencode($sku)],
	]);
	if ($code !== 200 || !is_string($body)) return null;
	$j = json_decode($body, true);
	if (!is_array($j)) return null;
	@file_put_contents($cacheFile, $body);
	return $j;
}

/** Extrae window.AppData.bootstrap del HTML usando un parser balanceado de llaves. */
function garminExtractBootstrap($html) {
	if (!is_string($html)) return null;
	// localizar "bootstrap: {"
	if (!preg_match('/\bbootstrap\s*:\s*(\{)/', $html, $m, PREG_OFFSET_CAPTURE)) return null;
	$start = $m[1][1]; // posición de la llave abierta
	$depth = 0; $inStr = false; $esc = false; $end = -1;
	$len = strlen($html);
	for ($i = $start; $i < $len; $i++) {
		$c = $html[$i];
		if ($inStr) {
			if ($esc) { $esc = false; continue; }
			if ($c === '\\') { $esc = true; continue; }
			if ($c === '"') $inStr = false;
			continue;
		}
		if ($c === '"') { $inStr = true; continue; }
		if ($c === '{') $depth++;
		elseif ($c === '}') {
			$depth--;
			if ($depth === 0) { $end = $i; break; }
		}
	}
	if ($end < 0) return null;
	$json = substr($html, $start, $end - $start + 1);
	$data = json_decode($json, true);
	return is_array($data) ? $data : null;
}

/**
 * Devuelve estructura limpia con todo lo extraído del scraping para un SKU:
 *   ['name','desc_features','in_box','image_url','gallery','weight','ean','origin']
 * Cualquier campo puede faltar o estar vacío.
 */
function garminScrapeProduct($sku) {
	$out = ['name' => '', 'desc_features' => [], 'in_box' => '', 'image_url' => '', 'gallery' => [], 'weight' => null, 'ean' => '', 'origin' => ''];
	$html = garminFetchProductHtml($sku);
	if (!$html) return $out;
	$bs = garminExtractBootstrap($html);
	if (is_array($bs)) {
		$es = $bs['esProduct'] ?? [];
		if (!empty($es['name'])) $out['name'] = $es['name'];
		if (!empty($es['mainMediumImagePath'])) $out['image_url'] = preg_replace('/-md(\.\w+)$/i', '-xl$1', $es['mainMediumImagePath']); // -xl = alta calidad
		if (!empty($bs['gallery']) && is_array($bs['gallery'])) {
			foreach ($bs['gallery'] as $g) {
				if (!empty($g['publicId'])) $out['gallery'][] = $g['publicId'];
			}
		}
		$prod = $bs['item']['product'] ?? [];
		if (isset($prod['unitWeight']) && is_numeric($prod['unitWeight'])) $out['weight'] = (float) $prod['unitWeight'];
		if (!empty($prod['countryOrigin'])) $out['origin'] = $prod['countryOrigin'];
		if (!empty($prod['crossReferences']) && is_array($prod['crossReferences'])) {
			foreach ($prod['crossReferences'] as $cr) {
				if (($cr['crossReferenceType'] ?? '') === 'EAN' && !empty($cr['crossRefValue'])) {
					$out['ean'] = $cr['crossRefValue']; break;
				}
			}
		}
	}
	$tabs = garminFetchTabsJson($sku);
	if (is_array($tabs)) {
		$ov = $tabs['overviewTab'] ?? '';
		// Extraer atributos text="..." y description="..." de los Vue components
		$features = [];
		if (preg_match_all('/(?:^|\s)(?:text|description|headline|content|subtitle|title|body)\s*=\s*"([^"]+)"/u', $ov, $mm)) {
			foreach ($mm[1] as $v) {
				$v = trim($v);
				// Filtrar shorts/keys/templates ({{...}}) e i18n keys (TODO_MAYUSCULAS_CON_GUIONES_BAJOS)
				if (mb_strlen($v, 'UTF-8') < 15) continue;
				if (preg_match('/^\{\{/', $v)) continue;
				if (preg_match('/^[A-Z_]+$/', $v)) continue;
				$features[] = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			}
		}
		// Dedup conservando orden
		$out['desc_features'] = array_values(array_unique($features));
		$inBox = $tabs['inTheBoxTab'] ?? '';
		if ($inBox !== '') $out['in_box'] = $inBox;
	}
	return $out;
}

/**
 * Compone descripción HTML para products_description a partir del scrape.
 * Estructura: párrafo principal + lista de features + "En la caja".
 */
function garminBuildDescriptionHtml(array $info, $fallbackName) {
	$parts = [];
	$features = $info['desc_features'] ?? [];
	if (!empty($features)) {
		// Primer feature: descripción intro (suele ser el más largo y descriptivo)
		usort($features, fn($a, $b) => mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'));
		$intro = array_shift($features);
		$parts[] = '<p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>';
		if (!empty($features)) {
			// Resto como bullets (top 20 para no saturar)
			$rest = array_slice($features, 0, 20);
			$parts[] = '<ul>' . implode('', array_map(
				fn($f) => '<li>' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '</li>',
				$rest
			)) . '</ul>';
		}
	} else {
		$parts[] = '<p>' . htmlspecialchars($fallbackName, ENT_QUOTES, 'UTF-8') . '</p>';
	}
	if (!empty($info['in_box'])) {
		// in_box ya viene en HTML <ul><li>...</li></ul>
		$parts[] = '<h4>En la caja</h4>' . trim($info['in_box']);
	}
	return implode("\n", $parts);
}

/**
 * Detecta si un texto está en español. Heurística simple:
 *  - presencia de caracteres únicos del español (ñ, ¿, ¡)
 *  - presencia de palabras frecuentes en ES
 *  - >0 caracteres acentuados específicos
 * Devuelve true si parece ES, false si parece EN.
 */
function garminIsSpanish($text) {
	$t = mb_strtolower((string) $text, 'UTF-8');
	if ($t === '') return true; // no hay nada que traducir
	if (preg_match('/[ñ¿¡]/u', $t)) return true;
	if (preg_match('/\b(para|con|los|las|del|cuando|también|según|incluye|disfruta|náuti)\b/u', $t)) return true;
	// Detectar palabras inglesas comunes que NO existen en ES
	if (preg_match('/\b(the|with|and|your|for|that|provides|features|allows|including|easily|navigation)\b/i', $t)) return false;
	// Si tiene muchos caracteres acentuados → ES; si pocos → EN
	$accents = preg_match_all('/[áéíóúÁÉÍÓÚüÜ]/u', $t);
	return $accents > 1;
}

function llmCallGarmin($systemPrompt, $userText, $maxRetries = 3, $maxTokens = 2500) {
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
	], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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

function llmTranslateEnEs($html)   { return llmCallGarmin(LLM_PROMPT_EN_ES, $html, 2, 2500); }
function llmTranslateEsEn($html)   { return llmCallGarmin(LLM_PROMPT_ES_EN, $html, 2, 2500); }
function llmTranslateTitleEnEs($t) { return llmCallGarmin(LLM_PROMPT_EN_ES_TITLE, $t, 3, 200); }
function llmTranslateTitleEsEn($t) { return llmCallGarmin(LLM_PROMPT_ES_EN_TITLE, $t, 3, 200); }
function llmFormatGarminHtml($raw) { return llmCallGarmin(LLM_FORMAT_PROMPT, $raw, 2, 2500); }

/** Construye URLs de gallery en tamaño medio a partir de publicIds del bootstrap. */
function garminGalleryUrls(array $publicIds) {
	$urls = [];
	foreach ($publicIds as $pid) {
		// publicId típico: "Product_Images/es_ES/products/010-02904-51/v/cf-xl"
		// Construir URL: https://res.garmin.com/<resto>.jpg manteniendo "-xl" (alta calidad)
		$rel = preg_replace('#^Product_Images/#', '', $pid);
		$urls[] = 'https://res.garmin.com/' . $rel . '.jpg';
	}
	return array_values(array_unique(array_filter($urls)));
}

function garminDownloadImage($url, $destAbs) {
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
	$ok = $ok && $code === 200 && filesize($destAbs) > 100;
	if (!$ok) @unlink($destAbs);
	return $ok;
}

// ─── Helpers ────────────────────────────────────────────────────────────────
function garminParseEuroNum($v) {
	$v = trim((string) $v);
	if ($v === '') return null;
	// quita BOM y caracteres invisibles
	$v = preg_replace('/[^0-9,.\-]/', '', $v);
	if ($v === '' || $v === '-') return null;
	// Casos:
	//   "1.234,56" → 1234.56 (miles + decimal europeo)
	//   "12,99"    → 12.99   (decimal coma)
	//   "1.049"    → 1049    (miles europeo, SIN decimales) — heurística: \d{1,3}\.\d{3}
	//   "0.25"     → 0.25    (decimal punto, p.ej. peso "0,25 KG" tras quitar KG ya viene como "0,25")
	if (substr_count($v, ',') === 1 && substr_count($v, '.') >= 1) {
		$v = str_replace('.', '', $v);
		$v = str_replace(',', '.', $v);
	} elseif (strpos($v, ',') !== false) {
		$v = str_replace(',', '.', $v);
	} elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {
		// solo puntos como separadores de miles ("1.049", "12.345", "1.234.567")
		$v = str_replace('.', '', $v);
	}
	return is_numeric($v) ? (float) $v : null;
}

function garminParseWeight($v) {
	// "0,06 KG" → 0.06
	$v = strtolower(trim((string) $v));
	$v = preg_replace('/\s*kg\s*$/', '', $v);
	$n = garminParseEuroNum($v);
	return ($n !== null && $n >= 0) ? $n : null;
}

/** Quita marcas registradas (®, ™, ©) y normaliza espacios. */
function garminCleanTitle($text) {
	$t = (string) $text;
	$t = str_replace(["\u{00AE}", "\u{2122}", "\u{00A9}", '®', '™', '©'], '', $t);
	$t = preg_replace('/\s{2,}/u', ' ', $t);
	return trim($t);
}

function garminSlugify($text, $maxLen = 50) {
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

/**
 * Detecta manufacturer del Product Name (case-insensitive).
 * Orden: JL Audio > Fusion > Clarion > Garmin (default).
 * Devuelve manufacturers_id resolviendo desde BD.
 */
function detectManufacturerId($mysqli, $productName, &$cache) {
	if (empty($cache)) {
		$cache = ['JL AUDIO' => 0, 'FUSION' => 0, 'CLARION' => 0, 'GARMIN' => 0];
		foreach (array_keys($cache) as $k) {
			$qk = $mysqli->real_escape_string($k);
			$r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))='$qk' LIMIT 1");
			if ($r && $row = $r->fetch_assoc()) $cache[$k] = (int) $row['manufacturers_id'];
		}
	}
	$up = strtoupper((string) $productName);
	// `®` rompe \b por ser non-word; usamos \b de inicio + (boundary | ®) al final.
	if (preg_match('/\bJL\s+AUDIO(?:®|\b)/iu', $up) && $cache['JL AUDIO']) return $cache['JL AUDIO'];
	if (preg_match('/\bFUSION(?:®|\b)/iu', $up)     && $cache['FUSION'])   return $cache['FUSION'];
	if (preg_match('/\bCLARION(?:®|\b)/iu', $up)    && $cache['CLARION'])  return $cache['CLARION'];
	return $cache['GARMIN'] ?: MFG_GARMIN_ID;
}

/**
 * Etiqueta de marca derivada del NOMBRE (no del manufacturers_id).
 * Permite filtrar/agrupar por marcas que no tienen entry propia en `manufacturers`
 * (ej. Airmar — se inserta bajo manufacturer Garmin pero se identifica como Airmar).
 * Orden: Airmar > JL Audio > Fusion > Clarion > Garmin (default).
 */
function detectBrandLabel($productName) {
	$up = strtoupper((string) $productName);
	if (preg_match('/\bAIRMAR(?:®|\b)/iu', $up))    return 'Airmar';
	if (preg_match('/\bJL\s+AUDIO(?:®|\b)/iu', $up)) return 'JL Audio';
	if (preg_match('/\bFUSION(?:®|\b)/iu', $up))    return 'Fusion';
	if (preg_match('/\bCLARION(?:®|\b)/iu', $up))   return 'Clarion';
	return 'Garmin';
}

/** Mapa de duplicados — FILTRADO por marcas Garmin (fix 2026-05-11).
 *  Garmin importa 4 manufacturers: Garmin(5), Fusion(449), JL Audio(589), Clarion(590).
 *  Un SKU en otra marca distinta NO bloquea el alta. EAN sigue siendo global (GS1). */
function buildExistingMap($mysqli) {
	$existing = [];
	$f = "(p.manufacturers_id IN (5, 449, 589, 590) OR p.products_import_origin LIKE 'garmin%')";
	$r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	// EAN existentes — globales (GS1)
	$r = $mysqli->query("SELECT product_ean FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL AND LENGTH(product_ean)=13");
	while ($row = $r->fetch_assoc()) $existing['ean:' . $row['product_ean']] = true;
	// Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
	require_once dirname(__FILE__) . '/includes/import_blacklist.php';
	$existing += fb_blacklist_keys();
	return $existing;
}

function calcG1Price($price, $cost) {
	$price = (float) $price; $cost = (float) $cost;
	if ($price <= 0) return 0.0;
	$base  = $price * G1_FACTOR;
	$floor = $cost  * G1_FLOOR_FACTOR;
	return round(max($base, $floor), 4);
}

/**
 * Calcula el precio especial (SIN IVA) para products_groups customers_group_id=0 (todos los clientes).
 * - Solo si PVP CON IVA > SPECIAL_PVP_THRESHOLD (100€).
 * - Margen sobre venta = (price - cost) / price (price y cost ya sin IVA).
 *   - margen ≥ 30%        → 15% off
 *   - margen > 24% y <30% → 10% off
 *   - margen ≤ 24%        → no aplicar
 * Devuelve null si no aplica, o float (precio especial sin IVA) si aplica.
 */
function calcSpecialPrice($priceNoIva, $cost, $rspWithIva) {
	if ($rspWithIva <= SPECIAL_PVP_THRESHOLD) return null;
	if ($priceNoIva <= 0 || $cost <= 0) return null;
	$margin = ($priceNoIva - $cost) / $priceNoIva;
	if      ($margin >= 0.30) $disc = SPECIAL_TIER_30_DISC;
	elseif  ($margin >  0.24) $disc = SPECIAL_TIER_24_DISC;
	else                       return null;
	return roundToNickel($priceNoIva * (1 - $disc));
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA; ése es el que debe acabar en .X0/.X5. */
function roundToNickel($net) {
	$wi = ((float) $net) * TAX_RATE;
	$r  = round($wi * 20) / 20;
	return round($r / TAX_RATE, 4);
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

/** Lee el CSV (con BOM, comillas, decimal coma). Devuelve array de filas asociativas. */
function loadCsvRows($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	// salta línea 1 (disclaimer) y línea 2 (cabecera). PHP 8.5 requiere $escape explícito.
	fgetcsv($f, 0, ',', '"', ''); // disclaimer
	$header = fgetcsv($f, 0, ',', '"', '');
	if (!$header) { fclose($f); return []; }
	// eliminar BOM
	$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
	$rows = [];
	while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
		if (count($r) < 2) continue;
		$row = [];
		foreach ($header as $i => $h) $row[trim($h)] = $r[$i] ?? '';
		if (empty(trim($row['ItemNo/Part Number'] ?? ''))) continue;
		$rows[] = $row;
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
	if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Importador Garmin — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('import-garmin-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
	. ($brandFilter !== '' ? " | filtro marca='$brandFilter'" : "")
	. ($skipDownload ? " (sin descargar CSV del SSO)" : "")
	. ($max > 0 ? ", max=$max" : ""));

if (!$skipDownload) {
	logMsg("Descargando priceList desde dealers.garmin.com (SSO)…");
	$dl = downloadGarminCsv(fn($m) => logMsg("  $m"));
	if (!$dl['ok']) {
		logMsg("ERROR descarga: " . $dl['err'] . " — uso copia local si existe");
		if (!file_exists(GARMIN_CSV)) { logMsg("ERROR: no hay copia local"); goto end_action; }
	} else {
		logMsg("  ✓ CSV descargado: " . round($dl['size'] / 1024) . " KB (mtime " . date('Y-m-d H:i', filemtime(GARMIN_CSV)) . ")");
	}
} else {
	if (!file_exists(GARMIN_CSV)) { logMsg("ERROR: no hay CSV local y skip_download=1"); goto end_action; }
	// Si vamos a hacer scraping necesitamos sesión SSO activa.
	// Solo re-login si el JWT actual está expirado o no existe (evita rate-limit 429 de Garmin).
	if (!$skipScrape) {
		if (garminSessionIsValid()) {
			logMsg("Sesión SSO ya válida (DRC_JWT no caducado) — sin relogin.");
		} else {
			logMsg("Sesión SSO caducada o ausente, refrescando…");
			$dl = downloadGarminCsv(fn($m) => logMsg("  $m"));
			if (!$dl['ok']) logMsg("AVISO sesión SSO: " . $dl['err'] . " — el scraping puede fallar");
			else logMsg("  ✓ sesión activa, CSV: " . round($dl['size'] / 1024) . " KB");
		}
	}
}

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

logMsg("Leyendo CSV…");
$rows = loadCsvRows(GARMIN_CSV);
logMsg("Filas con SKU: " . count($rows));

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias");

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }

$mfgCache = [];
$nInserted = $nSkippedExisting = $nSkippedNoPrice = $nSkippedNoName = $nSkippedBrand = 0;
$nSpecial10 = $nSpecial15 = 0;
$nWithImg = $nWithSubImg = $nWithDesc = 0;
$translateFail = $formatFail = 0;
$processed = $errors = 0;
$mfgStats = ['Garmin' => 0, 'Fusion' => 0, 'JL Audio' => 0, 'Clarion' => 0, 'Airmar' => 0];

foreach ($rows as $row) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }
	$processed++;
	$sku = trim($row['ItemNo/Part Number']);
	$name = trim($row['Product Name']);
	if ($sku === '') continue;
	if (!empty($onlyCodes) && !isset($onlyCodes[$sku])) continue;
	if ($name === '') { $nSkippedNoName++; continue; }
	if (isset($existing[strtolower($sku)])) { $nSkippedExisting++; continue; }

	$ean = trim($row['EAN'] ?? '');
	if ($ean !== '' && isset($existing['ean:' . $ean])) { $nSkippedExisting++; continue; }

	$rspWithIva = garminParseEuroNum($row['RSP Price Inc Vat'] ?? '');
	$dealerCost = garminParseEuroNum($row['Dealer Price'] ?? '');
	if ($rspWithIva === null || $rspWithIva <= 0) { $nSkippedNoPrice++; continue; }
	if ($dealerCost === null || $dealerCost <= 0) { $nSkippedNoPrice++; continue; }

	$cost  = $dealerCost;                    // Dealer Price (sin IVA)
	$price = round($rspWithIva / TAX_RATE, 4); // PVP con IVA = RSP EXACTO del CSV (Garmin ya da un PVP terminado en ,99; NO redondear a .05). G1 y specials SÍ se redondean.
	$g1    = roundToNickel(calcG1Price($price, $cost));
	$weight = garminParseWeight($row['Netweight'] ?? '');
	if ($weight === null || $weight <= 0) $weight = 1.0;
	$specialPrice = calcSpecialPrice($price, $cost, $rspWithIva);

	$mfgId = detectManufacturerId($mysqli, $name, $mfgCache);
	$brandLabel = detectBrandLabel($name); // etiqueta UI (incluye Airmar, que se asigna a manufacturer Garmin)
	$mfgStats[$brandLabel] = ($mfgStats[$brandLabel] ?? 0) + 1;
	if ($brandFilter !== '' && $brandLabel !== $brandFilter) { $nSkippedBrand++; continue; }

	// Enriquecimiento web: nombre real (con acentos), peso, EAN, descripción larga, imagen
	$scrape = $skipScrape ? ['name'=>'','desc_features'=>[],'in_box'=>'','image_url'=>'','gallery'=>[],'weight'=>null,'ean'=>'','origin'=>''] : garminScrapeProduct($sku);
	if (!empty($scrape['name']) && mb_strlen($scrape['name'], 'UTF-8') > mb_strlen($name, 'UTF-8')) $name = $scrape['name']; // portal solo gana si es más largo (evita perder descriptor del CSV detrás de coma)
	if (!empty($scrape['ean']) && empty($ean)) $ean = $scrape['ean'];
	if ($scrape['weight'] !== null && $scrape['weight'] > 0) $weight = $scrape['weight'];

	// Limpieza de marcas registradas (®, ™, ©) en el título
	// PERO recordamos si tenía marca registrada para decidir si traducir el título o no.
	$nameHadTrademark = preg_match('/[®™]/u', (string) $name) === 1;
	$nameClean = garminCleanTitle($name);
	$descRaw   = garminBuildDescriptionHtml($scrape, $nameClean);

	// Traducción EN→ES si hace falta y maquetado al estilo Cressi
	$nameEs = $nameClean;
	$nameEn = $nameClean;
	$descEs = $descRaw;
	$descEn = $descRaw;
	if (!$skipTranslation && !$dryRun && $descRaw !== '') {
		// Detectar idioma del scrape: ES o EN. Garmin sirve descripciones en uno u otro idioma según el producto/locale.
		$descIsSpanish = garminIsSpanish($descRaw);
		$nameIsSpanish = garminIsSpanish($nameClean);

		if (!$descIsSpanish) {
			// scrape en EN → traducir a ES; mantener EN tal cual
			$tr = llmTranslateEnEs($descRaw);
			if ($tr !== '') $descEs = $tr; else $translateFail++;
		} else {
			// scrape en ES → traducir a EN; mantener ES tal cual
			$tr = llmTranslateEsEn($descRaw);
			if ($tr !== '') $descEn = $tr; else $translateFail++;
		}

		// Nombre: traducir SIEMPRE al idioma "contrario" del scrape (incluso si tiene ®/™).
		// El prompt de título preserva nombres de marca y modelo (Approach, fenix, ECHOMAP, etc.) y quita ®/™.
		// Antes había un guard `$nameHadTrademark` que bloqueaba toda traducción si había ® — pero como casi todos los
		// nombres de Garmin/Fusion/JL Audio/Clarion llevan ® en la marca de empresa, ese guard dejaba sin traducir
		// los descriptivos (Watch Bands, Mount Fixture, Flush Mount Kits…). El prompt nuevo distingue marca de descriptivo.
		if (mb_strlen($nameClean, 'UTF-8') > 3) {
			if (!$nameIsSpanish) {
				$tn = llmTranslateTitleEnEs($nameClean);
				if ($tn !== '' && $tn !== $nameClean) $nameEs = mb_substr(garminCleanTitle($tn), 0, PRODUCT_NAME_MAX, 'UTF-8');
			} else {
				$tn = llmTranslateTitleEsEn($nameClean);
				if ($tn !== '' && $tn !== $nameClean) $nameEn = mb_substr(garminCleanTitle($tn), 0, PRODUCT_NAME_MAX, 'UTF-8');
			}
		}

		// Maquetado HTML estilo Cressi (solo aplica al ES; el EN queda con el HTML básico que viene de garminBuildDescriptionHtml o de la traducción ES→EN)
		$fmt = llmFormatGarminHtml($descEs);
		if ($fmt !== '') $descEs = $fmt; else $formatFail++;
	}
	$nameTrunc = mb_substr($nameEs, 0, PRODUCT_NAME_MAX, 'UTF-8');
	$nameEnTrunc = mb_substr($nameEn, 0, PRODUCT_NAME_MAX, 'UTF-8');

	if ($dryRun) {
		$nInserted++;
		if ($specialPrice !== null) { $disc = round((1 - $specialPrice / $price) * 100); ($disc >= 12 ? $nSpecial15++ : $nSpecial10++); }
		if ($nInserted <= 10) {
			$specStr = $specialPrice !== null ? sprintf(' SPECIAL %.2f€ (-%.0f%%)', $specialPrice, round((1 - $specialPrice / $price) * 100)) : '';
			logMsg(sprintf("  WOULD INSERT sku=%s [%s] cost=%.2f price=%.2f g1=%.2f rspIVA=%.2f%s name=\"%s\"",
				$sku, $brandLabel, $cost, $price, $g1, $rspWithIva, $specStr, mb_substr($nameClean, 0, 60, 'UTF-8')));
		}
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($sku);
		$qean   = $mysqli->real_escape_string($ean);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($price, 4, '.', '') . ", " . number_format($cost, 4, '.', '') . ", NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameTrunc);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($nameEnTrunc);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($g1, 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		// Specials (oferta para todos los clientes, customers_group_id=0)
		if ($specialPrice !== null) {
			$expires = date('Y-m-d H:i:s', strtotime('+' . SPECIAL_DURATION_DAYS . ' days'));
			$disc = round((1 - $specialPrice / $price) * 100);
			$sqlSp = "INSERT INTO specials (products_id, specials_new_products_price, specials_date_added, specials_last_modified, expires_date, expires_repeat, status, customers_group_id, start_date) VALUES ($pid, " . number_format($specialPrice, 4, '.', '') . ", NOW(), NULL, '$expires', 1, 1, 0, NOW())";
			if (!$mysqli->query($sqlSp)) throw new Exception("specials: " . $mysqli->error);
			if ($disc >= 12) $nSpecial15++; else $nSpecial10++;
		}

		if (count($scrape['desc_features'] ?? []) > 0) $nWithDesc++;

		// Imágenes: principal + hasta 7 sub-imágenes desde la galería
		$slug = garminSlugify($nameTrunc);
		$mainOk = false;
		// La gallery viene toda en -xl (alta calidad). La 1ª (vista cf) es la principal.
		// Usarla como principal evita: (a) baja calidad de mainMediumImagePath (-md), y
		// (b) duplicado cuando el cf-xl trae un UUID distinto al de mainMediumImagePath.
		$galleryUrls = !empty($scrape['gallery']) ? garminGalleryUrls($scrape['gallery']) : [];
		$mainUrl = !empty($galleryUrls) ? $galleryUrls[0] : ($scrape['image_url'] ?? '');
		if ($mainUrl !== '') {
			$finalName = $slug . '-' . $pid . '.jpg';
			$finalAbs = IMG_ABS_DIR . $finalName;
			if (garminDownloadImage($mainUrl, $finalAbs)) {
				$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($finalName) . "\" WHERE products_id=$pid");
				$nWithImg++; $mainOk = true;
			}
		}
		// Sub-imágenes: el resto de la gallery (la 1ª ya es la principal). Sin duplicar.
		$rest = array_slice($galleryUrls, 1, 7);
		$subFiles = [];
		foreach ($rest as $i => $u) {
			$subName = $slug . '-' . $pid . '-' . ($i + 2) . '.jpg';
			$subAbs  = IMG_ABS_DIR . $subName;
			if (garminDownloadImage($u, $subAbs)) $subFiles[] = $subName;
		}
		if (!empty($subFiles)) {
			$json = $mysqli->real_escape_string(json_encode($subFiles, JSON_UNESCAPED_UNICODE));
			$mysqli->query("UPDATE products SET products_subimages='$json' WHERE products_id=$pid");
			$nWithSubImg++;
		}

		$mysqli->commit();

		// EAN interno si el del feed es vacío o checksum inválido
		if (!isValidEan13($ean)) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++;
		$specStr = $specialPrice !== null ? sprintf(' [special %.2f€]', $specialPrice) : '';
		logMsg("INSERT pid=$pid sku=$sku [$brandLabel] price=$price cost=$cost g1=$g1 rspIVA=$rspWithIva$specStr");
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR sku=$sku → " . $e->getMessage());
	}
}

logMsg("==================== RESUMEN ====================");
logMsg("Procesadas: $processed");
logMsg("Insertadas: $nInserted");
logMsg("SKIP existentes: $nSkippedExisting");
if ($brandFilter !== '') logMsg("SKIP por filtro de marca ('$brandFilter'): $nSkippedBrand");
logMsg("SKIP sin nombre: $nSkippedNoName | sin precio: $nSkippedNoPrice");
logMsg("Errores: $errors");
logMsg("Specials creadas: -10% = $nSpecial10 | -15% = $nSpecial15");
logMsg("Enriquecimiento: con imagen=$nWithImg | con sub-imgs=$nWithSubImg | con descripción=$nWithDesc");
logMsg("LLM: traducciones fallidas=$translateFail | maquetado fallido=$formatFail");
logMsg("Distribución por marca: " . json_encode($mfgStats, JSON_UNESCAPED_UNICODE));

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('import-garmin-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Importador Garmin (altas)</h2>
	<p>
		Descarga vía SSO el priceList CSV de <code>dealers.garmin.com</code> (selectedDealerId=<?php echo GARMIN_DEALER_ID; ?>) e inserta en
		categoría <strong><?php echo NEW_CATEGORY_ID; ?> (Garmin Nuevos)</strong> los SKUs nuevos. Productos como
		<em>pendiente de revisión</em> (status=2, check_stock=0). Sin variantes.
	</p>
	<form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>SKUs específicos</strong> (opcional, ignora "Inserts máximos"):<br>
			<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios SKUs (Part Number) separados por coma, espacio o salto de línea. Ej: 010-12100-11"><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_download" value="1"> No descargar CSV del SSO (usar copia local)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_scrape" value="1"> No scrapear portal (sin descripción larga ni imagen — más rápido)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción/maquetado LLM EN→ES (más rápido, descripción queda en EN)</label>
		</p>
		<p>
			<label>Filtrar por marca:
			<select name="brand" style="padding:4px;">
				<option value="">Todas (auto-detectar)</option>
<?php foreach (['Garmin','JL Audio','Fusion','Clarion','Airmar'] as $__b): ?>
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
		<strong>Reglas aplicadas:</strong><br>
		- <code>products_cost</code> = Dealer Price del CSV (sin IVA).<br>
		- <code>products_price</code> = RSP Price Inc Vat / 1.21 (precio neto sin IVA).<br>
		- <strong>Grupo 1 (Profesionales)</strong>: <code>price × <?php echo G1_FACTOR; ?></code> con piso <code>cost × <?php echo G1_FLOOR_FACTOR; ?></code> (margen mínimo 12% sobre coste).<br>
		- <strong>Oferta automática</strong> (specials, todos los clientes): si PVP CON IVA &gt; <?php echo SPECIAL_PVP_THRESHOLD; ?>€:<br>
		&nbsp;&nbsp;· margen ≥ 30% sobre venta → -<?php echo (int)(SPECIAL_TIER_30_DISC*100); ?>%<br>
		&nbsp;&nbsp;· margen &gt; 24% y &lt; 30% sobre venta → -<?php echo (int)(SPECIAL_TIER_24_DISC*100); ?>%<br>
		&nbsp;&nbsp;· duración: <?php echo SPECIAL_DURATION_DAYS; ?> días.<br>
		- Manufacturer detectado del Product Name: <strong>JL Audio</strong> / <strong>Fusion</strong> / <strong>Clarion</strong> / <strong>Garmin</strong> (default).<br>
		- EAN: si no es EAN-13 válido (checksum) → genera interno con prefijo <strong>24</strong>.<br>
		- Stock NO se toca (status=2 esperando revisión + alta de stock).<br>
		- <strong>Enriquecimiento web</strong> por SKU desde dealers.garmin.com (cache en <code><?php echo GARMIN_CACHE_DIR; ?></code>):<br>
		&nbsp;&nbsp;· Nombre, peso real, EAN: <code>window.AppData.bootstrap</code> embedido en HTML del portal.<br>
		&nbsp;&nbsp;· Descripción larga: <code>/api/tabs/.../</code> (overviewTab features + inTheBoxTab).<br>
		&nbsp;&nbsp;· Imagen principal: <code>res.garmin.com/{locale}/products/{sku}/v/cf-md.jpg</code>.<br>
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
