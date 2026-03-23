<?php
// webhook_paymongo.php
// ================================================================
// PayMongo POSTs here when a payment succeeds.
// This is the ONLY place that credits the wallet.
// Register this URL in PayMongo Dashboard → Webhooks:
//   https://edupay.page.gd/webhook_paymongo.php
// Event: payment.paid
// ================================================================
require_once __DIR__ . '/includes/config.php';

// ── Log everything for debugging ─────────────────────────────
if (!is_dir(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
$logFile  = __DIR__ . '/logs/webhook.log';
$rawBody  = file_get_contents('php://input');
$headers  = getallheaders();
file_put_contents($logFile,
    date('[Y-m-d H:i:s]') . ' SIG:' . ($headers['Paymongo-Signature'] ?? 'none') . "\n" . $rawBody . "\n\n",
    FILE_APPEND
);

// ── Verify signature ──────────────────────────────────────────
$sigHeader = $headers['Paymongo-Signature'] ?? '';
if (!$sigHeader) { http_response_code(401); die('Missing signature'); }

$parts = [];
foreach (explode(',', $sigHeader) as $part) {
    [$k, $v] = explode('=', $part, 2);
    $parts[$k] = $v;
}
$timestamp = $parts['t']  ?? '';
$testSig   = $parts['te'] ?? '';
$liveSig   = $parts['li'] ?? '';
$expected  = hash_hmac('sha256', $timestamp . '.' . $rawBody, PAYMONGO_WEBHOOK_SECRET);

if (!hash_equals($expected, $testSig) && !hash_equals($expected, $liveSig)) {
    http_response_code(401);
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " SIGNATURE MISMATCH\n", FILE_APPEND);
    die('Invalid signature');
}

// ── Parse event ───────────────────────────────────────────────
$event     = json_decode($rawBody, true);
$eventType = $event['data']['attributes']['type'] ?? '';

file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Event: $eventType\n", FILE_APPEND);

if ($eventType !== 'payment.paid') {
    http_response_code(200); echo 'OK'; exit;
}

// ── Extract payment data ──────────────────────────────────────
$paymentData  = $event['data']['attributes']['data'] ?? [];
$paymentId    = $paymentData['id'] ?? '';
$paymentAttrs = $paymentData['attributes'] ?? [];
$amountCents  = $paymentAttrs['amount'] ?? 0;
$amount       = $amountCents / 100;
$remarks      = $paymentAttrs['description'] ?? $paymentAttrs['remarks'] ?? '';

preg_match('/REF:([A-Z0-9]+)/i', $remarks, $refMatch);
preg_match('/USER:(\d+)/i',      $remarks, $userMatch);

$db = getDB();

// ── Find matching topup_request ───────────────────────────────
$topupReq = null;

if (!empty($refMatch[1])) {
    $s = $db->prepare("SELECT * FROM topup_requests WHERE ref_code = ? AND status = 'pending'");
    $s->execute([$refMatch[1]]);
    $topupReq = $s->fetch();
}

if (!$topupReq && !empty($userMatch[1])) {
    $s = $db->prepare("
        SELECT * FROM topup_requests
        WHERE user_id = ? AND amount = ? AND status = 'pending'
        ORDER BY created_at DESC LIMIT 1
    ");
    $s->execute([$userMatch[1], $amount]);
    $topupReq = $s->fetch();
}

if (!$topupReq) {
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " No matching request. PaymentID: $paymentId\n", FILE_APPEND);
    http_response_code(200); echo 'OK - no match'; exit;
}

// ── Idempotency — don't credit twice ─────────────────────────
$dup = $db->prepare("SELECT id FROM topup_requests WHERE paymongo_payment_id = ?");
$dup->execute([$paymentId]);
if ($dup->fetch()) {
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Duplicate ignored. PaymentID: $paymentId\n", FILE_APPEND);
    http_response_code(200); echo 'OK - duplicate'; exit;
}

// ── Atomic wallet credit ──────────────────────────────────────
try {
    $db->beginTransaction();

    $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
       ->execute([$amount, $topupReq['user_id']]);

    $txnRef = generateRef();
    $db->prepare("
        INSERT INTO transactions (sender_id, receiver_id, amount, description, ref_code)
        VALUES (1, ?, ?, 'GCash/Maya Top-Up via PayMongo', ?)
    ")->execute([$topupReq['user_id'], $amount, $txnRef]);

    $db->prepare("
        UPDATE topup_requests
        SET status = 'paid', paymongo_payment_id = ?, paid_at = NOW()
        WHERE id = ?
    ")->execute([$paymentId, $topupReq['id']]);

    $db->commit();

    file_put_contents($logFile,
        date('[Y-m-d H:i:s]') . " ✅ Credited ₱{$amount} to user {$topupReq['user_id']} TxnRef:$txnRef\n",
        FILE_APPEND
    );

} catch (Exception $e) {
    $db->rollBack();
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " ❌ " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500); die('DB error');
}

http_response_code(200);
echo 'OK';
