<?php
/**
 * Re-maqueta descripciones ES (lang=3) de productos Garmin con el LLM_FORMAT_PROMPT actualizado
 * (h3 secciones + <strong>etiqueta:</strong> al inicio de cada <li>).
 *
 * Uso:
 *   php garmin_reformat.php DRY                    # solo lista candidatos
 *   php garmin_reformat.php                        # todos los pids garmin
 *   php garmin_reformat.php PID 361265 361283      # solo esos
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

const LLM_FORMAT_PROMPT = "Eres un experto en maquetar fichas de producto de electrónica náutica/deportiva (Garmin, Fusion, JL Audio, Clarion). Recibes una descripción comercial y la transformas en HTML legible y atractivo.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto.\n\n2. AGRUPACIÓN POR SECCIONES (OBLIGATORIO si el producto tiene >6 features): clasifica las features en secciones temáticas con <h3>. Ejemplos de secciones típicas:\n   - \"Características principales\"\n   - \"Funciones náuticas\" / \"Funciones golf\" / \"Funciones de fitness\" (según el tipo de producto)\n   - \"Pantalla y autonomía\"\n   - \"Conectividad\"\n   - \"Mapas y navegación\"\n   - \"Salud y forma física\"\n   Cada sección abre con <h3>...</h3> y debajo un <ul><li>...</li></ul>. Si el producto tiene <6 features, una sola lista sin h3.\n\n3. ÉNFASIS CON <strong>: en CADA <li>, identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>. Sigue al concepto con dos puntos \":\" + el resto de la frase. Ejemplo: <li><strong>Distancia precisa hasta la bandera:</strong> vincula el dispositivo con telémetros compatibles para...</li>. NO inventes texto: identifica las palabras clave que ya están en la frase.\n\n4. SECCIÓN \"EN LA CAJA\": si hay contenido de \"En la caja\", maquétalo al final con <h4>En la caja</h4><ul><li>...</li></ul>.\n\n5. PRESERVA enlaces <a href> y <sup> existentes sin tocarlos.\n\n6. NO resumas, NO parafrasees: conserva TODO el texto original. Solo añades estructura HTML, secciones, bold y dos puntos.\n\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

const LLM_FORMAT_PROMPT_DENSE = "Eres un experto en maquetar fichas de producto de electrónica náutica/deportiva (Garmin, Fusion, JL Audio, Clarion). Recibes una descripción comercial DENSA (un único párrafo o texto plano sin estructura) y la transformas en HTML legible con párrafo introductorio + lista de características destacables.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: extrae del texto la frase que mejor presenta el producto y úsala como primer <p>. Debe ser corta (máx 25 palabras).\n\n2. EXTRAE FEATURES del resto del texto: identifica cada característica/cualidad/atributo que se menciona y conviértela en un <li>. Si el texto solo menciona 2-3 cualidades, lista esas. Si menciona 5-8, lista todas. NO inventes información que no esté en el texto original.\n\n3. <strong> AL INICIO DE CADA <li>: identifica el concepto clave (1-4 palabras) y envuélvelo en <strong>:</strong>, seguido del resto de la frase. Ejemplo: <li><strong>Acabado anodizado negro:</strong> resistente a la corrosión y a los rayos ultravioleta.</li>.\n\n4. SECCIONES <h3>: SOLO si hay >6 features distintas, agrupa por tema (Construcción, Acabado, Compatibilidad…). Si hay menos, una sola lista sin h3.\n\n5. PRESERVA marcas con ™/® del original, enlaces <a href> y <sup>.\n\n6. CONSERVA toda la información del texto original — puedes reordenar y dividir en bullets pero no omitir hechos.\n\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting product datasheets for marine and sports electronics (Garmin, Fusion, JL Audio, Clarion). You receive a commercial description and transform it into readable, attractive HTML.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: the first <p> is the most complete descriptive sentence about the product.\n\n2. SECTION GROUPING (MANDATORY if the product has >6 features): classify the features into thematic sections with <h3>. Typical section examples:\n   - \"Key features\"\n   - \"Marine functions\" / \"Golf functions\" / \"Fitness functions\" (depending on product type)\n   - \"Display and battery life\"\n   - \"Connectivity\"\n   - \"Maps and navigation\"\n   - \"Health and fitness\"\n   Each section opens with <h3>...</h3> and below a <ul><li>...</li></ul>. If the product has <6 features, a single list without h3.\n\n3. EMPHASIS WITH <strong>: in EACH <li>, identify the key concept (1-4 words at the beginning) and wrap it in <strong>. Follow the concept with a colon \":\" + the rest of the sentence. Example: <li><strong>Precise distance to the flag:</strong> pair the device with compatible rangefinders to...</li>. DO NOT invent text: identify the keywords already in the sentence.\n\n4. \"IN THE BOX\" SECTION: if there is \"In the box\" content, format it at the end with <h4>In the box</h4><ul><li>...</li></ul>.\n\n5. PRESERVE existing <a href> and <sup> links untouched.\n\n6. DO NOT summarise, DO NOT paraphrase: keep ALL the original text. Only add HTML structure, sections, bold and colons.\n\n7. Allowed tags: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Forbidden: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Output: ONLY the HTML, no markdown, no comments, no triple-backticks.";

const LLM_FORMAT_PROMPT_DENSE_EN = "You are an expert in formatting product datasheets for marine and sports electronics (Garmin, Fusion, JL Audio, Clarion). You receive a DENSE commercial description (a single paragraph or plain text without structure) and transform it into readable HTML with an introductory paragraph + list of highlighted features.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: extract from the text the sentence that best presents the product and use it as the first <p>. It must be short (max 25 words).\n\n2. EXTRACT FEATURES from the rest of the text: identify each characteristic/quality/attribute mentioned and turn it into a <li>. If the text only mentions 2-3 qualities, list those. If it mentions 5-8, list them all. DO NOT invent information that is not in the original text.\n\n3. <strong> AT THE START OF EACH <li>: identify the key concept (1-4 words) and wrap it in <strong>:</strong>, followed by the rest of the sentence. Example: <li><strong>Black anodized finish:</strong> corrosion and UV resistant.</li>.\n\n4. <h3> SECTIONS: ONLY if there are >6 distinct features, group by theme (Construction, Finish, Compatibility…). If fewer, a single list without h3.\n\n5. PRESERVE ™/® brand marks from the original, <a href> links and <sup>.\n\n6. KEEP all information from the original text — you may reorder and split into bullets but not omit facts.\n\n7. Allowed tags: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Forbidden: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Output: ONLY the HTML, no markdown, no comments, no triple-backticks.";

function llmCall($sys, $user, $maxRetries = 3, $maxTokens = 4000) {
	$payload = json_encode(['model' => 'qwen36-sakamaki-nvfp4', 'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], 'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20, 'max_tokens' => $maxTokens, 'chat_template_kwargs' => ['enable_thinking' => false]], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init('http://217.127.199.171:28001/v1/chat/completions');
		curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180]);
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

/** Decide modo de maquetado según el HTML actual. Devuelve 'list', 'dense' o null (skip). */
function pickReformatMode($desc) {
	if ($desc === '' || $desc === null) return null;
	if (preg_match('/<h3\b/i', $desc) || preg_match('/<li>\s*<strong\b/i', $desc)) return null; // ya maquetado
	$len = mb_strlen($desc);
	$liCount = preg_match_all('/<li\b/i', $desc);
	if ($liCount >= 3 && $len >= 200) return 'list';
	if ($liCount < 3 && $len >= 250) {
		$plain = trim(html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		$sentences = preg_split('/[.!?]+\s+/u', $plain);
		$sentences = array_filter($sentences, fn($s) => mb_strlen(trim($s), 'UTF-8') > 12);
		$longOnes  = array_filter($sentences, fn($s) => mb_strlen(trim($s), 'UTF-8') > 50);
		// Aceptar si hay ≥3 oraciones >12 chars o ≥2 oraciones largas (>50 chars)
		if (count($sentences) >= 3 || count($longOnes) >= 2) return 'dense';
	}
	return null;
}

$where = "p.products_import_origin='garmin'";
if ($onlyPids) $where .= " AND p.products_id IN (" . implode(',', $onlyPids) . ")";

// Procesa los dos idiomas en una sola query
$r = $m->query("
SELECT p.products_id, p.products_model,
  pde.products_description AS desc_en,
  pds.products_description AS desc_es
FROM products p
JOIN products_description pde ON pde.products_id=p.products_id AND pde.language_id=1
JOIN products_description pds ON pds.products_id=p.products_id AND pds.language_id=3
WHERE $where ORDER BY p.products_id");

// Construye lista de tareas: una entrada por (pid, lang) que necesite reformatear
$tasks = []; // ['pid'=>..., 'sku'=>..., 'lang'=>1|3, 'mode'=>'list'|'dense', 'desc'=>...]
while ($row = $r->fetch_assoc()) {
	$mEs = pickReformatMode($row['desc_es']);
	if ($mEs) $tasks[] = ['pid'=>(int)$row['products_id'], 'sku'=>$row['products_model'], 'lang'=>3, 'mode'=>$mEs, 'desc'=>$row['desc_es']];
	$mEn = pickReformatMode($row['desc_en']);
	if ($mEn) $tasks[] = ['pid'=>(int)$row['products_id'], 'sku'=>$row['products_model'], 'lang'=>1, 'mode'=>$mEn, 'desc'=>$row['desc_en']];
}

$counts = ['list_es'=>0,'list_en'=>0,'dense_es'=>0,'dense_en'=>0];
foreach ($tasks as $t) {
	$counts[$t['mode'].($t['lang']===3?'_es':'_en')]++;
}
echo "Tareas de re-maquetado: " . count($tasks) . "\n";
echo "  ES: list=" . $counts['list_es'] . ", dense=" . $counts['dense_es'] . "\n";
echo "  EN: list=" . $counts['list_en'] . ", dense=" . $counts['dense_en'] . "\n";
foreach ($tasks as $t) {
	$lng = $t['lang']===3 ? 'ES' : 'EN';
	echo "  pid={$t['pid']} sku={$t['sku']} lang=$lng mode={$t['mode']} (len=" . mb_strlen($t['desc']) . ")\n";
}

if ($dryRun) { echo "\n[DRY] no se aplica nada\n"; exit; }
if (empty($tasks)) exit;

echo "\nProcesando…\n";
$ok = 0; $fail = 0;
foreach ($tasks as $t) {
	if ($t['lang'] === 3) {
		$prompt = $t['mode'] === 'dense' ? LLM_FORMAT_PROMPT_DENSE : LLM_FORMAT_PROMPT;
	} else {
		$prompt = $t['mode'] === 'dense' ? LLM_FORMAT_PROMPT_DENSE_EN : LLM_FORMAT_PROMPT_EN;
	}
	$out = llmCall($prompt, $t['desc']);
	$valid = $out !== ''
		&& preg_match('/<ul\b/i', $out)
		&& preg_match('/<li>\s*<strong\b/i', $out);
	if ($valid && $t['mode'] === 'list' && mb_strlen($out) < mb_strlen($t['desc']) * 0.5) $valid = false;
	$lng = $t['lang']===3 ? 'ES' : 'EN';
	if (!$valid) {
		$fail++;
		echo "  FAIL pid={$t['pid']} lang=$lng mode={$t['mode']} (LLM no maquetó correctamente)\n";
		continue;
	}
	$qd = $m->real_escape_string($out);
	if ($m->query("UPDATE products_description SET products_description='$qd' WHERE products_id={$t['pid']} AND language_id={$t['lang']}")) {
		$ok++;
		echo "  OK pid={$t['pid']} sku={$t['sku']} lang=$lng mode={$t['mode']} (in=" . mb_strlen($t['desc']) . " out=" . mb_strlen($out) . ")\n";
	} else {
		$fail++;
		echo "  ERR pid={$t['pid']} lang=$lng: " . $m->error . "\n";
	}
}
echo "\n=== RESUMEN: ok=$ok fail=$fail ===\n";
