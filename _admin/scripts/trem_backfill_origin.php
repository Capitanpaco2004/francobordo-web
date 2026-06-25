<?php
/**
 * Backfill del flag products_import_origin='trem-legacy' sobre productos Trem del catálogo antiguo
 * que NUNCA recibieron el flag (origin vacío) y por tanto el Actualizador_precios_trem.php ignora.
 *
 * SCOPE (decisión usuario 2026-06-24): SOLO fabricante Trem (manufacturers_id=189) cuya referencia
 * (products_model o reference_prov) coincida con un SKU del listino-export.xlsx actual.
 *
 * El valor 'trem-legacy' empieza por 'trem' → lo recoge el actualizador (filtro LIKE 'trem%'),
 * pero es distinguible de los 32 dados de alta por el importador ('trem' a secas).
 *
 * NO toca precios. Solo estampa el flag. Los precios los ajustará el Actualizador_precios_trem.php
 * en su próxima ejecución (con su cap del 30% de protección).
 *
 * Uso:
 *   php trem_backfill_origin.php                  # DRY, fabricante Trem(189) por defecto
 *   php trem_backfill_origin.php EXECUTE          # aplica, fabricante Trem(189)
 *   php trem_backfill_origin.php MFG=481          # DRY, fabricante Can(481)
 *   php trem_backfill_origin.php MFG=481 EXECUTE  # aplica, fabricante Can(481)
 *   php trem_backfill_origin.php MFG=189,481 EXECUTE  # varios fabricantes a la vez
 *
 * Fabricantes que distribuye Trem (con productos legacy en el xlsx): 189 Trem, 481 Can,
 * 29 Generico, 345 Inox Mare.
 */
include '/home/francobordo/public_html/includes/configure.php';
require '/home/francobordo/public_html/includes/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

const TREM_XLSX     = '/home/francobordo/public_html/import/Trem/listino-export.xlsx';
const NEW_ORIGIN    = 'trem-legacy';

$dry = !in_array('EXECUTE', $argv ?? [], true);

// Fabricante(s) objetivo (default Trem 189). Acepta MFG=481 o MFG=189,481
$mfgIds = [189];
foreach (($argv ?? []) as $a) {
	if (preg_match('/^MFG=([\d,]+)$/i', $a, $mm)) {
		$mfgIds = array_values(array_filter(array_map('intval', explode(',', $mm[1]))));
	}
}
$mfgInClause = implode(',', $mfgIds);

$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

// 1) Cargar SKUs del xlsx (col C = ITEM)
if (!file_exists(TREM_XLSX)) { echo "ERROR: no existe " . TREM_XLSX . "\n"; exit(1); }
$reader = IOFactory::createReaderForFile(TREM_XLSX);
$reader->setReadDataOnly(true);
$sheet = $reader->load(TREM_XLSX)->getActiveSheet();
$skus = [];
foreach ($sheet->getRowIterator(2) as $row) {
	$c = trim((string) $sheet->getCell('C' . $row->getRowIndex())->getValue());
	if ($c !== '') $skus[strtolower($c)] = true;
}
echo "SKUs en xlsx Trem: " . count($skus) . "\n";

// 2) Candidatos: fabricante(s) objetivo, sin flag trem, referencia en el xlsx
echo "Fabricante(s) objetivo: $mfgInClause\n";
$r = $m->query("
	SELECT products_id, products_model, reference_prov, products_status, products_import_origin
	FROM products
	WHERE manufacturers_id IN ($mfgInClause)
	  AND (products_import_origin IS NULL OR products_import_origin NOT LIKE 'trem%')
	ORDER BY products_id");

$candidates = [];
$noMatch = 0;
while ($x = $r->fetch_assoc()) {
	$keys = [strtolower((string) $x['products_model']), strtolower((string) $x['reference_prov'])];
	$hit = false;
	foreach ($keys as $k) { if ($k !== '' && isset($skus[$k])) { $hit = true; break; } }
	if (!$hit) { $noMatch++; continue; }
	$candidates[] = $x;
}

echo "Productos mfg=Trem sin flag y CON match en xlsx: " . count($candidates) . "\n";
echo "Productos mfg=Trem sin flag SIN match en xlsx (se dejan intactos): $noMatch\n\n";

$byStatus = [];
foreach ($candidates as $c) $byStatus[$c['products_status']] = ($byStatus[$c['products_status']] ?? 0) + 1;
echo "Por status: ";
foreach ($byStatus as $k => $v) echo "status=$k:$v  ";
echo "\n\n";

foreach ($candidates as $c) {
	echo "  pid={$c['products_id']} model={$c['products_model']} ref_prov=" . ($c['reference_prov'] ?: '-') . " status={$c['products_status']} origin=" . ($c['products_import_origin'] ?: '(vacio)') . "\n";
}

if ($dry) {
	echo "\n[DRY] no se aplica nada. Pasa EXECUTE para estampar origin='" . NEW_ORIGIN . "'.\n";
	exit;
}
if (empty($candidates)) { echo "\nNada que marcar.\n"; exit; }

// 3) Aplicar
echo "\nAplicando flag '" . NEW_ORIGIN . "'…\n";
$ok = 0; $fail = 0;
$ids = array_map(fn($c) => (int) $c['products_id'], $candidates);
foreach (array_chunk($ids, 200) as $chunk) {
	$in = implode(',', $chunk);
	if ($m->query("UPDATE products SET products_import_origin='" . NEW_ORIGIN . "' WHERE products_id IN ($in)")) {
		$ok += $m->affected_rows;
	} else {
		$fail++; echo "  ERR chunk: " . $m->error . "\n";
	}
}
echo "\n=== RESUMEN: flag aplicado a $ok productos (errores: $fail) ===\n";
echo "Ahora ejecuta el Actualizador de precios Trem (dry-run primero) para ver qué precios ajustaría.\n";
