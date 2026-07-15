<?php

include('includes/modules/rma/install.php');
include('includes/modules/rma/cex.php'); // Integración Correos Express (recogidas/envíos RMA)
include('includes/modules/rma/seur.php'); // Integración SEUR (envíos/etiquetas de devolución RMA)
include('includes/modules/rma/correos.php'); // Integración Correos (etiquetas de devolución RMA, depósito en oficina)
include('includes/modules/rma/ontime.php'); // Integración Ontime (recogidas de devolución RMA)

// Métodos de reembolso que acreditan puntos al cliente al pasar el RMA a status=13.
// id=1  : método legacy "Puntos" (sin bonus) — solo accesible vía admin para ajustes manuales
// id=5  : método "Saldo en tu cuenta + 10% bonus" — el visible para el cliente
const RMA_PAYMENT_METHOD_POINTS_LEGACY = 1;
const RMA_PAYMENT_METHOD_POINTS_BONUS  = 5;
// Cap en € sobre el bonus del +10% (límite máximo de bonificación adicional)
const RMA_REFUND_BONUS_MAX_EUR = 50.00;
// Status del RMA que dispara la acreditación de puntos
const RMA_STATUS_REFUND_POINTS = 13;

function rmaSection() {
    global $sAction, $sTitle;

    rmaCheckInstallation();
    $sAction = $_GET['action'] != '' ? tep_db_prepare_input($_GET['action']) : 'list';
    $sFile = 'includes/modules/rma/views/'.$sAction.'.php';

    switch ($sAction) {
        case 'types-return':
            $sTitle = 'Tipos de retorno';
            break;
        case 'options-return':
            $sTitle = 'Razones de devolución';
            break;
        case 'list':
            $sTitle = 'Listado devoluciones';
            break;
        case 'status':
            $sTitle = 'Estados de las devoluciones';
            break;
        case 'payment-method':
            $sTitle = 'Métodos de reembolso';
            break;
        case 'view':
            $rmaDetail = getRmaDetail();
            $sTitle = 'Detalles para <strong>' . str_pad($rmaDetail['id'], 10, "0", STR_PAD_LEFT) . '</strong>';
            break;
        case 'change-payment-method':
            tep_db_perform(TABLE_RMA, array('payment_method' => intval($_POST['payment_method'])), 'update', 'id_rma = '. intval($_POST['id']));
            tep_redirect(tep_href_link('rma.php', 'action=view&id=' . intval($_POST['id'])));
            break;
        case 'change-return-method':
            tep_db_perform(TABLE_RMA, array('type_return' => intval($_POST['type_return'])), 'update', 'id_rma = '. intval($_POST['id']));
            tep_redirect(tep_href_link('rma.php', 'action=view&id=' . intval($_POST['id'])));
            break;
        case 'change-pickup-cost':
            // Coste de recogida (€) que el operador descuenta del importe a reembolsar.
            // NULL si se deja vacío; valor positivo si se introduce.
            $sRaw = trim((string) ($_POST['pickup_cost'] ?? ''));
            $aFields = ['pickup_cost' => ($sRaw === '' ? 'null' : (float) str_replace(',', '.', $sRaw))];
            tep_db_perform(TABLE_RMA, $aFields, 'update', 'id_rma = '. intval($_POST['id']));
            tep_redirect(tep_href_link('rma.php', 'action=view&id=' . intval($_POST['id'])));
            break;
        case 'kayako-search':
            // AJAX (fetch POST): tickets del cliente en Kayako (JSON + exit).
            rmaKayakoSearch();
            break;
        case 'kayako-create':
            // Crea ticket en Kayako, lo asigna al RMA (y a la caja del pedido) y redirige a Kayako.
            rmaKayakoCreate();
            break;
        case 'add-attachments':
            // Subida de imágenes/documentos por parte de un operador (lado admin).
            rmaAddAttachments();
            tep_redirect(tep_href_link('rma.php', 'action=view&id=' . intval($_POST['id'])));
            break;
        case 'remove-attachment':
            // Borra un adjunto AÑADIDO POR EL OPERADOR (source='staff'); nunca evidencia del cliente.
            rmaRemoveAttachment();
            tep_redirect(tep_href_link('rma.php', 'action=view&id=' . intval($_POST['id'])));
            break;
        case 'request-pickup':
            // Correos Express: genera envío+etiqueta o recogida sola para el RMA.
            rmaRequestPickup();
            break;
        case 'cex-label':
            // Descarga el PDF de etiqueta almacenado (hace exit dentro).
            rmaCexDownloadLabel();
            break;
        case 'cex-email-label':
            // Envía la etiqueta por email al cliente.
            rmaCexEmailLabel();
            break;
        case 'cex-cancel':
            // Anula una recogida en Correos Express.
            rmaCexCancel();
            break;
        case 'cex-set-env':
            // Interruptor global entorno Correos Express (test/pro).
            rmaCexSetEnv();
            break;
        case 'correos-label-gen':
            // Correos: preregistra devolución + genera etiqueta PDF.
            rmaCorreosGenerateLabel();
            break;
        case 'correos-label':
            // Descarga el PDF de etiqueta Correos almacenado (hace exit dentro).
            rmaCorreosDownloadLabel();
            break;
        case 'correos-email-label':
            // Envía la etiqueta Correos por email al cliente.
            rmaCorreosEmailLabel();
            break;
        case 'correos-cancel':
            // Anula el preregistro de devolución en Correos.
            rmaCorreosCancel();
            break;
        case 'seur-label-gen':
            // SEUR: graba envío de devolución + genera etiqueta PDF.
            rmaSeurGenerateLabel();
            break;
        case 'seur-label':
            // Descarga el PDF de etiqueta SEUR almacenado (hace exit dentro).
            rmaSeurDownloadLabel();
            break;
        case 'seur-email-label':
            // Envía la etiqueta SEUR por email al cliente.
            rmaSeurEmailLabel();
            break;
        case 'seur-cancel':
            // Anula un envío en SEUR.
            rmaSeurCancel();
            break;
        case 'seur-set-env':
            // Interruptor global entorno SEUR (pre/pro).
            rmaSeurSetEnv();
            break;
        case 'ontime-pickup':
            // Ontime: expedición + etiqueta + recogida de devolución.
            rmaOntimePickup();
            break;
        case 'ontime-label':
            // Descarga el PDF de etiqueta Ontime almacenado (hace exit dentro).
            rmaOntimeDownloadLabel();
            break;
        case 'ontime-email-label':
            // Envía la etiqueta Ontime por email al cliente.
            rmaOntimeEmailLabel();
            break;
        case 'ontime-set-env':
            // Interruptor global entorno Ontime (pre/pro).
            rmaOntimeSetEnv();
            break;
        case 'save-types-return':
            rmaSaveTypesReturn();
            tep_redirect(tep_href_link('rma.php', 'action=types-return'));
        case 'remove-types-return':
            rmaRemoveTypesReturn();
            tep_redirect(tep_href_link('rma.php', 'action=types-return'));
            break;
        case 'active-types-return':
            tep_db_perform(TABLE_RMA_TYPES_RETURN, array('active' => intval($_GET['active'])), 'update', 'id = '. intval($_GET['id']));
            tep_redirect(tep_href_link('rma.php', 'action=types-return'));
            break;

        case 'save-options-return':
            rmaSaveOptionsReturn();
            tep_redirect(tep_href_link('rma.php', 'action=options-return'));
            break;
        case 'remove-options-return':
            rmaRemoveOptionsReturn();
            tep_redirect(tep_href_link('rma.php', 'action=options-return'));
            break;
        case 'active-options-return':
            tep_db_perform(TABLE_RMA_OPTIONS, array('active' => intval($_GET['active'])), 'update', 'id = '. intval($_GET['id']));
            tep_redirect(tep_href_link('rma.php', 'action=options-return'));
            break;

        case 'save-status':
            rmaSaveStatus();
            tep_redirect(tep_href_link('rma.php', 'action=status'));
            break;
        case 'remove-status':
            rmaRemoveStatus();
            tep_redirect(tep_href_link('rma.php', 'action=status'));
            break;
        case 'active-status':
            tep_db_perform(TABLE_RMA_STATUS, array('active' => intval($_GET['active'])), 'update', 'id = '. intval($_GET['id']));
            tep_redirect(tep_href_link('rma.php', 'action=status'));
            break;

        case 'save-payment-method':
            rmaSavePaymentMethod();
            tep_redirect(tep_href_link('rma.php', 'action=payment-method'));
            break;
        case 'remove-payment-method':
            rmaRemovePaymentMethod();
            tep_redirect(tep_href_link('rma.php', 'action=payment-method'));
            break;
        case 'active-payment-method':
            tep_db_perform(TABLE_RMA_PAYMENT_METHODS, array('active' => intval($_GET['active'])), 'update', 'id = '. intval($_GET['id']));
            tep_redirect(tep_href_link('rma.php', 'action=payment-method'));
            break;

        case 'change-status':
            rmaChangeStatus();
            tep_redirect( $_SERVER['HTTP_REFERER'] );
            break;
        case 'remove-rma':
            rmaRemove();
            tep_redirect( $_SERVER['HTTP_REFERER'] );
            break;


    }

    include( THEME . 'html/header.php' );
    //Añadimos cabecera
    require('includes/modules/rma/views/header.php');
    //Añadimo módulo
    if (file_exists($sFile)) {
        require($sFile);
    }
    require('includes/modules/rma/views/footer.php');
    $sJavascript .= '<script type="text/javascript" src="includes/modules/rma/functions.js"></script>';
    include( THEME . 'html/footer.php' );
}


