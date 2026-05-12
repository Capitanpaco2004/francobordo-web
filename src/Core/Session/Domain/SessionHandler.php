<?php

namespace Oscdenox\Core\Session\Domain;

use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\EntryIdentifier\EntryIdentifierInterface;
use Oscdenox\Core\Session\Infrastructure\Session\SessionCustomerInFileTemp;
use Ramsey\Uuid\Uuid;

final class SessionHandler
{
	private $name;
	private $blockSpider;
	private $cookie;
	private $entryIdentifier;
	private $sessionHandler;
	private $started = false;
	private $spiders = ['abot', 'dbot', 'ebot', 'hbot', 'kbot', 'lbot', 'mbot', 'nbot', 'obot', 'pbot', 'rbot', 'sbot', 'tbot', 'vbot', 'ybot', 'zbot', 'bot.', 'bot/', '_bot', '.bot', '/bot', '-bot', ':bot', '(bot', 'crawl', 'slurp', 'spider', 'seek', 'accoona', 'acoon', 'adressendeutschland', 'ah-ha.com', 'ahoy', 'altavista', 'ananzi', 'anthill', 'appie', 'arachnophilia', 'arale', 'araneo', 'aranha', 'architext', 'aretha', 'arks', 'asterias', 'atlocal', 'atn', 'atomz', 'augurfind', 'backrub', 'bannana_bot', 'baypup', 'bdfetch', 'big brother', 'biglotron', 'bjaaland', 'blackwidow', 'blaiz', 'blog', 'blo.', 'bloodhound', 'boitho', 'booch', 'bradley', 'calif', 'cassandra', 'ccubee', 'cfetch', 'charlotte', 'churl', 'cienciaficcion', 'cmc', 'collective', 'comagent', 'combine', 'computingsite', 'csci', 'curl', 'cusco', 'daumoa', 'deepindex', 'delorie', 'depspid', 'deweb', 'die blinde kuh', 'digger', 'ditto', 'dmoz', 'docomo', 'download express', 'dtaagent', 'dwcp', 'ebiness', 'ebingbong', 'e-collector', 'ejupiter', 'emacs-w3 search engine', 'esther', 'evliya celebi', 'ezresult', 'falcon', 'felix ide', 'ferret', 'fetchrover', 'fido', 'findlinks', 'fireball', 'fish search', 'fouineur', 'funnelweb', 'gazz', 'gcreep', 'genieknows', 'getterroboplus', 'geturl', 'glx', 'goforit', 'golem', 'grabber', 'grapnel', 'gralon', 'griffon', 'gromit', 'grub', 'gulliver', 'hamahakki', 'harvest', 'havindex', 'helix', 'heritrix', 'hku www octopus', 'homerweb', 'htdig', 'html index', 'html_analyzer', 'htmlgobble', 'hubater', 'hyper-decontextualizer', 'ia_archiver', 'infoseek', 'kit_fireball', 'lachesis', 'larbin', 'legs', 'libwww', 'linkalarm', 'link validator', 'linkscan', 'lockon', 'lwp', 'lycos', 'magpie', 'mantraagent', 'mapoftheinternet', 'marvin/', 'mattie', 'mediafox', 'mediapartners', 'mercator', 'merzscope', 'microsoft url control', 'minirank', 'miva', 'mj12', 'mnogosearch', 'moget', 'monster', 'moose', 'motor', 'multitext', 'muncher', 'muscatferret', 'mwd.search', 'myweb', 'najdi', 'nameprotect', 'nationaldirectory', 'nazilla', 'ncsa beta', 'nec-meshexplorer', 'nederland.zoek', 'netcarta webmap engine', 'netmechanic', 'netresearchserver', 'netscoop', 'newscan-online', 'ng/', 'nhse', 'nokia6682/', 'nomad', 'noyona', 'nutch', 'nzexplorer', 'objectssearch', 'occam', 'omni', 'open text', 'openfind', 'openintelligencedata', 'orb search', 'osis-project', 'pack rat', 'pageboy', 'pagebull', 'page_verifier', 'panscient', 'parasite', 'partnersite', 'patric', 'pear.', 'pegasus', 'peregrinator', 'pgp key agent', 'phantom', 'phpdig', 'picosearch', 'piltdownman', 'pimptrain', 'pinpoint', 'pioneer', 'piranha', 'plumtreewebaccessor', 'pogodak', 'poirot', 'pompos', 'poppelsdorf', 'poppi', 'popular iconoclast', 'psycheclone', 'publisher', 'python', 'rambler', 'raven search', 'roach', 'road runner', 'roadhouse', 'robbie', 'robofox', 'robozilla', 'rules', 'salty', 'sbider', 'scooter', 'scoutjet', 'scrubby', 'search.', 'searchprocess', 'semanticdiscovery', 'senrigan', 'sg-scout', "shai'hulud", 'shark', 'shopwiki', 'sidewinder', 'sift', 'silk', 'simmany', 'site searcher', 'site valet', 'sitetech-rover', 'skymob.com', 'sleek', 'smartwit', 'sna-', 'snappy', 'snooper', 'sohu', 'speedfind', 'sphere', 'sphider', 'spinner', 'spyder', 'steeler/', 'suke', 'suntek', 'supersnooper', 'surfnomore', 'sven', 'sygol', 'szukacz', 'tach black widow', 'tarantula', 'templeton', '/teoma', 't-h-u-n-d-e-r-s-t-o-n-e', 'theophrastus', 'titan', 'titin', 'tkwww', 'toutatis', 't-rex', 'tutorgig', 'twiceler', 'twisted', 'ucsd', 'udmsearch', 'url check', 'updated', 'vagabondo', 'valkyrie', 'verticrawl', 'victoria', 'vision-search', 'volcano', 'voyager/', 'voyager-hc', 'w3c_validator', 'w3m2', 'w3mir', 'walker', 'wallpaper', 'wanderer', 'wauuu', 'wavefire', 'web core', 'web hopper', 'web wombat', 'webbandit', 'webcatcher', 'webcopy', 'webfoot', 'weblayers', 'weblinker', 'weblog monitor', 'webmirror', 'webquest', 'webreaper', 'websitepulse', 'websnarf', 'webstolperer', 'webvac', 'webwalk', 'webwatch', 'webwombat', 'webzinger', 'wget', 'whizbang', 'whowhere', 'wild ferret', 'wire', 'worldlight', 'wwwc', 'wwwster', 'xenu', 'xget', 'xift', 'xirq', 'yandex', 'yanga', 'yeti', 'yodao', 'zao/', 'zippp', 'zyborg'];

