<?php

namespace util\authentication\Sessions;

use Ramsey\Uuid\Uuid;

class Sessions
{
	private static $instance;
	private $started;
	private $cookie;
	private $spiders = ['abot', 'dbot', 'ebot', 'hbot', 'kbot', 'lbot', 'mbot', 'nbot', 'obot', 'pbot', 'rbot', 'sbot', 'tbot', 'vbot', 'ybot', 'zbot', 'bot.', 'bot/', '_bot', '.bot', '/bot', '-bot', ':bot', '(bot', 'crawl', 'slurp', 'spider', 'seek', 'accoona', 'acoon', 'adressendeutschland', 'ah-ha.com', 'ahoy', 'altavista', 'ananzi', 'anthill', 'appie', 'arachnophilia', 'arale', 'araneo', 'aranha', 'architext', 'aretha', 'arks', 'asterias', 'atlocal', 'atn', 'atomz', 'augurfind', 'backrub', 'bannana_bot', 'baypup', 'bdfetch', 'big brother', 'biglotron', 'bjaaland', 'blackwidow', 'blaiz', 'blog', 'blo.', 'bloodhound', 'boitho', 'booch', 'bradley', 'calif', 'cassandra', 'ccubee', 'cfetch', 'charlotte', 'churl', 'cienciaficcion', 'cmc', 'collective', 'comagent', 'combine', 'computingsite', 'csci', 'curl', 'cusco', 'daumoa', 'deepindex', 'delorie', 'depspid', 'deweb', 'die blinde kuh', 'digger', 'ditto', 'dmoz', 'docomo', 'download express', 'dtaagent', 'dwcp', 'ebiness', 'ebingbong', 'e-collector', 'ejupiter', 'emacs-w3 search engine', 'esther', 'evliya celebi', 'ezresult', 'falcon', 'felix ide', 'ferret', 'fetchrover', 'fido', 'findlinks', 'fireball', 'fish search', 'fouineur', 'funnelweb', 'gazz', 'gcreep', 'genieknows', 'getterroboplus', 'geturl', 'glx', 'goforit', 'golem', 'grabber', 'grapnel', 'gralon', 'griffon', 'gromit', 'grub', 'gulliver', 'hamahakki', 'harvest', 'havindex', 'helix', 'heritrix', 'hku www octopus', 'homerweb', 'htdig', 'html index', 'html_analyzer', 'htmlgobble', 'hubater', 'hyper-decontextualizer', 'ia_archiver', 'infoseek', 'kit_fireball', 'lachesis', 'larbin', 'legs', 'libwww', 'linkalarm', 'link validator', 'linkscan', 'lockon', 'lwp', 'lycos', 'magpie', 'mantraagent', 'mapoftheinternet', 'marvin/', 'mattie', 'mediafox', 'mediapartners', 'mercator', 'merzscope', 'microsoft url control', 'minirank', 'miva', 'mj12', 'mnogosearch', 'moget', 'monster', 'moose', 'motor', 'multitext', 'muncher', 'muscatferret', 'mwd.search', 'myweb', 'najdi', 'nameprotect', 'nationaldirectory', 'nazilla', 'ncsa beta', 'nec-meshexplorer', 'nederland.zoek', 'netcarta webmap engine', 'netmechanic', 'netresearchserver', 'netscoop', 'newscan-online', 'ng/', 'nhse', 'nokia6682/', 'nomad', 'noyona', 'nutch', 'nzexplorer', 'objectssearch', 'occam', 'omni', 'open text', 'openfind', 'openintelligencedata', 'orb search', 'osis-project', 'pack rat', 'pageboy', 'pagebull', 'page_verifier', 'panscient', 'parasite', 'partnersite', 'patric', 'pear.', 'pegasus', 'peregrinator', 'pgp key agent', 'phantom', 'phpdig', 'picosearch', 'piltdownman', 'pimptrain', 'pinpoint', 'pioneer', 'piranha', 'plumtreewebaccessor', 'pogodak', 'poirot', 'pompos', 'poppelsdorf', 'poppi', 'popular iconoclast', 'psycheclone', 'publisher', 'python', 'rambler', 'raven search', 'roach', 'road runner', 'roadhouse', 'robbie', 'robofox', 'robozilla', 'rules', 'salty', 'sbider', 'scooter', 'scoutjet', 'scrubby', 'search.', 'searchprocess', 'semanticdiscovery', 'senrigan', 'sg-scout', "shai'hulud", 'shark', 'shopwiki', 'sidewinder', 'sift', 'silk', 'simmany', 'site searcher', 'site valet', 'sitetech-rover', 'skymob.com', 'sleek', 'smartwit', 'sna-', 'snappy', 'snooper', 'sohu', 'speedfind', 'sphere', 'sphider', 'spinner', 'spyder', 'steeler/', 'suke', 'suntek', 'supersnooper', 'surfnomore', 'sven', 'sygol', 'szukacz', 'tach black widow', 'tarantula', 'templeton', '/teoma', 't-h-u-n-d-e-r-s-t-o-n-e', 'theophrastus', 'titan', 'titin', 'tkwww', 'toutatis', 't-rex', 'tutorgig', 'twiceler', 'twisted', 'ucsd', 'udmsearch', 'url check', 'updated', 'vagabondo', 'valkyrie', 'verticrawl', 'victoria', 'vision-search', 'volcano', 'voyager/', 'voyager-hc', 'w3c_validator', 'w3m2', 'w3mir', 'walker', 'wallpaper', 'wanderer', 'wauuu', 'wavefire', 'web core', 'web hopper', 'web wombat', 'webbandit', 'webcatcher', 'webcopy', 'webfoot', 'weblayers', 'weblinker', 'weblog monitor', 'webmirror', 'webquest', 'webreaper', 'websitepulse', 'websnarf', 'webstolperer', 'webvac', 'webwalk', 'webwatch', 'webwombat', 'webzinger', 'wget', 'whizbang', 'whowhere', 'wild ferret', 'wire', 'worldlight', 'wwwc', 'wwwster', 'xenu', 'xget', 'xift', 'xirq', 'yandex', 'yanga', 'yeti', 'yodao', 'zao/', 'zippp', 'zyborg'];

