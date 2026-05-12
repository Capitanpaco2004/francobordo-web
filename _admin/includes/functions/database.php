<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2013 osCommerce

  Released under the GNU General Public License
*/

  function tep_db_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD, $database = DB_DATABASE, $link = 'db_link') {
    global $$link;

    if (USE_PCONNECT == 'true') {
      $server = 'p:' . $server;
    }

    $$link = mysqli_connect($server, $username, $password, $database);

    if ( !mysqli_connect_errno() ) {
      mysqli_set_charset($$link, 'utf8');
    }
    
    @mysqli_query($$link, 'set session sql_mode=""');

    return $$link;
  }

 function tep_db_num_fields($string ){
      return mysqli_num_fields($string);  
  }
  function tep_db_close($link = 'db_link') {
    global $$link;

    return mysqli_close($$link);
  }

  function tep_db_error($query, $errno, $error) {
    global $logger;

    if (defined('STORE_DB_TRANSACTIONS') && (STORE_DB_TRANSACTIONS == 'true')) {
      $logger->write('[' . $errno . '] ' . $error, 'ERROR');
    }

    die('<font color="#000000"><strong>' . $errno . ' - ' . $error . '<br /><br />' . $query . '<br /><br /><small><font color="#ff0000">[TEP STOP]</font></small><br /><br /></strong></font>');
  }

  function tep_db_query($query, $link = 'db_link') {
    global $$link, $logger;

    if (defined('STORE_DB_TRANSACTIONS') && (STORE_DB_TRANSACTIONS == 'true')) {
      if (!is_object($logger)) $logger = new logger;
      $logger->write($query, 'QUERY');
    }

    $result = mysqli_query($$link, $query) or tep_db_error($query, mysqli_errno($$link), mysqli_error($$link));

    return $result;
  }

  function tep_db_perform($table, $data, $action = 'insert', $parameters = '', $link = 'db_link') {
    if ($action == 'insert') {
      $query = 'insert into ' . $table . ' (';
      foreach(array_keys($data) as $columns) {
        $query .= $columns . ', ';
      }
      $query = substr($query, 0, -2) . ') values (';
	  foreach($data as $value) {
        switch ((string)$value) {
          case 'now()':
            $query .= 'now(), ';
            break;
          case 'null':
            $query .= 'null, ';
            break;
          default:
            $query .= '\'' . tep_db_input($value) . '\', ';
            break;
        }
      }
      $query = substr($query, 0, -2) . ')';
    } elseif ($action == 'update') {
      $query = 'update ' . $table . ' set ';
      foreach($data as $columns => $value) {
        switch ((string)$value) {
          case 'now()':
            $query .= $columns . ' = now(), ';
            break;
          case 'null':
            $query .= $columns .= ' = null, ';
            break;
          default:
            $query .= $columns . ' = \'' . tep_db_input($value) . '\', ';
            break;
        }
      }
      $query = substr($query, 0, -2) . ' where ' . $parameters;
    }

    return tep_db_query($query, $link);
  }

  function tep_db_fetch_array($db_query) {
	if (!is_null($db_query))  
    return mysqli_fetch_array($db_query, MYSQLI_ASSOC);
  }

  function tep_db_result($result, $row, $field = '') {
    if ( $field === '' ) {
      $field = 0;
    }

    tep_db_data_seek($result, $row);
    $data = tep_db_fetch_array($result);

    return $data[$field];
  }

  function tep_db_num_rows($db_query) {
    return mysqli_num_rows($db_query);
  }

  function tep_db_data_seek($db_query, $row_number) {
    return mysqli_data_seek($db_query, $row_number);
  }

  function tep_db_insert_id($link = 'db_link') {
    global $$link;

    return mysqli_insert_id($$link);
  }

  function tep_db_field_name($db_query, $offset)
  {
	$aInfo = mysqli_fetch_fields( $db_query );

	foreach( $aInfo as $aVal )
	{
		if( $nCont == $offset )
			return $aVal->name;
		++$nCont;
	}
  }

  function tep_db_field_type($db_query, $offset)
  {
	$aInfo = mysqli_fetch_fields( $db_query );

	foreach( $aInfo as $aVal )
	{
		if( $nCont == $offset )
			return $aVal->type;
		++$nCont;
	}
  }
 
  function tep_db_free_result($db_query) {
    return mysqli_free_result($db_query);
  }

  function tep_db_fetch_fields($db_query) {
    return mysqli_fetch_field($db_query);
  }

  function tep_db_output($string) {
    return htmlspecialchars($string);
  }

