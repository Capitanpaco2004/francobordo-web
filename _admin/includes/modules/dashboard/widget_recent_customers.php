<?php
/**
 * Dashboard Widget: Recent Customers
 */
$recent_customers_query = tep_db_query("SELECT c.customers_id, c.customers_firstname, c.customers_lastname,
    c.customers_email_address, ci.customers_info_date_account_created as date_created
    FROM " . TABLE_CUSTOMERS . " c
    LEFT JOIN " . TABLE_CUSTOMERS_INFO . " ci ON c.customers_id = ci.customers_info_id
    ORDER BY ci.customers_info_date_account_created DESC LIMIT 10");
?>

<div class="dash-widget" id="widget-recent-customers" data-widget="recent-customers">
    <div class="dash-widget-header">
        <h3><i class="fa fa-user-plus"></i> Ultimos Clientes</h3>
        <div class="dash-widget-actions">
            <button class="dash-widget-action" onclick="dashRefreshWidget('recent-customers')" title="Refrescar"><i class="fa fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="dash-widget-body no-padding">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th style="text-align: right;">Fecha registro</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (tep_db_num_rows($recent_customers_query) > 0) {
                    while ($cust = tep_db_fetch_array($recent_customers_query)) {
                ?>
                <tr>
                    <td>
                        <a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, 'cID=' . $cust['customers_id'] . '&action=edit'); ?>" style="color: var(--dash-primary); font-weight: 500;">
                            <?php echo htmlspecialchars($cust['customers_firstname'] . ' ' . $cust['customers_lastname']); ?>
                        </a>
                    </td>
                    <td style="font-size: 12px; color: var(--dash-text-light);"><?php echo htmlspecialchars($cust['customers_email_address']); ?></td>
                    <td style="text-align: right;"><?php echo tep_date_short($cust['date_created']); ?></td>
                </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="3"><div class="dash-empty"><i class="fa fa-users"></i><p>No hay clientes recientes</p></div></td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <a class="dash-view-all" href="<?php echo tep_href_link(FILENAME_CUSTOMERS); ?>">Ver todos los clientes <i class="fa fa-arrow-right"></i></a>
</div>
