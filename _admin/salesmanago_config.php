<?php
/**
 * SalesManago — Admin configuration page (standalone).
 *
 * URL: /_admin/salesmanago_config.php
 * Menu: Marketing > Sales Manago
 *
 * Stores all settings as SALESMANAGO_* keys in the `configuration` table
 * (configuration_group_id = 888030). After save, flushes the config cache
 * file so constants pick up the new values on the next request.
 *
 * Actions:
 *   default                → render form
 *   action=update    POST  → save SALESMANAGO_* keys
 *   action=test      POST  → ping SM API and show result
 *   action=export_csv GET  → stream contacts CSV download
 *
 * Created: 2026-05-18
 * Updated: 2026-05-19 — added CSV export
 */

require_once 'includes/application_top.php';

use util\tools as tools;

// ---------------------------------------------------------------------------
// Export constants — defaults; overridable per-request via GET (cap, months)
// ---------------------------------------------------------------------------
const SM_EXPORT_MONTHS_BACK_DEFAULT = 60;      // 5 años. 0 = sin filtro de años.
const SM_EXPORT_MAX_ROWS_DEFAULT    = 49995;   // < 50000 hard cap de SM
const SM_EXPORT_MAX_ROWS_HARDCAP    = 50000;   // ni el admin puede pasar de aquí

/** Get the effective cap (clamped) from $_GET or default. */
function sm_export_cap(): int
{
    $v = isset($_GET['cap']) ? (int) $_GET['cap'] : SM_EXPORT_MAX_ROWS_DEFAULT;
    return max(1, min(SM_EXPORT_MAX_ROWS_HARDCAP, $v));
}

/** Get the effective months-back filter from $_GET or default. 0 = no filter. */
function sm_export_months(): int
{
    if (!isset($_GET['months'])) return SM_EXPORT_MONTHS_BACK_DEFAULT;
    return max(0, min(240, (int) $_GET['months']));
}

// ---------------------------------------------------------------------------
// Field definitions
// ---------------------------------------------------------------------------

