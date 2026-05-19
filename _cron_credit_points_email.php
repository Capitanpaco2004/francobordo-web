<?php
/**
 * Endpoint HTTPS para enviar email al cliente cuando el cron Windows
 * (sync_garan_status.py) acredita puntos al cerrar una garantía en QFac.
 *
 * Auth: token compartido en configuration.CRON_POINTS_EMAIL_TOKEN.
 * Llamado vía POST con: token, customer_id, points_delta, comment, orders_id (opcional).
 *
 * Reutiliza tep_send_points_notification() del sistema de puntos para que la plantilla
 * HTML del email sea idéntica a la del resto del sistema. Bilingüe (ES/EN) según
 * customers.customers_language_id.
 *
 * Respuesta: JSON {ok, message}.
 */

// === Pre-bootstrap: detectar idioma del cliente ANTES de cargar application_top ===
// Para que application_top.php cargue los lang files en el idioma correcto, hay que
// setear $_GET['language'] = directory_name antes del require. Hacemos un mini bootstrap
// para consultar customers_language_id sin pasar por application_top.
$_cron_cID = (int) ($_POST['customer_id'] ?? 0);
if ($_cron_cID > 0) {
    // Parse manual de configure.php (NO require) para no redefinir constantes que
    // application_top.php cargará después. Evita warnings "Constant already defined".
    $_cfg = @file_get_contents(__DIR__ . '/includes/configure.php');
    if ($_cfg !== false
        && preg_match("/'DB_SERVER',\\s*'([^']+)'/",          $_cfg, $_mh)
        && preg_match("/'DB_SERVER_USERNAME',\\s*'([^']+)'/", $_cfg, $_mu)
        && preg_match("/'DB_SERVER_PASSWORD',\\s*'([^']+)'/", $_cfg, $_mp)
        && preg_match("/'DB_DATABASE',\\s*'([^']+)'/",        $_cfg, $_md)) {
        $_link = @mysqli_connect($_mh[1], $_mu[1], $_mp[1], $_md[1]);
        if ($_link) {
            mysqli_set_charset($_link, 'utf8mb4');
            // OJO: application_top → language::setLanguage() indexa catalogLanguages por CODE
            // ('es'/'en'), NO por directory ('espanol'/'english'). Por eso pasamos el code.
            $_r = mysqli_query($_link, "SELECT l.code AS lang_code
                FROM customers c LEFT JOIN languages l ON l.languages_id = c.customers_language_id
                WHERE c.customers_id = " . $_cron_cID . " LIMIT 1");
            if ($_row = mysqli_fetch_assoc($_r)) {
                if (!empty($_row['lang_code']) && in_array($_row['lang_code'], ['es', 'en'], true)) {
                    $_GET['language'] = $_row['lang_code'];
                }
            }
            mysqli_close($_link);
        }
    }
}
unset($_cron_cID, $_cfg, $_mh, $_mu, $_mp, $_md, $_link, $_r, $_row);

require('includes/application_top.php');

// === Override de strings legacy bilingüe ===
// PHP usa la PRIMERA llamada a define() — definirlas aquí antes del require lang admin.
// El idioma efectivo lo trae $language (puesto por application_top desde $_GET['language']).
$_is_en = ($language === 'english');
define('EMAIL_TEXT_INTRO', $_is_en
    ? 'We are sending you this email to inform you that the points for the return of your order have been activated'
    : 'Le enviamos este correo para informarle de que los puntos por la devolución de su pedido han sido activados');
define('EMAIL_TEXT_COMMENT', $_is_en ? 'Detail: %s' : 'Detalle: %s');
unset($_is_en);

// Constantes de plantilla del email de puntos:
//  - EMAIL_GREET_* viven en frontend (create_account.php) — ya en idioma correcto
//  - EMAIL_TEXT_BALANCE / TABLE_HEADING_* viven solo en lang admin (customers_points.php)
//    pero solo existe la versión ESPAÑOLA del admin. Para EN definimos las constantes
//    inline (la app admin no necesita inglés porque la usan operadores españoles).
require_once(DIR_WS_LANGUAGES . $language . '/create_account.php');
$_admin_lang_path = DIR_FS_CATALOG . '_admin/includes/languages/' . $language . '/';
if (is_dir($_admin_lang_path)) {
    require_once($_admin_lang_path . 'customers_points.php');
    require_once($_admin_lang_path . 'customers_points_pending.php');
}
unset($_admin_lang_path);

// Fallback inline en INGLÉS para las constantes que solo existen en _admin/.../espanol/
// (definidas tras los require_once → si ya están definidas, define() las ignora)
if ($language === 'english') {
    @define('EMAIL_TEXT_SUBJECT',        'Points Account Update');
    @define('EMAIL_TEXT_BALANCE',        '· Your current balance is <b style="color: #22a1d1;">%s Points</b> valued at %s<br>');
    @define('EMAIL_TEXT_EXPIRE',         '· Your points will expire on: %s<br>');
    @define('EMAIL_TEXT_POINTS_URL',     '· This is the link to view your Points Account:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
    @define('EMAIL_TEXT_POINTS_URL_HELP','· You can find all the information about the Points System at the following link:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
    @define('EMAIL_TEXT_SUCCESS_POINTS', 'The Points are available in your account; on your next order you can use them as part of the payment<br>');
    @define('EMAIL_TEXT_ORDER_NUMBER',   'Order N&deg;:');
    @define('TABLE_HEADING_POINTS',      'Points');
    @define('TABLE_HEADING_POINTS_VALUE','Value');
    @define('TABLE_HEADING_DATE_ADDED',  'Date:');
    @define('TABLE_HEADING_POINTS_TYPE', 'Points Concept:');
    @define('TABLE_HEADING_PRODUCTS_NAME','Product:');
    @define('EMAIL_CONTACT',             'If you have any query or need help with any of our online services, please send us<br>an e-mail to: <b style="color: #22a1d1;">' . STORE_OWNER_EMAIL_ADDRESS . '</b><br>');
    @define('EMAIL_SEPARATOR',           '------------------------------------------------------');
    @define('EMAIL_AUTO',                'This is an automated response, please do not reply to it.');
}
if (!class_exists('currencies', false)) {
    require(DIR_WS_CLASSES . 'currencies.php');
}
$currencies = new currencies();

// Shims/alias (las funciones/constantes existen en admin pero no en frontend)
if (!function_exists('tep_catalog_href_link')) {
    function tep_catalog_href_link($page = '', $parameters = '', $connection = 'NONSSL') {
        return tep_href_link($page, $parameters, $connection);
    }
}
if (!defined('FILENAME_CATALOG_MY_POINTS') && defined('FILENAME_MY_POINTS')) {
    define('FILENAME_CATALOG_MY_POINTS', FILENAME_MY_POINTS);
}
if (!defined('FILENAME_CATALOG_MY_POINTS_HELP') && defined('FILENAME_MY_POINTS_HELP')) {
    define('FILENAME_CATALOG_MY_POINTS_HELP', FILENAME_MY_POINTS_HELP);
}
if (!defined('DIR_FS_DOCUMENT_ROOT') && defined('DIR_FS_CATALOG')) {
    define('DIR_FS_DOCUMENT_ROOT', DIR_FS_CATALOG);
}

header('Content-Type: application/json; charset=utf-8');

// Auth — comparación constant-time
$tokenRecibido = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenEsperado = defined('CRON_POINTS_EMAIL_TOKEN') ? (string) CRON_POINTS_EMAIL_TOKEN : '';
if ($tokenEsperado === '' || strlen($tokenRecibido) !== strlen($tokenEsperado) || !hash_equals($tokenEsperado, $tokenRecibido)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Auth fallida']);
    exit;
}

$cID         = (int) ($_POST['customer_id'] ?? 0);
$pointsDelta = (int) ($_POST['points_delta'] ?? 0);
$sComment    = trim((string) ($_POST['comment'] ?? ''));
$ordersId    = (int) ($_POST['orders_id'] ?? 0);

if ($cID <= 0 || $pointsDelta === 0) {
    echo json_encode(['ok' => false, 'message' => 'customer_id o points_delta inválidos']);
    exit;
}

// Datos del cliente
$qC = tep_db_query("
    SELECT customers_id, customers_gender, customers_firstname, customers_lastname,
           customers_email_address, customers_shopping_points, customers_points_expires
    FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID . " LIMIT 1
");
$c = tep_db_fetch_array($qC);
if (!$c || empty($c['customers_email_address'])) {
    echo json_encode(['ok' => false, 'message' => 'Cliente no encontrado o sin email']);
    exit;
}

// Mensaje de caducidad si POINTS_AUTO_EXPIRES está activo
$expireMsg = (defined('POINTS_AUTO_EXPIRES') && tep_not_null(POINTS_AUTO_EXPIRES) && tep_not_null($c['customers_points_expires']))
    ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($c['customers_points_expires']))
    : null;

$emailData = [
    'customer_id'         => (int) $c['customers_id'],
    'customers_email'     => $c['customers_email_address'],
    'customers_gender'    => $c['customers_gender'],
    'customers_firstname' => $c['customers_firstname'],
    'customers_lastname'  => $c['customers_lastname'],
    'points_delta'        => $pointsDelta,
    'new_balance'         => (int) round((float) $c['customers_shopping_points']),
    'subject'             => defined('EMAIL_TEXT_SUBJECT') ? EMAIL_TEXT_SUBJECT : 'Points account update',
    'comment'             => $sComment,
    'expire_msg'          => $expireMsg,
];
if ($ordersId > 0) {
    $emailData['extra'] = ['order_id' => $ordersId];
}

try {
    tep_send_points_notification($emailData);
    echo json_encode(['ok' => true, 'message' => 'Email enviado a ' . $c['customers_email_address'] . ' (lang=' . $language . ')']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error enviando: ' . $e->getMessage()]);
}
exit;
