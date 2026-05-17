<?php
/*
 * Points & Rewards V2.1rc2a — caducidad de puntos.
 * Originalmente por Ben Zukrel (Deep Silver Accessories) 2008, osCommerce, GPL.
 *
 * Refactor 2026-05-16:
 *  - Separado mantenimiento de productos/precios/ofertas → cron_maintenance.php
 *  - Sin emails de "se ejecutó el cron" (spam). Solo email si hubo error o avisos significativos.
 *  - INSERT fila negativa en customers_points_pending por cada cliente al caducar (points_type='EX',
 *    status=3, comment='Puntos caducados') para que el historial cuadre internamente con el saldo.
 *  - Esas filas EX NO se muestran al cliente en my_points.php (filtradas por points_type).
 *  - includes de idioma/layout movidos fuera del while (carga única).
 *
 * Invocar diario vía cron HTTP.
 */

include_once('includes/application_top.php');

// Guardia: solo aceptamos invocaciones desde el cron (User-Agent cPanel-Cron).
// El script está exento de login admin (application_top.php), así que necesita su propio gate.
if (($_SERVER['HTTP_USER_AGENT'] ?? '') !== 'cPanel-Cron') {
    http_response_code(403);
    exit('Forbidden');
}

echo "<pre>\n";

if ((USE_POINTS_SYSTEM != 'true') || !tep_not_null(POINTS_AUTO_EXPIRES)) {
    echo "Sistema de puntos desactivado o sin caducidad — nada que hacer.\n</pre>";
    return;
}

// 1) Registrar filas negativas internas (points_type='EX') ANTES de borrar saldos.
//    Permite reconstruir el historial completo (auditoría) sin mostrarlo al cliente.
$insAfected = 0;
tep_db_query("START TRANSACTION");
try {
    $resIns = tep_db_query("
        INSERT INTO " . TABLE_CUSTOMERS_POINTS_PENDING . "
          (customer_id, orders_id, points_pending, date_added,
           points_status, points_type, points_comment)
        SELECT
          customers_id, 0, -customers_shopping_points, NOW(),
          3, 'EX', 'Puntos caducados'
        FROM " . TABLE_CUSTOMERS . "
        WHERE customers_points_expires < CURDATE()
          AND customers_shopping_points IS NOT NULL
          AND customers_shopping_points > 0
    ");
    // Determinar cuántos clientes han sido afectados.
    $cntQ = tep_db_query("
        SELECT COUNT(*) n FROM " . TABLE_CUSTOMERS . "
        WHERE customers_points_expires < CURDATE()
          AND customers_shopping_points IS NOT NULL
          AND customers_shopping_points > 0
    ");
    $cntRow     = tep_db_fetch_array($cntQ);
    $insAfected = (int) $cntRow['n'];

    // 2) Vaciar saldos vencidos.
    tep_db_query("
        UPDATE " . TABLE_CUSTOMERS . "
        SET customers_shopping_points = NULL, customers_points_expires = NULL
        WHERE customers_points_expires < CURDATE()
    ");

    tep_db_query("COMMIT");
} catch (\Throwable $e) {
    tep_db_query("ROLLBACK");
    echo "ERROR caducando puntos: " . htmlspecialchars($e->getMessage()) . "\n</pre>";
    return;
}

echo "Caducados saldos de {$insAfected} clientes (fila EX interna registrada).\n";

// 3) Avisar a quienes caducan dentro de POINTS_EXPIRES_REMIND días, respetando RGPD.
if (!tep_not_null(POINTS_EXPIRES_REMIND)) {
    echo "POINTS_EXPIRES_REMIND no configurado — sin avisos.\n</pre>";
    return;
}

include_once(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CUSTOMERS_POINTS_PENDING);
// Carga única (fuera del while) del idioma y layout HTML que usa tep_mail.
include_once('../includes/languages/espanol.php');
$sLayoutPath = '../' . DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/varios.php';

$sSql = "
    SELECT c.customers_gender, c.customers_lastname, c.customers_firstname,
           c.customers_email_address, c.customers_shopping_points, c.customers_points_expires
    FROM " . TABLE_CUSTOMERS . " c
    INNER JOIN rgpd_account_term rgat ON rgat.customers_id = c.customers_id
    WHERE rgat.id_term_pivacy_trade = 4
      AND c.customers_points_expires = ADDDATE(CURDATE(), INTERVAL " . (int) POINTS_EXPIRES_REMIND . " DAY)
";

$customer_query = tep_db_query($sSql);
$avisados       = 0;
$errores        = 0;

while ($customer = tep_db_fetch_array($customer_query)) {
    $first_name = $customer['customers_firstname'];
    $last_name  = $customer['customers_lastname'];
    $name       = trim($first_name . ' ' . $last_name);

    if (ACCOUNT_GENDER == 'true') {
        $greet = ($customer['customers_gender'] === 'f')
            ? sprintf(EMAIL_GREET_MS, $first_name . ' ' . $last_name)
            : sprintf(EMAIL_GREET_MR, $first_name . ' ' . $last_name);
    } else {
        $greet = sprintf(EMAIL_GREET_NONE, $first_name);
    }

    $email = '<span style="padding: 0 30px; font-size: 18px; font-weight: bold; line-height: 14px; color: #1fa1d0;">' . $greet . '</span><br><br>'
        . '<span style="padding: 0px 30px; display: block; line-height: 14px;">' . EMAIL_EXPIRE_INTRO . '</span><br><br>'
        . '<br><br>'
        . '<span style="padding: 0 0 0 30px; display: block; line-height: 16px;">'
            . sprintf(EMAIL_EXPIRE_DET, number_format($customer['customers_shopping_points'], POINTS_DECIMAL_PLACES, ',', '.')) . '<br>'
            . sprintf(EMAIL_EXPIRE_DET2, tep_date_short($customer['customers_points_expires'])) . '<br><br>'
            . sprintf(EMAIL_TEXT_POINTS_URL,      tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS,      '', 'SSL'),    tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS,      '', 'SSL'))    . '<br><br>'
            . sprintf(EMAIL_TEXT_POINTS_URL_HELP, tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS_HELP, '', 'NONSSL'), tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS_HELP, '', 'NONSSL')) . '<br><br>'
            . EMAIL_TEXT_SUCCESS_POINTS . '<br>'
            . EMAIL_CONTACT . '<br>'
            . EMAIL_SEPARATOR . '<br><br>'
            . EMAIL_AUTO
        . '</span>';

    // Inyectar en el layout UHtmlEmails (la variable $email es leída y reescrita en varios.php)
    include $sLayoutPath;   // produce $sHtmlEmail
    $email = $sHtmlEmail;

    try {
        tep_mail($name, $customer['customers_email_address'], EMAIL_EXPIRE_SUBJECT, $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        $avisados++;
    } catch (\Throwable $e) {
        $errores++;
    }
}

echo "Avisados {$avisados} clientes (errores: {$errores}).\n";
echo "</pre>\n";
