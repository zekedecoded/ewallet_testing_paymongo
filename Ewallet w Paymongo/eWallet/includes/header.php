<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_PATH')) require_once __DIR__ . '/config.php';
$pageTitle = $pageTitle ?? 'GJC EduPay';
$role      = $_SESSION['role'] ?? 'guest';
$bp        = BASE_PATH;
$roleName  = match($role) { 'student' => 'Student', 'merchant' => 'Merchant', 'admin' => 'Admin', default => '' };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — GJC EduPay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= $bp ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="<?= $bodyClass ?? '' ?>">

<!-- Yellow navbar with green text — exact GJC match -->
<nav class="navbar navbar-expand-lg ep-navbar">
  <div class="container-fluid px-3">

    <a class="navbar-brand ep-brand" href="<?= $bp ?>/<?= $role ?>/dashboard.php">
      <div class="ep-logo-icon">GJC</div>
      <div>
        <div class="ep-logo-text">Edu<strong>Pay</strong></div>
        <span class="ep-logo-sub">General de Jesus College</span>
      </div>
    </a>

    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="ep-nav-right d-flex align-items-center gap-2 ms-auto">
      <?php if ($roleName): ?>
        <span class="ep-nav-role d-none d-sm-inline"><?= $roleName ?></span>
      <?php endif; ?>
      <span class="ep-nav-avatar"><?= $_SESSION['avatar'] ?? '👤' ?></span>
      <span class="ep-nav-name d-none d-md-inline"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
      <a href="<?= $bp ?>/logout.php" class="ep-nav-logout" title="Sign out">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
    <?php endif; ?>

  </div>
</nav>
