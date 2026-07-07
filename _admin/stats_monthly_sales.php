<?php
require('includes/application_top.php');

// ======================================================
// CONFIG SOLENOPSIS
// ======================================================

$sTitle    = 'Informe de Ventas Anuales';
$sSubtitle = 'Histórico comparativo de ventas anuales/mensuales';
$aButtons  = [];
$sHtml     = '';

$messageStack->style = 'solenopsis';

// ======================================================
// PARAMS
// ======================================================
$action = isset($_GET['action']) ? tep_db_prepare_input($_GET['action']) : '';
$monthsBack = isset($_GET['months']) ? (int)$_GET['months'] : 1;
if ($monthsBack < 0) $monthsBack = 1;

// Filtros (year 0 = Todos)
$year   = (isset($_GET['year']) && $_GET['year'] !== '') ? (int)$_GET['year'] : 0;
$month  = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$status = isset($_GET['status']) ? (int)$_GET['status'] : 0;

$isDailyView = ($year > 0 && $month > 0);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'tax_breakdown') {

	$year   = (int)$_GET['year'];
	$month  = (int)$_GET['month'];
	$day    = isset($_GET['day']) ? (int)$_GET['day'] : 0;
	$status = (int)$_GET['status'];

	$where = [];
	$where[] = "YEAR(o.date_purchased) = $year";
	$where[] = "MONTH(o.date_purchased) = $month";

	if ($day > 0) {
		$where[] = "DAY(o.date_purchased) = $day";
	}

	$where[] = "(ot.class LIKE 'ot_tax%' OR ot.class LIKE 'ot_recargo%')";

	if ($status > 0) {
		$where[] = "o.orders_status = $status";
	}

	$sql = "
		SELECT ot.title, SUM(ot.value) total
		FROM " . TABLE_ORDERS_TOTAL . " ot
		JOIN " . TABLE_ORDERS . " o ON o.orders_id = ot.orders_id
		WHERE " . implode(' AND ', $where) . "
		GROUP BY ot.title
		ORDER BY ot.title
	";

	$q = tep_db_query($sql);

	echo '<table class="xform">';
	echo '<tr><th>Concepto</th><th class="tright">Importe</th></tr>';

	while ($r = tep_db_fetch_array($q)) {
		echo '<tr>';
		echo '<td>'.$r['title'].'</td>';
		echo '<td class="tright">'.number_format($r['total'],2).'</td>';
		echo '</tr>';
	}

	echo '</table>';
	exit;
}

// ======================================================
// HELPERS DB
// ======================================================

function db_table_exists($tableName)
{
	$q = tep_db_query("SHOW TABLES LIKE '" . tep_db_input($tableName) . "'");
	return tep_db_num_rows($q) > 0;
}

function db_index_exists($table, $indexName)
{
	$q = tep_db_query("SHOW INDEX FROM `" . tep_db_input($table) . "` WHERE Key_name = '" . tep_db_input($indexName) . "'");
	return tep_db_num_rows($q) > 0;
}

function db_column_exists($table, $column)
{
	$q = tep_db_query("SHOW COLUMNS FROM `" . tep_db_input($table) . "` LIKE '" . tep_db_input($column) . "'");
	return tep_db_num_rows($q) > 0;
}

