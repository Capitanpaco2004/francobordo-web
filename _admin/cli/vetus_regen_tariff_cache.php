<?php
/**
 * Regenera la cache JSON de la tarifa Vetus desde CLI (donde shell_exec sí está habilitado).
 * Necesario cuando el SAPI web no puede ejecutar pdftotext (común en cPanel/PHP-FPM).
 *
 * Uso: /opt/cpanel/ea-php84/root/usr/bin/php /home/francobordo/public_html/_admin/cli/vetus_regen_tariff_cache.php
 *
 * Lee /home/francobordo/public_html/import/Vetus/Tarifa general VETUS.pdf y guarda
 * /tmp/vetus_tariff_<md5path>.json. El SAPI web del actualizador detecta la cache fresca
 * (mtime >= PDF mtime) y la usa sin tocar el binario pdftotext.
 *
 * Conviene engancharlo a un cron diario o disparar manualmente tras subir un PDF nuevo.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Solo CLI.\n");
}

// Cargar el parser desde el actualizador (sin ejecutar la UI del actualizador entero).
$updater = '/home/francobordo/public_html/_admin/Actualizador_precios_vetus.php';
if (!is_readable($updater)) { fwrite(STDERR, "No leo $updater\n"); exit(1); }
$src = file_get_contents($updater);

// Extraer SOLO las funciones que el parser necesita: vetusRunPdftotext + vetusParsePdfTariff.
preg_match('/^function vetusRunPdftotext\(.*?^\}/sm', $src, $a);
preg_match('/^function vetusParsePdfTariff\(.*?^\}/sm', $src, $b);
if (!$a || !$b) { fwrite(STDERR, "No encuentro funciones del parser en $updater\n"); exit(2); }
eval($a[0]);
eval($b[0]);

$pdfPath = '/home/francobordo/public_html/import/Vetus/Tarifa general VETUS.pdf';
if (!is_readable($pdfPath)) { fwrite(STDERR, "No leo $pdfPath\n"); exit(3); }

$cachePath = sys_get_temp_dir() . '/vetus_tariff_' . md5($pdfPath) . '.json';
// Forzar regeneración: borrar cache previa si existe
@unlink($cachePath);

$t0 = microtime(true);
try {
    $parsed = vetusParsePdfTariff($pdfPath);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR parser: " . $e->getMessage() . "\n");
    exit(4);
}
$dur = round(microtime(true) - $t0, 2);

if (!is_readable($cachePath)) {
    fwrite(STDERR, "Parser no escribió la cache en $cachePath\n");
    exit(5);
}

$tCount = [];
$onDemand = 0;
foreach ($parsed as $row) {
    $tCount[$row['tipo']] = ($tCount[$row['tipo']] ?? 0) + 1;
    if (!empty($row['price_on_demand'])) $onDemand++;
}
ksort($tCount);

echo "OK | " . count($parsed) . " SKUs parseados en {$dur}s\n";
echo "  Cache: $cachePath (" . round(filesize($cachePath) / 1024) . " KB, mtime " . date('Y-m-d H:i:s', filemtime($cachePath)) . ")\n";
echo "  PDF:   $pdfPath (mtime " . date('Y-m-d H:i:s', filemtime($pdfPath)) . ")\n";
echo "  Tipos: ";
foreach ($tCount as $t => $n) echo "$t=$n ";
echo "\n";
echo "  price_on_demand: $onDemand\n";
exit(0);
