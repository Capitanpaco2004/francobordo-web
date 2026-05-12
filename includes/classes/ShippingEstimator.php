<?php
namespace util;

class ShippingEstimator
{
	/**
	 * Devuelve una predicción de envío
	 */
	public static function getEstimate(
		bool $detailed = false,
		bool $asText = true,
			 $hoursToReception = false,
			 $module = false
	) {
		global $customerCore, $customer_id;

		// CP y ciudad inicial
		$city = 'Madrid';
		$cp   = '28070';

		if ($customerCore->hasLogin()) {
			if (!array_key_exists('customer_cp', $_SESSION)) {
				$aAddress = tep_db_query(
					'SELECT ab.entry_postcode, z.zone_name
                     FROM address_book ab
                     INNER JOIN zones z ON (ab.entry_zone_id = z.zone_id)
                     WHERE ab.address_book_id = "' . $_SESSION['customer_default_address_id'] . '"'
				);
				$aAddress = tep_db_fetch_array($aAddress);
				$cp   = $aAddress['entry_postcode'];
				$city = $aAddress['zone_name'];
			} else {
				$cp = $_SESSION['customer_cp'];
			}
		}

		$days       = 1;
		$prediction = true;

		// Ciudades especiales
		if ($city == 'Baleares') {
			$days = 3;
		} elseif (in_array($city, ['Las Palmas', 'Ceuta', 'Melilla'])) {
			$prediction = false;
		} else {
			// Buscar en tabla prediction_cp
			$res = tep_db_query('SELECT prediction FROM shipping_prediction_cp WHERE cp = "' . $cp . '"');
			if (tep_db_num_rows($res) > 0) {
				$row = tep_db_fetch_array($res);
				switch ($row['prediction']) {
					case 'diary':    $days = 1; break;
					case '+24':      $days = 2; break;
					case 'no-predict':
					case 'consult':  $days = 1; break;
				}
			} elseif ($cp != '') {
				tep_db_query('INSERT INTO shipping_prediction_cp VALUES ("' . $cp . '", "consult")');
			}
		}

		// Fecha de envío base
		$delivery = self::addHours(date('Y-m-d'), (date('H') >= MODULE_ESTIMATED_SHIP_TIMES ? 24 : 0));
		$delivery = self::adjustWeekendAndHolidays($delivery, $module);

		// Fecha de recepción
		if ($hoursToReception !== false) {
			$days += ($hoursToReception / 24);
		}

		$reception = self::addHours(
			$delivery['year'] . '-' . $delivery['month'] . '-' . $delivery['day'],
			24 * $days
		);
		$reception = self::adjustWeekendAndHolidays($reception, $module);

		if ($asText) {
			if ($prediction) {
				return '<p>Compra antes de las <span>' . MODULE_ESTIMATED_SHIP_TIMES . ':00h</span> de ' .
					(date('H') >= MODULE_ESTIMATED_SHIP_TIMES ? 'mañana' : 'hoy') .
					' y recibirás tu pedido el <span>' .
					self::dateToSpanish(
						date('l j \d\e F',
							 strtotime($reception['year'] . '-' . $reception['month'] . '-' . $reception['day']))
					) . '</span>.</p>';
			}
			return '<p>No se ha podido realizar una predicción de envío.</p>';
		}

		return $reception;
	}

