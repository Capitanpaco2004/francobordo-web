<?php
// #FB-VIES  Cron de re-validacion VIES. Re-comprueba en VIES los VAT de empresas (grupos 0 retail-con-
// empresa y 1 profesionales) cuyo next_recheck ha vencido; conserva el ultimo estado valido conocido si
// VIES falla. Endpoint publico. Bootstrap storefront (solo necesita tep_db_query + fb_vies + curl).
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

// Token secreto de acceso (como el resto de crons publicos). Cambialo si se filtra.
const FB_VIES_CRON_TOKEN = 'f3a9d7c15e8b4206a1c9e0d2b6f84537';

// Gate: CLI o token secreto valido. (El UA 'cPanel-Cron' es falsificable y no basta como auth; asi se
// evita que hits web anonimos disparen llamadas masivas a VIES desde la IP del servidor.)
$tok = (string) ($_GET['token'] ?? '');
if (php_sapi_name() !== 'cli' && !hash_equals(FB_VIES_CRON_TOKEN, $tok)) { http_response_code(403); exit('Forbidden'); }

$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";

chdir(__DIR__);
require __DIR__ . '/includes/application_top.php';

if (!class_exists('fb_vies')) { require_once DIR_FS_CATALOG . 'includes/classes/fb_vies.php'; }
fb_vies::ensureTables();

// Solo MANTENIMIENTO: re-valida filas de fb_vies_status ya existentes cuyo next_recheck vencio (no
// descubre clientes nuevos; la validacion inicial la hace la pagina admin / el alta). Asi el cron nunca
// dispara cientos de llamadas de golpe. Limite pequeño + pausa entre llamadas (gentil con VIES).
$limit = 25;
$due = tep_db_query("select s.customers_id
                       from " . fb_vies::T_STATUS . " s
                       join customers c on c.customers_id = s.customers_id
                      where c.customers_group_id in (0, 1)
                        and (s.next_recheck is null or s.next_recheck < now())
                      order by s.next_recheck asc
                      limit " . (int) $limit);

$n = 0; $ok = 0; $err = 0;
while ($d = tep_db_fetch_array($due)) {
    $r  = fb_vies::validateCustomer((int) $d['customers_id'], 'cron');
    $n++;
    $st = $r['applied_status'] ?? ($r['status'] ?? '');
    if ($st === 'valid') $ok++;
    if (($r['status'] ?? '') === 'error') $err++;
    usleep(250000); // 0,25s entre llamadas: no saturar VIES ni la IP del servidor
}

$line = date('Y-m-d H:i:s') . " fb_vies cron: procesados=$n validos=$ok errores_vies=$err";
echo $line . $br;
error_log($line . "\n");
