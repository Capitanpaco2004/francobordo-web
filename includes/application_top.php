<?php
if (defined('APPLICATION_TOP_LOADED')) return;
define('APPLICATION_TOP_LOADED', true);

// Librerias
use AddonDependencyInjection\Application\DependencyInjection;
use Oscdenox\Adapter\Configuration\Configuration;
use Oscdenox\Adapter\Session\SessionCustomerMysqlInitializeBuild;
use Oscdenox\Core\Cookie\Application\CalculateLifeTime\CookieCalculateLifeTimeCommand;
use Oscdenox\Core\Cookie\Application\CalculateLifeTime\CookieCalculateLifeTimeCommandHandler;
use Oscdenox\Core\Cookie\Application\Intialize\CookieInitializeCommand;
use Oscdenox\Core\Cookie\Application\Intialize\CookieInitializeCommandHandler;
use util\authentication\Customer;
use util\template;
use util\event;
use util\Query;
use util\tools;
use util\breadcrumb;

// Añadimos composer
require 'includes/vendor/autoload.php';

// Incluimos las variables del servidor
require('includes/define.php');
(file_exists('includes/configure.php') ? require('includes/configure.php') : die('Falta el archivo configure.php'));

// Scraper guard (anadido 2026-05-19): 403 a IPs autobaneadas (honeypot, heuristica)
require_once(DIR_WS_INCLUDES . "scraper_guard.php");

// Lista de archivos de proyecto
require(DIR_WS_INCLUDES . 'filenames.php');

// Lista de tablas de base de datos
require(DIR_WS_INCLUDES . 'database_tables.php');

// Funciones de base de datos
require_once(DIR_WS_FUNCTIONS . 'database.php');

// Configurar mysqli en modo excepcion para PHP 8.1+ (DEP-05)
// Nota: la capa principal usa PDO; esta configuracion cubre llamadas mysqli directas (ej. seo.class.php)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// make a connection to the database... now
$pdo = tep_db_connect();

// Crear los define
require('includes/configuration_cache_read.php');
// Librerias
include 'includes/classes/errorBacktrace.php';
include 'includes/classes/boxes.php';
include 'includes/classes/message_stack.php';
include 'includes/classes/preventDuplicates.php';
include 'includes/classes/wishlist.php';
include 'includes/classes/rgpd.php';
include 'includes/classes/shopping_cart.php';
include 'includes/classes/navigation_history.php';
include 'includes/classes/PriceFormatter.php';
include 'includes/classes/PriceFormatterStore.php';
include 'includes/classes/currencies.php';
include 'includes/classes/language.php';
include 'includes/classes/seo.class.php';
include 'includes/classes/split_page_results.php';
include 'includes/functions/general.php';
include 'includes/functions/html_output.php';
include 'includes/functions/sessions.php';
include 'includes/functions/whos_online.php';
include 'includes/functions/password_funcs.php';
include 'includes/functions/validations.php';
include 'includes/functions/banner.php';
include 'includes/functions/specials.php';
include 'theme/web/functions/functions.php';

// Incluimos partial de productos
include($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_THEME . 'html/partial/_product.php');

include 'includes/modules/security/includes/classes/security.php';
include 'includes/modules/debugbar/includes/classes/debugbar.php';

// Creamos los salt y hash de seguridad
if (!defined('SECURITY_KEY') || !defined('SECURITY_SALT')) {
	$securityKey = tools::createRandomValue(136);
	$securitySalt = tools::createRandomValue(32);

	$aConfigGroup = tools::insertConfigurationGroup('Seguridad', '', 0);
	tools::insertConfiguration('Key de seguridad', 'SECURITY_KEY', $securityKey, 'Key para la encriptación de datos', $aConfigGroup->records['configuration_group_id']);
	tools::insertConfiguration('Salt de seguridad', 'SECURITY_SALT', $securitySalt, 'Salt para la encriptación de datos', $aConfigGroup->records['configuration_group_id']);
	tools::createCacheFile();

	define('SECURITY_KEY', $securityKey);
	define('SECURITY_SALT', $securitySalt);
}

