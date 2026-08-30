<?php
/**
 * SalesManagoQueue — async event queue helper.
 *
 * Used by:
 *   - Emitters (create_account / account_edit / checkout_process) → enqueue()
 *   - Worker (_admin/sm_worker.php) → claim / markSent / markFailed / markDead
 *
 * Design rules:
 *   1. enqueue() NEVER throws. Emitters wrap callers in try/catch anyway,
 *      but this class swallows DB errors silently (we'd rather drop an SM
 *      event than break a customer's checkout).
 *   2. INSERT IGNORE on dedup_key ensures idempotency.
 *   3. Worker uses a uuid worker_token to claim a slice atomically, so
 *      overlapping cron runs don't process the same rows.
 *
 * @see includes/classes/SalesManago.php  (the API client this feeds)
 */

class SalesManagoQueue
{
    public const TYPE_CONTACT_UPSERT = 'CONTACT_UPSERT';
    public const TYPE_PURCHASE       = 'PURCHASE';
    public const TYPE_CART           = 'CART';

    /** True if the integration is globally on (master switch). */
    public static function isEnabled(): bool
    {
        return defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true';
    }

    /** True if the given event type should be emitted right now. */
    public static function shouldSend(string $type): bool
    {
        if (!self::isEnabled()) return false;
        $flag = 'SALESMANAGO_SEND_' . strtoupper($type);
        return defined($flag) && constant($flag) === 'true';
    }

    /**
     * Build the product id exactly as the SM feed does:
     *   variant → "{products_id}-{products_attributes_id}"  (e.g. 346788-44325)
     *   simple  → "{products_id}"
     * @see feedmachine_with_attributes_comparador.php line ~329
     */
    private static function feedProductId(int $productsId, $attributesId): string
    {
        return ($attributesId !== null && (int) $attributesId > 0)
            ? $productsId . '-' . (int) $attributesId
            : (string) $productsId;
    }

    /**
     * Resolve the SM `location` per language so SM can map each language's
     * product feed to the right events.
     *   ES → francobordo_es   (mapea sm-feed-es.xml)
     *   EN → francobordo_en   (mapea sm-feed-en.xml)
     *
     * Priority: explicit languages_id (1=en, 3=es) → else infer from country
     * (ES or unknown → es; any other country → en).
     *
     * @param int|null $languageId osCommerce languages_id (1=en, 3=es, 0/null=unknown)
     * @param string   $countryIso ISO-2 country code for the fallback
     */
    public static function resolveLocation(?int $languageId, string $countryIso = ''): string
    {
        $base = defined('SALESMANAGO_LOCATION') ? (string) SALESMANAGO_LOCATION : 'francobordo_web';
        $base = preg_replace('/_web$/', '', $base);
        if ($base === '' || $base === null) $base = 'francobordo';

        if ($languageId === 1) {
            $lang = 'en';
        } elseif ($languageId === 3) {
            $lang = 'es';
        } else {
            $iso  = strtoupper(trim($countryIso));
            $lang = ($iso === 'ES' || $iso === '') ? 'es' : 'en';
        }
        return $base . '_' . $lang;
    }

