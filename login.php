<?php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/' . $_SESSION['role'] . '/dashboard.php'); exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Security token mismatch. Please refresh.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR student_id = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $bal = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $bal->execute([$user['id']]);
            $wallet = $bal->fetch();
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['avatar']     = $user['avatar'];
            $_SESSION['balance']    = $wallet['balance'] ?? 0;
            $_SESSION['student_id'] = $user['student_id'];
            header('Location: ' . BASE_PATH . '/' . $user['role'] . '/dashboard.php'); exit;
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    }
}
$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GJC EduPay — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="ep-login-wrap">

  <div class="ep-login-card">
    <div class="ep-login-logo">GJC</div>
    <h1 class="text-center mb-0" style="font-family:'Poppins',sans-serif;font-size:1.45rem;font-weight:800;color:#1e5c1e;">GJC EduPay</h1>
    <div class="ep-login-school">
      General de Jesus College<br>
      San Isidro, Nueva Ecija · Cashless Payment System
    </div>

    <?php if ($error): ?>
    <div class="ep-alert ep-alert-danger mb-3">
      <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= $token ?>">

      <div class="ep-form-group">
        <label class="ep-label">Student ID or Email</label>
        <div style="position:relative;">
          <span style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:#7a9a7a;font-size:1rem;">
            <i class="bi bi-person"></i>
          </span>
          <input type="text" name="identifier" class="ep-input" style="padding-left:2.5rem;"
                 placeholder="STU-2024-001 or you@school.edu"
                 value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                 required autofocus autocomplete="username">
        </div>
      </div>

      <div class="ep-form-group">
        <label class="ep-label">Password</label>
        <div style="position:relative;">
          <span style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:#7a9a7a;font-size:1rem;">
            <i class="bi bi-lock"></i>
          </span>
          <input type="password" name="password" id="pwd" class="ep-input"
                 style="padding-left:2.5rem;padding-right:2.8rem;"
                 placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" onclick="togglePwd()"
                  style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#7a9a7a;cursor:pointer;padding:.25rem;font-size:1rem;">
            <i class="bi bi-eye" id="pwd-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-ep btn-ep-navy w-100 mt-1" style="font-size:.95rem;padding:.75rem;">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
      </button>
    </form>

    <div style="display:flex;align-items:center;gap:.75rem;margin:1.25rem 0;">
      <div style="flex:1;height:1px;background:#d4e0d4;"></div>
      <span style="font-size:.7rem;color:#7a9a7a;font-family:'Poppins',sans-serif;font-weight:600;">DEMO ACCOUNTS</span>
      <div style="flex:1;height:1px;background:#d4e0d4;"></div>
    </div>

    <div style="font-size:.75rem;color:#4a6b4a;text-align:center;line-height:2;">
      Password for all: <code>password123</code><br>
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-1">
        <span>⚡ <code>STU-2024-001</code> Student</span>
        <span>🍱 <code>canteen@school.edu</code> Merchant</span>
        <span>🛡️ <code>admin@school.edu</code> Admin</span>
      </div>
    </div>
  </div>

  <div class="ep-footer-credit">
    © <?= date('Y') ?> General de Jesus College · San Isidro, Nueva Ecija · EduPay v1.0
  </div>

</div>
<script>
function togglePwd() {
  const i = document.getElementById('pwd');
  const ic = document.getElementById('pwd-icon');
  if (i.type==='password') { i.type='text'; ic.className='bi bi-eye-slash'; }
  else { i.type='password'; ic.className='bi bi-eye'; }
}
</script>
</body>
</html>
