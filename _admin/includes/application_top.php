<?php
if (defined('ADMIN_APPLICATION_TOP_LOADED')) return;
define('ADMIN_APPLICATION_TOP_LOADED', true);

// Mostrar todos los errores en .loc (antes de cargar nada)
if (str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.loc')) {
	ini_set('display_errors', 1);
	error_reporting(E_ALL);
}

// Librerias
use AddonDependencyInjection\Application\DependencyInjection;
use Oscdenox\Adapter\Configuration\Configuration;
use Oscdenox\Adapter\Session\SessionAdminMysqlInitializeBuild;
use Oscdenox\Core\Cookie\Application\CalculateLifeTime\CookieCalculateLifeTimeCommand;
use Oscdenox\Core\Cookie\Application\CalculateLifeTime\CookieCalculateLifeTimeCommandHandler;
use Oscdenox\Core\Cookie\Application\Intialize\CookieInitializeCommand;
use Oscdenox\Core\Cookie\Application\Intialize\CookieInitializeCommandHandler;
use util\authentication\Admin;
use util\event;

// Defines
define('PAGE_PARSE_START_TIME', microtime());
define('PROJECT_VERSION', 'oscDenox v3.0');
define('BOX_WIDTH', 0);
// Añadimos composer
include dirname(__FILE__) . '/../../includes/vendor/autoload.php';

// Incluimos las variables del servidor
include dirname(__FILE__) . '/../../includes/define.php';
(file_exists(dirname(__FILE__) . '/../../includes/configure.php') ? require(dirname(__FILE__) . '/../../includes/configure.php') : die('Falta el archivo configure.php'));

// Lista de archivos de proyecto
include DIR_WS_INCLUDES . 'filenames.php';

// Lista de tablas de base de datos
include dirname(__FILE__) . '/../../includes/database_tables.php';

// Define how do we update currency exchange rates
// Possible values are 'oanda' 'xe' or ''
define('CURRENCY_SERVER_PRIMARY', 'oanda');
define('CURRENCY_SERVER_BACKUP', 'xe');

// Funciones de base de datos
include_once dirname(__FILE__) . '/../../includes/functions/database.php';

// Configurar mysqli en modo error para PHP 8.1+ (DEP-05)
// Nota: no se usa MYSQLI_REPORT_STRICT porque tep_db_query() admin usa patron
// "mysqli_query() or tep_db_error()" que requiere que mysqli devuelva false en error
// en lugar de lanzar excepciones no capturadas.
mysqli_report(MYSQLI_REPORT_ERROR);

// make a connection to the database... now
$pdo = tep_db_connect();

// Crear los define
include dirname(__FILE__) . '/../../includes/configuration_cache_read.php';

// Librerias
include( '../includes/classes/affiliates.php' );
include DIR_WS_FUNCTIONS . 'general.php';
include DIR_WS_FUNCTIONS . 'html_output.php';
include DIR_WS_FUNCTIONS . 'orders.php';
include dirname(__FILE__) . '/../../includes/functions/password_funcs.php';
include dirname(__FILE__) . '/../../includes/functions/validations.php';
include DIR_WS_CLASSES . 'table_block.php';
include DIR_WS_CLASSES . 'box.php';
include DIR_WS_CLASSES . 'shopping_cart.php';
include DIR_WS_CLASSES . 'message_stack.php';
include DIR_WS_CLASSES . 'split_page_results.php';
include DIR_WS_CLASSES . 'object_info.php';
include DIR_WS_CLASSES . 'mime.php';
include dirname(__FILE__) . '/../../includes/classes/email.php';
include DIR_WS_CLASSES . 'upload.php';
include dirname(__FILE__) . '/../../includes/classes/errorBacktrace.php';
include dirname(__FILE__) . '/../../includes/modules/debugbar/includes/classes/debugbar.php';
include dirname(__FILE__) . '/../../includes/functions/sessions.php';
include_once dirname(__FILE__) . '/../../includes/classes/language.php';

