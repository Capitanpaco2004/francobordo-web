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
 *   default              → render form
 *   action=update        → POST: save SALESMANAGO_* keys
 *   action=test          → POST: ping SM API and show result
 *
 * Created: 2026-05-18
 */

require_once 'includes/application_top.php';

use util\tools as tools;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Definition of every config field we manage from this page.
 * Order here drives the rendering order.
 */
function sm_field_defs(): array
{
    return [
        // --- Bloque: Activación ---
        'SALESMANAGO_STATUS'             => ['type' => 'bool',   'label' => 'Activar integración Sales Manago',          'help' => 'Interruptor maestro. Si está apagado, no se envía nada a SM aunque los eventos individuales estén activos.'],
        'SALESMANAGO_JS_TRACKING'        => ['type' => 'bool',   'label' => 'Activar Monitoring Code (JS de tracking)',   'help' => 'Inyecta el snippet JS de SM en el front para registrar visitas y crear cookie smclient.'],
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
                                              'options' => ['products_model' => 'products_model (recomendado)', 'products_id' => 'products_id']],
        'SALESMANAGO_LOCATION'            => ['type' => 'text', 'label' => 'Location (identificador tienda)',          'help' => 'Solo a-z, A-Z, 0-9, _. Ej: <code>francobordo_web</code>'],
        'SALESMANAGO_TIMEOUT'             => ['type' => 'number','label' => 'Timeout HTTP (s)',                         'help' => 'Tiempo máximo de cada llamada API. Default: 5.'],
        'SALESMANAGO_MAX_ATTEMPTS'        => ['type' => 'number','label' => 'Reintentos máximos del worker',            'help' => 'Tras N fallos, el evento queda como dead. Default: 8.'],
    ];
}

/** Get current value (constant or empty). */
function sm_v(string $key): string
{
    return defined($key) ? (string) constant($key) : '';
}

/** Render a single form row. */
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
            echo '<input type="hidden" name="' . $key . '" value="false"/>'; // baseline so unchecked = false
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
// Action dispatch
// ---------------------------------------------------------------------------

$sUrlPage    = 'salesmanago_config.php';
$sPostAction = isset($_POST['action']) ? tep_db_input($_POST['action']) : '';
$messageStack->style = 'solenopsis';
$defs = sm_field_defs();

if ($sPostAction === 'update') {
    $updated = 0;
    foreach ($defs as $key => $def) {
        if (!array_key_exists($key, $_POST)) continue;

        // Bool fields: the hidden baseline ensures we always get a value.
        // The actual checkbox value will override the hidden one if checked.
        if ($def['type'] === 'bool') {
            // PHP merges duplicates by keeping the last submitted value.
            // The checkbox (if checked) submits 'true' AFTER the hidden 'false',
            // so $_POST[$key] is already correct.
            $raw = ($_POST[$key] === 'true') ? 'true' : 'false';
        } else {
            $raw = (string) $_POST[$key];
            // Strip control chars but keep unicode (names, emails)
            $raw = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
        }

        $esc = tep_db_input($raw);
        tep_db_query('UPDATE configuration SET configuration_value = "' . $esc . '"
                      WHERE configuration_key = "' . tep_db_input($key) . '"');
        $updated++;
    }

    // Invalidate the config cache file so constants reload on next request.
    if (class_exists(tools::class) && method_exists(tools::class, 'createCacheFile')) {
        tools::createCacheFile();
    } else {
        @unlink(DIR_FS_CATALOG . 'cache/cachefile.inc.php');
    }

    $messageStack->addSession('success', '✅ Configuración guardada (' . $updated . ' campos). El caché se ha refrescado.', 'success');
    tep_redirect(tep_href_link($sUrlPage));
}

if ($sPostAction === 'test') {
    require_once DIR_FS_CATALOG . 'includes/classes/SalesManago.php';

    // Build a transient client with the current FORM values (not yet persisted)
    // so the user can test BEFORE saving.
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
                foreach (['SALESMANAGO_STATUS','SALESMANAGO_JS_TRACKING','SALESMANAGO_JS_REQUIRE_CONSENT'] as $k) {
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
