<?php
// student/confirm_payment.php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');

$db = getDB();

// ── Accept params from GET (scan redirect) or POST (form re-submit) ──
$token      = trim($_REQUEST['token']       ?? '');
$merchantId = (int)($_REQUEST['merchant_id'] ?? 0);
$amount     = (float)($_REQUEST['amount']    ?? 0);
$desc       = trim($_REQUEST['desc']         ?? '');

$error   = '';
$success = '';

// ── Validate token from DB ────────────────────────────────────
if (empty($token)) {
    $error = 'No payment token provided. Please scan again.';
} else {
    $qrStmt = $db->prepare("
        SELECT q.*, u.name AS merchant_name, u.avatar AS merchant_avatar
        FROM   qr_tokens q
        JOIN   users u ON q.merchant_id = u.id
        WHERE  q.token = ? AND q.used = 0 AND q.expires_at > NOW()
        LIMIT 1
    ");
    $qrStmt->execute([$token]);
    $qr = $qrStmt->fetch();

    if (!$qr) {
        $error = 'This QR code is invalid, expired, or has already been used.';
    } else {
        // Use DB values (don't trust URL params for amount/merchant)
        $merchantId = (int)$qr['merchant_id'];
        $amount     = (float)$qr['amount'];
        $desc       = $qr['description'] ?? $desc;
    }
}

// ── Fetch student balance ─────────────────────────────────────
$balStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$balStmt->execute([$session['user_id']]);
$wallet  = $balStmt->fetch();
$balance = (float)($wallet['balance'] ?? 0);

// ── Fetch merchant info ───────────────────────────────────────
$merchant = null;
if (!$error && $merchantId) {
    $mStmt = $db->prepare("SELECT id, name, avatar FROM users WHERE id = ? AND role = 'merchant'");
    $mStmt->execute([$merchantId]);
    $merchant = $mStmt->fetch();
    if (!$merchant) $error = 'Merchant not found.';
}

// ── PROCESS PAYMENT (POST) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Security token mismatch.';
    } elseif ($balance < $amount) {
        $error = 'Insufficient balance. Your wallet has ' . money($balance) . '.';
    } else {
        $ref = generateRef();
        try {
            $db->beginTransaction();

            // 1. Deduct from student
            $deduct = $db->prepare("
                UPDATE wallets SET balance = balance - ?
                WHERE user_id = ? AND balance >= ?
            ");
            $deduct->execute([$amount, $session['user_id'], $amount]);
            if ($deduct->rowCount() === 0) {
                throw new Exception('Insufficient balance (race condition).');
            }

            // 2. Add to merchant
            $credit = $db->prepare("
                UPDATE wallets SET balance = balance + ?
                WHERE user_id = ?
            ");
            $credit->execute([$amount, $merchantId]);
            if ($credit->rowCount() === 0) {
                throw new Exception('Merchant wallet not found.');
            }

            // 3. Record transaction
            $ins = $db->prepare("
                INSERT INTO transactions
                    (sender_id, receiver_id, amount, description, ref_code)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$session['user_id'], $merchantId, $amount, $desc, $ref]);

            // 4. Mark QR token as used
            $mark = $db->prepare("UPDATE qr_tokens SET used = 1 WHERE token = ?");
            $mark->execute([$token]);

            $db->commit();

            // Update session balance
            $_SESSION['balance'] = $balance - $amount;
            $newBalance = $balance - $amount;
            $success = 'Payment successful!';

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Payment failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Confirm Payment';
$token_csrf = csrfToken();
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/student/scan.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Confirm Payment</h1>
  </div>

  <?php if ($success): ?>
  <!-- SUCCESS STATE -->
  <div class="ep-card text-center py-4">
    <div style="font-size:3.5rem;">✅</div>
    <h2 class="ep-heading mt-2 mb-1"><?= $success ?></h2>
    <p style="color:var(--ep-muted);font-size:.9rem;">
      You paid <?= money($amount) ?> to <?= htmlspecialchars($merchant['name'] ?? 'Merchant') ?>
    </p>
    <div class="ep-alert ep-alert-success mt-3 text-start">
      <div style="font-size:.8rem;">Transaction Reference</div>
      <code style="color:var(--gjc-green);font-size:1rem;"><?= $ref ?></code>
    </div>
    <div class="mt-3" style="font-size:.9rem;color:var(--ep-muted);">
      New balance: <strong style="color:var(--gjc-green);"><?= money($newBalance) ?></strong>
    </div>
    <a href="<?= BASE_PATH ?>/student/dashboard.php" class="btn-ep btn-ep-primary w-100 mt-4">
      Back to Wallet
    </a>
  </div>

  <?php elseif ($error): ?>
  <!-- ERROR STATE -->
  <div class="ep-alert ep-alert-danger mb-3">
    <i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($error) ?>
  </div>
  <a href="<?= BASE_PATH ?>/student/scan.php" class="btn-ep btn-ep-outline w-100">
    <i class="bi bi-arrow-left me-1"></i> Scan Again
  </a>

  <?php else: ?>
  <!-- CONFIRMATION FORM -->

  <!-- Merchant info -->
  <div class="ep-card mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="ep-txn-icon credit" style="width:48px;height:48px;font-size:1.4rem;">
        <?= $merchant['avatar'] ?? '🏪' ?>
      </div>
      <div>
        <div style="font-size:.75rem;color:var(--ep-muted);font-family:'Syne',sans-serif;text-transform:uppercase;letter-spacing:.08em;">Paying to</div>
        <div style="font-weight:600;font-size:1rem;"><?= htmlspecialchars($merchant['name']) ?></div>
      </div>
    </div>
  </div>

  <!-- Breakdown -->
  <div class="ep-card mb-3">
    <h3 class="ep-heading mb-3" style="font-size:.9rem;">Payment Details</h3>
    <div class="ep-pay-breakdown">
      <div class="ep-pay-row">
        <span class="ep-pay-lbl">Item / Description</span>
        <span class="ep-pay-val"><?= htmlspecialchars($desc ?: 'No description') ?></span>
      </div>
      <div class="ep-pay-row">
        <span class="ep-pay-lbl">Your Balance</span>
        <span class="ep-pay-val"><?= money($balance) ?></span>
      </div>
      <div class="ep-pay-row total">
        <span class="ep-pay-lbl"><strong>Amount Due</strong></span>
        <span class="ep-pay-val"><?= money($amount) ?></span>
      </div>
    </div>

    <?php if ($balance >= $amount): ?>
      <div class="ep-alert ep-alert-success mt-3" style="font-size:.82rem;">
        After payment: <strong><?= money($balance - $amount) ?></strong> remaining
      </div>
    <?php else: ?>
      <div class="ep-alert ep-alert-danger mt-3" style="font-size:.82rem;">
        Insufficient balance. You need <?= money($amount - $balance) ?> more.
      </div>
    <?php endif; ?>
  </div>

  <!-- Expiry countdown -->
  <?php
  $expiresAt = strtotime($qr['expires_at']);
  $remaining = max(0, $expiresAt - time());
  ?>
  <div class="ep-card mb-4 text-center">
    <div style="font-size:.75rem;color:var(--ep-muted);margin-bottom:.25rem;font-family:'Syne',sans-serif;text-transform:uppercase;letter-spacing:.1em;">QR Expires In</div>
    <div class="ep-qr-countdown" id="countdown"><?= $remaining ?>s</div>
  </div>

  <!-- Action buttons -->
  <form method="POST">
    <input type="hidden" name="csrf"        value="<?= $token_csrf ?>">
    <input type="hidden" name="token"       value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="merchant_id" value="<?= $merchantId ?>">
    <input type="hidden" name="amount"      value="<?= $amount ?>">
    <input type="hidden" name="desc"        value="<?= htmlspecialchars($desc) ?>">

    <?php if ($balance >= $amount): ?>
      <button type="submit" class="btn-ep btn-ep-primary w-100 mb-2">
        <i class="bi bi-check-circle me-1"></i> Confirm &amp; Pay <?= money($amount) ?>
      </button>
    <?php endif; ?>
    <a href="<?= BASE_PATH ?>/student/scan.php" class="btn-ep btn-ep-outline w-100 d-block text-center text-decoration-none">
      Cancel
    </a>
  </form>

  <script>
  let sec = <?= $remaining ?>;
  const el = document.getElementById('countdown');
  const iv = setInterval(() => {
    sec--;
    if (el) {
      el.textContent = sec + 's';
      if (sec <= 30) el.classList.add('urgent');
    }
    if (sec <= 0) {
      clearInterval(iv);
      alert('QR code expired. Please ask the merchant to generate a new one.');
      window.location.href = '<?= BASE_PATH ?>/student/scan.php';
    }
  }, 1000);
  </script>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
