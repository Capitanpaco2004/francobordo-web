<?php
/**
 * Retraduce los NOMBRES (products_name) de productos Garmin en ambos sentidos:
 *   - lang=3 (ES) que esté en inglés → traducir EN→ES con prompt de título
 *   - lang=1 (EN) que esté en español → traducir ES→EN con prompt de título
 *
 * Usa LLM_PROMPT_EN_ES_TITLE / LLM_PROMPT_ES_EN_TITLE: preserva marcas (Garmin, Fusion, JL Audio,
 * Clarion) y nombres de modelo (Approach, fenix, Instinct, ECHOMAP, GPSMAP, Forerunner, etc.) +
 * códigos alfanuméricos. Solo traduce los descriptivos (Watch Bands, Mount Fixture, Flush Mount Kits…).
 *
 * MODO STRICT (default): solo procesa si name_es === name_en (no hay edición manual detectable).
 * MODO LOOSE (LOOSE flag): procesa cualquier nombre cuya heurística de idioma diga que está en
 *   el idioma "incorrecto" para su language_id (riesgo de pisar ediciones manuales).
 *
 * Uso:
 *   php garmin_translate_names.php DRY              # solo lista (STRICT)
 *   php garmin_translate_names.php DRY LOOSE        # lista en LOOSE
 *   php garmin_translate_names.php                  # ejecuta STRICT
 *   php garmin_translate_names.php LOOSE            # ejecuta LOOSE
 *   php garmin_translate_names.php PID 361308 ...   # solo esos pids (LOOSE implícito)
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
	$loose = true;
}

const LLM_PROMPT_EN_ES_TITLE = "Traduces títulos de productos de electrónica náutica y deportiva (Garmin, Fusion, JL Audio, Clarion) de inglés a español de España.\n\nREGLAS:\n1) Mantén SIN TRADUCIR los nombres de marca (Garmin, Fusion, JL Audio, Clarion) y de modelo (Approach, fenix, Instinct, Quatix, Forerunner, Vivoactive, ECHOMAP, GPSMAP, Edge, Tread, Striker, Panoptix, Signature, Tactix, Venu, Lily, Descent, etc.) y los códigos alfanuméricos (G82, UHD2, 75cv, PS22, etc.).\n2) Traduce SOLO las palabras descriptivas genéricas: Watch Bands→Correas, Mount Fixture→Soporte, Mounting→Montaje, Protective Cover→Funda protectora, Flush Mount Kit→Kit de montaje empotrado, Speaker→Altavoz, Antenna→Antena, Cable→Cable, Charger→Cargador, Bracket→Soporte, Bundle→Pack, Holder→Soporte, Plate→Placa, Arm→Brazo, Ball→Bola, Base→Base, Switch→Conmutador, Adapter→Adaptador, Sensor→Sensor, Transducer→Transductor, Replacement→Repuesto, Accessory/Acc→Accesorio, Enclosed→Cerrado, Tower→Torre, Outdoor→Exterior, Grille→Rejilla, White→Blanco, Black→Negro, Thru-hull→Paso de casco, Fitting→Accesorio.\n3) Mantén unidades (mm, kg, GHz, W, etc.) y las comillas de pulgada (\").\n4) Quita los símbolos ®/™/© del resultado.\n5) Responde SOLO con el título traducido, sin comentarios, sin comillas envolventes.";
const LLM_PROMPT_ES_EN_TITLE = "You translate product titles for marine and sports electronics (Garmin, Fusion, JL Audio, Clarion) from Spanish to English.\n\nRULES:\n1) Keep brand names (Garmin, Fusion, JL Audio, Clarion) and model names (Approach, fenix, Instinct, Quatix, Forerunner, Vivoactive, ECHOMAP, GPSMAP, Edge, Tread, Striker, Panoptix, Signature, Tactix, Venu, Lily, Descent, etc.) and alphanumeric codes (G82, UHD2, 75cv, PS22, etc.) UNTRANSLATED.\n2) Translate ONLY generic descriptive words to English (Correas→Watch Bands, Soporte→Mount, Funda protectora→Protective Cover, Kit de montaje empotrado→Flush Mount Kit, Altavoz→Speaker, Antena→Antenna, Cable→Cable, Cargador→Charger, Placa→Plate, Brazo→Arm, Bola→Ball, Conmutador→Switch, Accesorio→Accessory, Cerrado→Enclosed, Torre→Tower, Exterior→Outdoor, Rejilla→Grille, Blanco→White, Negro→Black, Paso de casco→Thru-hull).\n3) Keep units (mm, kg, GHz, W, etc.) and inch marks (\").\n4) Strip ®/™/© symbols from the output.\n5) Reply ONLY with the translated title, no comments, no surrounding quotes.";

const PRODUCT_NAME_MAX = 80;

/** Heurística rápida de idioma. Mismo enfoque que en el importador. */
function looksSpanish($text) {
	$t = mb_strtolower((string) $text, 'UTF-8');
	if ($t === '') return null;
	if (preg_match('/[ñ¿¡]/u', $t)) return true;
	// Palabras frecuentes ES (excluyendo ambiguas que existen igual en EN: cable, kit, base, sensor, audio, video)
	if (preg_match('/\b(para|con|los|las|del|cuando|también|según|incluye|disfruta|náuti|montaje|venden|pares|amarre|correa|reloj|pulsera|altavoz|altavoces|antena|funda|soporte|cargador|bater[íi]a|placa|brazo|bola|cerrado|torre|exterior|rejilla|blanco|negro|empotrado|conmutador|accesorio|adaptador|repuesto|protectora|paso de casco|de la|de los|del)\b/u', $t)) return true;
	// Palabras EN exclusivas
	if (preg_match('/\b(the|with|and|your|for|that|provides|features|allows|including|easily|navigation|sold|pairs|mount|mounting|holder|strap|band|charger|battery|case|cover|antenna|enclosed|tower|outdoor|grille|white|black|thru|hull|fitting|switch|protective|adapter|replacement|accessory|fixture|speaker|plate|arm|ball|kit|bundle)\b/i', $t)) return false;
	if (preg_match_all('/[áéíóúÁÉÍÓÚüÜ]/u', $t)) return true;
	return null; // indeterminado (común en nombres muy cortos)
}