/*

Tipos de retorno

 */

function rmaGetTypesReturn($id = false) {
    global $languages_id;

    $typesReturn = array();

    if ($id > 0) {
        $sSql = 'SELECT tr.agencia, tr.id, tr.price_cost, trd.text, trd.languages_id FROM '.TABLE_RMA_TYPES_RETURN.' tr LEFT JOIN '.TABLE_RMA_TYPES_RETURN_DESCRIPTION.' trd on tr.id = trd.id_type_return WHERE tr.id = ' . $id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aDato = tep_db_fetch_array( $aSql )) {
                $typesReturn['agencia'] = $aDato['agencia'];
                $typesReturn['price_cost'] = $aDato['price_cost'];
                $typesReturn['languages'][$aDato['languages_id']]['text'] = $aDato['text'];
            }
        }
    } else {
        $sSql = 'SELECT tr.active, tr.agencia, tr.id, tr.price_cost, trd.text FROM '.TABLE_RMA_TYPES_RETURN.' tr LEFT JOIN '.TABLE_RMA_TYPES_RETURN_DESCRIPTION.' trd on tr.id = trd.id_type_return WHERE trd.languages_id = ' . $languages_id .' ORDER BY tr.price_cost DESC';
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
                $typesReturn[] = $aOptionReturn;
            }
        }
    }
    return $typesReturn;
}

function rmaSaveTypesReturn() {
    $languages = tep_get_languages();
    if (intval($_POST['id']) > 0) {
        $id = intval($_POST['id']);
        $aDatos = array('date_modify' =>  'now()', 'price_cost' => floatval($_POST['coste']), 'agencia' => intval($_POST['agencia']));
        tep_db_perform(TABLE_RMA_TYPES_RETURN, $aDatos, 'update', 'id = '. $id);

        foreach($languages as $language) {
            $aDatos = array('date_modify' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]));
            tep_db_perform(TABLE_RMA_TYPES_RETURN_DESCRIPTION, $aDatos, 'update', 'id_type_return = '. $id .' AND languages_id = ' . $language['id']);
        }
    } else {
        $aDatos = array('price_cost' => floatval($_POST['coste']), 'agencia' => intval($_POST['agencia']), 'date_added' =>  'now()');
        tep_db_perform(TABLE_RMA_TYPES_RETURN, $aDatos);
        $id = tep_db_insert_id();

        foreach($languages as $language) {
            $aDatos = array('date_added' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]), 'languages_id' => $language['id'], 'id_type_return' => $id);
            tep_db_perform(TABLE_RMA_TYPES_RETURN_DESCRIPTION, $aDatos);
        }
    }

}

function rmaRemoveTypesReturn() {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_TYPES_RETURN . ' WHERE id = ' . $id;
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_TYPES_RETURN_DESCRIPTION . ' WHERE id_type_return = ' . $id;
        foreach($sSql as $sql) {
            tep_db_query($sql);
        }
    }
}


/*

Razones para la devolución

 */

function rmaGetOptionsReturn($id = false) {
    global $languages_id;

    $optionsReturn = array();
    if ($id > 0) {
        $sSql = 'SELECT ro.muestra_reembolso, ro.plazo_maximo, ro.id, rod.text, rod.languages_id FROM ' . TABLE_RMA_OPTIONS . ' ro LEFT JOIN ' . TABLE_RMA_OPTIONS_DESCRIPTION.' rod on ro.id = rod.id_rma WHERE ro.id = ' . $id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aDato = tep_db_fetch_array( $aSql )) {
                $optionsReturn['plazo_maximo'] = $aDato['plazo_maximo'];
                $optionsReturn['muestra_reembolso'] = $aDato['muestra_reembolso'];
                $optionsReturn['languages'][$aDato['languages_id']]['text'] = $aDato['text'];
            }
        }
    } else {
        $sSql = 'SELECT ro.muestra_reembolso, ro.active, ro.plazo_maximo, ro.id, rod.text FROM ' . TABLE_RMA_OPTIONS . ' ro LEFT JOIN ' . TABLE_RMA_OPTIONS_DESCRIPTION.' rod on ro.id = rod.id_rma WHERE rod.languages_id = ' . $languages_id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
                $optionsReturn[] = $aOptionReturn;
            }
        }
    }


    return $optionsReturn;
}

