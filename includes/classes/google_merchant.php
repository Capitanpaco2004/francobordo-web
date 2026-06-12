<?php
/**
 * Cliente ligero de Google Merchant API v1 (sucesora de Content API for Shopping,
 * que Google apaga el 18/08/2026). Sin dependencias externas: OAuth2 de service
 * account con JWT RS256 firmado vía openssl + curl.
 *
 * Config en /home/francobordo/google_merchant_config.php (FUERA del docroot,
 * mismo patrón que seur_credentials.php / correos_credentials.php):
 *
 *   return array(
 *     'account_id'           => '7605527',
 *     'service_account_json' => '/home/francobordo/google_merchant_sa.json',
 *     'token_cache'          => '/home/francobordo/.gm_token_cache.json',
 *     'backup_dir'           => '/home/francobordo/gm_backups',
 *   );
 *
 * El service account (creado en Google Cloud Console, API "Merchant API"
 * habilitada) tiene que estar añadido en Merchant Center > Configuración >
 * Personas y acceso con rol ADMINISTRADOR (shippingSettings:insert lo exige).
 *
 * Uso:
 *   $gm = new google_merchant();
 *   if (!$gm->configured()) { ... $gm->error() ... }
 *   $r = $gm->getShippingSettings();      // ['code'=>200,'data'=>[...],'raw'=>...]
 *   $r = $gm->insertShippingSettings($settings);
 */
class google_merchant {

    const CONFIG_FILE = '/home/francobordo/google_merchant_config.php';
    const API_BASE    = 'https://merchantapi.googleapis.com/accounts/v1/';
    const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    const SCOPE       = 'https://www.googleapis.com/auth/content';

    private $config   = null;
    private $sa       = null;     // service account json decodificado
    private $error    = '';
    private $timeout  = 30;

    public function __construct($configFile = self::CONFIG_FILE) {
        if (!is_file($configFile)) {
            $this->error = 'Falta el fichero de configuración ' . $configFile;
            return;
        }
        $cfg = include $configFile;
        if (!is_array($cfg) || empty($cfg['account_id']) || empty($cfg['service_account_json'])) {
            $this->error = 'Configuración inválida en ' . $configFile . ' (faltan account_id / service_account_json)';
            return;
        }
        if (!is_file($cfg['service_account_json'])) {
            $this->error = 'Falta el JSON del service account: ' . $cfg['service_account_json'];
            return;
        }
        $sa = json_decode((string)@file_get_contents($cfg['service_account_json']), true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            $this->error = 'JSON de service account ilegible o sin client_email/private_key';
            return;
        }
        $this->config = $cfg;
        $this->sa     = $sa;
    }

    public function configured() { return is_array($this->config); }
    public function error()      { return $this->error; }
    public function accountId()  { return $this->configured() ? (string)$this->config['account_id'] : ''; }
    public function saEmail()    { return is_array($this->sa) ? (string)$this->sa['client_email'] : ''; }
    public function backupDir()  {
        return ($this->configured() && !empty($this->config['backup_dir']))
            ? rtrim($this->config['backup_dir'], '/') : '/home/francobordo/gm_backups';
    }

    /* ------------------------------------------------------------------ *
     *  OAuth2 service account (JWT bearer)                                *
     * ------------------------------------------------------------------ */