function llmCall($sys, $user, $maxRetries = 3, $maxTokens = 200) {
	if (trim((string) $user) === '') return '';
	$payload = json_encode(['model' => 'qwen36-sakamaki-nvfp4', 'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], 'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20, 'max_tokens' => $maxTokens, 'chat_template_kwargs' => ['enable_thinking' => false]], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init('http://217.127.199.171:28001/v1/chat/completions');
		curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
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

/** Quita ®/™/© y normaliza espacios. */
function cleanTitle($t) {
	$t = preg_replace('/[®™©]/u', '', (string) $t);
	$t = preg_replace('/\s+/u', ' ', $t);
	return trim($t);
}

$where = "p.products_import_origin='garmin'";
if ($onlyPids) $where .= " AND p.products_id IN (" . implode(',', $onlyPids) . ")";

$r = $m->query("
SELECT p.products_id, p.products_model,
  pde.products_name AS name_en,
  pds.products_name AS name_es
FROM products p
JOIN products_description pde ON pde.products_id=p.products_id AND pde.language_id=1
JOIN products_description pds ON pds.products_id=p.products_id AND pds.language_id=3
WHERE $where ORDER BY p.products_id");

$candidates = [];
while ($row = $r->fetch_assoc()) {
	$esLooksEn = (looksSpanish($row['name_es']) === false);  // ES está en inglés → traducir a ES
	$enLooksEs = (looksSpanish($row['name_en']) === true);   // EN está en español → traducir a EN

	$esNeeds = false; $enNeeds = false;
	if ($esLooksEn) {
		if ($loose) $esNeeds = true;
		elseif ($row['name_es'] === $row['name_en']) $esNeeds = true;
	}
	if ($enLooksEs) {
		if ($loose) $enNeeds = true;
		elseif ($row['name_es'] === $row['name_en']) $enNeeds = true;
	}
	if (!$esNeeds && !$enNeeds) continue;
	$candidates[] = $row + ['_es_needs' => $esNeeds, '_en_needs' => $enNeeds];
}

echo "Modo: " . ($loose ? 'LOOSE (puede pisar ediciones manuales)' : 'STRICT (solo si name_es==name_en)') . "\n";
echo "Productos a retraducir nombre: " . count($candidates) . "\n";
foreach ($candidates as $c) {
	$tags = [];
	if ($c['_es_needs']) $tags[] = "ES←(EN→ES)";
	if ($c['_en_needs']) $tags[] = "EN←(ES→EN)";
	echo "  pid={$c['products_id']} sku={$c['products_model']} | " . implode(' ', $tags) . "\n";
	echo "      EN: " . substr($c['name_en'], 0, 70) . "\n";
	echo "      ES: " . substr($c['name_es'], 0, 70) . "\n";
}
if ($dryRun) { echo "\n[DRY] no se aplica nada\n"; exit; }
if (empty($candidates)) exit;

echo "\nProcesando…\n";
$ok = 0; $fail = 0;
foreach ($candidates as $c) {
	$pid = (int) $c['products_id'];
	$updates = [];
	if ($c['_es_needs']) {
		$tn = llmCall(LLM_PROMPT_EN_ES_TITLE, $c['name_en'], 3, 200);
		$tn = cleanTitle($tn);
		if ($tn !== '' && $tn !== $c['name_es']) {
			$updates['es'] = ['lang' => 3, 'val' => mb_substr($tn, 0, PRODUCT_NAME_MAX, 'UTF-8'), 'old' => $c['name_es']];
		}
	}
	if ($c['_en_needs']) {
		$tn = llmCall(LLM_PROMPT_ES_EN_TITLE, $c['name_es'], 3, 200);
		$tn = cleanTitle($tn);
		if ($tn !== '' && $tn !== $c['name_en']) {
			$updates['en'] = ['lang' => 1, 'val' => mb_substr($tn, 0, PRODUCT_NAME_MAX, 'UTF-8'), 'old' => $c['name_en']];
		}
	}
	if (empty($updates)) { $fail++; echo "  FAIL pid=$pid (LLM no devolvió traducción válida)\n"; continue; }
	$applied = [];
	foreach ($updates as $k => $u) {
		$qv = $m->real_escape_string($u['val']);
		if ($m->query("UPDATE products_description SET products_name='$qv' WHERE products_id=$pid AND language_id=" . $u['lang'])) {
			$applied[] = "$k: '" . substr($u['old'], 0, 35) . "…' → '" . substr($u['val'], 0, 35) . "…'";
		} else {
			echo "  ERR pid=$pid $k: " . $m->error . "\n";
		}
	}
	if ($applied) { $ok++; echo "  OK pid=$pid sku={$c['products_model']}\n      " . implode("\n      ", $applied) . "\n"; }
	else $fail++;
}
echo "\n=== RESUMEN: ok=$ok fail=$fail ===\n";
