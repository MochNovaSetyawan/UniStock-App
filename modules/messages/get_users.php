<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo '[]';
    exit;
}

$q   = trim($_GET['q'] ?? '');
$me  = Auth::id();
$pdo = Database::getInstance();

if (strlen($q) >= 1) {
    $stmt = $pdo->prepare("
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
    $stmt = $pdo->prepare("
        SELECT id, full_name, role, department
        FROM users
        WHERE id != ? AND is_active = 1
        ORDER BY full_name
        LIMIT 20
    ");
    $stmt->execute([$me]);
}

echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