// To allow tags, either pass (boolean)true for all tags or example (string)'<strong><em>' for certain tags.
  function tep_db_input($string, $link = 'db_link', $allowable_tags = false) {
    global $$link;
    if (function_exists('mysqli_real_escape_string')) {
    return mysqli_real_escape_string($$link, $string);
    } elseif (function_exists('mysql_escape_string')) {
      return mysql_escape_string($string);
    }
    return addslashes($string);
  }

  function tep_db_prepare_input($string) {
global $purifier;
    if (is_string($string)) {
      return $purifier->purify(trim(stripslashes($string)));
    } elseif (is_array($string)) {
		foreach($string as $key => $value) {
        	$string[$key] = tep_db_prepare_input($value);
      	}
      return $string;
    } else {
      return $string;
    }
  }

  function tep_db_affected_rows($link = 'db_link') {
    global $$link;

    return mysqli_affected_rows($$link);
  }

  function tep_db_get_server_info($link = 'db_link') {
    global $$link;

    return mysqli_get_server_info($$link);
  }

	function tep_db_fetch_object($db_query) {
		return mysqli_fetch_object($db_query);
	}
  if ( !function_exists('mysqli_connect') ) {
    define('MYSQLI_ASSOC', MYSQL_ASSOC);

    function mysqli_connect($server, $username, $password, $database) {
      if ( substr($server, 0, 2) == 'p:' ) {
        $link = mysql_pconnect(substr($server, 2), $username, $password);
      } else {
        $link = mysql_connect($server, $username, $password);
      }

      if ( $link ) {
        mysql_select_db($database, $link);
      }

      return $link;
    }

    function mysqli_connect_errno($link = null) {
      if ( is_null($link) ) {
        return mysql_errno();
      }

      return mysql_errno($link);
    }

    function mysqli_connect_error($link = null) {
      if ( is_null($link) ) {
        return mysql_error();
      }

      return mysql_error($link);
    }

    function mysqli_set_charset($link, $charset) {
      if ( function_exists('mysql_set_charset') ) {
        return mysql_set_charset($charset, $link);
      }
    }

    function mysqli_close($link) {
      return mysql_close($link);
    }

    function mysqli_query($link, $query) {
      return mysql_query($query, $link);
    }

    function mysqli_errno($link = null) {
      if ( is_null($link) ) {
        return mysql_errno();
      }

      return mysql_errno($link);
    }

    function mysqli_error($link = null) {
      if ( is_null($link) ) {
        return mysql_error();
      }

      return mysql_error($link);
    }

    function mysqli_fetch_array($query, $type) {
      return mysql_fetch_array($query, $type);
    }

    function mysqli_num_rows($query) {
      return mysql_num_rows($query);
    }

    function mysqli_data_seek($query, $offset) {
      return mysql_data_seek($query, $offset);
    }

    function mysqli_insert_id($link) {
      return mysql_insert_id($link);
    }

    function mysqli_free_result($query) {
      return mysql_free_result($query);
    }

    function mysqli_fetch_field($query) {
      return mysql_fetch_field($query);
    }

    function mysqli_real_escape_string($link, $string) {
      if ( function_exists('mysql_real_escape_string') ) {
        return mysql_real_escape_string($string, $link);
      } elseif ( function_exists('mysql_escape_string') ) {
        return mysql_escape_string($string);
      }

      return addslashes($string);
    }

    function mysqli_affected_rows($link) {
      return mysql_affected_rows($link);
    }

    function mysqli_get_server_info($link) {
      return mysql_get_server_info($link);
    }
  }