function rmaSaveOptionsReturn() {
    $languages = tep_get_languages();
    if (intval($_POST['id']) > 0) {
        $id = intval($_POST['id']);
        $aDatos = array('date_modify' =>  'now()', 'plazo_maximo' => intval($_POST['plazo_maximo']), 'muestra_reembolso' => intval($_POST['muestra_reembolso']));
        tep_db_perform(TABLE_RMA_OPTIONS, $aDatos, 'update', 'id = '. $id);

        foreach($languages as $language) {
            $aDatos = array('date_modify' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]));
            tep_db_perform(TABLE_RMA_OPTIONS_DESCRIPTION, $aDatos, 'update', 'id_rma = '. $id .' AND languages_id = ' . $language['id']);
        }
    } else {
        $aDatos = array('date_added' =>  'now()', 'plazo_maximo' => intval($_POST['plazo_maximo']));
        tep_db_perform(TABLE_RMA_OPTIONS, $aDatos);
        $id = tep_db_insert_id();

        foreach($languages as $language) {
            $aDatos = array('date_added' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]), 'languages_id' => $language['id'], 'id_rma' => $id);
            tep_db_perform(TABLE_RMA_OPTIONS_DESCRIPTION, $aDatos);
        }
    }

}

function rmaRemoveOptionsReturn() {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_OPTIONS . ' WHERE id = ' . $id;
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_OPTIONS_DESCRIPTION . ' WHERE id_rma = ' . $id;
        foreach($sSql as $sql) {
            tep_db_query($sql);
        }
    }
}

function rmaGetStatusText($id_status, $language_id) {
    $sSql = 'SELECT sd.email_text FROM ' . TABLE_RMA_STATUS . ' s LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION.' sd on s.id = sd.id_status WHERE s.id = ' . $id_status .' AND sd.languages_id = ' . $language_id;
    $aSql = tep_db_query($sSql);
    if (tep_db_num_rows($aSql)) {
        $aDato = tep_db_fetch_array( $aSql );
        return $aDato['email_text'];
    }
}
/*

Estados de las devoluciones

 */

/**
 * Calcula el desglose de puntos que se acreditarían al cliente si se aprueba el RMA $id_rma
 * como devolución por puntos.
 *
 * Devuelve array con: importe_bruto (PVP IVA inc × qty), pickup_cost, importe_neto, bonus_eur,
 * bonus_capped (bool), total_eur, puntos, point_value, already_credited_at, payment_method.
 * Devuelve null si no se encuentra el RMA o no tiene productos asociados.
 */