// ErrorBacktrace
//$errorBacktrace = errorBacktrace::getInstance(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// DebugBar
$debugbar = debugbar::getInstance();

// Si cookie no es un array lo sera, ya que el boot de google entra sin esta variable y da WARNING en algunos servidores cuando indexa
if (!isset($_COOKIE) || !is_array($_COOKIE))
	$_COOKIE = array();

// start the timer for the page parse time log
define('PAGE_PARSE_START_TIME', microtime());
$debug = array();

// Versión del proyecto
define('PROJECT_VERSION', 'oscDenox v 3.0');

// Esteblecemos el tipo de petición (seguro o no)
$request_type = getenv('HTTPS') == 'on' ? 'SSL' : 'NONSSL';

// set php_self in the local scope
$PHP_SELF = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']);

if (!defined('DIR_WS_CATALOG')) {
	if ($request_type == 'NONSSL')
		define('DIR_WS_CATALOG', DIR_WS_HTTP_CATALOG);
	else
		define('DIR_WS_CATALOG', DIR_WS_HTTPS_CATALOG);
}

// customization for the design layout
if (!defined('BOX_WIDTH')) define('BOX_WIDTH', 185);


// Si gzip_compression esta habilitado, la salida sera comprimida
if (GZIP_COMPRESSION == 'true' && $ext_zlib_loaded = extension_loaded('zlib') && !headers_sent()) {
	if (($ini_zlib_output_compression = (int)ini_get('zlib.output_compression')) < 1) {
			ob_start('ob_gzhandler');
	} elseif (function_exists('ini_set'))
		ini_set('zlib.output_compression_level', GZIP_LEVEL);
}

// Establece los parametros $_GET automaticamente si search_engine_friendly_urls está habilitado
if (SEARCH_ENGINE_FRIENDLY_URLS == 'true') {
	if (strlen(getenv('PATH_INFO')) > 1) {
		$GET_array = [];
		$PHP_SELF  = str_replace(getenv('PATH_INFO'), '', $PHP_SELF);
		$vars      = explode('/', substr(getenv('PATH_INFO'), 1));
		for ($i = 0, $n = count($vars); $i < $n; $i++) {
			if (strpos($vars[$i], '[]'))
				$GET_array[substr($vars[$i], 0, -2)][] = $vars[$i + 1];
			else
				$_GET[$vars[$i]] = $vars[$i + 1];

			$i++;
		}

		if (is_array($GET_array) && count($GET_array) > 0)
			foreach ($GET_array as $key => $value)
				$_GET[$key] = $value;
	}
}


if (strpos($_SERVER['REQUEST_URI'], '&amp;') && tep_db_prepare_input($_GET['dxfilter'] ?? '')) {
	header('Location: ' . str_replace('&amp;', '&', $_SERVER['REQUEST_URI']));
	exit;
}

/**
 * XCC-313-91043
 */
require($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'affiliates.php');

// Configuracion
$configurationAdapter = new Configuration();

// Cookie lifetime
$cookieLifeTimeFrontOffice             = $configurationAdapter->get('COOKIE_LIFETIME_FRONT_OFFICE', 5);
$cookieCalculateLifeTimeCommandHandler = DependencyInjection::getInstance()->get(CookieCalculateLifeTimeCommandHandler::class);
$cookieLifetime                        = $cookieCalculateLifeTimeCommandHandler->__invoke(new CookieCalculateLifeTimeCommand($cookieLifeTimeFrontOffice));

// Cookie
$cookieInitializeCommandHandler = DependencyInjection::getInstance()->get(CookieInitializeCommandHandler::class);
$cookie                         = $cookieInitializeCommandHandler->__invoke(new CookieInitializeCommand('dnxStore', $configurationAdapter->get('HTTPS_COOKIE_PATH'), $cookieLifetime, $configurationAdapter->check('request_type', 'SSL')));

