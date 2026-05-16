<?php
/*
 * Points & Rewards V2.1rc2a — originalmente por Ben Zukrel (Deep Silver Accessories) para osCommerce, 2008.
 * Liberado bajo GPL. https://www.oscommerce.com
 *
 * Refactor 2026-05-15:
 * - PHP 8.x compat: `tep_session_*` → `$_SESSION` directo.
 * - Eliminadas las asignaciones muertas `$sql = "OPTIMIZE TABLE ..."` (nunca se ejecutaban).
 * - Transacciones en `tep_add_pending_points` y `tep_redeemed_points` para evitar saldo descuajado si falla un INSERT.
 * - Comparaciones estrictas donde aporta (POINTS_AUTO_ON).
 * - `for ($i; sizeof()...)` → `foreach` y simplificaciones de control de flujo (sin cambio funcional).
 */

// Saldo de puntos vigente del cliente (respetando caducidad y filtro de grupo)
function tep_get_shopping_points($id = '', $check_session = true)
{
    global $customer_id;

    if (!is_numeric($id)) {
        if (isset($_SESSION['customer_id'])) {
            $id = $customer_id;
        } else {
            return 0;
        }
    }

    if ($check_session && (!isset($_SESSION['customer_id']) || ($id != $customer_id))) {
        return 0;
    }

    if (tep_not_null(POINTS_AUTO_EXPIRES)) {
        $points_query = tep_db_query("select customers_shopping_points, cg.group_order_total_allowed from " . TABLE_CUSTOMERS . " c inner join customers_groups cg on(c.customers_group_id = cg.customers_group_id) where c.customers_id = '" . (int) $id . "' and c.customers_points_expires > CURDATE() limit 1");
    } else {
        $points_query = tep_db_query("select customers_shopping_points, cg.group_order_total_allowed from " . TABLE_CUSTOMERS . " c inner join customers_groups cg on(c.customers_group_id = cg.customers_group_id) where customers_id = '" . (int) $id . "' limit 1");
    }

    $points = tep_db_fetch_array($points_query);

    // FIXME: lógica original buggy — el 2º arg de preg_match es una comparación booleana (`$g == ''`),
    // por lo que NUNCA se matchea 'ot_redemptions'. En la práctica, cualquier grupo con
    // group_order_total_allowed != '' devuelve 0 puntos. Se preserva por estabilidad hasta confirmar
    // si afecta a clientes reales (consultar `customers_groups` y `customers.customers_group_id`).
    // Lógica esperada sería: $ok = $g === '' || preg_match('/ot_redemptions/i', $g);
    if ($points['group_order_total_allowed'] == '' || ($points['group_order_total_allowed'] != '' && preg_match('/ot_redemptions/i', $points['group_order_total_allowed'] == ''))) {
        return $points['customers_shopping_points'];
    }

    return 0;
}

// Equivalencia puntos → € (1 punto = REDEEM_POINT_VALUE €, IVA inc.)
function tep_calc_shopping_pvalue($points)
{
    return tep_round(((float) $points * (float) REDEEM_POINT_VALUE), 2);
}

// Importe del producto para calcular puntos (con o sin IVA)
function tep_display_points($products_price, $products_tax, $quantity = 1)
{
    if ((DISPLAY_PRICE_WITH_TAX == 'true') && (USE_POINTS_FOR_TAX == 'true')) {
        return tep_add_tax($products_price, $products_tax) * $quantity;
    }

    return $products_price * $quantity;
}

function tep_calc_products_price_points($products_price_points_query)
{
    return $products_price_points_query * POINTS_PER_AMOUNT_PURCHASE;
}

function tep_calc_price_pvalue($products_points_total)
{
    return tep_calc_shopping_pvalue($products_points_total);
}