function rmaCalcRefundPoints($id_rma) {
    $id_rma = (int) $id_rma;
    // Cabecera del RMA (sin JOIN para evitar duplicados si orders_products tiene varias líneas
    // con el mismo orders_id+products_id por atributos distintos)
    $q = tep_db_query("
        SELECT id_rma, customers_id, orders_id, products_id, quantity,
               payment_method, pickup_cost, points_credited_at
        FROM " . TABLE_RMA . "
        WHERE id_rma = " . $id_rma . " LIMIT 1
    ");
    $row = tep_db_fetch_array($q);
    if (!$row) return null;

    // Precio del producto en el pedido. Si hay varias líneas con el mismo (orders_id, products_id)
    // por distintos atributos, promediamos — el operador debería revisar a mano si los precios
    // difieren significativamente entre líneas. final_price ya incluye los modifiers de atributos.
    $qp = tep_db_query("
        SELECT AVG(final_price) AS unit_price, AVG(products_tax) AS tax_pct, COUNT(*) AS nlines
        FROM orders_products
        WHERE orders_id = " . (int) $row['orders_id'] . "
          AND products_id = " . (int) $row['products_id'] . "
    ");
    $rp = tep_db_fetch_array($qp);
    if (!$rp || $rp['unit_price'] === null) {
        $row['products_price'] = 0;
        $row['products_tax']   = 0;
        $row['nlines']         = 0;
    } else {
        $row['products_price'] = (float) $rp['unit_price'];
        $row['products_tax']   = (float) $rp['tax_pct'];
        $row['nlines']         = (int) $rp['nlines'];
    }

    $rate = (float) REDEEM_POINT_VALUE;
    if ($rate <= 0) $rate = 0.05; // fallback defensivo

    $price_iva_unit = (float) $row['products_price'] * (1 + (float) $row['products_tax'] / 100.0);
    $importe_bruto  = $price_iva_unit * (int) $row['quantity'];
    $pickup_cost    = (float) $row['pickup_cost'];
    $importe_neto   = max(0.0, $importe_bruto - $pickup_cost);

    $bonus_eur    = 0.0;
    $bonus_capped = false;
    if ((int) $row['payment_method'] === RMA_PAYMENT_METHOD_POINTS_BONUS) {
        $bonus_raw    = $importe_neto * 0.10;
        $bonus_eur    = min($bonus_raw, RMA_REFUND_BONUS_MAX_EUR);
        $bonus_capped = $bonus_raw > RMA_REFUND_BONUS_MAX_EUR;
    }
    $total_eur = $importe_neto + $bonus_eur;
    $puntos    = (int) round($total_eur / $rate);

    return [
        'id_rma'         => $id_rma,
        'customers_id'   => (int) $row['customers_id'],
        'orders_id'      => (int) $row['orders_id'],
        'products_id'    => (int) $row['products_id'],
        'quantity'       => (int) $row['quantity'],
        'payment_method' => (int) $row['payment_method'],
        'price_iva_unit' => $price_iva_unit,
        'importe_bruto'  => $importe_bruto,
        'pickup_cost'    => $pickup_cost,
        'importe_neto'   => $importe_neto,
        'bonus_eur'      => $bonus_eur,
        'bonus_capped'   => $bonus_capped,
        'total_eur'      => $total_eur,
        'puntos'         => $puntos,
        'point_value'    => $rate,
        'already_credited_at' => $row['points_credited_at'],
        // Avisos para el operador (mostrados en el preview admin)
        'warn_neto_zero'    => ($importe_neto <= 0 && $importe_bruto > 0),
        'warn_no_lines'     => ($importe_bruto <= 0),
        'warn_multilines'   => ((int) $row['nlines'] > 1),
        'order_lines_count' => (int) $row['nlines'],
    ];
}

/**
 * Acredita los puntos del RMA $id_rma al cliente. Idempotente: si ya se acreditaron
 * (points_credited_at IS NOT NULL) o el payment_method no es de puntos, no hace nada.
 *
 * - Inserta fila en customers_points_pending (status=2)
 * - Suma al saldo del cliente (con GREATEST por seguridad)
 * - Marca rma.points_credited_at = NOW()
 * - Registra en customers_points_audit (si la tabla existe)
 */
function rmaCreditRefundPoints($id_rma) {
    global $login_id;
    $calc = rmaCalcRefundPoints($id_rma);
    if (!$calc) return false;
    // Solo acreditar si es un método de puntos
    if (!in_array($calc['payment_method'], [RMA_PAYMENT_METHOD_POINTS_LEGACY, RMA_PAYMENT_METHOD_POINTS_BONUS], true)) {
        return false;
    }
    // Idempotencia
    if (!empty($calc['already_credited_at'])) return false;
    if ($calc['puntos'] <= 0) return false;

    $cID    = $calc['customers_id'];
    $oID    = $calc['orders_id'];
    $puntos = $calc['puntos'];

    $sComment = sprintf(
        'Devolución RMA #%d — %s€ IVA inc.%s%s',
        $calc['id_rma'],
        number_format($calc['importe_bruto'], 2, ',', '.'),
        ($calc['pickup_cost'] > 0 ? ' − recogida ' . number_format($calc['pickup_cost'], 2, ',', '.') . '€' : ''),
        ($calc['bonus_eur'] > 0
            ? ' + bonus ' . number_format($calc['bonus_eur'], 2, ',', '.') . '€' . ($calc['bonus_capped'] ? ' (capado a 50€)' : '')
            : '')
    );

    // Saldo antes (para auditoría)
    $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
    $row = tep_db_fetch_array($r);
    $saldoAntes = (int) round((float) $row['customers_shopping_points']);

    tep_db_query('START TRANSACTION');
    tep_db_query("
        INSERT INTO " . TABLE_CUSTOMERS_POINTS_PENDING . "
          (customer_id, orders_id, points_pending, date_added, points_status, points_type, points_comment)
        VALUES (" . $cID . ", " . $oID . ", " . $puntos . ", NOW(), 2, 'SP', '" . tep_db_input($sComment) . "')
    ");
    $insertId = (int) tep_db_insert_id();
    tep_db_query("
        UPDATE " . TABLE_CUSTOMERS . "
        SET customers_shopping_points = GREATEST(0, customers_shopping_points + " . $puntos . ")
        WHERE customers_id = " . $cID . " LIMIT 1
    ");
    tep_db_query("
        UPDATE " . TABLE_RMA . "
        SET points_credited_at = NOW()
        WHERE id_rma = " . $calc['id_rma'] . " LIMIT 1
    ");
    tep_db_query('COMMIT');

    // Saldo después
    $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
    $row = tep_db_fetch_array($r);
    $saldoDespues = (int) round((float) $row['customers_shopping_points']);

    // Auditoría (solo si la tabla existe)
    $checkTbl = tep_db_query("SHOW TABLES LIKE 'customers_points_audit'");
    if (tep_db_num_rows($checkTbl) > 0) {
        $adminEmail = '';
        if (!empty($login_id)) {
            $q2 = tep_db_query("SELECT admin_email_address FROM admin WHERE admin_id = " . (int) $login_id . " LIMIT 1");
            $r2 = tep_db_fetch_array($q2);
            $adminEmail = $r2['admin_email_address'] ?? '';
        }
        tep_db_perform('customers_points_audit', [
            'admin_id'          => (int) ($login_id ?? 0),
            'admin_email'       => $adminEmail,
            'customer_id'       => $cID,
            'action'            => 'rma_refund',
            'pending_unique_id' => $insertId,
            'points_delta'      => $puntos,
            'balance_before'    => $saldoAntes,
            'balance_after'     => $saldoDespues,
            'data_after'        => json_encode([
                'rma_id'        => $calc['id_rma'],
                'orders_id'     => $oID,
                'products_id'   => $calc['products_id'],
                'quantity'      => $calc['quantity'],
                'price_iva_unit'=> $calc['price_iva_unit'],
                'pickup_cost'   => $calc['pickup_cost'],
                'bonus_eur'     => $calc['bonus_eur'],
                'bonus_capped'  => $calc['bonus_capped'],
                'total_eur'     => $calc['total_eur'],
                'payment_method'=> $calc['payment_method'],
            ], JSON_UNESCAPED_UNICODE),
            'comment'           => $sComment,
            'notify_sent'       => 0,
            'ip'                => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'        => 'now()',
        ]);
    }

    return true;
}

 function rmaChangeStatus() {
     $id = intval($_POST['id']);
     $id_status = intval($_POST['id_status']);
     $language_id = intval($_POST['language_id']);
     $id_status_previous = intval($_POST['id_status_previous']);
     if ($id_status_previous <> $id_status) {

		 // Si el estado es "Devolución por puntos": acreditar puntos al cliente con/sin bonus
		 // según el payment_method elegido. Solo se acredita una vez (idempotencia vía points_credited_at).
		 if ($id_status == RMA_STATUS_REFUND_POINTS) {
		     rmaCreditRefundPoints($id);
		 }

		 tep_db_perform(TABLE_RMA, array('status' => $id_status, 'date_modify' => 'now()'), 'update', 'id_rma = '. $id);
         $aDatos = array();
         $message = $_POST['message'] != '' ? nl2br($_POST['message']) : rmaGetStatusText($id_status, $language_id);

         tep_db_perform(TABLE_RMA_STATUS_HISTORY, array('email_text'=>$message, 'notify' => intval($_POST['notify']),'id_rma' => $id, 'id_status' => $id_status,'message' => tep_db_prepare_input($_POST['message']),'private_message' => tep_db_prepare_input($_POST['private_message']), 'date_added' => 'now()'));
         if (intval($_POST['notify']) == 1) {
             $customersName = tep_db_prepare_input($_POST['customers_name']);
             $customersEmail = tep_db_prepare_input($_POST['customers_email']);
             //$customersEmail = 'daniellucia84@gmail.com';

             if ($customersEmail != '' && $customersName != '') {
                  if ($message != '') {
                     require('../includes/languages/espanol.php');
                     require(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/rma.php');
                     tep_mail($customersName, $customersEmail, 'El estado de su RMA ha cambiado', $sHtmlEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
                 }
             }
         }
     }
	 // Si el estado no cambia, pero tenemos mensaje privado
	 else if( tep_db_prepare_input( $_POST['private_message'] ) != '' )
	 {
		 // Insertamos en BBDD
         tep_db_perform( TABLE_RMA_STATUS_HISTORY, array( 'email_text' => '', 'notify' => intval( 0 ),'id_rma' => $id, 'id_status' => $id_status, 'message' => '', 'private_message' => tep_db_prepare_input( $_POST['private_message'] ), 'date_added' => 'now()' ) );
	 }
 }
 function rmaRemove() {
     $id = intval($_GET['id']);
     $aSql[] = 'DELETE FROM ' . TABLE_RMA .' WHERE id_rma = ' .$id;
     $aSql[] = 'DELETE FROM ' . TABLE_RMA_STATUS_HISTORY .' WHERE id_rma = ' .$id;
     foreach($aSql as $sSql) {
          tep_db_query($sSql);
     }

 }
function rmaGetStatus($id = false, $force_languages_id = false) {
    global $languages_id;

    $language_id = intval($force_languages_id) > 0 ? intval($force_languages_id) : $languages_id;
    $rmaStatus = array();
    if ($id > 0) {
        $sSql = 'SELECT s.id, s.color, sd.text, sd.email_text, sd.languages_id FROM ' . TABLE_RMA_STATUS . ' s LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION.' sd on s.id = sd.id_status WHERE s.id = ' . $id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aDato = tep_db_fetch_array( $aSql )) {
                $rmaStatus['color'] = $aDato['color'];
                $rmaStatus['languages'][$aDato['languages_id']]['text'] = $aDato['text'];
                $rmaStatus['languages'][$aDato['languages_id']]['email_text'] = $aDato['email_text'];
            }
        }
    } else {
        $sSql = 'SELECT s.active, s.id, s.color, sd.email_text, sd.text FROM ' . TABLE_RMA_STATUS . ' s LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION.' sd on s.id = sd.id_status WHERE sd.languages_id = ' . $language_id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
                $rmaStatus[] = $aOptionReturn;
            }
        }
    }


    return $rmaStatus;
}

function rmaSaveStatus() {
    $languages = tep_get_languages();
    if (intval($_POST['id']) > 0) {
        $id = intval($_POST['id']);
        $aDatos = array('date_modify' =>  'now()', 'color' => tep_db_prepare_input($_POST['color']));
        tep_db_perform(TABLE_RMA_STATUS, $aDatos, 'update', 'id = '. $id);

        foreach($languages as $language) {
            $aDatos = array('date_modify' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]), 'email_text' => tep_db_prepare_input($_POST['email_text'][$language['id']]));
            tep_db_perform(TABLE_RMA_STATUS_DESCRIPTION, $aDatos, 'update', 'id_status = '. $id .' AND languages_id = ' . $language['id']);
        }
    } else {
        $aDatos = array('date_added' =>  'now()', 'color' => tep_db_prepare_input($_POST['color']));
        tep_db_perform(TABLE_RMA_STATUS, $aDatos);
        $id = tep_db_insert_id();

        foreach($languages as $language) {
            $aDatos = array('date_added' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]), 'email_text' => tep_db_prepare_input($_POST['email_text'][$language['id']]), 'languages_id' => $language['id'], 'id_status' => $id);
            tep_db_perform(TABLE_RMA_STATUS_DESCRIPTION, $aDatos);
        }
    }

}

