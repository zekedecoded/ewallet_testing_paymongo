<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('admin');
$db = getDB();

$error   = '';
$success = '';
$csrfToken = csrfToken();

// Avatar options by role
$avatars = [
    'student'  => ['⚡','🌸','🎯','🏆','🎓','📚','🌟','🔥','💡','🎵','⚽','🎨','🧠','🌈','🦋'],
    'merchant' => ['🍱','🍔','☕','🛒','🥗','🍕','🧋','🏪','🍜','🍰'],
    'admin'    => ['🛡️','⚙️','🔑','👔','🏫'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Security token mismatch. Please refresh.';
    } else {
        $name       = trim($_POST['name']       ?? '');
        $role       = trim($_POST['role']        ?? 'student');
        $email      = trim($_POST['email']       ?? '');
        $student_id = trim($_POST['student_id']  ?? '');
        $password   = $_POST['password']         ?? '';
        $password2  = $_POST['password2']        ?? '';
        $avatar     = $_POST['avatar']            ?? '🎓';
        $balance    = (float)($_POST['balance']  ?? 0);

        // ── Validation ──────────────────────────────────────────
        if (empty($name)) {
            $error = 'Full name is required.';
        } elseif (strlen($name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } elseif (!in_array($role, ['student','merchant','admin'])) {
            $error = 'Invalid role selected.';
        } elseif (empty($password)) {
            $error = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } elseif ($role === 'student' && empty($student_id)) {
            $error = 'Student ID is required for student accounts.';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($balance < 0) {
            $error = 'Initial balance cannot be negative.';
        } else {
            // Check for duplicates
            if ($role === 'student' && !empty($student_id)) {
                $chk = $db->prepare("SELECT id FROM users WHERE student_id = ?");
                $chk->execute([$student_id]);
                if ($chk->fetch()) $error = "Student ID «{$student_id}» is already registered.";
            }
            if (!$error && !empty($email)) {
                $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetch()) $error = "Email «{$email}» is already registered.";
            }
        }

        // ── Insert ───────────────────────────────────────────────
        if (!$error) {
            try {
                $db->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

                $ins = $db->prepare("
                    INSERT INTO users (name, student_id, role, email, password, avatar)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $name,
                    ($role === 'student' && $student_id) ? $student_id : null,
                    $role,
                    $email ?: null,
                    $hash,
                    $avatar
                ]);
                $newId = $db->lastInsertId();

                // Create wallet
                $walIns = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
                $walIns->execute([$newId, $balance]);

                // If initial balance > 0, log it as a top-up transaction
                if ($balance > 0) {
                    $ref = generateRef();
                    $txn = $db->prepare("
                        INSERT INTO transactions (sender_id, receiver_id, amount, description, ref_code)
                        VALUES (?, ?, ?, 'Initial Balance / Admin Top-Up', ?)
                    ");
                    $txn->execute([$session['user_id'], $newId, $balance, $ref]);
                }

                $db->commit();
                $success = "Account created successfully for {$name}!";

                // Clear form on success
                $_POST = [];

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to create account: ' . $e->getMessage();
            }
        }
    }
}

// Auto-generate next student ID
$lastId = $db->query("
    SELECT student_id FROM users
    WHERE role='student' AND student_id REGEXP '^STU-[0-9]{4}-[0-9]+$'
    ORDER BY id DESC LIMIT 1
")->fetchColumn();

$nextStudentId = 'STU-' . date('Y') . '-001';
if ($lastId) {
    preg_match('/(\d+)$/', $lastId, $m);
    $nextNum = str_pad((int)$m[1] + 1, 3, '0', STR_PAD_LEFT);
    $nextStudentId = 'STU-' . date('Y') . '-' . $nextNum;
}

$pageTitle = 'Add New User';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/admin/users.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Add New User</h1>
  </div>

  <?php if ($success): ?>
  <div class="ep-alert ep-alert-success mb-3">
    <i class="bi bi-check-circle me-1"></i><strong><?= htmlspecialchars($success) ?></strong>
    <div class="d-flex gap-2 mt-2 flex-wrap">
      <a href="<?= BASE_PATH ?>/admin/add_user.php" class="btn-ep btn-ep-navy" style="font-size:.82rem;padding:.4rem .9rem;">
        <i class="bi bi-plus me-1"></i> Add Another
      </a>
      <a href="<?= BASE_PATH ?>/admin/users.php" class="btn-ep btn-ep-outline" style="font-size:.82rem;padding:.4rem .9rem;">
        <i class="bi bi-people me-1"></i> View All Users
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="ep-alert ep-alert-danger mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <div class="ep-card">
    <form method="POST" id="add-user-form">
      <input type="hidden" name="csrf" value="<?= $csrfToken ?>">

      <!-- ── ROLE SELECTOR ── -->
      <div class="ep-form-group">
        <label class="ep-label">Account Role</label>
        <div class="d-flex gap-2">
          <?php foreach (['student' => ['🎓','Student'], 'merchant' => ['🍱','Merchant'], 'admin' => ['🛡️','Admin']] as $r => [$ico, $lbl]): ?>
          <label style="flex:1;cursor:pointer;">
            <input type="radio" name="role" value="<?= $r ?>"
                   <?= (($_POST['role'] ?? 'student') === $r) ? 'checked' : '' ?>
                   onchange="onRoleChange(this.value)"
                   style="position:absolute;opacity:0;width:0;">
            <div class="role-pill" id="pill-<?= $r ?>"
                 style="border:2px solid var(--ep-border);border-radius:10px;padding:.6rem;text-align:center;transition:all .15s;<?= (($_POST['role'] ?? 'student') === $r) ? 'border-color:var(--gjc-green);background:var(--gjc-yellow-pale);' : '' ?>">
              <div style="font-size:1.3rem;"><?= $ico ?></div>
              <div style="font-size:.78rem;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:var(--gjc-green);"><?= $lbl ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <hr class="ep-divider">

      <!-- ── BASIC INFO ── -->
      <div class="ep-form-group">
        <label class="ep-label">Full Name <span style="color:var(--ep-danger);">*</span></label>
        <input type="text" name="name" class="ep-input"
               placeholder="e.g. Juan dela Cruz"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               required>
      </div>

      <!-- Student ID — shown only for students -->
      <div class="ep-form-group" id="student-id-group"
           style="<?= (($_POST['role'] ?? 'student') !== 'student') ? 'display:none;' : '' ?>">
        <label class="ep-label">
          Student ID <span style="color:var(--ep-danger);">*</span>
          <span style="color:var(--ep-muted);font-size:.7rem;text-transform:none;letter-spacing:0;font-weight:400;margin-left:.4rem;">
            Auto-suggested: <code style="color:var(--gjc-green);"><?= $nextStudentId ?></code>
          </span>
        </label>
        <input type="text" name="student_id" id="student-id-input" class="ep-input"
               placeholder="<?= $nextStudentId ?>"
               value="<?= htmlspecialchars($_POST['student_id'] ?? $nextStudentId) ?>">
      </div>

      <div class="ep-form-group">
        <label class="ep-label">Email Address <span style="color:var(--ep-muted);font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
        <input type="email" name="email" class="ep-input"
               placeholder="student@gendejesus.edu.ph"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <hr class="ep-divider">

      <!-- ── AVATAR PICKER ── -->
      <div class="ep-form-group">
        <label class="ep-label">Avatar</label>
        <div id="avatar-grid" style="display:flex;flex-wrap:wrap;gap:.4rem;">
          <?php
          $currentRole = $_POST['role'] ?? 'student';
          $selectedAvatar = $_POST['avatar'] ?? $avatars[$currentRole][0];
          foreach ($avatars[$currentRole] as $av):
          ?>
          <label style="cursor:pointer;">
            <input type="radio" name="avatar" value="<?= $av ?>"
                   <?= ($selectedAvatar === $av) ? 'checked' : '' ?>
                   style="position:absolute;opacity:0;width:0;">
            <div class="avatar-opt" style="width:38px;height:38px;border-radius:8px;display:grid;place-items:center;font-size:1.2rem;border:2px solid <?= ($selectedAvatar === $av) ? 'var(--gjc-green)' : 'var(--ep-border)' ?>;background:<?= ($selectedAvatar === $av) ? 'var(--gjc-yellow-pale)' : 'var(--ep-surface2)' ?>;transition:all .15s;">
              <?= $av ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <hr class="ep-divider">

      <!-- ── PASSWORD ── -->
      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="ep-label">Password <span style="color:var(--ep-danger);">*</span></label>
          <input type="password" name="password" class="ep-input"
                 placeholder="Min. 6 characters" required minlength="6">
        </div>
        <div class="col-6">
          <label class="ep-label">Confirm Password <span style="color:var(--ep-danger);">*</span></label>
          <input type="password" name="password2" class="ep-input"
                 placeholder="Repeat password" required>
        </div>
      </div>

      <div style="margin-bottom:1rem;">
        <div style="font-size:.78rem;color:var(--ep-muted);margin-bottom:.4rem;">Quick password options:</div>
        <div class="d-flex gap-2 flex-wrap">
          <?php foreach (['gjc2024', 'student123', 'edupay123'] as $pw): ?>
          <button type="button" class="btn-ep btn-ep-outline"
                  style="padding:.25rem .65rem;font-size:.75rem;"
                  onclick="setPassword('<?= $pw ?>')">
            <?= $pw ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <hr class="ep-divider">

      <!-- ── INITIAL BALANCE (students only) ── -->
      <div class="ep-form-group" id="balance-group"
           style="<?= (($_POST['role'] ?? 'student') !== 'student') ? 'display:none;' : '' ?>">
        <label class="ep-label">
          Initial Wallet Balance (₱)
          <span style="color:var(--ep-muted);font-weight:400;text-transform:none;letter-spacing:0;"> — optional</span>
        </label>
        <input type="number" name="balance" class="ep-input"
               placeholder="0.00" step="0.01" min="0" max="10000"
               value="<?= htmlspecialchars($_POST['balance'] ?? '0') ?>">
        <div style="font-size:.75rem;color:var(--ep-muted);margin-top:.3rem;">
          Leave at 0 and use the Top-Up page to add balance later.
        </div>
      </div>

      <!-- ── SUBMIT ── -->
      <button type="submit" class="btn-ep btn-ep-navy w-100" style="margin-top:.5rem;">
        <i class="bi bi-person-plus me-1"></i> Create Account
      </button>
    </form>
  </div>

  <!-- Bulk add hint -->
  <div class="ep-card mt-3" style="background:var(--gjc-yellow-pale);border-color:var(--gjc-yellow);">
    <div style="font-size:.82rem;color:var(--gjc-green);">
      <strong><i class="bi bi-lightbulb me-1"></i>Adding many students at once?</strong><br>
      You can insert multiple rows directly via phpMyAdmin SQL tab using the seed format in <code>schema.sql</code>, or ask your developer to build a CSV import feature.
    </div>
  </div>

</div>

<script>
const avatarSets = <?= json_encode($avatars) ?>;

function onRoleChange(role) {
  // Toggle Student ID field
  document.getElementById('student-id-group').style.display =
    role === 'student' ? '' : 'none';
  // Toggle Balance field
  document.getElementById('balance-group').style.display =
    role === 'student' ? '' : 'none';

  // Update pill styles
  ['student','merchant','admin'].forEach(r => {
    const pill = document.getElementById('pill-' + r);
    if (r === role) {
      pill.style.borderColor = 'var(--gjc-green)';
      pill.style.background  = 'var(--gjc-yellow-pale)';
    } else {
      pill.style.borderColor = 'var(--ep-border)';
      pill.style.background  = '';
    }
  });

  // Rebuild avatar grid for the role
  const grid = document.getElementById('avatar-grid');
  grid.innerHTML = '';
  (avatarSets[role] || []).forEach((av, i) => {
    const lbl = document.createElement('label');
    lbl.style.cursor = 'pointer';
    lbl.innerHTML = `
      <input type="radio" name="avatar" value="${av}" ${i===0?'checked':''}
             style="position:absolute;opacity:0;width:0;"
             onchange="highlightAvatar(this)">
      <div class="avatar-opt" style="width:38px;height:38px;border-radius:8px;display:grid;place-items:center;font-size:1.2rem;border:2px solid ${i===0?'var(--gjc-green)':'var(--ep-border)'};background:${i===0?'var(--gjc-yellow-pale)':'var(--ep-surface2)'};transition:all .15s;">
        ${av}
      </div>`;
    grid.appendChild(lbl);
  });
}

function highlightAvatar(input) {
  document.querySelectorAll('.avatar-opt').forEach(d => {
    d.style.borderColor = 'var(--ep-border)';
    d.style.background  = 'var(--ep-surface2)';
  });
  const opt = input.nextElementSibling;
  opt.style.borderColor = 'var(--gjc-green)';
  opt.style.background  = 'var(--gjc-yellow-pale)';
}

// Wire up existing avatar options on page load
document.querySelectorAll('input[name="avatar"]').forEach(inp => {
  inp.addEventListener('change', () => highlightAvatar(inp));
});

function setPassword(pw) {
  document.querySelectorAll('[name="password"],[name="password2"]').forEach(i => i.value = pw);
}

// Client-side password match validation
document.getElementById('add-user-form').addEventListener('submit', function(e) {
  const p1 = this.querySelector('[name="password"]').value;
  const p2 = this.querySelector('[name="password2"]').value;
  if (p1 !== p2) {
    e.preventDefault();
    alert('Passwords do not match. Please re-enter.');
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
