<?php
// ============================================================
// includes/config.php  –  Database config & global constants
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_ewallet');

define('APP_NAME',       'EduPay');

// Base path — change this to match your XAMPP setup:
// '' if eWallet/ is your web root (localhost/)
// '/eWallet' if accessed via localhost/eWallet/
define('BASE_PATH', '/eWallet');
define('APP_CURRENCY',   '₱');
define('QR_SECRET_KEY',  'EduPay_S3cr3t_S@lt_2024');  // Change in production!
define('QR_TTL_SECONDS', 120);  // QR expires after 2 minutes

// ─── PDO Connection ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// ─── Session helper ──────────────────────────────────────────
function requireLogin(string $role = ''): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login.php');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        http_response_code(403);
        die('<h2>Access Denied.</h2>');
    }
    return $_SESSION;
}

// ─── CSRF helpers ────────────────────────────────────────────
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function verifyCsrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

// ─── Misc helpers ────────────────────────────────────────────
function money(float $amount): string {
    return APP_CURRENCY . number_format($amount, 2);
}
function generateRef(): string {
    return strtoupper(bin2hex(random_bytes(8)));
}
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return "{$diff}s ago";
    if ($diff < 3600)  return round($diff/60)   . "m ago";
    if ($diff < 86400) return round($diff/3600)  . "h ago";
    return round($diff/86400) . "d ago";
}

// ================================================================
// PAYMONGO CONFIGURATION
// ================================================================
// Get your keys from: https://dashboard.paymongo.com/developers
// Use TEST keys while developing — no real money moves
// Switch to LIVE keys when ready for production
// ----------------------------------------------------------------
define('PAYMONGO_SECRET_KEY',    'sk_test_REPLACE_WITH_YOUR_KEY');
define('PAYMONGO_PUBLIC_KEY',    'pk_test_REPLACE_WITH_YOUR_KEY');
define('PAYMONGO_WEBHOOK_SECRET','whsec_REPLACE_WITH_WEBHOOK_SECRET');

// Your public HTTPS URL — needed for webhooks
// Use ngrok for local: https://xxxx.ngrok-free.app
// Use your InfinityFree domain for live: https://edupay.page.gd
define('APP_URL', 'https://edupay.page.gd');

// Top-up limits
define('TOPUP_MIN',  50.00);
define('TOPUP_MAX',  5000.00);

// ── PayMongo API helper ──────────────────────────────────────
function paymongoRequest(string $method, string $endpoint, array $data = []): array {
    $ch = curl_init('https://api.paymongo.com/v1' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => ['attributes' => $data]]));
    }
    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (!$decoded) throw new Exception("PayMongo API returned invalid JSON (HTTP $httpCode)");
    if (isset($decoded['errors'])) {
        $msg = $decoded['errors'][0]['detail'] ?? 'PayMongo API error';
        throw new Exception($msg);
    }
    return $decoded;
}