function rmaRemoveStatus() {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_STATUS . ' WHERE id = ' . $id;
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_STATUS_DESCRIPTION . ' WHERE id_status = ' . $id;
        foreach($sSql as $sql) {
            tep_db_query($sql);
        }
    }
}



/*

Métodos de pago

 */

function rmaGetPaymentMethod($id = false) {
    global $languages_id;

    $paymentMethods = array();
    if ($id > 0) {
        $sSql = 'SELECT pm.id, pmd.text, pmd.languages_id FROM '.TABLE_RMA_PAYMENT_METHODS.' pm LEFT JOIN '.TABLE_RMA_PAYMENT_METHODS_DESCRIPTION.' pmd on pm.id = pmd.id_payment_method WHERE pm.id = ' . $id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aDato = tep_db_fetch_array( $aSql )) {
                $paymentMethods['color'] = $aDato['color'];
                $paymentMethods['languages'][$aDato['languages_id']]['text'] = $aDato['text'];
            }
        }
    } else {
        $sSql = 'SELECT pm.active, pm.id, pmd.text FROM '.TABLE_RMA_PAYMENT_METHODS.' pm LEFT JOIN '.TABLE_RMA_PAYMENT_METHODS_DESCRIPTION.' pmd on pm.id = pmd.id_payment_method WHERE pmd.languages_id = ' . $languages_id;
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
                $paymentMethods[] = $aOptionReturn;
            }
        }
    }


    return $paymentMethods;
}

function rmaSavePaymentMethod() {
    $languages = tep_get_languages();
    if (intval($_POST['id']) > 0) {
        $id = intval($_POST['id']);
        $aDatos = array('date_modify' =>  'now()');
        tep_db_perform(TABLE_RMA_PAYMENT_METHODS, $aDatos, 'update', 'id = '. $id);

        foreach($languages as $language) {
            $aDatos = array('date_modify' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]));
            tep_db_perform(TABLE_RMA_PAYMENT_METHODS_DESCRIPTION, $aDatos, 'update', 'id_payment_method = '. $id .' AND languages_id = ' . $language['id']);
        }
    } else {
        $aDatos = array('date_added' =>  'now()');
        tep_db_perform(TABLE_RMA_PAYMENT_METHODS, $aDatos);
        $id = tep_db_insert_id();

        foreach($languages as $language) {
            $aDatos = array('date_added' =>  'now()', 'text' => tep_db_prepare_input($_POST['text'][$language['id']]), 'languages_id' => $language['id'], 'id_payment_method' => $id);
            tep_db_perform(TABLE_RMA_PAYMENT_METHODS_DESCRIPTION, $aDatos);
        }
    }

}

function rmaRemovePaymentMethod() {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_PAYMENT_METHODS . ' WHERE id = ' . $id;
        $sSql[] = 'DELETE FROM ' . TABLE_RMA_PAYMENT_METHODS_DESCRIPTION . ' WHERE id_payment_method = ' . $id;
        foreach($sSql as $sql) {
            tep_db_query($sql);
        }
    }
}


/*

Listado de devoluciones


 */