	public static function getInstance(?int $expire = null): Sessions
	{
		if (!(self::$instance instanceof self)) {
			$expire = $expire ?? 1400;

			self::$instance = new self($expire);
		}

		return self::$instance;
	}

	public function __construct(int $expire)
	{
		global $cookieCore;

		$this->started = false;
		$this->cookie = $cookieCore;

		if (STORE_SESSIONS == 'mysql') {
			session_set_save_handler(new SessionsMysql($expire), true);
		}
	}

	public function hasStarted(): bool
	{
		return $this->started;
	}

	public function start(): bool
	{
		$sane_session_id = true;

		if (isset($_GET[$this->name()])) {
			if ((SESSION_FORCE_COOKIE_USE == 'True') || (preg_match('/^[a-zA-Z0-9,-]+$/', $_GET[$this->name()]) == false)) {
				unset($_GET[$this->name()]);

				$sane_session_id = false;
			}
		}

		if (isset($_POST[$this->name()])) {
			if ((SESSION_FORCE_COOKIE_USE == 'True') || (preg_match('/^[a-zA-Z0-9,-]+$/', $_POST[$this->name()]) == false)) {
				unset($_POST[$this->name()]);

				$sane_session_id = false;
			}
		}

		if (isset($_COOKIE[$this->name()]) && preg_match('/^[a-zA-Z0-9,-]+$/', $_COOKIE[$this->name()]) == false) {
			$this->cookie->createCookie($this->name(), '', time() - 42000);

			unset($_COOKIE[$this->name()]);

			$sane_session_id = false;
		}

		if ($sane_session_id == false) {
			tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'NONSSL', false));
		}

		return session_start();
	}

	public function register($variable): bool
	{
		if ($this->started == true) {
			if (!isset($GLOBALS[$variable])) {
				$GLOBALS[$variable] = null;
			}

			$_SESSION[$variable] =& $GLOBALS[$variable];
		}

		return false;
	}

	public function isRegistered($variable): bool
	{
		return isset($_SESSION) && array_key_exists($variable, $_SESSION);
	}

	public function unregister($variable): void
	{
		unset($_SESSION[$variable]);
	}

	public function id($sessid = ''): string
	{
		if (!empty($sessid)) {
			return session_id($sessid);
		} else {
			return session_id();
		}
	}

	public function name($name = ''): string
	{
		if (!empty($name)) {
			return session_name($name);
		} else {
			return session_name();
		}
	}

	public function destroy(): bool
	{
		if (isset($_COOKIE[$this->name()])) {
			$this->cookie->createCookie($this->name(), '', time() - 42000);

			unset($_COOKIE[$this->name()]);
		}

		return $this->hasStarted() ? session_destroy() : true;
	}

	public function savePath($path = ''): string
	{
		if (STORE_SESSIONS != 'mysql') {
			if (!empty($path)) {
				return session_save_path($path);
			} else {
				return session_save_path();
			}
		}

		return '';
	}

	public function recreate(): void
	{
		global $SID;

		$old_id = session_id();

		session_regenerate_id(true);

		if (!empty($SID)) {
			$SID = $this->name() . '=' . $this->id();
		}

		tep_whos_online_update_session_id($old_id, $this->id());
	}

	public function initialize()
	{
		global $request_type;

		if ($this->started) {
			return;
		}

		if (SESSION_BLOCK_SPIDERS == 'True') {
			$user_agent = strtolower(getenv('HTTP_USER_AGENT'));
			$spider_flag = false;

			if (tep_not_null($user_agent)) {
				foreach ($this->spiders as $spider) {
					if (is_integer(strpos($user_agent, trim($spider)))) {
						$spider_flag = true;
						break;
					}
				}
			}

			if ($spider_flag == false) {
				$this->started = true;
			}
		} else {
			$this->started = true;
		}

		if ($this->started) {
			$this->name('osCsid');
			$this->savePath(SESSION_WRITE_DIRECTORY);

			if (isset($_POST[$this->name()])) {
				$this->id($_POST[$this->name()]);
			} elseif ($request_type == 'SSL' && isset($_GET[$this->name()])) {
				$this->id($_GET[$this->name()]);
			} elseif (isset($this->cookie->sessionId)) {
				$this->id($this->cookie->sessionId);
			} else {
				$this->cookie->sessionId = Uuid::uuid4()->toString();
				$this->id($this->cookie->sessionId);
			}

			$this->start();
		}
	}
}

