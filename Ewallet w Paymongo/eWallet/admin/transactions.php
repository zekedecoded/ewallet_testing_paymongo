<?php
// admin/transactions.php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('admin');
$db = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$countStmt = $db->query("SELECT COUNT(*) FROM transactions");
$total     = (int)$countStmt->fetchColumn();
$pages     = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
    SELECT t.*,
           s.name AS sender_name,   s.avatar AS sa, s.student_id AS s_sid,
           r.name AS receiver_name, r.avatar AS ra, r.role AS r_role
    FROM transactions t
    JOIN users s ON t.sender_id   = s.id
    JOIN users r ON t.receiver_id = r.id
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$txns = $stmt->fetchAll();

$pageTitle = 'All Transactions';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page-wide">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">All Transactions</h1>
    <span class="ep-badge ep-badge-info"><?= number_format($total) ?> total</span>
  </div>

  <div class="ep-card">
    <div style="overflow-x:auto;">
      <table class="ep-table">
        <thead>
          <tr>
            <th>#</th>
            <th>From</th>
            <th>To</th>
            <th>Amount</th>
            <th>Description</th>
            <th>Ref</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($txns as $t): ?>
          <tr>
            <td style="color:var(--ep-muted);font-size:.78rem;"><?= $t['id'] ?></td>
            <td style="font-size:.85rem;">
              <?= $t['sa'] ?> <?= htmlspecialchars($t['sender_name']) ?>
              <?php if ($t['s_sid']): ?><div style="font-size:.7rem;color:var(--ep-muted);"><?= $t['s_sid'] ?></div><?php endif; ?>
            </td>
            <td style="font-size:.85rem;">
              <?= $t['ra'] ?> <?= htmlspecialchars($t['receiver_name']) ?>
              <div style="font-size:.7rem;color:var(--ep-muted);"><?= $t['r_role'] ?></div>
            </td>
            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--gjc-green);">
              <?= money((float)$t['amount']) ?>
            </td>
            <td style="font-size:.82rem;color:var(--ep-muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($t['description'] ?: '—') ?>
            </td>
            <td>
              <code style="font-size:.72rem;color:var(--ep-muted);"><?= $t['ref_code'] ?></code>
            </td>
            <td>
              <?php
              $sBadge = match($t['status']) {
                'success'  => 'ep-badge-success',
                'reversed' => 'ep-badge-warning',
                default    => 'ep-badge-danger'
              };
              ?>
              <span class="ep-badge <?= $sBadge ?>"><?= $t['status'] ?></span>
            </td>
            <td style="font-size:.78rem;color:var(--ep-muted);">
              <?= date('M d, Y', strtotime($t['created_at'])) ?><br>
              <?= date('g:i a', strtotime($t['created_at'])) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-center gap-1 pt-3 flex-wrap">
      <?php for ($p = 1; $p <= min($pages, 10); $p++): ?>
        <a href="?page=<?= $p ?>" class="btn-ep <?= $p === $page ? 'btn-ep-primary' : 'btn-ep-outline' ?>"
           style="padding:.3rem .65rem;font-size:.78rem;"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
