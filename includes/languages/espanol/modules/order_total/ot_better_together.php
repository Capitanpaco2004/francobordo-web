<?php
//
// +----------------------------------------------------------------------+
// | Better Together discount strings                                     |
// +----------------------------------------------------------------------+
// | Copyright (c) 2006 That Software Guy                                 |
// +----------------------------------------------------------------------+
// | Released under the GNU General Public License.                       |
// +----------------------------------------------------------------------+
//
  define('MODULE_ORDER_TOTAL_BETTER_TOGETHER_TITLE', 'Descuento Mejor Juntos');
  define('MODULE_ORDER_TOTAL_BETTER_TOGETHER_DESCRIPTION', 'Descuento Mejor Juntos');
  define('TWOFER_PROMO_STRING', 'Compre este producto y consiga otro igual gratis');
  define('TWOFER_QUALIFY_STRING', "Usted ha satisfecho los requisitos para conseguir un segundo %s gratis"); 
  define('BUY_THIS_ITEM', 'Compre este producto,');
  define('QUALIFY', "Ustes reune los requisitos para "); 
  define('GET_THIS',' y obtenga ');  // for prod_to_prod
  define('GET_ANY',' obtenga un producto de '); // for prod_to_cat, cat_to_cat
  define('OFF_STRING_PCT',' con un %s de descuento');  // e.g. at 50% off
  define('OFF_STRING_CURR',' para un %s de descuento');  // e.g. $20 off 
  define('SECOND_ONE',' otro producto igual');  // for prod_to_prod, both same
  define('SECOND',' otro ');  // if both same
  define('FREE_STRING',' gratis');  // i.e. amount off 
  if (!defined('TWOFER_PROMO_STRING')) define('TWOFER_PROMO_STRING', 'Compre este producto y consiga otro igual de forma gratuita');
  define('TWOFER_CAT_PROMO_STRING', 'Compre este producto y consiga otro igual de forma gratuita');
  // Reverse defs
  define('REV_GET_ANY', 'Compre cualquier artículo de '); 
  define('REV_GET_THIS', 'Compre '); 
  define('REV_GET_DISC', ', consiga este artículo '); 
  // No context (off product info page)
  define('FREE', " gratis"); 
  define('GET_YOUR_PROD', ", consiga "); 
  define('GET_YOUR_CAT', ", obtenga su selección de "); 
?>
