<div class="rmaContent Transition Transition">
    <h1><?php echo HEADING_TITLE; ?></h1>
    <?php if ($nStep == 0 ): ?>
        <form action="<?php echo tep_href_link(FILENAME_RMA, 'orders_id=' . $rma->ordersID . '&products_id=' . $rma->productsID); ?>" method="POST" class="rmaPage">
            <input type="hidden" name="step" value="1"/>
            <ul class="rmaList">
                <?php foreach($rma->optionsReturn as $aOptionsReturn): ?>
                    <li<?php if ((int)$aOptionsReturn['active'] == 0): ?> class="Disabled"<?php endif; ?>>
                        <label>
                            <input required type="radio" name="option_return" value="<?php echo $aOptionsReturn['id']; ?>" <?php if ((int)$aOptionsReturn['active'] == 0): ?> disabled<?php endif; ?>>
                            <?php echo $aOptionsReturn['text']; ?>
                        </label>
                        <?php if ((int)$aOptionsReturn['active'] == 0): ?>
                            <small><?php echo $rma->calculateExpiration( $rma->Antiguedad ); ?></small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <?php echo RMA_CONTACTO; ?>
            </p>
            <p class="rmaButtons">
                <button type="submit" name="rmaNext" class="Button buttonBig buttonFirst"><?php echo RMA_NEXT; ?></button>
            </p>
        </form>
    <?php endif; ?>

    <?php if ($nStep == 1 ): ?>
        <form action="<?php echo tep_href_link(FILENAME_RMA, 'orders_id=' . $rma->ordersID . '&products_id=' . $rma->productsID); ?>" method="POST" enctype="multipart/form-data" class="rmaPage">
            <input type="hidden" name="step" value="2"/>
            <?php $rma->getFieldsForm($aFields); ?>
            <div class="rmaComments">
                <p class="rmaImage"><?php echo $rma->getImageProduct(); ?></p>
                <div>
                    <p class="rmaTitleText"><strong><?php echo $rma->Product['products_name']; ?></strong></p>
                    <p class="rmaProductsQuantity">
                        <label>
                            <input type="number" name="quantity" max="<?php echo $rma->Product['products_quantity']; ?>" value="<?php echo $rma->Product['products_quantity']; ?>" class="rmaTextBox" /> <?php echo RMA_UNITS; ?>
                        </label>
                    </p>
                    <p><?php echo RMA_COMMENTS; ?></p>
                    <p><textarea name="comments" required></textarea></p>

                    <!-- Adjuntos del cliente (fotos / PDF) — opcional, hasta 5 archivos de 5 MB -->
                    <p class="rmaTitleText" style="margin-top:12px"><strong><?php echo RMA_ATTACH_TITLE; ?></strong></p>
                    <p style="font-size:0.85em;color:#666;margin:2px 0 6px"><?php echo RMA_ATTACH_HELP; ?></p>
                    <p>
                        <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif,application/pdf,.heic,.heif" class="rmaAttachments" />
                        <span class="rmaAddMoreHint" style="display:none;color:#a02020;font-size:0.85em;margin-left:8px"><?php echo RMA_ATTACH_MAX_REACHED; ?></span>
                    </p>
                    <p class="rmaAttachmentsCount" style="margin:4px 0;font-size:0.85em;color:#1fa1d0;font-weight:bold"></p>
                    <ul class="rmaAttachmentsPreview" style="list-style:none;padding:0;margin:6px 0;font-size:0.85em;color:#555"></ul>
                    <script>
                    // i18n strings para el preview de adjuntos en JS (consumidas por app.js)
                    window.RMA_LANG = window.RMA_LANG || {};
                    window.RMA_LANG.attach_max     = <?php echo json_encode(RMA_ATTACH_JS_MAX); ?>;
                    window.RMA_LANG.attach_toobig  = <?php echo json_encode(RMA_ATTACH_JS_TOOBIG); ?>;
                    window.RMA_LANG.attach_remove  = <?php echo json_encode(RMA_ATTACH_JS_REMOVE); ?>;
                    window.RMA_LANG.attach_count   = <?php echo json_encode(RMA_ATTACH_JS_COUNT); ?>;
                    </script>

                    <?php if (!$rma->showReembolso($_POST['option_return'])): ?>
                        <p><label><input type="checkbox" name="conditions" required /> <?php echo RMA_CONDITIONS; ?> <a href="https://www.francobordo.com/condiciones-generales-i-3.html" target="_blank"><?php echo RMA_CONDITIONS_VIEW; ?></a></label></p>
                    <?php endif; ?>
                </div>
            </div>
            <p class="rmaButtons">
                <button type="submit" name="rmaNext" class="Button buttonBig buttonFirst"><?php echo ($rma->showReembolso($_POST['option_return']) ? RMA_NEXT : RMA_PROCESS); ?></button>
            </p>
        </form>
    <?php endif; ?>

    <?php if ($nStep == 2 ): ?>
        <form action="<?php echo tep_href_link(FILENAME_RMA, 'orders_id=' . $rma->ordersID . '&products_id=' . $rma->productsID); ?>" method="POST" class="rmaPage">
            <input type="hidden" name="step" value="3"/>
            <?php $rma->getFieldsForm($aFields); ?>
            <p class="rmaTitleText"><?php echo RMA_REEMBOLSO; ?></p>
            <ul class="rmaList">
                <?php
					if( isset( $rma->paymentMethods ) && count( $rma->paymentMethods ) > 0 ):
						foreach ($rma->paymentMethods as $paymentMethod):
				?>
						<li>
							<label><input type="radio" name="payment_method" value="<?php echo $paymentMethod['id']; ?>" required data-address="<?php echo $paymentMethod['is_address']; ?>" class="rmaIsAddress"> <?php echo $paymentMethod['text']; ?></label>
						</li>
				<?php
						endforeach;
					endif;
				?>
                <li style="display: none;"><small><?php echo sprintf(RMA_PAYMENT_REMINDER, $rma->paymentMethodDefault); ?></small></li>
            </ul>

                <?php
                // "No coincide con lo que solicité" (id=5): no preguntamos por la recogida
                // — el operador admin la gestiona manualmente. Para el resto de razones
                // con reembolso, mostramos la sección normal.
                $bShowRecogida = ((int) ($_POST['option_return'] ?? 0) !== 5);
                ?>
                <?php if ($bShowRecogida): ?>
                <p class="rmaTitleText"><?php echo RMA_RETURN_PRODUCT; ?></p>
                <ul class="rmaList">
                    <?php foreach ($rma->typesReturn as $Type): ?>
                        <li>
                            <label><input type="radio" name="type_return" value="<?php echo $Type['id']; ?>" required data-agencia="<?php echo $Type['agencia']; ?>" class="rmaTypeReturn"> <?php echo $Type['text']; ?></label>
                            <?php
                            // Solo mostrar el subtexto auto de coste si el texto del catálogo no
                            // trae ya su propio <small> embebido (algunos tipos tienen detalle custom).
                            if ((float) $Type['price_cost'] > 0 && stripos($Type['text'], '<small') === false): ?>
                                <small><?php echo sprintf(RMA_RETURN_PRICE_COST, number_format($Type['price_cost'], 2).'€'); ?></small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

            <?php
            // === Correos Express: al elegir "Envío a cargo de Francobordo" (type_return id=2)
            // ofrecemos recogida a domicilio o depósito en oficina. Se REGISTRA la elección;
            // el operador la confirma y genera la recogida/etiqueta real desde el admin. ===
            if ($bShowRecogida):
                $cexTypeId = rma::CEX_TYPE_RETURN_ID;
                $cexCp = $rma->getDeliveryPostcode();
                $cexOfis = array();
                $cexClassFile = $_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'correos_express.php';
                if ($cexCp !== '' && is_file($cexClassFile)) {
                    require_once $cexClassFile;
                    $cexEnvQ = tep_db_query("SELECT config_value FROM cex_config WHERE config_key = 'env'");
                    $cexEnv  = tep_db_num_rows($cexEnvQ) ? tep_db_fetch_array($cexEnvQ)['config_value'] : 'test';
                    $cexCli  = new correos_express($cexEnv);
                    $cexCli->setTimeout(6); // de cara al cliente: que un CEX lento no cuelgue el modal
                    $cexOfis = correos_express::oficinasDeposito($cexCli->consultarOficinas($cexCp));
                }
            ?>
            <div class="cexCollect" data-cextype="<?php echo (int) $cexTypeId; ?>" style="display:none;margin:10px 0;padding:10px;background:#eef6ff;border:1px solid #b9d7f5;border-radius:4px">
                <p class="rmaTitleText">¿Cómo prefieres entregar el producto?</p>
                <ul class="rmaList">
                    <li><label><input type="radio" name="cex_metodo" value="domicilio" class="cexMetodo"> 🏠 Que un mensajero pase a recogerlo a tu domicilio</label></li>
                    <li><label><input type="radio" name="cex_metodo" value="oficina" class="cexMetodo"> 🏤 Lo llevo yo a una oficina de Correos</label></li>
                </ul>
                <div class="cexDomicilio" style="display:none">
                    <div class="rmaColumns">
                        <div class="Column">
                            <p class="rmaTitleText">Fecha de recogida</p>
                            <p><input type="date" name="cex_fecha" min="<?php echo date('Y-m-d', strtotime('+1 weekday')); ?>" class="rmaTextBox" /></p>
                        </div>
                        <div class="Column">
                            <p class="rmaTitleText">Franja horaria</p>
                            <ul class="rmaList">
                                <li><label><input type="radio" name="cex_franja" value="1" checked> Mañana</label></li>
                                <li><label><input type="radio" name="cex_franja" value="2"> Tarde</label></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="cexOficina" style="display:none">
                    <?php if ($cexOfis): ?>
                        <p>Puedes dejar el paquete en cualquiera de estas oficinas de Correos cercanas a tu CP <strong><?php echo htmlspecialchars($cexCp); ?></strong>. Te enviaremos la etiqueta por email para imprimir y pegar:</p>
                        <ul class="rmaList">
                            <?php foreach (array_slice($cexOfis, 0, 6) as $o): ?>
                                <li><strong><?php echo htmlspecialchars($o['nombreOficina'] ?? ''); ?></strong> — <?php echo htmlspecialchars(trim(($o['direccionOficina'] ?? '') . ', ' . ($o['poblacionOficina'] ?? ''))); ?><br><small><?php echo htmlspecialchars($o['horarioOficina'] ?? ''); ?></small></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><small>Te indicaremos por email la etiqueta y las oficinas donde puedes depositar el paquete.</small></p>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            (function(){
                function cexMetodoSync(){
                    var box = document.querySelector('.cexCollect');
                    if(!box || box.style.display==='none') return;
                    var m = box.querySelector('input[name="cex_metodo"]:checked');
                    var dom = box.querySelector('.cexDomicilio'), ofi = box.querySelector('.cexOficina');
                    var isDom = !!(m && m.value==='domicilio'), isOfi = !!(m && m.value==='oficina');
                    if(dom){ dom.style.display = isDom?'block':'none'; dom.querySelectorAll('input[name="cex_fecha"]').forEach(function(i){ i.required = isDom; }); }
                    if(ofi){ ofi.style.display = isOfi?'block':'none'; }
                }
                function cexSync(){
                    var t = document.querySelector('input[name="type_return"]:checked');
                    var box = document.querySelector('.cexCollect');
                    if(!box) return;
                    var on = !!(t && t.value === box.getAttribute('data-cextype'));
                    box.style.display = on?'block':'none';
                    box.querySelectorAll('input[name="cex_metodo"]').forEach(function(r){ r.required = on; });
                    if(!on){ box.querySelectorAll('input').forEach(function(i){ i.required=false; }); }
                    cexMetodoSync();
                }
                document.addEventListener('change', function(e){
                    if(e.target && e.target.name==='type_return') cexSync();
                    if(e.target && e.target.name==='cex_metodo') cexMetodoSync();
                });
                cexSync();
            })();
            </script>
            <?php endif; ?>

            <?php if ($bShowRecogida): ?>
            <div class="rmaTypeReturnView" data-agencia="1">
                <p class="rmaTitleText"><?php echo RMA_RETURN_ADDRESS_AGENCY; ?></p>
                <p>
                    <select name="address">
                        <?php foreach ($rma->Addresses as $Address): ?>
                            <option value="<?php echo $Address['address_book_id']; ?>"><?php echo $Address['address']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <div class="rmaColumns">
                    <div class="Column">
                        <p class="rmaTitleText"><?php echo RMA_RETURN_DATE; ?></p>
                        <p><input type="date" name="date_return" min="<?php echo date('Y-m-d', strtotime('+1 weekday')); ?>" class="rmaTextBox" /></p>
                    </div>
                    <div class="Column">
                        <p class="rmaTitleText"><?php echo RMA_RETURN_HORARIO; ?></p>
                        <ul class="rmaList">
                            <li>
                                <label><input type="radio" name="schedule_return" value="1" checked="checked"> <?php echo RMA_HORARIO_1A; ?></label>
                                <small><?php echo RMA_HORARIO_1B; ?></small>
                            </li>
                            <li>
                                <label><input type="radio" name="schedule_return" value="2"> <?php echo RMA_HORARIO_2A; ?></label>
                                <small><?php echo RMA_HORARIO_2B; ?></small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="rmaIsAddressContent">
                <p class="rmaTitleText"><?php echo RMA_RETURN_ADDRESS; ?></p>
                <p>
                    <select name="address_return">
                        <?php foreach ($rma->Addresses as $Address): ?>
                            <option value="<?php echo $Address['address_book_id']; ?>"><?php echo $Address['address']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>
            <?php endif; /* bShowRecogida */ ?>
            <p><label><input type="checkbox" name="conditions" required /> <?php echo RMA_CONDITIONS; ?> <a href="href="https://www.francobordo.com/condiciones-generales-i-3.html" target="_blank"><?php echo RMA_CONDITIONS_VIEW; ?></a></label></p>
            <p class="rmaButtons">
                <button type="submit" name="rmaNext" class="Button buttonBig buttonFirst"><?php echo RMA_PROCESS; ?></button>
            </p>
        </form>
    <?php endif; ?>
    <?php if ($nStep == 3 ):  ?>
        <form action="<?php echo tep_href_link(FILENAME_RMA, 'orders_id=' . $rma->ordersID . '&products_id=' . $rma->productsID); ?>" method="POST" class="rmaPage">
            <input type="hidden" name="step" value="4"/>
            <?php $rma->getFieldsForm($aFields); ?>
            <p class="rmaTitleText"><?php echo RMA_RESUMEN; ?></p>
            <p><?php echo RMA_DATE; ?> <strong><?php echo date('d/m/Y'); ?></strong></p>
            <!--<p>Tipo: <strong>Devolución</strong></p>-->
            <p>
                <strong><?php echo RMA_INFORMATION; ?></strong><br /> <?php echo RMA_INFORMATION2; ?>
            </p>
            <?php $status = array_pop($rma->historyStatus); ?>
            <p><?php echo RMA_STATUS; ?> <strong><?php echo $status['status']; ?></strong></p>
            <p><?php echo RMA_STATUS_ID; ?> <strong><?php echo $rma->idRma; ?></strong></p>
            <p class="rmaButtons">
                <button type="submit" name="rmaNext" class="Button buttonBig buttonFirst rmaFinish"><?php echo RMA_FINISH; ?></button>
            </p>
        </form>
    <?php endif; ?>
    <?php if ($nStep == 5 ): ?>
        <div class="rmaPage">
            <?php if (!empty($rma->historyStatus)) : ?>
                <p class="rmaTitleText"><?php echo RMA_STATUS_RETURN; ?></p>
                <div class="rmaHistoryStatus">
                    <ul>
                    <?php foreach($rma->historyStatus as $historyStatus): ?>
                        <li>
                            <span class="rmaHistoryDate"><?php echo $historyStatus['date']; ?></span>
                            <span class="rmaHistoryStatusText" style="background-color: <?php echo $historyStatus['color']; ?>"><?php echo $historyStatus['status']; ?></span>
                        </li>
                        <?php if ($historyStatus['message'] != ''): ?>
                            <li>
                                <span class="rmaHistoryDate"></span>
                                <span class="rmaHistoryMessage"><?php echo $historyStatus['message']; ?></span>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="rmaTitleText"><?php echo RMA_HAS_ERROR; ?></p>
                <p><?php echo RMA_ERROR; ?></p>
            <?php endif; ?>
            <p><a href="<?php echo tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $rma->ordersID , 'SSL'); ?>" class="Button rmaCloseButton"><?PHP echo RMA_BACK; ?></a></p>
        </div>
    <?php endif; ?>

</div>
