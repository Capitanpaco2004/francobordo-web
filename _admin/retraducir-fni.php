<?php
/**
 * Retraducir productos FNI: re-traduce nombre y descripción IT→ES (LLM) y
 * aplica el prefijo de marca a los productos products_import_origin='fni'
 * que se importaron antes de añadirse esa lógica (2026-05-01) o cuya
 * traducción inicial falló y se quedó con el texto italiano.
 *
 * Lee el CSV cacheado en /home/francobordo/public_html/import/feed/FNI/.
 */

require 'includes/application_top.php';

const FNI_CSV          = '/home/francobordo/public_html/import/feed/FNI/Tracciato_master_10.csv';
const LLM_URL          = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL        = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT       = 'Eres un traductor experto IT→ES de productos náuticos para una tienda española. Recibes la versión italiana y, como referencia para desambiguar, la inglesa. TRADUCE TODA palabra italiana al español; conserva intactos marcas, modelos, códigos, números y unidades (V, Ah, mm, kg…). Glosario obligatorio: ELICA=HÉLICE, BATTERIA=BATERÍA, TUBETTO=TUBO, GIUNZIONE=EMPALME, ANCORA=ANCLA, OMBRELLO=PARAGUAS, PIASTRA=PLACA, ANELLO=ANILLO, COPPIA=PAR, MENSOLE=ESTANTES, SUPPORTI=SOPORTES, INOX=INOX, INOSSIDABILE=INOXIDABLE, COMPATTA=COMPACTA, RETRATTILE=RETRÁCTIL, AVVOLGITORE=ENROLLADOR, CUSCINETTO=COJINETE, RUOTA=RUEDA, VITE=TORNILLO, RONDELLA=ARANDELA, GUARNIZIONE=JUNTA. Conserva <br> si los hay como saltos de línea. Texto plano. Responde SOLO con la traducción al español, sin comentarios ni etiquetas.';
const PRODUCT_NAME_MAX = 80;
const LANG_ID_ES       = 3;
const LANG_ID_EN       = 1;
const DEFAULT_BRAND    = 'Generico';
const BRAND_NO_PREFIX  = ['GENERICO', 'FORNITURE NAUTICHE ITALIANE'];

$action      = $_POST['action'] ?? $_GET['action'] ?? '';
$dryRun      = ($action !== 'execute');
$isAction    = ($action === 'execute' || $action === 'dry_run');
$retranslate = isset($_POST['retranslate']) || isset($_GET['retranslate']);

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
	if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}

