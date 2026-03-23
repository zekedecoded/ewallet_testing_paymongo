<?php
// student/history.php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');
$db = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$countStmt = $db->prepare("
    SELECT COUNT(*) FROM transactions
    WHERE sender_id = ? OR receiver_id = ?
");
$countStmt->execute([$session['user_id'], $session['user_id']]);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$txnStmt = $db->prepare("
    SELECT t.*,
           s.name AS sender_name, s.avatar AS sender_avatar,
           r.name AS receiver_name, r.avatar AS receiver_avatar
    FROM transactions t
    JOIN users s ON t.sender_id   = s.id
    JOIN users r ON t.receiver_id = r.id
    WHERE t.sender_id = ? OR t.receiver_id = ?
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$txnStmt->execute([$session['user_id'], $session['user_id'], $perPage, $offset]);
$transactions = $txnStmt->fetchAll();

// Totals
$totStmt = $db->prepare("
    SELECT
      SUM(CASE WHEN sender_id   = ? THEN amount ELSE 0 END) AS total_spent,
      SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) AS total_received
    FROM transactions
    WHERE sender_id = ? OR receiver_id = ?
");
$totStmt->execute([$session['user_id'],$session['user_id'],$session['user_id'],$session['user_id']]);
$totals = $totStmt->fetch();

$pageTitle = 'Transaction History';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/student/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Transaction History</h1>
  </div>

  <!-- Stat pills -->
  <div class="d-flex gap-2 mb-4">
    <div class="ep-card flex-fill text-center py-2 px-3">
      <div style="font-size:.72rem;color:var(--ep-muted);font-family:'Syne',sans-serif;text-transform:uppercase;letter-spacing:.08em;">Total Spent</div>
      <div style="font-family:'Syne',sans-serif;font-weight:700;color:var(--ep-danger);">
        <?= money((float)($totals['total_spent'] ?? 0)) ?>
      </div>
    </div>
    <div class="ep-card flex-fill text-center py-2 px-3">
      <div style="font-size:.72rem;color:var(--ep-muted);font-family:'Syne',sans-serif;text-transform:uppercase;letter-spacing:.08em;">Total Received</div>
      <div style="font-family:'Syne',sans-serif;font-weight:700;color:var(--gjc-green);">
        <?= money((float)($totals['total_received'] ?? 0)) ?>
      </div>
    </div>
  </div>

  <div class="ep-card">
    <?php if (empty($transactions)): ?>
      <div class="text-center py-4" style="color:var(--ep-muted);">
        <div style="font-size:2.5rem;">🧾</div>
        <div style="font-size:.9rem;">No transactions found.</div>
      </div>
    <?php else: ?>
    <ul class="ep-txn-list">
      <?php foreach ($transactions as $t):
        $isDebit = ($t['sender_id'] == $session['user_id']);
        $other = $isDebit ? $t['receiver_name'] : $t['sender_name'];
        $otherAvatar = $isDebit ? $t['receiver_avatar'] : $t['sender_avatar'];
        $sign = $isDebit ? '-' : '+';
        $cls  = $isDebit ? 'debit' : 'credit';
      ?>
      <li class="ep-txn-item">
        <div class="ep-txn-icon <?= $cls ?>">
          <?= $otherAvatar ?? ($isDebit ? '💸' : '💰') ?>
        </div>
        <div class="ep-txn-meta">
          <div class="ep-txn-desc">
            <?= htmlspecialchars($t['description'] ?: ($isDebit ? 'To '.$other : 'From '.$other)) ?>
          </div>
          <div class="ep-txn-time">
            <?= date('M d, Y · g:i a', strtotime($t['created_at'])) ?>
            &nbsp;·&nbsp;
            <code style="font-size:.68rem;color:var(--ep-muted);"><?= $t['ref_code'] ?></code>
          </div>
        </div>
        <div class="ep-txn-amount <?= $cls ?>">
          <?= $sign . money((float)$t['amount']) ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-center gap-2 pt-3">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>" class="btn-ep <?= $p === $page ? 'btn-ep-primary' : 'btn-ep-outline' ?>"
           style="padding:.35rem .75rem;font-size:.8rem;">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
