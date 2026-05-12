<?php
/**
 * Detecta valores de `products_options_values` (atributos de productos osculati) que están en italiano
 * y los traduce a ES (lang=3) y EN (lang=1) usando el LLM local.
 *
 * Heurística para "está en italiano":
 *   - El valor (lang=3) NO es solo medida (mm/kg/...).
 *   - Coincide con regex de palabras IT comunes (di, piccolo, grande, nero, blu, verde, rosso, bianco,
 *     senza, con, per, della, dello, dei, delle, degli, della, oscillanti, fissi, alta velocità...).
 *   - O el valor lang=3 es idéntico al lang=1 (síntoma del bug viejo donde no se traducía).
 *
 * Modo DRY (default) lista candidatos. Modo EXECUTE traduce.
 */
include '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

$dry = !in_array('EXECUTE', $argv ?? [], true);
// Opcional: LIMIT N (ej. para test)
$limit = 0;
foreach (($argv ?? []) as $a) if (preg_match('/^LIMIT=(\d+)$/i', $a, $mm)) $limit = (int) $mm[1];

const LLM_URL   = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';

function isJustMeasure($s) {
	$s = trim((string) $s);
	if ($s === '') return true;
	// Quitar todas las unidades comunes y todos los caracteres no alfabéticos.
	// Si tras eso no queda ninguna letra → es solo medida (4 mm, 8 mm x 30 m, 500 W - 12 V, 8/16 mm, Ø 195mm).
	$stripped = preg_replace('/\b(mm|cm|m|km|mt|kg|g|mg|kn|w|kw|v|hp|cv|hz|khz|mhz|ghz|ah|mah|l|ml|n|nm|ø|rpm|°)\b/iu', '', $s);
	$stripped = preg_replace('/[\d\s.,\/\\\\\-+×x*()ø°]+/u', '', $stripped);
	return trim($stripped) === '';
}

/**
 * Una sola llamada LLM que pide traducción IT → {ES, EN} en JSON.
 * Devuelve ['es' => '...', 'en' => '...'] o ['es' => '', 'en' => ''] si falla.
 */
function translateITboth($text) {
	$text = trim((string) $text);
	if ($text === '') return ['es' => '', 'en' => ''];

	$sysPrompt = "You translate Italian product variant labels (marine/fishing items) into Spanish (Spain) AND English. Reply ONLY with a JSON object {\"es\":\"...\",\"en\":\"...\"} — no comments, no markdown. Keep brand names, model codes, units (mm, kg, V, W, m, etc.), colours when they are part of the descriptor (translate Italian colour words: nero/nera→negro/black, bianco/bianca→blanco/white, rosso/rossa→rojo/red, blu→azul/blue, verde→verde/green, giallo/gialla→amarillo/yellow, grigio/grigia→gris/grey, arancio→naranja/orange). Translate descriptive Italian words (con→con/with, senza→sin/without, per→para/for, di→de/of, della→de la/of the, piccolo→pequeño/small, grande→grande/large, doppio→doble/double, singolo→individual/single).";

	$payload = json_encode([
		'model'    => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $sysPrompt],
			['role' => 'user',   'content' => 'Italian: ' . $text],
		],
		'chat_template_kwargs' => ['enable_thinking' => false],
		'max_tokens'  => 200,
		'temperature' => 0.1,
	], JSON_UNESCAPED_UNICODE);
	$ch = curl_init(LLM_URL);
	curl_setopt_array($ch, [
		CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
	]);
	$resp = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($httpCode !== 200 || $resp === false) return ['es' => '', 'en' => ''];
	$data = json_decode($resp, true);
	$content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
	// Quitar markdown fences si los hay
	$content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);
	$json = json_decode($content, true);
	if (!is_array($json)) return ['es' => '', 'en' => ''];
	return ['es' => trim((string) ($json['es'] ?? '')), 'en' => trim((string) ($json['en'] ?? ''))];
}

function isOsculatiCodeOnly($s) {
	$s = trim((string) $s);
	if (preg_match('/^\d+\.\d+\.\d+[A-Z]*$/i', $s)) return true;        // 06.430.08BN
	if (preg_match('/^OEM\s+\d+$/i', $s)) return true;                 // OEM 1213889
	if (preg_match('/^[A-Z]{2,5}\s*\d+[A-Z\-\/\s\d]*$/i', $s)) return true; // BPW S2005-7
	if (preg_match('/^Knott\s/i', $s)) return true;
	return false;
}

