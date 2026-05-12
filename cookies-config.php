<?php
require 'includes/application_top.php';

require DIR_WS_LANGUAGES . $language . '/' . basename(__FILE__);
$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(basename(__FILE__), '', 'SSL'));

if ($_GET['action'] == 'process') {

    $cookieList = ['cookies-funcionales', 'cookies-analiticas', 'cookies-publicidad', 'cookies-cesion'];
    foreach ($cookieList as $value) {
        $_SESSION[$value] = false;
        if (isset($_POST[$value]) && $_POST[$value] == 'true') {
            $_SESSION[$value] = true;
        }
    }

    tep_redirect(tep_href_link('index.php'));
}

$cookieList = [
    'cookies-funcionales' => [
        'title' => COOKIES_FUNCIONALES_TITLE,
        'text' => COOKIES_FUNCIONALES_TEXT,
        'checked' => ($_SESSION['cookies-funcionales'] === false ? false : true),
    ],
    'cookies-analiticas' => [
        'title' => COOKIES_ANALYTICS_TITLE,
        'text' => COOKIES_ANALYTICS_TEXT,
        'checked' => ($_SESSION['cookies-analiticas'] === false || !isset($_SESSION['cookies-analiticas']) ? false : true),
    ],
    'cookies-publicidad' => [
        'title' => COOKIES_PUBLICIDAD_TITLE,
        'text' => COOKIES_PUBLICIDAD_TEXT,
        'checked' => ($_SESSION['cookies-publicidad'] === false || !isset($_SESSION['cookies-publicidad']) ? false : true),
    ],
    'cookies-cesion' => [
        'title' => COOKIES_CESION_TITLE,
        'text' => COOKIES_CESION_TEXT,
        'checked' => ($_SESSION['cookies-cesion'] === false || !isset($_SESSION['cookies-cesion']) ? false : true),
    ],
];

require DIR_THEME . 'html/header.php';
require DIR_THEME . 'html/column_left.php';
include DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__);
require DIR_THEME . 'html/column_right.php';
require DIR_THEME . 'html/footer.php';
require DIR_WS_INCLUDES . 'application_bottom.php';
