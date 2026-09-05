<?php
/**
 * Traduce a INGLÉS las descripciones/nombres EN (lang=1) de productos Garmin que están en español
 * (porque el scrape del portal devolvió contenido ES y el importador antiguo no traducía ES→EN).
 *
 * MODO STRICT (default): solo procesa si name_en === name_es y desc_en === desc_es (no hay edición manual).
 * MODO LOOSE (con flag LOOSE): procesa cualquier campo que esté en español (riesgo de pisar ediciones manuales).
 *
 * Uso:
 *   php garmin_translate_to_en.php DRY              # solo lista (modo STRICT)
 *   php garmin_translate_to_en.php DRY LOOSE        # lista en modo LOOSE
 *   php garmin_translate_to_en.php                  # ejecuta STRICT
 *   php garmin_translate_to_en.php LOOSE            # ejecuta LOOSE (cuidado, puede pisar ediciones)
 *   php garmin_translate_to_en.php PID 361307       # solo ese pid (LOOSE implícito)
 */
include '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

$dryRun  = in_array('DRY',   $argv ?? [], true);
$loose   = in_array('LOOSE', $argv ?? [], true);
$onlyPids = [];
$pidArg = array_search('PID', $argv ?? [], true);
if ($pidArg !== false) {
	for ($i = $pidArg + 1; $i < count($argv); $i++) {
		if (ctype_digit($argv[$i])) $onlyPids[] = (int) $argv[$i];
	}
	$loose = true; // si especificas PIDs explícitos, asumes el riesgo
}

const LLM_PROMPT_ES_EN = 'You are a professional Spanish→English translator specialised in marine electronics, GPS, smartwatches, autopilots, plotters and marine audio (Garmin, Fusion, JL Audio, Clarion). Use precise English technical terminology. The input is HTML — preserve all tags (<p>, <ul>, <li>, <strong>, <h3>, <h4>) untouched, only translate the text content. Keep brand names, model names and units untranslated (Garmin, Fusion, GPS, BLUETOOTH, AMOLED, mm, kg, mAh, etc.). Reply ONLY with the translated HTML, no comments.';

function garminIsSpanish($text) {
	$t = mb_strtolower((string) $text, 'UTF-8');
	if ($t === '') return true;
	if (preg_match('/[ñ¿¡]/u', $t)) return true;
	if (preg_match('/\b(para|con|los|las|del|cuando|también|según|incluye|disfruta|náuti|montaje|venden|pares|amarre)\b/u', $t)) return true;
	if (preg_match('/\b(the|with|and|your|for|that|provides|features|allows|including|easily|navigation|sold|pairs)\b/i', $t)) return false;
	$accents = preg_match_all('/[áéíóúÁÉÍÓÚüÜ]/u', $t);
	return $accents > 1;
}

function llmCall($sys, $user, $maxRetries = 3, $maxTokens = 2500) {
	if (trim((string) $user) === '') return '';
	$payload = json_encode(['model' => 'qwen36-sakamaki-nvfp4', 'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], 'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20, 'max_tokens' => $maxTokens, 'chat_template_kwargs' => ['enable_thinking' => false]], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init('http://217.127.199.171:28001/v1/chat/completions');
		curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
		$r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
		if ($r !== false && $code === 200) {
			$j = json_decode($r, true);
			$c = $j['choices'][0]['message']['content'] ?? null;
			if (is_string($c) && trim($c) !== '') return trim($c);
		}
		usleep(800000);
	}
	return '';
}

$where = "p.products_import_origin='garmin'";
if ($onlyPids) $where .= " AND p.products_id IN (" . implode(',', $onlyPids) . ")";

$r = $m->query("
SELECT p.products_id, p.products_model,
  pde.products_name AS name_en, pde.products_description AS desc_en,
  pds.products_name AS name_es, pds.products_description AS desc_es
FROM products p
JOIN products_description pde ON pde.products_id=p.products_id AND pde.language_id=1
JOIN products_description pds ON pds.products_id=p.products_id AND pds.language_id=3
WHERE $where ORDER BY p.products_id");

$candidates = [];
while ($row = $r->fetch_assoc()) {
	$nameNeeds = false; $descNeeds = false;
	// Nombre: si EN está en español
	if (garminIsSpanish($row['name_en'])) {
		if ($loose) $nameNeeds = true;
		elseif ($row['name_en'] === $row['name_es']) $nameNeeds = true; // STRICT: solo si idéntico
	}
	// Descripción: si EN está en español
	if (garminIsSpanish($row['desc_en']) && trim($row['desc_en']) !== '') {
		if ($loose) $descNeeds = true;
		elseif ($row['desc_en'] === $row['desc_es']) $descNeeds = true;
	}
	if (!$nameNeeds && !$descNeeds) continue;
	$candidates[] = $row + ['_name_needs' => $nameNeeds, '_desc_needs' => $descNeeds];
}

echo "Modo: " . ($loose ? 'LOOSE (puede pisar ediciones manuales)' : 'STRICT (solo si EN==ES)') . "\n";
echo "Productos a traducir a EN: " . count($candidates) . "\n";
foreach ($candidates as $c) echo "  pid={$c['products_id']} sku={$c['products_model']} | name? " . ($c['_name_needs'] ? 'YES' : 'no') . " | desc? " . ($c['_desc_needs'] ? 'YES' : 'no') . "\n";
if ($dryRun) { echo "\n[DRY] no se aplica nada\n"; exit; }
if (empty($candidates)) exit;

echo "\nProcesando…\n";
$ok = 0; $fail = 0;
foreach ($candidates as $c) {
	$pid = (int) $c['products_id'];
	$updates = [];
	if ($c['_name_needs']) {
		$tn = llmCall(LLM_PROMPT_ES_EN, $c['name_en'], 3, 200);
		if ($tn !== '' && $tn !== $c['name_en']) $updates['name'] = mb_substr($tn, 0, 80, 'UTF-8');
	}
	if ($c['_desc_needs']) {
		$td = llmCall(LLM_PROMPT_ES_EN, $c['desc_en'], 3, 2500);
		if ($td !== '' && $td !== $c['desc_en']) $updates['desc'] = $td;
	}
	if (empty($updates)) { $fail++; echo "  FAIL pid=$pid (LLM no devolvió traducción)\n"; continue; }
	$set = [];
	if (isset($updates['name'])) $set[] = "products_name='" . $m->real_escape_string($updates['name']) . "'";
	if (isset($updates['desc'])) $set[] = "products_description='" . $m->real_escape_string($updates['desc']) . "'";
	if ($m->query("UPDATE products_description SET " . implode(', ', $set) . " WHERE products_id=$pid AND language_id=1")) {
		$ok++;
		echo "  OK pid=$pid sku={$c['products_model']} | " . implode(', ', array_keys($updates)) . "\n";
	} else { $fail++; echo "  ERR pid=$pid: " . $m->error . "\n"; }
}
echo "\n=== RESUMEN: ok=$ok fail=$fail ===\n";
