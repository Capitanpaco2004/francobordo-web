<?php
include( 'includes/application_top.php' );

// Ruta absoluta
$sServer = dirname(__FILE__);

// Conectamos al ftp
$ftp_server = "ftp.onlinux-es.setupdns.net";
$ftp_conn = ftp_connect($ftp_server) or die("No ha sido posible conectar por FTP a la dirección $ftp_server");
$login = ftp_login($ftp_conn, 'stock@dismarina.com', 'st0ckFTPd1sm4C0ntrasenya!');

ftp_pasv($ftp_conn, true);

// Descargamos el csv de productos
ftp_get($ftp_conn, $sServer . '/import/feed/dismarina.txt', 'Stock.txt', FTP_BINARY);

// Cerramos
ftp_close($ftp_conn);

chmod( $sServer . '/import/feed/dismarina.txt', 0777 );

if (!file_exists( $sServer . '/import/feed/dismarina.txt' )) {
	exit("El feed no existe." . PHP_EOL);
}

$fFile = fopen( $sServer . '/import/feed/dismarina.txt', "r");

while(!feof($fFile))
{
	// Variables
	$aSeparateRow = preg_split( '/\;+/', fgets( $fFile ) );

	// El stock es el último campo
	$nStock = trim( $aSeparateRow[count( $aSeparateRow ) - 1] );
	// El modulo es el primer campo
	$sModelo = trim( $aSeparateRow[0] );

	if( $sModelo == '' )
		continue;

	addProduct( array( 'products_quantity' => $nStock,
					   'products_model' => $sModelo
					  ), $aDatabase );
}

fclose($fFile);

if( isset( $_GET['action'] ) && $_GET['action'] == 'rel' )
{
	tep_redirect( '_admin/importador.php?i=m&imp=1', '' );
}

// Función para añadir productos
function addProduct($aValues, $aDatabase)
{
	// Comprobamos si tenemos los valores minimos
	if( $aValues['products_model'] === false )
		return false;

	$nStock = $aValues['products_quantity'];

	// Comprobamos si existe el registro
	$aProducts = tep_db_query( 'SELECT products_id, products_quantity FROM products WHERE LCASE( products_model ) = "' . strtolower( $aValues['products_model'] ) . '" AND manufacturers_id IN (2,99,232,513) AND products_status = 1;' );

	// Si el registro existe
	if( tep_db_num_rows( $aProducts ) > 0  )
	{
		$aProducts = tep_db_fetch_array( $aProducts );

		if( $aProducts['products_quantity'] <= 0 && $nStock > 0 )
			$aValues['products_quantity'] = -100;
		elseif( $aProducts['products_quantity'] <= 0 && $nStock <= 0)
			$aValues['products_quantity'] = -800;
		else
			$aValues['products_quantity'] = $aProducts['products_quantity'];

		if( $aValues['products_quantity'] != $aProducts['products_quantity'] )
			update( 'products', $aValues, 'products_id = ' . $aProducts['products_id'], $aDatabase );
	}

	// Comprobamos si existe el atributo
	$aProducts = tep_db_query( 'SELECT pa.products_id, pa.options_id, pa.options_values_id FROM products_attributes pa INNER JOIN products p ON (pa.products_id = p.products_id) WHERE LCASE( pa.reference ) = "' . strtolower( $aValues['products_model'] ) . '" AND manufacturers_id IN (2,99,232,513) AND p.products_status = 1;' );

	if( tep_db_num_rows( $aProducts ) > 0  )
	{
		$aProducts = tep_db_fetch_array( $aProducts );

		// Obtenemos stock atributo
		$aStockAtri = tep_db_query( 'SELECT products_stock_quantity FROM products_stock WHERE products_id = ' . $aProducts['products_id'] . ' AND products_stock_attributes = "' . $aProducts['options_id'] . '-' . $aProducts['options_values_id'] . '"' );
		$aStockAtri = tep_db_fetch_array( $aStockAtri );

		if( $aStockAtri['products_stock_quantity'] <= 0 && $nStock > 0 )
			$nStock = -100;
		elseif( $aStockAtri['products_stock_quantity'] <= 0 && $nStock <= 0 )
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

	// Recorremos los campos y componemos el update. Fix 2026-05-16: $aValue ahora se escapa con tep_db_input.
	// El CSV de Dismarina sólo trae stock numérico hoy, pero la función era vulnerable por diseño.
	foreach( $aValues as $sKey => $aValue )
		$sInsert .= $sKey . ' = "' . tep_db_input( (string) $aValue ) . '", ';
	$sInsert = substr( $sInsert, 0, -2 );

	// Añadimos el where
	$sInsert .= ' WHERE ' . $sWhere . ';';

	// Actualizamos el registro
	tep_db_query( $sInsert );
}
?>