<?php
/**
 * SalesManago — async event queue worker.
 *
 * URL: /_admin/sm_worker.php?token=<SALESMANAGO_CRON_TOKEN>
 *
 * Called by cron every minute via curl. Auth is by `token` GET param
 * (matches `SALESMANAGO_CRON_TOKEN`). The file is also added to
 * `$excludedFilesFromLogin` in application_top.php so the admin-session
 * check is skipped.
 *
 * Pipeline:
 *   1. Claim up to N pending events atomically (UPDATE … LIMIT N).
 *   2. For each, dispatch to the right SM endpoint.
 *   3. Mark sent / failed (with exp backoff) / dead.
 *
 * @see includes/classes/SalesManagoQueue.php
 * @see includes/classes/SalesManago.php
 */

require_once 'includes/application_top.php';

@set_time_limit(290);          // < 300s safety
@ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');

// --- Auth ---
$token = $_GET['token'] ?? '';
$expected = defined('SALESMANAGO_CRON_TOKEN') ? (string) SALESMANAGO_CRON_TOKEN : '';
if ($expected === '' || !hash_equals($expected, (string) $token)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

// --- Master switch ---
if (!defined('SALESMANAGO_STATUS') || SALESMANAGO_STATUS !== 'true') {
    echo "SM_STATUS=off — skip\n";
    exit;
}

require_once DIR_FS_CATALOG . 'includes/classes/SalesManago.php';
require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';

$batchLimit = defined('SALESMANAGO_WORKER_BATCH') ? max(1, (int) SALESMANAGO_WORKER_BATCH) : 50;
$workerTok  = bin2hex(random_bytes(8)) . '-' . dechex(getmypid());  // 16 hex + pid

$rows = SalesManagoQueue::claim($workerTok, $batchLimit);
$claimed = count($rows);
echo "claimed: $claimed (token=$workerTok, batch=$batchLimit)\n";

if ($claimed === 0) exit;

$sm = new SalesManago();
if (!$sm->isConfigured()) {
    echo "sm not configured — abort\n";
    // Roll the claimed rows back to pending without incrementing attempts further
    foreach ($rows as $r) {
        tep_db_query("UPDATE sm_event_queue SET status='pending', worker_token=NULL,
                        attempts=attempts-1, updated_at=NOW()
                      WHERE id=" . (int) $r['id']);
    }
    exit;
}

$tStart = microtime(true);
$nOk = $nErr = $nDead = 0;

foreach ($rows as $r) {
    $id       = (int) $r['id'];
    $type     = (string) $r['event_type'];
    $attempts = (int) $r['attempts'];
    $payload  = json_decode($r['payload'], true);
    if (!is_array($payload)) {
        SalesManagoQueue::markFailed($id, $attempts, 0, 'Malformed JSON payload');
        $nErr++;
        echo "  #$id $type → bad json\n";
        continue;
    }

    try {
        $res = ['ok' => false, 'http' => 0, 'error' => 'no dispatcher'];
        switch ($type) {
            case SalesManagoQueue::TYPE_CONTACT_UPSERT:
                // payload is the full upsert body
                $res = $sm->call('api/contact/upsert', $payload);
                break;

            case SalesManagoQueue::TYPE_PURCHASE:
            case SalesManagoQueue::TYPE_CART:
                // payload has top-level email + contactEvent
                $res = $sm->call('api/v2/contact/addContactExtEvent', $payload);
                break;

            default:
                $res = ['ok' => false, 'http' => 0, 'error' => 'unknown event type: ' . $type];
        }

        if ($res['ok']) {
            SalesManagoQueue::markSent($id);
            $nOk++;
            echo "  #$id $type OK\n";
        } else {
            $isDead = ($attempts + 0) >= (defined('SALESMANAGO_MAX_ATTEMPTS') ? (int) SALESMANAGO_MAX_ATTEMPTS : 8);
            SalesManagoQueue::markFailed($id, $attempts, (int) $res['http'], (string) $res['error']);
            if ($isDead) $nDead++;
            else         $nErr++;
            echo "  #$id $type FAIL http={$res['http']} err=" . substr((string) $res['error'], 0, 100) . "\n";
        }
    } catch (\Throwable $e) {
        SalesManagoQueue::markFailed($id, $attempts, 0, 'Exception: ' . $e->getMessage());
        $nErr++;
        echo "  #$id $type EXCEPTION " . substr($e->getMessage(), 0, 100) . "\n";
    }
}

$elapsed = round((microtime(true) - $tStart) * 1000);
echo "done: ok=$nOk err=$nErr dead=$nDead elapsed_ms=$elapsed\n";
