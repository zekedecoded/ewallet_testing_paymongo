<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');
$db = getDB();

$error = '';
$csrf  = csrfToken();

$balStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$balStmt->execute([$session['user_id']]);
$balance = (float)($balStmt->fetch()['balance'] ?? 0);

$histStmt = $db->prepare("
    SELECT * FROM topup_requests WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 5
");
$histStmt->execute([$session['user_id']]);
$recentTopups = $histStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount < TOPUP_MIN) {
            $error = 'Minimum top-up is ' . money(TOPUP_MIN) . '.';
        } elseif ($amount > TOPUP_MAX) {
            $error = 'Maximum top-up is ' . money(TOPUP_MAX) . '.';
        } else {
            try {
                $ref = generateRef();
                $result = paymongoRequest('POST', '/links', [
                    'amount'      => (int)($amount * 100),
                    'currency'    => 'PHP',
                    'description' => 'GJC EduPay Wallet Top-Up — ' . $session['name'],
                    'remarks'     => 'REF:' . $ref . ' USER:' . $session['user_id'],
                ]);

                $linkId      = $result['data']['id'];
                $checkoutUrl = $result['data']['attributes']['checkout_url'];

                $ins = $db->prepare("
                    INSERT INTO topup_requests
                        (user_id, amount, paymongo_link_id, checkout_url, status, ref_code)
                    VALUES (?, ?, ?, ?, 'pending', ?)
                ");
                $ins->execute([$session['user_id'], $amount, $linkId, $checkoutUrl, $ref]);

                header('Location: ' . $checkoutUrl);
                exit;

            } catch (Exception $e) {
                $error = 'Could not create payment link: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Load Wallet';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/student/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Load Wallet</h1>
  </div>

  <!-- Balance -->
  <div class="ep-balance-card mb-4">
    <div class="ep-balance-label">Current Balance</div>
    <div class="ep-balance-amount"><?= money($balance) ?></div>
    <div class="ep-balance-id">
      <strong><?= $session['avatar'] ?? '🎓' ?> <?= htmlspecialchars($session['name']) ?></strong>
      &nbsp;·&nbsp; <?= htmlspecialchars($session['student_id'] ?? '') ?>
    </div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gjc-yellow),transparent);"></div>
  </div>

  <?php if ($error): ?>
  <div class="ep-alert ep-alert-danger mb-3">
    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <div class="ep-card mb-3">
    <div class="ep-section-header">
      <i class="bi bi-wallet2"></i>
      <h2>Add Money</h2>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="ep-form-group">
        <label class="ep-label">Amount to Load (₱)</label>
        <input type="number" name="amount" id="amount-input" class="ep-input"
               placeholder="0.00" step="1"
               min="<?= TOPUP_MIN ?>" max="<?= TOPUP_MAX ?>"
               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
               required autofocus
               style="font-size:1.6rem;font-family:'Poppins',sans-serif;font-weight:800;text-align:center;"
               oninput="updatePreview(this.value)">
        <div style="font-size:.75rem;color:var(--ep-muted);margin-top:.3rem;text-align:center;">
          Min: <?= money(TOPUP_MIN) ?> &nbsp;·&nbsp; Max: <?= money(TOPUP_MAX) ?>
        </div>
      </div>

      <!-- Quick amounts -->
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ([50,100,200,300,500,1000] as $q): ?>
        <button type="button"
                class="btn-ep btn-ep-outline"
                style="flex:1;min-width:60px;padding:.4rem .5rem;font-size:.82rem;"
                onclick="setAmount(<?= $q ?>)">
          ₱<?= number_format($q) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Balance preview -->
      <div id="balance-preview" class="ep-alert ep-alert-info mb-3" style="display:none;font-size:.85rem;">
        After loading: <strong id="new-balance-preview"></strong>
      </div>

      <!-- Accepted payment methods -->
      <div class="d-flex gap-2 flex-wrap mb-3 justify-content-center">
        <?php foreach ([['💚','GCash'],['💙','Maya'],['💳','Card'],['🏦','Online Banking']] as [$ico,$lbl]): ?>
        <div style="background:var(--ep-surface2);border:1px solid var(--ep-border);border-radius:8px;padding:.35rem .7rem;font-size:.75rem;display:flex;align-items:center;gap:.35rem;">
          <span><?= $ico ?></span>
          <span style="font-weight:600;color:var(--gjc-green);"><?= $lbl ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="ep-alert ep-alert-warning mb-3" style="font-size:.8rem;">
        <i class="bi bi-shield-check me-1"></i>
        Payments processed securely by <strong>PayMongo</strong>.
        Your GCash/card details are never stored on our server.
      </div>

      <button type="submit" class="btn-ep btn-ep-navy w-100" style="font-size:1rem;padding:.8rem;">
        <i class="bi bi-credit-card me-1"></i> Proceed to Payment
      </button>
    </form>
  </div>

  <!-- Recent top-ups -->
  <?php if (!empty($recentTopups)): ?>
  <div class="ep-card">
    <div class="ep-section-header">
      <i class="bi bi-clock-history"></i>
      <h2>Recent Top-Ups</h2>
    </div>
    <ul class="ep-txn-list">
      <?php foreach ($recentTopups as $t):
        $badgeClass = match($t['status']) {
          'paid'    => 'ep-badge-success',
          'pending' => 'ep-badge-warning',
          default   => 'ep-badge-danger',
        };
        $badgeText = match($t['status']) {
          'paid'    => '✓ Paid',
          'pending' => '⏳ Pending',
          'failed'  => '✗ Failed',
          'expired' => '⏰ Expired',
          default   => $t['status'],
        };
      ?>
      <li class="ep-txn-item">
        <div class="ep-txn-icon <?= $t['status']==='paid'?'credit':'debit' ?>">
          <?= $t['status']==='paid'?'💰':'🕐' ?>
        </div>
        <div class="ep-txn-meta">
          <div class="ep-txn-desc">Wallet Top-Up</div>
          <div class="ep-txn-time">
            <?= timeAgo($t['created_at']) ?> · <code><?= $t['ref_code'] ?></code>
            · <span class="ep-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
          </div>
        </div>
        <div class="ep-txn-amount <?= $t['status']==='paid'?'credit':'' ?>">
          +<?= money((float)$t['amount']) ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</div>

<script>
const currentBalance = <?= $balance ?>;
function setAmount(v) {
  document.getElementById('amount-input').value = v;
  updatePreview(v);
}
function updatePreview(v) {
  const amt = parseFloat(v);
  const el  = document.getElementById('balance-preview');
  if (amt >= <?= TOPUP_MIN ?> && amt <= <?= TOPUP_MAX ?>) {
    el.style.display = 'block';
    document.getElementById('new-balance-preview').textContent =
      '₱' + (currentBalance + amt).toFixed(2);
  } else {
    el.style.display = 'none';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