	/**
	 * Construye el texto de predicción en base a la clase CSS del producto
	 */
	/**
	 * Construye el texto de predicción en base a la clase CSS del producto
	 */
	public static function buildText(string $class): string
	{
		// Bajo pedido
		if (preg_match('/\bprdt-bjpdd\b/i', $class)) {
			return '<span class="cl2">' .
				sprintf(TEXT_ENTREGA_SUPR, '<b>30 ' . SPECIALS_CUENTA_ATRAS_DIAS . '</b>') .
				'</span>';
		}

		// Agotado
		if (preg_match('/\bprdt-agtd\b/i', $class)) {
			return '<span class="cl2">' . TEXT_SIN_STOCK . '</span>';
		}

		// Texto corto inicial y horas de predicción
		$shortText = TEXT_ENTREGA_24_2; // fallback 24/48h
		$nAdd1 = 0;
		$nAdd2 = 24;

		if (preg_match('/\bprdt-4dias\b/i', $class)) {
			$shortText = sprintf(TEXT_ENTREGA_EN, '<b>2-4 días</b>');
			$nAdd1 = 24 * 2;
			$nAdd2 = 24 * 6;
		} elseif (preg_match('/\bprdt-5dias\b/i', $class)) {
			$shortText = sprintf(TEXT_ENTREGA_EN, '<b>7-10 días</b>');
			$nAdd1 = 24 * 8;
			$nAdd2 = 24 * 13;
		}

		// Calculamos estimación larga
		$aEstimate1 = self::getEstimate(true, false, $nAdd1);
		$aEstimate2 = self::getEstimate(true, false, $nAdd2);

		if ($aEstimate1['date'] === $aEstimate2['date']) {
			$aEstimate2 = self::addHours(
				$aEstimate2['year'].'-'.$aEstimate2['month'].'-'.$aEstimate2['day'],
				24
			);
		}

		$longText = str_replace(
			['%s1', '%s2'],
			[
				self::dateToSpanish(
					date('l j ' . SHIPPING_PREDICTION_FROM . ' F',
						 strtotime($aEstimate1['year'].'-'.$aEstimate1['month'].'-'.$aEstimate1['day']))
				),
				self::dateToSpanish(
					date('l j ' . SHIPPING_PREDICTION_FROM . ' F',
						 strtotime($aEstimate2['year'].'-'.$aEstimate2['month'].'-'.$aEstimate2['day']))
				)
			],
			SHIPPING_PREDICTION_BUY_NOW
		);

		// Texto extra
		$infoExtra = '<p class="exceptoPred">'
			. SHIPPING_PREDICTION_EXCEPT
			. '<a href="shipping_estimate_more_info.php" rel="nofollow" class="mgp-ajax" '
			. 'title="' . SHIPPING_PREDICTION_MORE_INFO . '" style="display:block;">(+ info.)</a>'
			. '</p>';

		return '<span>' . $shortText . '</span><br><span class="cl2">' . $longText . '.</span>' . $infoExtra;
	}


	/**
	 * Ajusta sábados, domingos y festivos
	 */
	private static function adjustWeekendAndHolidays(array $date, $module = false): array
	{
		while (true) {
			$isHoliday = tep_db_query(
				'SELECT * FROM shipping_prediction_calendar
                 WHERE calendar_day = "' . $date['day'] . '"
                 AND (calendar_month = "' . $date['month'] . '" OR calendar_month IS NULL OR calendar_month = "")
                 AND (calendar_year = "' . $date['year'] . '" OR calendar_year IS NULL OR calendar_year = "")'
			);

			if (tep_db_num_rows($isHoliday) > 0) {
				$date = self::addHours($date['year'].'-'.$date['month'].'-'.$date['day'], 24);
				continue;
			}

			$dow = date('N', mktime(0,0,0, $date['month'],$date['day'],$date['year']));
			if ($dow == 6 && $module != 'seurnacional') {
				$date = self::addHours($date['year'].'-'.$date['month'].'-'.$date['day'], 48);
			} elseif ($dow == 7) {
				$date = self::addHours($date['year'].'-'.$date['month'].'-'.$date['day'], 24);
			} else {
				break;
			}
		}

		return $date;
	}

	/**
	 * Añade horas a una fecha
	 */
	public static function addHours(string $date, int $hours): array
	{
		$date = strtotime('+' . $hours . ' hour', strtotime($date));
		return [
			'date'  => date('d-m-Y', $date),
			'day'   => date('d', $date),
			'month' => date('m', $date),
			'year'  => date('Y', $date),
		];
	}

	/**
	 * Traduce fecha al español
	 */
	public static function dateToSpanish(string $date): string
	{
		global $languages_id;

		if ($languages_id == 3) {
			$date = str_replace(
				['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
				['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'],
				$date
			);
			$date = str_replace(
				['January','February','March','April','May','June','July','August','September','October','November','December'],
				['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'],
				$date
			);
		}
		return $date;
	}
}