function getRmaList() {
    global $languages_id;

    $rmaList = array();

    $sWhere = '';
    if (intval($_GET['status_id']) > 0) {
        $sWhere .= ' AND r.status = ' . intval($_GET['status_id']);
    } else {
        if (intval($_GET['id_rma']) == 0 && $_GET['customer'] == '') {
            $sWhere .= ' AND r.status <> 5';
        }

    }

    if (intval($_GET['id_rma']) > 0) {
        $sWhere .= ' AND r.id_rma = ' . intval($_GET['id_rma']) ;
    }

    if ($_GET['customer'] != '') {
        $sWhere .= ' AND LOWER(r.customers_name) LIKE "%' . strtolower(tep_db_prepare_input($_GET['customer'])) .'%"';
    }
    $rmaList['sql'] = 'SELECT
    r.customers_name as entry_name,
    r.customers_email_address,
    r.languages_id,
    r.id_rma as id,
    r.orders_id,
    c.customers_id,
    r.products_id,
    r.quantity,
    op.products_name,
    op.final_price,
    op.products_tax,
    CONCAT(c.customers_firstname, " ", c.customers_lastname) as customer,
    tod.text as option_return,
    trd.text as type_return,
    rsd.text as status,
    r.status as status_id,
    DATE_FORMAT(r.date_added, "%d/%m/%Y") as date,
    DATE_FORMAT(o.date_purchased, "%d/%m/%Y") as date_purchased,
    o.date_purchased as date_purchased_raw,
    rs.color,
    pmd.text as payment_method,
    r.payment_method_default,
    DATEDIFF(CURDATE(),r.date_added) as dias,
    r.date_added as date_raw,
    DATE_FORMAT(rsh.date_added, "%d/%m/%Y") as ultima_modificacion,
    DATEDIFF(CURDATE(),rsh.date_added) as dias_ultima_modificacion,
    rsh.date_added as ultima_modificacion_raw,
    r.date_added
    FROM ' . TABLE_RMA .' r
    LEFT JOIN ' . TABLE_CUSTOMERS .' c ON c.customers_id = r.customers_id
    LEFT JOIN ' . TABLE_RMA_TYPES_RETURN_DESCRIPTION .' trd ON trd.id_type_return = r.type_return AND trd.languages_id = ' . $languages_id . '
    LEFT JOIN ' . TABLE_RMA_OPTIONS_DESCRIPTION .' tod ON tod.id = r.option_return AND tod.languages_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_RMA_PAYMENT_METHODS_DESCRIPTION .' pmd ON pmd.id_payment_method = r.payment_method AND pmd.languages_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_RMA_STATUS .' rs ON rs.id = r.status
    LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION .' rsd ON rsd.id_status = r.status AND rsd.languages_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_ORDERS .' o ON o.orders_id = r.orders_id
    LEFT OUTER JOIN ( SELECT id_rma, id, MAX(date_added) AS date_added FROM ' . TABLE_RMA_STATUS_HISTORY . ' GROUP BY id ORDER BY date_added DESC LIMIT 1) AS rsh ON rsh.id_rma = r.id_rma
    LEFT JOIN ' . TABLE_ORDERS_PRODUCTS .' op ON op.orders_id = r.orders_id AND op.products_id = r.products_id
    WHERE 1 = 1 ' . $sWhere . '
    GROUP BY r.id_rma
    ORDER BY r.id_rma DESC';

    $rmaList['sqlNumRows'] = 'select count(*) as total FROM ' . TABLE_RMA .' r
    LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION .' pd ON pd.products_id = r.products_id AND pd.language_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_CUSTOMERS .' c ON c.customers_id = r.customers_id
    LEFT JOIN ' . TABLE_RMA_TYPES_RETURN_DESCRIPTION .' trd ON trd.id_type_return = r.type_return AND trd.languages_id = ' . $languages_id . '
    LEFT JOIN ' . TABLE_RMA_OPTIONS_DESCRIPTION .' tod ON tod.id_rma = r.option_return AND tod.languages_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_RMA_PAYMENT_METHODS_DESCRIPTION .' pmd ON pmd.id_payment_method = r.payment_method AND pmd.languages_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_RMA_STATUS .' rs ON rs.id = r.status
    LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION .' rsd ON rsd.id_status = r.status AND rsd.languages_id = ' . $languages_id .'
    LEFT JOIN ' . TABLE_ORDERS .' o ON o.orders_id = r.orders_id
    WHERE 1 = 1 ' . $sWhere;

    $sSqlRef = tep_db_query( $rmaList['sqlNumRows'] );

    $rmaList['paging'] = new splitPageResults($_GET['page'], 10, $rmaList['sql'], $sSqlRef, $rmaList['sqlNumRows'] );

    //echo '<p>'.$sSql.'</p>';
    $aSql = tep_db_query($rmaList['sql']);
    if (tep_db_num_rows($aSql)) {
        while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
            $rmaList['data'][] = $aOptionReturn;
        }
    }

    $rmaList['count'] = $rmaList['paging']->display_count($sSqlRef, 10, intval($_GET['page']), 10);
    $rmaList['links'] = $rmaList['paging']->display_links($sSqlRef, 10, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], tep_get_all_get_params(array('page')));

    return $rmaList;
}

function rmaGetLanguages() {
    $languages = array();
    $languagesTemp = tep_get_languages();
    foreach ($languagesTemp as $language) {
        $languages[$language['id']] = $language;
    }
    return $languages;
}

function getRmaDetail() {
    global $languages_id;


    if (intval($_GET['id']) > 0) {
        $sSql = 'SELECT
        r.*,
        op.products_model,
        r.languages_id,
        r.id_rma as id,
		r.ticket,
        r.orders_id,
        c.customers_id,
        r.products_id,
        r.quantity,
        op.products_name,
        op.final_price,
        op.products_tax,
        CONCAT(c.customers_firstname, " ", c.customers_lastname) as customer,
        tod.text as option_return,
        trd.text as type_return,
        tr.agencia as agencia,
        rsd.text as status,
        r.status as status_id,
        DATE_FORMAT(r.date_added, "%d/%m/%Y") as date,
        DATE_FORMAT(o.date_purchased, "%d/%m/%Y") as date_purchased,
        o.date_purchased as date_purchased_raw,
        rs.color,
        pmd.text as payment_method,
        r.payment_method_default,
        DATEDIFF(CURDATE(),r.date_added) as dias,
        DATE_FORMAT(rsh.date_added, "%d/%m/%Y") as ultima_modificacion,
        DATEDIFF(CURDATE(),rsh.date_added) as dias_ultima_modificacion,
        rsh.date_added as ultima_modificacion_raw,
        r.date_added
        FROM ' . TABLE_RMA .' r
        LEFT JOIN ' . TABLE_PRODUCTS .' p ON p.products_id = r.products_id
        LEFT JOIN ' . TABLE_CUSTOMERS .' c ON c.customers_id = r.customers_id
        LEFT JOIN ' . TABLE_RMA_TYPES_RETURN .' tr ON tr.id = r.type_return
        LEFT JOIN ' . TABLE_RMA_TYPES_RETURN_DESCRIPTION .' trd ON trd.id_type_return = r.type_return AND trd.languages_id = ' . $languages_id . '
        LEFT JOIN ' . TABLE_RMA_OPTIONS_DESCRIPTION .' tod ON tod.id_rma = r.option_return AND tod.languages_id = ' . $languages_id .'
        LEFT JOIN ' . TABLE_RMA_PAYMENT_METHODS_DESCRIPTION .' pmd ON pmd.id_payment_method = r.payment_method AND pmd.languages_id = ' . $languages_id .'
        LEFT JOIN ' . TABLE_RMA_STATUS .' rs ON rs.id = r.status
        LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION .' rsd ON rsd.id_status = r.status AND rsd.languages_id = ' . $languages_id .'
        LEFT JOIN ' . TABLE_ORDERS .' o ON o.orders_id = r.orders_id
        LEFT OUTER JOIN ( SELECT id_rma, id, MAX(date_added) AS date_added FROM ' . TABLE_RMA_STATUS_HISTORY . ' GROUP BY id ORDER BY date_added DESC LIMIT 1) AS rsh ON rsh.id_rma = r.id_rma
        LEFT JOIN ' . TABLE_ORDERS_PRODUCTS .' op ON op.orders_id = r.orders_id AND op.products_id = r.products_id
        WHERE r.id_rma = '.intval($_GET['id']).'
        ORDER BY r.id_rma DESC';
        //echo '<pre>'.print_r($aSql, 1).'</pre>';
        $aSql = tep_db_query($sSql);
        return tep_db_fetch_array( $aSql );
    }
    return false;

}


