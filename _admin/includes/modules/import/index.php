<?php
// Tools
use util\date as date;
use util\tools as tools;

if (array_key_exists('action', $_GET) && in_array($_GET['action'], array('install', 'cron_facebook_feed', 'run'))) {
    $_SERVER['PHP_SELF'] = 'login_admin.php';
    $_SERVER['SCRIPT_FILENAME'] = 'login_admin.php';
}


require 'includes/application_top.php';
require 'includes/modules/import/functions/functions.php';
require 'class/import_log.php';

ini_set('memory_limit', '2048M');
set_time_limit(-1);


// Variables
$url = 'import.php';
$pathModule = 'includes/modules/import';
$pathTemplate = $pathModule . '/template';
$title = 'Importadores';
$subtitle = 'Importadores de productos desde diferentes orígenes de datos';
$buttons = array();
$module = array_key_exists('module', $_POST) ? tep_db_input($_POST['module']) : (array_key_exists('module', $_GET) ? tep_db_input($_GET['module']) : false);
$action = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);
$page = tep_db_prepare_input(isset($_GET['page']) ? $_GET['page'] : '');

$modules = array('azimut');

// Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch (true) {
    case ($action == 'readme'):
        // Variables
        $subtitle = 'Instrucciones para la instalación del Módulo de Importadores';
        $buttons = array(
            array('title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $url),
        );

        $sHtmlModule = tools::parsedown($pathModule . '/readme.txt');
        break;

    case ($action == 'install'):
        // Insertamos admin file
        tools::insertAdminFiles($url, 1);

        // Reset cache
        tools::createCacheFile();

        // Mensajes
        $messageStack->addSession('success', 'El módulo <em>Importador</em> se ha instalado correctamente.', 'success');

        // Redireccionamos
        tep_redirect($url . '?action=readme');
        break;

    case (in_array($module, $modules) && $action == false):
        $parent = (isset($_GET['parent']) ? tep_db_prepare_input($_GET['parent']) : 2);
        $button = array();
		$page = (isset($_GET['page']) ? tep_db_prepare_input($_GET['page']) : 1);

        $title = 'Importadores';
        $subtitle = 'Importadores de productos de ' . ucfirst($module);

        $aJs = array('includes/modules/import/js/select2.js', 'includes/modules/import/js/import.js');
		$aStyle = array('theme/web/css/select2.min.css', 'includes/modules/import/css/style.css');

        $sSql = 'select import_categories_id, import_categories_name, import_categories_parent_id, import_categories_status, import_categories_mapped, (SELECT COUNT(ic2.import_categories_id) FROM import_categories ic2 WHERE ic2.import_categories_parent_id = ic.import_categories_id) AS qty_subcategories FROM import_categories ic WHERE import_categories_origin = "' . $module . '" AND import_categories_parent_id = "' . $parent . '"';
		$sSqlCount = 'SELECT COUNT(table_aux.import_categories_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aDatoSplit = new splitPageResults($page, 60, $sSql, $nAux, $sSqlCount);
		$aDatos = tep_db_query($sSql);
		$categoriesMapped = tep_db_query($sSql);

        $categories = array();
		$categoriesAll = getAllCategoryArray();
        getRecursiveIdCategories($categoriesAll, 0, $categories, '');

        if ($parent != 0 && $parent != 2) {
            $aux = tep_db_query('SELECT import_categories_parent_id FROM import_categories WHERE import_categories_id = "' . $parent . '";');

            if (tep_db_num_rows($aux) > 0) {
                $aux = tep_db_fetch_array($aux);

                $button[] = array('title' => 'Volver', 'icon' => 'fa-arrow-left', 'href' => $url . '?module=' . $module . ($aux['import_categories_parent_id'] != '' && $aux['import_categories_parent_id'] != 0 ? '&parent=' . $aux['import_categories_parent_id'] : ''));
            } else {
                $button[] = array('title' => 'Volver', 'icon' => 'fa-arrow-left', 'href' => $url);
            }
        } else {
            $button[] = array('title' => 'Volver', 'icon' => 'fa-arrow-left', 'href' => $url);
        }

        $buttons = array_merge($button, array(
            array('title' => 'Forzar importador', 'icon' => 'fa fa-eject', 'extra' => 'onclick="if (! confirm(\'¿Estás seguro que deseas forzar el importador?\')) return false;"', 'href' => $url . '?module=' . $module . '&action=' . (preg_match('/develop/i', $_SERVER['SERVER_NAME']) && $module == 'dietisur' ? 'test' : 'force')),
        ));

        if ($module != 'dietisur') {
        }

        // Template
        $sHtmlModule = includeTemplate($pathTemplate . '/' . $module . '.php');

        break;

    case (in_array($module, $modules) && $action == 'mapping'):
		$id = (isset($_POST['id']) ? $_POST['id'] : false);
		$selected = (isset($_POST['selected']) ? $_POST['selected'] : false);

		if ($id !== false && $selected !== false) {
			tep_db_query('UPDATE import_categories SET import_categories_mapped = "' . implode(',', $selected) . '" WHERE import_categories_id = "' . $id . '" AND import_categories_origin = "' . $module . '";');
		} else if ($id !== false && $selected === false) {
			tep_db_query('UPDATE import_categories SET import_categories_mapped = NULL WHERE import_categories_id = "' . $id . '" AND import_categories_origin = "' . $module . '";');
		}

		die();

		break;

    case (in_array($module, $modules) && $action == 'update'):
        tep_db_query('UPDATE import_categories SET import_categories_mapped = NULL WHERE import_categories_origin = "' . $module . '" AND import_categories_parent_id = "' . ($_GET['parent'] != '' ? $_GET['parent'] : '0') . '";');

        if (isset($_POST['categories_id'])) {
            foreach ($_POST['categories_id'] as $id => $category) {
                tep_db_query('UPDATE import_categories SET import_categories_mapped = "' . implode($category, ',') . '" WHERE import_categories_id = "' . $id . '" AND import_categories_origin = "' . $module . '";');
            }
        }

        $messageStack->addSession('success', 'Registros mapeados correctamente.', 'success');

        tep_redirect($url . '?module=' . $module . (isset($_GET['parent']) ? '&parent=' . tep_db_prepare_input($_GET['parent']) : ''));
        break;

    case (in_array($module, $modules) && $action == 'status'):
        function importStatusCategoriesRecursively($id, $flag)
		{
            tep_db_query('UPDATE import_categories SET import_categories_status = "' . $flag . '" WHERE import_categories_id = "' . $id . '";');

            $categories = tep_db_query('SELECT import_categories_id FROM import_categories WHERE import_categories_parent_id = "' . $id . '";');

            while ($category = tep_db_fetch_array($categories)) {
                importStatusCategoriesRecursively($category['import_categories_id'], $flag);
            }
        }

        $id = tep_db_prepare_input($_GET['mapped']);
        $flag = tep_db_prepare_input($_GET['flag']);

        importStatusCategoriesRecursively($id, $flag);

        break;

    case ($action == 'log'):
        $logs = tep_db_query('SELECT import_log_id, import_log_text, DATE_FORMAT(import_log_date, "%H:%i:%s") AS import_log_date_format FROM import_log ORDER BY import_log_date ASC;');

        if (tep_db_num_rows($logs) > 0) {

            while ($log = tep_db_fetch_array($logs)) {
                echo '<br />' . $log['import_log_date_format'] . ' - ' . $log['import_log_text'];
            }
        }

        die();
        break;

    case (in_array($module, $modules) && $action == 'force'):
/*
        tep_db_query('TRUNCATE TABLE import_log;');

        tep_db_query('UPDATE configuration SET configuration_value = 1 WHERE configuration_key = "IMPORT_' . strtoupper($module) . '_FORCE";');

        $messageStack->addSession('success', date('H:i:s') . ' - Se ha lanzado el importador. Por favor espere unos segundos...', 'success');

        tep_redirect($url . '?module=' . $module);
*/

        include $pathModule . '/' . $module . '.php';
        break;

    case (in_array($module, $modules) && $action == 'test'):
        include $pathModule . '/' . $module . '.php';
        break;

    case ($action == 'cron'):
        foreach ($modules as $module) {
            $aux = tep_db_query('SELECT configuration_value FROM configuration WHERE configuration_key = "IMPORT_' . strtoupper($module) . '_FORCE";');

            if (tep_db_num_rows($aux) > 0) {
                $aux = tep_db_fetch_array($aux);

                if ($aux['configuration_value'] == 1) {
                    // tep_db_query('UPDATE configuration SET configuration_value = 0 WHERE configuration_key = "IMPORT_' . strtoupper($module) . '_FORCE";');

                    include $pathModule . '/' . $module . '.php';
                }
            }
        }
        break;

    case (in_array($module, $modules) && $action == 'run'):
        include $pathModule . '/' . $module . '.php';
        break;

    case (in_array($module, $modules) && $action == 'configure'):
        $aStyle = array('includes/modules/import/css/style.css');
        $aJs = array('includes/modules/import/js/import.js');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            array_walk($_POST, function ($value, $key) {global $_POST; $_POST[$key] = tep_db_prepare_input($_POST[$key]);});

            foreach ($_POST as $key => $value) {
                if (preg_match('/^IMPORT_/i', $key)) {
                    if (defined($key)) {
                        tep_db_query('UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"');
                    } else {
                        tep_db_perform('configuration', array('configuration_key' => $key,
                            'configuration_value' => $value,
                            'configuration_group_id' => 26271));
                    }
                }

                if (preg_match('/_ACTIVE$/i', $key) && $value == 'false') {
                    tep_db_query('UPDATE products SET products_status = 0 WHERE products_model LIKE "' . strtoupper($module) . '_%";');
                }
            }

            $messageStack->addSession('success', 'Configuración actualizada correctamente.', 'success');

            tools::createCacheFile();

            sleep(5);

            tep_redirect($url . '?module=' . $module . '&action=configure');
        }

        $categories = tep_get_category_tree();

        $buttons = array(
            array('title' => 'Volver', 'href' => tep_href_link($url), 'icon' => 'fa-arrow-left'),
            array('title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde'),
        );

        $subtitle = 'Configuración del importador';

        // Template
        $sHtmlModule = includeTemplate($pathTemplate . '/' . $module . '_configure.php');
        break;

    case (in_array($module, $modules) && $action == 'clean'):
        /*
        ini_set('memory_limit', '2048M');
        set_time_limit(-1);

        $importLog = new import_log();

        $products = tep_db_query('SELECT products_id FROM products WHERE products_import_origin LIKE "' . strtoupper($module) . '%";');

        $importLog->setTotal(tep_db_num_rows($products));

        while ($product = tep_db_fetch_array($products)) {
        $importLog->addRow();
        tep_remove_product($product['products_id']);
        }

        $categories = tep_db_query('SELECT import_categories_id, import_categories_mapped FROM import_categories WHERE import_categories_origin = "' . $module . '" AND import_categories_mapped != "" AND import_categories_mapped IS NOT NULL;');

        while ($category = tep_db_fetch_array($categories)) {
        $aux = tep_db_query('SELECT COUNT(products_id) AS qty FROM products_to_categories WHERE categories_id = "' . $category['import_categories_mapped'] . '"');
        $aux = tep_db_fetch_array($aux);

        if ($aux['qty'] == 0) {
        $aux2 = tep_db_query('SELECT categories_id FROM categories WHERE parent_id = "' . $category['import_categories_mapped'] . '"');

        if (tep_db_num_rows($aux2) == 0) {
        tep_remove_category($category['import_categories_mapped']);
        tep_db_query('UPDATE import_categories SET import_categories_mapped = NULL WHERE import_categories_id = "' . $category['import_categories_id'] . '"');
        }
        }
        }

        $importLog->log('Limpieza completada.', 1);
         */

        die('FIN');
        break;

    default:
        $aStyle = array('includes/modules/import/css/style.css');

        // Template
        $sHtmlModule = includeTemplate($pathTemplate . '/index.php');
        break;
}

// Pintamos
echo includeTemplate($pathTemplate . '/base.php');