function ensure_sales_stats_table_and_indexes()
{
	// 1) Tabla projector
	if (!db_table_exists('sales_stats_monthly')) {
		tep_db_query("
			CREATE TABLE sales_stats_monthly (
				year INT NOT NULL,
				month INT NOT NULL,
				orders_status_id INT NOT NULL DEFAULT 0,

				ingresos DECIMAL(15,2) NOT NULL DEFAULT 0,
				ventas DECIMAL(15,2) NOT NULL DEFAULT 0,
				impuestos DECIMAL(15,2) NOT NULL DEFAULT 0,
				envio DECIMAL(15,2) NOT NULL DEFAULT 0,
				descuento DECIMAL(15,2) NOT NULL DEFAULT 0,
				seguro DECIMAL(15,2) NOT NULL DEFAULT 0,
				puntos DECIMAL(15,2) NOT NULL DEFAULT 0,
				mediana DECIMAL(15,2) NOT NULL DEFAULT 0,
				cost DECIMAL(15,2) NOT NULL DEFAULT 0,
				pedidos INT NOT NULL DEFAULT 0,

				updated_at DATETIME NOT NULL,

				PRIMARY KEY (year, month, orders_status_id),
				KEY idx_year (year),
				KEY idx_status (orders_status_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");
	}

	// 1b) Migración: columnas seguro de envío + puntos canjeados (2026-07)
	if (!db_column_exists('sales_stats_monthly', 'seguro')) {
		tep_db_query("ALTER TABLE sales_stats_monthly ADD COLUMN seguro DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER descuento");
	}
	if (!db_column_exists('sales_stats_monthly', 'puntos')) {
		tep_db_query("ALTER TABLE sales_stats_monthly ADD COLUMN puntos DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER seguro");
	}
	if (!db_column_exists('sales_stats_monthly', 'mediana')) {
		tep_db_query("ALTER TABLE sales_stats_monthly ADD COLUMN mediana DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER puntos");
	}

	// 2) Índices recomendados (si no existen)
	// orders(date_purchased)
	if (!db_index_exists(TABLE_ORDERS, 'idx_orders_date_purchased')) {
		@tep_db_query("CREATE INDEX idx_orders_date_purchased ON " . TABLE_ORDERS . " (date_purchased)");
	}
	// orders(orders_status)
	if (!db_index_exists(TABLE_ORDERS, 'idx_orders_status')) {
		@tep_db_query("CREATE INDEX idx_orders_status ON " . TABLE_ORDERS . " (orders_status)");
	}
	// orders_total(orders_id, class)
	if (!db_index_exists(TABLE_ORDERS_TOTAL, 'idx_orders_total_order_class')) {
		@tep_db_query("CREATE INDEX idx_orders_total_order_class ON " . TABLE_ORDERS_TOTAL . " (orders_id, class)");
	}
	// orders_products(orders_id)
	if (!db_index_exists(TABLE_ORDERS_PRODUCTS, 'idx_orders_products_order')) {
		@tep_db_query("CREATE INDEX idx_orders_products_order ON " . TABLE_ORDERS_PRODUCTS . " (orders_id)");
	}
}

ensure_sales_stats_table_and_indexes();

// ======================================================
// AUTOREBUILD MES ACTUAL
// ======================================================

$qLast = tep_db_query("
	SELECT MAX(CONCAT(year, LPAD(month,2,'0'))) ym
	FROM sales_stats_monthly
	WHERE orders_status_id = 0
");
$rLast = tep_db_fetch_array($qLast);

if ((int)$rLast['ym'] < (int)date('Ym')) {
	rebuild_sales_stats_last_months(1);
}

// ======================================================
// ACTION REBUILD
// ======================================================

if ($action === 'rebuild') {
	rebuild_sales_stats_last_months($monthsBack);
	$messageStack->add('success', 'Estadísticas recalculadas.');
	tep_redirect(basename(__FILE__) . '?' . tep_get_all_get_params(['action','months']));
}

// ======================================================
// SELECTORES
// ======================================================

$aYears = [['id'=>0,'text'=>'Todos']];
$qYears = tep_db_query("SELECT DISTINCT year y FROM sales_stats_monthly WHERE orders_status_id=0 ORDER BY y DESC");
while ($r=tep_db_fetch_array($qYears)) {
	$aYears[] = ['id'=>$r['y'],'text'=>$r['y']];
}

$aMonths = [
	1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
	7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];

$aOrderStatuses = [['id'=>0,'text'=>'Todos']];
$qStatuses = tep_db_query("
	SELECT orders_status_id, orders_status_name
	FROM " . TABLE_ORDERS_STATUS . "
	WHERE language_id=".(int)$languages_id."
");
while ($r=tep_db_fetch_array($qStatuses)) {
	$aOrderStatuses[]=['id'=>$r['orders_status_id'],'text'=>$r['orders_status_name']];
}

// ======================================================
// QUERY DATOS
// ======================================================

if ($isDailyView) {

	$dateFrom = sprintf('%04d-%02d-01 00:00:00',$year,$month);
	$dateTo   = date('Y-m-t 23:59:59', strtotime($dateFrom));

	$sql = "
	SELECT
		DAY(o.date_purchased) d,
		COUNT(DISTINCT o.orders_id) pedidos,

		-- orders_total (NO se duplican)
		SUM(CASE WHEN ot.class='ot_total' THEN ot.value ELSE 0 END) ingresos,
		SUM(CASE WHEN ot.class='ot_subtotal' THEN ot.value ELSE 0 END) ventas,
		SUM(
			CASE
				WHEN ot.class LIKE 'ot_tax%'
				  OR ot.class LIKE 'ot_recargo%'
				THEN ot.value
				ELSE 0
			END
		) impuestos,
		SUM(CASE WHEN ot.class='ot_shipping' THEN ot.value ELSE 0 END) envio,
		-- las líneas ot_custom_* las crea el editor de pedidos del admin al reguardar;
		-- se reclasifican por título para que no escapen de su columna
		-- (orders_total.title es utf8mb3_bin → LIKE distingue mayúsculas; de ahí el LOWER)
		SUM(CASE WHEN ot.class LIKE 'ot_discount%'
			  OR (ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE 'cup%' OR LOWER(ot.title) LIKE 'descuento cup%'))
			THEN ot.value ELSE 0 END) descuento,
		SUM(CASE WHEN ot.class='ot_insurance'
			  OR (ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE 'seguro%' OR LOWER(ot.title) LIKE 'shipping insurance%'))
			THEN ot.value ELSE 0 END) seguro,
		SUM(CASE WHEN ot.class='ot_redemptions' THEN ot.value
			 WHEN ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE '%puntos%' OR LOWER(ot.title) LIKE 'points redeemed%') THEN -ot.value
			 ELSE 0 END) puntos,

		-- coste PREAGREGADO por pedido, contado UNA sola vez (en su fila ot_total;
		-- sin el CASE, el join con orders_total lo multiplica por el nº de líneas)
		SUM(CASE WHEN ot.class='ot_total' THEN COALESCE(c.cost,0) ELSE 0 END) cost,

		-- mediana de cesta del día (constante por día en el subquery md)
		MAX(COALESCE(md.mediana,0)) mediana

	FROM orders o

	LEFT JOIN orders_total ot
		ON ot.orders_id = o.orders_id

	LEFT JOIN (
		SELECT
			orders_id,
			SUM(products_cost * products_quantity) cost
		FROM orders_products
		GROUP BY orders_id
	) c ON c.orders_id = o.orders_id

	LEFT JOIN (
		SELECT t.d2, MAX(t.med) mediana
		FROM (
			SELECT
				DAY(o2.date_purchased) d2,
				MEDIAN(ot2.value) OVER (PARTITION BY DAY(o2.date_purchased)) med
			FROM orders o2
			JOIN orders_total ot2 ON ot2.orders_id = o2.orders_id AND ot2.class = 'ot_subtotal'
			WHERE o2.date_purchased BETWEEN '$dateFrom' AND '$dateTo'
			".($status>0?" AND o2.orders_status=".(int)$status:"")."
		) t
		GROUP BY t.d2
	) md ON md.d2 = DAY(o.date_purchased)

	WHERE o.date_purchased BETWEEN '$dateFrom' AND '$dateTo'
	".($status>0?" AND o.orders_status=".(int)$status:"")."

	GROUP BY d
	ORDER BY d ASC
";

} else {

	$where = ["orders_status_id=".(int)$status];
	if ($year>0)  $where[]="year=".(int)$year;
	if ($month>0) $where[]="month=".(int)$month;

	$sql = "
		SELECT year y, month m, ingresos, ventas, impuestos, envio, descuento, seguro, puntos, mediana, cost, pedidos
		FROM sales_stats_monthly
		WHERE ".implode(' AND ',$where)."
		ORDER BY y DESC, m DESC
	";
}

$q = tep_db_query($sql);

// ======================================================
// UI: FILTROS + BOTONES (Solenopsis)
// ======================================================

$aButtons[] = [
	'title' => 'Recalcular último mes',
	'icon'  => 'fa-refresh',
	'href'  => basename(__FILE__) . '?' . tep_get_all_get_params(['action','months']) . 'action=rebuild&months=1',
	'anchor_class' => 'verde'
];
$aButtons[] = [
	'title' => 'Recalcular últimos 3 meses',
	'icon'  => 'fa-refresh',
	'href'  => basename(__FILE__) . '?' . tep_get_all_get_params(['action','months']) . 'action=rebuild&months=3',
	'anchor_class' => 'verde'
];
$aButtons[] = [
	'title' => 'Recalcular TODO el histórico',
	'icon'  => 'fa-bomb',
	'href'  => basename(__FILE__) . '?' . tep_get_all_get_params(['action','months']) . 'action=rebuild&months=0',
	'anchor_class' => 'rojo'
];


// ======================================================
// UI FILTROS (AJUSTADO A SOLENOPSIS)
// ======================================================

$sHtml .= '<div class="oeBox column a12 row ax"><div class="oeWrpr">';
$sHtml .= '<div class="oeTitu"><i class="fa fa-filter"></i> Filtros</div>';

$sHtml .= '<div class="oeCntd">';
$sHtml .= '<form method="get" class="row ax sp20 amiddle">';

// Año
$sHtml .= '<div class="column a04">';
$sHtml .= '<label>Año</label>';
$sHtml .= '<div class="xselect">';
$sHtml .= tep_draw_pull_down_menu('year', $aYears, $year);
$sHtml .= '</div>';
$sHtml .= '</div>';

// Mes
$sHtml .= '<div class="column a04">';
$sHtml .= '<label>Mes</label>';
$sHtml .= '<select name="month" class="xform" style="width:100%">';
$sHtml .= '<option value="0">Todos</option>';
foreach ($aMonths as $id => $name) {
	$sHtml .= '<option value="'.$id.'"'.($month==$id?' selected':'').'>'.$name.'</option>';
}
$sHtml .= '</select>';
$sHtml .= '</div>';

// Estado
$sHtml .= '<div class="column a03">';
$sHtml .= '<label>Estado</label>';
$sHtml .= '<div class="xselect">';
$sHtml .= tep_draw_pull_down_menu('status', $aOrderStatuses, $status);
$sHtml .= '</div>';
$sHtml .= '</div>';

// Botón
$sHtml .= '<div class="column a01 tcenter">';
$sHtml .= '<label>&nbsp;</label>';
$sHtml .= '<button class="xbutton hv8 verde expand">';
$sHtml .= '<i class="fa fa-search"></i>';
$sHtml .= '</button>';
$sHtml .= '</div>';

$sHtml .= '</form>';
$sHtml .= '</div></div></div>';


// ======================================================
// TABLA RESULTADOS
// ======================================================

$sHtml .= '<div class="oeBox oeTable column a12 row ax"><div class="oeWrpr">';
$sHtml .= '<div class="oeTitu"><i class="fa fa-table"></i> Resultados</div><div class="oeCntd">';
$sHtml .= '<table class="xform"><thead><tr>';
$sHtml .= '<th>'.($isDailyView?'Día':'Mes').'</th>';
$sHtml .= '<th class="tright">Ingresos</th>';
$sHtml .= '<th class="tright">Ventas</th>';
$sHtml .= '<th class="tright">Coste</th>';
$sHtml .= '<th class="tright">Impuestos</th>';
$sHtml .= '<th class="tright">Envío</th>';
$sHtml .= '<th class="tright">Seguro envío</th>';
$sHtml .= '<th class="tright">Cupones</th>';
$sHtml .= '<th class="tright">Puntos (€)</th>';
$sHtml .= '<th class="tright" title="Ventas − Coste + Cupones − Puntos (sin envío ni seguro)">Beneficio</th>';
$sHtml .= '<th class="tright">Cesta mediana</th>';
$sHtml .= '<th class="tright">Pedidos</th>';
$sHtml .= '</tr></thead><tbody>';

function render_year_totals_row($year, $totals)
{
	$s  = '<tr class="dataTableHeadingRow dataTableYearTotal"><td><b>Totales del Año '.$year.'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['ingresos'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['ventas'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['cost'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['impuestos'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['envio'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['seguro'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['descuento'],2).'</b></td>';
	$s .= '<td class="tright"><b>'.number_format($totals['puntos'] == 0 ? 0 : -$totals['puntos'],2).'</b></td>';
	// Beneficio = ventas − coste + cupones(neg) − puntos; sin coste registrado (años <2023) no es calculable
	$s .= '<td class="tright"><b>'.($totals['cost'] > 0 ? number_format($totals['ventas'] - $totals['cost'] + $totals['descuento'] - $totals['puntos'],2) : '—').'</b></td>';
	$s .= '<td class="tright">—</td>';
	$s .= '<td class="tright"><b>'.$totals['orders'].'</b></td></tr>';
	return $s;
}

$currentYear=null;
$yearTotals=['ingresos'=>0,'ventas'=>0,'cost'=>0,'impuestos'=>0,'envio'=>0,'descuento'=>0,'seguro'=>0,'puntos'=>0,'orders'=>0];

while($r=tep_db_fetch_array($q)){

	if(!$isDailyView){
		if($currentYear!==null && $currentYear!=$r['y']){
			$sHtml.=render_year_totals_row($currentYear,$yearTotals);
			foreach($yearTotals as $k=>$v)$yearTotals[$k]=0;
		}
		$currentYear=$r['y'];
	}

	$basket=$r['mediana'];
	$benefit=($r['cost'] > 0) ? ($r['ventas'] - $r['cost'] + $r['descuento'] - $r['puntos']) : null;

	if(!$isDailyView){
		$yearTotals['ingresos']+=$r['ingresos'];
		$yearTotals['ventas']+=$r['ventas'];
		$yearTotals['cost']+=$r['cost'];
		$yearTotals['impuestos']+=$r['impuestos'];
		$yearTotals['envio']+=$r['envio'];
		$yearTotals['descuento']+=$r['descuento'];
		$yearTotals['seguro']+=$r['seguro'];
		$yearTotals['puntos']+=$r['puntos'];
		$yearTotals['orders']+=$r['pedidos'];
	}

	$label=$isDailyView
		? 'Día '.$r['d']
		: '<a href="'.basename(__FILE__).'?year='.$r['y'].'&month='.$r['m'].'&status='.$status.'">'.$aMonths[$r['m']].' '.$r['y'].'</a>';

	$sHtml.='<tr>';
	$sHtml.='<td>'.$label.'</td>';
	$sHtml.='<td class="tright">'.number_format($r['ingresos'],2).'</td>';
	$sHtml.='<td class="tright">'.number_format($r['ventas'],2).'</td>';
	$sHtml.='<td class="tright">'.number_format($r['cost'],2).'</td>';
	$sHtml .= '<td class="tright">';
			if ($isDailyView) {
				// Vista por DÍAS → año/mes vienen del filtro
				$sHtml .= '<span class="tax-tooltip-trigger" data-year="'.$year.'" data-month="'.$month.'" data-day="'.$r['d'].'" data-status="'.$status.'">';
			} else {
				// Vista MENSUAL → año/mes vienen del row
				$sHtml .= '<span class="tax-tooltip-trigger" data-year="'.$r['y'].'" data-month="'.$r['m'].'" data-status="'.$status.'">';
			}
			$sHtml .= number_format($r['impuestos'],2);
		$sHtml .= '</span>';
	$sHtml .= '</td>';
	$sHtml.='<td class="tright">'.number_format($r['envio'],2).'</td>';
	$sHtml.='<td class="tright">'.number_format($r['seguro'],2).'</td>';
	$sHtml.='<td class="tright">'.number_format($r['descuento'],2).'</td>';
	$sHtml.='<td class="tright">'.number_format($r['puntos'] == 0 ? 0 : -$r['puntos'],2).'</td>';
	$sHtml.='<td class="tright">'.($benefit === null ? '—' : number_format($benefit,2)).'</td>';
	$sHtml.='<td class="tright">'.number_format($basket,2).'</td>';
	$sHtml.='<td class="tright">'.$r['pedidos'].'</td>';
	$sHtml.='</tr>';
}

if(!$isDailyView && $currentYear!==null){
	$sHtml.=render_year_totals_row($currentYear,$yearTotals);
}

$sHtml.='</tbody></table></div></div></div>';

// ======================================================
// SALIDA
// ======================================================

$sHtmlModuleOe=$sHtml;
$sMessageStack=$messageStack->output(false);
$messageStack->reset();

include('theme/solenopsis/html/header.php');
?>
<style>
.dataTableYearTotal {background: #4f4f4f !important;color: #fff;}
.tax-tooltip {
	position: absolute;
	background: #fff;
	border: 1px solid #ccc;
	box-shadow: 0 4px 10px rgba(0,0,0,.15);
	padding: 10px;
	min-width: 220px;
	z-index: 9999;
	font-size: 13px;
}

.tax-tooltip table {
	width: 100%;
}

.tax-tooltip th {
	text-align: left;
	font-weight: 600;
	border-bottom: 1px solid #ddd;
	padding-bottom: 4px;
}

.tax-tooltip td {
	padding: 3px 0;
}

.tax-tooltip .tright {
	text-align: right;
}

.tax-tooltip-trigger {
	cursor: pointer;
	color: #1779ba;
	text-decoration: underline;
}
</style>
<?php
// Cabecera Solenopsis + botones
echo '<div class="oeHead column a12 row ax amiddle aflex">';
echo '<div class="oeTitu column logo afixed"><b><i class="fa fa-chart-line"></i> ' . $sTitle . '</b><small>' . $sSubtitle . '</small></div>';
echo '<div class="oeButton column dtright">';
foreach ($aButtons as $aButton) {
	echo '<a class="xbutton hv8 small ' . ($aButton['anchor_class'] ?? '') . '" href="' . $aButton['href'] . '"><i class="fa ' . $aButton['icon'] . '"></i> ' . $aButton['title'] . '</a> ';
}
echo '</div>';
echo '</div>';

echo $sMessageStack;
echo $sHtmlModuleOe;

include('theme/solenopsis/html/footer.php');
?>
<script>
	(function(){

		let tooltip;

		function removeTooltip(){
			if(tooltip){
				tooltip.remove();
				tooltip = null;
			}
		}

		document.addEventListener('click', function(e){

			const trigger = e.target.closest('.tax-tooltip-trigger');
			if(!trigger){
				removeTooltip();
				return;
			}

			e.stopPropagation();
			removeTooltip();

			const year   = trigger.dataset.year;
			const month  = trigger.dataset.month;
			const status = trigger.dataset.status;

			tooltip = document.createElement('div');
			tooltip.className = 'tax-tooltip';
			tooltip.innerHTML = 'Cargando...';
			document.body.appendChild(tooltip);

			const rect = trigger.getBoundingClientRect();
			tooltip.style.top  = (rect.bottom + window.scrollY + 5) + 'px';
			tooltip.style.left = (rect.left + window.scrollX) + 'px';

			const day = trigger.dataset.day || '';

			let url = 'stats_monthly_sales.php?ajax=tax_breakdown'
				+ '&year=' + year
				+ '&month=' + month
				+ '&status=' + status;

			if (day) {
				url += '&day=' + day;
			}

			fetch(url)
				.then(r => r.text())
				.then(html => tooltip.innerHTML = html);
		});

	})();
</script>
<?php
require(DIR_WS_INCLUDES.'application_bottom.php');

/**
 * Recalcula la proyección para los últimos X meses.
 * - monthsBack = 0 → TODO el histórico
 * - monthsBack > 0 → últimos X meses
 */
function rebuild_sales_stats_last_months($monthsBack)
{
	if ($monthsBack === 0) {
		$startDate = '2000-01-01 00:00:00'; // fecha mínima real
	} else {
		$startDate = date('Y-m-01 00:00:00', strtotime('-'.(int)$monthsBack.' months'));
	}

	$endDate = date('Y-m-d 23:59:59');

	tep_db_query('START TRANSACTION');

	// Borramos los meses afectados
	tep_db_query("
		DELETE FROM sales_stats_monthly
		WHERE STR_TO_DATE(
			CONCAT(year,'-',LPAD(month,2,'0'),'-01'),
			'%Y-%m-%d'
		) >= '".tep_db_input(substr($startDate,0,10))."'
	");

	// Subquery de coste por pedido (evita duplicados)
	$costSubquery = "
		SELECT orders_id, SUM(products_cost * products_quantity) cost
		FROM ".TABLE_ORDERS_PRODUCTS."
		GROUP BY orders_id
	";

	// ===== TODOS LOS ESTADOS (orders_status_id = 0)
	$sqlAll = "
		REPLACE INTO sales_stats_monthly
		(year,month,orders_status_id,ingresos,ventas,impuestos,envio,descuento,seguro,puntos,cost,pedidos,updated_at)
		SELECT
			YEAR(o.date_purchased),
			MONTH(o.date_purchased),
			0,
			SUM(CASE WHEN ot.class='ot_total' THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_subtotal' THEN ot.value ELSE 0 END),
			SUM(CASE
				WHEN ot.class LIKE 'ot_tax%'
				  OR ot.class LIKE 'ot_recargo%'
				THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_shipping' THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class LIKE 'ot_discount%'
				  OR (ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE 'cup%' OR LOWER(ot.title) LIKE 'descuento cup%'))
				THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_insurance'
				  OR (ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE 'seguro%' OR LOWER(ot.title) LIKE 'shipping insurance%'))
				THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_redemptions' THEN ot.value
				 WHEN ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE '%puntos%' OR LOWER(ot.title) LIKE 'points redeemed%') THEN -ot.value
				 ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_total' THEN COALESCE(c.cost,0) ELSE 0 END),
			COUNT(DISTINCT o.orders_id),
			NOW()
		FROM ".TABLE_ORDERS." o
		LEFT JOIN ".TABLE_ORDERS_TOTAL." ot ON ot.orders_id=o.orders_id
		LEFT JOIN ($costSubquery) c ON c.orders_id=o.orders_id
		WHERE o.date_purchased BETWEEN '".tep_db_input($startDate)."' AND '".tep_db_input($endDate)."'
		GROUP BY YEAR(o.date_purchased), MONTH(o.date_purchased)
	";
	tep_db_query($sqlAll);

	// ===== POR CADA ESTADO (para filtro rápido)
	$sqlByStatus = "
		REPLACE INTO sales_stats_monthly
		(year,month,orders_status_id,ingresos,ventas,impuestos,envio,descuento,seguro,puntos,cost,pedidos,updated_at)
		SELECT
			YEAR(o.date_purchased),
			MONTH(o.date_purchased),
			o.orders_status,
			SUM(CASE WHEN ot.class='ot_total' THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_subtotal' THEN ot.value ELSE 0 END),
			SUM(CASE
				WHEN ot.class LIKE 'ot_tax%'
				  OR ot.class LIKE 'ot_recargo%'
				THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_shipping' THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class LIKE 'ot_discount%'
				  OR (ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE 'cup%' OR LOWER(ot.title) LIKE 'descuento cup%'))
				THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_insurance'
				  OR (ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE 'seguro%' OR LOWER(ot.title) LIKE 'shipping insurance%'))
				THEN ot.value ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_redemptions' THEN ot.value
				 WHEN ot.class LIKE 'ot_custom%' AND (LOWER(ot.title) LIKE '%puntos%' OR LOWER(ot.title) LIKE 'points redeemed%') THEN -ot.value
				 ELSE 0 END),
			SUM(CASE WHEN ot.class='ot_total' THEN COALESCE(c.cost,0) ELSE 0 END),
			COUNT(DISTINCT o.orders_id),
			NOW()
		FROM ".TABLE_ORDERS." o
		LEFT JOIN ".TABLE_ORDERS_TOTAL." ot ON ot.orders_id=o.orders_id
		LEFT JOIN ($costSubquery) c ON c.orders_id=o.orders_id
		WHERE o.date_purchased BETWEEN '".tep_db_input($startDate)."' AND '".tep_db_input($endDate)."'
		GROUP BY YEAR(o.date_purchased), MONTH(o.date_purchased), o.orders_status
	";
	tep_db_query($sqlByStatus);

	// ===== MEDIANA de cesta (subtotal por pedido) — no derivable de agregados,
	// se calcula aparte con función de ventana y se vuelca sobre los meses reconstruidos
	$sqlMedianaAll = "
		UPDATE sales_stats_monthly s
		JOIN (
			SELECT t.y, t.m, MAX(t.med) med
			FROM (
				SELECT
					YEAR(o.date_purchased) y,
					MONTH(o.date_purchased) m,
					MEDIAN(ot.value) OVER (PARTITION BY YEAR(o.date_purchased), MONTH(o.date_purchased)) med
				FROM ".TABLE_ORDERS." o
				JOIN ".TABLE_ORDERS_TOTAL." ot ON ot.orders_id=o.orders_id AND ot.class='ot_subtotal'
				WHERE o.date_purchased BETWEEN '".tep_db_input($startDate)."' AND '".tep_db_input($endDate)."'
			) t
			GROUP BY t.y, t.m
		) x ON x.y=s.year AND x.m=s.month
		SET s.mediana = x.med
		WHERE s.orders_status_id=0
	";
	tep_db_query($sqlMedianaAll);

	$sqlMedianaByStatus = "
		UPDATE sales_stats_monthly s
		JOIN (
			SELECT t.y, t.m, t.st, MAX(t.med) med
			FROM (
				SELECT
					YEAR(o.date_purchased) y,
					MONTH(o.date_purchased) m,
					o.orders_status st,
					MEDIAN(ot.value) OVER (PARTITION BY YEAR(o.date_purchased), MONTH(o.date_purchased), o.orders_status) med
				FROM ".TABLE_ORDERS." o
				JOIN ".TABLE_ORDERS_TOTAL." ot ON ot.orders_id=o.orders_id AND ot.class='ot_subtotal'
				WHERE o.date_purchased BETWEEN '".tep_db_input($startDate)."' AND '".tep_db_input($endDate)."'
			) t
			GROUP BY t.y, t.m, t.st
		) x ON x.y=s.year AND x.m=s.month AND x.st=s.orders_status_id
		SET s.mediana = x.med
		WHERE s.orders_status_id>0
	";
	tep_db_query($sqlMedianaByStatus);

	tep_db_query('COMMIT');
}
