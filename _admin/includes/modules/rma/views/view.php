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
		$sTicket = strtoupper( trim( (string)$sTicket ) );
		// Si el ticket ha cambiado lo actualizamos
		if( $rmaDetail['ticket'] != $sTicket )
		{
			// Actualizamos (valores escapados: tep_db_prepare_input no escapa para SQL)
			tep_db_query( 'UPDATE rma SET ticket = "' . tep_db_input( $sTicket ) . '" WHERE id_rma = ' . (int)$_GET['id'] );
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
                    <div class="column a09">
                        <form id="rmaTicketForm" action="rma.php?action=view&id=<?php echo (int)$_GET['id']; ?>" method="POST"><input type="text" id="rmaTicketInput" name="ticket" value="<?php echo htmlspecialchars( (string)$rmaDetail['ticket'], ENT_QUOTES, 'UTF-8' ); ?>" title="Nº de ticket de Kayako (formato: ABC-123-12345)" />&nbsp;<button class="column a12 xbutton verde" type="submit">Actualizar</button>&nbsp;<button class="column a12 xbutton" type="button" onclick="rmaKayakoSearch(<?php echo (int)$rmaDetail['id_rma']; ?>, this);">Buscar en Kayako</button></form>
                        <div id="rmaKayakoResults"></div>
                        <form action="rma.php?action=kayako-create" method="POST" style="margin-top:6px;padding-top:6px;border-top:1px solid #eee;" onsubmit="return confirm('Se creará el ticket en Kayako, quedará asignado al RMA (y al pedido) y te llevará a Kayako para escribir el email al cliente.\n(No se envía nada hasta que tú respondas el ticket.)');">
                            <input type="hidden" name="id" value="<?php echo (int)$rmaDetail['id_rma']; ?>">
                            <input type="text" name="ticket_subject" value="RMA <?php echo (int)$rmaDetail['id_rma']; ?> - Pedido <?php echo (int)$rmaDetail['orders_id']; ?>" maxlength="255" style="width:200px;" title="Asunto del ticket">
                            <select name="ticket_department" title="Departamento">
                                <?php foreach (fb_kayako_departments() as $iDeptId => $sDeptName): ?>
                                    <option value="<?php echo (int)$iDeptId; ?>"<?php echo ($iDeptId == 4 ? ' selected' : ''); ?>><?php echo htmlspecialchars($sDeptName, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="column a12 xbutton" type="submit">Crear ticket</button>
                        </form>
                        <?php if( isset( $_GET['kayako_error'] ) ): ?>
                            <div style="color:#c0392b;margin-top:4px;">No se pudo crear el ticket en Kayako (¿línea de la oficina caída?). Añádelo a mano.</div>
                        <?php endif; ?>
                    </div>
					<?php if( $rmaDetail['ticket'] != '' ): ?>
					<a href="<?php echo fb_kayako_staff_url( $rmaDetail['ticket'] ); ?>" class="column a12 xbutton green" target="_blank">Ir al ticket</a>
					<?php endif; ?>
					<script>
					function rmaKayakoEsc(s) {
						return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
							return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
						});
					}
					function rmaKayakoSearch(idRma, btn) {
						var box = document.getElementById('rmaKayakoResults');
						btn.disabled = true;
						box.innerHTML = 'Buscando tickets del cliente en Kayako…';
						fetch('rma.php?action=kayako-search', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'id=' + idRma
						})
							.then(function(r) { return r.json(); })
							.then(function(d) {
								btn.disabled = false;
								if (!d.ok) { box.innerHTML = '<span style="color:#c0392b;">' + rmaKayakoEsc(d.error || 'Error consultando Kayako.') + '</span>'; return; }
								if (!d.tickets.length) { box.innerHTML = 'El cliente no tiene tickets en Kayako (buscado por el email del pedido).'; return; }
								var h = '';
								for (var i = 0; i < d.tickets.length; i++) {
									var t = d.tickets[i];
									h += '<div style="border-top:1px solid #eee;padding:6px 0;">';
									h += '<a href="https://soporte.francobordo.com/staff/index.php?/Tickets/Ticket/View/' + encodeURIComponent(t.mask) + '/inbox/-1/-1/-1" target="_blank" style="font-weight:bold;">' + rmaKayakoEsc(t.mask) + '</a> ';
									h += '<small style="color:#888;">' + rmaKayakoEsc(t.lastactivity) + ' · ' + rmaKayakoEsc(t.status) + ' · ' + rmaKayakoEsc(t.department) + '</small><br>';
									if (t.subject) { h += '<small style="color:#555;">' + rmaKayakoEsc(t.subject) + '</small><br>'; }
									if (t.linked) {
										h += '<small style="color:#27ae60;">Ya asignado a este RMA</small>';
									} else {
										h += '<button type="button" style="margin-top:3px;" onclick="rmaKayakoAssign(this)" data-mask="' + rmaKayakoEsc(t.mask) + '">Asignar a este RMA</button>';
									}
									h += '</div>';
								}
								box.innerHTML = h;
							})
							.catch(function() {
								btn.disabled = false;
								box.innerHTML = '<span style="color:#c0392b;">Error de red consultando Kayako.</span>';
							});
					}
					function rmaKayakoAssign(btn) {
						var input = document.getElementById('rmaTicketInput');
						var mask = btn.getAttribute('data-mask');
						if (input.value !== '' && input.value !== mask && !confirm('El RMA ya tiene el ticket ' + input.value + '. ¿Sustituirlo por ' + mask + '?')) {
							return;
						}
						input.value = mask;
						document.getElementById('rmaTicketForm').submit();
					}
					</script>
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
                    <form class="rmaListStatus rows sp10 column a12" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-status'); ?>">
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

                        <?php
                        // Adjuntos del RMA: separamos los del cliente (source='client') de
                        // los añadidos por un operador desde el admin (source='staff').
                        $idRmaInt = (int) $rmaDetail['id_rma'];
                        $attQ = tep_db_query("SELECT id, filename_original, filename_stored, mime_type, size_bytes, date_added, source FROM rma_attachments WHERE id_rma = " . $idRmaInt . " ORDER BY id ASC");
                        $aClientAtt = array();
                        $aStaffAtt  = array();
                        while ($a = tep_db_fetch_array($attQ)) {
                            if (($a['source'] ?? 'client') === 'staff') $aStaffAtt[] = $a; else $aClientAtt[] = $a;
                        }

                        // Pinta una miniatura/enlace de un adjunto. $allowDelete añade el botón ✕ (solo staff).
                        $renderAtt = function($a, $idRmaInt, $allowDelete) {
                            $url   = '/images/rma/' . $idRmaInt . '/' . rawurlencode($a['filename_stored']);
                            $isImg = (strpos($a['mime_type'], 'image/') === 0);
                            $kb    = round($a['size_bytes'] / 1024);
                            ?>
                            <div style="position:relative;width:90px">
                                <?php if ($allowDelete): ?>
                                <form method="post" action="<?php echo tep_href_link('rma.php', 'action=remove-attachment'); ?>"
                                      onsubmit="return confirm('¿Eliminar este adjunto del operador?');"
                                      style="position:absolute;top:2px;right:2px;z-index:2;margin:0">
                                    <input type="hidden" name="id" value="<?php echo $idRmaInt; ?>" />
                                    <input type="hidden" name="att_id" value="<?php echo (int) $a['id']; ?>" />
                                    <button type="submit" title="Eliminar adjunto"
                                            style="border:none;background:rgba(200,0,0,.85);color:#fff;width:18px;height:18px;line-height:16px;border-radius:9px;cursor:pointer;font-size:12px;padding:0">&times;</button>
                                </form>
                                <?php endif; ?>
                                <a href="<?php echo $url; ?>" target="_blank" title="<?php echo htmlspecialchars($a['filename_original']); ?> (<?php echo $kb; ?> KB)"
                                   style="display:block;width:90px;text-align:center;text-decoration:none;color:#333;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;box-sizing:border-box">
                                    <?php if ($isImg): ?>
                                        <img src="<?php echo $url; ?>" alt="" style="max-width:80px;max-height:80px;object-fit:cover;border-radius:3px" />
                                    <?php else: ?>
                                        <div style="width:80px;height:80px;display:flex;align-items:center;justify-content:center;background:#f4f4f4;border-radius:3px;font-size:11px;color:#666">📄 PDF</div>
                                    <?php endif; ?>
                                    <div style="font-size:10px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($a['filename_original']); ?></div>
                                    <div style="font-size:9px;color:#888"><?php echo $kb; ?> KB</div>
                                </a>
                            </div>
                            <?php
                        };
                        ?>

                        <?php if (count($aClientAtt) > 0): ?>
                            <p style="margin-top:10px"><strong>Adjuntos del cliente</strong> (<?php echo count($aClientAtt); ?>):</p>
                            <div style="display:flex;flex-wrap:wrap;gap:8px">
                            <?php foreach ($aClientAtt as $a) $renderAtt($a, $idRmaInt, false); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (count($aStaffAtt) > 0): ?>
                            <p style="margin-top:10px"><strong>Adjuntos del operador</strong> (<?php echo count($aStaffAtt); ?>):</p>
                            <div style="display:flex;flex-wrap:wrap;gap:8px">
                            <?php foreach ($aStaffAtt as $a) $renderAtt($a, $idRmaInt, true); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo tep_href_link('rma.php', 'action=add-attachments'); ?>" enctype="multipart/form-data"
                              style="margin-top:12px;padding:8px;background:#f4f4f4;border:1px solid #ddd;border-radius:4px">
                            <input type="hidden" name="id" value="<?php echo $idRmaInt; ?>" />
                            <label style="display:block;font-size:12px;margin-bottom:4px"><strong>Añadir imágenes / documentos</strong> (operador)</label>
                            <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.heic,.heif,.pdf,image/*,application/pdf" />
                            <div style="font-size:10px;color:#888;margin:4px 0 6px">Formatos: JPG, PNG, GIF, WEBP, HEIC, PDF (sin límite de tamaño ni de número)</div>
                            <button class="xbutton verde" type="submit">Subir adjuntos</button>
                        </form>
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

						<?php
						// Preview de puntos a acreditar si el método es de puntos
						$calcPreview = rmaCalcRefundPoints($rmaDetail['id_rma']);
						$isPointsMethod = $calcPreview && in_array($calcPreview['payment_method'], [RMA_PAYMENT_METHOD_POINTS_LEGACY, RMA_PAYMENT_METHOD_POINTS_BONUS], true);
						?>
						<?php if ($isPointsMethod): ?>
							<div style="margin-top:10px;padding:10px;background:#eaf6fb;border:1px solid #1fa1d0;border-radius:4px;font-size:12px;color:#155a78">
								<?php if (!empty($calcPreview['already_credited_at'])): ?>
									<strong>✓ Puntos ya acreditados</strong> el <?php echo htmlspecialchars($calcPreview['already_credited_at']); ?>.
								<?php else: ?>
									<strong>Al pasar a "Devolución por puntos" se acreditarán:</strong>
									<div style="margin-top:5px;line-height:1.5">
										Importe bruto: <strong><?php echo number_format($calcPreview['importe_bruto'], 2, ',', '.'); ?>€</strong> (PVP IVA inc × <?php echo $calcPreview['quantity']; ?>)<br>
										<?php if ($calcPreview['pickup_cost'] > 0): ?>
											− recogida: −<?php echo number_format($calcPreview['pickup_cost'], 2, ',', '.'); ?>€<br>
											Importe neto: <strong><?php echo number_format($calcPreview['importe_neto'], 2, ',', '.'); ?>€</strong><br>
										<?php endif; ?>
										<?php if ($calcPreview['bonus_eur'] > 0): ?>
											+ bonus 10%: +<?php echo number_format($calcPreview['bonus_eur'], 2, ',', '.'); ?>€
											<?php if ($calcPreview['bonus_capped']): ?>
												<span style="color:#a02020">(capado a 50€)</span>
											<?php endif; ?>
											<br>
										<?php endif; ?>
										Total a reembolsar: <strong><?php echo number_format($calcPreview['total_eur'], 2, ',', '.'); ?>€</strong><br>
										<span style="font-size:14px;color:#1fa1d0">→ <strong><?php echo number_format($calcPreview['puntos'], 0, ',', '.'); ?> puntos</strong></span>
										<span style="color:#888">(1 pt = <?php echo number_format($calcPreview['point_value'], 2, ',', '.'); ?>€)</span>
									</div>
									<?php if ($calcPreview['warn_no_lines']): ?>
										<div style="margin-top:8px;padding:6px;background:#f5d6d6;border:1px solid #a02020;color:#701818;border-radius:3px">
											⚠ No se encontró el producto en el pedido — importe = 0. Revisa el RMA antes de aprobar.
										</div>
									<?php elseif ($calcPreview['warn_neto_zero']): ?>
										<div style="margin-top:8px;padding:6px;background:#f0f0d6;border:1px solid #b89500;color:#7a5f00;border-radius:3px">
											⚠ El coste de recogida (<?php echo number_format($calcPreview['pickup_cost'], 2, ',', '.'); ?>€) es ≥ al importe del producto (<?php echo number_format($calcPreview['importe_bruto'], 2, ',', '.'); ?>€). No se acreditarán puntos.
										</div>
									<?php endif; ?>
									<?php if ($calcPreview['warn_multilines']): ?>
										<div style="margin-top:8px;padding:6px;background:#f0f0d6;border:1px solid #b89500;color:#7a5f00;border-radius:3px">
											ℹ El producto aparece en <strong><?php echo $calcPreview['order_lines_count']; ?> líneas</strong> del pedido (atributos distintos). Se usa el precio <strong>promedio</strong>. Revisa si los precios difieren mucho.
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
                    </div>
					<form class="rows sp12 column a12" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-payment-method'); ?>">
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

							<!-- Coste de recogida (€) que se descontará del reembolso -->
							<form class="rows sp10 column a12" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-pickup-cost'); ?>" style="margin-top:8px;padding:8px;background:#f4f4f4;border:1px solid #ddd;border-radius:4px">
								<label class="column a12" style="font-weight:bold;font-size:12px;color:#555;margin-bottom:4px;display:block">Coste de recogida (€)</label>
								<div style="display:flex;gap:6px;align-items:center">
									<input type="number" name="pickup_cost" step="0.01" min="0" value="<?php echo $rmaDetail['pickup_cost'] !== null ? number_format((float)$rmaDetail['pickup_cost'], 2, '.', '') : ''; ?>" placeholder="0.00" style="flex:1;padding:5px;font-size:12px;border:1px solid #aaa;border-radius:3px" />
									<button type="submit" class="xbutton verde" style="padding:5px 10px;font-size:11px">Guardar</button>
								</div>
								<small style="color:#888;display:block;margin-top:3px">Vacío = sin descuento. Se resta del importe a reembolsar.</small>
								<input type="hidden" name="id" value="<?php echo $rmaDetail['id']; ?>" />
							</form>

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

						<!-- Correos Express: recogida / envío de devolución (siempre visible) -->
						<?php if (function_exists('rmaCexRenderBox')) rmaCexRenderBox($rmaDetail); ?>

						<!-- SEUR: envío/etiqueta de devolución (siempre visible) -->
						<?php if (function_exists('rmaSeurRenderBox')) rmaSeurRenderBox($rmaDetail); ?>

						<!-- Ontime: recogida de devolución (siempre visible) -->
						<?php if (function_exists('rmaOntimeRenderBox')) rmaOntimeRenderBox($rmaDetail); ?>
						<!-- Correos: etiqueta de devolución para depósito en oficina (siempre visible) -->
						<?php if (function_exists('rmaCorreosRenderBox')) rmaCorreosRenderBox($rmaDetail); ?>
                    </div>
					<div class="oeCntd rows sp10 ax xform">
						<form class="rows sp12 column a12" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-return-method'); ?>">
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
