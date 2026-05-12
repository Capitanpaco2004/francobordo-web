<?php
/**
 * #XCC-313-91043
 */

// Aplicacion
include 'includes/application.php';

if (!Affiliates::customerIsAffiliate(intval($customer_id), false)) {
    tep_redirect('account.php');
}

$affiliate = Affiliates::getAffiliateCustomer(intval($customer_id));

if (empty($affiliate)) {
    tep_redirect('account.php');
}

$networksAccepted = (constant('AFFILIATES_NETWORKS_ACCEPTED') != '' ? array_map('trim', explode("\n", constant('AFFILIATES_NETWORKS_ACCEPTED'))) : array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    //Comprobamos que tenemos redes sociales
    $networks = [];
    if (isset($_POST['network'])) {
        foreach ($networksAccepted as $network) {
            if (isset($_POST['network'][$network]) && $_POST['network'][$network] != '') {
                $networks[$network] = $_POST['network'][$network];
            }
        }
    }

    $data = array(
        'bio' => tep_db_prepare_input($_POST['bio']),
        'networks_json' => json_encode($networks),
        'image' => $affiliate['image'],
    );

    if ($_FILES['image']['tmp_name'] != '') {

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), array('gif', 'jpg', 'png'))) {
            $image = md5(time()) . '.' . $ext;

            if (move_uploaded_file($_FILES['image']['tmp_name'], DIR_WS_IMAGES . 'influencers/' . $image)) {
                unlink(DIR_WS_IMAGES . 'influencers/' . $affiliate['image']);
                $data['image'] = $image;
            }
        } else {
            $messageStack->addSession('account_affiliates', 'La imagen no tiene un formato válido', 'error');
            tep_redirect('account_affiliate.php');
        }
    }

    tep_db_perform(
        'affiliates',
        $data,
        'update',
        'customers_id = "' . (int) $customer_id . '"'
    );

    $messageStack->addSession('account_affiliates', 'Operación llevada con éxito', 'success');
    tep_redirect('account_affiliate.php');

}

if ($_GET['process'] == 'payment') {

    $dato = tep_db_query('select customers_firstname, customers_lastname, customers_email_address from customers where customers_id = "' . (int) $customer_id . '"');
    $dato = tep_db_fetch_array($dato);

    $sql = sprintf(
        'SELECT id, orders_total as value, comission
		FROM affiliates_orders
		WHERE affiliate_id = %d AND status = "%s"',
        $affiliate['id'],
        'prepared'
    );
    $sql = tep_db_query($sql);

    $ids = [];
    $total = 0;
    while ($order = tep_db_fetch_array($sql)) {
        $ids[] = $order['id'];
        $total += $order['comission'];
    }

    if (!empty($ids)) {

        switch ($_GET['type']) {
            case 'invoice':
                $type = 'invoice';
                $email = sprintf(
                    'El afiliado con el e-mail %s ha solitado el pago de sus comisiones creando una <strong>factura</strong> por un valor de %s.<br>Quede a la espera del recibir la factura correspondiente por parte del afiliado.
					Puede <a href="%s">ver más información aquí</a>',
                    $dato['customers_email_address'],
                    $currencies->format($total),
                    tep_href_link('_admin/affiliates.php', 'action=history&id=' . $affiliate['id'])
                );
                break;

            default:
                $type = 'points';
                $email = sprintf(
                    'El afiliado con el e-mail %s ha solitado el pago de sus comisiones <strong>en forma de puntos</strong> por un valor de %s.<br>
					Puede <a href="%s">ver más información aquí</a>',
                    $dato['customers_email_address'],
                    $currencies->format($total),
                    tep_href_link('_admin/affiliates.php', 'action=history&id=' . $affiliate['id'])
                );
                break;
        }

        tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, 'Solicitud de pago afiliado', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

        tep_db_perform(
            'affiliates_history',
            array(
                'affiliate_id' => $affiliate['id'],
                'status' => 'pending',
                'total' => $total,
                'type' => $type,
                'date_created' => 'now()',
            )
        );

        $sql = sprintf(
            'UPDATE affiliates_orders SET history_id = %d, status = "%s" WHERE id IN (%s)',
            tep_db_insert_id(),
            'requested',
            implode(',', $ids)
        );
        tep_db_query($sql);

        $messageStack->addSession('account_affiliates', 'Operación llevada con éxito', 'success');
    }

    tep_redirect('account_affiliate.php');
}

