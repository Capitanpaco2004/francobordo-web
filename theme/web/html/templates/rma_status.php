<?php
// Página dedicada de estado de RMA. Reusa las clases CSS de account_history_info.php
// para mantener la misma estética (banner azul, cajas grises, layout 2-columnas).

// i18n minimal por $language
$isEN = (isset($language) && $language === 'english');
$tRma           = $isEN ? 'RMA'                     : 'RMA';
$tOrder         = $isEN ? 'Order'                   : 'Pedido';
$tProduct       = $isEN ? 'Product'                 : 'Producto';
$tRequestedOn   = $isEN ? 'Requested on'            : 'Solicitado el';
$tCurrentStatus = $isEN ? 'Current status'          : 'Estado actual';
$tHistory       = $isEN ? 'Status history'          : 'Historial de estados';
$tBack          = $isEN ? 'Back to order'           : 'Volver al pedido';
$tNoHistory     = $isEN ? 'No status history yet.'  : 'Aún no hay historial de estados.';

// historyStatus viene ORDER BY rsh.id DESC en getHistoryStatus(): [0] = más reciente, end() = más antiguo.
$lastStatus = !empty($rma->historyStatus) ? $rma->historyStatus[0] : null;       // estado actual
$firstStat  = !empty($rma->historyStatus) ? end($rma->historyStatus) : null;     // solicitud (creación)
reset($rma->historyStatus);
$firstDate  = $firstStat ? $firstStat['date'] : '';

$productName  = $rma->Product['products_name']  ?? '';
$productModel = $rma->Product['products_model'] ?? '';
?>
<div class="orderHistoryInfo">
    <div class="orderHistoryHeader orderFlex">
        <h2><strong><?php echo $tRma; ?>:</strong> <?php echo (int) $rma->idRma; ?></h2>
    </div>

    <div class="orderHistoryDetail orderFlex">
        <p>
            <?php echo $tOrder; ?> <strong>#<?php echo (int) $ordersID; ?></strong>
            <?php if ($productName !== ''): ?>
                | <?php echo $tProduct; ?>: <strong><?php echo $productName; ?></strong>
            <?php endif; ?>
        </p>
        <?php if ($firstDate !== ''): ?>
            <p class="textRight"><?php echo $tRequestedOn; ?> <strong><?php echo $firstDate; ?></strong></p>
        <?php endif; ?>
    </div>

    <div class="orderHistoryContent orderFlex">
        <div class="orderHistoryColumn">
            <div class="orderHistoryBox">
                <h3><?php echo $tProduct; ?></h3>
                <p>
                    <strong><?php echo $productName; ?></strong>
                    <?php if ($productModel !== ''): ?>
                        <br/><small>(<?php echo $productModel; ?>)</small>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($lastStatus): ?>
                <div class="orderHistoryBox">
                    <h3><?php echo $tCurrentStatus; ?></h3>
                    <p>
                        <span class="rmaHistoryStatusText" style="background-color: <?php echo $lastStatus['color']; ?>;color:#fff;font-weight:bold;padding:4px 12px;border-radius:4px;display:inline-block;">
                            <?php echo $lastStatus['status']; ?>
                        </span>
                    </p>
                    <?php if (!empty($lastStatus['message'])): ?>
                        <p style="margin-top:10px;line-height:1.55;"><?php echo $lastStatus['message']; ?></p>
                    <?php endif; ?>
                    <p><small style="color:#888;"><?php echo $lastStatus['date']; ?></small></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="orderHistoryColumn">
            <div class="orderHistoryBox orderHistoryBoxResumen">
                <h3><?php echo $tHistory; ?></h3>
                <?php if (!empty($rma->historyStatus)): ?>
                    <ul style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($rma->historyStatus as $hs): ?>
                            <li style="padding:12px 0;border-bottom:1px solid #eee;">
                                <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                                    <span style="flex:0 0 145px;font-size:13px;color:#666;line-height:1.4;"><?php echo $hs['date']; ?></span>
                                    <span style="background-color: <?php echo $hs['color']; ?>;color:#fff;font-weight:bold;padding:3px 10px;border-radius:4px;font-size:13px;"><?php echo $hs['status']; ?></span>
                                </div>
                                <?php if (!empty($hs['message'])): ?>
                                    <p style="margin:8px 0 0 0;padding-left:157px;font-size:13px;color:#444;line-height:1.5;"><?php echo $hs['message']; ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><em><?php echo $tNoHistory; ?></em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="orderHistoryButtons orderFlex">
        <p class="textRight" style="width:100%;">
            <a href="<?php echo tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . (int) $ordersID, 'SSL'); ?>" class="Button buttonFirst">← <?php echo $tBack; ?></a>
        </p>
    </div>
</div>
