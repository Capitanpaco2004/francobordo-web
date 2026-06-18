<?php
/**
 * Modulo Fecha Estimada de Entrega — clase FRONTEND (runtime)
 *
 * Sólo contiene lo que necesitan checkout y cuenta de cliente:
 *   - Cálculo automático al crear pedido (calculateForOrder)
 *   - Consulta de la fecha vigente de un pedido (getCurrent, static)
 *
 * La lógica de instalación, edición manual y envío de email vive en la clase admin:
 *   _admin/includes/modules/delivery_estimate/classes/delivery_estimate_admin.php
 *
 * Reglas (configurables desde admin):
 *   - Todos los productos con stock > 0          -> fecha_compra + DAYS_IN_STOCK  (defecto 2)
 *   - Algun producto con stock < 0 y > -800      -> fecha_compra + DAYS_NO_STOCK  (defecto 14)
 *   - Algun producto con stock = -800            -> fecha_compra + DAYS_BACKORDER (defecto 30)
 *   - Cantidad solicitada > stock disponible     -> DAYS_NO_STOCK
 *
 * Dias naturales o laborables configurable (DELIVERY_ESTIMATE_BUSINESS_DAYS).
 */
class delivery_estimate {

	/**
	 * Calcula la fecha estimada inicial de un pedido y la guarda en la tabla.
	 * Llamado desde checkout_process.php tras crear el pedido.
	 *
	 * Devuelve false (sin tocar nada) si el módulo no está activo o la tabla aún no existe
	 * — así el checkout puede llamar directamente sin check previo.
	 */
	public function calculateForOrder( $orders_id ) {

		$orders_id = (int)$orders_id;
		if( $orders_id <= 0 )
			return false;

		if( ! defined('DELIVERY_ESTIMATE_ENABLED') || constant('DELIVERY_ESTIMATE_ENABLED') != 'True' )
			return false;

		// Tabla de histórico (la crea el admin). Si no existe aún, salimos.
		$check = tep_db_query( "show tables like 'orders_delivery_estimate'" );
		if( tep_db_num_rows( $check ) == 0 )
			return false;

		$sql = tep_db_query( "select date_purchased, shipping_module from " . TABLE_ORDERS . " where orders_id = '" . $orders_id . "'" );
		if( tep_db_num_rows( $sql ) == 0 )
			return false;
		$row = tep_db_fetch_array( $sql );
		$datePurchased = $row['date_purchased'];

		// SEUR 13:30 ('seurnacional') y SEUR 10 ('seurdiez'): entrega al SIGUIENTE DIA HABIL
		// (24h), independientemente del stock. Ambos solo se ofrecen L-V; SEUR no entrega en
		// sabado salvo servicio complementario (no contratado), asi que un pedido del VIERNES
		// se entrega el LUNES. addDays(,1) salta sabados/domingos y festivos (BUSINESS_DAYS=True)
		// -> viernes->lunes, jueves->viernes. Las reglas de stock de abajo no aplican.
		$sm = (string)$row['shipping_module'];
		if( strpos( $sm, 'seurnacional' ) === 0 || strpos( $sm, 'seurdiez' ) === 0 ) {
			$estimated = $this->addDays( $datePurchased, 1 );
			$rule = ( strpos( $sm, 'seurdiez' ) === 0 ) ? 'seur10_24h' : 'seur1330_24h';
			tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', '" . tep_db_input( $rule ) . "', NULL, 0, NULL, 0, now())" );
			return $estimated;
		}

		$sqlP = tep_db_query( "select orders_products_id, products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $orders_id . "'" );

		$rule        = 'stock_ok';
		$daysInStock = (int)( defined('DELIVERY_ESTIMATE_DAYS_IN_STOCK')  ? DELIVERY_ESTIMATE_DAYS_IN_STOCK  : 2 );
		$daysNoStock = (int)( defined('DELIVERY_ESTIMATE_DAYS_NO_STOCK')  ? DELIVERY_ESTIMATE_DAYS_NO_STOCK  : 14 );
		$daysBackord = (int)( defined('DELIVERY_ESTIMATE_DAYS_BACKORDER') ? DELIVERY_ESTIMATE_DAYS_BACKORDER : 30 );

		$daysToAdd = $daysInStock;

		while( $prod = tep_db_fetch_array( $sqlP ) ) {

			$pid = (int)$prod['products_id'];
			$qty = (int)$prod['products_quantity'];

			$aux = tep_db_query( "select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . $pid . "'" );
			if( tep_db_num_rows( $aux ) == 0 )
				continue;
			$aux = tep_db_fetch_array( $aux );
			// El stock leído ya viene descontado por checkout_process.php (corre antes que este cálculo).
			// Sumamos la cantidad de este pedido para evaluar contra el stock que había en el momento de la compra.
			// Sin esto, una compra que vacía stock (qty == stock previo) dispararía falso "no_stock".
			$stock = (int)$aux['products_quantity'] + $qty;

			// Stock por atributos (combinacion)
			// Sólo consultamos products_stock si tenemos IDs reales de option/value.
			// En oscDenox legacy hay pedidos con products_options_id y products_options_values_id a 0
			// (el ID real está en NIDATRIB). En esos casos hay riesgo de matchear filas residuales
			// con stock_attributes='0-0' y stock negativo, lo que dispararía falsos positivos
			// de no_stock. Si no tenemos IDs válidos, fallback al stock general de la tabla products.
			$attrs = tep_db_query( "select products_options_id, products_options_values_id from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . $orders_id . "' and orders_products_id = '" . (int)$prod['orders_products_id'] . "'" );
			if( tep_db_num_rows( $attrs ) > 0 ) {
				$parts = array();
				while( $a = tep_db_fetch_array( $attrs ) ) {
					$oid = (int)$a['products_options_id'];
					$vid = (int)$a['products_options_values_id'];
					if( $oid <= 0 || $vid <= 0 ) continue;
					$parts[] = $oid . '-' . $vid;
				}
				if( ! empty( $parts ) ) {
					$attrKey = implode( ',', $parts );

					$s2 = tep_db_query( "select products_stock_quantity from " . TABLE_PRODUCTS_STOCK . " where products_id = '" . $pid . "' and products_stock_attributes = '" . tep_db_input( $attrKey ) . "'" );
					if( tep_db_num_rows( $s2 ) > 0 ) {
						$s2 = tep_db_fetch_array( $s2 );
						// Igual que con el stock general: revertimos el descuento del propio pedido.
						$stock = (int)$s2['products_stock_quantity'] + $qty;
					}
				}
			}

			$effective = $daysInStock;
			$effRule   = 'stock_ok';

			if( $stock == -800 ) {
				$effective = $daysBackord;
				$effRule   = 'backorder';
			}
			elseif( $stock < 0 && $stock > -800 ) {
				$effective = $daysNoStock;
				$effRule   = 'no_stock';
			}
			elseif( $qty > $stock ) {
				$effective = $daysNoStock;
				$effRule   = 'no_stock';
			}

			if( $effective > $daysToAdd ) {
				$daysToAdd = $effective;
				$rule      = $effRule;
			}
		}

		$estimated = $this->addDays( $datePurchased, $daysToAdd );

		tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', '" . tep_db_input( $rule ) . "', NULL, 0, NULL, 0, now())" );

		return $estimated;
	}

	/**
	 * Devuelve la fecha estimada vigente (ultima fila) de un pedido, o null.
	 * Usado por frontend (account/account_history, account_history_info) y por admin (orders.php).
	 *
	 * Si no existe fila para el pedido y el módulo está activo, la genera lazy llamando
	 * a calculateForOrder — así los pedidos que no pasaron por el hook de checkout quedan
	 * cubiertos la primera vez que alguien consulta su fecha estimada.
	 */
	public static function getCurrent( $orders_id ) {
		$orders_id = (int)$orders_id;
		if( $orders_id <= 0 )
			return null;

		$check = tep_db_query( "show tables like 'orders_delivery_estimate'" );
		if( tep_db_num_rows( $check ) == 0 )
			return null;

		$q = tep_db_query( "select estimated_date, rule_applied, comment, is_manual, created_at from orders_delivery_estimate where orders_id = '" . $orders_id . "' order by created_at desc, delivery_estimate_id desc limit 1" );

		// Lazy backfill: si no hay fila y el módulo está activo, calculamos ahora
		if( tep_db_num_rows( $q ) == 0 && defined('DELIVERY_ESTIMATE_ENABLED') && constant('DELIVERY_ESTIMATE_ENABLED') == 'True' ) {
			$instance = new self();
			if( $instance->calculateForOrder( $orders_id ) !== false ) {
				$q = tep_db_query( "select estimated_date, rule_applied, comment, is_manual, created_at from orders_delivery_estimate where orders_id = '" . $orders_id . "' order by created_at desc, delivery_estimate_id desc limit 1" );
			}
		}

		if( tep_db_num_rows( $q ) == 0 )
			return null;

		return tep_db_fetch_array( $q );
	}

	// ---------------------------------------------------------------
	// Helpers internos
	// ---------------------------------------------------------------

	private function addDays( $fromDate, $days ) {

		$businessDays = ( defined('DELIVERY_ESTIMATE_BUSINESS_DAYS') && constant('DELIVERY_ESTIMATE_BUSINESS_DAYS') == 'True' );

		$ts = strtotime( (string)$fromDate );
		if( $ts === false )
			$ts = time();

		if( ! $businessDays )
			return date( 'Y-m-d', strtotime( '+' . (int)$days . ' days', $ts ) );

		// Precargamos festivos del calendario (shipping_prediction_calendar) si existe.
		// Saltamos sábados, domingos y festivos nacionales/personales por igual.
		$holidays = $this->loadHolidays( $ts, (int)$days );

		$added  = 0;
		$cursor = $ts;
		while( $added < $days ) {
			$cursor = strtotime( '+1 day', $cursor );
			$dow = (int)date( 'N', $cursor ); // 1=Lunes ... 7=Domingo
			if( $dow >= 6 ) continue; // sábado o domingo
			$ymd = date( 'Y-m-d', $cursor );
			if( isset( $holidays[$ymd] ) ) continue; // festivo
			$added++;
		}
		return date( 'Y-m-d', $cursor );
	}

	/**
	 * Carga festivos relevantes (nacionales + personales) de `shipping_prediction_calendar`
	 * para el rango que se va a recorrer. Resuelve campos NULL en calendar_year/calendar_month
	 * como "se repite cada año" / "se repite cada mes".
	 *
	 * Devuelve ['YYYY-MM-DD' => true, ...] o [] si la tabla no existe.
	 */
	private function loadHolidays( $fromTs, $daysToAdd ) {
		$out = array();

		// Tabla opcional — si no existe, devolvemos array vacío (comportamiento igual al anterior)
		$check = tep_db_query( "show tables like 'shipping_prediction_calendar'" );
		if( tep_db_num_rows( $check ) == 0 )
			return $out;

		// Holgura: al contar días laborables podemos saltarnos muchos festivos seguidos.
		// Proyectamos festivos en un rango amplio (daysToAdd * 3 + 30) para cubrir Nochebuena, Semana Santa, etc.
		$endTs   = strtotime( '+' . ( max( 30, (int)$daysToAdd * 3 + 30 ) ) . ' days', $fromTs );
		$startY  = (int)date( 'Y', $fromTs );
		$endY    = (int)date( 'Y', $endTs );

		// Traemos sólo nacionales + personales (autonómicos/locales dependen de provincia del pedido y quedan para Fase 2)
		$q = tep_db_query( "select calendar_day, calendar_month, calendar_year from shipping_prediction_calendar where calendar_type in ('national','personal')" );
		while( $r = tep_db_fetch_array( $q ) ) {
			$d = (int)$r['calendar_day'];
			if( $d < 1 || $d > 31 ) continue;

			// Años: si es NULL / vacío → se repite cada año del rango
			$years = array();
			if( $r['calendar_year'] === null || $r['calendar_year'] === '' || (int)$r['calendar_year'] === 0 ) {
				for( $y = $startY; $y <= $endY; $y++ ) $years[] = $y;
			}
			else {
				$years[] = (int)$r['calendar_year'];
			}

			// Meses: si es NULL / vacío → se repite cada mes
			$months = array();
			if( $r['calendar_month'] === null || $r['calendar_month'] === '' || (int)$r['calendar_month'] === 0 ) {
				for( $m = 1; $m <= 12; $m++ ) $months[] = $m;
			}
			else {
				$m = (int)$r['calendar_month'];
				if( $m >= 1 && $m <= 12 ) $months[] = $m;
			}

			// Generamos las fechas concretas (validando con checkdate para evitar 30-feb, etc.)
			foreach( $years as $yy ) {
				foreach( $months as $mm ) {
					if( checkdate( $mm, $d, $yy ) ) {
						$out[ sprintf( '%04d-%02d-%02d', $yy, $mm, $d ) ] = true;
					}
				}
			}
		}

		return $out;
	}
}