// Reglas de productos elegibles para canje (model / pid / path)
function get_redemption_rules($order)
{
    if (!tep_not_null(RESTRICTION_MODEL) && !tep_not_null(RESTRICTION_PID) && !tep_not_null(RESTRICTION_PATH)) {
        return true;
    }

    if (tep_not_null(RESTRICTION_MODEL)) {
        foreach ($order->products as $product) {
            // NOTA: comportamiento original — sólo evalúa el primer producto y retorna inmediatamente.
            return (substr($product['model'], 0, 10) == RESTRICTION_MODEL);
        }
    }

    if (tep_not_null(RESTRICTION_PID)) {
        $p_ids = preg_split('/[,]/', RESTRICTION_PID);
        foreach ($order->products as $product) {
            if (in_array($product['id'], $p_ids)) {
                return true;
            }
        }
    }

    if (tep_not_null(RESTRICTION_PATH)) {
        $cat_ids = preg_split('/[,]/', RESTRICTION_PATH);
        foreach ($order->products as $product) {
            $sub_cat_ids = preg_split('/[_]/', tep_get_product_path($product['id']));
            if (array_intersect($sub_cat_ids, $cat_ids)) {
                return true;
            }
        }
    }

    return false;
}

function get_award_discounted($order)
{
    if (USE_POINTS_FOR_SPECIALS == 'false') {
        foreach ($order->products as $product) {
            if (tep_get_products_special_price($product['id']) > 0) {
                return true;
            }
        }
        return false;
    }

    return true;
}

// Base imponible que se premia (descontando shipping/tax/specials si así están las flags)
function get_points_toadd($order)
{
    if ($order->info['total'] <= 0) {
        return false;
    }

    $points_toadd = $order->info['total'];
    if (USE_POINTS_FOR_SHIPPING == 'false') $points_toadd -= $order->info['shipping_cost'];
    if (USE_POINTS_FOR_TAX      == 'false') $points_toadd -= $order->info['tax'];

    if (USE_POINTS_FOR_SPECIALS == 'false') {
        foreach ($order->products as $product) {
            if (tep_get_products_special_price($product['id']) > 0) {
                if (USE_POINTS_FOR_TAX == 'true') {
                    $points_toadd -= tep_add_tax($product['final_price'], $product['tax']) * $product['qty'];
                } else {
                    $points_toadd -= $product['final_price'] * $product['qty'];
                }
            }
        }
    }

    return $points_toadd;
}

// Suma puntos al cliente (auto-confirmados si POINTS_AUTO_ON='0', si no se quedan en pending status=1)
function tep_add_pending_points($customer_id, $insert_id, $points_toadd, $points_comment, $points_type)
{
    $points_awarded = ($points_type !== 'SP') ? $points_toadd : $points_toadd * POINTS_PER_AMOUNT_PURCHASE;
    // Convención francobordo 2026-05-15: los puntos no llevan decimales
    $points_awarded = (int) round($points_awarded);
    if ($points_awarded == 0) {
        return;
    }
    $is_auto        = (POINTS_AUTO_ON === '0');

    tep_db_query('START TRANSACTION');
    try {
        tep_db_perform(TABLE_CUSTOMERS_POINTS_PENDING, [
            'customer_id'    => (int) $customer_id,
            'orders_id'      => (int) $insert_id,
            'points_pending' => $points_awarded,
            'date_added'     => 'now()',
            'points_comment' => $points_comment,
            'points_type'    => $points_type,
            'points_status'  => $is_auto ? 2 : 1,
        ]);

        if ($is_auto) {
            if (tep_not_null(POINTS_AUTO_EXPIRES)) {
                $expire = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
                tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . (float) $points_awarded . "', customers_points_expires = '" . $expire . "' where customers_id = '" . (int) $customer_id . "' limit 1");
            } else {
                tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . (float) $points_awarded . "' where customers_id = '" . (int) $customer_id . "' limit 1");
            }
        }
        tep_db_query('COMMIT');
    } catch (\Throwable $e) {
        tep_db_query('ROLLBACK');
        throw $e;
    }
}

// Resta del saldo lo canjeado y registra el movimiento negativo en el historial
function tep_redeemed_points($customer_id, $insert_id, $customer_shopping_points_spending)
{
    // Convención francobordo 2026-05-15: los puntos canjeados se redondean a entero
    $customer_shopping_points_spending = (int) round($customer_shopping_points_spending);
    if ($customer_shopping_points_spending <= 0) {
        return;
    }

    tep_db_query('START TRANSACTION');
    try {
        if ((tep_get_shopping_points($customer_id) - $customer_shopping_points_spending) > 0) {
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points - '" . $customer_shopping_points_spending . "' where customers_id = '" . (int) $customer_id . "' limit 1");
        } else {
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = null, customers_points_expires = null where customers_id = '" . (int) $customer_id . "' limit 1");
        }

        if (DISPLAY_POINTS_REDEEMED == 'true') {
            tep_db_perform(TABLE_CUSTOMERS_POINTS_PENDING, [
                'customer_id'    => (int) $customer_id,
                'orders_id'      => (int) $insert_id,
                'points_pending' => -$customer_shopping_points_spending,
                'date_added'     => 'now()',
                'points_comment' => 'TEXT_DEFAULT_REDEEMED',
                'points_type'    => 'SP',
                'points_status'  => 4,
            ]);
        }
        tep_db_query('COMMIT');
    } catch (\Throwable $e) {
        tep_db_query('ROLLBACK');
        throw $e;
    }
}

