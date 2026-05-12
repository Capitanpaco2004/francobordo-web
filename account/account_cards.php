<?php
// Aplicacion
include 'includes/application.php';
if (!$customerCore->hasLogin()) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
}

// needs to be included earlier to set the success message in the messageStack
require DIR_WS_LANGUAGES . $language . '/account_cards.php';

if (isset($_GET['action']) && ($_GET['action'] == 'process')) {
    // Variables
    $nCardId = intval(tep_db_prepare_input($_POST['card_id']));
    $sCardName = tep_db_prepare_input($_POST['card_name']);
    if ($nCardId > 0) {
        if ($sCardName != 'Sin nombre') {
            tep_db_query('UPDATE customers_cards SET customers_cards_name = "' . $sCardName . '" WHERE customers_cards_id = ' . (int) $nCardId);
        }
    
        if (isset($_POST['default_card']) && $_POST['default_card'] == 'on') {
            tep_db_query('UPDATE customers SET customers_default_card_id = ' . (int) $nCardId . ' WHERE customers_id = ' . (int) $customer_id);
            $customer_default_card_id = $nCardId;
        }
    
        $messageStack->add_session('account_cards', SUCCESS_ACCOUNT_CARDS_ENTRY_UPDATED, 'success');
    } else {
        $messageStack->add_session('account_cards', 'Vaya.. ha ocurrido un error', 'error');
    }
    

    tep_redirect('account_cards.php');
} elseif (isset($_GET['action']) && ($_GET['action'] == 'delete')) {
    // Variables
    $nCardId = tep_db_prepare_input($_GET['cID']);

    // Eliminamos la tarjeta
    tep_db_query('DELETE FROM customers_cards WHERE customers_cards_id = ' . (int) $nCardId);

    $messageStack->add_session('account_cards', SUCCESS_ADDRESS_BOOK_ENTRY_DELETED, 'success');

    // Si la tarjeta predeterminada es la misma que la que eliminamos
    if ($nCardId == $customer_default_card_id) {
        // Buscamos si tiene otras tarjetas
        $aAuxs = tep_db_query('SELECT * FROM customers_cards WHERE customers_id = ' . (int) $customer_id . ' ORDER BY customers_cards_id');

        if (tep_db_num_rows($aAuxs) > 0) {
            $aAux = tep_db_fetch_array($aAuxs);
            tep_db_query('UPDATE customers SET customers_default_card_id = ' . (int) $aAux['customers_cards_id'] . ' WHERE customers_id = ' . (int) $customer_id);
            $customer_default_card_id = $aAux['customers_cards_id'];
        } else {
            tep_db_query('UPDATE customers SET customers_default_card_id = NULL WHERE customers_id = ' . (int) $customer_id);
            tep_session_unregister('customer_default_card_id');
            tep_redirect('account.php');
        }
    }

    tep_redirect('account_cards.php');
}

$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_ACCOUNT, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link('account/account_cards.php', '', 'SSL'));

// Header
include 'account/includes/header.php';
?>


<div class="contenido">
	<h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>

    
						<div style="padding: 20px; margin-bottom: 20px; line-height: 1.7; border-bottom: 1px solid #ccc;">
							<i class="fa fa-exclamation-triangle"></i> Si usted quiere grabar los datos de una nueva tarjeta, puede hacerlo al realizar un pedido. Cuando se encuentre en la página de pago del pedido, seleccione el pago con “Tarjeta de crédito pago en un click” y debajo seleccione añadir más tarjetas. Pulse en confirmar compra y al pagar ese nuevo pedido, los datos de la nueva tarjeta quedarán grabados.
						</div>
                    
<?php
if ($messageStack->size('account_cards') > 0) {
    ?>
      <div class="mensaje"><?php echo $messageStack->output('account_cards'); ?></div>
<?php
}
?>
	<table border="0" width="100%" cellspacing="2" cellpadding="4">
		<tr>
			<td width="40%" valign="top" style="font-size: 15px; font-weight: bold;"><?php echo CARD_NAME; ?></td>
			<td width="30%" align="center" valign="top" style="font-size: 15px; font-weight: bold;"><?php echo CARD_EXPIRE; ?></td>
			<td width="10%" align="center" valign="top" style="font-size: 15px; font-weight: bold;"><?php echo CARD_PREDER; ?></td>
			<td width="20%" align="center" valign="top" style="font-size: 15px; font-weight: bold;"><?php echo CARD_ACTION; ?></td>
		</tr>
	</table>
	<?php
// Obtenemos todas las tarjetas del usuario
$aCards = tep_db_query('SELECT * FROM customers_cards WHERE customers_cards_identifier <> "Array" AND customers_id = ' . $customer_id);

while ($aCard = tep_db_fetch_array($aCards)) {
    echo tep_draw_form('account_cards', tep_href_link('account/account_cards.php?action=process', '', 'SSL'));
    echo tep_draw_hidden_field('card_id', $aCard['customers_cards_id']);

    echo '<table border="0" width="100%" cellspacing="2" cellpadding="4">';
    echo '<tr>';
    echo '<td width="40%" valign="top" class="main"><input type="text" name="card_name" value="' . ($aCard['customers_cards_name'] != '' ? $aCard['customers_cards_name'] : 'Sin nombre') . '" /></td>';
    echo '<td width="30%" align="center" valign="top" class="main" align="center">' . substr($aCard['customers_cards_expire'], 2) . '/' . substr($aCard['customers_cards_expire'], 0, 2) . '</td>';
    echo '<td width="10%" align="center" valign="top"class="main" align="center">' . tep_draw_checkbox_field('default_card', 'on', ($customer_default_card_id == $aCard['customers_cards_id'] ? true : false), 'onclick="this.form.submit();"') . '</td>';
    echo '<td width="20%" align="center" valign="top"class="main" align="center"><div class="bton-dflt smll">' . SMALL_IMAGE_BUTTON_EDIT . '<input type="submit" alt="' . SMALL_IMAGE_BUTTON_EDIT . '" title="' . SMALL_IMAGE_BUTTON_EDIT . '"></div>' . ' <a href="javascript:void(0);" data-msg="' . DELETE_ADDRESS_DESCRIPTION . '" data-href="account/account_cards.php?action=delete&cID=' . $aCard['customers_cards_id'] . '" class="delete-card">' . tep_image_button('small_delete.gif', SMALL_IMAGE_BUTTON_DELETE, 'smll') . '</a></td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
}
?>
</div>

<?php

// Footer
include 'account/includes/footer.php';
