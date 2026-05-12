<?php
/**
 * Dashboard Widget: Orders by Status (Donut Chart + Table with qty & totals)
 * With period tabs: Hoy / 30 Días / Todo
 * Uses real colors from orders_status.color field
 */
require_once(DIR_WS_CLASSES . 'currencies.php');
if (!is_object($currencies)) $currencies = new currencies();

$fallback_colors = ['#5d9cec', '#8dca35', '#ffbd4a', '#da3610', '#7266ba', '#5fbeaa', '#fb6d9d', '#34d3eb', '#4c5667'];

// Obtener todos los estados con su color (base)
$all_statuses = [];
$os_base_query = tep_db_query("SELECT orders_status_id, orders_status_name, color
    FROM " . TABLE_ORDERS_STATUS . "
    WHERE language_id = '" . (int)$languages_id . "'
    ORDER BY orders_status_id");
$cidx = 0;
while ($row = tep_db_fetch_array($os_base_query)) {
    $color = (!empty($row['color']) && $row['color'] != '#000000') ? $row['color'] : $fallback_colors[$cidx % count($fallback_colors)];
    $all_statuses[$row['orders_status_id']] = [
        'id' => $row['orders_status_id'],
        'name' => $row['orders_status_name'],
        'color' => $color,
    ];
    $cidx++;
}

// Query con los 3 periodos en una sola consulta usando conditional aggregation
$os_query = tep_db_query("SELECT o.orders_status,
    COUNT(CASE WHEN DATE(o.date_purchased) = CURDATE() THEN 1 END) as qty_today,
    COALESCE(SUM(CASE WHEN DATE(o.date_purchased) = CURDATE() THEN ot.value END), 0) as total_today,
    COUNT(CASE WHEN o.date_purchased >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as qty_30d,
    COALESCE(SUM(CASE WHEN o.date_purchased >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ot.value END), 0) as total_30d,
    COUNT(*) as qty_all,
    COALESCE(SUM(ot.value), 0) as total_all
    FROM " . TABLE_ORDERS . " o
    LEFT JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    GROUP BY o.orders_status");

$status_agg = [];
while ($row = tep_db_fetch_array($os_query)) {
    $status_agg[$row['orders_status']] = $row;
}

// Construir datos para cada periodo
$periods = ['today' => 'Hoy', '30d' => '30 Dias', 'all' => 'Todo'];
$chart_data = [];
$table_data = [];

foreach ($periods as $period_key => $period_label) {
    $labels = []; $data = []; $colors = []; $rows = [];
    $total_qty = 0; $total_amount = 0;

    // Construir filas por status, ordenadas por qty DESC
    $period_rows = [];
    foreach ($all_statuses as $sid => $sinfo) {
        $agg = isset($status_agg[$sid]) ? $status_agg[$sid] : null;
        if ($period_key === 'today') {
            $qty = $agg ? (int)$agg['qty_today'] : 0;
            $amount = $agg ? round((float)$agg['total_today'], 2) : 0;
        } elseif ($period_key === '30d') {
            $qty = $agg ? (int)$agg['qty_30d'] : 0;
            $amount = $agg ? round((float)$agg['total_30d'], 2) : 0;
        } else {
            $qty = $agg ? (int)$agg['qty_all'] : 0;
            $amount = $agg ? round((float)$agg['total_all'], 2) : 0;
        }
        $period_rows[] = [
            'id' => $sid,
            'name' => $sinfo['name'],
            'color' => $sinfo['color'],
            'qty' => $qty,
            'amount' => $amount,
        ];
    }
    // Ordenar por qty DESC
    usort($period_rows, function($a, $b) { return $b['qty'] - $a['qty']; });

    foreach ($period_rows as $pr) {
        $labels[] = $pr['name'];
        $data[] = $pr['qty'];
        $colors[] = $pr['color'];
        $rows[] = $pr;
        $total_qty += $pr['qty'];
        $total_amount += $pr['amount'];
    }

    $chart_data[$period_key] = ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    $table_data[$period_key] = ['rows' => $rows, 'total_qty' => $total_qty, 'total_amount' => $total_amount];
}

// Default: "all" (todo el periodo)
$default_period = 'today';
?>

<div class="dash-widget" id="widget-orders-status" data-widget="orders-status">
    <div class="dash-widget-header">
        <h3><i class="fa fa-chart-pie"></i> Pedidos por Estado</h3>
        <div class="dash-widget-actions">
            <div class="dash-chart-tabs">
                <?php foreach ($periods as $pk => $pl): ?>
                <button class="dash-chart-tab<?php echo $pk === $default_period ? ' active' : ''; ?>" data-os-tab="<?php echo $pk; ?>"><?php echo $pl; ?></button>
                <?php endforeach; ?>
            </div>
            <button class="dash-widget-action" onclick="dashRefreshWidget('orders-status')" title="Refrescar"><i class="fa fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="dash-widget-body">
        <!-- Donut Chart -->
        <div class="dash-chart-container small">
            <canvas id="ordersStatusChart"></canvas>
        </div>

        <!-- Status Tables (one per period) -->
        <?php foreach ($periods as $pk => $pl): $td = $table_data[$pk]; ?>
        <div class="dash-os-tab-content" id="os-tab-<?php echo $pk; ?>" style="<?php echo $pk !== $default_period ? 'display:none;' : ''; ?>">
            <table class="dash-table" style="margin-top: 12px;">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th style="text-align: right;">Pedidos</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($td['rows'] as $sr): ?>
                    <tr>
                        <td>
                            <a href="<?php echo tep_href_link(FILENAME_ORDERS, 'selected_box=customers&status=' . (int)$sr['id']); ?>" style="display: inline-flex; align-items: center; gap: 6px; color: inherit; text-decoration: none;">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo htmlspecialchars($sr['color']); ?>; flex-shrink: 0;"></span>
                                <?php echo tep_db_prepare_input($sr['name']); ?>
                            </a>
                        </td>
                        <td style="text-align: right; font-weight: 600;"><?php echo number_format($sr['qty']); ?></td>
                        <td style="text-align: right; font-weight: 600;"><?php echo $currencies->format($sr['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 2px solid var(--dash-border);">
                        <td><strong>Total</strong></td>
                        <td style="text-align: right;"><strong><?php echo number_format($td['total_qty']); ?></strong></td>
                        <td style="text-align: right;"><strong><?php echo $currencies->format($td['total_amount']); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <a class="dash-view-all" href="<?php echo tep_href_link(FILENAME_ORDERS); ?>">Ver todos los pedidos <i class="fa fa-arrow-right"></i></a>
</div>

<script>
var dashOrdersStatusData = <?php echo json_encode($chart_data); ?>;
</script>
