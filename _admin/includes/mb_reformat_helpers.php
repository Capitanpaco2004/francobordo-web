<?php
/**
 * Funciones de reformat para descripciones de Marine Business.
 * Compartidas entre:
 *   - _admin/scripts/mb_reformat.php (post-import batch)
 *   - _admin/import-marinebusiness-altas.php (al importar nuevos)
 *
 * Reglas (decidido 2026-05-11):
 *  1. Títulos <h3>/<h4> → <p><strong>X</strong></p>
 *  2. Espacio <p>&nbsp;</p> antes de cada título
 *  3. Texto >5 frases en un <p> → partir cada 5 frases
 *  4. Si título cae justo tras un párrafo, sólo un <p>&nbsp;</p> (no duplicar)
 *  5. Quitar ™ ® © (literales y entities)
 *  6. ALL-CAPS palabras ≥4 letras → Title Case (preserva acrónimos cortos)
 *  7. <ul><li>X</li></ul> → <p>• X</p>
 *  8. Decode HTML entities (&trade;, &aacute;, &Oslash;, &amp;…)
 */

if (!function_exists('mbDecodeEntities')) {

function mbDecodeEntities($html) {
    if ($html === null) return '';
    return html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Quita ™ ® © (caracteres unicode y entities &trade; &reg; &copy;). */
function mbStripTM($text) {
    $text = preg_replace('/&(trade|reg|copy);/i', '', $text);
    $text = preg_replace('/[\x{2122}\x{00AE}\x{00A9}]/u', '', $text);
    // Colapsar espacios/tabs duplicados. OJO: NO incluir \x{00A0} (NBSP) porque <p>&nbsp;</p>
    // se decodifica a NBSP en la pasada anterior y es estructura que hay que preservar.
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    return $text;
}

/** Palabras ALL-CAPS de ≥4 letras → Title Case. Preserva acrónimos cortos (BPA, UV, LED, IP, USB, ABS, PVC, AISI…). */
function mbTitleCaseAllCaps($text) {
    return preg_replace_callback('/\b([A-ZÁÉÍÓÚÑÜ]{4,})\b/u', function ($m) {
        $w = $m[1];
        $low = mb_strtolower($w, 'UTF-8');
        return mb_convert_case($low, MB_CASE_TITLE, 'UTF-8');
    }, $text);
}

/** <ul>/<ol><li>X</li>... → <p>• X</p>... */
function mbConvertListsToBullets($html) {
    return preg_replace_callback('#<(ul|ol)\b[^>]*>(.*?)</\1>#is', function ($m) {
        preg_match_all('#<li\b[^>]*>(.*?)</li>#is', $m[2], $items);
        $out = "\n";
        foreach ($items[1] as $item) {
            $clean = trim($item);
            $clean = preg_replace('#<br\s*/?>#i', ' ', $clean);
            $clean = trim(preg_replace('/\s+/u', ' ', $clean));
            if ($clean !== '') $out .= '<p>• ' . $clean . '</p>' . "\n";
        }
        return $out;
    }, $html);
}

/** <h1..h6>X</h*> → <p><strong>X</strong></p> (sin doble negrita si X ya tenía <strong> interno). */
function mbConvertTitlesToBold($html) {
    return preg_replace_callback('#<h[1-6]\b[^>]*>(.*?)</h[1-6]>#is', function ($m) {
        $inner = trim($m[1]);
        $inner = preg_replace('#</?(strong|b)\b[^>]*>#i', '', $inner);
        $inner = trim($inner);
        if ($inner === '') return '';
        return '<p><strong>' . $inner . '</strong></p>';
    }, $html);
}

/** Inserta <p>&nbsp;</p> antes de cada <p><strong>X</strong></p> (título).
 *  Aplica sin condicionales — si genera duplicados consecutivos, mbFinalCleanup los colapsa.
 *  La limpieza final también elimina el separador si quedara al inicio del HTML. */
function mbInsertSpaceBeforeTitles($html) {
    return preg_replace('#(<p><strong>[^<]{1,120}</strong></p>)#', "<p>&nbsp;</p>\n$1", $html);
}

/** Para cada <p>...</p> con >5 frases, parte cada 5 frases en <p>s separados. */
function mbSplitLongParagraphs($html, $maxSentences = 5) {
    return preg_replace_callback('#<p>(?!<strong>|&nbsp;|\s*•)(.+?)</p>#is', function ($m) use ($maxSentences) {
        $content = $m[1];
        if (preg_match('#^\s*<strong>.*?</strong>\s*$#is', $content)) return $m[0];
        if (preg_match('#<(img|a|table)\b#i', $content)) return $m[0];

        $text = preg_replace('#<br\s*/?>#i', ' ', $content);
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        $sentences = preg_split('/(?<=[\.!?])\s+(?=[A-ZÁÉÍÓÚÑ¡¿"\'])/u', $text);
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn($s) => $s !== ''));
        if (count($sentences) <= $maxSentences) return '<p>' . $text . '</p>';

        $chunks = array_chunk($sentences, $maxSentences);
        $out = '';
        foreach ($chunks as $chunk) {
            $out .= '<p>' . trim(implode(' ', $chunk)) . '</p>' . "\n";
        }
        return rtrim($out);
    }, $html);
}

/** Limpieza final: colapsa saltos en blanco y <p>&nbsp;</p> duplicados; quita separador inicial. */
function mbFinalCleanup($html) {
    // 1) Normaliza cualquier <p>(NBSP|space|vacío)</p> a la forma canónica <p>&nbsp;</p>
    $html = preg_replace('#<p>[\s\x{00A0}]*(?:&nbsp;[\s\x{00A0}]*)*</p>#iu', '<p>&nbsp;</p>', $html);
    // 2) Colapsa 2+ <p>&nbsp;</p> consecutivos a 1 solo
    $html = preg_replace('#(<p>&nbsp;</p>\s*){2,}#', "<p>&nbsp;</p>\n", $html);
    // 3) Si el HTML empieza con <p>&nbsp;</p> (separador inicial inútil), lo quita
    $html = preg_replace('#^\s*<p>&nbsp;</p>\s*#', '', $html);
    // 4) Si el HTML termina con <p>&nbsp;</p>, también lo quita
    $html = preg_replace('#<p>&nbsp;</p>\s*$#', '', $html);
    // 5) Colapsa saltos múltiples de línea
    $html = preg_replace('/(\n\s*){3,}/', "\n\n", $html);
    return trim($html);
}

/** Orchestrator: aplica todas las reglas en orden. Idempotente (se puede llamar varias veces). */
function mbReformatDescription($html) {
    if ($html === null || trim($html) === '') return $html;
    $h = $html;
    $h = mbDecodeEntities($h);
    $h = mbStripTM($h);
    $h = mbConvertListsToBullets($h);
    $h = mbConvertTitlesToBold($h);
    $h = mbTitleCaseAllCaps($h);
    $h = mbSplitLongParagraphs($h, 5);
    $h = mbInsertSpaceBeforeTitles($h);
    $h = mbFinalCleanup($h);
    return $h;
}

} // if !function_exists
