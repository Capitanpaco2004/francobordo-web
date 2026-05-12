<?php

$free_shipping = false;
$products_ship_free = false;
$free_pass = false;
$ship_free_count = 0;
if ((defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') || (defined('MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE') && MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE == 'true')) {
    switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
        case 'national':
            if ($order->delivery['country_id'] == STORE_COUNTRY) {
                $free_pass = true;
            }
            /*
             * @daniel.lucia
             * #UVT-295-80035
             * Deshabilitamos envios gratis a Ceuta, Melilla e islas
             */

            if (in_array(strtolower($order->delivery['state']), array('melilla', 'ceuta', 'las palmas', 'santa cruz de tenerife'))) {
                $free_pass = false;
            }
            break;
        case 'international':if ($order->delivery['country_id'] != STORE_COUNTRY) {
                $free_pass = true;
            }

            break;
        case 'both':$free_pass = true;
            break;
    }
    if ($free_pass == true) {
        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true' && $order->info['total'] >= ($customer_group_id == 0 ? MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI)) {
            $free_shipping = true;
        } elseif (defined('MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE') && (MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE == 'true')) {
            $products = $cart->get_products();

            for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
                if ($products[$i]['ship_free'] == '1') {
                    $ship_free_count += $products[$i]['quantity'];
                    $total_weight -= $products[$i]['weight'] * $products[$i]['quantity'];
                    $total_count -= $products[$i]['quantity'];
                }
            }
            if ($customer_group_id == 0 && $total_weight == 0 && $total_count == 0) {
                $products_ship_free = true;
                $free_shipping = true;
            }
        }
        if ($free_shipping == true || $products_ship_free == true || $ship_free_count > 0) {
            include DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php';
        }

    }
}

// Añadido envio gratis, pedidos que coincidan con los valores del modullo freeamount_freeamount
if ($order->info['subtotal'] >= ($customer_group_id == 0 ? MODULE_SHIPPING_FREEAMOUNT_AMOUNT : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) && $cart->show_weight() < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone()) {
    $products_ship_free = true;
    $free_shipping = true;
    include DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php';
}
// FIN Añadido envio gratis, pedidos que coincidan con los valores del modullo freeamount_freeamount

