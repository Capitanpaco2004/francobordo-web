<?php
// Lib auto-extraída de import-osculati-altas.php (2026-05-29) — funciones de tabla de specs.
// Requiere LLM_URL y LLM_MODEL definidas antes del require.
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
