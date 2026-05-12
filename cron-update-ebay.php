<?php
require 'includes/application_top.php';

define('EBAY_ROUTE', getcwd() . '/_admin/');
require_once EBAY_ROUTE . 'includes/modules/ebay/functions.php';

switch ($_GET['action']) {
    case 'sync-products':
        cronSyncProducts(1.1);
        break;
    case 'remove-duplicates':
        cronRemoveDuplicates(100);
        break;
    case 'remove-no-id':
        cronRemoveNoID(200);
        break;
    case 'update-id-products':
        ebaySyncEbayID();
        break;
    case 'active-products':
        ebayActiveProducts();
        break;
    case 'revise-products':
        ebayReviseProducts();
        break;
    case 'remove-no-stock':
        ebayRemoveProductsNoStock();
        break;
    case 'queue':
        ebayExecuteQueue();
        echo '<script>setTimeout(function(){ location.href=location.href; }, 2000);</script>';
        break;
    case 'categories-features':
        $aDatos = tep_db_query('select id_ebay from categories where id_ebay > 0 group by id_ebay');
        $aCategories = array();

        while ($aDato = tep_db_fetch_array($aDatos)) {
            $aCategories[] = $aDato['id_ebay'];
        }

        getCategoryFeaturesEbay($aCategories);
        break;
    default:
        cronUpdateOrders();
        break;
}

if ($_GET['referer'] != '') {
    header('Location: ' . base64_decode($_GET['referer']));
}

require 'includes/application_bottom.php';
