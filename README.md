# 📱 EduPay — School E-Wallet System

A mobile-first, QR-code-based school payment system built with PHP 8+ and MySQL.
Students scan a merchant's QR code to pay instantly. Funds transfer atomically via MySQL transactions — no money ever disappears.

---

## 🗂 Project Structure

```
eWallet/
├── schema.sql                  ← Run this first (database setup + seed data)
├── login.php                   ← Unified login for all roles
├── logout.php
│
├── includes/
│   ├── config.php              ← DB connection, constants, helpers
│   ├── header.php              ← Shared navbar partial
│   └── footer.php              ← Shared script loader partial
│
├── assets/
│   └── css/app.css             ← Full custom stylesheet (no Bootstrap overrides needed)
│
├── student/
│   ├── dashboard.php           ← Balance widget + recent transactions
│   ├── scan.php                ← html5-qrcode camera scanner
│   ├── confirm_payment.php     ← Payment confirmation + atomic SQL transaction
│   └── history.php             ← Paginated transaction history
│
├── merchant/
│   ├── dashboard.php           ← Revenue overview + recent payments
│   ├── generate_qr.php         ← Amount entry → secure QR generation
│   └── history.php             ← Sales history table
│
└── admin/
    ├── dashboard.php           ← System stats overview
    ├── topup.php               ← Search student → add balance
    ├── users.php               ← User management table
    └── transactions.php        ← Full audit log
```

---

## ⚙️ Setup Instructions

### 1. Requirements
- PHP 8.0+ with PDO and PDO_MySQL extensions
- MySQL 5.7+ or MariaDB 10.3+
- A web server: Apache (with `mod_rewrite`) or Nginx
- HTTPS in production (required for camera access on mobile)

### 2. Database Setup
```sql
-- In your MySQL client or phpMyAdmin:
SOURCE /path/to/eWallet/schema.sql;
```
Or via CLI:
```bash
mysql -u root -p < schema.sql
```

### 3. Configure Database Credentials
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'school_ewallet');

