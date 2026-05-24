<?php
if (defined('DATABASE_FUNCTIONS_LOADED')) return;
define('DATABASE_FUNCTIONS_LOADED', true);

// Librerias
use util\tools;
use util\event;

/**
 * Conexión hacia la base de datos
 * @param string $server Servidor para conectarnos
 * @param string $username Usuario de la base de datos
 * @param string $password Password de la base de datos
 * @param string $database Base de datos
 * @return object
 */
function tep_db_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD, $database = DB_DATABASE)
{
    // Variables
    global $pdo;

    // Opciones PDO
    $options = array(
        (defined('Pdo\\Mysql::ATTR_INIT_COMMAND') ? Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND) => 'SET NAMES utf8mb4',
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    );

    // Conexión
    try {
        $pdo = new PDO('mysql:host=' . $server . ';dbname=' . $database . ';charset=utf8mb4', $username, $password, $options);
    } catch (Exception $e) {
        echo $e->getMessage();
        exit();
    }

    return $pdo;
}

/**
 * Devuelve la cantidad de columnas
 * @param object $string
 * @return int
 */
function tep_db_num_fields($string){
	return $string->columnCount();
}

/**
 * Realiza consultas con parámetros opcionales
 * @param string $query Consulta SQL con posibles parámetros
 * @param array $params Parámetros para la consulta (opcional)
 * @return array Resultado de la consulta como un array
 */
function tep_db_query($query, $params = null)
{
	// Variables
	global $pdo;

	if (stripos($query, 'information_schema') !== false || stripos($query, 'sleep(') !== false) {
		return false;
	}

	$nTimerStart = microtime();

	// Evento
	event::getInstance()->execute((tools::isAdmin() ? 'back' : 'front') . '_office_database_query_before', [$query, 'before']);

	try {
		if ($params === null) {
			$result = $pdo->query($query);
		} else {
			$stmt = $pdo->prepare($query);
			$stmt->execute($params);
			$result = $stmt;
		}
	} catch (PDOException $e) {
		$GLOBALS['_last_failed_query'] = $query;
		throw $e; // relanza para que se capture en errorBacktrace
	}
	// Evento
	event::getInstance()->execute((tools::isAdmin() ? 'back' : 'front') . '_office_database_query_after', [$query, 'after']);

	// Debug SQL
	if (class_exists('debugbar')) {
		$debugbar = debugbar::getInstance();
		$debugbar->debugSql($nTimerStart, $query);
	}

	// Retornamos el resultado como un PDOStatement (si no se especifica lo contrario)
	return $result;
}

/**
 * Realiza insert y update más faciles
 * @param string $table Tabla de la base de datos
 * @param string $data Datos a insertar/modificar
 * @param string $action Accion a realizar
 * @param string $parameters Parametros para añadir a la consulta
 * @return object
 */
function tep_db_perform($table, $data, $action = 'insert', $parameters = '')
{
	$column_info = [];
	$column_result = tep_db_query("SHOW COLUMNS FROM " . $table);

	while ($row = tep_db_fetch_array($column_result)) {
		$column_info[$row['Field']] = [
			'null' => $row['Null'] === 'YES',
		];
	}

	if ($action == 'insert') {
		$query = 'insert into ' . $table . ' (';

		foreach (array_keys($data) as $columns) {
			$query .= $columns . ', ';
		}

		$query = substr($query, 0, -2) . ') values (';

		foreach ($data as $key => $value) {
			$value = $value === '' && !$column_info[$key]['null'] ? '' : $value;

			switch ((string) $value) {
				case 'now()':
					$query .= 'now(), ';
					break;
				case 'null':
				case '':
					$query .= $column_info[$key]['null'] ? 'null, ' : '\'\', ';
					break;
				default:
					$query .= '\'' . tep_db_input($value) . '\', ';
					break;
			}
		}

        $query = substr($query, 0, -2) . ')';

		// Evento
		event::getInstance()->execute((tools::isAdmin() ? 'back' : 'front') . '_office_database_insert_before', [$table, $data, $parameters]);
	} elseif ($action == 'update') {
		$query = 'update ' . $table . ' set ';
		foreach ($data as $columns => $value) {
			$value = $value === '' && !$column_info[$columns]['null'] ? '' : $value;

			switch ((string) $value) {
				case 'now()':
					$query .= $columns . ' = now(), ';
					break;
				case 'null':
				case '':
					$query .= $columns . ' = ' . ($column_info[$columns]['null'] ? 'null' : '\'\'' ) . ', ';
                    break;
                default:
                    $query .= $columns . ' = \'' . tep_db_input($value) . '\', ';
                    break;
            }
        }
        $query = substr($query, 0, -2) . ' where ' . $parameters;

		// Evento
		event::getInstance()->execute((tools::isAdmin() ? 'back' : 'front') . '_office_database_update_before', [$table, $data, $parameters]);
    }

    return tep_db_query($query);
}

