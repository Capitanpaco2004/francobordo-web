<?php
$rmaLists = getRmaList();
$statuses = rmaGetStatus();
$languages = rmaGetLanguages();
require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();
?>
<div class="row ax">
    <div class="oeBox column a12 T12 row ax">
        <div class="oeWrpr">
            <div class="oeTitu">
                <i class="fa fa-cog"></i> <?php echo $sTitle; ?>
                <form class="rmaListSearch" method="get" action="<?php echo tep_href_link('rma.php'); ?>">
                    <input type="text" name="id_rma" placeholder="Número de RMA" value="<?php echo tep_db_prepare_input($_GET['id_rma']); ?>"/>
                    <input type="text" name="customer" placeholder="Nombre de cliente" value="<?php echo tep_db_prepare_input($_GET['customer']); ?>"/>
                    <select name="status_id" class="skip">
                        <option value="0">Todos</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>" <?php echo (intval($_GET['status_id']) == $status['id'] ? 'selected' : ''); ?>><?php echo $status['text']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button class="button verde" type="submit">Filtrar</button>
                    <a class="button rojo" href="<?php echo tep_href_link('rma.php'); ?>">Quitar filtros</a>
                    <input type="hidden" name="action" value="list" />
                </form>
            </div>
            <div class="oeCntd">
                <pre><?php //echo $rmaLists['sql']; ?></pre>
                <?php if (!empty($rmaLists['data'])): ?>
                    <ul class="rmaPaging">
                        <!--<li><?php echo $rmaLists['count']; ?></li>-->
                        <li><?php echo $rmaLists['links']; ?></li>
                    </ul>
                <table>
                    <thead>
                        <tr>
                            <td width="40"></td>
                            <td>#RMA</td>
                            <td>#Pedido</td>
                            <td>Cliente</td>
                            <td>Cantidad</td>
                            <td>Producto</td>
                            <td>Razón devolución</td>

                            <td>Fecha pedido</td>
                            <td>Fecha apertura RMA</td>
                            <td>Última modificación</td>
                            <td>Estado</td>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rmaLists['data'] as $key => $rmaDetail): ?>
                            <tr class="oeTrProduct">
                                <td><img src="../includes/languages/<?php echo $languages[$rmaDetail['languages_id']]['directory']; ?>/images/<?php echo $languages[$rmaDetail['languages_id']]['image']; ?>" /></td>
                                <td>
                                    <a href="<?php echo tep_href_link('rma.php', 'action=view&id=' . $rmaDetail['id']); ?>">
                                        <?php echo str_pad($rmaDetail['id'], 10, "0", STR_PAD_LEFT); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo tep_href_link('orders.php', 'oID='.$rmaDetail['orders_id'].'&action=edit'); ?>" target="_blank">
                                        <?php echo $rmaDetail['orders_id']; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $rmaDetail['entry_name']; ?>
                                </td>
                                <td>
                                    <?php echo $rmaDetail['quantity']; ?>
                                </td>
                                <td>
                                    <a href="<?php echo tep_href_link('categories.php', 'pID='.$rmaDetail['products_id'].'&action=new_product'); ?>" target="_blank">
                                        <?php echo $rmaDetail['products_name']; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $rmaDetail['option_return']; ?>
                                </td>

                                <td>
                                    <?php echo $rmaDetail['date_purchased']; ?>
                                    <small><?php echo rmaTraduceDias($rmaDetail['date_purchased_raw']); ?></small>
                                </td>
                                <td>
                                    <?php echo $rmaDetail['date']; ?>
                                    <small><?php echo rmaTraduceDias($rmaDetail['date_raw']); ?></small>
                                </td>
                                <td>
                                    <?php echo $rmaDetail['ultima_modificacion']; ?>
                                    <small><?php echo rmaTraduceDias($rmaDetail['ultima_modificacion_raw']); ?></small>
                                </td>
                                <td class="rmaStatus">
                                    <div class="rmaStatusActual">
                                        <div style="margin-right: 5px; display: inline-block; width: 10px; height: 10px; border-radius: 100%; background-color: <?php echo $rmaDetail['color']; ?>"></div> <?php echo $rmaDetail['status']; ?>
                                    </div>
                                    <form class="rmaListStatus rows sp10" method="post" action="<?php echo tep_href_link('rma.php', 'action=change-status'); ?>">
                                        <select name="id_status" class="column a12 skip">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo $status['id']; ?>" <?php echo ($rmaDetail['status_id'] == $status['id'] ? 'selected' : ''); ?>><?php echo $status['text']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="column a12"><input type="checkbox" name="notify" value="1" checked="checked" /> Notificar al cliente</label>
                                        <button class="column a12 xbutton verde" type="submit">Guardar</button>
                                        <input type="hidden" name="id" value="<?php echo $rmaDetail['id']; ?>" />
                                        <input type="hidden" name="customers_name" value="<?php echo $rmaDetail['entry_name']; ?>" />
                                        <input type="hidden" name="customers_email" value="<?php echo $rmaDetail['customers_email_address']; ?>" />
                                        <input type="hidden" name="id_status_previous" value="<?php echo $rmaDetail['status_id']; ?>" />
                                        <input type="hidden" name="language_id" value="<?php echo $rmaDetail['languages_id']; ?>" />
                                    </form>
                                    <p><a href="javascript:void(0);" class="rmaChangeStatus">Cambiar</a></p>
                                </td>
                                <td>
                                    <a class="column a12 xbutton tiny verde" href="<?php echo tep_href_link('rma.php', 'action=view&id=' . $rmaDetail['id']); ?>">Ver</a>
                                    <a class="column a12 xbutton tiny rojo rmaRemove" href="<?php echo tep_href_link('rma.php', 'action=remove-rma&id=' . $rmaDetail['id']); ?>">Borrar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <ul class="rmaPaging">
                    <!--<li><?php echo $rmaLists['count']; ?></li>-->
                    <li><?php echo $rmaLists['links']; ?></li>
                </ul>
            <?php else: ?>
                Vaya... no hay datos que mostrar.

					<?php if ($_GET !== [] && count($_GET) > 3): ?>
					<a href="<?php echo tep_href_link('rma.php'); ?>">Quitar filtros</a>
					<?php endif; ?>

            <?php endif; ?>
            </div>
        </div>
    </div>
</div>