// CHANGE THIS in production!
define('QR_SECRET_KEY', 'your-unique-secret-key-here');
```

### 4. Web Server Configuration

**Apache** — place in project root as `.htaccess`:
```apache
RewriteEngine On
RewriteRule ^$ /login.php [L,R=302]
```

**Nginx** — add to server block:
```nginx
root /var/www/eWallet;
index login.php;
location / {
    try_files $uri $uri/ /login.php;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 5. Permissions
```bash
chmod 755 /var/www/eWallet
chmod 644 /var/www/eWallet/includes/config.php
```

---

## 🔐 Demo Accounts

All demo accounts use password: `password123`

| Role     | Login                  | Avatar |
|----------|------------------------|--------|
| Admin    | admin@school.edu       | 🛡️     |
| Merchant | canteen@school.edu     | 🍱     |
| Student  | STU-2024-001 or zeke@student.edu | ⚡ |
| Student  | STU-2024-002           | 🌸     |
| Student  | STU-2024-003           | 🎯     |

---

## 💡 How the Payment Flow Works

```
[Merchant]                              [Student]
    │                                       │
    │  1. Enter amount + description        │
    │  2. Token stored in qr_tokens table   │
    │  3. QR code rendered on screen        │
    │                                       │
    │         ←── Student scans QR ──────── │
    │                                       │
    │  4. Student's phone reads JSON token  │
    │  5. Redirect to confirm_payment.php   │
    │  6. Token validated (not expired,     │
    │     not used, merchant exists)        │
    │                                       │
    │  ── MySQL TRANSACTION begins ─────────│
    │  7a. Deduct amount from student       │
    │  7b. Add amount to merchant           │
    │  7c. Insert into transactions table   │
    │  7d. Mark QR token as used=1          │
    │  ── COMMIT (or ROLLBACK on error) ────│
    │                                       │
    │  8. Receipt shown to student          │
    │  9. Reference code logged             │
```

---

## 🔒 Security Features

### QR Code Security
- **Expiration**: Each QR token expires after **120 seconds** (configurable via `QR_TTL_SECONDS`)
- **Single-use**: `used` flag in `qr_tokens` prevents replay attacks
- **Server-side amount**: Amount is read from DB (not URL), preventing tampering
- **HMAC signature**: Partial HMAC embedded in QR JSON for tamper detection

### Application Security
- **CSRF tokens**: All POST forms are CSRF-protected via `csrfToken()` / `verifyCsrf()`
- **Parameterized queries**: All DB queries use PDO prepared statements (no SQL injection)
- **Role-based access**: `requireLogin('role')` enforces access per page
- **Atomic transactions**: MySQL `BEGIN/COMMIT/ROLLBACK` ensures money integrity
- **Race condition guard**: `balance >= amount` check inside UPDATE (not just SELECT)
- **Password hashing**: bcrypt via `password_hash()` / `password_verify()`

### Race Condition Prevention
```sql
-- This single UPDATE prevents double-spend even under concurrent requests:
UPDATE wallets SET balance = balance - ?
WHERE user_id = ? AND balance >= ?
-- rowCount() === 0 means insufficient funds → ROLLBACK
```

---

## 🗄️ Database Schema

### `users`
| Column     | Type         | Notes                          |
|------------|--------------|--------------------------------|
| id         | INT PK       | Auto-increment                 |
| name       | VARCHAR(120) |                                |
| student_id | VARCHAR(30)  | Unique, NULL for non-students  |
| role       | ENUM         | student / merchant / admin     |
| email      | VARCHAR(150) | Unique                         |
| password   | VARCHAR(255) | bcrypt hash                    |
| avatar     | VARCHAR(10)  | Emoji for UI display           |

### `wallets`
| Column    | Type          | Notes                    |
|-----------|---------------|--------------------------|
| user_id   | INT FK        | One wallet per user      |
| balance   | DECIMAL(12,2) | Never goes below 0       |
| updated_at| DATETIME      | Auto-updated             |

### `transactions`
| Column      | Type          | Notes                      |
|-------------|---------------|----------------------------|
| id          | INT PK        |                            |
| sender_id   | INT FK        | The payer                  |
| receiver_id | INT FK        | The payee                  |
| amount      | DECIMAL(12,2) |                            |
| description | VARCHAR(255)  |                            |
| status      | ENUM          | success / failed / reversed|
| ref_code    | VARCHAR(32)   | Unique reference number    |
| created_at  | DATETIME      |                            |

### `qr_tokens`
| Column      | Type          | Notes                      |
|-------------|---------------|----------------------------|
| token       | VARCHAR(64) PK| Cryptographically random   |
| merchant_id | INT FK        |                            |
| amount      | DECIMAL(12,2) |                            |
| description | VARCHAR(255)  |                            |
| used        | TINYINT       | 0=active, 1=consumed       |
| expires_at  | DATETIME      | NOW() + QR_TTL_SECONDS     |

---

## 🧹 Maintenance

### Clean up expired QR tokens (run via cron)
```sql
DELETE FROM qr_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY;
```

### Add this to crontab (daily at 2 AM):
```
0 2 * * * mysql -u root school_ewallet -e "DELETE FROM qr_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY;"
```

---

## 🚀 Production Checklist

- [ ] Change `QR_SECRET_KEY` to a long random string
- [ ] Set `DB_PASS` to a strong password
- [ ] Enable HTTPS (required for camera API on mobile browsers)
- [ ] Set PHP `display_errors = Off` in `php.ini`
- [ ] Add `session.cookie_secure = 1` and `session.cookie_httponly = 1`
- [ ] Set up the cron job to clean expired QR tokens
- [ ] Consider adding rate limiting on `login.php` and `generate_qr.php`
- [ ] Back up the database regularly

---

## 📦 External Libraries Used

| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap | 5.3.3 | Responsive grid + utilities |
| Bootstrap Icons | 1.11.3 | UI icons |
| html5-qrcode | 2.3.8 | Camera QR scanning (student) |
| qrcodejs | 1.0.0 | QR code rendering (merchant) |
| Google Fonts | — | Syne + DM Sans typography |

All libraries are loaded via CDN — no npm/composer required.
