<?php
/**
 * Dashboard Widget: Customer Acquisition Sources
 */
$aHowMeet = [
    1 => 'Buscadores (Google, Yahoo...)',
    2 => 'Marketplace (Ebay, Amazon...)',
    3 => 'Publicidad',
    4 => 'Redes sociales',
    5 => 'A traves de un conocido'
];

$source_colors = ['#5d9cec', '#8dca35', '#ffbd4a', '#7266ba', '#fb6d9d'];
$howmeet_data = [];
$howmeet_total = 0;

$howmeet_query = tep_db_query("SELECT COUNT(*) as totl, how_meet
    FROM " . TABLE_CUSTOMERS . "
    WHERE how_meet > 0
    GROUP BY how_meet
    ORDER BY totl DESC");

while ($hm = tep_db_fetch_array($howmeet_query)) {
    $howmeet_data[$hm['how_meet']] = (int)$hm['totl'];
    $howmeet_total += (int)$hm['totl'];
}

// Rellenar los que faltan con 0
foreach ($aHowMeet as $key => $name) {
    if (!isset($howmeet_data[$key])) {
        $howmeet_data[$key] = 0;
    }
}
?>

<div class="dash-widget" id="widget-how-meet" data-widget="how-meet">
    <div class="dash-widget-header">
        <h3><i class="fa fa-bullhorn"></i> Como nos Conocieron</h3>
    </div>
    <div class="dash-widget-body">
        <?php
        $idx = 0;
        foreach ($aHowMeet as $key => $name) {
            $qty = $howmeet_data[$key];
            $pct = ($howmeet_total > 0) ? round(($qty / $howmeet_total) * 100, 1) : 0;
            $color = $source_colors[$idx % count($source_colors)];
        ?>
        <div class="dash-source-item">
            <div class="dash-source-label"><?php echo $name; ?></div>
            <div class="dash-source-bar-wrap">
                <div class="dash-source-bar">
                    <div class="dash-source-bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></div>
                </div>
            </div>
            <div class="dash-source-value"><?php echo number_format($qty); ?></div>
        </div>
        <?php
            $idx++;
        }
        ?>
    </div>
</div>