function sm_field_defs(): array
{
    return [
        // --- Bloque: Activación ---
        'SALESMANAGO_STATUS'             => ['type' => 'bool',   'label' => 'Activar integración Sales Manago',          'help' => 'Interruptor maestro. Si está apagado, no se envía nada a SM aunque los eventos individuales estén activos.'],
        'SALESMANAGO_JS_TRACKING'        => ['type' => 'bool',   'label' => 'Activar Monitoring Code (JS de tracking)',   'help' => 'Inyecta el snippet JS de SM en el front para registrar visitas y crear cookie smclient.'],
        'SALESMANAGO_JS_POPUPS'          => ['type' => 'bool',   'label' => 'Activar Pop-ups SM',                         'help' => 'Inyecta <code>popups.js</code> de SM. Los popups se diseñan y publican desde el panel de SM.'],
        'SALESMANAGO_JS_REQUIRE_CONSENT' => ['type' => 'bool',   'label' => 'Requerir consentimiento de cookies (GDPR)',  'help' => 'Solo carga el JS si el visitante ha aceptado cookies. Mantener activo para cumplir GDPR.'],

        // --- Bloque: API access details ---
        'SALESMANAGO_ENDPOINT'      => ['type' => 'text',     'label' => 'Endpoint',          'help' => 'Sin https:// ni /api. Ej: <code>www.salesmanago.pl</code>'],
        'SALESMANAGO_CLIENT_ID'     => ['type' => 'text',     'label' => 'Client ID',         'help' => ''],
        'SALESMANAGO_INSTANCE_ID'   => ['type' => 'text',     'label' => 'Instance ID',       'help' => 'Normalmente <code>1</code>.'],
        'SALESMANAGO_API_KEY'       => ['type' => 'password', 'label' => 'API Key',           'help' => 'Generado en SM > Integration Center > "Generate API key and SHA".'],
        'SALESMANAGO_API_SECRET'    => ['type' => 'password', 'label' => 'API Secret',        'help' => 'NO confundir con la API Key.'],
        'SALESMANAGO_MICROSITE_KEY' => ['type' => 'password', 'label' => 'MicroSite Key',     'help' => 'Necesaria para el JS de Monitoring Code.'],
        'SALESMANAGO_OWNER'         => ['type' => 'text',     'label' => 'Owner email',       'help' => 'Email del usuario SM propietario de los contactos importados.'],
        'SALESMANAGO_ENCRYPTION'    => ['type' => 'select',   'label' => 'Encryption algorithm','help' => 'Algoritmo de firma. Mantener SHA-1 salvo que SM indique otro.',
                                        'options' => ['SHA-1' => 'SHA-1', 'AES' => 'AES']],

        // --- Bloque: Eventos a enviar ---
        'SALESMANAGO_SEND_CONTACT_UPSERT' => ['type' => 'bool', 'label' => 'Enviar contactos (alta/edición cuenta)',   'help' => 'Sincroniza el alta y la edición de cuenta de cliente a SM.'],
        'SALESMANAGO_SEND_PURCHASE'       => ['type' => 'bool', 'label' => 'Enviar compras (PURCHASE)',                'help' => 'Tras cada pedido confirmado, encola un evento PURCHASE.'],
        'SALESMANAGO_SEND_CART'           => ['type' => 'bool', 'label' => 'Enviar carritos abandonados (CART)',       'help' => 'Cron periódico: detecta carritos con > 30 min de inactividad y los envía a SM.'],
        'SALESMANAGO_PRODUCT_ID_FIELD'    => ['type' => 'select','label' => 'ID de producto a enviar',                  'help' => 'Debe coincidir con el ID del Product Feed XML.',
                                              'options' => ['products_model' => 'products_model', 'products_id' => 'products_id (recomendado, coincide con el feed comparador.txt)']],
        'SALESMANAGO_LOCATION'            => ['type' => 'text', 'label' => 'Location (identificador tienda)',          'help' => 'Solo a-z, A-Z, 0-9, _. Ej: <code>francobordo_web</code>'],
        'SALESMANAGO_TIMEOUT'             => ['type' => 'number','label' => 'Timeout HTTP (s)',                         'help' => 'Tiempo máximo de cada llamada API. Default: 5.'],
        'SALESMANAGO_MAX_ATTEMPTS'        => ['type' => 'number','label' => 'Reintentos máximos del worker',            'help' => 'Tras N fallos, el evento queda como dead. Default: 8.'],
    ];
}

function sm_v(string $key): string
{
    return defined($key) ? (string) constant($key) : '';
}

function sm_render_field(string $key, array $def): void
{
    $val   = sm_v($key);
    $label = htmlspecialchars($def['label']);
    $help  = $def['help'] ?? '';

    echo '<div class="xline xline-dashed"></div>';
    echo '<label for="' . $key . '" class="column a02 tright inline">' . $label . ':</label>';
    echo '<div class="column a10">';

    switch ($def['type']) {
        case 'bool':
            $checked = ($val === 'true' || $val === '1') ? 'checked="checked"' : '';
            echo '<input type="hidden" name="' . $key . '" value="false"/>';
            echo '<input type="checkbox" name="' . $key . '" id="' . $key . '" value="true" ' . $checked . '/>';
            echo '<label for="' . $key . '"><span></span></label>';
            break;

        case 'password':
            echo '<input type="password" name="' . $key . '" id="' . $key . '" value="' . htmlspecialchars($val, ENT_QUOTES) . '" autocomplete="new-password"/>';
            echo '<label for="' . $key . '"><span></span></label>';
            break;

        case 'number':
            echo '<input type="number" name="' . $key . '" id="' . $key . '" value="' . htmlspecialchars($val, ENT_QUOTES) . '" min="0"/>';
            echo '<label for="' . $key . '"><span></span></label>';
            break;

        case 'select':
            echo '<select name="' . $key . '" id="' . $key . '">';
            foreach (($def['options'] ?? []) as $optV => $optLabel) {
                $sel = ($val === (string)$optV) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($optV, ENT_QUOTES) . '"' . $sel . '>' . htmlspecialchars($optLabel) . '</option>';
            }
            echo '</select>';
            break;

        case 'text':
        default:
            echo '<input type="text" name="' . $key . '" id="' . $key . '" value="' . htmlspecialchars($val, ENT_QUOTES) . '"/>';
            echo '<label for="' . $key . '"><span></span></label>';
            break;
    }

    if ($help !== '') {
        echo '<div class="DFhelp">' . $help . '</div>';
    }
    echo '</div>';
}