// ErrorBacktrace
//$errorBacktrace = errorBacktrace::getInstance(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// Error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);


// DebugBar
$debugbar = debugbar::getInstance();

// Esteblecemos el tipo de petición (seguro o no)
$request_type = getenv('HTTPS') == 'on' ? 'SSL' : 'NONSSL';

// Establecemos PHP_SELF en ambito local
$PHP_SELF = $_SERVER['PHP_SELF'];

// Configuracion
$configurationAdapter = new Configuration();

// Cookie lifetime
$cookieLifeTimeBackOffice = $configurationAdapter->get('COOKIE_LIFETIME_BACK_OFFICE', 5);
$cookieCalculateLifeTimeCommandHandler = DependencyInjection::getInstance()->get(CookieCalculateLifeTimeCommandHandler::class);
$cookieLifetime = $cookieCalculateLifeTimeCommandHandler->__invoke(new CookieCalculateLifeTimeCommand($cookieLifeTimeBackOffice));

// Cookie
$cookieInitializeCommandHandler = DependencyInjection::getInstance()->get(CookieInitializeCommandHandler::class);
$cookie = $cookieInitializeCommandHandler->__invoke(new CookieInitializeCommand('dnxAdmin', $configurationAdapter->get('HTTPS_COOKIE_PATH'), $cookieLifetime, $configurationAdapter->check('request_type', 'SSL')));

// Cookie store
$cookieLifeTimeFrontOffice = $configurationAdapter->get('COOKIE_LIFETIME_FRONT_OFFICE', 5);
$cookieCalculateLifeTimeCommandHandler = DependencyInjection::getInstance()->get(CookieCalculateLifeTimeCommandHandler::class);
$cookieLifetime = $cookieCalculateLifeTimeCommandHandler->__invoke(new CookieCalculateLifeTimeCommand($cookieLifeTimeFrontOffice));
$cookieInitializeCommandHandler = DependencyInjection::getInstance()->get(CookieInitializeCommandHandler::class);
$cookieStore = $cookieInitializeCommandHandler->__invoke(new CookieInitializeCommand('dnxStore', realpath($configurationAdapter->get('HTTPS_COOKIE_PATH') . '..'), $cookieLifetime, $configurationAdapter->check('request_type', 'SSL')));

// Session
$sessionCore = (new SessionAdminMysqlInitializeBuild())();

// Extraemos todas las varaibles de session a variables globales
extract($_SESSION ?? [], EXTR_OVERWRITE + EXTR_REFS);

// Admin
$adminCore = new Admin();

// Autologin
$adminCore->autologin();

// Htmlpurifier
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.SafeIframe', true);
$config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed)%');
$purifier = new HTMLPurifier($config);

// Idioma
$lng ??= new language();
$lng->setRequestLanguage();
$lng->setLocale();

// Incluir las traducciones
include DIR_WS_LANGUAGES . $language . '.php';

// Incluir idioma de la pagina
$current_page = basename((string) $_SERVER['SCRIPT_FILENAME']);
if (file_exists(DIR_WS_LANGUAGES . $language . '/' . $current_page)) {
	include(DIR_WS_LANGUAGES . $language . '/' . $current_page);
}

// Inicializamos messagestack
$messageStack = new messageStack;

define('WARN_CONFIG_WRITEABLE', 'true');
define('WARN_SESSION_DIRECTORY_NOT_WRITEABLE', 'true');
define('WARN_SESSION_AUTO_START', 'true');
define('WARN_DOWNLOAD_DIRECTORY_NOT_READABLE', 'true');

// RGPD
include('../includes/classes/rgpd.php');
  $rgpd = new rgpd();

// Security
include('../includes/modules/security/includes/classes/security.php');
$dxSecurity = new denox\security();

// calculate category path
$cPath = $_GET['cPath'] ?? '';
$cPath_array = [];
if (tep_not_null($cPath)) {
	$cPath_array = tep_parse_category_path($cPath);
	$cPath = implode('_', $cPath_array);
	$current_category_id = $cPath_array[(count($cPath_array) - 1)];
} else {
	$current_category_id = 0;
}