	public function __construct(string $name, bool $blockSpider, Cookie $cookie, EntryIdentifierInterface $entryIdentifier, SessionHandlerInterface $sessionHandler)
	{
		$this->name = $name;
		$this->blockSpider = $blockSpider;
		$this->cookie = $cookie;
		$this->entryIdentifier = $entryIdentifier;
		$this->sessionHandler = $sessionHandler;
	}

	public function sessionHandler(): SessionHandlerInterface
	{
		return $this->sessionHandler;
	}

	public function hasStarted(): bool
	{
		return $this->started;
	}

	/**
	 * Obtenemos la url actual donde nos encontramos
	 *
	 * @return string $sUrl
	 */
	private function getCurrentUrl()
	{
		// Variables
		$sUrl = 'http';

		if( $_SERVER["HTTPS"] == "on" )
			$sUrl .= "s";

		$sUrl .= "://";

		if( $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443" )
			$sUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		else
			$sUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

		return $sUrl;
	}

	public function start(): bool
	{
		session_set_cookie_params([
			'lifetime' => 0,
			'path' => $this->cookie->path(),
			'domain' => $this->cookie->domain(),
			'secure' => $this->cookie->secure(),
			'httponly' => true,
			'samesite' => $this->cookie->sameSite()
		]);

		// Php por defecto es true y nosotros lo tenemos en false, para que las pasarelas de pago puedan loguearse con el usuario, https://www.php.net/manual/es/session.configuration.php#ini.session.use-only-cookies
		@ini_set('session.use_only_cookies', false);

		if ($this->entryIdentifier->sane() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
			$url = $this->getCurrentUrl();
			$parsed = parse_url($url);

			$query = $parsed['query'];

			parse_str($query, $params);

			unset($params[$this->name]);
			$query = http_build_query($params);

			tep_redirect($parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'] . ($query != '' ? '?' : '') . $query);
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
		if ($this->isRegistered($variable)) {
			unset($_SESSION[$variable]);
		}
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

	public function savePath($path = ''): string
	{
		return $this->sessionHandler->savePath($path);
	}

	public function destroy(): bool
	{
		if (isset($_COOKIE[$this->name()])) {
			$this->cookie->createCookie($this->name(), '', time() - 42000);

			unset($_COOKIE[$this->name()]);
		}

		return $this->hasStarted() ? session_destroy() : true;
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

	private function blockSessionForSpiders(): bool
	{
		if (!$this->blockSpider) {
			return false;
		}

		$user_agent = strtolower(getenv('HTTP_USER_AGENT'));

		if (tep_not_null($user_agent)) {
			foreach ($this->spiders as $spider) {
				if (is_integer(strpos($user_agent, trim($spider)))) {
					return true;
				}
			}
		}

		return false;
	}

	public function exists(string $id): bool
	{
		return $this->sessionHandler->exists($id);
	}

	public function initialize(): bool
	{
		if ($this->started) {
			return false;
		}

		if ($this->blockSessionForSpiders()) {
			return false;
		}

		$this->name($this->name);

		$this->started = true;

		$id = $this->entryIdentifier->id();

		// @TODO el token es solo del customer ya que contiene los valores almacenados en otra tabla y hace join mediante dicho token. Por aqui pasa el admin que nada tiene que ver con token
		if ($this->sessionHandler instanceof SessionCustomerInterface && !($this->sessionHandler instanceof SessionCustomerInFileTemp) && isset($_GET[$this->name]) && !is_null($id)) {
			$row = pharaonix_queryOne('SELECT token FROM customers_session WHERE sesskey = "' . $id .'"');

			if ($row->num_rows > 0) {
				$this->cookie->token = $row->records['token'];
				$this->cookie->sessionId = $id;
			}
			else {
				$id = null;
			}
		}

		if (is_null($id)) {
			if (isset($this->cookie->sessionId)) {
				$this->cookie->logout();
			}

			$this->cookie->sessionId = Uuid::uuid4()->toString();

			$id = $this->cookie->sessionId;
		}

		$this->id($id);

		// @TODO el token es solo del customer ya que contiene los valores almacenados en otra tabla y hace join mediante dicho token. Por aqui pasa el admin que nada tiene que ver con token
		if ($this->sessionHandler instanceof SessionCustomerInterface && !($this->sessionHandler instanceof SessionCustomerInFileTemp)) {
			$this->sessionHandler->token($this->cookie->token);
			$this->sessionHandler->sessionId($this->cookie->sessionId);
		}

		return $this->start();
	}
}