function tep_add_welcome_points($customer_id)
{
    if (NEW_SIGNUP_POINT_AMOUNT <= 0) {
        return;
    }

    // Convención francobordo 2026-05-15: los puntos no llevan decimales
    $welcome_points = (int) round((float) NEW_SIGNUP_POINT_AMOUNT);
    if ($welcome_points == 0) {
        return;
    }

    if (tep_not_null(POINTS_AUTO_EXPIRES)) {
        tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $welcome_points . "', customers_points_expires = DATE_ADD(NOW(),INTERVAL '" . (int) POINTS_AUTO_EXPIRES . "' MONTH) where customers_id = '" . (int) $customer_id . "' limit 1");
    } else {
        tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $welcome_points . "' where customers_id = '" . (int) $customer_id . "' limit 1");
    }
}

function tep_get_last_date($key)
{
    $key_date_query = tep_db_query("select last_modified from " . TABLE_CONFIGURATION . " where configuration_key = '" . tep_db_input($key) . "' limit 1");
    $key_date       = tep_db_fetch_array($key_date_query);

    return tep_date_long($key_date['last_modified']);
}

function get_points_rules_discounted($order)
{
    if (REDEMPTION_DISCOUNTED != 'true') {
        return true;
    }

    foreach ($order->products as $product) {
        if (tep_get_products_special_price($product['id']) > 0) {
            return false;
        }
    }

    return true;
}

function get_redemption_awards($customer_shopping_points_spending)
{
    if (USE_POINTS_FOR_REDEEMED == 'false') {
        return !$customer_shopping_points_spending;
    }

    return true;
}

// Tope efectivo de puntos canjeables en el pedido actual
function calculate_max_points($customer_shopping_points)
{
    global $order;

    if (!$order) {
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new order;
    }

    $max_points = $order->info['total'] / REDEEM_POINT_VALUE;

    if (REDEMPTION_DISCOUNTED == 'true') {
        foreach ($order->products as $product) {
            $special_price = tep_get_products_special_price($product['id']);
            if ($special_price > 0) {
                $max_points -= ($special_price * $product['qty']) / REDEEM_POINT_VALUE;
            }
        }
    }

    if (tep_not_null(RESTRICTION_MODEL)) {
        foreach ($order->products as $product) {
            if ($product['model'] != RESTRICTION_MODEL) {
                $max_points -= ($product['price'] * $product['qty']) / REDEEM_POINT_VALUE;
            }
        }
    }

    if (tep_not_null(RESTRICTION_PID) && !tep_not_null(RESTRICTION_MODEL)) {
        $p_ids = preg_split('/[,]/', RESTRICTION_PID);
        foreach ($order->products as $product) {
            if (in_array($product['id'], $p_ids)) {
                $pid_points = ($product['price'] * $product['qty']) + ($order->info['shipping_cost'] + $order->info['tax']);
                $max_points = $pid_points / REDEEM_POINT_VALUE;
            }
        }
    }

    if (tep_not_null(RESTRICTION_PATH) && !tep_not_null(RESTRICTION_PID) && !tep_not_null(RESTRICTION_MODEL)) {
        $cat_ids = preg_split('/[,]/', RESTRICTION_PATH);
        foreach ($order->products as $product) {
            $sub_cat_ids = preg_split('/[_]/', tep_get_product_path($product['id']));
            if (array_intersect($sub_cat_ids, $cat_ids)) {
                $path_points = ($product['price'] * $product['qty']) + ($order->info['shipping_cost'] + $order->info['tax']);
                $max_points  = $path_points / REDEEM_POINT_VALUE;
            }
        }
    }

    $max_points = $max_points > POINTS_MAX_VALUE ? POINTS_MAX_VALUE : $max_points;
    $max_points = $customer_shopping_points > $max_points ? $max_points : $customer_shopping_points;

    // Convención francobordo 2026-05-15: los puntos canjeables se redondean a entero (floor para no exceder el saldo real)
    return (int) floor($max_points);
}

