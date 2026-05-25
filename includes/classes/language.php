<?php

// Existe otra clase `language` (estilo osCommerce viejo) en _admin/includes/classes/language.php.
// Ambas son globales sin namespace; el addon-dependency-injection puede cargar cualquiera de las
// dos rutas en el backoffice y provocar "Cannot redeclare class language". Esta guarda (igual que
// la del admin) hace que la segunda en cargarse no redeclare. El código consumidor tolera ambas.
if (class_exists('language')) return;

class language
{
	public $language = '';
	public $catalogLanguages;
	public $browserLanguages = [
		'ar' => 'ar([-_][[:alpha:]]{2})?|arabic',
		'bg' => 'bg|bulgarian',
		'br' => 'pt[-_]br|brazilian portuguese',
		'ca' => 'ca|catalan',
		'cs' => 'cs|czech',
		'da' => 'da|danish',
		'de' => 'de([-_][[:alpha:]]{2})?|german',
		'el' => 'el|greek',
		'en' => 'en([-_][[:alpha:]]{2})?|english',
		'es' => 'es([-_][[:alpha:]]{2})?|spanish',
		'et' => 'et|estonian',
		'fi' => 'fi|finnish',
		'fr' => 'fr([-_][[:alpha:]]{2})?|french',
		'gl' => 'gl|galician',
		'he' => 'he|hebrew',
		'hu' => 'hu|hungarian',
		'id' => 'id|indonesian',
		'it' => 'it|italian',
		'ja' => 'ja|japanese',
		'ko' => 'ko|korean',
		'ka' => 'ka|georgian',
		'lt' => 'lt|lithuanian',
		'lv' => 'lv|latvian',
		'nl' => 'nl([-_][[:alpha:]]{2})?|dutch',
		'no' => 'no|norwegian',
		'pl' => 'pl|polish',
		'pt' => 'pt([-_][[:alpha:]]{2})?|portuguese',
		'ro' => 'ro|romanian',
		'ru' => 'ru|russian',
		'sk' => 'sk|slovak',
		'sr' => 'sr|serbian',
		'sv' => 'sv|swedish',
		'th' => 'th|thai',
		'tr' => 'tr|turkish',
		'uk' => 'uk|ukrainian',
		'tw' => 'zh[-_]tw|chinese traditional',
		'zh' => 'zh|chinese simplified'
	];

	function __construct($lng = '')
	{
		global $languages_code;

		$this->catalogLanguages = pharaonix_getArrayAssociativeSql('SELECT languages_id as id, name, code, image, directory FROM languages ORDER BY sort_order', 'code', null, false, 2);

		if ($lng === '' && isset($languages_code)) {
			$lng = $languages_code;
		}

		$this->setLanguage($lng);
	}

	public function setRequestLanguage()
	{
		global $language, $language_name, $languages_id, $languages_code;

		if (!tep_session_is_registered('language') || isset($_GET['language']) || empty($language)) {
			if (!tep_session_is_registered('language')) {
				tep_session_register('language');
				tep_session_register('language_name');
				tep_session_register('languages_id');
				tep_session_register('languages_code');
			}

			if (isset($_GET['language']) && tep_not_null($_GET['language'])) {
				$this->setLanguage($_GET['language']);
			} else {
				$this->setLanguage($this->getBrowserLanguage());
			}

			$language = $this->language['directory'];
			$language_name = $this->language['name'];
			$languages_id = $this->language['id'];
			$languages_code = $this->language['code'];
		}
	}

	public function setLanguage($language)
	{
		if (tep_not_null($language) && isset($this->catalogLanguages[$language])) {
			$this->language = $this->catalogLanguages[$language];
		} else {
			$this->language = $this->catalogLanguages[DEFAULT_LANGUAGE];
		}
	}

	public function setLocale()
	{
		$_system_locale_numeric = setlocale(LC_NUMERIC, 0);

		// Prevent LC_ALL from setting LC_NUMERIC to a locale with 1,0 float/decimal values instead of 1.0 (see bug #634)
		setlocale(LC_NUMERIC, $_system_locale_numeric);
		date_default_timezone_set('Europe/Madrid');

		// Funcion de fecha y locale transpasadas aqui ya que en los archivos de idioma solo debemos de tener defines
		if ($this->language['id'] == 3)
		{
			setlocale(LC_TIME, 'es_ES.ISO_8859-1');
			setlocale(LC_CTYPE, 'C');
		} else {
			setlocale(LC_TIME, 'en_US.ISO_8859-1');
		}
	}

	public function getBrowserLanguage(): string
	{
		$browserLanguages = explode(',', getenv('HTTP_ACCEPT_LANGUAGE'));

		for ($i = 0, $n = count($browserLanguages); $i < $n; $i++) {
			foreach ($this->browserLanguages as $key => $value) {
				if (preg_match('/^(' . $value . ')(;q=[0-9]\\.[0-9])?$/i', $browserLanguages[$i]) && isset($this->catalogLanguages[$key])) {
					return $key;
				}
			}
		}

		return DEFAULT_LANGUAGE;
	}
}