setlocale(LC_MONETARY, 'es_ES');

$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_AFFILIATE, ''));

include 'account/includes/header.php';

echo '<div class="ccTitle">' . NAVBAR_TITLE_2 . '</div>';

if (!Affiliates::customerIsAffiliate(intval($customer_id))) {
    echo '<div class="mensaje warning">Su cuenta está pendiente de aprobación</div>';
} else {
    echo $messageStack->show('account_affiliates');

    echo '<ul class="xtabs affiliates-tabs" data-tabs id="affiliate-tab">';
    echo '<li class="xtabs-title actv" data-tabs-link><span class="skew">Mis datos</span></li>';
    echo '<li class="xtabs-title" data-tabs-link><span class="skew">Comisiones</span></li>';
    echo '<li class="xtabs-title" data-tabs-link><span class="skew">Historial de pagos</span></li>';
    echo '</ul>';

    echo '<div class="ccCnt ccOrderInfo">';

    echo '<div class="xtabs-content" data-tabs-content="affiliate-tab">';
    echo '<div class="row dx ccOrder xtabs-item"  itemprop="description">';

    echo '<div class="col ccHead d03">' . HEADING_COUPON . '</div>';
    echo '<div class="col d09" style="padding-left: 20px"><div class="xform"><input style="margin: 0;" type="text" readonly value="' . $affiliate['coupon'] . '" /></div></div>';

    echo '<div class="col ccHead d03">' . HEADING_URL . '</div>';
    echo '<div class="col d09" style="padding-left: 20px"><div class="xform"><input style="margin: 0;" type="text" readonly value="' . tep_href_link('index.php', 'ref-affiliate=' . $affiliate['coupon']) . '" /></div></div>';

    echo '</div>';
    echo '<div class="row dx ccOrder xtabs-item"  itemprop="description">';
    $orders = Affiliates::getOrdersFromAffiliate($affiliate['id'], $affiliate['sales_comission'], ['pending', 'prepared']);
    if (!empty($orders)) {
        echo '<div class="col d12">';
        echo '<div class="ccHead">' . HEADING_DETAILS . '</div>';
        echo '
			<table class="hover">
				<thead>
					<tr>
						<!--<th class="tcenter">' . HEADING_VALUE_COMISSION . '</th>-->
						<th class="tcenter">' . HEADING_COMISSION . '</th>
						<th class="tcenter">' . HEADING_DATE . '</th>
						<th class="tcenter">' . HEADING_DAYS_LEFT . '</th>
						<th class="tcenter">' . HEADING_COMISSION_STATUS . '</th>
					</tr>
				</thead>
				';

        $total_comission = 0;
        $can_comission = false;

        foreach ($orders as $order) {

            if ($order['comission'] == 0) {
                continue;
            }

            echo '<tr>
						<!--<td class="tcenter" width="200">' . $currencies->format($order['comission_value']) . '</td>-->
						<td class="tcenter">' . $currencies->format($order['comission']) . '</td>
						';
            echo '<td class="tcenter">' . date('d/m/Y H:i:s', strtotime($order['date_created'])) . '</td>';

            if ($order['date_order_completed'] != '0000-00-00 00:00:00' && $order['date_order_completed'] != '') {
                $dateVerification = new DateTime($order['date_order_completed']);
                $dateVerification->add(new DateInterval(sprintf('P%dD', AFFILLIATES_DAYS_LEFT)));

                $now = new DateTime();
                $daysLeft = $now->diff($dateVerification);
                $days = intval($daysLeft->format('%r%a'));
                if ($order['status'] == 'prepared') {
                    echo '<td class="tcenter">--</td>';
                } else {
                    if ($days >= 0) {
                        echo '<td class="tcenter"><strong>' . $daysLeft->format('%r%a') . ' días</strong><br /><small>' . $dateVerification->format('d/m/Y H:i:s') . '</small></td>';
                    } else {
                        echo '<td class="tcenter">--</td>';
                    }
                }

            } else {
                if ($order['status'] == 'prepared') {
                    echo '<td class="tcenter">--</td>';
                } else {
                    echo '<td class="tcenter">En espera de verificación</td>';
                }
            }

            if ($order['status'] == 'prepared') {
                echo '<td class="tcenter"><strong>Preparado</strong></td>';
                $total_comission += $order['comission'];
                $can_comission = true;
            } else {
                echo '<td class="tcenter">Pendiente</td>';
            }
            echo '</tr>';
        }

        if ($total_comission > 0) {
            echo '<tr>
					<td class="tright" colspan="5" style="border-top: 1px solid #ccc"><strong>' . $currencies->format($total_comission) . '</strong></td>
				</tr>';
        }

        echo '</table>';

        if ($can_comission && $total_comission > AFFILLIATES_MINIMUM) {
            echo '<div class="tcenter" style="margin-top: 20px; width: 100%;">';
            echo '<a href="' . tep_href_link('account/account_affiliate.php', 'process=payment&type=invoice') . '" class="xbutton" style="margin-top: 4px !important; margin-left: 4px!important;font-size: 1.3em;"><i class="fa fa-credit-card"></i> Retirar fondos creando factura</a>';
            echo '<a href="' . tep_href_link('account/account_affiliate.php', 'process=payment&type=points') . '" class="xbutton" style="margin-top: 4px !important; margin-left: 4px!important;font-size: 1.3em;"><i class="fas fa-coins"></i> Retirar fondos en puntos canejables</a>';
            echo '</div>';
        }

        echo '</div>';

    }

    echo '</div>';
    echo '<div class="row dx ccOrder xtabs-item"  itemprop="description">';
    $history = Affiliates::getHistoryFromAffiliate((int) $affiliate['id']);

    if (!empty($history)) {
        echo '<div class="col d12">';
        echo '<div class="ccHead">' . HEADING_HISTORY . '</div>';
        echo '
			<table class="hover">
				<thead>
					<tr>
						<th class="tcenter">' . HEADING_HISTORY_ID . '</th>
						<th class="tcenter">' . HEADING_HISTORY_TOTAL . '</th>
						<th class="tcenter">' . HEADING_HISTORY_STATUS . '</th>
						<th class="tcenter">' . HEADING_HISTORY_DATE . '</th>
						<th class="tcenter"></th>
					</tr>
				</thead>
				';

        foreach ($history as $order) {
			switch ($order['type']) {
				case 'invoice':
					$type = '<span style="background-color: #104add;color: #fff;padding: 2px 8px;font-size: 11px;border-radius: 3px;">Factura</span>';
					break;
				
				default:
					$type = '<span style="background-color: #167417;color: #fff;padding: 2px 8px;font-size: 11px;border-radius: 3px;">Puntos</span>';
					break;
			}

            echo '<tr>
						<td class="tcenter" width="200">#' . str_pad($order['id'], 5, "0", STR_PAD_LEFT) . '</td>
						<td class="tcenter">' . $currencies->format($order['total']) . '</td>
						<td class="tcenter">' . $order['status'] . '</td>
						<td class="tcenter">' . date('d/m/Y H:i:s', strtotime($order['date_created'])) . '</td>
						<td class="tcenter">' . $type . '</td>
					</tr>';
        }
        echo '
					</table>
				</div>
			';

    }

    echo sprintf(
        TEXT_BOTTOM,
        AFFILLIATES_EMAIL,
        $currencies->format(AFFILLIATES_MINIMUM)
    );

    echo '</div>';
    echo '</div>';
    echo '</div>';
}

echo '<div class="tcenter" style="margin-top: 20px;width: 100%;">';
echo '<a href="' . tep_href_link('account.php') . '" class="xbutton" style="margin-top: 4px !important;"><i class="fa fa-arrow-left"></i> ' . IMAGE_BUTTON_BACK . '</a>';
echo '</div>';

echo '</div>';

// Footer
include 'account/includes/footer.php';