// Render de la caja de canje en el checkout
function points_selection($cart_show_total)
{
    global $currencies, $order;

    $customer_shopping_points = tep_get_shopping_points();
    if (!$customer_shopping_points || $customer_shopping_points <= 0) {
        return;
    }
    if (!get_redemption_rules($order) || (!get_points_rules_discounted($order) && !get_cart_mixed($order))) {
        return;
    }
    if ($customer_shopping_points < POINTS_LIMIT_VALUE) {
        return;
    }
    if (POINTS_MIN_AMOUNT != '' && $cart_show_total < POINTS_MIN_AMOUNT) {
        return;
    }

    unset($_SESSION['customer_shopping_points_spending']);

    $max_points = calculate_max_points($customer_shopping_points);

    $note = '';
    if ($order->info['total'] > tep_calc_shopping_pvalue($max_points)) {
        $note = '<br /><small>' . TEXT_REDEEM_SYSTEM_NOTE . '</small>';
    }

    $customer_shopping_points_spending = $max_points;
    ?>
    <div class="paymentRedeem boxStyle">
        <div class="titu1"><?php echo TABLE_HEADING_REDEEM_SYSTEM; ?></div>
        <label for="customer_shopping_points_spending" data-customer_shopping_points_spending="<?php echo $customer_shopping_points_spending; ?>">
            <?php printf(TEXT_REDEEM_SYSTEM_SPENDING_ONE_CHECKOUT, number_format($max_points, POINTS_DECIMAL_PLACES), $currencies->format(tep_calc_shopping_pvalue($max_points))); ?>
            <span class="check"></span>
            <span>
                <?php printf(TEXT_REDEEM_SYSTEM_START_ONECHECKOUT, $currencies->format(tep_calc_shopping_pvalue($customer_shopping_points)), $currencies->format($order->info['total']) . $note); ?>
            </span>
        </label>
    </div>
    <?php
}

function referral_input()
{
    if (!tep_not_null(USE_REFERRAL_SYSTEM)) {
        return;
    }

    global $customer_referred;
    ?>
    <tr>
        <td>
            <table border="0" width="100%" cellspacing="0" cellpadding="2">
                <tr><td class="main"><strong><?php echo TABLE_HEADING_REFERRAL; ?></strong></td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
                <tr class="infoBoxContents">
                    <td>
                        <table border="0" width="100%" cellspacing="5" cellpadding="2">
                            <tr>
                                <td class="main"><?php echo TEXT_REFERRAL_REFERRED; ?></td>
                                <td class="main"><?php echo tep_draw_input_field('customer_referred', $customer_referred); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php
}

function get_cart_mixed($order)
{
    if (sizeof($order->products) <= 1) {
        return false;
    }

    $special = false;
    $normal  = false;
    foreach ($order->products as $product) {
        if (tep_get_products_special_price($product['id']) > 0) {
            $special = true;
        } else {
            $normal = true;
        }
    }

    return $special && $normal;
}

/**
 * Helper común para enviar el email HTML al cliente cuando cambia su saldo.
 * Lo usan customers_points.php (alta manual), customers_points_pending.php (confirmación)
 * y customers_points_referral.php (referral/reviews). Sustituye 3 bloques de ~80 líneas duplicados.
 *
 * @param array $data {
 *   customer_id        int
 *   customers_email    string
 *   customers_gender   string ('m'|'f'|'')
 *   customers_firstname string
 *   customers_lastname  string
 *   points_delta       int|float  (+ añade, - retira)
 *   new_balance        int|float
 *   subject            string
 *   comment            string|null
 *   expire_msg         string|null     ya formateado tipo "Sus puntos caducan el dd/mm/yyyy"
 *   extra              array|null {
 *     order_id       int|null
 *     points_type    'RF'|'RV'|null
 *     products_name  string|null
 *     date_added     string|null      Y-m-d H:i:s
 *   }
 * }
 * @return bool true si tep_mail() devolvió OK.
 */
