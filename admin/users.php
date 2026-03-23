<?php
// admin/users.php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('admin');
$db = getDB();

$search = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? '';

$where  = [];
$params = [];
if ($search) {
    $where[]  = "(u.name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($role) {
    $where[]  = "u.role = ?";
    $params[] = $role;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT u.*, w.balance
    FROM users u
    JOIN wallets w ON w.user_id = u.id
    {$whereSql}
    ORDER BY u.role, u.name
    LIMIT 50
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page-wide">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Manage Users</h1>
    <a href="<?= BASE_PATH ?>/admin/add_user.php" class="btn-ep btn-ep-navy ms-auto" style="padding:.4rem .9rem;font-size:.85rem;white-space:nowrap;">
      <i class="bi bi-person-plus me-1"></i> Add User
    </a>
  </div>

  <!-- Filters -->
  <div class="ep-card mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="text" name="q" class="ep-input" style="flex:1;min-width:180px;"
             placeholder="Search name, ID, email…"
             value="<?= htmlspecialchars($search) ?>">
      <select name="role" class="ep-input" style="width:auto;">
        <option value="">All Roles</option>
        <option value="student"  <?= $role==='student'  ? 'selected' : '' ?>>Students</option>
        <option value="merchant" <?= $role==='merchant' ? 'selected' : '' ?>>Merchants</option>
        <option value="admin"    <?= $role==='admin'    ? 'selected' : '' ?>>Admins</option>
      </select>
      <button type="submit" class="btn-ep btn-ep-primary">
        <i class="bi bi-funnel"></i> Filter
      </button>
    </form>
  </div>

  <div class="ep-card">
    <?php if (empty($users)): ?>
      <div class="text-center py-4" style="color:var(--ep-muted);">No users found.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="ep-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Student ID</th>
            <th>Role</th>
            <th>Balance</th>
            <th>Email</th>
            <th>Joined</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <span style="font-size:1.1rem;"><?= $u['avatar'] ?? '👤' ?></span>
              <strong><?= htmlspecialchars($u['name']) ?></strong>
            </td>
            <td>
              <code style="font-size:.8rem;color:var(--ep-muted);"><?= $u['student_id'] ?? '—' ?></code>
            </td>
            <td>
              <?php
              $badge = match($u['role']) {
                'admin'    => 'ep-badge-warning',
                'merchant' => 'ep-badge-info',
                default    => 'ep-badge-success'
              };
              ?>
              <span class="ep-badge <?= $badge ?>"><?= $u['role'] ?></span>
            </td>
            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--gjc-green);">
              <?= money((float)$u['balance']) ?>
            </td>
            <td style="font-size:.82rem;color:var(--ep-muted);"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td style="font-size:.78rem;color:var(--ep-muted);">
              <?= date('M d, Y', strtotime($u['created_at'])) ?>
            </td>
            <td>
              <?php if ($u['role'] === 'student'): ?>
                <a href="<?= BASE_PATH ?>/admin/topup.php?student_id=<?= urlencode($u['student_id']) ?>"
                   class="ep-badge ep-badge-success" style="text-decoration:none;cursor:pointer;">
                  Top-Up
                </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="font-size:.78rem;color:var(--ep-muted);padding:.5rem 0 0;">
      Showing <?= count($users) ?> users
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