// Session
$sessionCore = (new SessionCustomerMysqlInitializeBuild())();

// Extraemos todas las varaibles de session a variables globales
isset($_SESSION) ? extract($_SESSION, EXTR_OVERWRITE + EXTR_REFS) : null;

// Establecemos SID una vez, incluso si está vacío
$SID = (session_id() !== '' && !ini_get('session.use_cookies')) ? session_name() . '=' . session_id() : '';

// FIX: crear shopping cart si es necesario
if (!tep_session_is_registered('cart') || !is_object($cart)) {
	tep_session_register('cart');
	$cart = new shoppingCart;
}

// Cliente
$customerCore = new Customer();
// Incluye la clase currencies y la crea

$currencies = new currencies();
$pf         = new PriceFormatter;
$pfs        = new PriceFormatterStore;

// Autologin
$customerCore->autologin();

// Sampedro: Editor de pedidos, si mandamos por GET dicha variable modficamos valores necesarios
if (array_key_exists('curl_oe', $_GET))
	changeVarsCheckout_oe();

$lng = new language();

if (isset($_GET['language']) && tep_not_null($_GET['language'])) {
	// Viene de GET → usarlo y guardarlo en sesión
	$lng->setLanguage($_GET['language']);
	$_SESSION['language']      = $lng->language['directory'];
	$_SESSION['language_name'] = $lng->language['name'];
	$_SESSION['languages_id']  = $lng->language['id'];
} elseif (isset($_SESSION['language'])) {
	// Ya hay idioma en sesión → mantenerlo
	$lng->setLanguage($_SESSION['language']);
} else {
	// Si no hay nada, usar el idioma por defecto
	$lng->setLanguage(DEFAULT_LANGUAGE);
	$_SESSION['language']      = $lng->language['directory'];
	$_SESSION['language_name'] = $lng->language['name'];
	$_SESSION['languages_id']  = $lng->language['id'];
}

// Variables locales para compatibilidad
$language      = $_SESSION['language'];
$language_name = $_SESSION['language_name'];
$languages_id  = $_SESSION['languages_id'];

// Incluir las traducciones
$_system_locale_numeric = setlocale(LC_NUMERIC, 0);
require($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_LANGUAGES . $language . '.php');
// Prevent LC_ALL from setting LC_NUMERIC to a locale with 1,0 float/decimal values instead of 1.0 (see bug #634)
setlocale(LC_NUMERIC, $_system_locale_numeric);
// Funcion de fecha y locale transpasadas aqui ya que en los archivos de idioma solo debemos de tener defines
if ($languages_id == 3) // Español
{
	@setlocale(LC_TIME, 'es_ES.ISO_8859-1');
	setlocale(LC_CTYPE, 'C');

	if (!function_exists('tep_date_raw')) {
		function tep_date_raw($date, $reverse = false) {
			if ($reverse)
				return substr($date, 0, 2) . substr($date, 3, 2) . substr($date, 6, 4);
			else
				return substr($date, 6, 4) . substr($date, 3, 2) . substr($date, 0, 2);
		}
	}
} else {
	@setlocale(LC_TIME, 'en_US.ISO_8859-1');

	function tep_date_raw($date, $reverse = false) {
		if ($reverse)
			return substr($date, 3, 2) . substr($date, 0, 2) . substr($date, 6, 4);
		else
			return substr($date, 6, 4) . substr($date, 0, 2) . substr($date, 3, 2);
	}
}

// SEO URLs ACTIVADAS
if (SEO_ENABLED == 'true' || (SEO_ENABLED != 'true' && SEO_ENABLED != 'false')) {
	if (!is_object($seo_urls) && $_GET['action'] != 'buy_now') {
		$seo_urls = new SEO_URL($languages_id);
	}
}

