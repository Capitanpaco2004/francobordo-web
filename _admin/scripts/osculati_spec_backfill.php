<?php
/**
 * osculati_spec_backfill.php — CLI
 *
 * Backfill de la tabla de especificaciones (Code2SerXml) en productos Osculati
 * YA importados. ACOTADO a: scope Osculati + products_status=2 + importados el
 * último mes (products_date_added >= CURDATE() - 1 MONTH).
 *
 * Reutiliza las MISMAS funciones que el importador (osculati_spec_lib.php).
 * Anexa el bloque <p><strong>Especificaciones</strong></p> + tabla a
 * products_description (lang 3 ES traducida, lang 1 EN original), SALTANDO los
 * que ya tienen 'osc-spec-table' (idempotente).
 *
 * Modos:
 *   php osculati_spec_backfill.php          → DRY-RUN (no toca BD; lista qué haría)
 *   php osculati_spec_backfill.php APPLY    → aplica (backup JSON antes)
 */
$apply = in_array('APPLY', $argv ?? [], true);

const OSC_USER     = 'C54293';
const OSC_PASS     = '0XxBkWSb';
const OSC_FTP_BASE = 'ftp://fw.osculati.it/';
const LLM_URL      = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL    = 'qwen36-sakamaki-nvfp4';
const LANG_ES = 3;
const LANG_EN = 1;

require '/home/francobordo/public_html/includes/configure.php';
require '/home/francobordo/public_html/_admin/scripts/osculati_spec_lib.php'; // funciones osc*()

$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($m->connect_error) { fwrite(STDERR, "DB ERROR\n"); exit(1); }
$m->set_charset('utf8');

echo "Modo: " . ($apply ? "APPLY (modifica BD)" : "DRY-RUN") . "\n\n";

/* ---- 1) Descargar Code2SerXml.txt y construir $xtMap ---- */
function readUtf16File($path) {
    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $u8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
    $u8 = ltrim($u8, "\xEF\xBB\xBF\xFF\xFE");
    $rows = [];
    foreach (preg_split("/\r\n|\r|\n/", $u8) as $line) {
        if ($line === '') continue;
        $rows[] = explode("\t", $line);
    }
    return $rows;
}
$xtFile = sys_get_temp_dir() . '/Code2SerXml_backfill.txt';
echo "Descargando Code2SerXml.txt...\n";
$ch = curl_init(OSC_FTP_BASE . 'ENG/Code2SerXml.txt');
curl_setopt_array($ch, [CURLOPT_USERPWD => OSC_USER . ':' . OSC_PASS, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180]);
$data = curl_exec($ch);
if ($data === false || strlen($data) < 1000) { fwrite(STDERR, "FTP download falló\n"); exit(1); }
file_put_contents($xtFile, $data);
unset($data);
$xtMap = [];
foreach (readUtf16File($xtFile) as $r) {
    if (count($r) < 5) continue;
    $oc = strtolower(preg_replace('/#.*$/', '', trim($r[0])));
    if ($oc !== '' && strpos($r[4], '<attributo>') !== false) $xtMap[$oc] = $r[4];
}
@unlink($xtFile);
echo "  → " . count($xtMap) . " ítems con especificaciones\n\n";

/* ---- 2) Candidatos ---- */
$scope = "(manufacturers_id=259 OR products_import_origin LIKE 'osculati%') "
       . "AND products_status=2 "
       . "AND products_date_added >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
$r = $m->query("SELECT products_id, products_model FROM products WHERE $scope ORDER BY products_id");
$cands = [];
while ($x = $r->fetch_assoc()) $cands[(int) $x['products_id']] = $x['products_model'];
echo "Candidatos: " . count($cands) . "\n\n";

$backup = [];
$nDone = $nSkipHas = $nSkipNoSpec = $nErr = 0;

foreach ($cands as $pid => $model) {
    // OrderCodes: variantes (reference) o, si no hay, el products_model
    $codes = [];
    $rv = $m->query("SELECT reference FROM products_attributes WHERE products_id=$pid AND reference<>''");
    while ($y = $rv->fetch_assoc()) $codes[] = $y['reference'];
    if (!$codes) $codes[] = $model;

    list($specEs, $specEn) = oscSpecBlock($codes, $xtMap);
    if ($specEs === '') { $nSkipNoSpec++; echo "  pid=$pid [$model] SIN specs en XML → skip\n"; continue; }

    // Descripciones actuales
    $descs = [];
    $rd = $m->query("SELECT language_id, products_description FROM products_description WHERE products_id=$pid AND language_id IN (" . LANG_ES . "," . LANG_EN . ")");
    while ($y = $rd->fetch_assoc()) $descs[(int) $y['language_id']] = $y['products_description'];

    if ((isset($descs[LANG_ES]) && strpos($descs[LANG_ES], 'osc-spec-table') !== false)) {
        $nSkipHas++; echo "  pid=$pid [$model] YA tiene tabla → skip\n"; continue;
    }

    $newEs = ($descs[LANG_ES] ?? '');
    $newEs = ($newEs !== '' ? $newEs . "\n" : '') . $specEs;
    $newEn = ($descs[LANG_EN] ?? '');
    $newEn = ($newEn !== '' ? $newEn . "\n" : '') . $specEn;

    $nCodes = count($codes);
    echo "  pid=$pid [$model] OK ($nCodes variantes) → tabla " . (substr_count($specEs, '<tr>') - 1) . " filas\n";

    if ($apply) {
        $backup[$pid] = ['es' => $descs[LANG_ES] ?? null, 'en' => $descs[LANG_EN] ?? null];
        $okEs = $m->query("UPDATE products_description SET products_description='" . $m->real_escape_string($newEs) . "' WHERE products_id=$pid AND language_id=" . LANG_ES);
        $okEn = $m->query("UPDATE products_description SET products_description='" . $m->real_escape_string($newEn) . "' WHERE products_id=$pid AND language_id=" . LANG_EN);
        if ($okEs && $okEn) $nDone++; else { $nErr++; echo "    ERROR UPDATE: " . $m->error . "\n"; }
    } else {
        $nDone++;
    }
}

echo "\n==================== RESUMEN ====================\n";
echo "Procesados con tabla : $nDone\n";
echo "Saltados (ya tenían) : $nSkipHas\n";
echo "Saltados (sin specs) : $nSkipNoSpec\n";
echo "Errores UPDATE       : $nErr\n";

if ($apply && $backup) {
    $bk = sys_get_temp_dir() . '/osc_spec_backfill_backup_' . date('Ymd-His') . '.json';
    file_put_contents($bk, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Backup descripciones previas: $bk\n";
}
if (!$apply) echo "\nDRY-RUN. Para aplicar: php " . basename(__FILE__) . " APPLY\n";
