<?php
/**
 * SalesManago API client (standalone, no Composer).
 *
 * Auth: POST JSON with clientId + apiKey + requestTime (ms) + sha + owner.
 * sha = sha1(apiKey + clientId + apiSecret) — recomputed per request.
 *
 * Reads configuration from SALESMANAGO_* constants (defined automatically by
 * application_top.php from the `configuration` table) unless overridden in $cfg.
 *
 * Usage:
 *   $sm = new SalesManago();
 *   if (!$sm->isConfigured()) return;
 *   $r = $sm->ping();
 *   if (!$r['ok']) error_log('SM: ' . $r['error']);
 *
 * @see https://docs.salesmanago.com/
 */
class SalesManago
{
    private string $endpoint;     // e.g. 'www.salesmanago.pl' (no scheme, no /api)
    private string $clientId;
    private string $apiKey;
    private string $apiSecret;
    private string $owner;
    private int    $timeout;
    private int    $connectTimeout = 2;
    private bool   $verifyTls      = true;

    /** @var string|null Last raw response body for debugging. */
    public ?string $lastRaw = null;

    public function __construct(array $cfg = [])
    {
        $this->endpoint  = trim($cfg['endpoint']  ?? (defined('SALESMANAGO_ENDPOINT')   ? SALESMANAGO_ENDPOINT   : ''));
        $this->endpoint  = preg_replace('#^https?://#i', '', $this->endpoint);
        $this->endpoint  = rtrim($this->endpoint, '/');
        $this->clientId  = trim((string)($cfg['clientId']  ?? (defined('SALESMANAGO_CLIENT_ID')  ? SALESMANAGO_CLIENT_ID  : '')));
        $this->apiKey    = trim((string)($cfg['apiKey']    ?? (defined('SALESMANAGO_API_KEY')    ? SALESMANAGO_API_KEY    : '')));
        $this->apiSecret = trim((string)($cfg['apiSecret'] ?? (defined('SALESMANAGO_API_SECRET') ? SALESMANAGO_API_SECRET : '')));
        $this->owner     = trim((string)($cfg['owner']     ?? (defined('SALESMANAGO_OWNER')      ? SALESMANAGO_OWNER      : '')));
        $this->timeout   = (int)   ($cfg['timeout']   ?? (defined('SALESMANAGO_TIMEOUT') ? (int)SALESMANAGO_TIMEOUT : 5));
        $this->verifyTls = (bool)  ($cfg['verifyTls'] ?? true);
    }

    public function isConfigured(): bool
    {
        return $this->endpoint !== ''
            && $this->clientId !== ''
            && $this->apiKey !== ''
            && $this->apiSecret !== ''
            && $this->owner !== '';
    }

    /** sha1(apiKey + clientId + apiSecret) — SM auth requirement. */
    public function computeSha(): string
    {
        return sha1($this->apiKey . $this->clientId . $this->apiSecret);
    }

    /**
     * Raw POST. Automatically injects auth headers + payload fields.
     *
     * Returns [
     *   'ok'    => bool   true iff HTTP 200 and body.success === true
     *   'http'  => int    HTTP status code (0 on transport error)
     *   'body'  => array|null  decoded JSON
     *   'raw'   => string raw response body
     *   'error' => string|null human-readable error description
     * ]
     */
    public function call(string $path, array $payload = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fail(0, '', 'SalesManago: incomplete configuration');
        }

        // SM requires the auth fields at the TOP level of the payload.
        $payload = array_merge([
            'clientId'    => $this->clientId,
            'apiKey'      => $this->apiKey,
            'requestTime' => (int) round(microtime(true) * 1000),
            'sha'         => $this->computeSha(),
            'owner'       => $this->owner,
        ], $payload);