function getRmaDataAddress($method = false, $id_rma = 0) {
    $aDataCustomer = array();
    $data['customers'] = array('customers_email_address', 'customers_name as entry_name','customers_company as entry_company','customers_street_address as entry_street_address','customers_suburb as entry_suburb','customers_city as entry_city','customers_postcode as entry_postcode','customers_state as entry_state','customers_country as entry_country');
    $data['delivery'] = array('delivery_name as entry_name','delivery_company as entry_company','delivery_street_address as entry_street_address','delivery_suburb as entry_suburb','delivery_city as entry_city','delivery_postcode as entry_postcode','delivery_state as entry_state','delivery_country as entry_country');
    $data['delivery_return'] = array('delivery_return_name as entry_name','delivery_return_company as entry_company','delivery_return_street_address as entry_street_address','delivery_return_suburb as entry_suburb','delivery_return_city as entry_city','delivery_return_postcode as entry_postcode','delivery_return_state as entry_state','delivery_return_country as entry_country');
    $data['billing'] = array('billing_name as entry_name','billing_company as entry_company','billing_nif as entry_nif','billing_street_address as entry_street_address','billing_suburb as entry_suburb','billing_city as entry_city','billing_postcode as entry_postcode','billing_state as entry_state','billing_country as entry_country');
    if ($method && !empty($data[$method]) && intval($id_rma) > 0) {
        $sSql = 'SELECT ' . implode(', ', $data[$method]) .' FROM ' . TABLE_RMA .' WHERE id_rma = ' . intval($id_rma);
        $aDataCustomer = array();
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            $aDataCustomer = tep_db_fetch_array( $aSql );
        }
    }


    return $aDataCustomer;
}

function getRmaHistoryStatus($id_rma) {
    global $languages_id;

    $historyStatus = array();
    $sSql = 'SELECT rsh.notify, rsh.email_text, rsh.private_message, rsh.message, rs.color, rsd.text as status, DATE_FORMAT(rsh.date_added, "%d/%m/%Y %h:%m:%s") as date
    FROM ' . TABLE_RMA_STATUS_HISTORY . ' rsh
    LEFT JOIN ' . TABLE_RMA_STATUS .' rs ON rs.id = rsh.id_status
    LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION .' rsd ON rsd.id_status = rsh.id_status AND rsd.languages_id = "' . $languages_id . '"
    WHERE rsh.id_rma = "' . intval($id_rma) . '" ORDER BY rsh.id DESC';
    $aSql = tep_db_query($sSql);
    if (tep_db_num_rows($aSql)) {
        while ($aHistoryStatus = tep_db_fetch_array( $aSql )) {
            $historyStatus[] = $aHistoryStatus;
        }
    }
    return $historyStatus;
}

function rmaDefaultValue($value, $default) {
    if ($value != '') {
        return $value;
    } else {
        return $default;
    }
}

function rmaGetPlazos($selected) {
    $plazos = array();
    $sSql = 'SELECT text, dias FROM ' . TABLE_RMA_PLAZOS. ' ORDER BY dias ASC';
    $aSql = tep_db_query($sSql);
    if (tep_db_num_rows($aSql)) {
        while ($aPlazo = tep_db_fetch_array( $aSql )) {
            if ($aPlazo['dias'] == $selected) {
                $aPlazo['selected'] = true;
            }
            $plazos[] = $aPlazo;
        }
    }
    return $plazos;
}


function rmaTraduceDias($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime ?? 'now');
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - $weeks * 7;

    $parts = array(
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    );

    $labels = array(
        'y' => 'año',
        'm' => 'mes',
        'w' => 'semana',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );

    $string = array();
    foreach ($parts as $k => $v) {
        if ($v) {
            $string[] = $v . ' ' . $labels[$k] . ($v > 1 ? 's' : '');
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    $salida = $string ? 'hace ' . implode(', ', $string) : 'justo ahora';
    return str_replace('mess', 'meses', $salida);
}


function rmaGetDateRecibied($id_rma) {
    $nIdRecibido = 1;
    $sSql = 'SELECT date_added as date_raw, DATE_FORMAT(date_added, "%d/%m/%Y") as date ' . (isset( $data[$method] ) ? implode(', ', $data[$method]) : '') .' FROM ' . TABLE_RMA_STATUS_HISTORY .' WHERE id_rma = ' . intval($id_rma) . ' AND id_status = ' . $nIdRecibido;
    $aData = array();
    $aSql = tep_db_query($sSql);
    if (tep_db_num_rows($aSql)) {
        $aData = tep_db_fetch_array( $aSql );
    }

    return $aData;
}


/**
 * Subida de adjuntos (imágenes / PDFs) a un RMA existente desde el admin.
 * El RMA ya existe (id conocido), así que escribe directo en images/rma/{id_rma}/
 * y registra en rma_attachments con source='staff'. Mismas reglas de validación
 * que la subida del cliente (ver rma::processUploads en includes/classes/rma.php):
 * máx 5 archivos, 5 MB c/u, extensiones permitidas y MIME real verificado.
 */
function rmaAddAttachments() {
    $idRma = intval($_POST['id'] ?? 0);
    if ($idRma <= 0) return;
    // El RMA debe existir
    $chk = tep_db_query("SELECT id_rma FROM " . TABLE_RMA . " WHERE id_rma = " . $idRma);
    if (!tep_db_num_rows($chk)) return;
    if (empty($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) return;

    // Subida del operador: SIN límite de nº de archivos ni de tamaño (a diferencia
    // del cliente). Solo se valida tipo/MIME por seguridad. Nota: a nivel servidor
    // PHP aún capa por request (max_file_uploads=20, upload_max_filesize=500M).
    $allowedExt  = array('jpg','jpeg','png','gif','webp','heic','heif','pdf');
    $allowedMime = array('image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif','application/pdf');

    $files  = $_FILES['attachments'];
    $count  = count($files['name']);
    $dstDir = DIR_FS_CATALOG . 'images/rma/' . $idRma;
    if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $size = (int) $files['size'][$i];
        if ($size <= 0) continue;
        $orig = basename($files['name'][$i]);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) continue;
        // MIME real (no solo el reportado por el navegador)
        $realMime = @mime_content_type($files['tmp_name'][$i]) ?: $files['type'][$i];
        if (!in_array($realMime, $allowedMime, true)) continue;

        // Prefijo "st" para distinguir visualmente del naming del cliente (NN_hex)
        $stored = sprintf('st%02d_%s.%s', $i, bin2hex(random_bytes(8)), $ext);
        $target = $dstDir . '/' . $stored;
        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
            @chmod($target, 0644);
            tep_db_perform('rma_attachments', array(
                'id_rma'            => $idRma,
                'filename_original' => substr($orig, 0, 255),
                'filename_stored'   => substr($stored, 0, 96),
                'mime_type'         => substr((string) $realMime, 0, 64),
                'size_bytes'        => $size,
                'date_added'        => 'now()',
                'source'            => 'staff',
            ));
        }
    }
}