// Obtenemos todas las categorias
$aAllCategories = getAllCategoryArray();
$_aAllCategorias = $aAllCategories['parnt'];
$_aAllDatos = $aAllCategories['cat'];

// currency
if (!isset($currency) || !tep_session_is_registered('currency') || isset($_GET['currency']) || (USE_DEFAULT_LANGUAGE_CURRENCY == 'true' && LANGUAGE_CURRENCY != $currency)) {
	if (!tep_session_is_registered('currency'))
		tep_session_register('currency');

	if (isset($_GET['currency']) && $currencies->is_set($_GET['currency']))
		$currency = $_GET['currency'];
	else
		$currency = ((USE_DEFAULT_LANGUAGE_CURRENCY == 'true') && $currencies->is_set(LANGUAGE_CURRENCY)) ? LANGUAGE_CURRENCY : DEFAULT_CURRENCY;
}

// Historial de navegación
if (!tep_session_is_registered('navigation') || !is_object($navigation)) {
	tep_session_register('navigation');
	$navigation = new navigationHistory;
}
$navigation->add_current_page();
$navigation->add_last_page();

// Inicializamos messagestack
$messageStack = new messageStack;
// Evento
event::getInstance()->execute('front_office_custom_events');
event::getInstance()->execute('front_office_application_top_before');

