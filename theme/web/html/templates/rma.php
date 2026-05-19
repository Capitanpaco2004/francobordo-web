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