// default open navigation box
if (!tep_session_is_registered('selected_box')) {
	tep_session_register('selected_box');
	$selected_box = 'configuration';
}

if (isset($_GET['selected_box'])) {
	$selected_box = $_GET['selected_box'];
}

// check if a default currency is set
if (!defined('DEFAULT_CURRENCY')) {
	$messageStack->add(ERROR_NO_DEFAULT_CURRENCY_DEFINED, 'error');
}

// check if a default language is set
if (!defined('DEFAULT_LANGUAGE')) {
	$messageStack->add(ERROR_NO_DEFAULT_LANGUAGE_DEFINED, 'error');
}

if (function_exists('ini_get') && ((bool)ini_get('file_uploads') == false)) {
	$messageStack->add(WARNING_FILE_UPLOADS_DISABLED, 'warning');
}

// Guard: redirigir a TOTP si sesion esta en estado pendiente de segundo factor (SEC-02)
if (
    isset($_SESSION['twofactor_pending_id'])
    && !tep_session_is_registered('login_id')
    && basename($_SERVER['SCRIPT_FILENAME']) !== FILENAME_TOTP_VERIFY
    && basename($_SERVER['SCRIPT_FILENAME']) !== FILENAME_LOGIN_ADMIN
) {
    tep_redirect(tep_href_link(FILENAME_TOTP_VERIFY, '', 'SSL'));
}

// check Login Admin Session
$excludedFilesFromLogin = [
	'recover_cart_sales_cron.php',
	'cron_transportistas.php',
	'customers_points_expire.php',
	'cron_maintenance.php',
	'sm_worker.php',                  // SalesManago queue worker (token-gated internally)
	'sm_cart_scanner.php',            // SalesManago abandoned-cart scanner (token-gated internally)
	FILENAME_LOGIN_ADMIN,
	FILENAME_PASSWORD_FORGOTTEN,
	FILENAME_FORBIDDEN,
	'ajax_funciones.php',
	FILENAME_TOTP_VERIFY,
];
if(	 basename($_SERVER['SCRIPT_FILENAME']) == 'ws_shipping.php' && isset($_GET['action']) && ($_GET['action'] == 'cron_status_expedition' || $_GET['action'] == 'generate_expedition')) {
	$excludedFilesFromLogin[] = "ws_shipping.php";
}
$doofinderCronCondition = basename($_SERVER['SCRIPT_FILENAME']) == 'addons.php' && isset($_GET['action']) && $_GET['action'] == 'cron_doofinder_feed';
$instagramCondition = isset($_GET['action']) && $_GET['action'] == 'instagram.php';
$salesmanagoCronCondition = basename($_SERVER['SCRIPT_FILENAME']) == 'addons.php' && isset($_GET['action']) && ($_GET['action'] == 'addon_salesmanago_feed_products_now_crud_post' || $_GET['action'] == 'addon_salesmanago_feed_customers_now_crud_post');
$oct8neCronCondition = isset($_GET['action']) && $_GET['action'] == 'cron_oct8ne_feed';
$googlesitemapCronCondition = basename($_SERVER['SCRIPT_FILENAME']) == 'addon_googlesitemap.php' && isset($_GET['action']) && $_GET['action'] == 'cron';

if (!in_array(basename($_SERVER['SCRIPT_FILENAME']), $excludedFilesFromLogin) && !$doofinderCronCondition && !$instagramCondition && !$salesmanagoCronCondition && !$oct8neCronCondition && !$googlesitemapCronCondition) {
	tep_admin_check_login();
}

// Guard: 2FA obligatorio con periodo de gracia — delegado al modulo 2fa-admin
require __DIR__ . '/modules/2fa-admin/actions/guard.php';

