<?php
/* supertracker.php by MS-Onlinestore */

if (!defined('HEADING_TITLE')) define('HEADING_TITLE', 'Estad&iacute;sticas');

if (!defined('TEXT_TABLE_DATABASE')) define('TEXT_TABLE_DATABASE', 'La base de datos de Estad&iacute;sticas tiene actualmente: <b>%s</b> filas (Entradas antiguas:<b> %s </b>)');
if (!defined('TEXT_DATABASE_INFO')) define('TEXT_DATABASE_INFO', 'Informaci&oacute;n de la Base de Datos: ');
if (!defined('TEXT_TABLE_DELETE')) define('TEXT_TABLE_DELETE', 'Para borrar datos antiguos, introduzca el n&uacute;mero de filas a borrar: ');
if (!defined('TEXT_BUTTON_ERASE')) define('TEXT_BUTTON_ERASE', 'Borrar');
if (!defined('TEXT_BUTTON_UPDATE')) define('TEXT_BUTTON_UPDATE', 'Actualizar');
if (!defined('TABLE_TEXT_MENU_DESC_TEXT')) define('TABLE_TEXT_MENU_DESC_TEXT', 'Seleccione un informe: ');
if (!defined('TABLE_TEXT_MENU_TEXT')) define('TABLE_TEXT_MENU_TEXT', 'Men&uacute; desplegable de informes');

if (!defined('TEXT_TOP_REFERRERS')) define('TEXT_TOP_REFERRERS', 'Mejores URLs referidas');
if (!defined('TEXT_TOP_SALES')) define('TEXT_TOP_SALES', 'Mejores referentes generadores de ventas');
if (!defined('TEXT_AVERAGE_CLICKS')) define('TEXT_AVERAGE_CLICKS', 'Promedio de clics en el sitio por referencia');
if (!defined('TEXT_AVERAGE_TIME_SPENT')) define('TEXT_AVERAGE_TIME_SPENT', 'Promedio de tiempo gastado en el sitio por referencia');
if (!defined('TEXT_SEARCH_KEYWORDS')) define('TEXT_SEARCH_KEYWORDS', 'B&uacute;squeda de palabras usadas (Total)');
if (!defined('TEXT_SEARCH_KEYWORDS_24')) define('TEXT_SEARCH_KEYWORDS_24', 'B&uacute;squeda de palabras usadas en las &uacute;ltimas 24hrs');
if (!defined('TEXT_SEARCH_KEYWORDS_3')) define('TEXT_SEARCH_KEYWORDS_3', 'B&uacute;squpeda de palabras usadas en los 3 &uacute;ltimos d&iacute;as');
if (!defined('TEXT_SEARCH_KEYWORDS_7')) define('TEXT_SEARCH_KEYWORDS_7', 'B&uacute;squeda de palabras usadas en la &uacute;ltima semana');
if (!defined('TEXT_SEARCH_KEYWORDS_30')) define('TEXT_SEARCH_KEYWORDS_30', 'B&uacute;squeda de palabras usadas en el &uacute;ltimo mes');
if (!defined('TEXT_TOP_EXIT_PAGES')) define('TEXT_TOP_EXIT_PAGES', 'P&aacute;ginas de salida (no ventas)');
if (!defined('TEXT_TOP_EXIT_PAGES_NO_SALE')) define('TEXT_TOP_EXIT_PAGES_NO_SALE', 'P&aacute;ginas de salida (a&ntilde;adir a la cesta)');
if (!defined('TEXT_PRODUCTS_VIEWED_REPORT')) define('TEXT_PRODUCTS_VIEWED_REPORT', 'Informe de productos visitados');
if (!defined('TEXT_BROWSER')) define('TEXT_BROWSER', 'Informe de navegadores usados');
if (!defined('TEXT_LAST_TEN_VISITORS')) define('TEXT_LAST_TEN_VISITORS', '&Uacute;ltimos 10 visitantes del sitio');
if (!defined('TEXT_VISITORS')) define('TEXT_VISITORS', 'Pa&iacute;s de origen de los visitantes');
if (!defined('TEXT_VISITORS_STATE')) define('TEXT_VISITORS_STATE', 'Provincia de origen de los visitantes');
if (!defined('TEXT_VISITORS_CITY')) define('TEXT_VISITORS_CITY', 'Ciudad de origen de los visitantes');
if (!defined('TEXT_PAY_PER_CLICK')) define('TEXT_PAY_PER_CLICK', 'Rendimiento de pago por click');

