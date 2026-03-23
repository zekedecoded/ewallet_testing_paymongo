<?php
// admin/topup.php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('admin');
$db = getDB();

$error   = '';
$success = '';
$student = null;

// Search student
if (!empty($_GET['student_id'])) {
    $sid = trim($_GET['student_id']);
    $stmt = $db->prepare("
        SELECT u.*, w.balance
        FROM users u
        JOIN wallets w ON w.user_id = u.id
        WHERE u.student_id = ? AND u.role = 'student'
        LIMIT 1
    ");
    $stmt->execute([$sid]);
    $student = $stmt->fetch();
    if (!$student) {
        $error = "No student found with ID: " . htmlspecialchars($sid);
    }
}

// Process top-up
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $note   = trim($_POST['note'] ?? 'Admin Top-Up');

        if ($amount <= 0) {
            $error = 'Top-up amount must be greater than ₱0.';
        } elseif ($amount > 10000) {
            $error = 'Single top-up limit is ₱10,000.';
        } else {
            try {
                $db->beginTransaction();

                // Add to student wallet
                $upd = $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
                $upd->execute([$amount, $userId]);

                // Record as transaction (admin → student)
                $ref = generateRef();
                $ins = $db->prepare("
                    INSERT INTO transactions (sender_id, receiver_id, amount, description, ref_code)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ins->execute([$session['user_id'], $userId, $amount, $note, $ref]);

                $db->commit();
                $success = "Successfully topped up " . money($amount) . " (Ref: $ref)";

                // Refresh student data
                $stmt = $db->prepare("
                    SELECT u.*, w.balance
                    FROM users u JOIN wallets w ON w.user_id = u.id
                    WHERE u.id = ? LIMIT 1
                ");
                $stmt->execute([$userId]);
                $student = $stmt->fetch();

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Top-up failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Top-Up Wallet';
$csrfToken = csrfToken();
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Top-Up Student Wallet</h1>
  </div>

  <?php if ($error): ?>
    <div class="ep-alert ep-alert-danger mb-3">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ep-alert ep-alert-success mb-3">
      <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <!-- Student Search -->
  <div class="ep-card mb-3">
    <h2 class="ep-heading mb-3" style="font-size:.95rem;">Find Student</h2>
    <form method="GET">
      <div class="d-flex gap-2">
        <input type="text" name="student_id" class="ep-input"
               placeholder="Student ID (e.g. STU-2024-001)"
               value="<?= htmlspecialchars($_GET['student_id'] ?? '') ?>">
        <button type="submit" class="btn-ep btn-ep-primary" style="white-space:nowrap;">
          <i class="bi bi-search"></i> Find
        </button>
      </div>
    </form>
  </div>

  <!-- Student Card + Top-Up Form -->
  <?php if ($student): ?>
  <div class="ep-card mb-3">
    <div class="d-flex align-items-center gap-3 mb-4">
      <div class="ep-txn-icon credit" style="width:52px;height:52px;font-size:1.5rem;">
        <?= $student['avatar'] ?? '🎓' ?>
      </div>
      <div>
        <div style="font-weight:600;font-size:1.05rem;"><?= htmlspecialchars($student['name']) ?></div>
        <div style="font-size:.8rem;color:var(--ep-muted);"><?= $student['student_id'] ?></div>
        <div style="font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--gjc-green);">
          Current: <?= money((float)$student['balance']) ?>
        </div>
      </div>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf"    value="<?= $csrfToken ?>">
      <input type="hidden" name="user_id" value="<?= $student['id'] ?>">

      <div class="ep-form-group">
        <label class="ep-label">Top-Up Amount (₱)</label>
        <input type="number" name="amount" class="ep-input"
               placeholder="0.00"
               step="0.01" min="1" max="10000"
               required autofocus
               style="font-size:1.4rem;font-family:'Syne',sans-serif;font-weight:700;">
      </div>

      <div class="ep-form-group">
        <label class="ep-label">Note / Reason</label>
        <input type="text" name="note" class="ep-input"
               placeholder="Admin Top-Up"
               maxlength="100"
               value="Admin Top-Up">
      </div>

      <!-- Quick amount buttons -->
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ([50, 100, 200, 500] as $quick): ?>
          <button type="button" class="btn-ep btn-ep-outline"
                  style="padding:.35rem .75rem;font-size:.82rem;"
                  onclick="document.querySelector('[name=amount]').value='<?= $quick ?>'">
            +₱<?= $quick ?>
          </button>
        <?php endforeach; ?>
      </div>

      <button type="submit" class="btn-ep btn-ep-primary w-100">
        <i class="bi bi-plus-circle me-1"></i> Confirm Top-Up
      </button>
    </form>
  </div>
  <?php elseif (empty($_GET['student_id'])): ?>
  <div class="ep-alert ep-alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Enter a Student ID above to search for a student account.
  </div>
  <?php endif; ?>

  <!-- Recent top-ups by this admin -->
  <?php
  $recentStmt = $db->prepare("
      SELECT t.*, u.name AS student_name, u.student_id, u.avatar
      FROM transactions t
      JOIN users u ON t.receiver_id = u.id
      WHERE t.sender_id = ?
      ORDER BY t.created_at DESC LIMIT 8
  ");
  $recentStmt->execute([$session['user_id']]);
  $recentTopups = $recentStmt->fetchAll();
  ?>

  <?php if (!empty($recentTopups)): ?>
  <div class="ep-card">
    <h2 class="ep-heading mb-3" style="font-size:.95rem;">Recent Top-Ups by You</h2>
    <ul class="ep-txn-list">
      <?php foreach ($recentTopups as $t): ?>
      <li class="ep-txn-item">
        <div class="ep-txn-icon credit"><?= $t['avatar'] ?? '🎓' ?></div>
        <div class="ep-txn-meta">
          <div class="ep-txn-desc"><?= htmlspecialchars($t['student_name']) ?></div>
          <div class="ep-txn-time"><?= $t['student_id'] ?> · <?= timeAgo($t['created_at']) ?></div>
        </div>
        <div class="ep-txn-amount credit">+<?= money((float)$t['amount']) ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
