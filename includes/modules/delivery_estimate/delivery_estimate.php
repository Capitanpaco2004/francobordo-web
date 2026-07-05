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

		$sql = tep_db_query( "select date_purchased, shipping_module, delivery_postcode, delivery_state, delivery_country from " . TABLE_ORDERS . " where orders_id = '" . $orders_id . "'" );
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
		// SEUR Sábado ('seursabado'): solo se ofrece el VIERNES -> entrega el dia SIGUIENTE = SABADO
		// (el complemento 'Entrega en Sabado' lo permite). +1 NATURAL (no dia habil).
		if( strpos( $sm, 'seursabado' ) === 0 ) {
			$estimated = date( 'Y-m-d', strtotime( '+1 day', strtotime( $datePurchased ) ) );
			tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', 'seursabado_sat', NULL, 0, NULL, 0, now())" );
			return $estimated;
		}
		// SEUR 24 ('seur48' = B2C 31/2): entrega en 24h = +1 dia habil.
		if( strpos( $sm, 'seur48' ) === 0 ) {
			$estimated = $this->addDays( $datePurchased, 1 );
			tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', 'seur24_24h', NULL, 0, NULL, 0, now())" );
			return $estimated;
		}
		if( strpos( $sm, 'seurnacional' ) === 0 || strpos( $sm, 'seurdiez' ) === 0 ) {
			$estimated = $this->addDays( $datePurchased, 1 );
			$rule = ( strpos( $sm, 'seurdiez' ) === 0 ) ? 'seur10_24h' : 'seur1330_24h';
			tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', '" . tep_db_input( $rule ) . "', NULL, 0, NULL, 0, now())" );
			return $estimated;
		}

		$sqlP = tep_db_query( "select orders_products_id, products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $orders_id . "'" );

		// -------------------------------------------------------------
		// FASE 1 refactor plazos: bifurcacion por feature flag.
		// Con DELIVERY_ESTIMATE_UNIFIED ausente o != 'True' se ejecuta el
		// camino legacy INTACTO (else de mas abajo). Solo con el flag ON se
		// usa el nucleo canonico (leadTimeForProduct / transitDaysForCp).
		// -------------------------------------------------------------
		$unified = ( defined('DELIVERY_ESTIMATE_UNIFIED') && constant('DELIVERY_ESTIMATE_UNIFIED') == 'True' );

		if( $unified ) {
			$destCp   = isset( $row['delivery_postcode'] ) ? (string)$row['delivery_postcode'] : '';
			$destProv = isset( $row['delivery_state'] ) ? $row['delivery_state'] : null;

			$maxDispatch = 0;
			$anyUnavailable = false;
			$anyBackorder = false;
			$uRule = 'stock_ok';

			while( $prod = tep_db_fetch_array( $sqlP ) ) {
				$pid = (int)$prod['products_id'];
				$qty = (int)$prod['products_quantity'];

				// Reconstruir attrKey (mismo formato que products_stock_attributes: 'oid-vid[,oid-vid]')
				$attrKey = null;
				$attrs = tep_db_query( "select products_options_id, products_options_values_id from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . $orders_id . "' and orders_products_id = '" . (int)$prod['orders_products_id'] . "'" );
				if( tep_db_num_rows( $attrs ) > 0 ) {
					$parts = array();
					while( $a = tep_db_fetch_array( $attrs ) ) {
						$oid = (int)$a['products_options_id'];
						$vid = (int)$a['products_options_values_id'];
						if( $oid <= 0 || $vid <= 0 ) continue;
						$parts[] = $oid . '-' . $vid;
					}
					if( ! empty( $parts ) )
						$attrKey = implode( ',', $parts );
				}

				// MUST-FIX #2: el modulo B (checkout) lee stock YA decrementado -> stockDecremented=TRUE
				// para clasificar sobre (rawStock + qty) = stock en el momento de la compra.
				$lt = self::leadTimeForProduct( $pid, $qty, '', $attrKey, $destProv, true );

				if( (int)$lt['dispatch_days'] > $maxDispatch )
					$maxDispatch = (int)$lt['dispatch_days'];
				if( ! $lt['available'] )
					$anyUnavailable = true;
				// -800 bajo pedido: el pedido queda SIN plazo definido de entrega (no damos fecha).
				if( isset( $lt['category'] ) && $lt['category'] == 'backorder' )
					$anyBackorder = true;
				if( isset( $lt['rule'] ) && $lt['rule'] != 'stock_ok' && $uRule == 'stock_ok' )
					$uRule = $lt['rule'];
			}

			if( $anyUnavailable ) {
				$estimated = '0000-00-00';
				$uRule = 'unavailable';
				tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', '" . tep_db_input( $uRule ) . "', 'Producto no disponible / bajo consulta', 0, NULL, 0, now())" );
				return $estimated;
			}

			// Bajo pedido (-800): el pedido NO recibe una fecha estimada concreta. Guardamos
			// estimated '0000-00-00' (los consumidores lo tratan como "sin fecha") con regla 'backorder'.
			if( $anyBackorder ) {
				$estimated = '0000-00-00';
				$uRule = 'backorder';
				tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', '" . tep_db_input( $uRule ) . "', 'Producto bajo pedido — sin plazo definido de entrega', 0, NULL, 0, now())" );
				return $estimated;
			}

			$transit   = self::transitDaysForCp( $destCp, $destProv );

			// Regla de despacho: si TODO el pedido esta en stock, se prepara y ENVIA el mismo dia
			// cuando el pedido entra en dia laborable antes del corte (DELIVERY_ESTIMATE_CUTOFF_HOUR,
			// def. 16h); si entra tras el corte o en finde/festivo -> siguiente dia laborable. Si hay
			// algun producto con plazo (bajo pedido/proveedor/sin stock) se suman esos dias habiles.
			if( $uRule == 'stock_ok' )
				$estimated = $this->nextShipSlot( $datePurchased, $destProv );
			else
				$estimated = $this->addDays( $datePurchased, (int)$maxDispatch, $destProv );

			// El transito (dias de ruta del transportista) se suma en dias NATURALES.
			if( $transit > 0 )
				$estimated = date( 'Y-m-d', strtotime( '+' . (int)$transit . ' days', strtotime( $estimated ) ) );

			tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated ) . "', '" . tep_db_input( $uRule ) . "', NULL, 0, NULL, 0, now())" );

			return $estimated;
		}

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
	// ===============================================================
	// NUCLEO CANONICO (Fase 1) — metodos estaticos ADITIVOS.
	// Solo se invocan desde la rama unified (flag ON) y desde
	// ShippingEstimator::buildTextForProduct (flag ON). Con flag OFF
	// nunca se llaman: comportamiento byte-identico al actual.
	// ===============================================================

	/**
	 * Clasifica por el centinela CRUDO de stock. Devuelve
	 * ['dispatch_days','rule','available','category'].
	 */
	public static function classifyStock( $rawStock, $qty = 1 ) {
		$rawStock = (int)$rawStock;
		$qty      = (int)$qty;

		$dOnDemand = (int)( defined('DELIVERY_ESTIMATE_DAYS_ON_DEMAND') ? DELIVERY_ESTIMATE_DAYS_ON_DEMAND : ( defined('DELIVERY_ESTIMATE_DAYS_BACKORDER') ? DELIVERY_ESTIMATE_DAYS_BACKORDER : 30 ) );
		$dBackord  = (int)( defined('DELIVERY_ESTIMATE_DAYS_BACKORDER') ? DELIVERY_ESTIMATE_DAYS_BACKORDER : 30 );
		$dNoStock  = (int)( defined('DELIVERY_ESTIMATE_DAYS_NO_STOCK')  ? DELIVERY_ESTIMATE_DAYS_NO_STOCK  : 14 );
		$dInStock  = (int)( defined('DELIVERY_ESTIMATE_DAYS_IN_STOCK')  ? DELIVERY_ESTIMATE_DAYS_IN_STOCK  : 2 );

		// Centinela on_demand: disponible, se sirve bajo pedido corto.
		if( $rawStock == 2000 )
			return array( 'dispatch_days' => $dOnDemand, 'rule' => 'on_demand', 'available' => true, 'category' => 'on_demand' );

		// Descatalogado: NO disponible.
		if( $rawStock <= -900 && $rawStock >= -901 )
			return array( 'dispatch_days' => 0, 'rule' => 'discontinued', 'available' => false, 'category' => 'discontinued' );

		// Backorder clasico.
		if( $rawStock <= -800 && $rawStock >= -899 )
			return array( 'dispatch_days' => $dBackord, 'rule' => 'backorder', 'available' => true, 'category' => 'backorder' );

		// Backorder (multiplos).
		if( $rawStock <= -1500 && $rawStock >= -1699 )
			return array( 'dispatch_days' => $dBackord, 'rule' => 'backorder', 'available' => true, 'category' => 'backorder' );

		// Proveedor.
		if( $rawStock <= -100 && $rawStock >= -150 )
			return array( 'dispatch_days' => $dNoStock, 'rule' => 'supplier', 'available' => true, 'category' => 'supplier' );

		// Resto de negativos -> no_stock.
		if( $rawStock < 0 )
			return array( 'dispatch_days' => $dNoStock, 'rule' => 'no_stock', 'available' => true, 'category' => 'no_stock' );

		// Sin stock real para cubrir la cantidad pedida.
		if( $rawStock <= 0 || $qty > $rawStock )
			return array( 'dispatch_days' => $dNoStock, 'rule' => 'no_stock', 'available' => true, 'category' => 'no_stock' );

		// En stock.
		return array( 'dispatch_days' => $dInStock, 'rule' => 'stock_ok', 'available' => true, 'category' => 'stock_ok' );
	}

	/**
	 * Dias de transito por codigo postal (shipping_prediction_cp).
	 * Solo se usa con el flag ON.
	 */
	public static function transitDaysForCp( $cp, $province = null ) {
		$unknown = (int)( defined('DELIVERY_ESTIMATE_TRANSIT_UNKNOWN') ? DELIVERY_ESTIMATE_TRANSIT_UNKNOWN : 2 );

		$cp = (string)$cp;
		$prefix = ( strlen( $cp ) >= 2 ) ? substr( $cp, 0, 2 ) : '';

		// Canarias / Ceuta / Melilla -> transito desconocido (bajo consulta).
		if( in_array( $prefix, array( '35', '38', '51', '52' ), true ) )
			return $unknown;

		// Baleares -> +1 dia.
		$balearExtra = ( $prefix === '07' ) ? 1 : 0;

		$check = tep_db_query( "show tables like 'shipping_prediction_cp'" );
		if( tep_db_num_rows( $check ) == 0 )
			return $unknown + $balearExtra;

		$q = tep_db_query( "select * from shipping_prediction_cp where cp REGEXP '^[0-9]{5}$' and cp = '" . tep_db_input( $cp ) . "'" );
		if( tep_db_num_rows( $q ) == 0 )
			return $unknown + $balearExtra;

		$r = tep_db_fetch_array( $q );
		$pred = isset( $r['prediction'] ) ? strtolower( trim( (string)$r['prediction'] ) ) : '';

		if( $pred === 'diary' || $pred === 'diaria' )
			return 1 + $balearExtra;
		if( $pred === '+24' || $pred === '24' )
			return 2 + $balearExtra;

		// consult / no-predict / vacio -> desconocido.
		return $unknown + $balearExtra;
	}

	/**
	 * Lead time de un producto. Lee el stock crudo (products_stock por
	 * attrKey si hay, si no products.products_quantity). Si
	 * $stockDecremented=TRUE clasifica sobre (rawStock + qty) para
	 * reproducir el stock en el momento de la compra (MUST-FIX #2).
	 * Devuelve ['dispatch_days','rule','available','transit_days','category'].
	 */
	public static function leadTimeForProduct( $pid, $qty, $cp = '', $attrKey = null, $province = null, $stockDecremented = false ) {
		$pid = (int)$pid;
		$qty = (int)$qty;

		$rawStock = null;

		if( $attrKey !== null && $attrKey !== '' ) {
			$s2 = tep_db_query( "select products_stock_quantity from " . TABLE_PRODUCTS_STOCK . " where products_id = '" . $pid . "' and products_stock_attributes = '" . tep_db_input( (string)$attrKey ) . "'" );
			if( tep_db_num_rows( $s2 ) > 0 ) {
				$s2 = tep_db_fetch_array( $s2 );
				$rawStock = (int)$s2['products_stock_quantity'];
			}
		}

		if( $rawStock === null ) {
			$aux = tep_db_query( "select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . $pid . "'" );
			if( tep_db_num_rows( $aux ) == 0 )
				return array( 'dispatch_days' => 0, 'rule' => 'no_stock', 'available' => false, 'transit_days' => 0, 'category' => 'no_stock' );
			$aux = tep_db_fetch_array( $aux );
			$rawStock = (int)$aux['products_quantity'];
		}

		$forClassify = $stockDecremented ? ( $rawStock + $qty ) : $rawStock;
		$c = self::classifyStock( $forClassify, $qty );

		$transit = $c['available'] ? self::transitDaysForCp( $cp, $province ) : 0;

		return array(
			'dispatch_days' => $c['dispatch_days'],
			'rule'          => $c['rule'],
			'available'     => $c['available'],
			'transit_days'  => $transit,
			'category'      => $c['category'],
		);
	}

	/**
	 * Mapea la categoria de stock a la CLASE CSS existente, respetando
	 * check_stock EXACTAMENTE como claseBotonComprar (functions.php:380):
	 * si $checkStock activo y no hay stock real disponible -> ' prdt-agtd '.
	 * (MUST-FIX #1: no cambiar que productos se venden.)
	 */
	public static function classFromStock( $rawStock, $qty = 1, $checkStock = 0 ) {
		$rawStock = (int)$rawStock;

		// Reproduce la rama agotado de claseBotonComprar (functions.php:380):
		// (1) DISABLE_SHIPPING_5_DAYS=='true' && qty<=0 -> ' prdt-agtd '
		if( defined('DISABLE_SHIPPING_5_DAYS') && constant('DISABLE_SHIPPING_5_DAYS') == 'true' && $rawStock <= 0 )
			return ' prdt-agtd ';
		// (2) check_stock activo && qty<=0 -> ' prdt-agtd '
		if( $checkStock && $rawStock <= 0 )
			return ' prdt-agtd ';

		$c = self::classifyStock( $rawStock, $qty );
		switch( $c['category'] ) {
			case 'discontinued':
			case 'unavailable':
				return ' prdt-agtd ';
			case 'backorder':
			case 'on_demand':
				return ' prdt-bjpdd ';
			case 'supplier':
				return ' prdt-4dias ';
			case 'no_stock':
				return ' prdt-5dias ';
			case 'stock_ok':
			default:
				return '';
		}
	}

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

	/**
	 * Fecha de despacho para pedidos EN STOCK: el MISMO dia si el pedido entra en dia
	 * laborable antes del corte (DELIVERY_ESTIMATE_CUTOFF_HOUR, def. 16h); si no, el
	 * siguiente dia laborable. Considera fines de semana y festivos (por provincia de
	 * destino si se pasa). Devuelve 'Y-m-d'. Solo se invoca desde la rama unified (flag ON).
	 */
	private function nextShipSlot( $fromDatetime, $province = null ) {
		$cutoff = (int)( defined('DELIVERY_ESTIMATE_CUTOFF_HOUR') ? DELIVERY_ESTIMATE_CUTOFF_HOUR : 16 );

		$ts = strtotime( (string)$fromDatetime );
		if( $ts === false )
			$ts = time();

		$hour = (int)date( 'H', $ts );

		// Dia laborable y aun antes del corte -> se envia HOY.
		if( $this->isBusinessDay( $ts, $province ) && $hour < $cutoff )
			return date( 'Y-m-d', $ts );

		// Tras el corte, o finde/festivo -> siguiente dia laborable.
		return $this->addDays( date( 'Y-m-d', $ts ), 1, $province );
	}

	/**
	 * True si $ts (timestamp) cae en dia laborable: ni sabado/domingo ni festivo
	 * (nacional/personal + autonomico/local de la provincia si se pasa).
	 */
	private function isBusinessDay( $ts, $province = null ) {
		$dow = (int)date( 'N', $ts ); // 1=Lunes ... 7=Domingo
		if( $dow >= 6 )
			return false;

		$holidays = $this->loadHolidays( $ts, 1, $province );
		$ymd = date( 'Y-m-d', $ts );
		return ! isset( $holidays[$ymd] );
	}

	private function addDays( $fromDate, $days, $province = null ) {

		$businessDays = ( defined('DELIVERY_ESTIMATE_BUSINESS_DAYS') && constant('DELIVERY_ESTIMATE_BUSINESS_DAYS') == 'True' );

		$ts = strtotime( (string)$fromDate );
		if( $ts === false )
			$ts = time();

		if( ! $businessDays )
			return date( 'Y-m-d', strtotime( '+' . (int)$days . ' days', $ts ) );

		// Precargamos festivos del calendario (shipping_prediction_calendar) si existe.
		// Saltamos sábados, domingos y festivos nacionales/personales por igual.
		$holidays = $this->loadHolidays( $ts, (int)$days, $province );

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
	private function loadHolidays( $fromTs, $daysToAdd, $province = null ) {
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
		// Con $province (rama unified) ampliamos a autonomicos/locales de esa provincia.
		// Con $province=null el WHERE es IDENTICO al legacy (solo national+personal).
		$whereCal = "calendar_type in ('national','personal')";
		if( $province !== null && $province !== '' ) {
			$whereCal .= " or (calendar_type in ('autonomic','local') and calendar_province = '" . tep_db_input( (string)$province ) . "')";
		}
		$q = tep_db_query( "select calendar_day, calendar_month, calendar_year from shipping_prediction_calendar where " . $whereCal );
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
