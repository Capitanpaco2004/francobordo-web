<?php
// Alias
namespace util;

/**
 * Clase con herramientas para manipular arrays, como ordenar, extraer o buscar valores.
 */
class arrays
{
	/**
	 * Retorna el primer key del array.
	 * @param array $arr Array donde buscaremos.
	 * @return int|string|null
	 */
	public static function keyFirst(array $arr): int|string|null
	{
		foreach ($arr as $key => $unused) {
			return $key;
		}
		return null;
	}

	/**
	 * Busca un valor en un array asociativo dentro de una clave específica.
	 * @param array $aArray Array donde buscaremos.
	 * @param string $sKey Clave del array asociativo donde buscar.
	 * @param mixed $sSearch Valor a buscar.
	 * @param bool $bReturnArray Si true, devuelve array con las claves encontradas; si false, devuelve solo la primera clave encontrada.
	 * @return array|int|string|false
	 */
	public static function getKeySearchValueAssociatve(array $aArray, string $sKey, mixed $sSearch, bool $bReturnArray = true): array|int|string|false
	{
		$filtered = array_filter($aArray, fn($a) => is_array($a) && isset($a[$sKey]) && $a[$sKey] == $sSearch);

		if (count($filtered) === 1 && !$bReturnArray) {
			return array_key_first($filtered);
		}

		return count($filtered) > 0 ? array_keys($filtered) : ($bReturnArray ? [] : false);
	}

	/**
	 * Implode recursivo para concatenar valores de un array anidado.
	 * @param array $array Array de entrada.
	 * @param string $glue Separador de elementos.
	 * @param bool $includeKeys Incluir claves en la concatenación.
	 * @param bool $trimAll Eliminar espacios en blanco.
	 * @return string
	 */
	public static function implodeRecursive(array $array, string $glue = ',', bool $includeKeys = false, bool $trimAll = false): string
	{
		$sReturn = '';

		foreach ($array as $key => $value) {
			if (is_object($value)) {
				continue;
			}
			$includeKeys and $sReturn .= $key . $glue;
			if (is_array($value)) {
				$sReturn .= self::implodeRecursive($value, $glue, $includeKeys, $trimAll);
			} else {
				$sReturn .= (string) $value . $glue;
			}
		}

		// Elimina el último $glue
		return rtrim($sReturn, $glue);
	}

	/**
	 * Extrae valores de un array asociativo.
	 * @param array $aArray Array fuente.
	 * @param string|array $keys Claves a extraer.
	 * @return array
	 */
	public static function arrayColumn(array $aArray, string|array $keys): array
	{
		$keys = (array) $keys;
		return array_map(fn($item) => array_intersect_key($item, array_flip($keys)), $aArray);
	}

	/**
		* Comprobamos en el array en una columna especifica si existe el patron que estamos buscando
		* @param array Array donde buscar
		* @param string $sKeySearch Columna donde buscar
		* @param string $sValueSearch
		* @return bool Retornara true o false si ha sido encontrado
	 */
	public static function checkKeyValueArray(array $aArraySearch, string $sKeySearch, mixed $sValueSearch): bool
	{
		foreach ($aArraySearch as $sValue) {
			if (is_array($sValue) && self::checkKeyValueArray($sValue, $sKeySearch, $sValueSearch)) {
				return true;
			}
			if (isset($sValue[$sKeySearch]) && $sValue[$sKeySearch] == $sValueSearch) {
				return true;
			}
		}
		return false;
	}

	/**
		* Elimina elementos del array como segundo parametro permite un array o un conjunto de parametros
		* @param array $aArray
		* @param string|array
	 */
	public static function delete(array &$aArray, string|array ...$keys): void
	{
		$keys = isset($keys[0]) && is_array($keys[0]) ? $keys[0] : $keys;
		foreach ($keys as $key) {
			unset($aArray[$key]);
		}
	}

	/**
		* Retorna el valor del key
		* @param array Array donde buscar
		* @param string $sKeySearch Columna a buscar
		* @param string $valueDefault Valor por defecto si no existe
		* @param bool $bDelete Eliminar del array el registro si existe
		* @return string Retornara el valor del key o el valor por defecto si no ha sido encontrado
	 */
	public static function getValueByKey(array &$aArraySearch, string $sKeySearch, mixed $valueDefault = '', bool $bDelete = false): mixed
	{
		if (isset($aArraySearch[$sKeySearch])) {
			$valueDefault = $aArraySearch[$sKeySearch];
			if ($bDelete) {
				unset($aArraySearch[$sKeySearch]);
			}
		}
		return $valueDefault;
	}

	/**
		* Realiza una combinacion del array pasado como argumento
		* @param array $items Array para hacer la combinacion
		* @param array $return Array con todas las combinaciones donde se guardara
	 */
	public static function combinations(array $items, array &$return, array $perms = []): array
	{
		if (empty($items)) {
			$return[join(',', $perms)] = '';
		} else {
			for ($i = count($items) - 1; $i >= 0; --$i) {
				$newitems = $items;
				$newperms = $perms;
				list($foo) = array_splice($newitems, $i, 1);
				array_unshift($newperms, $foo);
				self::combinations($newitems, $return, $newperms);
			}
		}
		return $return;
	}

	/**
		 * Cambia el nombre de un key especifico
		 * @param array Array donde buscar
		 * @param string $oldKey Key a remplazar
		 * @param string $newKey Key nueva por la que remplazar
		 * @return array Retornara el array con el key nuevo
	 */
	public static function changeKey(array $array, string $oldKey, string $newKey): array
	{
		if (!isset($array[$oldKey])) {
			return $array;
		}
		$keys = array_keys($array);
		$keys[array_search($oldKey, $keys, true)] = $newKey;
		return array_combine($keys, $array);
	}
}
?>
