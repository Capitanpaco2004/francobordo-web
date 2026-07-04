<?php

/**
 * #HFI-249-15671
 */

// Tools

use util\tools as tools;

// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
if (array_key_exists('action', $_GET) && in_array($_GET['action'], array('install'))) {
    // FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
    // salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
    $_SERVER['PHP_SELF'] = 'index.php';
}

// Incluimos el application_top
require 'includes/application_top.php';

include 'includes/classes/currencies.php';
$currencies = new currencies();

// Variables
$sUrlPage = 'affiliates.php';
$sTitle = 'Afiliados';
$sSubtitle = '';
$aButtons = array();
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);
$sHtml = '';

// Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch ($sPostAction) {

    case 'readme':
        // Variables
        $sSubtitle = 'Readme de instalación';
        $aButtons = array(
            array('title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage),
        );

        $sHtml = tools::parsedown(DIR_WS_MODULES . '/affiliates/readme.txt');
        break;

    case 'install':

        // Insertamos admin file
        tools::insertAdminFiles($sUrlPage, 1);
        tools::insertAdminFiles('stats_affiliates_orders.php', 1);

        // Insertamos el grupo de configuracion
        $aConfigGroup = tools::insertConfigurationGroup('Sistema de afiliados', 0);

        // Insertamos la configuracion global
        tools::insertConfiguration('Estado', 'AFFILLIATES_STATUS', 'false', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Comisión', 'AFFILLIATES_SALES_COMISSION', '5', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Comisión (eu)', 'AFFILLIATES_SALES_COMISSION_EU', '5', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Valor del cupón generado', 'AFFILLIATES_VALUE_COUPON', '5', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Estado para considerar pedido válido', 'AFFILLIATES_ORDERS_STATUS', '5', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Días para considerar un pedido completado', 'AFFILLIATES_DAYS_LEFT', '14', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Importe mínimo para retirar fondos', 'AFFILLIATES_MINIMUM', '10', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('E-Mail donde enviar la factura.', 'AFFILLIATES_EMAIL', '[email]', '', $aConfigGroup->records['configuration_group_id']);
        tools::insertConfiguration('Categorías excluidas para cupones descuento', 'AFFILLIATES_BRANDS_EXCLUDES', '', '', $aConfigGroup->records['configuration_group_id']);

        // Reset cache
        tools::createCacheFile();

        //$sql[] = 'ALTER TABLE `orders` ADD `affiliate_id` INT NOT NULL;';

        // Mensajes
        $messageStack->addSession('success', 'El módulo <em>' . $sTitle . '</em> se ha instalado correctamente.', 'success');

        // Redireccionamos
        tep_redirect($sUrlPage . '?action=readme');
        break;

    case 'delete-affiliate':

        deleteAfilliate(intval($_GET['id']));

        // Mensajes
        $messageStack->addSession('success', 'Se borrado correctamente', 'success');

        // Redireccionamos
        tep_redirect(tep_href_link($sUrlPage, 'status=' . intval($_GET['status'])));
        break;
    case 'bulk-save':

        if (!empty($_POST['id'])) {
            switch ($_POST['task']) {
                case 'coupon_value':
                    if (floatval($_POST['value']) > 0) {
                        foreach ($_POST['id'] as $id => $coupon) {

                            tep_db_perform(
                                'affiliates',
                                [
                                    'coupon_value' => floatval($_POST['value']),
                                ],
                                'update',
                                'id = ' . $id
                            );

                            tep_db_perform(
                                'discount_coupons',
                                [
                                    'coupons_discount_amount' => floatval($_POST['value']),
                                ],
                                'update',
                                'coupons_id = "' . $coupon . '"'
                            );
                        }

                        $messageStack->addSession('success', 'Los cupones se han modificado con éxito', 'success');
                    }

                    break;
            }
        }
        tep_redirect($_SERVER['HTTP_REFERER']);
        break;
        break;

    case 'set-status':

        $response = setStatusAffiliate(intval($_GET['id']), intval($_GET['status']));

        // Mensajes
        if ($response == true) {
            $messageStack->addSession('success', 'Se ha cambiado el estado correctamente', 'success');
        } else {
            $messageStack->addSession('success', 'Revise el cupón, parece que ya existe.', 'error');
        }

        // Redireccionamos
        tep_redirect($_SERVER['HTTP_REFERER']);
        break;

    case 'update-coupons-brands':

        $brandsExcluded = getBrandsExcluded();
        $brands = getBrandsForExclude();
        $filter_type = 'manufacturers';

        $sql = 'SELECT coupon FROM affiliates';
        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($affiliate = tep_db_fetch_array($sql)) {
                tep_db_query(
                    sprintf(
                        'DELETE FROM discount_coupons_filters WHERE coupons_id = "%s" AND filter_type = "%s"',
                        $affiliate['coupon'],
                        $filter_type
                    )
                );

                if (!empty($brandsExcluded)) {
                    foreach ($brands as $id => $name) {
                        if (!in_array($id, $brandsExcluded)) {
                            tep_db_perform(
                                'discount_coupons_filters',
                                array(
                                    'coupons_id' => $affiliate['coupon'],
                                    'filter_type' => $filter_type,
                                    'filter_id' => $id,
                                )
                            );
                        }
                    }
                }
            }
        }

        tep_redirect(tep_href_link($sUrlPage, 'action=brands'));

        break;
    case 'save-brands':

        $id = intval($_GET['id']);
        $brandsExcluded = getBrandsExcluded();

        switch ($_GET['type']) {
            case 'remove':
                $brandsExcluded = array_diff($brandsExcluded, [$id]);
                break;
            case 'add':
                $brandsExcluded[] = $id;
                break;
        }

        tep_db_perform(
            'configuration',
            array(
                'configuration_value' => json_encode($brandsExcluded),
            ),
            'update',
            'configuration_key="AFFILLIATES_BRANDS_EXCLUDES"'
        );

        $messageStack->addSession('success', 'Datos guardados correctamente', 'success');
        tools::createCacheFile();

        tep_redirect(tep_href_link($sUrlPage, 'action=brands'));
        break;

    case 'brands':
        $aButtons[] = array('title' => 'Volver', 'href' => tep_href_link($sUrlPage, 'status=2'), 'icon' => 'fa-arrow-left');
        $aButtons[] = array('title' => 'Rehacer filtros de marcas', 'href' => tep_href_link($sUrlPage, 'action=update-coupons-brands'),'icon' => 'fa-cog');
        $sHtml .= includeAdminView(DIR_WS_MODULES . 'affiliates/views/brands.php');
        break;

    case 'stats-download':
        $data = getAffiliatesStats();

        $file = "../temp/list.txt";
        $txt = fopen($file, "w+");

        foreach ($data['customers'] as $customer) {
            fwrite($txt, $customer['customers_email_address'] . PHP_EOL);
        }

        fclose($txt);

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        header("Content-Type: text/plain");
        readfile($file);

        die();
        break;
    case 'stats':
        $aButtons[] = array('title' => 'Volver', 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left');


        if (!isset($_GET['date_to']) || $_GET['date_to'] == '') {
            $_GET['date_to'] = date('d/m/Y');
        }

        if (!isset($_GET['date_from']) || $_GET['date_from'] == '') {
            $_GET['date_from'] = date('01/m/Y');
            $sSubtitle = 'En estos momentos se están mostrando las transacciones del mes en curso';
        } else {
            $sSubtitle = sprintf(
                'Mostrando transacciones desde %s hasta %s',
                $_GET['date_from'],
                $_GET['date_to']
            );
        }

        $sHtml .= includeAdminView(DIR_WS_MODULES . 'affiliates/views/stats.php');
        break;

    case 'view':
        $aButtons[] = array('title' => 'Volver', 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left');
        $aButtons[] = array('title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde');

        $sHtml .= includeAdminView(DIR_WS_MODULES . 'affiliates/views/form.php');
        break;
    case 'orders':
        $aButtons[] = array('title' => 'Volver', 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left');
        $sHtml .= includeAdminView(DIR_WS_MODULES . 'affiliates/views/orders.php');
        break;
    case 'history':
        $aButtons[] = array('title' => 'Volver', 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left');
        $sHtml .= includeAdminView(DIR_WS_MODULES . 'affiliates/views/history.php');
        break;
    case 'order-process':
        if (isset($_GET['orders_id'])) {
            $messageStack->addSession('success', 'Se ha procesado el pedido', 'success');
            processOrderComission(intval($_GET['orders_id']), tep_db_prepare_input($_GET['status']));
            tep_redirect(
                tep_href_link(
                    'affiliates.php',
                    'action=orders&id=' . intval($_GET['id']) . '#' . tep_db_prepare_input($_GET['status'])
                )
            );
        }

        if (isset($_POST['orders_id']) && !empty($_POST['orders_id'])) {
            $messageStack->addSession('success', 'Se han procesado el pedido', 'success');
            processOrderComissionBulk($_POST['orders_id'], tep_db_prepare_input($_POST['status']));
            tep_redirect(
                tep_href_link(
                    'affiliates.php',
                    'action=orders&id=' . intval($_POST['id']) . '#' . tep_db_prepare_input($_GET['status'])
                )
            );
        }

        tep_redirect($sUrlPage);
        break;
    case 'history-process':
        if (isset($_GET['id_history'])) {
            $messageStack->addSession('success', 'Se ha procesado el pedido', 'success');
            processHistory(intval($_GET['id_history']), tep_db_prepare_input($_GET['status']), intval($_GET['id']));
            tep_redirect(
                tep_href_link(
                    'affiliates.php',
                    'action=history&id=' . intval($_GET['id'])
                )
            );
        }

        if (isset($_POST['id_history']) && !empty($_POST['id_history'])) {
            $messageStack->addSession('success', 'Se han procesado el pedido', 'success');
            processHistoryBulk($_POST['id_history'], tep_db_prepare_input($_POST['status']));
            tep_redirect(
                tep_href_link(
                    'affiliates.php',
                    'action=history&id=' . intval($_POST['id'])
                )
            );
        }

        tep_redirect($sUrlPage);
        break;
    case 'save-affiliate':
        if ($_POST['id']) {
            updateDataAffiliate($_POST, intval($_POST['id']));
            $messageStack->addSession('success', 'Los cambios han sido guardados correctamente', 'success');
            tep_redirect(
                tep_href_link(
                    'affiliates.php',
                    'action=view&id=' . intval($_POST['id'])
                )
            );
        }
        tep_redirect($sUrlPage);
        break;
    default:
        if (!isset($_GET['status'])) {
            tep_redirect(tep_href_link($sUrlPage, 'status=2'));
        }

        if (!isset($_GET['per_page']) || intval($_GET['per_page']) == 0) {
            $_GET['per_page'] = MAX_DISPLAY_SEARCH_RESULTS;
        } else {
            $_GET['per_page'] = intval($_GET['per_page']);
        }

        $aButtons[] = array('icon' => 'fas fa-signal', 'title' => 'Estadísticas', 'href' => tep_href_link($sUrlPage, 'action=stats'));
        $aButtons[] = array('icon' => 'fas fa-cog', 'title' => 'Configurar marcas', 'href' => tep_href_link($sUrlPage, 'action=brands'));

        $sHtml .= includeAdminView(DIR_WS_MODULES . 'affiliates/views/index.php');
        break;

}
$aStyle = array('theme/web/css/plugins.css', 'includes/modules/affiliates/css/style.css','theme/solenopsis/css/grid.css');

// Reemplazamos variable
$sHtmlModuleOe = $sHtml;

// MessageStack
$sMessageStack = $messageStack->output(false);
$messageStack->reset();

// Header
include 'theme/solenopsis/html/header.php';

// Cabecera
echo '<div class="oeHead column a12 row ax amiddle aflex">';
echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fas fa-user-shield"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
echo '<div class="oeButton column dtright">';
foreach ($aButtons as $aButton) {
    echo '<a class="xbutton hv8 small' . (array_key_exists('anchor_class', $aButton) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists('extra', $aButton) ? $aButton['extra'] : '') . ' ' . (array_key_exists('title', $aButton) ? 'title="' . $aButton['title'] . '"' : '') . ' id="' . $aButton['id'] . '" href="' . (array_key_exists('href', $aButton) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
}

echo '</div>';
echo '</div>';

// Mensajes
echo $sMessageStack;

// Pintamos
echo $sHtmlModuleOe;

// Footer
include 'theme/solenopsis/html/footer.php';