// ---------------------------------------------------------------------------
// CSV export — query + helpers
// ---------------------------------------------------------------------------

/**
 * Common FROM + WHERE clause for the export.
 * If $months > 0, applies year filter; if 0, no year filter (cap does the cutting).
 */
function sm_export_from_where(?int $months = null): string
{
    if ($months === null) $months = sm_export_months();
    $yearClause = ($months > 0)
        ? "AND ( c.customers_newsletter = '1'
                OR o.last_order >= DATE_SUB(NOW(), INTERVAL " . (int) $months . " MONTH) )"
        : "";

    return " FROM customers c
            LEFT JOIN address_book ab ON ab.address_book_id = c.customers_default_address_id
            LEFT JOIN countries     co ON co.countries_id = ab.entry_country_id
            LEFT JOIN zones         z  ON z.zone_id       = ab.entry_zone_id
            LEFT JOIN customers_groups cg ON cg.customers_group_id = c.customers_group_id
            LEFT JOIN (
              SELECT customers_id, MAX(date_purchased) AS last_order
              FROM orders GROUP BY customers_id
            ) o ON o.customers_id = c.customers_id
            WHERE c.sm_excluded = 0
              AND c.customers_email_address LIKE '%@%.%'
              $yearClause";
}

/** Live breakdown for the stats panel. */
function sm_export_stats(?int $cap = null, ?int $months = null): array
{
    if ($cap    === null) $cap    = sm_export_cap();
    if ($months === null) $months = sm_export_months();

    $q1 = tep_db_query('SELECT COUNT(*) AS n,
                               SUM(c.customers_newsletter=\'1\') AS opt_in'
                       . sm_export_from_where($months));
    $r1 = tep_db_fetch_array($q1);

    $q2 = tep_db_query('SELECT COUNT(*) AS n FROM customers WHERE sm_excluded=1');
    $r2 = tep_db_fetch_array($q2);

    $total = (int) $r1['n'];
    $optin = (int) $r1['opt_in'];

    return [
        'total'       => $total,
        'opt_in'      => $optin,
        'no_optin'    => $total - $optin,
        'excluded'    => (int) $r2['n'],
        'cap'         => $cap,
        'in_export'   => min($total, $cap),
        'cut'         => max(0, $total - $cap),
        'months_back' => $months,
    ];
}

/** Stream the CSV directly to the browser. Exits when done. */
function sm_stream_export_csv(): void
{
    @set_time_limit(300);
    @ini_set('display_errors', '0');           // PHP 8.4+ deprecation noise must NOT leak into the CSV
    error_reporting(E_ERROR | E_PARSE);
    while (ob_get_level() > 0) ob_end_clean();

    $filename = 'salesmanago_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Cache-Control: no-store, max-age=0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM (Excel)
    $out = fopen('php://output', 'w');
    // Explicit args silence PHP 8.4 deprecation about $escape.
    fputcsv($out, [
        'email','firstname','lastname','company','gender','birth_date',
        'country','region','city','postal_code','newsletter','customer_group'
    ], ';', '"', '');

    $select = "SELECT
        c.customers_email_address                                       AS email,
        c.customers_firstname                                           AS firstname,
        c.customers_lastname                                            AS lastname,
        COALESCE(ab.entry_company, '')                                  AS company,
        CASE c.customers_gender WHEN 'm' THEN 'M' WHEN 'f' THEN 'F' ELSE '' END AS gender,
        CASE WHEN c.customers_dob IS NULL OR c.customers_dob = '0000-00-00 00:00:00'
             THEN '' ELSE DATE_FORMAT(c.customers_dob, '%d/%m/%Y') END  AS birth_date,
        COALESCE(co.countries_iso_code_2, '')                           AS country,
        COALESCE(z.zone_code, ab.entry_state, '')                       AS region,
        COALESCE(ab.entry_city, '')                                     AS city,
        COALESCE(ab.entry_postcode, '')                                 AS postal_code,
        IF(c.customers_newsletter='1', 'yes', 'no')                     AS newsletter,
        COALESCE(cg.customers_group_name, '')                           AS customer_group";

    $cap    = sm_export_cap();
    $months = sm_export_months();
    $tail   = ' ORDER BY o.last_order IS NULL, o.last_order DESC, c.customers_id DESC
                LIMIT ' . $cap;

    $res = tep_db_query($select . sm_export_from_where($months) . $tail);
    $n = 0;
    while ($row = tep_db_fetch_array($res)) {
        fputcsv($out, [
            $row['email'], $row['firstname'], $row['lastname'], $row['company'],
            $row['gender'], $row['birth_date'], $row['country'], $row['region'],
            $row['city'], $row['postal_code'], $row['newsletter'], $row['customer_group'],
        ], ';', '"', '');
        $n++;
        if (($n % 1000) === 0) @flush();
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------------
// Action dispatch
// ---------------------------------------------------------------------------

$sUrlPage    = 'salesmanago_config.php';
$sAction     = '';
if (isset($_POST['action']))      $sAction = tep_db_input($_POST['action']);
elseif (isset($_GET['action']))   $sAction = tep_db_input($_GET['action']);
$messageStack->style = 'solenopsis';
$defs = sm_field_defs();

// CSV export — runs BEFORE any HTML output
if ($sAction === 'export_csv') {
    sm_stream_export_csv();
    exit;
}

// Queue: revive dead events
if ($sAction === 'revive_dead') {
    require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
    $n = SalesManagoQueue::reviveDead();
    $messageStack->addSession('success', '🔄 ' . $n . ' eventos reencolados (status: dead → pending).', 'success');
    tep_redirect(tep_href_link($sUrlPage));
}

// Queue: clear completed events (housekeeping)
if ($sAction === 'purge_sent') {
    tep_db_query("DELETE FROM sm_event_queue WHERE status='sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $messageStack->addSession('success', '🧹 ' . (int) tep_db_affected_rows() . ' eventos enviados (>7d) eliminados.', 'success');
    tep_redirect(tep_href_link($sUrlPage));
}

if ($sAction === 'update') {
    $updated = 0;
    foreach ($defs as $key => $def) {
        if (!array_key_exists($key, $_POST)) continue;
        if ($def['type'] === 'bool') {
            $raw = ($_POST[$key] === 'true') ? 'true' : 'false';
        } else {
            $raw = (string) $_POST[$key];
            $raw = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
        }
        $esc = tep_db_input($raw);
        tep_db_query('UPDATE configuration SET configuration_value = "' . $esc . '"
                      WHERE configuration_key = "' . tep_db_input($key) . '"');
        $updated++;
    }

    if (class_exists(tools::class) && method_exists(tools::class, 'createCacheFile')) {
        tools::createCacheFile();
    } else {
        @unlink(DIR_FS_CATALOG . 'cache/cachefile.inc.php');
    }

    // Forzar invalidación inmediata de OPcache para que el redirect siguiente
    // no sirva el bytecode viejo (revalidate_freq=2 introduce una ventana
    // donde valores recién guardados aparecen como no guardados).
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(DIR_FS_CATALOG . 'cache/cachefile.inc.php', true);
    }

    $messageStack->addSession('success', '✅ Configuración guardada (' . $updated . ' campos). El caché se ha refrescado.', 'success');
    tep_redirect(tep_href_link($sUrlPage));
}

if ($sAction === 'test') {
    require_once DIR_FS_CATALOG . 'includes/classes/SalesManago.php';

    $cfg = [
        'endpoint'  => $_POST['SALESMANAGO_ENDPOINT']   ?? sm_v('SALESMANAGO_ENDPOINT'),
        'clientId'  => $_POST['SALESMANAGO_CLIENT_ID']  ?? sm_v('SALESMANAGO_CLIENT_ID'),
        'apiKey'    => $_POST['SALESMANAGO_API_KEY']    ?? sm_v('SALESMANAGO_API_KEY'),
        'apiSecret' => $_POST['SALESMANAGO_API_SECRET'] ?? sm_v('SALESMANAGO_API_SECRET'),
        'owner'     => $_POST['SALESMANAGO_OWNER']      ?? sm_v('SALESMANAGO_OWNER'),
        'timeout'   => 8,
    ];

    $sm = new SalesManago($cfg);
    if (!$sm->isConfigured()) {
        $messageStack->addSession('test', '⚠️ Faltan credenciales para probar (rellena Endpoint, Client ID, API Key, API Secret y Owner).', 'warning');
    } else {
        $r = $sm->ping();
        if ($r['ok']) {
            $contacts = isset($r['body']['contacts']) ? count($r['body']['contacts']) : 0;
            $messageStack->addSession('test',
                '✅ <strong>Conexión OK</strong> con <code>' . htmlspecialchars($cfg['endpoint']) . '</code>. '
                . 'Auth válida. Contactos encontrados para el owner: ' . $contacts . '.', 'success');
        } else {
            $messageStack->addSession('test',
                '❌ <strong>Fallo de conexión</strong>: ' . htmlspecialchars($r['error'])
                . ' (HTTP ' . (int)$r['http'] . ')<br><small>Respuesta cruda: <code>'
                . htmlspecialchars(mb_substr((string)$r['raw'], 0, 300)) . '</code></small>', 'error');
        }
    }
    tep_redirect(tep_href_link($sUrlPage));
}

// ---------------------------------------------------------------------------
// Pre-compute stats for the panel (only on default render)
// ---------------------------------------------------------------------------
$exportStats = sm_export_stats();

require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
$queueStats    = SalesManagoQueue::stats();
$queueFailures = SalesManagoQueue::recentFailures(10);

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------

include 'theme/solenopsis/html/header.php';
?>

<div class="oeHead column a12 row ax amiddle">
    <div class="oeTitu column a05" style="padding-left: 20px;">
        <b><i class="fa fa-bullhorn"></i> Sales Manago</b>
        <small>Configuración de la integración con SalesManago.com</small>
    </div>
    <div class="oeButton column a07 dtright">
        <a class="xbutton hv8 small" href="javascript:void(0)" id="sm-test-btn" title="Probar conexión">
            <i class="fa fa-plug"></i> Probar conexión
        </a>
        <a class="xbutton hv8 small verde" href="javascript:void(0)" id="sm-save-btn" title="Guardar">
            <i class="fa fa-save"></i> Guardar
        </a>
    </div>
</div>

<?php echo $messageStack->output('test'); ?>
<?php echo $messageStack->output('success'); ?>

<form method="post" id="sm-form" action="<?php echo tep_href_link($sUrlPage); ?>">
    <input type="hidden" name="action" id="sm-form-action" value="update"/>

    <div class="oeBox column a12 row ax">
        <div class="oeWrpr">
            <div class="oeTitu"><i class="fas fa-power-off"></i> Activación</div>
            <div class="oeCntd row ax xform xform-horizontal">
                <?php
                foreach (['SALESMANAGO_STATUS','SALESMANAGO_JS_TRACKING','SALESMANAGO_JS_POPUPS','SALESMANAGO_JS_REQUIRE_CONSENT'] as $k) {
                    sm_render_field($k, $defs[$k]);
                }
                ?>
            </div>
        </div>
    </div>

    <div class="oeBox column a12 row ax">
        <div class="oeWrpr">
            <div class="oeTitu"><i class="fas fa-key"></i> API access details</div>
            <div class="oeCntd row ax xform xform-horizontal">
                <?php
                foreach (['SALESMANAGO_ENDPOINT','SALESMANAGO_CLIENT_ID','SALESMANAGO_INSTANCE_ID',
                          'SALESMANAGO_API_KEY','SALESMANAGO_API_SECRET','SALESMANAGO_MICROSITE_KEY',
                          'SALESMANAGO_OWNER','SALESMANAGO_ENCRYPTION'] as $k) {
                    sm_render_field($k, $defs[$k]);
                }
                ?>
            </div>
        </div>
    </div>

    <div class="oeBox column a12 row ax">
        <div class="oeWrpr">
            <div class="oeTitu"><i class="fas fa-paper-plane"></i> Eventos a enviar</div>
            <div class="oeCntd row ax xform xform-horizontal">
                <?php
                foreach (['SALESMANAGO_SEND_CONTACT_UPSERT','SALESMANAGO_SEND_PURCHASE','SALESMANAGO_SEND_CART',
                          'SALESMANAGO_PRODUCT_ID_FIELD','SALESMANAGO_LOCATION',
                          'SALESMANAGO_TIMEOUT','SALESMANAGO_MAX_ATTEMPTS'] as $k) {
                    sm_render_field($k, $defs[$k]);
                }
                ?>
            </div>
        </div>
    </div>
</form>

<!-- ===================== Exportar contactos ===================== -->
<div class="oeBox column a12 row ax">
    <div class="oeWrpr">
        <div class="oeTitu"><i class="fas fa-file-csv"></i> Exportar contactos a Sales Manago</div>
        <div class="oeCntd row ax" style="padding: 18px 22px;">
            <?php $curCap = sm_export_cap(); $curMonths = sm_export_months(); ?>
            <form method="get" action="<?php echo tep_href_link($sUrlPage); ?>" style="display: contents;">
            <div class="column a07" style="padding-right: 20px;">
                <p><b>Parámetros:</b></p>
                <table style="width:100%; line-height: 1.8;">
                    <tr>
                        <td style="padding:4px;"><label for="sm-cap">Cap (filas máx):</label></td>
                        <td style="padding:4px;">
                            <input type="number" id="sm-cap" name="cap" min="1" max="50000" value="<?php echo $curCap; ?>" style="width: 120px;">
                            <small style="color:#888;">Máximo SM: 50.000</small>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px;"><label for="sm-months">Filtrar compras (meses):</label></td>
                        <td style="padding:4px;">
                            <input type="number" id="sm-months" name="months" min="0" max="240" value="<?php echo $curMonths; ?>" style="width: 120px;">
                            <small style="color:#888;">0 = sin filtro · 60 = 5 años · 48 = 4 años</small>
                        </td>
                    </tr>
                </table>
                <p style="margin: 12px 0;">
                    <button type="submit" class="xbutton hv8 small"><i class="fa fa-sync"></i> Recalcular stats</button>
                    <a href="<?php echo tep_href_link($sUrlPage); ?>" class="xbutton hv8 small" style="background:#eee; color:#333;">Restablecer (49995, 60m)</a>
                </p>
                <p style="font-size: 12px; color:#666; margin-top: 14px;"><b>Filtro:</b>
                    <?php if ($curMonths > 0): ?>
                        <code>newsletter=1</code> <b>O</b> compra ≤ <?php echo (int)round($curMonths/12, 1); ?> años · <code>sm_excluded=0</code>
                    <?php else: ?>
                        <code>sm_excluded=0</code> · sin filtro por año (cap por recencia hace de corte natural)
                    <?php endif; ?>
                </p>
                <p style="font-size: 12px; color:#666;"><b>Orden:</b> compradores más recientes primero. Sin-compra al final.</p>
                <p style="font-size: 12px; color:#666;"><b>Columnas:</b> email · firstname · lastname · company · gender · birth_date · country · region · city · postal_code · newsletter</p>
            </div>
            <div class="column a05">
                <table class="oeTabl" style="width:100%; border-collapse: collapse;">
                    <tr><td style="padding:6px 4px;">Total candidatos</td>
                        <td style="text-align:right; padding:6px 4px;"><b><?php echo number_format($exportStats['total']); ?></b></td></tr>
                    <tr><td style="padding:4px 4px 4px 22px; color:#555;">↳ Suscritos (opt-in)</td>
                        <td style="text-align:right; padding:4px;"><?php echo number_format($exportStats['opt_in']); ?></td></tr>
                    <tr><td style="padding:4px 4px 4px 22px; color:#555;">↳ Compradores no-opt-in</td>
                        <td style="text-align:right; padding:4px;"><?php echo number_format($exportStats['no_optin']); ?></td></tr>
                    <tr><td style="padding:6px 4px; border-top: 1px dashed #ccc;">Excluidos manualmente</td>
                        <td style="text-align:right; padding:6px 4px; border-top: 1px dashed #ccc;"><?php echo number_format($exportStats['excluded']); ?></td></tr>
                    <tr><td style="padding:8px 4px; border-top: 2px solid #333;"><b>Filas en CSV</b></td>
                        <td style="text-align:right; padding:8px 4px; border-top: 2px solid #333;"><b><?php echo number_format($exportStats['in_export']); ?></b></td></tr>
                    <?php if ($exportStats['cut'] > 0): ?>
                    <tr><td style="padding:4px; color:#a55;">Cortados por cap</td>
                        <td style="text-align:right; padding:4px; color:#a55;">-<?php echo number_format($exportStats['cut']); ?></td></tr>
                    <?php endif; ?>
                </table>
                <div style="margin-top: 20px; text-align: center;">
                    <?php $dlParams = http_build_query(['action'=>'export_csv','cap'=>$curCap,'months'=>$curMonths]); ?>
                    <a href="<?php echo tep_href_link($sUrlPage, $dlParams); ?>"
                       class="xbutton hv8 verde" style="padding: 12px 22px;">
                        <i class="fa fa-download"></i>&nbsp; Descargar CSV (<?php echo number_format($exportStats['in_export']); ?> filas)
                    </a>
                </div>
                <p style="color: #888; font-size: 11px; margin-top: 18px; text-align: center;">Para excluir clientes bounce/baja:<br>
                    <code style="font-size: 10px;">UPDATE customers SET sm_excluded=1, sm_excluded_at=NOW(), sm_excluded_reason='bounce' WHERE customers_email_address IN (...)</code>
                </p>
            </div>
            </form>
        </div>
    </div>
</div>
<!-- =============================================================== -->

<!-- ===================== Cola de eventos ===================== -->
<div class="oeBox column a12 row ax">
    <div class="oeWrpr">
        <div class="oeTitu"><i class="fas fa-stream"></i> Cola de eventos (sm_event_queue)</div>
        <div class="oeCntd row ax" style="padding: 18px 22px;">
            <div class="column a05" style="padding-right: 20px;">
                <table class="oeTabl" style="width:100%; border-collapse: collapse;">
                    <tr><td style="padding:6px 4px;">Pendientes</td>
                        <td style="text-align:right; padding:6px 4px; color:#0a7;"><b><?php echo number_format($queueStats['pending']); ?></b></td></tr>
                    <tr><td style="padding:6px 4px;">En proceso</td>
                        <td style="text-align:right; padding:6px 4px; color:#0a7;"><?php echo number_format($queueStats['sending']); ?></td></tr>
                    <tr><td style="padding:6px 4px;">Enviados OK</td>
                        <td style="text-align:right; padding:6px 4px; color:#666;"><?php echo number_format($queueStats['sent']); ?></td></tr>
                    <tr><td style="padding:6px 4px; color:#a55;">Con error (reintentando)</td>
                        <td style="text-align:right; padding:6px 4px; color:#a55;"><b><?php echo number_format($queueStats['failed']); ?></b></td></tr>
                    <tr><td style="padding:6px 4px; color:#c33;">Muertos (max attempts)</td>
                        <td style="text-align:right; padding:6px 4px; color:#c33;"><b><?php echo number_format($queueStats['dead']); ?></b></td></tr>
                    <tr><td style="padding:8px 4px; border-top: 2px solid #333;"><b>Total</b></td>
                        <td style="text-align:right; padding:8px 4px; border-top: 2px solid #333;"><b><?php echo number_format($queueStats['total']); ?></b></td></tr>
                </table>
                <div style="margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ($queueStats['dead'] > 0): ?>
                    <a href="<?php echo tep_href_link($sUrlPage, 'action=revive_dead'); ?>"
                       class="xbutton hv8 small"
                       onclick="return confirm('Reencolar los <?php echo (int) $queueStats['dead']; ?> eventos muertos?');">
                        <i class="fa fa-redo"></i> Reencolar muertos (<?php echo (int) $queueStats['dead']; ?>)
                    </a>
                    <?php endif; ?>
                    <?php if ($queueStats['sent'] > 0): ?>
                    <a href="<?php echo tep_href_link($sUrlPage, 'action=purge_sent'); ?>"
                       class="xbutton hv8 small"
                       onclick="return confirm('Eliminar eventos enviados de más de 7 días?');">
                        <i class="fa fa-broom"></i> Limpiar &gt; 7d
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="column a07">
                <p style="margin: 0 0 8px 0;"><b>Últimos errores</b> (10 más recientes):</p>
                <?php if (empty($queueFailures)): ?>
                <p style="color: #0a7;"><i class="fa fa-check-circle"></i> Sin errores recientes.</p>
                <?php else: ?>
                <table style="width:100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background:#f3f3f3;">
                            <th style="padding:4px; text-align:left;">ID</th>
                            <th style="padding:4px; text-align:left;">Tipo</th>
                            <th style="padding:4px; text-align:right;">Intentos</th>
                            <th style="padding:4px; text-align:right;">HTTP</th>
                            <th style="padding:4px; text-align:left;">Error</th>
                            <th style="padding:4px; text-align:left;">Estado</th>
                            <th style="padding:4px; text-align:left;">Cuándo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queueFailures as $f): ?>
                        <tr style="border-top: 1px solid #eee;">
                            <td style="padding:4px;"><?php echo (int) $f['id']; ?></td>
                            <td style="padding:4px;"><?php echo htmlspecialchars($f['event_type']); ?></td>
                            <td style="padding:4px; text-align:right;"><?php echo (int) $f['attempts']; ?></td>
                            <td style="padding:4px; text-align:right;"><?php echo (int) $f['last_http_code'] ?: '—'; ?></td>
                            <td style="padding:4px;"><?php echo htmlspecialchars(mb_substr((string) $f['last_error'], 0, 80)); ?></td>
                            <td style="padding:4px; color: <?php echo $f['status']==='dead' ? '#c33' : '#a55'; ?>;"><b><?php echo $f['status']; ?></b></td>
                            <td style="padding:4px; color: #666;"><?php echo $f['updated_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <p style="color:#888; font-size:11px; margin-top:12px;">
                    El cron del worker se ejecuta cada minuto:
                    <code>* * * * * curl https://www.francobordo.com/_admin/sm_worker.php?token=•••</code>
                </p>
            </div>
        </div>
    </div>
</div>
<!-- =============================================================== -->

<script>
(function(){
    var form   = document.getElementById('sm-form');
    var action = document.getElementById('sm-form-action');
    var saveBtn= document.getElementById('sm-save-btn');
    var testBtn= document.getElementById('sm-test-btn');
    if (saveBtn) saveBtn.addEventListener('click', function(){ action.value = 'update'; form.submit(); });
    if (testBtn) testBtn.addEventListener('click', function(){ action.value = 'test';   form.submit(); });
})();
</script>

<?php
include 'theme/solenopsis/html/footer.php';
?>