/**
 * Obtiene la siguiente fila de un conjunto de resultados dando la columna como un array asociativo
 * @param object $db_query Objeto
 * @return array
 */
function tep_db_fetch_array($db_query, $type = PDO::FETCH_ASSOC)
{
	if (is_array($db_query)) {
        return $db_query;
    }

    return $db_query->fetch($type);
}

/**
* Devuelve la cantidad de filas obtenidas
* @param object $db_query Objeto
* @return object
*/
function tep_db_num_rows($db_query)
{
	if (isset($db_query)) {
		return $db_query->rowCount();
	}
}

/**
* Reposiciona el puntero del resultset. PDO no permite rebobinar un cursor
* forward-only, así que para volver al inicio (row_number 0) re-ejecutamos el
* statement; para offsets > 0 descartamos esas filas.
* @param PDOStatement $db_query
* @param int $row_number
* @return bool
*/
function tep_db_data_seek($db_query, $row_number)
{
	if (!($db_query instanceof PDOStatement)) {
		return false;
	}
	if ((int)$row_number === 0) {
		$db_query->execute();
		return true;
	}
	for ($i = 0; $i < (int)$row_number; $i++) {
		$db_query->fetch();
	}
	return true;
}

/**
* Ultimo ID insertado
* @return int
*/
function tep_db_insert_id()
{
	// Variables
	global $pdo;

	return $pdo->lastInsertId();
}

/**
* Devuelve el nombre de la columna pasada por argumento
* @param object $db_query Objeto
* @param int $offset Numero columna
* @return string
*/
function tep_db_field_name($db_query, $offset)
{
	$return = $db_query->getColumnMeta($offset);
	return $return['name'];
}

/**
* Devuelve el tipo de la columna pasada por argumento
* @param object $db_query Objeto
* @param int $offset Numero columna
* @return string
*/
function tep_db_field_type($db_query, $offset)
{
	$return = $db_query->getColumnMeta($offset);
	return $return['pdo_type'];
}

/**
* Limpia la consulta
* @return int
*/
function tep_db_free_result($db_query)
{
	$db_query->closeCursor();
}

/**
 * Obtiene la la primera columna de la primera fila
 * @param object $db_query Objeto
 * @return object
 */
function tep_db_fetch_fields($db_query) {
	return $db_query->fetch(PDO::FETCH_COLUMN);
}

/**
 * Limpiamos el string pasado
 * @param string|null $string
 * @return string
 */
function tep_db_output($string) {
	if ($string === null) {
		return '';
	}
	return htmlspecialchars($string);
}

/**
 * Limpiamos el string pasado
 * @param string $string Cadena a limpiar
 * @return string
 */
function tep_db_input($string)
{
	if (is_string($string)) {
		return strtr($string, array("\x00"=>'\x00', "\n"=>'\n', "\r"=>'\r', "\\"=>'\\\\', "'"=>"\'", '"'=>'\"',  "\x1a"=>'\x1a'));
	}
	return $string;
}