if (!defined('TEXT_SHOW_ALL')) define('TEXT_SHOW_ALL', 'Ver todo');
if (!defined('TEXT_BAILED_CARTS')) define('TEXT_BAILED_CARTS', 'Cestas liberadas');
if (!defined('TEXT_SUCCESSFUL_CHECKOUTS')) define('TEXT_SUCCESSFUL_CHECKOUTS', 'Marcas correctas');
if (!defined('TEXT_REFERRER_STRING')) define('TEXT_REFERRER_STRING', 'Filtro de palabras para referidos (e.j. "google", "msn", etc): ');
if (!defined('TABLE_TEXT_CUSTOMER_BROWSER')) define('TABLE_TEXT_CUSTOMER_BROWSER', 'Navegadores de clientes: ');
if (!defined('TABLE_TEXT_AVERAGE_TIME')) define('TABLE_TEXT_AVERAGE_TIME', 'referencia, tiempo promedio: ');
if (!defined('TABLE_TEXT_MINS_AVERAGE_CLICKS')) define('TABLE_TEXT_MINS_AVERAGE_CLICKS', 'minutos, promedio de clics: ');
if (!defined('TABLE_TEXT_PRODUCT_NAME')) define('TABLE_TEXT_PRODUCT_NAME', 'Nombre de productos');
if (!defined('TABLE_TEXT_NUMBER_OF_VIEWING')) define('TABLE_TEXT_NUMBER_OF_VIEWING', 'N&uacute;mero de vistas');
if (!defined('TABLE_TEXT_QUANTITY')) define('TABLE_TEXT_QUANTITY', 'Cantidad: ');
if (!defined('TABLE_TEXT_ORDER_VALUE')) define('TABLE_TEXT_ORDER_VALUE', 'Orden de valor: ');

if (!defined('TEXT_RANKING')) define('TEXT_RANKING', 'Ranking');
if (!defined('TEXT_REFERRING_URL')) define('TEXT_REFERRING_URL', 'Referencias URL');
if (!defined('TEXT_NUMBER_OF_HITS')) define('TEXT_NUMBER_OF_HITS', 'N&uacute;mero de selecciones');
if (!defined('TEXT_NUMBER_OF_SALES')) define('TEXT_NUMBER_OF_SALES', 'N&uacute;mero de ventas');
if (!defined('TEXT_EXIT_PAGE')) define('TEXT_EXIT_PAGE', 'P&aacute;gina de salida');
if (!defined('TEXT_NUMBER_OF_OCCURRENCES')) define('TEXT_NUMBER_OF_OCCURRENCES', 'N&uacute;mero de ocurrencias');
if (!defined('TEXT_NUMBER_OF_CLICKS')) define('TEXT_NUMBER_OF_CLICKS', 'N&uacute;mero de clics');
if (!defined('TEXT_AVERAGE_LENGTH_OF_TIME')) define('TEXT_AVERAGE_LENGTH_OF_TIME', 'Promedio de tiempo en el sitio web (minutos)');

if (!defined('TABLE_TEXT_COUNTRY')) define('TABLE_TEXT_COUNTRY', 'Pa&iacute;s');
if (!defined('TABLE_TEXT_REGION')) define('TABLE_TEXT_REGION', 'Regi&oacute;n: ');
if (!defined('TABLE_TEXT_CITY')) define('TABLE_TEXT_CITY', 'Ciudad: ');
if (!defined('TABLE_TEXT_IP')) define('TABLE_TEXT_IP', 'IP clientes direcci&oacute;n/pa&iacute;s: ');
if (!defined('TABLE_TEXT_NAME')) define('TABLE_TEXT_NAME', 'Nombre de cliente: ');
if (!defined('TABLE_TEXT_REFFERED_BY')) define('TABLE_TEXT_REFFERED_BY', 'Referidos por: ');
if (!defined('TABLE_TEXT_LANDING_PAGE')) define('TABLE_TEXT_LANDING_PAGE', 'P&aacute;gina de llegada: ');
if (!defined('TABLE_TEXT_LAST_PAGE_VIEWED')) define('TABLE_TEXT_LAST_PAGE_VIEWED', '&Uacute;ltima p&aacute;gina vista: ');
if (!defined('TABLE_TEXT_TIME_ARRIVED')) define('TABLE_TEXT_TIME_ARRIVED', 'Tiempo de llegada: ');
if (!defined('TABLE_TEXT_LAST_CLICK')) define('TABLE_TEXT_LAST_CLICK', '&Uacute;ltimo clic: ');
if (!defined('TABLE_TEXT_TIME_ON_SITE')) define('TABLE_TEXT_TIME_ON_SITE', 'Tiempo en el sitio: ');
if (!defined('TABLE_TEXT_NUMBER_OF_CLICKS')) define('TABLE_TEXT_NUMBER_OF_CLICKS', 'N&uacute;mero de clics: ');
if (!defined('TABLE_TEXT_ADDED_CART')) define('TABLE_TEXT_ADDED_CART', 'A&ntilde;adir a la cesta: ');
if (!defined('TABLE_TEXT_COMPLETED_PURCHASE')) define('TABLE_TEXT_COMPLETED_PURCHASE', 'Compras completadas: ');
if (!defined('TABLE_TEXT_CATEGORIES')) define('TABLE_TEXT_CATEGORIES', 'Categor&iacute;as vistas: ');
if (!defined('TABLE_TEXT_PRODUCTS')) define('TABLE_TEXT_PRODUCTS', 'Productos vistos: ');
if (!defined('TABLE_TEXT_CUSTOMERS_CART')) define('TABLE_TEXT_CUSTOMERS_CART', 'Cesta de clientes : ');
if (!defined('TABLE_TEXT_NEXT_TEN_RESULTS')) define('TABLE_TEXT_NEXT_TEN_RESULTS', 'Pr&oacute;ximos 10 resultados');
?>
