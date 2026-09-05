<?php
/**
 * Retraduce productos Garmin cuya descripción ES sigue idéntica a la EN (LLM falló al importar).
 * Reusa el LLM EN→ES + maquetado HTML del importador.
 *
 * Uso: php garmin_retranslate.php           # procesa todos los orphans
 *      php garmin_retranslate.php DRY       # solo dry-run (lista, no toca BD)
 *      php garmin_retranslate.php PID 361289 361290   # solo esos pids
 */
include '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

$dryRun = in_array('DRY', $argv ?? [], true);
$onlyPids = [];
$pidArg = array_search('PID', $argv ?? [], true);
if ($pidArg !== false) {
	for ($i = $pidArg + 1; $i < count($argv); $i++) {
		if (ctype_digit($argv[$i])) $onlyPids[] = (int) $argv[$i];
	}
}

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT_EN_ES = 'Eres un traductor profesional inglés→español especializado en electrónica náutica, GPS, smartwatches, autopilots, plotters y audio marino (Garmin, Fusion, JL Audio, Clarion). Usa terminología técnica precisa en español de España. La entrada es HTML — preserva todas las etiquetas (<p>, <ul>, <li>, <strong>, <h3>, <h4>) sin tocarlas, traduce SOLO el contenido textual. Mantén nombres de marca, modelos y unidades sin traducir (Garmin, Fusion, GPS, BLUETOOTH, AMOLED, mm, kg, mAh, etc.). Responde SOLO con el HTML traducido, sin comentarios.';
const LLM_FORMAT_PROMPT = "Eres un experto en maquetar fichas de producto de electrónica náutica/deportiva (Garmin, Fusion, JL Audio, Clarion).\n\nRecibes una descripción comercial como un bloque de texto o HTML simple con bullets. Tu tarea: estructurarla en HTML limpio y legible para una ficha de producto e-commerce.\n\nREGLAS ESTRICTAS:\n\n1. PÁRRAFO INICIAL: el primer <p> es la introducción del producto — la frase más completa y descriptiva del conjunto. 1-2 frases.\n\n2. SECCIONES OPCIONALES: si detectas bloques temáticos (\"Funciones náuticas\", \"Pantalla\", \"Batería\", \"Conectividad\"…) puedes abrirlos con <h3>. Si las features son una lista plana (caso típico), NO uses <h3>.\n\n3. LISTA DE CARACTERÍSTICAS: las features se representan como <ul><li>. Cada <li> es una característica concisa. Usa <strong> al inicio para etiquetar la característica clave (ej. <li><strong>Pantalla AMOLED:</strong> brillante de 1,4″, visible al sol</li>) cuando ayude a escanear visualmente. No abuses del bold.\n\n4. SECCIÓN \"EN LA CAJA\": si recibes contenido de \"En la caja\" (típicamente con bullets de qué incluye), maquétala con <h4>En la caja</h4><ul><li>...</li></ul> al final.\n\n5. NO resumas, NO parafrasees: conserva el contenido completo. Solo añades estructura HTML y bold.\n\n6. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>. NO uses <h1>, <h2>, <br>, <div>, <span>.\n\n7. Salida: SOLO el HTML resultante. Sin markdown, sin comentarios, sin tres-back-ticks.";

function garminIsSpanish($text) {
	$t = mb_strtolower((string) $text, 'UTF-8');
	if ($t === '') return true;
	if (preg_match('/[ñ¿¡]/u', $t)) return true;
	if (preg_match('/\b(para|con|los|las|del|cuando|también|según|incluye|disfruta|náuti)\b/u', $t)) return true;
	if (preg_match('/\b(the|with|and|your|for|that|provides|features|allows|including|easily|navigation)\b/i', $t)) return false;
	$accents = preg_match_all('/[áéíóúÁÉÍÓÚüÜ]/u', $t);
	return $accents > 1;
}

function llmCall($systemPrompt, $userText, $maxRetries = 3, $maxTokens = 2500) {
	if (trim((string) $userText) === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $systemPrompt],
			['role' => 'user',   'content' => $userText],
		],
		'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20,
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
		if ($i < $maxRetries) usleep(800000);
	}
	return '';
}

// Detectar productos garmin con descripción ES idéntica a EN (LLM falló) o nombre idéntico
$where = "p.products_import_origin='garmin'";
if ($onlyPids) $where .= " AND p.products_id IN (" . implode(',', $onlyPids) . ")";
$r = $m->query("
SELECT p.products_id, p.products_model,
  pde.products_name AS name_en, pde.products_description AS desc_en,
  pds.products_name AS name_es, pds.products_description AS desc_es
FROM products p
JOIN products_description pde ON pde.products_id=p.products_id AND pde.language_id=1
JOIN products_description pds ON pds.products_id=p.products_id AND pds.language_id=3
WHERE $where
");
$candidates = [];
while ($row = $r->fetch_assoc()) {
	// Saltar si nombre Y descripción ya parecen ES
	$nameNeeds = ($row['name_en'] === $row['name_es']) && !garminIsSpanish($row['name_en']);
	$descNeeds = ($row['desc_en'] === $row['desc_es']) && !garminIsSpanish($row['desc_en']);
	if (!$nameNeeds && !$descNeeds) continue;
	$candidates[] = $row + ['_name_needs' => $nameNeeds, '_desc_needs' => $descNeeds];
}
echo "Productos Garmin a retraducir: " . count($candidates) . "\n";
if (empty($candidates)) exit;
foreach ($candidates as $c) echo "  pid={$c['products_id']} sku={$c['products_model']} | name? " . ($c['_name_needs'] ? 'YES' : 'no') . " | desc? " . ($c['_desc_needs'] ? 'YES' : 'no') . "\n";

if ($dryRun) { echo "\n[DRY] no se aplica nada\n"; exit; }

echo "\nProcesando…\n";
$ok = 0; $fail = 0;
foreach ($candidates as $c) {
	$pid = (int) $c['products_id'];
	$updates = [];
	if ($c['_name_needs']) {
		$tn = llmCall(LLM_PROMPT_EN_ES, $c['name_en'], 3, 200);
		if ($tn !== '' && $tn !== $c['name_en']) {
			$tn = mb_substr($tn, 0, 80, 'UTF-8');
			$updates['name'] = $tn;
		}
	}
	if ($c['_desc_needs']) {
		// Traducir + maquetar
		$tr = llmCall(LLM_PROMPT_EN_ES, $c['desc_en'], 3, 2500);
		if ($tr !== '') {
			$fmt = llmCall(LLM_FORMAT_PROMPT, $tr, 2, 2500);
			$updates['desc'] = $fmt !== '' ? $fmt : $tr;
		}
	}
	if (empty($updates)) {
		$fail++;
		echo "  FAIL pid=$pid (LLM no devolvió traducción)\n";
		continue;
	}
	$set = [];
	if (isset($updates['name'])) $set[] = "products_name='" . $m->real_escape_string($updates['name']) . "'";
	if (isset($updates['desc'])) $set[] = "products_description='" . $m->real_escape_string($updates['desc']) . "'";
	if ($m->query("UPDATE products_description SET " . implode(', ', $set) . " WHERE products_id=$pid AND language_id=3")) {
		$ok++;
		echo "  OK pid=$pid sku={$c['products_model']} | " . implode(', ', array_keys($updates)) . "\n";
	} else {
		$fail++;
		echo "  ERR pid=$pid: " . $m->error . "\n";
	}
}
echo "\n=== RESUMEN: ok=$ok fail=$fail ===\n";