// Localiza valores asignados a productos osculati que parecen italianos
// (regex de palabras IT comunes en lang=3, o donde lang=3 == lang=1).
$ITALIAN_REGEX = '\\\\b(di|piccolo|piccola|grande|grandi|medio|media|spessoramento|nero|nera|bianco|bianca|rosso|rossa|verde|verdi|blu|gialla|giallo|maglia|della|dello|dei|delle|degli|con|senza|per|inox|oscillanti|fissi|fisso|alta|velocit|barbotin|campana|cima|cime|a vela|spessore|pesante|leggero|leggera|colore|colori|speciale|doppio|doppia|singolo|singola|doublebraid|fyn|spes|girella|attacchi|raccordi|prolunga|rotolo|matassa|nastro|fascetta|placca|piastra|gancio|moschettone)\\\\b';
$sql = "
SELECT pov_es.products_options_values_id AS ovid,
       pov_es.products_options_values_name AS valor_es,
       pov_en.products_options_values_name AS valor_en
FROM products_options_values pov_es
LEFT JOIN products_options_values pov_en
  ON pov_en.products_options_values_id = pov_es.products_options_values_id
  AND pov_en.language_id = 1
INNER JOIN products_attributes pa ON pa.options_values_id = pov_es.products_options_values_id
INNER JOIN products p ON p.products_id = pa.products_id AND p.products_import_origin LIKE 'osculati%'
WHERE pov_es.language_id = 3
GROUP BY pov_es.products_options_values_id
";
$r = $m->query($sql);
$candidates = [];
while ($row = $r->fetch_assoc()) {
	$valEs = $row['valor_es'];
	if (isJustMeasure($valEs)) continue; // ej. "4 mm" → no traducir
	if (isOsculatiCodeOnly($valEs)) continue; // códigos puros tipo 06.430.08BN, OEM 1213889
	// "Parece italiano": coincide regex italiano OR es exactamente igual al EN
	$looksItalian = preg_match('/' . $ITALIAN_REGEX . '/iu', $valEs)
	             || (mb_strlen(trim($valEs)) > 2 && trim($valEs) === trim((string) $row['valor_en']));
	if (!$looksItalian) continue;
	$candidates[] = $row;
}

echo "Valores de variantes osculati que parecen italianos: " . count($candidates) . "\n";
if ($limit > 0 && count($candidates) > $limit) {
	$candidates = array_slice($candidates, 0, $limit);
	echo "  Limitando a $limit primeros (LIMIT=$limit)\n";
}
foreach ($candidates as $c) {
	echo "  ovid={$c['ovid']} es='{$c['valor_es']}' en='{$c['valor_en']}'\n";
}

if ($dry) { echo "\n[DRY] no se traduce nada. Pasa EXECUTE para aplicar.\n"; exit; }
if (empty($candidates)) exit;

echo "\nTraduciendo…\n";
$ok = 0; $fail = 0;
$tStart = time();
foreach ($candidates as $i => $c) {
	$ovid = (int) $c['ovid'];
	$it   = $c['valor_es']; // texto en italiano (estaba mal etiquetado como ES)
	$tr   = translateITboth($it);
	$es = $tr['es']; $en = $tr['en'];
	if ($es === '' || $en === '') {
		$fail++;
		echo "  [$i] FAIL ovid=$ovid IT='$it'\n";
		continue;
	}
	$es = mb_substr($es, 0, 64, 'UTF-8');
	$en = mb_substr($en, 0, 64, 'UTF-8');
	$qe = $m->real_escape_string($es);
	$qn = $m->real_escape_string($en);
	$ok1 = $m->query("UPDATE products_options_values SET products_options_values_name='$qe' WHERE products_options_values_id=$ovid AND language_id=3");
	$ok2 = $m->query("UPDATE products_options_values SET products_options_values_name='$qn' WHERE products_options_values_id=$ovid AND language_id=1");
	if ($ok1 && $ok2) {
		$ok++;
		if ($ok <= 30 || $ok % 50 === 0) echo "  [$i] OK ovid=$ovid IT='$it' → ES='$es' EN='$en'\n";
	} else {
		$fail++;
		echo "  [$i] ERR ovid=$ovid: " . $m->error . "\n";
	}
}
$elapsed = time() - $tStart;
echo "\n=== RESUMEN: ok=$ok fail=$fail (tiempo: {$elapsed}s) ===\n";
