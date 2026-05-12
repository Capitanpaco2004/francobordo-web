<?php
include( 'includes/application_top.php' );

// Ruta absoluta
$sServer = dirname(__FILE__);

//Cargo Librerias de Excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$aPHPobj = \PhpOffice\PhpSpreadsheet\IOFactory::load($sServer . '/descargas/mb.xlsx');
$aSheetsNames = $aPHPobj->getSheetNames( $sServer . '/descargas/mb.xlsx' );
$aProducts = $aPHPobj->getActiveSheet()->toArray( null, true, true, true );

// Recorremos el feed
$nCont = 0;
foreach( $aProducts as $aProduct )
{
	if( $nCont == 0 )
	{
		++$nCont;
		continue;
	}

	// Variables
	$nStock = $aProduct['D']  ;
	$sModelo = trim( $aProduct['B'] );
	$nProducto = false;
	
	if( $sModelo == '' )
		continue;

	addProduct( array( 'products_quantity' => $nStock,
					   'products_model' => $sModelo
					  ), $aDatabase );
}

if( isset( $_GET['action'] ) && $_GET['action'] == 'rel' )
{
	tep_redirect( '_admin/importador.php?i=m&imp=1', '' );
}

// Email informativo
//mail( 'victor.ruiz@denox.es', '[CORRECTO] Importador de stock Marine Business a Francobordo', 'Se ha actualizado con exito la tienda Francobordo.', 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=UTF-8' . "\r\n" . 'From:info@francobordo.com' . "\r\n" );

// Función para añadir productos
function addProduct($aValues, $aDatabase)
{
	// Comprobamos si tenemos los valores minimos
	if( $aValues['products_model'] === false )
		return false;

	$nStock = $aValues['products_quantity'];

	// Comprobamos si existe el registro
	$aProducts = tep_db_query( 'SELECT products_id, products_quantity FROM products WHERE LCASE( products_model ) = "' . strtolower( $aValues['products_model'] ) . '" AND manufacturers_id = 47 AND products_status = 1;' );

	// Si el registro existe
	if( tep_db_num_rows( $aProducts ) > 0 )
	{
		$aProducts = tep_db_fetch_array( $aProducts );
		
		if( $aProducts['products_quantity'] <= 0 && $nStock > 0 )
			$aValues['products_quantity'] = -100;
		elseif( $aProducts['products_quantity'] <= 0 && $nStock <= 0 )
			$aValues['products_quantity'] = -900;
		else
			$aValues['products_quantity'] = $aProducts['products_quantity'];

		if( $aValues['products_quantity'] != $aProducts['products_quantity'] )
			update( 'products', $aValues, 'products_id = ' . $aProducts['products_id'], $aDatabase );
	}

	// Comprobamos si existe el atributo
	$aProducts = tep_db_query( 'SELECT pa.products_id, pa.options_id, pa.options_values_id FROM products_attributes pa INNER JOIN products p ON (pa.products_id = p.products_id) WHERE LCASE( pa.reference ) = "' . strtolower( $aValues['products_model'] ) . '"  AND p.manufacturers_id = 47 AND p.products_status = 1;' );

	if( tep_db_num_rows( $aProducts ) > 0 )
	{
		$aProducts = tep_db_fetch_array( $aProducts );

		// Obtenemos stock atributo
		$aStockAtri = tep_db_query( 'SELECT products_stock_quantity FROM products_stock WHERE products_id = ' . $aProducts['products_id'] . ' AND products_stock_attributes = "' . $aProducts['options_id'] . '-' . $aProducts['options_values_id'] . '"' );
		$aStockAtri = tep_db_fetch_array( $aStockAtri );

		if( $aStockAtri['products_stock_quantity'] <= 0 && $nStock > 0 )
			$nStock = -100;
		elseif( $aStockAtri['products_stock_quantity'] <= 0 && $nStock <= 0 )
			$nStock = -900;
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