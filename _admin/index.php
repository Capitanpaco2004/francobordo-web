<?php
    require('includes/application_top.php');

    // Dashboard AJAX handler
    $dash_action = $_POST['dash_action'] ?? $_GET['dash_action'] ?? '';
    if ($dash_action !== '') {
        header('Content-Type: application/json');
        $config_dir = dirname(__FILE__) . '/includes/modules/dashboard/config/';
        if (!is_dir($config_dir)) mkdir($config_dir, 0755, true);
        $config_file = $config_dir . 'admin_' . (int)$login_id . '.json';

        switch ($dash_action) {
            case 'save_config':
                $config = $_POST['config'] ?? '';
                if (!empty($config)) {
                    $decoded = json_decode($config, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        file_put_contents($config_file, json_encode($decoded, JSON_PRETTY_PRINT));
                        echo json_encode(['status' => 'ok']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'JSON invalido']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Config vacia']);
                }
                break;
            case 'load_config':
                if (file_exists($config_file)) {
                    echo json_encode(['status' => 'ok', 'config' => json_decode(file_get_contents($config_file), true)]);
                } else {
                    echo json_encode(['status' => 'ok', 'config' => null]);
                }
                break;
            case 'reset_config':
                if (file_exists($config_file)) unlink($config_file);
                echo json_encode(['status' => 'ok']);
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Accion no valida']);
        }
        exit;
    }

    require(DIR_WS_CLASSES . 'currencies.php');

    $currencies = new currencies();

    date_default_timezone_set("Europe/Madrid");
    setlocale(LC_TIME, "es_ES");

    // Lista de widgets disponibles con metadatos
    // 'size' = columnas de 12 por defecto (4=1/3, 6=1/2, 8=2/3, 12=full)
    $dashboard_widgets = [
        'kpis'              => ['label' => 'KPIs (Ventas, Pedidos, Clientes)',  'file' => 'widget_kpis.php',             'default' => true,  'size' => 12],
        'sales-chart'       => ['label' => 'Grafico de Ventas',                'file' => 'widget_sales_chart.php',       'default' => true,  'size' => 8,  'requires' => 'reports.php'],
        'orders-status'     => ['label' => 'Pedidos por Estado',               'file' => 'widget_orders_status.php',     'default' => true,  'size' => 4,  'row' => 2, 'requires' => 'reports.php'],
        'payment-methods'   => ['label' => 'Metodos de Pago',                  'file' => 'widget_payment_today.php',     'default' => true,  'size' => 8,  'requires' => 'reports.php'],
        'recent-orders'     => ['label' => 'Ultimos Pedidos',                  'file' => 'widget_recent_orders.php',     'default' => true,  'size' => 12, 'requires' => 'customers.php'],
        'recent-customers'  => ['label' => 'Ultimos Clientes',                 'file' => 'widget_recent_customers.php',  'default' => true,  'size' => 8,  'requires' => 'customers.php'],
        'top-products'      => ['label' => 'Top Productos',                    'file' => 'widget_top_products.php',      'default' => true,  'size' => 4],
        'low-stock'         => ['label' => 'Alertas de Stock',                 'file' => 'widget_low_stock.php',         'default' => true,  'size' => 4,  'requires' => 'reports.php'],
        'quick-actions'     => ['label' => 'Acciones Rapidas',                 'file' => 'widget_quick_actions.php',     'default' => true,  'size' => 4],
        'whos-online'       => ['label' => 'Usuarios Online',                  'file' => 'widget_whos_online.php',       'default' => true,  'size' => 12],
    ];

    $widget_dir = DIR_WS_INCLUDES . 'modules/dashboard/';

    // Cargar configuracion guardada del admin (orden, tamaños, visibilidad)
    $config_file = dirname(__FILE__) . '/includes/modules/dashboard/config/admin_' . (int)$login_id . '.json';
    $saved_config = null;
    if (file_exists($config_file)) {
        $saved_config = json_decode(file_get_contents($config_file), true);
    }

    // Aplicar orden guardado
    if ($saved_config && isset($saved_config['order']) && is_array($saved_config['order'])) {
        $ordered_widgets = [];
        // Primero los que estan en el orden guardado
        foreach ($saved_config['order'] as $wid) {
            if (isset($dashboard_widgets[$wid])) {
                $ordered_widgets[$wid] = $dashboard_widgets[$wid];
            }
        }
        // Luego los nuevos que no estaban guardados
        foreach ($dashboard_widgets as $wid => $wdata) {
            if (!isset($ordered_widgets[$wid])) {
                $ordered_widgets[$wid] = $wdata;
            }
        }
        $dashboard_widgets = $ordered_widgets;
    }

    // Aplicar tamaños y visibilidad guardados
    if ($saved_config && isset($saved_config['widgets']) && is_array($saved_config['widgets'])) {
        foreach ($saved_config['widgets'] as $wid => $wconf) {
            if (isset($dashboard_widgets[$wid])) {
                if (isset($wconf['size'])) $dashboard_widgets[$wid]['size'] = (int)$wconf['size'];
                if (isset($wconf['visible'])) $dashboard_widgets[$wid]['default'] = (bool)$wconf['visible'];
            }
        }
    }
?>
<?php include(THEME . 'html/header.php'); ?>

<!-- Dashboard CSS -->
<link rel="stylesheet" type="text/css" href="<?php echo THEME; ?>css/dashboard.css?v=<?php echo filemtime(THEME . 'css/dashboard.css'); ?>"/>

<div class="dashboard-wrap">

    <!-- Header -->
    <div class="dash-header">
        <h1>
            Dashboard
            <small><?php
                $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Madrid');
                echo ucfirst($formatter->format(new DateTime()));
            ?></small>
        </h1>
        <div class="dash-header-actions">
            <div class="dash-auto-refresh" id="dash-auto-refresh">
                <span class="dot" id="dash-refresh-dot"></span>
                <span id="dash-refresh-label">Auto-refresh: 5m</span>
            </div>
            <?php if (tep_admin_check_boxes('orders.php')): ?>
            <a class="dash-btn" href="orders.php"><i class="fa fa-shopping-cart"></i> Pedidos</a>
            <?php endif; ?>
            <?php if (tep_admin_check_boxes('customers.php')): ?>
            <a class="dash-btn" href="customers.php"><i class="fa fa-users"></i> Clientes</a>
            <?php endif; ?>
            <button class="dash-btn" id="dash-edit-btn"><i class="fa fa-th-large"></i> Editar Layout</button>
            <button class="dash-btn dash-btn-reset" id="dash-reset-btn" style="display:none;" title="Restaurar layout por defecto"><i class="fa fa-undo"></i> Reset</button>
            <button class="dash-btn" id="dash-config-btn"><i class="fa fa-cog"></i> Configurar</button>
            <a class="dash-btn dash-btn-primary" href="<?php echo HTTPS_CATALOG_SERVER; ?>" target="_blank"><i class="fa fa-store"></i> Ver Tienda</a>
        </div>
    </div>

    <!-- Sortable grid - drag header to reorder, drag right edge to resize, X to hide -->
    <div class="dash-sortable-grid" id="dash-sortable-grid">
        <?php
        foreach ($dashboard_widgets as $widget_id => $widget) {
            // Comprobar permisos
            if (isset($widget['requires']) && !tep_admin_check_boxes($widget['requires'])) continue;

            $size = (int)$widget['size'];
            $hidden = !$widget['default'] ? ' dash-hidden' : '';
            $rowspan = isset($widget['row']) ? ' dash-row-' . (int)$widget['row'] : '';
        ?>
        <div class="dash-cell dash-w-<?php echo $size; ?><?php echo $hidden; ?><?php echo $rowspan; ?>"
             data-widget="<?php echo $widget_id; ?>"
             data-size="<?php echo $size; ?>">
            <?php include($widget_dir . $widget['file']); ?>
        </div>
        <?php } ?>
    </div>

</div>

<!-- Configuration Panel (Slide-in) -->
<div class="dash-config-overlay" id="dash-config-overlay"></div>
<div class="dash-config-panel" id="dash-config-panel">
    <div class="dash-config-header">
        <h3><i class="fa fa-cog"></i> Configurar Dashboard</h3>
        <button class="dash-config-close" id="dash-config-close"><i class="fa fa-times"></i></button>
    </div>
    <div class="dash-config-body">
        <p style="font-size: 13px; color: var(--dash-text-light); margin-bottom: 16px;">
            Activa/desactiva widgets y KPIs. Arrastra para reordenar.
        </p>

        <!-- Auto-refresh -->
        <div style="margin-bottom: 16px;">
            <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--dash-text-light); margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--dash-border);">
                <i class="fa fa-sync-alt"></i> Auto-refresh
            </div>
            <div class="dash-refresh-options" id="dash-refresh-options">
                <button class="dash-refresh-opt" data-minutes="0">Off</button>
                <button class="dash-refresh-opt" data-minutes="1">1 min</button>
                <button class="dash-refresh-opt" data-minutes="2">2 min</button>
                <button class="dash-refresh-opt active" data-minutes="5">5 min</button>
                <button class="dash-refresh-opt" data-minutes="10">10 min</button>
                <button class="dash-refresh-opt" data-minutes="15">15 min</button>
                <button class="dash-refresh-opt" data-minutes="30">30 min</button>
            </div>
        </div>

        <!-- KPIs individuales -->
        <?php if (isset($kpi_cards) && is_array($kpi_cards)): ?>
        <div style="margin-bottom: 16px;">
            <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--dash-text-light); margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--dash-border);">
                <i class="fa fa-chart-bar"></i> KPIs individuales
            </div>
            <div id="dash-config-kpis">
                <?php foreach ($kpi_cards as $kpi_id => $kpi_data): ?>
                <?php $kpi_sz = isset($kpi_data['kpi_size']) ? (int)$kpi_data['kpi_size'] : 2; ?>
                <div class="dash-config-item dash-config-kpi-item" data-kpi="<?php echo $kpi_id; ?>">
                    <i class="fa fa-grip-vertical drag-handle"></i>
                    <div class="dash-config-item-info">
                        <label for="config-kpi-<?php echo $kpi_id; ?>"><?php echo $kpi_data['label']; ?></label>
                        <div class="dash-config-size-btns">
                            <button class="dash-cfg-kpi-size <?php echo $kpi_sz == 2 ? 'active' : ''; ?>" data-kpi-size="2">1/6</button>
                            <button class="dash-cfg-kpi-size <?php echo $kpi_sz == 3 ? 'active' : ''; ?>" data-kpi-size="3">1/4</button>
                            <button class="dash-cfg-kpi-size <?php echo $kpi_sz == 4 ? 'active' : ''; ?>" data-kpi-size="4">1/3</button>
                            <button class="dash-cfg-kpi-size <?php echo $kpi_sz == 6 ? 'active' : ''; ?>" data-kpi-size="6">1/2</button>
                        </div>
                    </div>
                    <div class="dash-toggle">
                        <input type="checkbox" id="config-kpi-<?php echo $kpi_id; ?>"
                               class="dash-config-kpi-toggle"
                               data-kpi="<?php echo $kpi_id; ?>"
                               <?php echo empty($kpi_data['hidden']) ? 'checked' : ''; ?>>
                        <span class="dash-toggle-slider"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Widgets -->
        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--dash-text-light); margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--dash-border);">
            <i class="fa fa-th-large"></i> Widgets
        </div>
        <div id="dash-config-list">
            <?php foreach ($dashboard_widgets as $widget_id => $widget):
                if (isset($widget['requires']) && !tep_admin_check_boxes($widget['requires'])) continue;
            ?>
            <div class="dash-config-item" data-widget="<?php echo $widget_id; ?>">
                <i class="fa fa-grip-vertical drag-handle"></i>
                <div class="dash-config-item-info">
                    <label for="config-<?php echo $widget_id; ?>"><?php echo $widget['label']; ?></label>
                    <div class="dash-config-size-btns">
                        <button class="dash-cfg-size <?php echo $widget['size'] == 4 ? 'active' : ''; ?>" data-size="4">1/3</button>
                        <button class="dash-cfg-size <?php echo $widget['size'] == 6 ? 'active' : ''; ?>" data-size="6">1/2</button>
                        <button class="dash-cfg-size <?php echo $widget['size'] == 8 ? 'active' : ''; ?>" data-size="8">2/3</button>
                        <button class="dash-cfg-size <?php echo $widget['size'] == 12 ? 'active' : ''; ?>" data-size="12">Full</button>
                    </div>
                </div>
                <div class="dash-toggle">
                    <input type="checkbox" id="config-<?php echo $widget_id; ?>"
                           class="dash-config-toggle"
                           data-widget="<?php echo $widget_id; ?>"
                           <?php echo $widget['default'] ? 'checked' : ''; ?>>
                    <span class="dash-toggle-slider"></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require(THEME . 'html/footer.php'); ?>
<!-- Chart.js CDN (after jQuery from footer) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<!-- Dashboard JS -->
<script src="<?php echo THEME; ?>js/dashboard.js?v=<?php echo filemtime(THEME . 'js/dashboard.js'); ?>"></script>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