function tep_send_points_notification(array $data)
{
    global $currencies;

    $first = $data['customers_firstname'] ?? '';
    $last  = $data['customers_lastname']  ?? '';
    $name  = trim($first . ' ' . $last);

    if (ACCOUNT_GENDER == 'true') {
        $greet = (($data['customers_gender'] ?? '') === 'm')
            ? sprintf(EMAIL_GREET_MR, $last)
            : sprintf(EMAIL_GREET_MS, $last);
    } else {
        $greet = sprintf(EMAIL_GREET_NONE, $first);
    }

    $balance_msg = sprintf(
        EMAIL_TEXT_BALANCE,
        number_format($data['new_balance'], POINTS_DECIMAL_PLACES),
        $currencies->format($data['new_balance'] * REDEEM_POINT_VALUE)
    );

    $points_value = $currencies->format(abs($data['points_delta']) * REDEEM_POINT_VALUE);

    // Filas de la tabla — orden: contexto (order/type) primero, luego cantidad + valor
    $rows = [];
    if (!empty($data['extra']['order_id'])) {
        $rows[] = [EMAIL_TEXT_ORDER_NUMBER, '<b>' . (int) $data['extra']['order_id'] . '</b>'];
    }
    if (!empty($data['extra']['points_type'])) {
        $type_label = ($data['extra']['points_type'] === 'RF') ? TEXT_TYPE_REFERRAL : TEXT_DEFAULT_REVIEWS;
        $rows[] = [TABLE_HEADING_POINTS_TYPE, '<b>' . $type_label . '</b>'];
    }
    if (!empty($data['extra']['date_added'])) {
        $rows[] = [TABLE_HEADING_DATE_ADDED, '<b>' . tep_date_short($data['extra']['date_added']) . '</b>'];
    }
    $rows[] = [TABLE_HEADING_POINTS,       '<b>' . $data['points_delta'] . '</b>'];
    $rows[] = [TABLE_HEADING_POINTS_VALUE, '<b>' . $points_value . '</b>'];
    if (!empty($data['extra']['products_name'])) {
        $rows[] = [TABLE_HEADING_PRODUCTS_NAME, '<b>' . htmlspecialchars($data['extra']['products_name']) . '</b>'];
    }

    $tableHtml = '<table width="100%" style="padding: 0 44px; line-height: 20px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f;" border="0" cellspacing="0" cellpadding="0">';
    foreach ($rows as $r) {
        $tableHtml .= '<tr>'
            . '<td width="220" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">' . $r[0] . '</td>'
            . '<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . $r[1] . '</td>'
            . '</tr>';
    }
    $tableHtml .= '</table>';

    $comment_html = !empty($data['comment']) ? sprintf(EMAIL_TEXT_COMMENT, $data['comment']) . '<br><br>' : '';
    $expire_html  = !empty($data['expire_msg']) ? $data['expire_msg'] . '<br><br>' : '';

    $email = '<span style="padding: 0 30px; font-size: 18px; font-weight: bold; line-height: 14px; color: #1fa1d0;">' . $greet . '</span><br><br>'
        . '<span style="padding: 0px 30px; display: block; line-height: 14px;">' . EMAIL_TEXT_INTRO . '</span><br><br>'
        . $tableHtml . '<br><br>'
        . '<span style="padding: 0 0 0 30px; display: block; line-height: 16px;">'
            . $comment_html
            . $balance_msg . '<br>'
            . $expire_html
            . sprintf(EMAIL_TEXT_POINTS_URL,      tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS,      '', 'SSL'),    tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS,      '', 'SSL'))    . '<br><br>'
            . sprintf(EMAIL_TEXT_POINTS_URL_HELP, tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS_HELP, '', 'NONSSL'), tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS_HELP, '', 'NONSSL')) . '<br><br>'
            . EMAIL_TEXT_SUCCESS_POINTS . '<br>'
            . EMAIL_CONTACT . '<br>'
            . EMAIL_SEPARATOR . '<br><br>'
            . EMAIL_AUTO
        . '</span>';

    require_once DIR_FS_DOCUMENT_ROOT . 'includes/languages/espanol.php';
    require_once DIR_FS_DOCUMENT_ROOT . DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/varios.php';
    $email = $sHtmlEmail;

    return tep_mail($name, $data['customers_email'], $data['subject'], $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
}