// Shopping cart actions
if (isset($_GET['action'])) {
	// redirect the customer to a friendly cookie-must-be-enabled page if cookies are disabled
	if (!$sessionCore->hasStarted()) {
		tep_redirect(tep_href_link(FILENAME_COOKIE_USAGE));
	}
	if (DISPLAY_CART == 'true') {
		$goto       = FILENAME_SHOPPING_CART;
		$parameters = ['action', 'cPath', 'products_id', 'pid'];
		/* Optional Related Products (ORP) */
	} else if ($_GET['action'] == 'rp_buy_now') {
		$goto       = FILENAME_PRODUCT_INFO;
		$parameters = ['action', 'pid', 'rp_products_id'];
		//ORP: end
	} else {
		$goto = basename($PHP_SELF);
		if ($_GET['action'] == 'buy_now') {
			// BOE: XSell
			if (isset($_GET['product_to_buy_id'])) {
				$parameters = ['action', 'pid', 'products_to_but_id'];
			} else {
				$parameters = ['action', 'pid', 'products_id'];
			}
			// EOE: XSell
		} else {
			$parameters = ['action', 'pid'];
		}
	}
	switch ($_GET['action']) {
		//Cambio de vista del producto y asignación a la session
		case 'cambiar_vista':
			$vista = tep_db_prepare_input($_GET['c']);
			tep_session_register("vista");
			exit();
			break;

		// Actualizar cantidad de productos en el carrito de compra
		case 'update_product':
			for ($i = 0, $n = sizeof($_POST['products_id']); $i < $n; $i++) {
				if (in_array($_POST['products_id'][$i], (is_array($_POST['cart_delete']) ? $_POST['cart_delete'] : [])))
					$cart->remove($_POST['products_id'][$i]);
				else {
					$attributes = $_POST['id'][$_POST['products_id'][$i]] ? $_POST['id'][$_POST['products_id'][$i]] : '';
					$cart->add_cart($_POST['products_id'][$i], $_POST['cart_quantity'][$i], $attributes, false);
				}
			}
			tep_redirect(tep_href_link($goto, tep_get_all_get_params($parameters)));
			break;
		// customer adds a product from the products page
		case 'add_product' :
			if (isset($_POST['products_id']) && is_numeric($_POST['products_id'])) {
				$cart->add_cart($_POST['products_id'], $cart->get_quantity(tep_get_uprid($_POST['products_id'], $_POST['id'])) + ($_POST['cart_quantity'] != '' ? $_POST['cart_quantity'] : 1), $_POST['id']);
			}
			tep_redirect(tep_href_link($goto, tep_get_all_get_params($parameters)));
			break;
		// performed by the 'buy now' button in product listings and review page
		case 'buy_now' :
			if (isset($$_GET['product_to_buy_id'])) {
				if (tep_has_product_attributes($$_GET['product_to_buy_id'])) {
					tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $$_GET['product_to_buy_id']));
				} else {
					$cart->add_cart($$_GET['product_to_buy_id'], $cart->get_quantity($$_GET['product_to_buy_id']) + 1);
				}
			}
			tep_redirect(tep_href_link($goto, tep_get_all_get_params($parameters)));
			break;

		case 'buy_now_oct8ne' :
			if (isset($_GET['products_id'])) {
				if (tep_has_product_attributes($_GET['products_id'])) {
					tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $_GET['products_id']));
				} else {
					$cart->add_cart($_GET['products_id'], $cart->get_quantity($_GET['products_id']) + 1);
				}
			}
			tep_redirect(tep_href_link($goto, tep_get_all_get_params($parameters)));
			break;

		case 'rp_buy_now' :
			if (isset($_GET['rp_products_id'])) {
				if (tep_has_product_attributes($_GET['rp_products_id'])) {
					tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $_GET['rp_products_id']));
				} else {
					$cart->add_cart($_GET['rp_products_id'], $cart->get_quantity($_GET['rp_products_id']) + 1);
				}
			}
			tep_redirect(tep_href_link($goto, tep_get_all_get_params($parameters)));
			break;

		case 'notify' :
			if (tep_session_is_registered('customer_id')) {
				if (isset($_GET['products_id'])) {
					$notify = $_GET['products_id'];
				} else if (isset($_GET['notify'])) {
					$notify = $_GET['notify'];
				} else if (isset($_POST['notify'])) {
					$notify = $_POST['notify'];
				} else {
					tep_redirect(tep_href_link(basename($PHP_SELF), tep_get_all_get_params(['action', 'notify'])));
				}
				if (!is_array($notify)) $notify = [$notify];
				for ($i = 0, $n = sizeof($notify); $i < $n; $i++) {
					$check_query = tep_db_query("select count(*) as count from " . TABLE_PRODUCTS_NOTIFICATIONS . " where products_id = '" . (int)$notify[$i] . "' and customers_id = '" . (int)$customer_id . "'");

					$check = tep_db_fetch_array($check_query);
					if ($check['count'] < 1) {
						tep_db_query("insert into " . TABLE_PRODUCTS_NOTIFICATIONS . " (products_id, customers_id, date_added) values ('" . (int)$notify[$i] . "', '" . (int)$customer_id . "', now())");

					}
				}
				tep_redirect(tep_href_link(basename($PHP_SELF), tep_get_all_get_params(['action', 'notify'])));
			} else {
				$navigation->set_snapshot();
				tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
			}
			break;
		case 'notify_remove' :
			if (tep_session_is_registered('customer_id') && isset($_GET['products_id'])) {
				$check_query = tep_db_query("select count(*) as count from " . TABLE_PRODUCTS_NOTIFICATIONS . " where products_id = '" . (int)$_GET['products_id'] . "' and customers_id = '" . (int)$customer_id . "'");

				$check = tep_db_fetch_array($check_query);
				if ($check['count'] > 0) {
					tep_db_query("delete from " . TABLE_PRODUCTS_NOTIFICATIONS . " where products_id = '" . (int)$_GET['products_id'] . "' and customers_id = '" . (int)$customer_id . "'");

				}
				tep_redirect(tep_href_link(basename($PHP_SELF), tep_get_all_get_params(['action'])));
			} else {
				$navigation->set_snapshot();
				tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
			}
			break;
		case 'cust_order' :
			if (tep_session_is_registered('customer_id') && isset($_GET['pid'])) {
				if (tep_has_product_attributes($_GET['pid'])) {
					tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $_GET['pid']));
				} else {
					$cart->add_cart($_GET['pid'], $cart->get_quantity($_GET['pid']) + 1);
				}
			}
			tep_redirect(tep_href_link($goto, tep_get_all_get_params($parameters)));
			break;
	}
}