function mysqli_field_name($result, $field_offset)
{
    $properties = mysqli_fetch_field_direct($result, $field_offset);
    return is_object($properties) ? $properties->name : null;
}

	/**
	* Devuelve si existe la tabla en base de datos
	* @param string $sTable Tabla a comprobar si existe
	* @return bool con el resultado
	*/
	function pharaonix_checkTableExists($sTable)
	{
		return (pharaonix_queryOne( 'SHOW TABLES LIKE "' . $sTable . '"' )->num_rows == 1 ? true : false);
	}

	/**
	* Devuelve el resultado de una consulta
	* @param mysqli_result $aRecords Objeto mysqli_result a procesar
	* @param array $aKeys Permite indicarle que campos quieres obetner solo de la consulta
	* @return array con los datos de la consulta
	*/
	function pharaonix_eachRecords($aRecords, $aKeys = false)
	{
		// Variables
		$aReturn = array();
		$bOnly = is_array( $aKeys ) && count( $aKeys ) == 1 ? true : false;
		$tools = new util\tools();

		// Recorremos la consulta
		while( $aRecord = tep_db_fetch_array( $aRecords ) )
		{
			if( $aKeys !== false )
			{
				$aRecord = $tools::arrayColumn( $aRecord, $aKeys );

				// Si solo es un registro lo pasamos a string
				if( $bOnly )
					$aRecord = $aRecord[$aKeys[0]];
			}

			// Guardamos
			$aReturn[] = $aRecord;
		}

		// Retornamos
		return $aReturn;
	}

	/**
	* Realiza la consulta pasada como argumento y devuelve el objeto result, ademas del total de filas
	* @param string $sSql Consulta SQL ha realizar
	* @param bool $bResult Si quieres obtener un array con los datos en vez de un objeto mysqli_result
	* @param array $aKeys Permite indicarle que campos quieres obetner solo de la consulta
	* @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
	*/
	function pharaonix_query($sSql, $bResult = false, $aKeys = false)
	{
		// Variables
		$objReturn = new \stdClass();

		// Consulta
		$objReturn->records = tep_db_query( $sSql );

		// Total de filas
		if( is_bool( $objReturn->records ) )
			$objReturn->num_rows = 1;
		else
			$objReturn->num_rows = tep_db_num_rows( $objReturn->records );

		// Si queremos un array como resultado
		if( $bResult )
			$objReturn->records = pharaonix_eachRecords( $objReturn->records, $aKeys );

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
		$objRecords = pharaonix_query( $sSql, true );

		// Si tenemos resultado
		if( $objRecords->num_rows > 0 )
			$objRecords->records = $objRecords->records[0];

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
		$sValue = array_key_exists( 'VALUE', $aArguments ) ? $aArguments['VALUE'] : false;
		$sColumn = array_key_exists( 'COLUMN', $aArguments ) ? $aArguments['COLUMN'] : false;
		$sTable = array_key_exists( 'TABLE', $aArguments ) ? $aArguments['TABLE'] : false;
		$sWhere = array_key_exists( 'WHERE', $aArguments ) ? $aArguments['WHERE'] : false;

		// Comprobamos si existe el campo
		return pharaonix_queryOne( 'SELECT * FROM ' . $sTable . ' WHERE ' . $sColumn . ' = "' .  $sValue . '" ' . ($sWhere !== false ? $sWhere : '') );
	}

	/**
	* Comprueba si una columna esta en una tabla especifica en base de datos
	* @param array $aArguments Array con los propiedades del mÃ©todo
	* <code>
	* $aArguments = array(
	*   'COLUMN'  => 'columna',  // Columna a comprobar
	*   'TABLE'   => 'tabla',    // Nombre de la tabla a comprobar
	*   'WHERE'   => 'where..'  // Condiciones extras si se necesitan
	* );
	* </code>
	* @return stdClass Propiedades records y num_rows para recorrer los datos y el nÃºmero de registros
	*/
	function pharaonix_checkColumTable($aArguments = array())
	{
		// Variables
		$sColumn = array_key_exists( 'COLUMN', $aArguments ) ? $aArguments['COLUMN'] : false;
		$sTable = array_key_exists( 'TABLE', $aArguments ) ? $aArguments['TABLE'] : false;
			
		return (pharaonix_queryOne( 'SHOW COLUMNS FROM ' . $sTable . ' LIKE "' . $sColumn . '"' )->num_rows > 0);
	}

	/**
	* Convertir la consulta pasada por parametro ha array asociativo clave valor segun los dos parametros tambien pasado. Usado para crear arrays choices en los select a traves de una consulta
	* @param string $sSql Consulta a recorrer
	* @param string $sKeykey Clave para el array asociativo
	* @param string $sKeyValue Clave para el valor array asociativo
	* @param bool $aDefault Array para aÃ±adir valor por defecto
	* @return array
	*/
	function pharaonix_getArrayAssociativeSql($sSql, $sKeykey, $sKeyValue, $aDefault = false, $type = 0)
	{
		// Variables
		$aReturn = array();

		// Realizamos la consulta
		$aRecords = pharaonix_query( $sSql );

		// Recorremos
		while( $aRecord = tep_db_fetch_array( $aRecords->records ) )
		{
			if( $type === 0 )
				$aReturn[] = array( 'id' => $aRecord[$sKeykey], 'text' => $aRecord[$sKeyValue] );
			else
				$aReturn[$aRecord[$sKeykey]] = $aRecord[$sKeyValue];
		}

		// Retornamos
		return ($aDefault == false ? $aReturn : $aDefault + $aReturn );
	}
?>