function logMsg($msg) {
	echo '[' . date('H:i:s') . '] ' . $msg . "<br>\n";
	@flush();
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

function fniBrandPrefix($rawBrand) {
	$brand = trim((string) $rawBrand);
	if ($brand === '') return '';
	if (in_array(strtoupper($brand), BRAND_NO_PREFIX, true)) return '';
	return fniNormalizeManufacturer($brand) . ' ';
}

function fniApplyBrandPrefix($prefix, $name) {
	if ($name === null || $name === '') return $prefix !== '' ? rtrim($prefix) : '';
	return mb_substr($prefix . $name, 0, PRODUCT_NAME_MAX, 'UTF-8');
}

/** Antepone $prefix a $name solo si el nombre actual no empieza ya con esa marca
 *  (case-insensitive). Si ya está prefijado, devuelve $name tal cual. */
function applyPrefixIfMissing($prefix, $name) {
	$name = (string) $name;
	if ($prefix === '') return $name;
	$pNorm = trim($prefix);
	if ($pNorm === '') return $name;
	if (mb_stripos($name, $pNorm . ' ', 0, 'UTF-8') === 0) return $name; // ya empieza por la marca
	if (mb_stripos($name, $pNorm, 0, 'UTF-8') === 0 && mb_strlen($name, 'UTF-8') === mb_strlen($pNorm, 'UTF-8')) return $name;
	return mb_substr($prefix . $name, 0, PRODUCT_NAME_MAX, 'UTF-8');
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

function llmTranslate($textIt, $textEnRef = '', $maxRetries = 5) {
	if (trim((string) $textIt) === '') return '';
	$user = "ITALIANO:\n" . $textIt;
	if (trim((string) $textEnRef) !== '' && $textEnRef !== $textIt) {
		$user .= "\n\nINGLÉS (referencia):\n" . $textEnRef;
	}
	$user .= "\n\nTraducción al español:";
	$payload = json_encode([
		'model'               => LLM_MODEL,
		'messages'            => [
			['role' => 'system', 'content' => LLM_PROMPT],
			['role' => 'user',   'content' => $user],
		],
		'temperature'         => 0,
		'max_tokens'          => 1500,
		'chat_template_kwargs' => ['enable_thinking' => false],
	], JSON_UNESCAPED_UNICODE);
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_TIMEOUT        => 90,
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
		usleep(500000 * ($i + 1));
	}
	return '';
}

function loadCsvBySku($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	stream_filter_append($f, 'convert.iconv.CP1252/UTF-8');
	$bySku = [];
	while (($r = fgetcsv($f, 0, ';', chr(34), '')) !== false) {
		if (count($r) < 30) continue;
		$sku = trim($r[1] ?? '');
		if ($sku === '') continue;
		$bySku[$sku] = [
			'NAME_IT'    => trim($r[3]  ?? ''),
			'SUBDESC_IT' => trim($r[4]  ?? ''),
			'NAME_EN'    => trim($r[5]  ?? ''),
			'SUBDESC_EN' => trim($r[6]  ?? ''),
			'BRAND'      => trim($r[7]  ?? ''),
		];
	}
	fclose($f);
	return $bySku;
}

function resolveManufacturer($mysqli, $rawName, $dryRun) {
	static $cache = [];
	$key = strtoupper(trim($rawName));
	if ($key === '') return null;
	if (isset($cache[$key])) return $cache[$key];
	$qkey = $mysqli->real_escape_string($key);
	$r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=\"$qkey\" LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) {
		$cache[$key] = (int) $row['manufacturers_id'];
		return $cache[$key];
	}
	if ($dryRun) { $cache[$key] = 0; return 0; }
	$display = fniNormalizeManufacturer($rawName);
	$qd = $mysqli->real_escape_string($display);
	$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES (\"$qd\", NOW())");
	$id = (int) $mysqli->insert_id;
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", \"\")");
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", \"\")");
	$cache[$key] = $id;
	return $id;
}
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.fni-retr code { background:#f4f4f4; padding:1px 4px; border-radius:3px; }
.fni-retr .row { padding:6px 8px; border-bottom:1px solid #eee; font-size:13px; }
.fni-retr .ko { color:#a00; }
</style>
<div class="fni-retr" style="max-width:1100px; margin:20px;">
<h1>Retraducir / prefijar productos FNI</h1>
<?php if (!$isAction): ?>
<p>Aplica el prefijo de marca al título (EN y ES) de los productos
<code>products_import_origin='fni'</code> y asigna fabricante a los que estén
sin él. Opcionalmente re-traduce nombre y descripción IT→ES vía LLM (solo recomendado
para productos cuya ES sigue en italiano: usa el CSV cacheado en
<code><?php echo FNI_CSV; ?></code>).</p>
<p>El primer paso debe ser un <em>dry run</em> para ver el diff antes de aplicar.</p>
<form method="POST">
	<label style="display:block; margin:10px 0;">
		<input type="checkbox" name="retranslate" value="1">
		También re-traducir IT→ES con el LLM <em>(opt-in: puede empeorar nombres ya correctos)</em>
	</label>
	<button type="submit" name="action" value="dry_run">Dry run</button>
	<button type="submit" name="action" value="execute"
		onclick="return confirm('¿Aplicar cambios a la BD?')">Ejecutar</button>
</form>
<?php else:
	echo str_pad('', 4096) . "<br>\n";
	@flush();
	logMsg($dryRun ? "MODO DRY-RUN (no escribe en BD)" : "MODO EJECUCIÓN (escribe en BD)");
	logMsg($retranslate ? "Modo: prefijo + retraducción LLM" : "Modo: solo prefijo (sin retraducir)");

	if (!file_exists(FNI_CSV)) { logMsg("ERROR: CSV no encontrado: " . FNI_CSV); goto end_action; }
	$bySku = loadCsvBySku(FNI_CSV);
	logMsg("CSV cargado: " . count($bySku) . " filas indexadas por SKU.");

	$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
	if ($mysqli->connect_errno) { logMsg("ERROR mysqli: " . $mysqli->connect_error); goto end_action; }
	$mysqli->set_charset('utf8mb4');

	$res = $mysqli->query("
		SELECT p.products_id, p.products_model, p.manufacturers_id,
		       m.manufacturers_name AS current_brand,
		       pdes.products_name AS old_name_es,
		       pden.products_name AS old_name_en
		FROM products p
		LEFT JOIN manufacturers m ON p.manufacturers_id=m.manufacturers_id
		LEFT JOIN products_description pdes ON p.products_id=pdes.products_id AND pdes.language_id=" . LANG_ID_ES . "
		LEFT JOIN products_description pden ON p.products_id=pden.products_id AND pden.language_id=" . LANG_ID_EN . "
		WHERE p.products_import_origin='fni'
		ORDER BY p.products_id
	");

	$st = ['total'=>0,'no_csv'=>0,'tn_ok'=>0,'tn_ko'=>0,'td_ok'=>0,'td_ko'=>0,'manuf_set'=>0,'manuf_chg'=>0];

	while ($p = $res->fetch_assoc()) {
		$st['total']++;
		$pid = (int) $p['products_id'];
		$sku = (string) $p['products_model'];
		if (!isset($bySku[$sku])) {
			logMsg("<span class='ko'>[$pid sku=$sku] no encontrado en CSV — salto</span>");
			$st['no_csv']++;
			continue;
		}
		$row = $bySku[$sku];
		$brand = $row['BRAND'] !== '' ? $row['BRAND'] : DEFAULT_BRAND;
		$brandPrefix = fniBrandPrefix($brand);

		// Manufacturer
		$newManuf = resolveManufacturer($mysqli, $brand, $dryRun);
		$curManuf = (int) ($p['manufacturers_id'] ?? 0);
		if ($newManuf && $newManuf !== $curManuf) {
			if ($curManuf === 0) $st['manuf_set']++; else $st['manuf_chg']++;
			logMsg("[$pid] manufacturer " . ($curManuf ?: '∅') . " → $newManuf <em>($brand)</em>");
			if (!$dryRun) $mysqli->query("UPDATE products SET manufacturers_id=$newManuf WHERE products_id=$pid");
		}

		// Build raw IT/EN
		$rawNameIt  = $row['NAME_IT'];
		$rawNameEn  = $row['NAME_EN'] !== '' ? $row['NAME_EN'] : $row['NAME_IT'];
		$descItFull = trim($rawNameIt . (trim($row['SUBDESC_IT']) !== '' ? "<br><br>" . $row['SUBDESC_IT'] : ''));
		$descIt     = cleanHtmlAggressive($descItFull);
		$descEnFull = trim($rawNameEn . (trim($row['SUBDESC_EN']) !== '' ? "<br><br>" . $row['SUBDESC_EN'] : ''));
		$descEn     = cleanHtmlAggressive($descEnFull);

		$oldNameEs = (string) ($p['old_name_es'] ?? '');
		$oldNameEn = (string) ($p['old_name_en'] ?? '');

		if ($retranslate) {
			// EN name desde el CSV con prefijo
			$nameEn = fniApplyBrandPrefix($brandPrefix, $rawNameEn);
			// ES name: traducir IT con EN como referencia, luego prefijar
			$tn = llmTranslate($rawNameIt, $rawNameEn);
			if ($tn !== '') {
				$nameEs = fniApplyBrandPrefix($brandPrefix, $tn);
				$st['tn_ok']++;
			} else {
				$nameEs = fniApplyBrandPrefix($brandPrefix, $rawNameIt);
				$st['tn_ko']++;
				logMsg("<span class='ko'>[$pid] WARN: traducción de nombre falló — uso IT con prefijo</span>");
			}
			// ES desc: traducir
			$descEsTrans = '';
			if ($descIt !== '') {
				$td = llmTranslate($descIt, $descEn);
				if ($td !== '') { $descEsTrans = $td; $st['td_ok']++; }
				else { $descEsTrans = $descIt; $st['td_ko']++; logMsg("<span class='ko'>[$pid] WARN: traducción de desc falló — dejo IT</span>"); }
			}
			$descEnNew = $descEn;
		} else {
			// SOLO prefijo: trabajamos sobre lo que ya hay en BD, sin LLM
			$nameEn = applyPrefixIfMissing($brandPrefix, $oldNameEn !== '' ? $oldNameEn : $rawNameEn);
			$nameEs = applyPrefixIfMissing($brandPrefix, $oldNameEs !== '' ? $oldNameEs : $rawNameIt);
			$descEsTrans = null; // null = no tocar
			$descEnNew   = null;
		}

		$enChanged = ($nameEn !== $oldNameEn);
		$esChanged = ($nameEs !== $oldNameEs);
		echo "<div class='row'>"
			. "[$pid sku=$sku] <strong>$brand</strong><br>"
			. "EN: <code>" . htmlspecialchars($oldNameEn) . "</code> " . ($enChanged ? "→ <code>" . htmlspecialchars($nameEn) . "</code>" : "<em>(sin cambio)</em>") . "<br>"
			. "ES: <code>" . htmlspecialchars($oldNameEs) . "</code> " . ($esChanged ? "→ <code>" . htmlspecialchars($nameEs) . "</code>" : "<em>(sin cambio)</em>")
			. "</div>\n";
		@flush();

		if (!$dryRun) {
			$qNameEs = $mysqli->real_escape_string($nameEs);
			$qNameEn = $mysqli->real_escape_string($nameEn);
			if ($descEsTrans !== null) {
				$qDescEs = $mysqli->real_escape_string($descEsTrans);
				$qDescEn = $mysqli->real_escape_string($descEnNew);
				$mysqli->query("UPDATE products_description SET products_name=\"$qNameEs\", products_description=\"$qDescEs\" WHERE products_id=$pid AND language_id=" . LANG_ID_ES);
				$mysqli->query("UPDATE products_description SET products_name=\"$qNameEn\", products_description=\"$qDescEn\" WHERE products_id=$pid AND language_id=" . LANG_ID_EN);
			} else {
				// Solo prefijo: actualiza nombre, deja descripción intacta
				if ($esChanged) $mysqli->query("UPDATE products_description SET products_name=\"$qNameEs\" WHERE products_id=$pid AND language_id=" . LANG_ID_ES);
				if ($enChanged) $mysqli->query("UPDATE products_description SET products_name=\"$qNameEn\" WHERE products_id=$pid AND language_id=" . LANG_ID_EN);
			}
		}
	}

	logMsg("─────────────────────────────────");
	logMsg("Total productos:        " . $st['total']);
	logMsg("Sin match en CSV:       " . $st['no_csv']);
	logMsg("Nombre traducido OK:    " . $st['tn_ok']);
	logMsg("Nombre traducción KO:   " . $st['tn_ko']);
	logMsg("Desc traducida OK:      " . $st['td_ok']);
	logMsg("Desc traducción KO:     " . $st['td_ko']);
	logMsg("Manufacturer asignado:  " . $st['manuf_set'] . " (era ∅)");
	logMsg("Manufacturer cambiado:  " . $st['manuf_chg']);
	logMsg($dryRun ? "Dry run terminado. Sin cambios en BD." : "Ejecución terminada.");

	end_action:
	echo '<p><a href="retraducir-fni.php">← volver</a></p>';
endif;
?>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
