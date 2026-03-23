<?php
require_once __DIR__ . '/../includes/config.php';
$session = requireLogin('student');
$db = getDB();
header('Content-Type: application/json');
$ref  = trim($_GET['ref'] ?? '');
if (!$ref) { echo json_encode(['status'=>'error']); exit; }
$stmt = $db->prepare("SELECT status FROM topup_requests WHERE ref_code = ? AND user_id = ?");
$stmt->execute([$ref, $session['user_id']]);
$row = $stmt->fetch();
echo json_encode(['status' => $row['status'] ?? 'not_found']);