/**
* Limpiamos el string pasado
* @param string $string Cadena a limpiar
* @return string
*/
function tep_db_prepare_input($string)
{
    if (is_string($string)) {
    	if(tools::isAdmin()){
			return trim(stripslashes($string));
    	}
    	else{
    		return trim(preg_replace(['/ +/','/[<>]/'], [' ', '_'], (stripslashes($string))));
    	}
    } elseif (is_array($string)) {
        foreach ($string as $key => $value) {
            $string[$key] = tep_db_prepare_input($value);
        }
        return $string;
    } else {
        return $string;
    }
}

/**
* Información del server
* @param string $string Cadena a limpiar
* @return string
*/
function tep_db_get_server_info()
{
	// Conectamos
	$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD);

	// Informacion
	$info = $mysqli->server_info;

	// Cerramos
	$mysqli->close();

	// Retornamos
    return $info;
}

/**
 * Obtiene la siguiente fila de un conjunto de resultados dando la columna como un objeto
 * @param object $db_query Objeto
 * @return object
 */
function tep_db_fetch_object($db_query)
{
    return $db_query->fetch(PDO::FETCH_OBJ);
}

/**
 * Devuelve si existe la tabla en base de datos
 * @param string $sTable Tabla a comprobar si existe
 * @return bool con el resultado
 */
function pharaonix_checkTableExists($sTable)
{
    return (pharaonix_queryOne('SHOW TABLES LIKE "' . $sTable . '"')->num_rows == 1 ? true : false);
}

/**
 * Devuelve el resultado de una consulta
 * @param mysqli_result $aRecords Objeto mysqli_result a procesar
 * @param bool $aKeys Permite indicarle que campos quieres obetner solo de la consulta
 * @param bool $index Permite especificarle un indice, por defecto sera un entero
 * @return array con los datos de la consulta
 */
function pharaonix_eachRecords($aRecords, $aKeys = false, $index = false)
{
    // Variables
    $aReturn = array();
    $bOnly = is_array($aKeys) && count($aKeys) == 1 ? true : false;
    $tools = new util\tools();
	$indexReturn = -1;

    // Recorremos la consulta
    while ($aRecord = tep_db_fetch_array($aRecords)) {
		$indexReturn = $index ? $aRecord[$index] : ++$indexReturn;

        if ($aKeys !== false) {
            $aRecord = $tools::arrayColumn($aRecord, $aKeys);

            // Si solo es un registro lo pasamos a string
            if ($bOnly) {
                $aRecord = $aRecord[$aKeys[0]];
            }

        }

        // Guardamos
		$aReturn[$indexReturn] = $aRecord;
    }

    // Retornamos
    return $aReturn;
}

/**
 * Realiza la consulta pasada como argumento y devuelve el objeto result, ademas del total de filas
 * @param string $sSql Consulta SQL ha realizar
 * @param bool $bResult Si quieres obtener un array con los datos en vez de un objeto mysqli_result
 * @param bool $aKeys Permite indicarle que campos quieres obetner solo de la consulta
 * @param bool $index Permite especificarle un indice, por defecto sera un entero
 * @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
 */
function pharaonix_query($sSql, $bResult = false, $aKeys = false, $index = false)
{
    // Variables
    $objReturn = new \stdClass();

    // Consulta
    $objReturn->records = tep_db_query($sSql);

    // Total de filas
    if (is_bool($objReturn->records)) {
        $objReturn->num_rows = 1;
    } else {
        $objReturn->num_rows = tep_db_num_rows($objReturn->records);
    }

    // Si queremos un array como resultado
    if ($bResult) {
        $objReturn->records = pharaonix_eachRecords($objReturn->records, $aKeys, $index);
    }

    // Retornamos
    return $objReturn;
}

/**
 * Funcion exacta a pharaonix_query pero como vas a obtener solo una fila, devuelve el resultado ya procesado
 * @param string $sSql Consulta SQL ha realizar
 * @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
 */
function pharaonix_queryOne($sSql)
{
    // Realizamos consulta
    $objRecords = pharaonix_query($sSql, true);

    // Si tenemos resultado
    if ($objRecords->num_rows > 0) {
        $objRecords->records = $objRecords->records[0];
    }

    // Retornamos
    return $objRecords;
}

