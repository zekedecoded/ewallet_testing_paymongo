<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('merchant');
$db = getDB();

$balStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$balStmt->execute([$session['user_id']]);
$balance = (float)(($balStmt->fetch())['balance'] ?? 0);

$txnStmt = $db->prepare("
    SELECT t.*, u.name AS student_name, u.student_id, u.avatar AS student_avatar
    FROM transactions t JOIN users u ON t.sender_id = u.id
    WHERE t.receiver_id = ? ORDER BY t.created_at DESC LIMIT 8
");
$txnStmt->execute([$session['user_id']]);
$transactions = $txnStmt->fetchAll();

$todayStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE receiver_id=? AND DATE(created_at)=CURDATE()");
$todayStmt->execute([$session['user_id']]);
$todayTotal = (float)$todayStmt->fetchColumn();

$pageTitle = 'Merchant Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <!-- Balance -->
  <div class="ep-balance-card mb-4">
    <div class="ep-balance-label">Wallet Balance</div>
    <div class="ep-balance-amount"><?= money($balance) ?></div>
    <div class="ep-balance-id">
      <strong><?= $session['avatar'] ?? '🍱' ?> <?= htmlspecialchars($session['name']) ?></strong>
      &nbsp;·&nbsp; Today: <strong style="color:var(--gjc-yellow);"><?= money($todayTotal) ?></strong>
    </div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gjc-yellow),transparent);"></div>
  </div>

  <!-- Quick Actions -->
  <div class="ep-quick-grid mb-4">
    <a href="<?= BASE_PATH ?>/merchant/generate_qr.php" class="ep-quick-btn">
      <span class="ep-quick-icon">📲</span>
      <span class="ep-quick-label">Generate QR</span>
    </a>
    <a href="<?= BASE_PATH ?>/merchant/history.php" class="ep-quick-btn">
      <span class="ep-quick-icon">📊</span>
      <span class="ep-quick-label">Sales History</span>
    </a>
  </div>

  <!-- Recent Payments -->
  <div class="ep-card">
    <div class="ep-section-header">
      <i class="bi bi-cash-coin"></i>
      <h2>Recent Payments</h2>
      <a href="<?= BASE_PATH ?>/merchant/history.php" class="ep-section-badge" style="text-decoration:none;">View All</a>
    </div>

    <?php if (empty($transactions)): ?>
    <div class="text-center py-4" style="color:var(--ep-muted);">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">💳</div>
      <div style="font-size:.88rem;">No payments received yet.</div>
    </div>
    <?php else: ?>
    <ul class="ep-txn-list">
      <?php foreach ($transactions as $t): ?>
      <li class="ep-txn-item">
        <div class="ep-txn-icon credit"><?= $t['student_avatar'] ?? '🎓' ?></div>
        <div class="ep-txn-meta">
          <div class="ep-txn-desc"><?= htmlspecialchars($t['student_name']) ?> <span style="font-size:.75rem;color:var(--ep-muted);">(<?= $t['student_id'] ?>)</span></div>
          <div class="ep-txn-time"><?= htmlspecialchars($t['description'] ?: 'Payment') ?> · <?= timeAgo($t['created_at']) ?></div>
        </div>
        <div class="ep-txn-amount credit">+<?= money((float)$t['amount']) ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
