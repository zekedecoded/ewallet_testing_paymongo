<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');
$db = getDB();

$ref = trim($_GET['ref'] ?? '');
$req = null;

if ($ref) {
    $s = $db->prepare("SELECT * FROM topup_requests WHERE ref_code = ? AND user_id = ?");
    $s->execute([$ref, $session['user_id']]);
    $req = $s->fetch();
}
if (!$req) {
    $s = $db->prepare("SELECT * FROM topup_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $s->execute([$session['user_id']]);
    $req = $s->fetch();
}

$pageTitle = 'Payment Status';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <h1 class="ep-heading mb-4" style="font-size:1.3rem;">Payment Status</h1>

  <?php if (!$req): ?>
  <div class="ep-alert ep-alert-warning">No top-up request found.</div>

  <?php elseif ($req['status'] === 'paid'): ?>
  <div class="ep-card text-center py-4">
    <div style="font-size:3.5rem;">✅</div>
    <h2 class="ep-heading mt-2">Payment Successful!</h2>
    <p style="color:var(--ep-muted);"><?= money((float)$req['amount']) ?> has been added to your wallet.</p>
    <div class="ep-alert ep-alert-success mt-3 text-start">
      Reference: <code><?= $req['ref_code'] ?></code>
    </div>
    <a href="<?= BASE_PATH ?>/student/dashboard.php" class="btn-ep btn-ep-navy w-100 mt-3">
      <i class="bi bi-wallet2 me-1"></i> Back to Wallet
    </a>
  </div>

  <?php elseif (in_array($req['status'], ['failed','expired'])): ?>
  <div class="ep-card text-center py-4">
    <div style="font-size:3.5rem;">❌</div>
    <h2 class="ep-heading mt-2">Payment <?= ucfirst($req['status']) ?></h2>
    <p style="color:var(--ep-muted);">Your wallet was not charged.</p>
    <a href="<?= BASE_PATH ?>/student/topup.php" class="btn-ep btn-ep-navy w-100 mt-3">Try Again</a>
  </div>

  <?php else: ?>
  <!-- Pending — poll every 3s -->
  <div class="ep-card text-center py-4" id="status-card">
    <div class="ep-spinner" style="width:48px;height:48px;margin:1rem auto;"></div>
    <h2 class="ep-heading">Confirming Payment…</h2>
    <p style="color:var(--ep-muted);font-size:.88rem;">
      Please wait while we confirm your payment.<br>
      This usually takes 5–15 seconds.
    </p>
    <div class="ep-alert ep-alert-info mt-3" style="font-size:.82rem;">
      <i class="bi bi-info-circle me-1"></i>
      You can safely close this page — your wallet will be credited automatically once confirmed.
    </div>
    <div style="font-size:.75rem;color:var(--ep-subtle);margin-top:1rem;">
      Ref: <code><?= $req['ref_code'] ?></code>
    </div>
  </div>

  <script>
  let attempts = 0;
  const ref = '<?= htmlspecialchars($req['ref_code']) ?>';
  function checkStatus() {
    attempts++;
    fetch('<?= BASE_PATH ?>/student/topup_check.php?ref=' + ref)
      .then(r => r.json())
      .then(data => {
        if (data.status === 'paid' || data.status === 'failed' || data.status === 'expired') {
          window.location.reload();
        } else if (attempts < 40) {
          setTimeout(checkStatus, 3000);
        }
      })
      .catch(() => { if (attempts < 40) setTimeout(checkStatus, 3000); });
  }
  setTimeout(checkStatus, 3000);
  </script>
  <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
