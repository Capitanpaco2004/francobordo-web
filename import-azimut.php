<?php

include( 'includes/application_top.php' );

// Ruta absoluta
$sServer = dirname(__FILE__);

// Cambiamos el limite de la memoria
ini_set( 'memory_limit', '-1' );
// Establecemos el tiempo de espera en infinito
set_time_limit( -1 );

$sCsvPath = $sServer . '/import/feed/Azimut/datos_nautica_francobordo.csv';

// ---------------------------------------------------------------------------
// Pre-paso: construir la lista dinámica de manufacturers_id que distribuye Azimut
// (los 76 brands del feed, mapeados case-insensitive contra la tabla manufacturers).
// Se almacena en $GLOBALS['gAzimutManuIds'] y se usa como filtro de seguridad
// junto con products_import_origin LIKE 'azimut%'.
// ---------------------------------------------------------------------------
$GLOBALS['gAzimutManuIds'] = buildAzimutManufacturerList( $sCsvPath );

$fFile = fopen( $sCsvPath, "r" );
if ( $fFile === false ) {
	die( '<p style="color:red;font-weight:bold;">ERROR: no se pudo abrir el CSV en ' . htmlspecialchars( $sCsvPath ) . '</p>' );
}
// El CSV viene en ISO-8859-1; convertirlo on-the-fly a UTF-8.
stream_filter_append( $fFile, 'convert.iconv.ISO-8859-1/UTF-8' );

$bFirstRow = true;
while ( ( $row = fgetcsv( $fFile, 0, ',', '"', '' ) ) !== false )
{
	// Saltar la línea de cabecera (UrlTienda,StockLevel,Category,ProductCode,…)
	if ( $bFirstRow ) {
		$bFirstRow = false;
		if ( isset( $row[0] ) && strcasecmp( trim( $row[0] ), 'UrlTienda' ) === 0 ) continue;
	}

	if ( count( $row ) < 4 ) continue;

	$nStock  = trim( $row[1] ?? '' );
	$sModelo = trim( $row[3] ?? '' );

	echo htmlspecialchars( $nStock ) . ' ' . htmlspecialchars( $sModelo ) . '<br>';

	if ( $sModelo === '' ) continue;

	addProduct( array( 'products_quantity' => $nStock,
					   'products_model' => $sModelo
					  ), $aDatabase );
}

fclose($fFile);

// Email informativo
//mail( 'f.rodriguez@francobordo.net', '[CORRECTO] Importador de stock Azimut a Francobordo', 'Se ha actualizado con exito la tienda Francobordo.', 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=UTF-8' . "\r\n" . 'From:info@francobordo.com' . "\r\n" );


/**
 * Lee la columna "Fabricante" del CSV (col índice 6) y resuelve cada nombre
 * a su manufacturers_id en BD (UPPER(TRIM(...)) = UPPER(TRIM(...))). Devuelve
 * la lista de IDs encontrados. Si no encuentra ninguno, devuelve una lista
 * legacy (12, 348, 483) como fallback de seguridad para no abrir el filtro.
 */
function buildAzimutManufacturerList($sCsvPath) {
	$brands = array();
	$f = @fopen( $sCsvPath, 'r' );
	if ( $f === false ) {
		echo '<p style="color:orange;">AVISO: CSV no accesible al construir lista de fabricantes; uso fallback (12,348,483).</p>';
		return array(12, 348, 483);
	}
	stream_filter_append( $f, 'convert.iconv.ISO-8859-1/UTF-8' );
	$bFirst = true;
	while ( ( $r = fgetcsv( $f, 0, ',', '"', '' ) ) !== false ) {
		if ( $bFirst ) { $bFirst = false; if ( isset( $r[0] ) && strcasecmp( trim( $r[0] ), 'UrlTienda' ) === 0 ) continue; }
		if ( count( $r ) < 7 ) continue;
		$b = strtoupper( trim( $r[6] ?? '' ) );
		if ( $b !== '' ) $brands[$b] = true;
	}
	fclose( $f );

	$ids = array();
	foreach ( array_keys( $brands ) as $b ) {
		$bEsc = tep_db_input( $b );
		$q = tep_db_query( 'SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name)) = "' . $bEsc . '" LIMIT 1' );
		if ( $row = tep_db_fetch_array( $q ) ) $ids[] = (int) $row['manufacturers_id'];
	}
	// Fusionar con legacy y deduplicar
	$ids = array_values( array_unique( array_merge( $ids, array(12, 348, 483) ) ) );

	echo '<p style="color:#888;font-size:12px;">Lista dinámica de manufacturers Azimut: ' . count( $ids ) . ' IDs (de ' . count( $brands ) . ' brands en CSV).</p>';

	return $ids;
}