/**
 * Comprueba si un dato esta en una columna en una tabla especifica en base de datos
 * @param array $aArguments Array con los propiedades del método
 * <code>
 * $aArguments = array(
 *   'VALUE'   => 'valor',    // Valor a comprobar en la columna especifica
 *   'COLUMN'  => 'columna',  // Columna a comprobar
 *   'TABLE'   => 'tabla',    // Nombre de la tabla a comprobar
 *   'WHERE'   => 'where..'  // Condiciones extras si se necesitan
 * );
 * </code>
 * @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
 */
function pharaonix_checkDataColumTable($aArguments = array())
{
    // Variables
    $sValue = array_key_exists('VALUE', $aArguments) ? $aArguments['VALUE'] : false;
    $sColumn = array_key_exists('COLUMN', $aArguments) ? $aArguments['COLUMN'] : false;
    $sTable = array_key_exists('TABLE', $aArguments) ? $aArguments['TABLE'] : false;
    $sWhere = array_key_exists('WHERE', $aArguments) ? $aArguments['WHERE'] : false;

    // Comprobamos si existe el campo
    return pharaonix_queryOne('SELECT * FROM ' . $sTable . ' WHERE ' . $sColumn . ' = "' . $sValue . '" ' . ($sWhere !== false ? $sWhere : ''));
}

/**
 * Comprueba si una columna esta en una tabla especifica en base de datos
 * @param array $aArguments Array con los propiedades del método
 * <code>
 * $aArguments = array(
 *   'COLUMN'  => 'columna',  // Columna a comprobar
 *   'TABLE'   => 'tabla',    // Nombre de la tabla a comprobar
 *   'WHERE'   => 'where..'  // Condiciones extras si se necesitan
 * );
 * </code>
 * @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
 */
function pharaonix_checkColumTable($aArguments = array())
{
    // Variables
    $sColumn = array_key_exists('COLUMN', $aArguments) ? $aArguments['COLUMN'] : false;
    $sTable = array_key_exists('TABLE', $aArguments) ? $aArguments['TABLE'] : false;

    return (pharaonix_queryOne('SHOW COLUMNS FROM ' . $sTable . ' LIKE "' . $sColumn . '"')->num_rows > 0);
}

/**
 * Convertir la consulta pasada por parametro ha array asociativo clave valor segun los dos parametros tambien pasado. Usado para crear arrays choices en los select a traves de una consulta
 * @param string $sSql Consulta a recorrer
 * @param string $sKeykey Clave para el array asociativo
 * @param string $sKeyValue Clave para el valor array asociativo
 * @param bool $aDefault Array para añadir valor por defecto
 * @param int $type
 * @return array
 */
function pharaonix_getArrayAssociativeSql($sSql, $sKeykey, $sKeyValue, $aDefault = false, $type = 0): array
{
    // Variables
    $aReturn = array();

    // Realizamos la consulta
    $aRecords = pharaonix_query($sSql);

    // Recorremos
    while ($aRecord = tep_db_fetch_array($aRecords->records)) {
        if ($type === 0) {
			$aReturn[] = array('id' => $aRecord[$sKeykey], 'text' => $aRecord[$sKeyValue]);
		}elseif ($type === 2) {
			$aReturn[$aRecord[$sKeykey]] = $aRecord;
        }
        else{
			$aReturn[$aRecord[$sKeykey]] = $aRecord[$sKeyValue];
		}

    }

    // Retornamos
    return ($aDefault == false ? $aReturn : array_merge($aDefault, $aReturn));
}
/**
 * Devuelve el número de filas afectadas por una consulta ejecutada con PDO.
 *
 * @param PDOStatement $stmt Consulta ejecutada con PDO.
 * @return int Número de filas afectadas por la consulta.
 */
function tep_db_affected_rows($stmt)
{
	// Verificamos si el parámetro es un objeto PDOStatement
	if ($stmt instanceof PDOStatement) {
		// Usamos rowCount() para obtener el número de filas afectadas
		return $stmt->rowCount();
	}

	// Si no es un PDOStatement, devolvemos 0
	return 0;
}
