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
