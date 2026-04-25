<?php
// ============================================
// UNISTOCK - Get Users for Compose (AJAX)
// ============================================
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo '[]'; exit; }

$q  = trim($_GET['q'] ?? '');
$db = getDB();
$me = (int)$_SESSION['user_id'];

if (strlen($q) >= 1) {
    $stmt = $db->prepare("
        SELECT id, full_name, role, department
        FROM users
        WHERE id != ? AND is_active = 1
          AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)
        ORDER BY full_name
        LIMIT 10
    ");
    $like = '%' . $q . '%';
    $stmt->execute([$me, $like, $like, $like]);
} else {
    $stmt = $db->prepare("
        SELECT id, full_name, role, department
        FROM users
        WHERE id != ? AND is_active = 1
        ORDER BY full_name
        LIMIT 20
    ");
    $stmt->execute([$me]);
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
