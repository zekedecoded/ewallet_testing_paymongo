<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');
$pageTitle = 'Scan to Pay';

// Detect if we're on a secure context
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
         || $_SERVER['HTTP_HOST'] === 'localhost'
         || $_SERVER['HTTP_HOST'] === '127.0.0.1'
         || str_ends_with($_SERVER['HTTP_HOST'], '.ngrok-free.app')
         || str_ends_with($_SERVER['HTTP_HOST'], '.ngrok.io');

include __DIR__ . '/../includes/header.php';
?>

<div class="ep-page">

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_PATH ?>/student/dashboard.php" style="color:var(--ep-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="ep-heading mb-0" style="font-size:1.3rem;">Scan QR Code</h1>
  </div>

  <?php if (!$isSecure): ?>
  <!-- ── HTTPS BLOCKER WARNING ── -->
  <div class="ep-alert ep-alert-danger mb-3">
    <strong><i class="bi bi-shield-lock me-1"></i> Camera blocked — HTTPS required</strong><br>
    <span style="font-size:.85rem;">
      Your browser requires a secure connection to access the camera.
      You are on <code><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></code> over plain HTTP.
    </span>
  </div>

  <div class="ep-card mb-3">
    <h2 class="ep-heading mb-3" style="font-size:1rem;"><i class="bi bi-lightning-fill me-1" style="color:var(--gjc-yellow);"></i> Fix Options</h2>

    <!-- Option 1: ngrok -->
    <div style="border:1.5px solid var(--ep-border);border-radius:10px;padding:1rem;margin-bottom:.75rem;">
      <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.9rem;color:var(--gjc-green);margin-bottom:.4rem;">
        ✅ Option 1: Use ngrok (recommended, free)
      </div>
      <div style="font-size:.82rem;color:var(--ep-muted);line-height:1.8;">
        1. Download ngrok from <strong>ngrok.com</strong><br>
        2. Open Command Prompt / Terminal<br>
        3. Run: <code>ngrok http 80</code><br>
        4. Copy the <code>https://xxxx.ngrok-free.app</code> URL<br>
        5. Open that URL on your phone — camera will work!
      </div>
    </div>

    <!-- Option 2: XAMPP HTTPS -->
    <div style="border:1.5px solid var(--ep-border);border-radius:10px;padding:1rem;margin-bottom:.75rem;">
      <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.9rem;color:var(--gjc-green);margin-bottom:.4rem;">
        🔧 Option 2: Enable HTTPS on XAMPP
      </div>
      <div style="font-size:.82rem;color:var(--ep-muted);line-height:1.8;">
        1. Open <strong>XAMPP Control Panel → Apache → Config → httpd-ssl.conf</strong><br>
        2. Or simply use <strong>mkcert</strong> to create a local SSL certificate<br>
        3. Access via <code>https://localhost/eWallet</code>
      </div>
    </div>

    <!-- Option 3: Chrome flag (Android only) -->
    <div style="border:1.5px solid var(--ep-border);border-radius:10px;padding:1rem;">
      <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.9rem;color:var(--gjc-green);margin-bottom:.4rem;">
        📱 Option 3: Chrome flag (Android only, temporary)
      </div>
      <div style="font-size:.82rem;color:var(--ep-muted);line-height:1.8;">
        1. On Android Chrome, go to: <code>chrome://flags</code><br>
        2. Search: <strong>Insecure origins treated as secure</strong><br>
        3. Add your IP: <code>http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></code><br>
        4. Tap <strong>Relaunch</strong> — camera will work on that IP<br>
        <em style="color:var(--ep-subtle);">⚠️ Only for testing. iOS does not support this.</em>
      </div>
    </div>
  </div>

  <!-- Manual token fallback always visible when no HTTPS -->
  <div class="ep-card">
    <h2 class="ep-heading mb-2" style="font-size:.95rem;"><i class="bi bi-keyboard me-1"></i> Use Manual Token Instead</h2>
    <p style="font-size:.82rem;color:var(--ep-muted);margin-bottom:.75rem;">
      Ask the merchant to share the token text from their QR page. Paste it below to pay without scanning.
    </p>
    <label class="ep-label">Payment Token</label>
    <input type="text" id="manual-token" class="ep-input mb-2"
           placeholder="Paste token here…"
           autocomplete="off" autocorrect="off" spellcheck="false">
    <button class="btn-ep btn-ep-navy w-100" onclick="submitManualToken()">
      <i class="bi bi-arrow-right-circle me-1"></i> Continue to Payment
    </button>
  </div>

  <?php else: ?>
  <!-- ── CAMERA SCANNER (secure context) ── -->

  <div id="status-bar" class="ep-alert ep-alert-info mb-3" style="display:flex;align-items:center;gap:.6rem;">
    <div class="ep-spinner" style="width:18px;height:18px;border-width:2px;margin:0;flex-shrink:0;"></div>
    <span id="status-text">Loading scanner…</span>
  </div>

  <div id="error-box" class="ep-alert ep-alert-danger mb-3" style="display:none;"></div>

  <div class="ep-card mb-3" style="padding:1rem;">
    <div id="qr-reader" style="width:100%;border-radius:10px;overflow:hidden;"></div>
  </div>

  <div class="d-flex gap-2 mb-3">
    <button id="btn-restart" class="btn-ep btn-ep-outline w-100" onclick="restartScanner()" style="display:none;">
      <i class="bi bi-arrow-clockwise me-1"></i> Restart Camera
    </button>
    <button id="btn-stop" class="btn-ep btn-ep-outline w-100" onclick="stopScanner()" style="display:none;">
      <i class="bi bi-stop-circle me-1"></i> Stop
    </button>
  </div>

  <!-- Manual fallback always available -->
  <details class="ep-card">
    <summary style="font-size:.85rem;color:var(--ep-muted);list-style:none;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
      <i class="bi bi-keyboard"></i> Can't scan? Enter token manually
    </summary>
    <div style="margin-top:1rem;">
      <label class="ep-label">Payment Token</label>
      <input type="text" id="manual-token" class="ep-input mb-2" placeholder="Paste token here…">
      <button class="btn-ep btn-ep-navy w-100" onclick="submitManualToken()">
        <i class="bi bi-arrow-right-circle me-1"></i> Continue to Payment
      </button>
    </div>
  </details>

  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script>
  const BASE = '<?= BASE_PATH ?>';
  let scanner = null, isScanning = false, scanned = false;

  const statusBar  = document.getElementById('status-bar');
  const statusText = document.getElementById('status-text');
  const errorBox   = document.getElementById('error-box');
  const btnRestart = document.getElementById('btn-restart');
  const btnStop    = document.getElementById('btn-stop');

  function setStatus(msg, type) {
    statusText.textContent = msg;
    const colors = { info:'var(--gjc-green)', success:'var(--ep-success)', warning:'var(--ep-warning)', error:'var(--ep-danger)' };
    statusText.style.color = colors[type] || colors.info;
    const sp = statusBar.querySelector('.ep-spinner');
    if (sp && type !== 'info') sp.style.display = 'none';
  }

  function showError(msg) {
    errorBox.innerHTML = msg;
    errorBox.style.display = 'block';
    btnRestart.style.display = 'block';
    btnStop.style.display = 'none';
    setStatus('Scanner stopped.', 'error');
    isScanning = false;
  }

  function onScanSuccess(decodedText) {
    if (scanned) return;
    scanned = true;
    setStatus('QR detected! Verifying…', 'success');
    stopScanner();

    let data;
    try { data = JSON.parse(decodedText); }
    catch(e) { showError('❌ Not a valid GJC EduPay QR code. Ask merchant to regenerate.'); scanned = false; return; }

    if (!data.token || !data.merchant_id || !data.amount) {
      showError('❌ QR code missing required fields.'); scanned = false; return;
    }
    if (data.exp && Date.now()/1000 > data.exp) {
      showError('⏰ This QR code has <strong>expired</strong>. Ask merchant to generate a new one.'); scanned = false; return;
    }

    const params = new URLSearchParams({ token: data.token, merchant_id: data.merchant_id, amount: data.amount, desc: data.desc || '' });
    window.location.href = BASE + '/student/confirm_payment.php?' + params.toString();
  }

  async function startScanner() {
    errorBox.style.display = 'none';
    scanned = false;
    setStatus('Requesting camera…', 'info');

    if (typeof Html5Qrcode === 'undefined') {
      showError('Scanner library failed to load. Check internet and refresh.'); return;
    }

    if (scanner) {
      try { await scanner.stop(); } catch(e) {}
      try { scanner.clear(); }   catch(e) {}
      scanner = null;
    }
    document.getElementById('qr-reader').innerHTML = '';
    scanner = new Html5Qrcode('qr-reader', { verbose: false });

    const config = {
      fps: 10,
      qrbox: (w, h) => ({ width: Math.min(w, h, 280), height: Math.min(w, h, 280) }),
      aspectRatio: 1.0
    };

    // Try back camera first, fallback to front
    for (const facing of ['environment', 'user']) {
      try {
        await scanner.start({ facingMode: facing }, config, onScanSuccess, ()=>{});
        isScanning = true;
        setStatus(facing === 'environment' ? '📷 Camera ready — point at QR code' : '🤳 Front camera active', 'success');
        btnStop.style.display = 'block';
        btnRestart.style.display = 'none';
        return;
      } catch(e) {
        if (facing === 'user') handleCameraError(e);
      }
    }
  }

  function handleCameraError(err) {
    const msg = String(err?.message || err).toLowerCase();
    if (msg.includes('permission') || msg.includes('denied') || msg.includes('notallowed'))
      showError('🔒 <strong>Camera permission denied.</strong> Tap the 🔒 icon in your browser address bar → allow Camera → refresh.');
    else if (msg.includes('notfound') || msg.includes('devicenotfound'))
      showError('📷 No camera found on this device. Use the manual token entry below.');
    else if (msg.includes('notreadable') || msg.includes('busy') || msg.includes('in use'))
      showError('📷 Camera is being used by another app. Close other apps and tap <strong>Restart Camera</strong>.');
    else
      showError('❌ Camera error: ' + (err?.message || err) + '<br>Try the manual token entry below.');
  }

  async function stopScanner() {
    if (scanner && isScanning) { try { await scanner.stop(); } catch(e) {} isScanning = false; }
    btnStop.style.display = 'none';
    btnRestart.style.display = 'block';
    setStatus('Camera stopped.', 'warning');
  }

  async function restartScanner() {
    btnRestart.style.display = 'none';
    await startScanner();
  }

  window.addEventListener('load', () => setTimeout(startScanner, 400));
  window.addEventListener('beforeunload', () => { if (scanner && isScanning) try { scanner.stop(); } catch(e) {} });
  </script>
  <?php endif; ?>

</div>

<!-- Manual token JS always available -->
<script>
const BASE_PATH_JS = '<?= BASE_PATH ?>';
function submitManualToken() {
  const token = (document.getElementById('manual-token')?.value || '').trim();
  if (!token) { alert('Please paste a payment token first.'); return; }
  window.location.href = BASE_PATH_JS + '/student/confirm_payment.php?token=' + encodeURIComponent(token);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