/**
 * Borra un adjunto del RMA. SOLO se permite borrar los añadidos por el operador
 * (source='staff'); la evidencia subida por el cliente es intocable desde aquí.
 */
function rmaRemoveAttachment() {
    $idAtt = intval($_POST['att_id'] ?? 0);
    $idRma = intval($_POST['id'] ?? 0);
    if ($idAtt <= 0 || $idRma <= 0) return;
    $q = tep_db_query("SELECT filename_stored, source FROM rma_attachments WHERE id = " . $idAtt . " AND id_rma = " . $idRma);
    if (!tep_db_num_rows($q)) return;
    $row = tep_db_fetch_array($q);
    if (($row['source'] ?? 'client') !== 'staff') return;   // no tocar adjuntos del cliente
    $path = DIR_FS_CATALOG . 'images/rma/' . $idRma . '/' . basename($row['filename_stored']);
    if (is_file($path)) @unlink($path);
    tep_db_query("DELETE FROM rma_attachments WHERE id = " . $idAtt . " AND id_rma = " . $idRma);
}

/**
 * Tickets de Kayako en el RMA (2026-07-14). Reusa el helper
 * includes/functions/kayako_tickets.php (lo carga rma.php) y comparte tabla
 * con la caja "Tickets Kayako" del pedido: un ticket creado desde el RMA
 * también queda vinculado en orders_kayako_tickets.
 */
function rmaKayakoSearch() {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        echo json_encode(array('ok' => false, 'error' => 'Método no permitido.'));
        exit;
    }
    $iRma = (int)($_POST['id'] ?? 0);
    $oSql = tep_db_query('SELECT r.ticket, o.customers_email_address FROM ' . TABLE_RMA . ' r JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = r.orders_id WHERE r.id_rma = ' . $iRma);
    if (!tep_db_num_rows($oSql)) {
        echo json_encode(array('ok' => false, 'error' => 'RMA no encontrado.'));
        exit;
    }
    $aRow = tep_db_fetch_array($oSql);
    $aLookup = fb_kayako_lookup(array('email' => trim((string)$aRow['customers_email_address'])));
    if (!is_array($aLookup) || empty($aLookup['ok'])) {
        echo json_encode(array('ok' => false, 'error' => 'Kayako no responde (¿línea de la oficina caída?). Añade el ticket a mano.'));
        exit;
    }
    foreach ($aLookup['tickets'] as $iKey => $aTicket) {
        $aLookup['tickets'][$iKey]['linked'] = ((string)$aTicket['mask'] === (string)$aRow['ticket']);
    }
    echo json_encode($aLookup, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function rmaKayakoCreate() {
    $iRma = (int)($_POST['id'] ?? 0);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $iRma <= 0) {
        tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $iRma));
    }
    $sSubject = trim((string)($_POST['ticket_subject'] ?? ''));
    $iDept = (int)($_POST['ticket_department'] ?? 4);
    if (!array_key_exists($iDept, fb_kayako_departments())) {
        $iDept = 4; // RMA
    }
    $oSql = tep_db_query('SELECT r.orders_id, o.customers_email_address, o.customers_name FROM ' . TABLE_RMA . ' r JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = r.orders_id WHERE r.id_rma = ' . $iRma);
    if (!tep_db_num_rows($oSql)) {
        tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $iRma . '&kayako_error=1'));
    }
    $aRow = tep_db_fetch_array($oSql);
    if ($sSubject === '') {
        $sSubject = 'RMA ' . $iRma . ' - Pedido ' . (int)$aRow['orders_id'];
    }

    $aResp = fb_kayako_create_ticket(array(
        'email'         => trim((string)$aRow['customers_email_address']),
        'fullname'      => trim((string)$aRow['customers_name']),
        'subject'       => $sSubject,
        'order_id'      => (int)$aRow['orders_id'],
        'department_id' => $iDept,
        'staff_email'   => trim((string)($_SESSION['login_email_address'] ?? '')),
    ));
    if (!is_array($aResp) || empty($aResp['ok']) || empty($aResp['mask'])) {
        tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $iRma . '&kayako_error=1'));
    }
    $sMask = strtoupper((string)$aResp['mask']);
    tep_db_query('UPDATE ' . TABLE_RMA . ' SET ticket = "' . tep_db_input($sMask) . '" WHERE id_rma = ' . $iRma);

    // También a la caja "Tickets Kayako" del pedido.
    $sSubjStore = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $sSubject);
    $oDup = tep_db_query("select id from orders_kayako_tickets where orders_id = '" . (int)$aRow['orders_id'] . "' and ticket_mask = '" . tep_db_input($sMask) . "'");
    if (!tep_db_num_rows($oDup)) {
        tep_db_perform('orders_kayako_tickets', array(
            'orders_id'   => (int)$aRow['orders_id'],
            'ticket_mask' => tep_db_prepare_input($sMask),
            'subject'     => tep_db_prepare_input(mb_substr($sSubjStore, 0, 255)),
            'date_added'  => 'now()',
            'added_by'    => tep_db_prepare_input(mb_substr(trim((string)($_SESSION['login_first_name'] ?? '')), 0, 64)),
        ));
    }

    // Directo a Kayako para que el operador escriba el email al cliente.
    tep_redirect(fb_kayako_staff_url($sMask));
}