    /** Devuelve un access token válido (cacheado en fichero) o false. */
    public function accessToken() {
        if (!$this->configured()) return false;

        $cacheFile = !empty($this->config['token_cache'])
            ? $this->config['token_cache'] : '/home/francobordo/.gm_token_cache.json';

        if (is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c) && !empty($c['token']) && !empty($c['exp']) && $c['exp'] - 120 > time()) {
                return (string)$c['token'];
            }
        }

        $now    = time();
        $header = self::b64url(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
        $claims = self::b64url(json_encode(array(
            'iss'   => $this->sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        )));
        $signInput = $header . '.' . $claims;

        $pkey = openssl_pkey_get_private($this->sa['private_key']);
        if ($pkey === false) { $this->error = 'private_key del service account no válida (openssl)'; return false; }
        $sig = '';
        if (!openssl_sign($signInput, $sig, $pkey, 'sha256WithRSAEncryption')) {
            $this->error = 'openssl_sign falló: ' . openssl_error_string();
            return false;
        }
        $jwt = $signInput . '.' . self::b64url($sig);

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            )),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cerr = curl_error($ch);
        // sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)

        if ($raw === false) { $this->error = 'curl token: ' . $cerr; return false; }
        $data = json_decode((string)$raw, true);
        if ($code !== 200 || empty($data['access_token'])) {
            $this->error = 'Token HTTP ' . $code . ': ' . substr((string)$raw, 0, 400);
            return false;
        }

        $exp = time() + (int)(isset($data['expires_in']) ? $data['expires_in'] : 3600);
        @file_put_contents($cacheFile, json_encode(array('token' => $data['access_token'], 'exp' => $exp)));
        @chmod($cacheFile, 0600);

        return (string)$data['access_token'];
    }

    /* ------------------------------------------------------------------ *
     *  HTTP genérico contra la Merchant API                               *
     * ------------------------------------------------------------------ */

    /**
     * @param string $method GET|POST
     * @param string $path   relativo a API_BASE ("accounts/123/shippingSettings") o URL
     *                       absoluta para otras sub-APIs (reports/v1, products/v1...)
     * @param array|null $body se serializa a JSON
     * @return array ['code'=>int, 'data'=>array|null, 'raw'=>string, 'error'=>string]
     */
    public function request($method, $path, $body = null) {
        $out = array('code' => 0, 'data' => null, 'raw' => '', 'error' => '');

        $token = $this->accessToken();
        if ($token === false) { $out['error'] = $this->error; return $out; }

        $url = preg_match('#^https?://#', $path) ? $path : self::API_BASE . ltrim($path, '/');
        $ch  = curl_init($url);
        $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');
        $opts = array(
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        );
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw  = curl_exec($ch);
        $out['code'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cerr = curl_error($ch);

        if ($raw === false) { $out['error'] = 'curl: ' . $cerr; return $out; }
        $out['raw']  = (string)$raw;
        $out['data'] = json_decode($out['raw'], true);
        if ($out['code'] >= 400) {
            $msg = '';
            if (is_array($out['data']) && isset($out['data']['error']['message'])) {
                $msg = $out['data']['error']['message'];
            }
            $out['error'] = 'HTTP ' . $out['code'] . ($msg !== '' ? ': ' . $msg : '');
        }
        return $out;
    }

    /** Datos de la cuenta — sirve como "probar conexión". */
    public function getAccount() {
        return $this->request('GET', 'accounts/' . rawurlencode($this->accountId()));
    }

    /** Configuración de envíos actual (incluye el etag necesario para insertar). */
    public function getShippingSettings() {
        return $this->request('GET', 'accounts/' . rawurlencode($this->accountId()) . '/shippingSettings');
    }

    /** Reemplaza TODA la configuración de envíos (pasar el recurso completo con etag fresco). */
    public function insertShippingSettings($settings) {
        return $this->request('POST', 'accounts/' . rawurlencode($this->accountId()) . '/shippingSettings:insert', $settings);
    }

    /** reports:search (Merchant Center Query Language). Devuelve TODAS las filas paginando. */
    public function reportSearch($query, $maxRows = 5000) {
        $url  = 'https://merchantapi.googleapis.com/reports/v1/accounts/' . rawurlencode($this->accountId()) . '/reports:search';
        $rows = array();
        $tok  = '';
        do {
            $body = array('query' => $query, 'pageSize' => 1000);
            if ($tok !== '') $body['pageToken'] = $tok;
            $r = $this->request('POST', $url, $body);
            if ($r['code'] !== 200) return $r;   // propaga el error tal cual
            foreach ((array)($r['data']['results'] ?? array()) as $row) $rows[] = $row;
            $tok = (string)($r['data']['nextPageToken'] ?? '');
        } while ($tok !== '' && count($rows) < $maxRows);
        return array('code' => 200, 'data' => array('results' => $rows), 'raw' => '', 'error' => '');
    }

    /* ------------------------------------------------------------------ *
     *  Utilidades                                                         *
     * ------------------------------------------------------------------ */

    public static function b64url($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

    /** 4.78 € -> 4780000 micros */
    public static function eur2micros($eur) { return (int)round(((float)$eur) * 1000000); }

    /** micros -> euros float (admite string del API; -1 = infinito → null) */
    public static function micros2eur($micros) {
        $m = (float)$micros;
        if ($m < 0) return null;
        return $m / 1000000;
    }
}