// Incluye who's online
if (!(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
	tep_update_whos_online();
}

//CSY-786-86518
require($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'HoldingOrder.php');

require($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_FUNCTIONS . 'redemptions.php');
// Auto activar y desactivar banners
tep_activate_banners();
tep_expire_banners();

// Auto desactivar ofertas
tep_expire_specials();


// Calcular path categories
if (isset($_GET['cPath']))
	$cPath = $_GET['cPath'];
elseif (isset($_GET['products_id']) && !isset($_GET['manufacturers_id']))
	$cPath = tep_get_product_path($_GET['products_id']);
else
	$cPath = '';

if (tep_not_null($cPath)) {
	$cPath_array = tep_parse_category_path($cPath);
	$cPath = implode('_', $cPath_array);
	$current_category_id = $cPath_array[(count($cPath_array) - 1)];
} else {
    $current_category_id = 0;
}

// Incluye breadcrumb class
$breadcrumb = new breadcrumb;
$breadcrumb->setSeparator('<span>»</span>');
$breadcrumb->add(HEADER_TITLE_TOP, (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER));

// Breadcrumb Categorias cuando exista $cPath_array
if (isset($cPath_array)) {
	foreach ($cPath_array as $categoryId) {
		// Verifica si la categoría existe en $aAllDatos
		if (isset($_aAllDatos[$categoryId])) {
			$categoryData = $_aAllDatos[$categoryId];
			$breadcrumb->add($categoryData['categories_name'], tep_href_link(FILENAME_CATEGORIES, 'cPath=' . implode('_', array_slice($cPath_array, 0, array_search($categoryId, $cPath_array) + 1))));
		} else {
			break;
		}
	}
}
// Establecemos las medidas que tenemos que comprobar para lanzar warnings
define('WARN_INSTALL_EXISTENCE', 'true');
define('WARN_CONFIG_WRITEABLE', 'true');
define('WARN_SESSION_DIRECTORY_NOT_WRITEABLE', 'true');
define('WARN_SESSION_AUTO_START', 'true');
define('WARN_DOWNLOAD_DIRECTORY_NOT_READABLE', 'true');

require($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_FUNCTIONS . 'ajax.php');

$customer_group_id = isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0' ? $_SESSION['sppc_customer_group_id'] : '0';
$preventDuplicates = new preventDuplicates();


// Variables unificadas
// Variable que va guardando los tax class para no tener que ir consultandolos a DB cada vez que se necesita
$aTaxCache = [];
// Variable que contiene todas las paginas de información
$aInformationPages = getInformationPages();

// Comprobamos la vista de los productos
if (empty($_SESSION['vista'])) {
	$vista = (PRODUCT_SHOW_METHOD == 'columnas') ? 'chng-vsta-vrtl' : 'chng-vsta-hrzt';

	tep_session_register("vista");
}

// Vemos si nos encontramos solo en el index
$isIndexPage = (in_array(basename($_SERVER['SCRIPT_NAME']), array('index.php', 'index_dev.php')) && count($_GET) == 0 && count($_POST) == 0) || (basename($_SERVER['SCRIPT_NAME']) == 'index.php' && isset($_GET['language']) && $_GET['language']);
define('ONLY_INDEX', $isIndexPage);

// Wishlist
$dxWishlist = new wishlist();

// Ley de proteccion de datos
$rgpd = new rgpd();

/*
Añadimos composer, añadimos modulos e iniciamos librerias
*/
if (!defined('NO_LOAD_MODULES')) {
	define('APP_PATH', dirname($_SERVER['REQUEST_URI']) . '/');
	require(dirname(__FILE__) . '/../includes/vendor/autoload.php');

	//Iniciamos los hooks
	$hooks = new daniellucia\Hooks\Manager;

	require(DIR_WS_FUNCTIONS . 'modules.php');
}

// Obtenemos todas las categorias
$aAllCategories  = getAllCategoryArray();
$_aAllCategorias = $aAllCategories['parnt'];
$_aAllDatos      = $aAllCategories['cat'];

// Inicio, llamada a un metodo de un objeto, o function mediante una peticion ajax, conteniendo en la llamada objeto:metodo, o nombre_funcion
if (isAjax() && array_key_exists('call', $_GET) && ($aAux = tep_db_prepare_input($_GET['call'])) != '') {
	// Solo permitimos estas llamadas, si se necesitan más llamadas personalizadas podemos añadirlas
	if (!in_array($aAux, array('cart:addProductAjax', 'cart:getHtmlCart', 'cart:remove', 'dxWishlist:add', 'dxWishlist:remove', 'ajaxCountryZoneCity','ajaxCustomEvent')))
		exit();

	// Explotamos la clase y el metodo, o en su defecto la funcion
	$aAux = explode(':', $aAux);
	$sArgs = array_key_exists('args', $_GET) ? tep_db_prepare_input($_GET['args']) : '';

	// Si solo tenemos una llamada es una funcion
	if (count($aAux) == 1)
		eval($aAux[0] . '(' . $sArgs . ');');
	else // Llamamos al metodo de la clase
		eval('$' . $aAux[0] . '->' . $aAux[1] . '("' . $sArgs . '");');

	// Salimos
	exit();
}
// Fin, llamada a un metodo de un objeto mediante una peticion ajax, conteniendo en la llamada objeto:metodo



// Módulo de promociones //
global $cart;

require(DIR_WS_MODULES . 'promotions/PromotionManager.php');

// Módulo de promociones
$promotionManager = new PromotionManager($cart, $customer_group_id);
$promotionResult  = $promotionManager->applyPromotions();

// Variables globales de compatibilidad
$aAllPromotions = PromotionManager::getAllPromotions();

// Fin módulo de promociones //

/* Módulo de notificaciones. Contabilizar clicks y entradas */
if (intval($_GET['push_id'] ?? 0)) {
	tep_db_query('UPDATE notificaciones SET total = total + 1 WHERE id = ' . intval($_GET['push_id']));
}

// Actualizamos la fecha de finalización de las ofertas a dos semanas
tep_db_query('UPDATE specials SET expires_repeat = 1 WHERE (expires_date IS NULL OR expires_date = "0000-00-00 00:00:00") AND status = 1;');
tep_db_query('UPDATE specials SET expires_date = NOW() + INTERVAL 14 DAY WHERE (expires_date IS NULL OR expires_date = "0000-00-00 00:00:00") AND expires_repeat = 1 AND status = 1;');
tep_db_query('UPDATE specials SET expires_date = NOW() + INTERVAL 14 DAY WHERE expires_date < NOW() AND expires_repeat = 1 AND status = 1;');

// Mantenimiento
if (MANTENIMIENTO != 'Abrir' && !in_array($_SERVER['REMOTE_ADDR'], explode(',', MANTENIMIENTO_IP)) && basename($_SERVER['SCRIPT_NAME']) != 'mantenimiento.php') {
	tep_redirect(tep_href_link('mantenimiento.php'));
	exit(1);
}

/*
* Configuración de One Page Checkout
*/
$bActiveCheckoutOnePage = false;

/**
 * #XCC-313-91043
 */
if (isset($_GET['ref-affiliate'])) {
	Affiliates::saveCouponAffiliateIfExsists(tep_db_prepare_input($_GET['ref-affiliate']));
}

// Evento
event::getInstance()->execute('front_office_application_top_after');
