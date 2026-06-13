<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\NotificationService;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = Database::getInstance();
$me  = Auth::id();
$id  = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
        ->execute([$id, $me]);
} else {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
        ->execute([$me]);
}

echo json_encode(['ok' => true, 'unread' => NotificationService::countUnread()]);