        $url  = 'https://' . $this->endpoint . '/' . ltrim($path, '/');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $this->fail(0, '', 'SalesManago: failed to encode JSON payload');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json;charset=UTF-8',
            ],
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $raw  = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch) ?: null;
        // No curl_close() — deprecated since PHP 8.0, removed in 8.5+.
        // Handle is freed when $ch goes out of scope.
        unset($ch);

        $this->lastRaw = is_string($raw) ? $raw : null;

        if ($raw === false || $raw === null) {
            return $this->fail(0, '', $err ?: 'curl_exec failed (no response)');
        }

        $body = json_decode($raw, true);
        if (!is_array($body)) {
            return $this->fail($http, $raw, 'Non-JSON response (HTTP ' . $http . ')');
        }

        $ok = ($http === 200) && !empty($body['success']);
        if ($ok) {
            return ['ok' => true, 'http' => $http, 'body' => $body, 'raw' => $raw, 'error' => null];
        }

        // SM puts errors in body.message (array) or body.error
        $msg = '';
        if (!empty($body['message'])) {
            $msg = is_array($body['message']) ? implode('; ', $body['message']) : (string)$body['message'];
        } elseif (!empty($body['error'])) {
            $msg = (string) $body['error'];
        } else {
            $msg = 'HTTP ' . $http;
        }
        return $this->fail($http, $raw, $msg, $body);
    }

    private function fail(int $http, string $raw, string $error, ?array $body = null): array
    {
        return ['ok' => false, 'http' => $http, 'body' => $body, 'raw' => $raw, 'error' => $error];
    }

    // ---------------------------------------------------------------------
    // High-level helpers (each maps 1:1 to an SM endpoint)
    // ---------------------------------------------------------------------

    /**
     * Cheap read-only call to validate credentials.
     * Calls /api/contact/list with the owner email — success=true even if the
     * contact doesn't exist (empty `contacts` array).
     */
    public function ping(): array
    {
        return $this->call('api/contact/list', ['email' => [$this->owner]]);
    }

    /**
     * Create or update a contact.
     * https://docs.salesmanago.com/#contact-upsert
     */
    public function upsertContact(string $email, array $contact = []): array
    {
        $contact = array_merge(['email' => $email], $contact);
        return $this->call('api/contact/upsert', ['contact' => $contact]);
    }

    /**
     * Add an external event (PURCHASE / CART / VISIT / ...) to a contact.
     * https://docs.salesmanago.com/#add-external-event
     */
    public function addExtEvent(string $email, string $type, array $event = []): array
    {
        $event = array_merge([
            'date'                => (int) round(microtime(true) * 1000),
            'contactExtEventType' => strtoupper($type),
        ], $event);
        return $this->call('api/v2/contact/addContactExtEvent', [
            'email'        => $email,
            'contactEvent' => $event,
        ]);
    }

    /**
     * Batch insert of external events.
     * https://docs.salesmanago.com/#batch-insert-of-external-events
     *
     * @param array $events Each item: ['email' => ..., 'contactEvent' => [...]]
     */
    public function batchAddExtEvents(array $events): array
    {
        return $this->call('api/v2/contact/batchInsertContactExtEvent', [
            'eventsData' => $events,
        ]);
    }

    // ---------------------------------------------------------------------
    // Payload builders (pure, testable)
    // ---------------------------------------------------------------------

    /**
     * Build a PURCHASE event payload from an osCommerce order.
     *
     * @param array  $order      Order header (orders_id, date_purchased, total, payment, shipping)
     * @param array  $products   Each item: [products_id, products_model, name, qty, price, category]
     * @param string $location   SM "location" identifier (alphanumeric+_)
     * @param string $idField    'products_id' or 'products_model'
     */
    public static function buildPurchaseEvent(array $order, array $products, string $location = 'francobordo_web', string $idField = 'products_model'): array
    {
        $ids = $qtys = $prices = $cats = [];
        $total = 0.0;
        foreach ($products as $p) {
            $ids[]    = (string) ($p[$idField] ?? $p['products_id'] ?? '');
            $qtys[]   = (string) ($p['qty'] ?? 1);
            $prices[] = number_format((float)($p['price'] ?? 0), 2, '.', '');
            $cats[]   = (string) ($p['category'] ?? '');
            $total   += ((float)($p['qty'] ?? 1)) * ((float)($p['price'] ?? 0));
        }

        $orderDate = !empty($order['date_purchased'])
            ? (int) (strtotime($order['date_purchased']) * 1000)
            : (int) round(microtime(true) * 1000);

        return [
            'date'                => $orderDate,
            'contactExtEventType' => 'PURCHASE',
            'externalId'          => (string) ($order['orders_id'] ?? ''),
            'location'            => $location,
            'value'               => (float) ($order['total'] ?? $total),
            'products'            => implode(',', array_filter($ids)),
            'detail1'             => implode(',', $qtys),
            'detail2'             => implode(',', $prices),
            'detail3'             => implode(',', array_filter($cats)),
            'detail4'             => (string) ($order['payment'] ?? ''),
            'detail5'             => (string) ($order['shipping'] ?? ''),
            'description'         => 'orders_id=' . ($order['orders_id'] ?? ''),
        ];
    }
}