// Función para añadir productos
//
// Filtro de seguridad: solo actualiza productos cuyo manufacturer está entre
// los del feed Azimut (lista dinámica $GLOBALS['gAzimutManuIds']) o que se
// importaron con products_import_origin = 'azimut'. Evita pisar stock de
// productos no-Azimut con SKU coincidente.
function addProduct($aValues, $aDatabase)
{
	// Comprobamos si tenemos los valores minimos
	if ( empty( $aValues['products_model'] ) )
		return false;

	$nStock = $aValues['products_quantity'];

	// Sanitización + variantes con/sin espacios para matchear ambos formatos en BD
	$sModeloEsc        = tep_db_input( strtolower( $aValues['products_model'] ) );
	$sModeloEscNoSpace = tep_db_input( strtolower( str_replace( ' ', '', $aValues['products_model'] ) ) );

	// Lista dinámica de manufacturers Azimut (computada al inicio del script)
	$mfgIds  = isset( $GLOBALS['gAzimutManuIds'] ) ? $GLOBALS['gAzimutManuIds'] : array(12, 348, 483);
	$mfgList = implode( ',', array_map( 'intval', $mfgIds ) );

	// Comprobamos si existe el registro
	$aProducts = tep_db_query( 'SELECT products_id, products_quantity FROM products
		WHERE LCASE( products_model ) IN ("' . $sModeloEsc . '", "' . $sModeloEscNoSpace . '")
		AND ( products_import_origin LIKE "azimut%" OR manufacturers_id IN (' . $mfgList . ') )
		AND products_status = 1;' );

	// Si el registro existe
	if( tep_db_num_rows( $aProducts ) > 0 )
	{
		$aProducts = tep_db_fetch_array( $aProducts );

		if(  $aProducts['products_quantity'] <= 0 && $nStock == 'InStock' )
			$aValues['products_quantity'] = -100;
		elseif(  $aProducts['products_quantity'] <= 0 && $nStock == 'OutStock' )
			$aValues['products_quantity'] = -800;
		else
			$aValues['products_quantity'] = $aProducts['products_quantity'];

		if( $aValues['products_quantity'] != $aProducts['products_quantity'] )
			update( 'products', $aValues, 'products_id = ' . (int) $aProducts['products_id'], $aDatabase );

	}

	// Comprobamos si existe el atributo
	$aProducts = tep_db_query( 'SELECT pa.products_id, pa.options_id, pa.options_values_id
		FROM products_attributes pa
		INNER JOIN products p ON (pa.products_id = p.products_id)
		WHERE LCASE( pa.reference ) IN ("' . $sModeloEsc . '", "' . $sModeloEscNoSpace . '")
		AND ( p.products_import_origin LIKE "azimut%" OR p.manufacturers_id IN (' . $mfgList . ') )
		AND p.products_status = 1;' );

	if( tep_db_num_rows( $aProducts ) > 0 )
	{
		$aProducts = tep_db_fetch_array( $aProducts );

		// Obtenemos stock atributo
		$sAttrKey = (int) $aProducts['options_id'] . '-' . (int) $aProducts['options_values_id'];
		$aStockAtri = tep_db_query( 'SELECT products_stock_quantity FROM products_stock
			WHERE products_id = ' . (int) $aProducts['products_id'] . '
			AND products_stock_attributes = "' . tep_db_input( $sAttrKey ) . '"' );
		$aStockAtri = tep_db_fetch_array( $aStockAtri );

		if( $aStockAtri['products_stock_quantity'] <= 0 && $nStock == 'InStock')
			$nStock = -100;
		elseif( $aStockAtri['products_stock_quantity'] <= 0 && $nStock == 'OutStock' )
			$nStock = -800;
		else
			$nStock = $aStockAtri['products_stock_quantity'];

		if( $nStock != $aStockAtri['products_stock_quantity'] )
			tep_db_query( 'UPDATE products_stock SET products_stock_quantity = ' . (int) $nStock . '
				WHERE products_id = ' . (int) $aProducts['products_id'] . '
				AND products_stock_attributes = "' . tep_db_input( $sAttrKey ) . '"' );
	}

	return false;
}
function update($sTable, $aValues, $sWhere, $aDatabase)
{
	// Preparamos la consulta de update
	$sInsert = 'UPDATE ' . $sTable . ' SET ';

	// Recorremos los campos y componemos el update (con escape)
	foreach( $aValues as $sKey => $aValue )
		$sInsert .= $sKey . ' = "' . tep_db_input( (string) $aValue ) . '", ';
	$sInsert = substr( $sInsert, 0, -2 );

	// Añadimos el where
	$sInsert .= ' WHERE ' . $sWhere . ';';

	// Actualizamos el registro
	tep_db_query( $sInsert );

}

?>
