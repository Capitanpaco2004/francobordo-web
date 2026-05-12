<?php
// Alias
namespace util;

// Librerias
use DateTimeInterface;
use DateTimeImmutable;

/**
 * Clase donde se encuentra herramientas para las fechas, comparas fechas, converti fechas, etc
 */
class date
{
	/**
	 * Transforma un objeto date en string
	 * @param DateTimeInterface $date
	 * @return string
	 **/
	public static function dateToString(DateTimeInterface $date): string
	{
		return $date->format(DateTimeInterface::ATOM);
	}

	/**
	 * Transforma un string a date
	 * @param string $date
	 * @return string
	 **/
	public static function stringToDate(string $date): DateTimeImmutable
	{
		return new DateTimeImmutable($date);
	}

	/**
	 * Comprueba si eres mayor de la edad pasado por argumento
	 * @param date $sDate Fecha a comprobar
	 * @param date $nDate Años para comprobar
	 * @return bool
	 **/
	public static function greaterDate($sDate, $nDate): bool
	{
		try {
			$dateToday = new \DateTime();
			$dateBirthday = \DateTime::createFromFormat('d/m/Y', $sDate);

			if (!$dateBirthday) {
				return false; // fecha inválida
			}

			$interval = $dateToday->diff($dateBirthday);
			return $interval->y >= $nDate;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Pattern para la fecha
	 * @var array
	 */
	private static $aPatternDate = array(
		'espanol' => array('/^(\d{2})-(\d{2})-(\d{4})/', array(3, 2, 1)),
		'english' => array('/^(\d{2})-(\d{2})-(\d{4})/', array(3, 1, 2)),
		'standar' => array('/^(\d{4})-(\d{2})-(\d{2})/', array(1, 2, 3))
	);

	/**
	 * Comprueba si la fecha pasada por argumento es de un idioma
	 * @param string $sDate
	 * @param string $sLanguage
	 * @return bool
	 */
	public static function checkDateLanguage($sDate, $sLanguage)
	{
		// Verificar si $sDate es nulo o vacío antes de usar preg_match
		if (empty($sDate)) {
			return false;
		}


		// Si la fecha coincide con el idioma
		if (preg_match(self::$aPatternDate[$sLanguage][0], $sDate)) {
			return true;
		}

		// Retornamos
		return false;
	}

	/**
	 * Cambia la fecha pasada por argumento
	 * @param string $sDate
	 * @param string $sLanguage
	 * @param string $sFormat
	 * @return bool
	 */
	public static function changeDate($sDate, $sLanguage, $sFormat)
	{
		// Si la fecha es nula o vacía, retornar un string vacío
		if (empty($sDate)) {
			return '';
		}
		
		// Obtenemos el pattern
		$aPattern = self::$aPatternDate[$sLanguage];

		// Obtenemos la fecha
		preg_match_all($aPattern[0], $sDate, $aMatches);

		// Componemos la fecha
		$sReturn = str_replace(array('y', 'm', 'd'), array($aMatches[$aPattern[1][0]][0], $aMatches[$aPattern[1][1]][0], $aMatches[$aPattern[1][2]][0]), $sFormat);

		// Concatenemos por si tuviera hora
		if (preg_match('/ /i', $sDate))
			$sReturn .= ' ' . preg_replace('/^.+ /i', '', $sDate);

		// Retornamos
		return $sReturn;
	}

	public static function dateRaw($date, $reverse = false): string
	{
		global $languages_id;

		if ($languages_id == 3) {
			if ($reverse)
				return substr($date, 0, 2) . substr($date, 3, 2) . substr($date, 6, 4);
			else
				return substr($date, 6, 4) . substr($date, 3, 2) . substr($date, 0, 2);
		} else {

			if ($reverse)
				return substr($date, 3, 2) . substr($date, 0, 2) . substr($date, 6, 4);
			else
				return substr($date, 6, 4) . substr($date, 0, 2) . substr($date, 3, 2);
		}
	}
}
