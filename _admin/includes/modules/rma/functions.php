<?php

include('includes/modules/rma/install.php');

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

 function rmaChangeStatus() {
     $id = intval($_POST['id']);
     $id_status = intval($_POST['id_status']);
     $language_id = intval($_POST['language_id']);
     $id_status_previous = intval($_POST['id_status_previous']);
     if ($id_status_previous <> $id_status) {

		 // Si el estado es devolución por puntos
		 if( $id_status == 13 )
		 {
			 // Obtenemos al usuario
			 $aCustomer = tep_db_query( 'SELECT customers_id, orders_id, products_id FROM rma WHERE id_rma = "' . $id . '";' );
			 $aCustomer = tep_db_fetch_array( $aCustomer );

			 // Obtenemos el precio de la devolución
			 $aPrice = tep_db_query( 'SELECT products_price FROM orders_products WHERE orders_id = "' . $aCustomer['orders_id'] . '" AND products_id = "' . $aCustomer['products_id'] . '";' );
			 $aPrice = tep_db_fetch_array( $aPrice );

			 // Actualizamos los puntos del cliente
			 tep_db_query( 'UPDATE customers SET customers_shopping_points = customers_shopping_points + ' . ( $aPrice['products_price'] / 0.06 ) . ' WHERE customers_id = "' . $aCustomer['customers_id'] . '";' );

			 // Añadimos log
			 tep_db_query( 'INSERT INTO customers_points_pending (customer_id, orders_id, points_pending, points_comment, date_added, points_status) VALUES ("' . $aCustomer['customers_id'] . '", "' . $aCustomer['orders_id'] . '", "' . ( $aPrice['products_price'] / 0.06 ) . '", "Puntos añadidos del RMA con ID: ' . $id . '", NOW(), 2);' );
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
