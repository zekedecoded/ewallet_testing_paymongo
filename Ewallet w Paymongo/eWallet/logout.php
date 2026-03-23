<?php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ' . BASE_PATH . '/login.php');
exit;
