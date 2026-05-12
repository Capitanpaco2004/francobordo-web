<?php
// Total de opiniones
$aDatos = tep_db_query('SELECT COUNT(id_opinion) AS cantidad FROM opinion WHERE status_aprobado = "true"');
$aDatos = tep_db_fetch_array($aDatos);
$nCantidad = $aDatos['cantidad'];

if ($nCantidad >= SISTEMA_OPINION_BOX_CANTIDAD) {
	// Suma total de puntos
	$aDatos = tep_db_query('SELECT SUM(general) AS suma FROM opinion WHERE status_aprobado = "true"');
	$aDatos = tep_db_fetch_array($aDatos);
	$nTotalPuntos = $aDatos['suma'];

	if ($nCantidad > 0) {
		// Obtenemos el porcentaje
		$nPorcentaje = round(($nTotalPuntos * 5) / ($nCantidad * 5), 1);
		$nRating = round(($nTotalPuntos * 5) / ($nCantidad * 5), 0);
	} else {
		// Manejar división entre 0
		$nPorcentaje = 0;
		$nRating = 0;
	}

	// Obtenemos una opinión al azar
	$aSqlOpinions = tep_db_query('SELECT op.comentario_general, op.general, o.customers_name
                                FROM opinion op
                                LEFT JOIN orders o ON (op.orders_id = o.orders_id)
                                WHERE op.status_aprobado = true
                                ORDER BY RAND() LIMIT 3');

	include DIR_THEME . 'html/components/' . basename(__FILE__);
}
?>
