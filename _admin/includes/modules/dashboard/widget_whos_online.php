<?php
/**
 * Dashboard Widget: Who's Online
 */

// Limpiar sesiones expiradas
$xx_mins_ago = (time() - 900);
tep_db_query("DELETE FROM " . TABLE_WHOS_ONLINE . " WHERE time_last_click < '" . $xx_mins_ago . "'");

// Contar visitantes reales (excluir bots: customer_id = -1)
$count_query = tep_db_query("SELECT COUNT(*) as qty FROM " . TABLE_WHOS_ONLINE . " WHERE customer_id >= 0");
$online_count = (int)tep_db_fetch_array($count_query)['qty'];

// Últimos 20 para la tabla
$whos_online_query = tep_db_query("SELECT customer_id, full_name, ip_address, time_entry, time_last_click, last_page_url
    FROM " . TABLE_WHOS_ONLINE . "
    WHERE customer_id >= 0
    ORDER BY time_last_click DESC LIMIT 20");
?>

<div class="dash-widget" id="widget-whos-online" data-widget="whos-online">
    <div class="dash-widget-header">
        <h3><i class="fa fa-users"></i> Usuarios Online</h3>
        <div class="dash-widget-actions">
            <span class="dash-auto-refresh"><span class="dot"></span> Auto-refresh</span>
        </div>
    </div>
    <div class="dash-widget-body no-padding">
        <?php if ($online_count > 0): ?>
        <div class="dash-online-count">
            <div class="dash-online-number"><?php echo $online_count; ?></div>
            <div class="dash-online-label"><span class="dash-online-dot"></span> visitantes ahora mismo</div>
        </div>
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Visitante</th>
                    <th>IP</th>
                    <th>Tiempo</th>
                    <th>Ultima pagina</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($wo = tep_db_fetch_array($whos_online_query)):
                    $time_online = (time() - $wo['time_entry']);
                    $page_url = $wo['last_page_url'];
                    if (preg_match('/^(.*)' . tep_session_name() . '=[a-f,0-9]+[&]*(.*)/', $page_url, $arr)) {
                        $page_url = $arr[1] . $arr[2];
                    }
                    // Acortar URL
                    if (strlen($page_url) > 40) $page_url = substr($page_url, 0, 40) . '...';
                ?>
                <tr>
                    <td>
                        <?php if ($wo['customer_id'] > 0): ?>
                            <i class="fa fa-user" style="color: var(--dash-primary); margin-right: 4px;"></i>
                        <?php else: ?>
                            <i class="fa fa-user-secret" style="color: var(--dash-text-light); margin-right: 4px;"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($wo['full_name']); ?>
                    </td>
                    <td style="font-family: monospace; font-size: 11px;"><?php echo $wo['ip_address']; ?></td>
                    <td><?php echo gmdate('H:i:s', $time_online); ?></td>
                    <td style="font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($wo['last_page_url']); ?>"><?php echo htmlspecialchars($page_url); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="dash-empty"><i class="fa fa-user-slash"></i><p>No hay usuarios online ahora mismo</p></div>
        <?php endif; ?>
    </div>
</div>
