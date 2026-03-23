<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('merchant');

$db     = getDB();
$qrData = null;
$error  = '';
$token  = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $desc   = trim($_POST['description'] ?? '');

        if ($amount <= 0) {
            $error = 'Amount must be greater than ₱0.';
        } elseif ($amount > 99999) {
            $error = 'Amount exceeds maximum limit of ₱99,999.';
        } else {
            $qrToken   = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + QR_TTL_SECONDS);
            $payload   = $session['user_id'] . '|' . $amount . '|' . $expiresAt;
            $sig       = hash_hmac('sha256', $payload, QR_SECRET_KEY);

            $ins = $db->prepare("
                INSERT INTO qr_tokens (token, merchant_id, amount, description, expires_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$qrToken, $session['user_id'], $amount, $desc, $expiresAt]);

            $qrData = json_encode([
                'token'       => $qrToken,
                'merchant_id' => (int)$session['user_id'],
                'amount'      => $amount,
                'desc'        => $desc,
                'sig'         => substr($sig, 0, 16),
                'exp'         => strtotime($expiresAt)
            ]);
        }
    }
}

$pageTitle = 'Generate QR';
include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/merchant/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Generate Payment QR</h1>
  </div>

  <?php if ($error): ?>
    <div class="ep-alert ep-alert-danger mb-3">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!$qrData): ?>
  <!-- ── INPUT FORM ── -->
  <div class="ep-card">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $token ?>">

      <div class="ep-form-group">
        <label class="ep-label">Amount (₱)</label>
        <input type="number" name="amount" class="ep-input"
               placeholder="0.00" step="0.01" min="1" max="99999"
               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
               required autofocus
               style="font-size:1.6rem;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;text-align:center;letter-spacing:-.02em;">
      </div>

      <!-- Quick amount buttons -->
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ([20, 35, 50, 75, 100, 150] as $q): ?>
          <button type="button" class="btn-ep btn-ep-outline"
                  style="padding:.3rem .7rem;font-size:.8rem;flex:1;min-width:50px;"
                  onclick="document.querySelector('[name=amount]').value='<?= $q ?>'">
            ₱<?= $q ?>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="ep-form-group">
        <label class="ep-label">Item / Description</label>
        <input type="text" name="description" class="ep-input"
               placeholder="e.g. Lunch Meal, School Supplies, Canteen Order…"
               maxlength="100"
               value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
      </div>

      <div class="ep-alert ep-alert-info mb-3" style="font-size:.82rem;">
        <i class="bi bi-clock me-1"></i>
        QR expires in <strong><?= QR_TTL_SECONDS ?> seconds</strong> and can only be used once.
      </div>

      <button type="submit" class="btn-ep btn-ep-navy w-100">
        <i class="bi bi-qr-code me-1"></i> Generate QR Code
      </button>
    </form>
  </div>

  <?php else: ?>
  <!-- ── QR DISPLAY ── -->
  <?php $qrDecoded = json_decode($qrData, true); ?>

  <div class="ep-card text-center">

    <!-- Amount header -->
    <div style="background:var(--gjc-green);margin:-1.5rem -1.5rem 1.25rem;padding:1.25rem;border-radius:14px 14px 0 0;">
      <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.55);font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;">Amount Due</div>
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:2.4rem;font-weight:900;color:#fff;line-height:1.1;letter-spacing:-.02em;">
        ₱<?= number_format((float)$qrDecoded['amount'], 2) ?>
      </div>
      <div style="font-size:.85rem;color:rgba(255,255,255,.6);margin-top:.15rem;">
        <?= htmlspecialchars($qrDecoded['desc'] ?: 'No description') ?>
      </div>
    </div>

    <!-- QR Code -->
    <p style="font-size:.8rem;color:var(--ep-muted);margin-bottom:.75rem;">
      Ask the student to scan this QR code
    </p>
    <div class="ep-qr-wrapper d-inline-block mb-3">
      <div id="qrcode"></div>
    </div>

    <!-- Countdown -->
    <div style="font-size:.7rem;color:var(--ep-muted);margin-bottom:.2rem;font-family:'Plus Jakarta Sans',sans-serif;text-transform:uppercase;letter-spacing:.1em;">Expires in</div>
    <div class="ep-qr-countdown" id="countdown"><?= QR_TTL_SECONDS ?>s</div>

    <div id="expired-msg" class="ep-alert ep-alert-danger mt-3" style="display:none;">
      <i class="bi bi-x-circle me-1"></i>This QR code has expired. Please generate a new one.
    </div>

    <!-- GJC watermark text -->
    <div style="margin-top:1rem;font-size:.72rem;color:var(--ep-muted);">
      General de Jesus College · Secure Payment
    </div>
  </div>


  <!-- Token share section — for when student can't scan -->
  <div class="ep-card mt-3" style="background:var(--gjc-green-pale);border-color:var(--ep-border2);">
    <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.85rem;color:var(--gjc-green);margin-bottom:.5rem;">
      <i class="bi bi-share me-1"></i> Student can\'t scan?
    </div>
    <p style="font-size:.8rem;color:var(--ep-muted);margin-bottom:.75rem;">
      Copy the token below and send it to the student via chat. They can paste it on the scan page.
    </p>
    <div style="display:flex;gap:.5rem;align-items:center;">
      <input type="text" id="token-display"
             value="<?= $qrDecoded[\'token\'] ?>"
             class="ep-input" style="font-size:.72rem;font-family:monospace;color:var(--gjc-green);"
             readonly onclick="this.select()">
      <button onclick="copyToken()" id="copy-btn"
              class="btn-ep btn-ep-navy" style="white-space:nowrap;flex-shrink:0;">
        <i class="bi bi-clipboard me-1"></i> Copy
      </button>
    </div>
  </div>

  <script>
  function copyToken() {
    const val = document.getElementById(\'token-display\').value;
    navigator.clipboard.writeText(val).then(() => {
      const btn = document.getElementById(\'copy-btn\');
      btn.innerHTML = \'<i class="bi bi-check-lg me-1"></i> Copied!\';
      btn.style.background = \'var(--ep-success)\';
      setTimeout(() => {
        btn.innerHTML = \'<i class="bi bi-clipboard me-1"></i> Copy\';
        btn.style.background = \'\';
      }, 2000);
    }).catch(() => {
      document.getElementById(\'token-display\').select();
      document.execCommand(\'copy\');
    });
  }
  </script>

  <a href="<?= BASE_PATH ?>/merchant/generate_qr.php" class="btn-ep btn-ep-outline w-100 d-block text-center text-decoration-none mt-3">
    <i class="bi bi-arrow-clockwise me-1"></i> Generate New QR
  </a>

  <!-- qrcodejs loaded inline here — BEFORE the script that calls new QRCode() -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
  // Fallback CDN in case cdnjs is slow
  if (typeof QRCode === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
    s.onload = initQR;
    document.head.appendChild(s);
  } else {
    initQR();
  }

  function initQR() {
    var qrData = <?= json_encode($qrData) ?>;
    new QRCode(document.getElementById('qrcode'), {
      text:         qrData,
      width:        220,
      height:       220,
      colorDark:    '#0a1f5c',
      colorLight:   '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  // Countdown timer
  var sec = <?= QR_TTL_SECONDS ?>;
  var el  = document.getElementById('countdown');
  var exp = document.getElementById('expired-msg');
  var iv  = setInterval(function() {
    sec--;
    if (el) {
      el.textContent = sec + 's';
      if (sec <= 30) el.classList.add('urgent');
    }
    if (sec <= 0) {
      clearInterval(iv);
      if (el)  el.style.display = 'none';
      if (exp) exp.style.display = 'block';
    }
  }, 1000);
  </script>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
