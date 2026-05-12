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
				cost DECIMAL(15,2) NOT NULL DEFAULT 0,
				pedidos INT NOT NULL DEFAULT 0,

				updated_at DATETIME NOT NULL,

				PRIMARY KEY (year, month, orders_status_id),
				KEY idx_year (year),
				KEY idx_status (orders_status_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");
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
		SUM(CASE WHEN ot.class LIKE 'ot_discount%' THEN ot.value ELSE 0 END) descuento,

		-- coste PREAGREGADO por pedido
		SUM(COALESCE(c.cost,0)) cost

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
		SELECT year y, month m, ingresos, ventas, impuestos, envio, descuento, cost, pedidos
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
$sHtml .= '<th class="tright">Descuento</th>';
$sHtml .= '<th class="tright">Beneficio</th>';
$sHtml .= '<th class="tright">Cesta media</th>';
$sHtml .= '<th class="tright">Pedidos</th>';
$sHtml .= '</tr></thead><tbody>';

$currentYear=null;
$yearTotals=['ingresos'=>0,'ventas'=>0,'cost'=>0,'impuestos'=>0,'envio'=>0,'descuento'=>0,'orders'=>0];

while($r=tep_db_fetch_array($q)){

	if(!$isDailyView){
		if($currentYear!==null && $currentYear!=$r['y']){
			$sHtml.='<tr class="dataTableHeadingRow dataTableYearTotal"><td><b>Totales del Año '.$currentYear.'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['ingresos'],2).'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['ventas'],2).'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['cost'],2).'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['impuestos'],2).'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['envio'],2).'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['descuento'],2).'</b></td>';
			$sHtml.='<td class="tright"><b>'.number_format($yearTotals['ventas']-$yearTotals['cost']-$yearTotals['descuento'],2).'</b></td>';
			$sHtml.='<td class="tright">—</td>';
			$sHtml.='<td class="tright"><b>'.$yearTotals['orders'].'</b></td></tr>';
			foreach($yearTotals as $k=>$v)$yearTotals[$k]=0;
		}
		$currentYear=$r['y'];
	}

	$benefit=$r['ventas']-$r['cost']+$r['descuento'];
	$basket=$r['pedidos']?($r['ventas']/$r['pedidos']):0;

	if(!$isDailyView){
		$yearTotals['ingresos']+=$r['ingresos'];
		$yearTotals['ventas']+=$r['ventas'];
		$yearTotals['cost']+=$r['cost'];
		$yearTotals['impuestos']+=$r['impuestos'];
		$yearTotals['envio']+=$r['envio'];
		$yearTotals['descuento']+=$r['descuento'];
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
	$sHtml.='<td class="tright">'.number_format($r['descuento'],2).'</td>';
	$sHtml.='<td class="tright">'.number_format($benefit,2).'</td>';
	$sHtml.='<td class="tright">'.number_format($basket,2).'</td>';
	$sHtml.='<td class="tright">'.$r['pedidos'].'</td>';
	$sHtml.='</tr>';
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
		(year,month,orders_status_id,ingresos,ventas,impuestos,envio,descuento,cost,pedidos,updated_at)
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
			SUM(CASE WHEN ot.class LIKE 'ot_discount%' THEN ot.value ELSE 0 END),
			SUM(COALESCE(c.cost,0)),
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
		(year,month,orders_status_id,ingresos,ventas,impuestos,envio,descuento,cost,pedidos,updated_at)
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
			SUM(CASE WHEN ot.class LIKE 'ot_discount%' THEN ot.value ELSE 0 END),
			SUM(COALESCE(c.cost,0)),
			COUNT(DISTINCT o.orders_id),
			NOW()
		FROM ".TABLE_ORDERS." o
		LEFT JOIN ".TABLE_ORDERS_TOTAL." ot ON ot.orders_id=o.orders_id
		LEFT JOIN ($costSubquery) c ON c.orders_id=o.orders_id
		WHERE o.date_purchased BETWEEN '".tep_db_input($startDate)."' AND '".tep_db_input($endDate)."'
		GROUP BY YEAR(o.date_purchased), MONTH(o.date_purchased), o.orders_status
	";
	tep_db_query($sqlByStatus);

	tep_db_query('COMMIT');
}
