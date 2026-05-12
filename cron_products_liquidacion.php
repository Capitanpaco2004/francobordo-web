<?php
	require( 'includes/application_top.php' );

	/*
	 * 2026-05-12 — Comentada la lógica de rebajar precios sobre `specials`.
	 * De momento este cron sólo aplica el cambio de estado (status=2) a productos
	 * en liquidación sin stock. Si se reactiva el descuento automático, descomentar
	 * el bloque siguiente y ajustar PERCENT_DISCOUNT_LIQUIDACION en configuración.
	 *
	 * Nota: la versión moderna del flujo está en
	 *   _admin/scripts/cron_products_liquidacion.php
	 * (PHP CLI, soporta variantes y sentinels de stock).
	 */

	/*
	// Obtenemos los productos marcados para liquidacion, que tengan stock, esté activo y cuyo precio de oferta sea mayor que el precio mínimo de oferta del producto
	$aProducts = tep_db_query( 'SELECT s.specials_id, s.specials_new_products_price, s.specials_min_price, s.expires_date
								FROM products p
								INNER JOIN specials s on (p.products_id = s.products_id and s.customers_group_id = 0)
								WHERE p.products_liquidacion = 1
								AND p.products_quantity > 0
								AND p.products_status = 1
								AND s.specials_new_products_price > s.specials_min_price' );

	// Recorremos los productos
	while( $aProduct = tep_db_fetch_array( $aProducts ) )
	{
		// Rebajamos el porcentaje configurado al precio de la oferta
		$nPrecioActual = $aProduct['specials_new_products_price'];
		$nSpecialPrice = $nPrecioActual - (($nPrecioActual * PERCENT_DISCOUNT_LIQUIDACION) / 100);

		// Si el precio de oferta es mas pequeño que el mínimo de precio, el precio en oferta será el mínimo indicado
		if( $nSpecialPrice < $aProduct['specials_min_price'] )
			$nSpecialPrice = $aProduct['specials_min_price'];

		// Fecha caducidad
		$dExpire = $aProduct['expires_date'];

		// Si tiene fecha de caducidad
		if( $aProduct['expires_date'] > 0 )
		{
			// Si la fecha de expiración es mas pequeño o igual que la fecha actual
			if( strtotime( date( 'Y-m-d 00:00:00', strtotime( '+2 day' , strtotime( date( 'Y-m-d' ) ) ) ) ) >= strtotime( date( 'Y-m-d 00:00:00', strtotime( $aProduct['expires_date'] ) ) ) )
				$dExpire = date( 'Y-m-d H:i:s', strtotime( '+10 day' , strtotime( date( 'Y-m-d' ) ) ) );
		}

		tep_db_query( 'UPDATE specials SET expires_date = "' . $dExpire . '", specials_new_products_price = "' . $nSpecialPrice . '" WHERE specials_id = ' . $aProduct['specials_id'] );
	}
	*/

	// Marcamos como descatalogados los productos que estén en liquidación y no tengan stock
	tep_db_query( 'UPDATE products SET products_status = 2 WHERE products_liquidacion = 1 AND products_quantity = 0' );
?>
