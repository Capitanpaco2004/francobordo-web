<?php
include( 'includes/application_top.php' );

// Ruta absoluta
$sServer = dirname(__FILE__);

// Cambiamos el limite de la memoria
ini_set( 'memory_limit', '-1' );
// Establecemos el tiempo de espera en infinito
set_time_limit( -1 );

$fFile = fopen( $sServer . '/descargas/dometic.csv', "r");

while(!feof($fFile))
	
{

	// Variables
	$aSeparateRow = explode( ';', fgets( $fFile ) );
	$nStockInternacional = trim($aSeparateRow[3]);
	$nStock = $aSeparateRow[2];
	$sModelo = $aSeparateRow[0];
	$nProducto = false;
	if( $sModelo == '' )
		continue;

	addProduct( array( 'products_quantity' => $nStock,
					   'products_model' => $sModelo
					  ),$nStockInternacional, $aDatabase );
}
fclose($fFile);
if( isset( $_GET['action'] ) && $_GET['action'] == 'rel' )
{
	tep_redirect( '_admin/importador.php?i=m&imp=1', '' );
}

// Email informativo
//mail( 'victor.ruiz@denox.es', '[CORRECTO] Importador de stock Dometic a Francobordo', 'Se ha actualizado con exito la tienda Francobordo.', 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=UTF-8' . "\r\n" . 'From:info@francobordo.com' . "\r\n" );


// Función para añadir productos
function addProduct($aValues, $nStockInternacional, $aDatabase)
{
	// Comprobamos si tenemos los valores minimos
	if( $aValues['products_model'] === false )
		return false;

	$nStock = $aValues['products_quantity'];

	// Comprobamos si existe el registro
	$aProducts = tep_db_query( 'SELECT products_id, products_quantity FROM products WHERE LCASE( products_model ) = "' . strtolower( $aValues['products_model'] ) . '" AND products_status = 1;' );

	// Si el registro existe
	if( tep_db_num_rows( $aProducts ) > 0 )
	{
		$aProducts = tep_db_fetch_array( $aProducts );
		
		if(  $aProducts['products_quantity'] <= 0 && ($nStock > 0 or $nStockInternacional == 'Si' ))
			$aValues['products_quantity'] = -100;
		elseif(  $aProducts['products_quantity'] <= 0 && $nStock <= 0 && $nStockInternacional == 'No' )
			$aValues['products_quantity'] = -800;
		else
			$aValues['products_quantity'] = $aProducts['products_quantity'];

		if( $aValues['products_quantity'] != $aProducts['products_quantity'] )
			update( 'products', $aValues, 'products_id = ' . $aProducts['products_id'], $aDatabase );

	}

	// Comprobamos si existe el atributo
	$aProducts = tep_db_query( 'SELECT pa.products_id, pa.options_id, pa.options_values_id FROM products_attributes pa INNER JOIN products p ON (pa.products_id = p.products_id) WHERE LCASE( pa.reference ) = "' . strtolower( $aValues['products_model'] ) . '" AND p.products_status = 1;' );

	if( tep_db_num_rows( $aProducts ) > 0 )
	{
		$aProducts = tep_db_fetch_array( $aProducts );

		// Obtenemos stock atributo
		$aStockAtri = tep_db_query( 'SELECT products_stock_quantity FROM products_stock WHERE products_id = ' . $aProducts['products_id'] . ' AND products_stock_attributes = "' . $aProducts['options_id'] . '-' . $aProducts['options_values_id'] . '"' );
		$aStockAtri = tep_db_fetch_array( $aStockAtri );

		if( $aStockAtri['products_stock_quantity'] <= 0 && ($nStock > 0 or $nStockInternacional == 'Si' ))
			$nStock = -100;
		elseif( $aStockAtri['products_stock_quantity'] <= 0 && $nStock <= 0 && $nStockInternacional == 'No' )
			$nStock = -800;
		else
			$nStock = $aStockAtri['products_stock_quantity'];

		if( $nStock != $aStockAtri['products_stock_quantity'] )
			tep_db_query( 'UPDATE products_stock SET products_stock_quantity = ' . $nStock . ' WHERE products_id = ' . $aProducts['products_id'] . ' AND products_stock_attributes = "' . $aProducts['options_id'] . '-' . $aProducts['options_values_id'] . '"' );
	}

	return false;
}
function update($sTable, $aValues, $sWhere, $aDatabase)
{
	// Preparamos la consulta de update
	$sInsert = 'UPDATE ' . $sTable . ' SET ';

	// Recorremos los campos y componemos el update
	foreach( $aValues as $sKey => $aValue )
		$sInsert .= $sKey . ' = "' . $aValue . '", ';
	$sInsert = substr( $sInsert, 0, -2 );

	// Añadimos el where
	$sInsert .= ' WHERE ' . $sWhere . ';';
	
	// Actualizamos el registro
	tep_db_query( $sInsert );
}

?>