if ($products_ship_free == false) {

    $bInFree = false;
    $nTotalWeight = 0;
    $check_free_shipping_basket_query = tep_db_query("select products_id from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int) $customer_id . "'");
    while ($check_free_shipping_basket = tep_db_fetch_array($check_free_shipping_basket_query)) {
        $check_free_shipping_query = tep_db_query("select products_ship_free, products_price, products_weight from " . TABLE_PRODUCTS . " where products_id = '" . (int) $check_free_shipping_basket['products_id'] . "'");
        $check_free_shipping = tep_db_fetch_array($check_free_shipping_query);

        $nTotalWeight += $check_free_shipping['products_weight'];

        if ($customer_group_id == 0) {
            if ((array_key_exists('products_ship_free', $check_free_shipping) && $check_free_shipping['products_ship_free'] && getProductFreeShippingByGeoZone()) || ($check_free_shipping['products_price'] >= (MODULE_SHIPPING_FREEAMOUNT_AMOUNT / 1.21) && $check_free_shipping['products_weight'] < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone())) {
                $bInFree = true;
            }
        } else {
            if ($check_free_shipping['products_price'] >= (MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) && $check_free_shipping['products_weight'] < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone()) {
                $bInFree = true;
            }

        }
    }

    if ($bInFree) {
        $products_ship_free = true;
        $free_shipping = true;
        include DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php';
    }
}


if (tep_count_shipping_modules() > 0) {

    ?>
            <div class="pghd"><?php echo CHECKOUT_FORMA_ENVIO ?></div>
<?php
if (sizeof($quotes) > 1 && sizeof($quotes[0]) > 1) {
    ?>
    <!-- PRODUCTS SHIP FREE START -->
    <?php
    if ($ship_free_count > 0 && $products_ship_free != true) {
        //echo sprintf(PRODUCTS_SHIP_FREE_COUNT, $ship_free_count);
        //echo $cart;
    } else {
    ?>
    <!-- PRODUCTS SHIP FREE END -->
    <?php echo '<p class="informacion">' . TEXT_CHOOSE_SHIPPING_METHOD . '</p>'; ?>
    <!-- PRODUCTS SHIP FREE START -->
<?php } ?>
<!-- PRODUCTS SHIP FREE END -->

<?php
} elseif ($free_shipping == false) {
?>
    <div class="contentText">
        <!-- PRODUCTS SHIP FREE START -->
        <?php if ($ship_free_count > 0 && $products_ship_free != true) {
            echo sprintf(PRODUCTS_SHIP_FREE_COUNT_ONLY, $ship_free_count);
        } else {?>
            <!-- PRODUCTS SHIP FREE END -->
            <?php echo TEXT_ENTER_SHIPPING_INFORMATION; ?>
            <!-- PRODUCTS SHIP FREE START -->
        <?php }?>
     <!-- PRODUCTS SHIP FREE END -->
    </div>
<?php
}
    ?>

  <div class="contentText<?php if ($free_shipping != true): ?> noEnvioGratis<?php endif;?>">
	  <?php if ($free_shipping != true): ?>
		  <div class="shippingEnvioGratis">
            <strong>Recuerda:</strong> <?php echo MODULE_SHIPPING_FREEAMOUNT_TEXT_ERROR; ?>
        </div>
	  <?php endif;?>
    <?php

    if ($GLOBALS['freeamount']->enabled || $free_shipping) {
        $aFreeAmount = $GLOBALS['freeamount'];
        $quotes[] = array('id' => $aFreeAmount->code, 'module' => $aFreeAmount->title, 'methods' => array(array('id' => $aFreeAmount->code, 'title' => $aFreeAmount->description, 'cost' => 0)), 'tax' => 21, 'icon' => $aFreeAmount->quotes['icon']);
    }

    $radio_buttons = 0;

    /**
     * @author Daniel Lucia <daniel.lucia@denox.es>
     * A veces, por algún motivo no llegan transportistas. COn esto muestro al menos un error.
     */
    if ($error_tarifa != false):
    ?>
    <div class="mensaje"><?php echo $error_tarifa; ?></div>
    <?php
    endif;

    for ($i = 0, $n = sizeof($quotes); $i < $n; $i++) {
        if (!$ie) { //
            echo '<div class="envios envios-' . (!isset($quotes[$i]['id']) ? 0 : ($i + 1)) . '"' . (!isset($quotes[$i]['id']) ? ' style="display: none !important;"' : '') . '>';
        } else {
            echo '<div class="envios_ie envios-' . (!isset($quotes[$i]['id']) ? 0 : ($i + 1)) . '"' . (!isset($quotes[$i]['id']) ? ' style="display: none !important;"' : '') . '>';
        }
        ?>
			<p>
				<b><?php echo $quotes[$i]['module']; ?></b>&nbsp;<?php if (isset($quotes[$i]['icon']) && tep_not_null($quotes[$i]['icon'])) {echo $quotes[$i]['icon'];}?>
			</p>
			<?php if (isset($quotes[$i]['error'])) {?>
			<div class="mensaje"><?php echo $quotes[$i]['error']; ?></div>

			<?php
        } else {
            $bKiala = false;
            for ($j = 0, $n2 = sizeof($quotes[$i]['methods']); $j < $n2; $j++) {
                // set the radio button to be checked if it is the method chosen
                $checked = (($quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'] == $shipping['id']) ? true : false);

                if (($checked == true) || ($n == 1 && $n2 == 1)) {
                    echo '                  <div id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect2(this, ' . $radio_buttons . ')">' . "\n";
                } else {
                    echo '                  <div class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect2(this, ' . $radio_buttons . ')">' . "\n";
                }
                ?>
        <?php

                // Obtenemos el tiempo de envío //

                // Globales
                global $cart;

                // Variables
                $aHours = false;
                $nHours1 = 0;
                $nHours2 = 24;
                $sEstimate = '';
                $nAdd1 = 0;
                $nAdd2 = 0;

                // Obtenemos los productos
                $products = $cart->get_products();

                // Del nombre del módulo
                if (preg_match('/(hora|hour)/i', $quotes[$i]['module'])) {
                    $sExtract = $quotes[$i]['module'];
                }

                // O del título
                else if (preg_match('/(hora|hour)/i', $quotes[$i]['methods'][$j]['title'])) {
                    $sExtract = $quotes[$i]['methods'][$j]['title'];
                }

                // Casos especiales
                else if ($quotes[$i]['id'] == 'seurnacional') {
                    $sExtract = '24 horas';
                }

                // Si no, no tenemos
                else {
                        $sExtract = false;
                    }

                    // Si tenemos horas, las extraemos
                    if ($sExtract !== false) {
                        // Extraemos las horas
                        preg_match('/(?<hour>[0-9]+(\-)?([0-9]+)?)/', $sExtract, $aMatches);

                        // Si tenemos horas
                        if (isset($aMatches['hour'])) {
                            // Si tenemos rango horario
                            if (preg_match('/(\-)/i', $aMatches['hour'])) {
                                // Dividimos y guardamos
                                $aHours = explode('-', $aMatches['hour']);
                                $nHours1 = $aHours[0];
                                $nHours2 = $aHours[1];
                            }
                            // Si no, obtenemos las horas
                        else {
                                // Obtenemos y sumamos 24 horas
                                $nHours1 = $aMatches['hour'];
                                $nHours2 = $aMatches['hour'] + 24;
                            }

                            // Quitamos 24 horas
                            $nHours1 -= 24;
                            $nHours2 -= 24;
                        }

                        // Recorremos los productos del carrito
                        for ($nCont = 0, $nQty = sizeof($products); $nCont < $nQty; $nCont++) {
                            $aProduct = $products[$nCont];

                            // Si no tenemos el valor de products_quantity
                            if (!isset($aProduct['products_quantity'])) {
                                // Obtenemos el ID del producto
                                $nID = (isset($aProduct['products_id']) ? $aProduct['products_id'] : $aProduct['id']);
                                $nID = (preg_match('/(\{)/i', $nID) ? preg_replace('/(\{)(.*)/i', '', $nID) : $nID);

                                // Obtenemos la cantidad del producto
                                $aAux = tep_db_query('SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $nID . '";');
                                $aAux = tep_db_fetch_array($aAux);
                                $aProduct['products_quantity'] = $aAux['products_quantity'];
                            }

                            // Entre 2 y 6 días
                            if ($aProduct['products_quantity'] <= -100 && $aProduct['products_quantity'] >= -150) {
                                if ($nAdd1 <= (24 * 2)) {
                                    $nAdd1 = (24 * 2);
                                }

                                if ($nAdd2 <= (24 * 6)) {
                                    $nAdd2 = (24 * 6);
                                }

                            }
                            // Entre 8 y 13 días
                        else if ($aProduct['products_quantity'] <= 0 && $aProduct['products_quantity'] >= -799) {
                            if ($nAdd1 <= (24 * 8)) {
                                $nAdd1 = (24 * 8);
                            }

                            if ($nAdd2 <= (24 * 13)) {
                                $nAdd2 = (24 * 13);
                            }

                        }
                        // Bajo pedido
                        else if ($aProduct['products_quantity'] <= -800 && $aProduct['products_quantity'] >= -899) {
                            $nAdd1 = false;
                            $nAdd2 = false;
                            break;
                        }
                        // Agotado
                        else if ($aProduct['products_quantity'] <= -900 && $aProduct['products_quantity'] >= -901) {
                            $nAdd1 = false;
                            $nAdd2 = false;
                            break;
                        }
                        // Agotado
                        //else if( $aProduct['products_quantity'] == 0 || $aProductoInfo['products_quantity'] == 1 )
                        //{
                        //$nAdd1 = false;
                        //$nAdd2 = false;
                        //break;
                        //}
                    }

                    // Si tenemos predicción
                    if ($nAdd1 !== false) {
                        // Obtenemos las dos estimaciones
                        $aEstimate1 = getShippingEstimate(true, false, $nAdd1 + $nHours1, $quotes[$i]['id']);
                        $aEstimate2 = getShippingEstimate(true, false, $nAdd2 + $nHours2, $quotes[$i]['id']);

                        // Si las fechas son iguales, sumamos un día
                        if ($aEstimate1['date'] == $aEstimate2['date']) {
                            $aEstimate2 = addHoursToDate($aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24);
                        }

                        // Si es SEUR 13:30
                        if ($quotes[$i]['id'] == 'seurnacional') {
                            // Hoy
                            if ($aEstimate1['date'] == date('d-m-Y', strtotime(date('Y-m-d') . ' + 1 day'))) {
                                $sEstimate = str_replace('%s1', dateToSpanish(date('l j \d\e F', strtotime($aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day']))), SHIPPING_PREDICTION_BUY_NOW_TOMORROW) . '.';
                            }

                            // Mañana
                            else {
                                    $sEstimate = str_replace('%s1', dateToSpanish(date('l j \d\e F', strtotime($aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day']))), SHIPPING_PREDICTION_BUY_NOW_PAST_TOMORROW) . '.';
                                }

                            }
                            // Si son los demás métodos
                        else {
                                $sEstimate = str_replace(array('%s1', '%s2'), array(dateToSpanish(date('l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime($aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day']))), dateToSpanish(date('l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime($aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'])))), SHIPPING_PREDICTION_BUY_NOW) . '.';
                            }

                        }
                        // Si no podemos hacer predicción
                    else {
                            $sEstimate = SHIPPING_PREDICTION_NONE . '.';
                        }

                    }

                    ?>
											<p><?php echo ($sEstimate != '' ? $sEstimate : $quotes[$i]['methods'][$j]['title']); ?> <strong>
						<?php if (($n > 1) || ($n2 > 1)) {?>
											<?php echo $currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))); ?> <?php echo tep_draw_radio_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'], $checked); ?></strong></p>
						<?php } else {?>
											<?php echo $currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], $quotes[$i]['tax'])) . tep_draw_hidden_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id']); ?></strong></p>
						<?php }?>
						<?php
                    $radio_buttons++;

                    // Inicio, select con las tiendas
                    if ($quotes[$i]['id'] == 'retira') {
                        echo '<div class="selct">';
                        echo '<label>' . TEXT_SELECT_STORE . '</label>';

                        // Consultamos las tiendas
                        $aDatos = tep_db_query('select id_store, store_name, store_address from store where store_status = 1 order by store_name asc');
                        $aAux = array();

                        while ($aDato = tep_db_fetch_array($aDatos)) {
                            $aAux[] = array('id' => $aDato['id_store'], 'text' => $aDato['store_name'] . ' (' . $aDato['store_address'] . ')');
                        }

                        echo tep_draw_pull_down_menu('store_id', $aAux, $store_id);

                        echo '</div>';
                    }
                    // Fin, select con las tiendas

                    echo '</div>';

                    if ($quotes[$i]['methods'][$j]['id'] == 'kialapoint') {
                        $bKiala = true;
                    }

                }
            }
            ?>
						<?php
            echo '</div>';

            if ($bKiala == true) {
                echo '<div id="dxkialap"></div>';
            }

        }

        /*$radio_buttons = 0;

        for ($i=0, $n=sizeof($quotes); $i<$n; $i++)
        {
        ?>

        <tr>

        <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        <td colspan="2"><table border="0" width="100%" cellspacing="0" cellpadding="2">

        <tr>

        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        <td class="main" colspan="3"><b><?php echo $quotes[$i]['module']; ?></b>&nbsp;<?php if (isset($quotes[$i]['icon']) && tep_not_null($quotes[$i]['icon'])) { echo $quotes[$i]['icon']; } ?></td>

        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        </tr>

        <?php

        if (isset($quotes[$i]['error'])) {

        ?>

        <tr>

        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        <td class="main" colspan="3"><?php echo $quotes[$i]['error']; ?></td>

        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        </tr>

        <?php

        } else {

        for ($j=0, $n2=sizeof($quotes[$i]['methods']); $j<$n2; $j++) {

        // set the radio button to be checked if it is the method chosen

        $checked = (($quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'] == $shipping['id']) ? true : false);

        if ( ($checked == true) || ($n == 1 && $n2 == 1) ) {
        $b="'".$quotes[$i]['methods'][$j]['title']."'";
        $c="'".tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))."'";
        echo '                  <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect2(this, ' . $radio_buttons . ');zhipper='.$b.';zprice='.$c.';">' . "\n";
        } else {
        $b="'".$quotes[$i]['methods'][$j]['title']."'";
        $c="'".tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))."'";
        $zz=' zhipper='.$b.';zprice='.$c.';" ';
        echo '                  <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect2(this, ' . $radio_buttons . ');zhipper='.$b.';zprice='.$c.';">' . "\n";
        }
        ?>

        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        <td class="main" width="75%"><?php echo $quotes[$i]['methods'][$j]['title']; ?></td>

        <?php

        if ( ($n > 1) || ($n2 > 1) ) {

        ?>

        <td class="main"><?php echo $currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))); ?></td>

        <td class="main" align="right"><?php

        $b="'".$quotes[$i]['methods'][$j]['title']."'";

        $c="'".tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))."'";

        $zz=' onchange="zhipper='.$b.';zprice='.$c.';" ';

        echo tep_draw_radio_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'], $checked,$zz); ?></td>

        <?php

        //'onclick="zhipper=value" '

        } else {

        ?>

        <td class="main" align="right" colspan="2"><?php echo $currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], $quotes[$i]['tax'])) . tep_draw_hidden_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id']); ?></td>
        <?php
        }
        ?>
        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
        </tr>
        <?php
        $radio_buttons++;
        }
        }
        ?>

        </table></td>

        <td><?php //echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>

        </tr>

        <?php

        }*/

/*
?>

<noscript><tr align="right"  class="infoBoxContents" > <td class="main" align="right"></td><td></td><td align="right"><INPUT TYPE="submit" class="button" name="save" value="update total"><?php// echo tep_image_submit('button_update_total.gif', IMAGE_BUTTON_CONTINUE,'name="save" value="update total"onmouseover="loadXMLDoc(this.value);"');  ?></td></tr></noscript>
<tr> <td class="main" align="right"></td><td></td><td align="right"><script type="text/javascript">
<!--//
document.write('<input type="button" class="az_button_submit2" value="Calcular Total a Pagar" onclick="ajaxLoader(\'checkout_2confirmation.php?tip=\'+ zprice+\'&zship=\'+zhipper+\'&osCsid=\'+Csid,\'contentLYR\')" name="CLEARBUTTON">');
//-->
</script>
</td></tr>
<?php */ ?>

			<?php

    }

    ?>
