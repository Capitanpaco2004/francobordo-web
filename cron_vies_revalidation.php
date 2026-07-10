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

// Dos fases, cada una con limite pequeño + pausa (gentil con VIES):
//  1) MANTENIMIENTO: re-valida filas de fb_vies_status cuyo next_recheck vencio.
//  2) DESCUBRIMIENTO: valida clientes de paises UE (no ES/UK) de grupos 0/1 con VAT/NIF y SIN fila en
//     fb_vies_status (backstop de la validacion automatica del alta/edicion de cuenta; acotado por el
//     mismo filtro de pais que _admin/vies.php, nunca dispara validaciones de clientes espanoles).
$eu_no_es = '14,21,33,53,55,56,57,67,72,73,74,81,84,97,103,105,117,123,124,132,141,150,170,171,175,189,190,203';
$limit = 25;

$n = 0; $ok = 0; $err = 0;
$due = tep_db_query("select s.customers_id
                       from " . fb_vies::T_STATUS . " s
                       join customers c on c.customers_id = s.customers_id
                      where c.customers_group_id in (0, 1)
                        and (s.next_recheck is null or s.next_recheck < now())
                      order by s.next_recheck asc
                      limit " . (int) $limit);
while ($d = tep_db_fetch_array($due)) {
    $r  = fb_vies::validateCustomer((int) $d['customers_id'], 'cron');
    $n++;
    $st = $r['applied_status'] ?? ($r['status'] ?? '');
    if ($st === 'valid') $ok++;
    if (($r['status'] ?? '') === 'error') $err++;
    usleep(250000); // 0,25s entre llamadas: no saturar VIES ni la IP del servidor
}

$d2 = 0; $ok2 = 0; $err2 = 0;
$news = tep_db_query("select c.customers_id
                        from customers c
                        join address_book ab on ab.address_book_id = c.customers_default_address_id
                        left join " . fb_vies::T_STATUS . " s on s.customers_id = c.customers_id
                       where s.customers_id is null
                         and c.customers_group_id in (0, 1)
                         and ab.entry_country_id in (" . $eu_no_es . ")
                         and (trim(coalesce(c.entry_company_tax_id, '')) <> '' or trim(coalesce(ab.entry_NIF, '')) <> '')
                       order by c.customers_id desc
                       limit " . (int) $limit);
while ($d = tep_db_fetch_array($news)) {
    $r  = fb_vies::validateCustomer((int) $d['customers_id'], 'cron-discovery');
    $d2++;
    $st = $r['applied_status'] ?? ($r['status'] ?? '');
    if ($st === 'valid') $ok2++;
    if (($r['status'] ?? '') === 'error') $err2++;
    usleep(250000);
}

$line = date('Y-m-d H:i:s') . " fb_vies cron: mantenimiento=$n (validos=$ok err=$err) descubiertos=$d2 (validos=$ok2 err=$err2)";
echo $line . $br;
error_log($line . "\n");
