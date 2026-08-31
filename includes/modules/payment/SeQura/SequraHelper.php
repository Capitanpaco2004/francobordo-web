<?php

class SequraHelper {

	static function getBuilder() {
		require_once(DIR_FS_SEQURA . 'SequraBuilderAbstract.php');
		require_once(DIR_FS_SEQURA . 'SequraBuilderOSC.php');
		$path = DIR_FS_SEQURA.'custom/SequraBuilder.php';
		if (file_exists($path)) {
			require_once($path);
			return new SequraBuilder(MODULE_PAYMENT_SEQURA_MERCHANT);
		}
		return new SequraBuilderOSC(MODULE_PAYMENT_SEQURA_MERCHANT);
	}

	static function getClient() {
		require_once(DIR_FS_SEQURA . 'SequraClient.php');
		return new SequraClient(MODULE_PAYMENT_SEQURA_USER, MODULE_PAYMENT_SEQURA_PASS, MODULE_PAYMENT_SEQURA_ENDPOINT);
	}

	static function sign($value) {
		return hash_hmac('sha256', $value, MODULE_PAYMENT_SEQURA_PASS);
	}

	/* #FB-SEQURA-SIG ----------------------------------------------------
	   Firma ampliada para pay-with-sequra.php (pago de presupuesto).

	   La clave y LOS DOS EXTREMOS son nuestros: SeQura no firma nada, solo
	   devuelve tal cual el blob `notification_parameters` que le mandamos en
	   la solicitud. Por eso podemos ampliar la firma unilateralmente sin
	   romper ningun contrato externo.

	   Se firma sid|oID|importe_en_centimos|ts: un par (sid,firma) valido deja
	   de servir para confirmar OTRO pedido ni OTRO importe. La firma sign(sid)
	   a secas no ataba ninguna de las dos cosas.
	   ------------------------------------------------------------------ */
	const NOTIFY_VERSION = 'v2';
	const NOTIFY_MAX_AGE = 86400; /* 24 h */

	static function payPayload($sid, $oID, $amount_cents, $ts) {
		return implode('|', array(self::NOTIFY_VERSION, (string)$sid, (int)$oID, (int)$amount_cents, (int)$ts));
	}

	static function signPay($sid, $oID, $amount_cents, $ts) {
		return self::sign(self::payPayload($sid, $oID, $amount_cents, $ts));
	}

	/* true SOLO si la notificacion trae una firma nuestra valida para ESE oID.
	   Obligatoria: si falta cualquier campo, es false. Sin rama opcional. */
	static function verifyPay($oID) {
		$sid = isset($_POST['sid'])       && is_string($_POST['sid'])       ? $_POST['sid']       : '';
		$sig = isset($_POST['signature']) && is_string($_POST['signature']) ? $_POST['signature'] : '';
		$amt = isset($_POST['amt'])       && is_scalar($_POST['amt'])       ? (int)$_POST['amt']  : -1;
		$ts  = isset($_POST['ts'])        && is_scalar($_POST['ts'])        ? (int)$_POST['ts']   : 0;
		if ($sid === '' || $sig === '' || $amt < 0 || $ts <= 0) {
			return false;
		}
		if (abs(time() - $ts) > self::NOTIFY_MAX_AGE) {
			return false;
		}
		return hash_equals(self::signPay($sid, $oID, $amt, $ts), $sig);
	}

	/* Importe del pedido en centimos, para firmarlo y para contrastarlo.

	   OJO: NO se puede usar $order->info['total'] con un pedido cargado de la
	   base de datos. En esa rama order.php lo rellena con el TEXTO formateado
	   de orders_total ("82,20&euro;"), no con un numero: multiplicarlo en PHP 8
	   trunca en la coma (82 en vez de 82,20) y ademas suelta un warning.
	   La columna numerica es orders_total.value, que order.php si expone en
	   $order->totals. Devuelve -1 si no hay ot_total: sin importe no se firma. */
	static function orderAmountCents($order) {
		if (!isset($order->totals) || !is_array($order->totals)) {
			return -1;
		}
		foreach ($order->totals as $ot) {
			if (isset($ot['class']) && $ot['class'] === 'ot_total') {
				$cv = isset($order->info['currency_value']) ? (float)$order->info['currency_value'] : 1.0;
				if ($cv <= 0) { $cv = 1.0; }
				return (int)round((float)$ot['value'] * $cv * 100);
			}
		}
		return -1;
	}

	/* Unico cuerpo de error para todos los rechazos: no revela si el pedido
	   existe, en que estado esta ni de quien es. */
	static function forbid() {
		if (!headers_sent()) {
			http_response_code(403);
			header('Content-Type: text/plain; charset=utf-8');
		}
		exit('Forbidden');
	}

	/**
	 * @param $file
	 * @param $data
	 * @return mixed
	 *
	 * Simple template alternative.
	 * If file.inc not found in custom folder use the one in default
	 */
	static function render($file, $data) {
		$path = self::getPath('view/'.$file . '.tpl');
		return self::parse(file_get_contents($path),$data);
	}

	static function parse($content,$data){
		$parsed = $content;
		//Parse includes
		preg_match_all('/\{include [\s]*([^\s^\}]*)[^\{]*}/x',$parsed,$includes,PREG_SET_ORDER);
		foreach ($includes as $include){
			$parsed = str_replace($include[0],self::render($include[1],$data),$parsed);
		}
		foreach($data as $key => $value){
			$parsed = str_replace('{'.$key.'}',$value,$parsed);
		}
		/*This way I can add variables {key} in languages too*/
		if($parsed != $content){
			$parsed = self::parse($parsed, $data);
		}
		return $parsed;
	}

	static function getPath($file) {
		$path = DIR_FS_SEQURA.'custom/' . $file;
		if (!file_exists($path)) {
			$path = str_replace('custom/', '', $path);
		}
		return $path;
	}

	static function getWsPath($file) {
		$path = self::getPath($file);
		return str_replace(DIR_FS_CATALOG,DIR_WS_HTTP_CATALOG,$path);
	}

	static function pp3_instalment_fee($total_amount) {
		if ($total_amount < 201) return 3;
		if ($total_amount < 401) return 5;
		if ($total_amount < 601) return 7;
		if ($total_amount < 801) return 8;
		if ($total_amount < 1001) return 10;
		return 12;
	}

	static function pp3_min_instalment($total_amount) {
		return min(1100,$total_amount)/12 + self::pp3_instalment_fee($total_amount);
	}
}
