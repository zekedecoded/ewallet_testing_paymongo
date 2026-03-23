<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');
$db = getDB();

$balStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$balStmt->execute([$session['user_id']]);
$wallet  = $balStmt->fetch();
$balance = (float)($wallet['balance'] ?? 0);

$txnStmt = $db->prepare("
    SELECT t.*,
           s.name AS sender_name,   s.avatar AS sender_avatar,
           r.name AS receiver_name, r.avatar AS receiver_avatar
    FROM transactions t
    JOIN users s ON t.sender_id   = s.id
    JOIN users r ON t.receiver_id = r.id
    WHERE t.sender_id = ? OR t.receiver_id = ?
    ORDER BY t.created_at DESC LIMIT 8
");
$txnStmt->execute([$session['user_id'], $session['user_id']]);
$transactions = $txnStmt->fetchAll();

$pageTitle = 'My Wallet';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <!-- Balance Hero -->
  <div class="ep-balance-card mb-4">
    <div class="ep-balance-label">Current Balance</div>
    <div class="ep-balance-amount"><?= money((float)$balance) ?></div>
    <div class="ep-balance-id">
      <strong><?= htmlspecialchars($session['avatar'] ?? '🎓') ?>
      <?= htmlspecialchars($session['name']) ?></strong>
      &nbsp;·&nbsp; <?= htmlspecialchars($session['student_id'] ?? '') ?>
    </div>
    <!-- Gold accent bar -->
    <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gjc-yellow),transparent);"></div>
  </div>

  <!-- Quick Actions -->
  <div class="ep-quick-grid mb-4">
    <a href="<?= BASE_PATH ?>/student/scan.php" class="ep-quick-btn">
      <span class="ep-quick-icon">📷</span>
      <span class="ep-quick-label">Scan QR</span>
    </a>
    <a href="<?= BASE_PATH ?>/student/topup.php" class="ep-quick-btn">
      <span class="ep-quick-icon">💳</span>
      <span class="ep-quick-label">Load Wallet</span>
    </a>
    <a href="<?= BASE_PATH ?>/student/history.php" class="ep-quick-btn">
      <span class="ep-quick-icon">📋</span>
      <span class="ep-quick-label">History</span>
    </a>
  </div>

  <!-- Recent Activity -->
  <div class="ep-card">
    <div class="ep-section-header">
      <i class="bi bi-clock-history"></i>
      <h2>Recent Activity</h2>
      <a href="<?= BASE_PATH ?>/student/history.php" class="ep-section-badge" style="text-decoration:none;">View All</a>
    </div>

    <?php if (empty($transactions)): ?>
    <div class="text-center py-4" style="color:var(--ep-muted);">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">🧾</div>
      <div style="font-size:.88rem;">No transactions yet.</div>
    </div>
    <?php else: ?>
    <ul class="ep-txn-list">
      <?php foreach ($transactions as $t):
        $isDebit     = ($t['sender_id'] == $session['user_id']);
        $other       = $isDebit ? $t['receiver_name'] : $t['sender_name'];
        $otherAvatar = $isDebit ? $t['receiver_avatar'] : $t['sender_avatar'];
        $sign        = $isDebit ? '−' : '+';
        $cls         = $isDebit ? 'debit' : 'credit';
      ?>
      <li class="ep-txn-item">
        <div class="ep-txn-icon <?= $cls ?>"><?= $otherAvatar ?? ($isDebit ? '💸' : '💰') ?></div>
        <div class="ep-txn-meta">
          <div class="ep-txn-desc"><?= htmlspecialchars($t['description'] ?: ($isDebit ? 'Paid to '.$other : 'From '.$other)) ?></div>
          <div class="ep-txn-time"><?= timeAgo($t['created_at']) ?> · <code><?= $t['ref_code'] ?></code></div>
        </div>
        <div class="ep-txn-amount <?= $cls ?>"><?= $sign . money((float)$t['amount']) ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
