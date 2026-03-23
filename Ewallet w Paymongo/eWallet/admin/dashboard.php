<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('admin');
$db = getDB();

$statsStmt = $db->query("
    SELECT
      (SELECT COUNT(*) FROM users WHERE role='student')  AS student_count,
      (SELECT COUNT(*) FROM users WHERE role='merchant') AS merchant_count,
      (SELECT COUNT(*) FROM transactions)                AS txn_count,
      (SELECT COALESCE(SUM(balance),0) FROM wallets
         JOIN users ON wallets.user_id=users.id WHERE users.role='student') AS total_student_balance,
      (SELECT COALESCE(SUM(amount),0) FROM transactions
         WHERE DATE(created_at)=CURDATE())               AS today_volume
");
$stats = $statsStmt->fetch();

$recentStmt = $db->query("
    SELECT t.*, s.name sn, s.avatar sa, r.name rn, r.avatar ra
    FROM transactions t
    JOIN users s ON t.sender_id=s.id
    JOIN users r ON t.receiver_id=r.id
    ORDER BY t.created_at DESC LIMIT 8
");
$recent = $recentStmt->fetchAll();

$topStmt = $db->query("
    SELECT u.name, u.avatar, COALESCE(SUM(t.amount),0) AS total
    FROM users u
    LEFT JOIN transactions t ON t.receiver_id=u.id AND DATE(t.created_at)=CURDATE()
    WHERE u.role='merchant'
    GROUP BY u.id ORDER BY total DESC LIMIT 5
");
$topMerchants = $topStmt->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page-wide">

  <!-- Page title -->
  <div class="d-flex align-items-center gap-2 mb-4">
    <h1 class="ep-heading mb-0" style="font-size:1.4rem;">🛡️ Admin Dashboard</h1>
    <span class="ep-badge ep-badge-gold ms-auto"><?= date('M d, Y') ?></span>
  </div>

  <!-- Stat cards -->
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['🎓','Students',       number_format($stats['student_count']),    'var(--gjc-green)'],
      ['🍱','Merchants',      number_format($stats['merchant_count']),   '#d97706'],
      ['🧾','Transactions',   number_format($stats['txn_count']),        '#16a34a'],
      ['📈',"Today's Volume", money((float)$stats['today_volume']),      '#2563eb'],
    ] as [$ico, $lbl, $val, $clr]): ?>
    <div class="col-6 col-md-3">
      <div class="ep-stat-card">
        <div style="font-size:1.6rem;margin-bottom:.3rem;"><?= $ico ?></div>
        <div class="ep-stat-value" style="color:<?= $clr ?>;"><?= $val ?></div>
        <div class="ep-stat-label"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">

    <!-- Quick links + top merchants -->
    <div class="col-12 col-md-4">
      <div class="ep-card h-100">
        <div class="ep-section-header">
          <i class="bi bi-lightning-fill"></i>
          <h3>Quick Actions</h3>
        </div>
        <div class="d-flex flex-column gap-2">
          <a href="<?= BASE_PATH ?>/admin/topup.php"     class="btn-ep btn-ep-primary w-100"><i class="bi bi-plus-circle me-1"></i> Top-Up Student Wallet</a>
          <a href="<?= BASE_PATH ?>/admin/add_user.php"  class="btn-ep btn-ep-navy w-100"><i class="bi bi-person-plus me-1"></i> Add New User</a>
          <a href="<?= BASE_PATH ?>/admin/users.php"     class="btn-ep btn-ep-outline w-100"><i class="bi bi-people me-1"></i> Manage Users</a>
          <a href="<?= BASE_PATH ?>/admin/transactions.php" class="btn-ep btn-ep-outline w-100"><i class="bi bi-list-ul me-1"></i> All Transactions</a>
        </div>

        <?php if (!empty($topMerchants)): ?>
        <hr class="ep-divider">
        <div style="font-size:.72rem;font-family:'Poppins',sans-serif;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--ep-muted);margin-bottom:.75rem;">
          Today's Top Merchants
        </div>
        <?php foreach ($topMerchants as $m): ?>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span style="font-size:.88rem;"><?= $m['avatar'] ?? '🏪' ?> <?= htmlspecialchars($m['name']) ?></span>
          <span class="ep-badge ep-badge-gold"><?= money((float)$m['total']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent transactions -->
    <div class="col-12 col-md-8">
      <div class="ep-card">
        <div class="ep-section-header">
          <i class="bi bi-arrow-left-right"></i>
          <h3>Recent Transactions</h3>
          <a href="<?= BASE_PATH ?>/admin/transactions.php" class="ep-section-badge" style="text-decoration:none;">See All</a>
        </div>
        <div style="overflow-x:auto;">
          <table class="ep-table">
            <thead><tr><th>From</th><th>To</th><th>Amount</th><th>Description</th><th>When</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $t): ?>
              <tr>
                <td><?= $t['sa'] ?> <strong><?= htmlspecialchars($t['sn']) ?></strong></td>
                <td><?= $t['ra'] ?> <?= htmlspecialchars($t['rn']) ?></td>
                <td style="font-family:'Poppins',sans-serif;font-weight:700;color:var(--ep-success);"><?= money((float)$t['amount']) ?></td>
                <td style="color:var(--ep-muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['description'] ?: '—') ?></td>
                <td style="color:var(--ep-subtle);font-size:.8rem;white-space:nowrap;"><?= timeAgo($t['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