// Insertamos el log //
// Si tenemos usuario
if (LOG_ACTIVE && (isset($login_id) && $login_id != '' && !preg_match('/(log\.php)/i', (string) $_SERVER['REQUEST_URI']) && !preg_match('/(order\_checker\.php)/i', (string) $_SERVER['REQUEST_URI']))) {
	// Variables
	$sGet = false;
	$sPost = false;

	// Obtenemos los valores GET
	if (count($_GET) > 0) {
		// Pasamos a JSON
		$sGet = json_encode($_GET);

		// Si tenemos un password lo limpiamos
		if (preg_match('/password/i', $sGet)) {
            $sGet = false;
        }
	}
	// Obtenemos los valores POST
	if (count($_POST) > 0) {
		// Pasamos a JSON
		$sPost = json_encode($_POST);

		// Si tenemos un password lo limpiamos
		if (preg_match('/password/i', $sPost)) {
            $sPost = false;
        }
	}

	// Obtenemos los datos del usuario
	$aUser = tep_db_query('SELECT admin_firstname, admin_lastname FROM admin WHERE admin_id = "' . $login_id . '";');
	$aUser = tep_db_fetch_array($aUser);

	// Insertamos en BD
		tep_db_query( 'INSERT INTO log (admin, ip, log, `get`, `post`) VALUES ("' . implode( ' ', array_map( 'ucfirst', explode( ' ', strtolower( $aUser['admin_firstname'] . ' ' . $aUser['admin_lastname'] ) ) ) ) . '", "' . $_SERVER['REMOTE_ADDR'] . '", "' . tep_db_input( 'El administrador <b>' . $aUser['admin_firstname'] . ' ' . $aUser['admin_lastname'] . '</b> ha accedido a <b><a href="' . $_SERVER['REQUEST_URI'] . '" title="' . $_SERVER['REQUEST_URI'] . '" target="_blank">' . $_SERVER['REQUEST_URI'] . '</a></b>.' ) . '", ' . ($sGet !== false ? '"' . tep_db_input( $sGet ) . '"' : 'NULL') . ', ' . ($sPost !== false ? '"' . tep_db_input( $sPost ) . '"' : 'NULL') . ');' );

	// Si tenemos límite en el número de registros del log
	if (LOG_RECORDS != -1) {
		//Declaramos variable vacía
		$sIDs = NULL;

		// Eliminamos los registros sobrantes
		$aIDs = tep_db_query('SELECT log_id FROM log ORDER BY log_date DESC LIMIT ' . LOG_RECORDS);
		while ($aID = tep_db_fetch_array($aIDs))
			$sIDs .= $aID['log_id'] . ', ';
		$sIDs = substr((string) $sIDs, 0, -2);
		tep_db_query('DELETE FROM log WHERE log_id NOT IN (' . $sIDs . ');');
	}
}
// FIN Log

if (!tep_session_is_registered('admin_menu_left')) {
	tep_session_register('admin_menu_left');
	$admin_menu_left = (defined('ADMIN_MENU_LEFT') && ADMIN_MENU_LEFT == 'Plegado' ? 'menu-minz' : '');
}

// Acciones globales
if (isset($_GET['action_global'])) {
	switch (tep_db_prepare_input($_GET['action_global'])) {
		case 'admin_menu_left':
			$admin_menu_left = (tep_db_prepare_input($_GET['value']) == 'Plegado' ? 'menu-minz' : '');
			session_write_close();
			exit();

		case 'message_alert_denox':
			// Obtenemos numero
			$aRowChecksum = json_decode((string) pharaonix_queryOne('SELECT configuration_value FROM configuration WHERE configuration_key = "MESSAGE_ALERT_DENOX"')->records['configuration_value'], true);
			tep_db_perform('configuration', ['configuration_value' => json_encode(['checksum' => $aRowChecksum['checksum'], 'change' => false])], 'update', 'configuration_key = "MESSAGE_ALERT_DENOX"');
			exit();
	}
}

  include('includes/application_top_support.php');

// Evento
event::getInstance()->execute('back_office_application_top_after');
