<?php
	require( DIR_WS_LANGUAGES . $language . '/also_purchased_products.php' );
	
	// Cantidad de elementos
	$nElementos = 6;

	// Variables
	$aDatos = null;
	$aIdsOrders = array();
	$sub_orders = null;
	$aAux = null;
	$aProductos = null;
	$sIdProducto = preg_replace( '/\{.+$/i', '', $_GET['products_id'] );
	
	// Obtenemos los 100 pedidos ultimos de este producto
	$aDatos = tep_db_query( 'SELECT op.orders_id 
							 FROM orders_products op
							 INNER JOIN orders o ON (op.orders_id = o.orders_id)
							 WHERE products_id = "' . (int)$sIdProducto . '" AND  DATE_SUB(CURDATE(), INTERVAL 720 DAY) <= o.date_purchased
							 ORDER BY o.date_purchased DESC 
							 LIMIT 100' ); 

	// Si hemos obtenido resultados
	if( tep_db_num_rows( $aDatos ) > 0 ) 
	{
		while( $sub_orders = tep_db_fetch_array( $aDatos ) )
			$aIdsOrders[] = $sub_orders['orders_id'];

		$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_ship_free, p.products_status, p.products_tax_class_id, p.products_quantity, p.products_image, p.products_price, pd.products_id, pd.products_name
				 FROM orders_products opb
				 INNER JOIN orders o ON (opb.orders_id = o.orders_id)
				 INNER JOIN products p ON (opb.products_id = p.products_id)
				 INNER JOIN products_description pd ON (pd.products_id = p.products_id)
				 ' . SQL_FROM . '
				 WHERE opb.products_id != "' . (int)$sIdProducto . '" AND o.orders_id in (' . implode( ',', $aIdsOrders ) . ') AND p.products_status = 1
				 GROUP BY p.products_id
				 ORDER BY o.date_purchased DESC
				 limit ' . $nElementos;

		// Obtenemos los productos cambiando el precio segun tipo de cliente
		$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
		
		// Si contiene productos
		if( $aAux['TOTAL'] > 0 )
		{
			$aProductos = $aAux['PRODUCTOS'];
			include( DIR_THEME. 'html/components/' . basename(__FILE__) );
		}
	}
?>