    /**
     * Append a new event to the queue.
     * Returns the inserted id, 0 if dedup hit (row already queued), null on error.
     *
     * @param string $type        CONTACT_UPSERT / PURCHASE / CART / OTHER
     * @param string $dedup_key   Unique key per logical event (idempotency)
     * @param array  $payload     Will be JSON-encoded for storage
     */
    public static function enqueue(string $type, string $dedup_key, array $payload): ?int
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) return null;

            $sql = "INSERT IGNORE INTO sm_event_queue
                      (event_type, dedup_key, payload, status, created_at, updated_at)
                    VALUES
                      ('" . tep_db_input($type) . "',
                       '" . tep_db_input($dedup_key) . "',
                       '" . tep_db_input($json) . "',
                       'pending', NOW(), NOW())";
            tep_db_query($sql);
            return (int) tep_db_insert_id();
        } catch (\Throwable $e) {
            @error_log('[SalesManagoQueue::enqueue] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Atomically claim up to $limit pending rows for this worker run.
     * Returns the rows as array.
     */
    public static function claim(string $worker_token, int $limit = 50): array
    {
        $tok = tep_db_input($worker_token);
        $lim = max(1, (int) $limit);

        // Mark them with our worker token in a single UPDATE — atomic.
        tep_db_query("UPDATE sm_event_queue
                      SET status='sending',
                          worker_token='" . $tok . "',
                          attempts=attempts+1,
                          updated_at=NOW()
                      WHERE status='pending'
                        AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                      ORDER BY id ASC
                      LIMIT " . $lim);

        $rows = [];
        $res = tep_db_query("SELECT id, event_type, dedup_key, payload, attempts
                             FROM sm_event_queue
                             WHERE worker_token='" . $tok . "'
                               AND status='sending'
                             ORDER BY id ASC");
        while ($r = tep_db_fetch_array($res)) {
            $rows[] = $r;
        }
        return $rows;
    }

    public static function markSent(int $id): void
    {
        tep_db_query("UPDATE sm_event_queue
                      SET status='sent', sent_at=NOW(), worker_token=NULL,
                          last_error=NULL, last_http_code=NULL,
                          next_attempt_at=NULL
                      WHERE id=" . (int) $id);
    }

    /**
     * Mark a failed attempt. If max attempts reached → status='dead'.
     * Otherwise schedules next attempt with exponential backoff.
     */
    public static function markFailed(int $id, int $attempts, int $httpCode, string $error): void
    {
        $maxAttempts = defined('SALESMANAGO_MAX_ATTEMPTS')
            ? (int) SALESMANAGO_MAX_ATTEMPTS : 8;
        $base = defined('SALESMANAGO_BACKOFF_BASE')
            ? (int) SALESMANAGO_BACKOFF_BASE : 30;

        $newStatus = ($attempts >= $maxAttempts) ? 'dead' : 'pending';
        $delay     = min(3600, $base * (1 << max(0, $attempts - 1))); // exp backoff, cap 1h

        $err = mb_substr($error, 0, 500);
        $sql = "UPDATE sm_event_queue
                SET status='" . $newStatus . "',
                    worker_token=NULL,
                    last_error='" . tep_db_input($err) . "',
                    last_http_code=" . (int) $httpCode . ",
                    next_attempt_at=" . ($newStatus === 'pending'
                        ? "DATE_ADD(NOW(), INTERVAL " . (int) $delay . " SECOND)"
                        : "NULL") . "
                WHERE id=" . (int) $id;
        tep_db_query($sql);
    }

    /** Stats for the admin observability panel. */
    public static function stats(): array
    {
        $out = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0, 'total' => 0];
        $res = tep_db_query("SELECT status, COUNT(*) AS n FROM sm_event_queue GROUP BY status");
        while ($r = tep_db_fetch_array($res)) {
            $out[$r['status']] = (int) $r['n'];
            $out['total'] += (int) $r['n'];
        }
        return $out;
    }

    /** Last N events with error. */
    public static function recentFailures(int $limit = 10): array
    {
        $rows = [];
        $res = tep_db_query("SELECT id, event_type, dedup_key, attempts, last_error,
                                    last_http_code, status, updated_at
                             FROM sm_event_queue
                             WHERE status IN ('failed','dead') OR last_error IS NOT NULL
                             ORDER BY updated_at DESC
                             LIMIT " . max(1, (int) $limit));
        while ($r = tep_db_fetch_array($res)) {
            $rows[] = $r;
        }
        return $rows;
    }

    /**
     * Drain the sm_contact_resync table (populated by the customers_group_change_sm
     * trigger on ANY customers_group_id change — manual admin edit, approval, cron,
     * import, etc.). For each pending customer, enqueue a fresh CONTACT_UPSERT so SM
     * gets the updated customer_group property.
     *
     * Called by sm_worker.php each tick. Returns number of customers processed.
     */
    public static function drainContactResync(int $limit = 200): int
    {
        try {
            // If contact sync is off, leave rows so we don't lose the change.
            if (!self::shouldSend('CONTACT_UPSERT')) return 0;

            $ids = [];
            $res = tep_db_query("SELECT customers_id FROM sm_contact_resync
                                 ORDER BY changed_at ASC LIMIT " . max(1, (int) $limit));
            while ($r = tep_db_fetch_array($res)) $ids[] = (int) $r['customers_id'];
            if (empty($ids)) return 0;

            foreach ($ids as $cid) {
                self::emitContactUpsert($cid, false);
            }
            tep_db_query("DELETE FROM sm_contact_resync
                          WHERE customers_id IN (" . implode(',', $ids) . ")");
            return count($ids);
        } catch (\Throwable $e) {
            @error_log('[SalesManagoQueue::drainContactResync] ' . $e->getMessage());
            return 0;
        }
    }

    /** Re-queue all dead events for another round. */
    public static function reviveDead(): int
    {
        $st = tep_db_query("UPDATE sm_event_queue
                      SET status='pending', attempts=0, last_error=NULL,
                          last_http_code=NULL, next_attempt_at=NULL, worker_token=NULL
                      WHERE status='dead'");
        return (int) tep_db_affected_rows($st); // requiere el PDOStatement (firma PDO)
    }

    // -----------------------------------------------------------------
    // High-level emit helpers (call from osCommerce hooks)
    // -----------------------------------------------------------------

    /**
     * Build & enqueue a CONTACT_UPSERT for a customer_id.
     * Reads the customer + their default address from DB so the caller
     * doesn't need to pass them.
     *
     * @param int  $customer_id
     * @param bool $isNew   true on signup, false on profile edit
     */
    public static function emitContactUpsert(int $customer_id, bool $isNew = false): void
    {
        try {
            if (!self::shouldSend('CONTACT_UPSERT'))   return;
            if ($customer_id <= 0)                     return;

            if (!class_exists('SalesManago')) {
                require_once DIR_FS_CATALOG . 'includes/classes/SalesManago.php';
            }

            $sql = "SELECT c.*, ab.entry_telephone, ab.entry_street_address,
                           ab.entry_postcode, ab.entry_city, ab.entry_state,
                           co.countries_iso_code_2, z.zone_code,
                           cg.customers_group_name
                    FROM customers c
                    LEFT JOIN address_book ab ON ab.address_book_id = c.customers_default_address_id
                    LEFT JOIN countries     co ON co.countries_id = ab.entry_country_id
                    LEFT JOIN zones         z  ON z.zone_id = ab.entry_zone_id
                    LEFT JOIN customers_groups cg ON cg.customers_group_id = c.customers_group_id
                    WHERE c.customers_id = " . (int) $customer_id . "
                    LIMIT 1";
            $row = tep_db_fetch_array(tep_db_query($sql));
            if (!$row || empty($row['customers_email_address'])) return;
            if (!empty($row['sm_excluded']))                     return; // bounced/etc

            $payload = SalesManago::buildContactPayload(
                $row, $row,
                (string) ($row['countries_iso_code_2'] ?? ''),
                (string) ($row['zone_code'] ?? ''),
                $isNew
            );

            // Hash of payload → dedup only when content actually changes
            $hash  = substr(md5(json_encode($payload)), 0, 12);
            $dedup = 'CONTACT_UPSERT:' . $customer_id . ':' . $hash;

            self::enqueue(self::TYPE_CONTACT_UPSERT, $dedup, $payload);
        } catch (\Throwable $e) {
            @error_log('[SalesManagoQueue::emitContactUpsert] ' . $e->getMessage());
        }
    }

    /**
     * Synchronously create/refresh the SM contact and set the `smclient`
     * cookie with the returned contactId. This links the browser's behavioural
     * tracking (sm.js) to the known contact.
     *
     * Runs in the user's HTTP request (login / signup / edit). Short timeout +
     * try/catch so it never blocks the page. Only acts when the cookie is
     * absent — i.e. at most once per browser. The async emitContactUpsert()
     * remains the guaranteed data-sync path.
     *
     * Must be called BEFORE any output (it sends a Set-Cookie header).
     */
    public static function setIdentityCookie(int $customer_id): void
    {
        try {
            if (!self::isEnabled())              return;
            if (!self::shouldSend('CONTACT_UPSERT')) return;
            if (!empty($_COOKIE['smclient']))    return; // already linked
            if ($customer_id <= 0)               return;
            if (headers_sent())                  return;

            if (!class_exists('SalesManago')) {
                require_once DIR_FS_CATALOG . 'includes/classes/SalesManago.php';
            }

            $sql = "SELECT c.*, ab.entry_telephone, ab.entry_street_address,
                           ab.entry_postcode, ab.entry_city, ab.entry_state,
                           co.countries_iso_code_2, z.zone_code,
                           cg.customers_group_name
                    FROM customers c
                    LEFT JOIN address_book ab ON ab.address_book_id = c.customers_default_address_id
                    LEFT JOIN countries     co ON co.countries_id = ab.entry_country_id
                    LEFT JOIN zones         z  ON z.zone_id = ab.entry_zone_id
                    LEFT JOIN customers_groups cg ON cg.customers_group_id = c.customers_group_id
                    WHERE c.customers_id = " . (int) $customer_id . " LIMIT 1";
            $row = tep_db_fetch_array(tep_db_query($sql));
            if (!$row || empty($row['customers_email_address'])) return;
            if (!empty($row['sm_excluded']))                     return;

            $payload = SalesManago::buildContactPayload(
                $row, $row,
                (string) ($row['countries_iso_code_2'] ?? ''),
                (string) ($row['zone_code'] ?? ''),
                false
            );

            // Short timeout: cookie linkage is best-effort, must not block login.
            $sm = new SalesManago(['timeout' => 3]);
            $r  = $sm->call('api/contact/upsert', $payload);

            if ($r['ok'] && !empty($r['body']['contactId'])) {
                $contactId = (string) $r['body']['contactId'];
                $domain = '.' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'francobordo.com');
                setcookie('smclient', $contactId, [
                    'expires'  => time() + 60 * 60 * 24 * 3650, // ~10 años
                    'path'     => '/',
                    'domain'   => $domain,
                    'secure'   => true,
                    'httponly' => false,   // sm.js (JS) necesita leerla
                    'samesite' => 'Lax',
                ]);
                $_COOKIE['smclient'] = $contactId; // disponible ya en este request
            }
        } catch (\Throwable $e) {
            @error_log('[SalesManagoQueue::setIdentityCookie] ' . $e->getMessage());
        }
    }

    /**
     * Build & enqueue a CART (abandoned) event for a customer_id.
     * Reads the current customers_basket + linked products. Dedup hashes the
     * cart contents so the same cart doesn't get re-enqueued every 15 min.
     */
    public static function emitAbandonedCart(int $customer_id): ?int
    {
        try {
            if (!self::shouldSend('CART')) return null;
            if ($customer_id <= 0)         return null;

            // Customer + email guard (+ language/country for location)
            $cust = tep_db_fetch_array(tep_db_query(
                "SELECT c.customers_email_address, c.sm_excluded, c.customers_language_id,
                        co.countries_iso_code_2 AS country_iso
                 FROM customers c
                 LEFT JOIN address_book ab ON ab.address_book_id = c.customers_default_address_id
                 LEFT JOIN countries     co ON co.countries_id = ab.entry_country_id
                 WHERE c.customers_id = " . (int) $customer_id . " LIMIT 1"));
            if (!$cust || empty($cust['customers_email_address'])) return null;
            if (!empty($cust['sm_excluded']))                      return null;

            $idField = defined('SALESMANAGO_PRODUCT_ID_FIELD')
                ? (string) SALESMANAGO_PRODUCT_ID_FIELD
                : 'products_id';

            // Read basket — products_id may have attribute syntax like "123{34}567"
            // We strip everything from `{` onwards to get the plain numeric id.
            $sql = "SELECT
                      CAST(SUBSTRING_INDEX(cb.products_id, '{', 1) AS UNSIGNED) AS plain_pid,
                      cb.products_id           AS raw_pid,
                      cb.customers_basket_quantity AS qty,
                      cb.final_price           AS price,
                      cb.customers_basket_modified AS modified
                    FROM customers_basket cb
                    WHERE cb.customers_id = " . (int) $customer_id . "
                      AND cb.customers_basket_quantity > 0";
            $rows = [];
            $res  = tep_db_query($sql);
            while ($r = tep_db_fetch_array($res)) $rows[] = $r;
            if (empty($rows)) return null;

            // Look up the configured ID field per product
            $pidList = array_unique(array_filter(array_map(fn($r) => (int) $r['plain_pid'], $rows)));
            if (empty($pidList)) return null;
            $idMap = [];
            $modelRes = tep_db_query("SELECT products_id, products_model, products_price
                                       FROM products
                                       WHERE products_id IN (" . implode(',', $pidList) . ")");
            while ($pr = tep_db_fetch_array($modelRes)) {
                $idMap[(int) $pr['products_id']] = [
                    'model' => (string) $pr['products_model'],
                    'price' => (float) $pr['products_price'],
                ];
            }

            $ids = $qtys = $prices = [];
            $value     = 0.0;
            $latestMod = '';
            $attrCache = [];
            foreach ($rows as $r) {
                $pid = (int) $r['plain_pid'];
                $info = $idMap[$pid] ?? null;
                if (!$info) continue;

                // Parse basket products_id like "346788{533}14615" → product/option/value
                // and resolve products_attributes_id so the id matches the feed
                // ({products_id}-{products_attributes_id} for variants).
                $attrId = null;
                if (preg_match('/^\d+\{(\d+)\}(\d+)/', (string) $r['raw_pid'], $m)) {
                    $optionId = (int) $m[1];
                    $valueId  = (int) $m[2];
                    $cacheKey = $pid . ':' . $optionId . ':' . $valueId;
                    if (!array_key_exists($cacheKey, $attrCache)) {
                        $aRow = tep_db_fetch_array(tep_db_query(
                            "SELECT products_attributes_id FROM products_attributes
                             WHERE products_id = " . $pid . "
                               AND options_id = " . $optionId . "
                               AND options_values_id = " . $valueId . " LIMIT 1"));
                        $attrCache[$cacheKey] = $aRow ? (int) $aRow['products_attributes_id'] : null;
                    }
                    $attrId = $attrCache[$cacheKey];
                }

                $idValue = self::feedProductId($pid, $attrId);
                $price   = ((float) $r['price']) > 0 ? (float) $r['price'] : $info['price'];

                $ids[]    = $idValue;
                $qtys[]   = (string) (int) $r['qty'];
                $prices[] = number_format($price, 2, '.', '');
                $value   += ((int) $r['qty']) * $price;

                if ($r['modified'] && $r['modified'] > $latestMod) $latestMod = $r['modified'];
            }
            if (empty($ids)) return null;

            $location = self::resolveLocation(
                isset($cust['customers_language_id']) ? (int) $cust['customers_language_id'] : null,
                (string) ($cust['country_iso'] ?? '')
            );
            $eventTs  = $latestMod ? (int) (strtotime($latestMod) * 1000) : (int) round(microtime(true) * 1000);

            $event = [
                'date'                => $eventTs,
                'contactExtEventType' => 'CART',
                'externalId'          => 'cart-' . $customer_id . '-' . substr(md5(implode(',', $ids) . '|' . implode(',', $qtys)), 0, 8),
                'location'            => $location,
                'value'               => round($value, 2),
                'products'            => implode(',', $ids),
                'detail1'             => implode(',', $qtys),
                'detail2'             => implode(',', $prices),
                'description'         => 'Abandoned cart (' . count($ids) . ' items)',
            ];

            $payload = [
                'email'        => $cust['customers_email_address'],
                'contactEvent' => $event,
            ];

            // Dedup by customer + cart-contents hash → same cart not re-enqueued
            $hash  = substr(md5(implode(',', $ids) . '|' . implode(',', $qtys)), 0, 12);
            $dedup = 'CART:' . $customer_id . ':' . $hash;

            return self::enqueue(self::TYPE_CART, $dedup, $payload);
        } catch (\Throwable $e) {
            @error_log('[SalesManagoQueue::emitAbandonedCart] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build & enqueue a PURCHASE event for an orders_id.
     *
     * @param int $orders_id
     */
    public static function emitPurchase(int $orders_id): void
    {
        try {
            if (!self::shouldSend('PURCHASE')) return;
            if ($orders_id <= 0)               return;

            if (!class_exists('SalesManago')) {
                require_once DIR_FS_CATALOG . 'includes/classes/SalesManago.php';
            }

            // Order header (+ language/country for location)
            $headerSql = "SELECT o.orders_id, o.customers_id, o.customers_email_address,
                                 o.date_purchased, o.payment_method, o.customers_language_id,
                                 co.countries_iso_code_2 AS country_iso
                          FROM orders o
                          LEFT JOIN customers   c  ON c.customers_id = o.customers_id
                          LEFT JOIN address_book ab ON ab.address_book_id = c.customers_default_address_id
                          LEFT JOIN countries   co ON co.countries_id = ab.entry_country_id
                          WHERE o.orders_id = " . (int) $orders_id . " LIMIT 1";
            $order = tep_db_fetch_array(tep_db_query($headerSql));
            if (!$order || empty($order['customers_email_address'])) return;

            // Total
            $totQ = tep_db_query("SELECT value FROM orders_total
                                   WHERE orders_id = " . (int) $orders_id . "
                                     AND class = 'ot_total'
                                   LIMIT 1");
            $totRow = tep_db_fetch_array($totQ);
            $total  = $totRow ? (float) $totRow['value'] : 0.0;

            // Shipping method (class ot_shipping)
            $shipQ = tep_db_query("SELECT title FROM orders_total
                                    WHERE orders_id = " . (int) $orders_id . "
                                      AND class = 'ot_shipping'
                                    LIMIT 1");
            $shipRow = tep_db_fetch_array($shipQ);
            $shipping = $shipRow ? (string) $shipRow['title'] : '';

            // Products — resolve variant id to match the feed
            //   ({products_id}-{products_attributes_id} for variants).
            $idField = defined('SALESMANAGO_PRODUCT_ID_FIELD')
                ? (string) SALESMANAGO_PRODUCT_ID_FIELD
                : 'products_id';
            $prodSql = "SELECT op.orders_products_id, op.products_id, op.products_model AS model,
                               op.products_quantity AS qty, op.products_price AS price,
                               op.products_name AS name,
                               pa.products_attributes_id AS attr_id
                        FROM orders_products op
                        LEFT JOIN orders_products_attributes opa
                               ON opa.orders_products_id = op.orders_products_id
                        LEFT JOIN products_attributes pa
                               ON pa.products_id        = op.products_id
                              AND pa.options_id         = opa.products_options_id
                              AND pa.options_values_id  = opa.products_options_values_id
                        WHERE op.orders_id = " . (int) $orders_id . "
                        GROUP BY op.orders_products_id";
            $products = [];
            $pq = tep_db_query($prodSql);
            while ($p = tep_db_fetch_array($pq)) {
                $feedId = self::feedProductId((int) $p['products_id'], $p['attr_id']);
                $products[] = [
                    'products_id'    => $feedId,   // already feed-formatted (with -attrid if variant)
                    'products_model' => $p['model'],
                    'qty'            => (int) $p['qty'],
                    'price'          => (float) $p['price'],
                    'category'       => '',
                ];
            }

            $orderArr = [
                'orders_id'      => (int) $order['orders_id'],
                'date_purchased' => $order['date_purchased'],
                'total'          => $total,
                'payment'        => (string) $order['payment_method'],
                'shipping'       => $shipping,
            ];

            $location = self::resolveLocation(
                isset($order['customers_language_id']) ? (int) $order['customers_language_id'] : null,
                (string) ($order['country_iso'] ?? '')
            );

            $event = SalesManago::buildPurchaseEvent($orderArr, $products, $location, $idField);

            $payload = [
                'email'        => $order['customers_email_address'],
                'contactEvent' => $event,
            ];

            self::enqueue(self::TYPE_PURCHASE, 'PURCHASE:' . (int) $orders_id, $payload);
        } catch (\Throwable $e) {
            @error_log('[SalesManagoQueue::emitPurchase] ' . $e->getMessage());
        }
    }
}
