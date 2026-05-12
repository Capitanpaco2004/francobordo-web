<?php

$statuses = rmaGetStatus();
require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

// Método POST
if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
	// Variables
	$sTicket = (isset( $_POST['ticket'] ) ? tep_db_prepare_input( $_POST['ticket'] ) : false);

	// Si tenemos ticket
	if( $sTicket !== false )
	{
		// Si el ticket ha cambiado lo actualizamos
		if( $rmaDetail['ticket'] != $sTicket )
		{
			// Actualizamos
			tep_db_query( 'UPDATE rma SET ticket = "' . $sTicket . '" WHERE id_rma = "' . tep_db_prepare_input( $_GET['id'] ) . '";' );
			$rmaDetail['ticket'] = $sTicket;
		}
	}
}

?>
<?php if ($rmaDetail): ?>
    <?php $aCustomer = getRmaDataAddress('customers', $rmaDetail['id_rma']); ?>
    <?php $aAddress = getRmaDataAddress('delivery', $rmaDetail['id_rma']); ?>
    <?php $aAddressBilling = getRmaDataAddress('billing', $rmaDetail['id_rma']); ?>
    <?php $aAddressReturn = getRmaDataAddress('delivery_return', $rmaDetail['id_rma']); ?>
    <div class="row ax">
        <div class="oeBox oeBoxCustomer column a03 T06 row ax">
            <div class="oeWrpr">
                <div class="oeTitu">
                    <i class="fa fa-user"></i> Cliente
                </div>
                <div class="oeCntd rows sp10 ax xform">
                    <label class="column a03 tright">Pedido:</label>
                    <div class="column a09"><a href="<?php echo tep_href_link('orders.php', 'oID='.$rmaDetail['orders_id'].'&action=edit'); ?>" target="_blank">
                        <?php echo $rmaDetail['orders_id']; ?>
                    </a></div>

                    <label class="column a03 tright">Nombre:</label>
                    <div class="column a09"><?php echo $aCustomer['entry_name']; ?></div>
                    <label class="column a03 tright">Empresa:</label>
                    <div class="column a09"><?php echo $aCustomer['entry_company']; ?></div>
                    <label class="column a03 tright">Dirección:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aCustomer['entry_street_address'], 'n/a'); ?></div>
                    <label class="column a03 tright">Ciudad:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aCustomer['entry_city'], 'n/a'); ?></div>
                    <label class="column a03 tright">CP:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aCustomer['entry_postcode'], 'n/a'); ?></div>
                    <label class="column a03 tright">Provincia:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aCustomer['entry_state'], 'n/a'); ?></div>
                    <label class="column a03 tright">País:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aCustomer['countries_name'], 'n/a'); ?></div>
                    <label class="column a03 tright">E-Mail:</label>
                    <div class="column a09"><a href="mailto:<?php echo $aCustomer['customers_email_address']; ?>"><?php echo $aCustomer['customers_email_address']; ?></a></div>
                    <label class="column a03 tright">Teléfono:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aCustomer['customers_telephone'], 'n/a'); ?></div>
                    <label class="column a03 tright">Ticket:</label>
                    <div class="column a09"><form action="rma.php?action=view&id=<?php echo tep_db_prepare_input( $_GET['id'] ); ?>" method="POST"><input type="text" name="ticket" placeholder="Introduce el ID del ticket de Kayako" value="<?php echo $rmaDetail['ticket']; ?>" />&nbsp;<button class="column a12 xbutton verde" type="submit">Actualizar</button></form></div>
					<?php if( $rmaDetail['ticket'] != '' ): ?>
					<a href="http://soporte.francobordo.com/staff/index.php?/Tickets/Ticket/View/<?php echo $rmaDetail['ticket']; ?>/inbox/-1/-1/-1" class="column a12 xbutton green" target="_blank">Ir al ticket</a>
					<?php endif; ?>
                </div>
            </div>
        </div>

        <div class="oeBox oeBoxCustomer column a03 T06 row ax">
            <div class="oeWrpr">
                <div class="oeTitu">
                    <i class="fa fa-home"></i> Dirección de envio
                </div>
                <div class="oeCntd rows sp10 ax xform">
                    <label class="column a03 tright">Nombre:</label>
                    <div class="column a09"><?php echo $aAddress['entry_name']; ?></div>
                    <label class="column a03 tright">Empresa:</label>
                    <div class="column a09"><?php echo $aAddress['entry_company']; ?></div>
                    <label class="column a03 tright">Dirección:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddress['entry_street_address'], 'n/a'); ?></div>
                    <label class="column a03 tright">Ciudad:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddress['entry_city'], 'n/a'); ?></div>
                    <label class="column a03 tright">CP:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddress['entry_postcode'], 'n/a'); ?></div>
                    <label class="column a03 tright">Provincia:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddress['entry_state'], 'n/a'); ?></div>
                    <label class="column a03 tright">País:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddress['countries_name'], 'n/a'); ?></div>
                    <label class="column a03 tright">Teléfono:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddress['customers_telephone'], 'n/a'); ?></div>
                </div>
            </div>
        </div>

        <div class="oeBox oeBoxCustomer column a03 T06 row ax">
            <div class="oeWrpr">
                <div class="oeTitu">
                    <i class="fa fa-home"></i> Dirección de facturación
                </div>
                <div class="oeCntd rows sp10 ax xform">
                    <label class="column a03 tright">Nombre:</label>
                    <div class="column a09"><?php echo $aAddressBilling['entry_name']; ?></div>
                    <label class="column a03 tright">Empresa:</label>
                    <div class="column a09"><?php echo $aAddressBilling['entry_company']; ?></div>
                    <label class="column a03 tright">NIF/CIF:</label>
                    <div class="column a09"><?php echo $aAddressBilling['entry_nif']; ?></div>
                    <label class="column a03 tright">Dirección:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddressBilling['entry_street_address'], 'n/a'); ?></div>
                    <label class="column a03 tright">Ciudad:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddressBilling['entry_city'], 'n/a'); ?></div>
                    <label class="column a03 tright">CP:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddressBilling['entry_postcode'], 'n/a'); ?></div>
                    <label class="column a03 tright">Provincia:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddressBilling['entry_state'], 'n/a'); ?></div>
                    <label class="column a03 tright">País:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddressBilling['countries_name'], 'n/a'); ?></div>
                    <label class="column a03 tright">Teléfono:</label>
                    <div class="column a09"><?php echo rmaDefaultValue($aAddressBilling['customers_telephone'], 'n/a'); ?></div>
                </div>
            </div>
        </div>



        <div class="oeBox oeBoxCustomer column a03 T06 row ax">
            <div class="oeWrpr">
                <div class="oeTitu">
                    <i class="fa fa-home"></i> Estado actual
                </div>
                <div class="oeCntd rows sp10 ax">
                    <form class="rmaListStatus rows sp10" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-status'); ?>">
                        <select name="id_status" class="column a12 skip" id="id_status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo ($rmaDetail['status_id'] == $status['id'] ? 'selected' : ''); ?>><?php echo $status['text']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="column a12"><input type="checkbox" name="notify" value="1" checked="checked" /> Notificar al cliente</label>
                        <textarea id="message" name="message" placeholder="Enviar un mensaje al cliente" style="max-height: 110px; min-height: 0; height: 150px;"></textarea>

                        <label class="column a12">Mensaje interno</label>
                        <textarea id="private_message" name="private_message" placeholder="Mensaje interno" style="max-height: 110px; min-height: 0; height: 100px;"></textarea>

                        <button class="column a12 xbutton verde" type="submit">Guardar</button>
                        <input type="hidden" name="id" value="<?php echo $rmaDetail['id']; ?>" />
                        <input type="hidden" name="customers_name" value="<?php echo $aCustomer['entry_name']; ?>" />
                        <input type="hidden" name="customers_email" value="<?php echo $aCustomer['customers_email_address']; ?>" />
                        <input type="hidden" name="id_status_previous" value="<?php echo $rmaDetail['status_id']; ?>" />
                        <input type="hidden" name="language_id" value="<?php echo $rmaDetail['languages_id']; ?>" />

                    </form>
                </div>
            </div>
        </div>



        <div class="column a12 T12 row ax">
            <div class="oeBox oeBoxCustomer column a03 T03 row ax">
                <div class="oeWrpr">
                    <div class="oeTitu">
                        <i class="fa fa-user"></i> Detalles del pedido
                    </div>
                    <div class="oeCntd rows sp10 ax xform">
                        <label class="column a03 tright">Pedido:</label>
                        <div class="column a09"><a href="<?php echo tep_href_link('orders.php', 'oID='.$rmaDetail['orders_id'].'&action=edit'); ?>" target="_blank">
                            <?php echo $rmaDetail['orders_id']; ?>
                        </a></div>

                        <label class="column a03 tright">Fecha de pedido:</label>
                        <div class="column a09"><?php echo $rmaDetail['date_purchased']; ?>
	                        <small><?php echo rmaTraduceDias($rmaDetail['date_purchased_raw']); ?></small>
						</div>
						<label class="column a03 tright">Fecha de recepción:</label>
						<?php $dateRecibied = rmaGetDateRecibied($rmaDetail['id']); ?>
						<?php if (!empty($dateRecibied)): ?>
                        <div class="column a09"><?php echo $dateRecibied['date']; ?>
	                        <small><?php echo rmaTraduceDias($dateRecibied['date_raw']); ?></small>
						</div>
						<?php else: ?>
							<div class="column a09"><em>Aún no se ha recibido</em>
							</div>
						<?php endif; ?>

                        <table>
                            <thead>
                                <tr>
                                    <td width="60">Cantidad</td>
                                    <td>Producto</td>
                                    <td>Modelo</td>
                                    <td>Precio</td>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="oeTrProduct">
                                    <td style="text-align: right;">
                                        <?php echo $rmaDetail['quantity']; ?>
                                    </td>
                                    <td>
                                        <?php echo $rmaDetail['products_name']; ?>
                                    </td>
                                    <td>
                                        <?php echo $rmaDetail['products_model']; ?>
                                    </td>
                                    <td>
                                        <?php echo $currencies->display_price($rmaDetail['final_price'], $rmaDetail['products_tax'], $rmaDetail['quantity']); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>

            <div class="oeBox oeBoxCustomer column a03 T03 row ax">
                <div class="oeWrpr">
                    <div class="oeTitu">
                        <i class="fa fa-home"></i> Razón de devolución
                    </div>
                    <div class="oeCntd">
                        <p><strong><?php echo $rmaDetail['option_return']; ?></strong></p>
                        <p><strong>Comentarios del cliente</strong>: <?php echo $rmaDetail['comments']; ?></p>
                    </div>
                </div>
            </div>
            <div class="oeBox oeBoxCustomer column a03 T03 row ax">
                <div class="oeWrpr">
                    <div class="oeTitu">
                        <i class="fa fa-home"></i> Método de reembolso
                    </div>
                    <div class="oeCntd rows sp10 ax xform">
						<?php $aPaymentMethods = rmaGetPaymentMethod(); ?>
						<?php if ($rmaDetail['payment_method'] != ''): ?>
                        	<?php echo $rmaDetail['payment_method']; ?>
						<?php else: ?>
							<em>No se ha seleccionado ninguno</em>
						<?php endif; ?>

                    </div>
					<form class="rows sp12" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-payment-method'); ?>">
						<p><strong>Cambiar método de reembolso</strong></p>
						<p>
							<select name="payment_method" class="column a12 skip" id="payment_method">
								<option value="0">Ninguno</option>
								<?php foreach ($aPaymentMethods as $aPaymentMethod): ?>
									<option value="<?php echo $aPaymentMethod['id']; ?>"><?php echo $aPaymentMethod['text']; ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<button class="column a12 xbutton verde" type="submit">Guardar</button>
						</p>
						<input type="hidden" name="id" value="<?php echo $rmaDetail['id']; ?>" />
					</form>
                </div>
            </div>
            <div class="oeBox oeBoxCustomer column a03 T03 row ax">
                <div class="oeWrpr">
                    <div class="oeTitu">
                        <i class="fa fa-home"></i> Retorno
                    </div>
                    <div class="oeCntd rows sp10 ax xform">
						<?php $aTypesReturns = rmaGetTypesReturn(); ?>
						<?php if ($rmaDetail['type_return'] != ''): ?>
	                        <strong><?php echo $rmaDetail['type_return']; ?></strong>

	                        <?php if (intval($rmaDetail['agencia']) == 1): ?>
	                            <label class="column a03 tright">Nombre:</label>
	                            <div class="column a09"><?php echo $aAddressReturn['entry_name']; ?></div>
	                            <label class="column a03 tright">Empresa:</label>
	                            <div class="column a09"><?php echo $aAddressReturn['entry_company']; ?></div>
	                            <label class="column a03 tright">Dirección:</label>
	                            <div class="column a09"><?php echo rmaDefaultValue($aAddressReturn['entry_street_address'], 'n/a'); ?></div>
	                            <label class="column a03 tright">Ciudad:</label>
	                            <div class="column a09"><?php echo rmaDefaultValue($aAddressReturn['entry_city'], 'n/a'); ?></div>
	                            <label class="column a03 tright">CP:</label>
	                            <div class="column a09"><?php echo rmaDefaultValue($aAddressReturn['entry_postcode'], 'n/a'); ?></div>
	                            <label class="column a03 tright">Provincia:</label>
	                            <div class="column a09"><?php echo rmaDefaultValue($aAddressReturn['entry_state'], 'n/a'); ?></div>
	                            <label class="column a03 tright">País:</label>
	                            <div class="column a09"><?php echo rmaDefaultValue($aAddressReturn['countries_name'], 'n/a'); ?></div>
	                            <label class="column a03 tright">Teléfono:</label>
	                            <div class="column a09"><?php echo rmaDefaultValue($aAddressReturn['customers_telephone'], 'n/a'); ?></div>
	                        <?php endif; ?>
						<?php else: ?>
							<em>No se ha seleccionado ninguno</em>
						<?php endif; ?>
                    </div>
					<div class="oeCntd rows sp10 ax xform">
						<form class="rows sp12" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-return-method'); ?>">
							<p><strong>Cambiar retorno</strong></p>
							<p>
								<select name="type_return" class="column a12 skip" id="type_return">
									<option value="0">Ninguno</option>
									<?php foreach ($aTypesReturns as $aTypesReturn): ?>
										<option value="<?php echo $aTypesReturn['id']; ?>"><?php echo $aTypesReturn['text']; ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<button class="column a12 xbutton verde" type="submit">Guardar</button>
							</p>
							<input type="hidden" name="id" value="<?php echo $rmaDetail['id']; ?>" />
						</form>
					</div>
                </div>
            </div>
        </div>

        <div class="oeBox oeBoxCustomer column a12 T12 row ax">
            <div class="oeWrpr">
                <div class="oeTitu">
                    <i class="fa fa-home"></i> Historial de estados
                </div>
                <div class="oeCntd rows sp10 ax xform">
                    <?php $historyStatus = getRmaHistoryStatus($rmaDetail['id']); ?>
                    <?php if (!empty($historyStatus)) : ?>
                            <ul class="rmaListHistory">
                            <?php foreach($historyStatus as $historyStatus): ?>
                                <li style="margin: 0 0 5px 0;">
                                    <span style="padding: 5px; display: inline-block;"><?php echo $historyStatus['date']; ?></span>
                                    <span style="padding: 5px; color: #fff; display: inline-block; background-color: <?php echo $historyStatus['color']; ?>"><?php echo $historyStatus['status']; ?></span>
                                </li>
                                <?php if ($historyStatus['message'] != ''): ?>
                                    <li style="margin: 0 0 5px 0;">
                                        <span style="padding: 5px; display: inline-block;"><?php echo $historyStatus['date']; ?></span>
                                        <span style="padding: 5px; font-size: 12px; display: inline-block;"><strong>Mensaje</strong>: <?php echo $historyStatus['message']; ?></span>
                                    </li>
                                <?php endif; ?>
                                <?php if ($historyStatus['private_message'] != ''): ?>
                                    <li style="margin: 0 0 5px 0; background-color: #F0F0F0;border: none; padding: 10px 15px;">
                                        <span style="padding: 5px; display: inline-block;"><?php echo $historyStatus['date']; ?></span>
                                        <span style="padding: 5px; font-size: 12px; display: inline-block;"><strong>Mensaje interno</strong>: <?php echo $historyStatus['private_message']; ?></span>
                                    </li>
                                <?php endif; ?>
                                <?php if ($historyStatus['email_text'] != '' && intval($historyStatus['notify']) == 1): ?>
                                    <li style="margin: 0 0 5px 0;">
                                        <span style="padding: 5px; display: inline-block;"><?php echo $historyStatus['date']; ?></span>
                                        <span style="padding: 5px; font-size: 12px; display: inline-block;"><strong>E-mail enviado: </strong>: <br /><?php echo $historyStatus['email_text']; ?></span>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </ul>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!--<div class="oeBox oeBoxCustomer column a12 T12 row ax">
            <div class="oeWrpr">
                <div class="oeTitu">
                    <i class="fa fa-home"></i> Enviar mensaje
                </div>
                <div class="oeCntd rows sp10 ax xform">
                    <textarea name="message" placeholder="Enviar un mensaje al cliente"></textarea>
                </div>
            </div>
        </div>-->
    </div>
    <?php ob_start(); ?>
    <?php $aStatuses = rmaGetStatus(false, $rmaDetail['languages_id']); ?>
    <script>
    $(document).ready(function() {
        var rmaStatus = new Array(<?php count($aStatuses); ?>);
        <?php foreach($aStatuses as $aStatus): ?>
            rmaStatus[<?php echo $aStatus['id']; ?>] = $.br2nl("<?php echo preg_replace( "/\r|\n/", "", nl2br($aStatus['email_text']) ); ?>");
        <?php endforeach; ?>
        $('#id_status').change(function() {
            id_status = $(this).val()
            $('#message').val(rmaStatus[id_status])
        })
    })
    jQuery.br2nl = function(varTest){
        return varTest.replace(/<br \/>/g, "\r");
    };
    </script>
    <?php
    $sJavascript .= ob_get_contents();
    ob_end_clean();
    ?>
<?php endif; ?>
