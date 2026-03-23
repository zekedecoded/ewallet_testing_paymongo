<?php
// merchant/history.php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('merchant');
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE receiver_id = ?");
$countStmt->execute([$session['user_id']]);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$txnStmt = $db->prepare("
    SELECT t.*, u.name AS student_name, u.student_id, u.avatar AS student_avatar
    FROM transactions t
    JOIN users u ON t.sender_id = u.id
    WHERE t.receiver_id = ?
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$txnStmt->execute([$session['user_id'], $perPage, $offset]);
$txns = $txnStmt->fetchAll();

// Summary
$sumStmt = $db->prepare("
    SELECT
      COUNT(*)                                             AS txn_count,
      COALESCE(SUM(amount),0)                              AS total,
      COALESCE(SUM(CASE WHEN DATE(created_at)=CURDATE() THEN amount END),0) AS today
    FROM transactions WHERE receiver_id = ?
");
$sumStmt->execute([$session['user_id']]);
$summary = $sumStmt->fetch();

$pageTitle = 'Sales History';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page-wide">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/merchant/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Sales History</h1>
  </div>

  <!-- Summary pills -->
  <div class="row g-2 mb-4">
    <div class="col-4">
      <div class="ep-card text-center py-2 px-1">
        <div style="font-size:.7rem;color:var(--ep-muted);font-family:'Syne',sans-serif;text-transform:uppercase;">Total Sales</div>
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;color:var(--gjc-green);"><?= money((float)$summary['total']) ?></div>
      </div>
    </div>
    <div class="col-4">
      <div class="ep-card text-center py-2 px-1">
        <div style="font-size:.7rem;color:var(--ep-muted);font-family:'Syne',sans-serif;text-transform:uppercase;">Today</div>
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;color:var(--gjc-green);"><?= money((float)$summary['today']) ?></div>
      </div>
    </div>
    <div class="col-4">
      <div class="ep-card text-center py-2 px-1">
        <div style="font-size:.7rem;color:var(--ep-muted);font-family:'Syne',sans-serif;text-transform:uppercase;">Transactions</div>
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;"><?= number_format($summary['txn_count']) ?></div>
      </div>
    </div>
  </div>

  <div class="ep-card">
    <?php if (empty($txns)): ?>
      <div class="text-center py-4" style="color:var(--ep-muted);">No transactions yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="ep-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Ref</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($txns as $t): ?>
          <tr>
            <td>
              <span style="font-size:1rem;"><?= $t['student_avatar'] ?? '🎓' ?></span>
              <?= htmlspecialchars($t['student_name']) ?>
              <div style="font-size:.72rem;color:var(--ep-muted);"><?= $t['student_id'] ?></div>
            </td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($t['description'] ?: '—') ?>
            </td>
            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--gjc-green);">
              +<?= money((float)$t['amount']) ?>
            </td>
            <td>
              <code style="font-size:.72rem;color:var(--ep-muted);"><?= $t['ref_code'] ?></code>
            </td>
            <td style="font-size:.8rem;color:var(--ep-muted);">
              <?= date('M d · g:i a', strtotime($t['created_at'])) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-center gap-2 pt-3 flex-wrap">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>" class="btn-ep <?= $p === $page ? 'btn-ep-primary' : 'btn-ep-outline' ?>"
           style="padding:.35rem .75rem;font-size:.8rem;"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
