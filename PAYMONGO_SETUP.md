# PayMongo Setup Guide

## Step 1 — Create PayMongo Account
1. Go to https://paymongo.com → Sign up free
2. Go to Developers → API Keys
3. Copy your Secret Key and Public Key (use TEST keys first)

## Step 2 — Update includes/config.php
```php
define('PAYMONGO_SECRET_KEY',    'sk_test_xxxxxxxxxxxx');
define('PAYMONGO_PUBLIC_KEY',    'pk_test_xxxxxxxxxxxx');
define('PAYMONGO_WEBHOOK_SECRET','whsec_xxxxxxxxxxxx');
define('APP_URL', 'https://edupay.page.gd');
```

## Step 3 — Run the new DB table
In phpMyAdmin SQL tab:
```sql
USE school_ewallet;
CREATE TABLE IF NOT EXISTS topup_requests (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    paymongo_link_id    VARCHAR(100) UNIQUE,
    paymongo_payment_id VARCHAR(100),
    checkout_url        TEXT,
    status              ENUM('pending','paid','failed','expired') DEFAULT 'pending',
    ref_code            VARCHAR(32) UNIQUE,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at             DATETIME,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    CONSTRAINT fk_topup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

## Step 4 — Register Webhook in PayMongo Dashboard
1. PayMongo Dashboard → Developers → Webhooks → Add Webhook
2. URL: https://edupay.page.gd/webhook_paymongo.php
3. Events: payment.paid
4. Copy the Webhook Secret → paste into config.php

## Step 5 — Test
1. Login as student → Load Wallet → enter ₱100
2. Use test card: 4343434343434343 (any expiry, any CVV)
3. Wallet should update automatically within seconds

## Going Live
1. Complete PayMongo KYC (school documents)
2. Switch sk_test_ → sk_live_ in config.php
3. Register new webhook with live domain
