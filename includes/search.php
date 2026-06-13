<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo '[]';
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo '[]';
    exit;
}

$pdo     = Database::getInstance();
$results = [];

// Search items
$stmt = $pdo->prepare("
    SELECT i.id, i.code, i.name, c.name AS category, l.name AS location
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN locations l ON i.location_id = l.id
    WHERE (i.name LIKE ? OR i.code LIKE ? OR i.brand LIKE ?) AND i.status = 'active'
    LIMIT 6
");
$stmt->execute(["%$q%", "%$q%", "%$q%"]);
foreach ($stmt->fetchAll() as $item) {
    $results[] = [
        'code' => $item['code'],
        'name' => $item['name'],
        'meta' => ($item['category'] ?? '') . ($item['location'] ? ' · ' . $item['location'] : ''),
        'url'  => APP_URL . '/modules/inventory/view.php?id=' . $item['id'],
    ];
}

// Search transactions
$stmt = $pdo->prepare("
    SELECT t.code, i.name AS item_name, t.borrower_name
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    WHERE (t.code LIKE ? OR t.borrower_name LIKE ?)
    LIMIT 3
");
$stmt->execute(["%$q%", "%$q%"]);
foreach ($stmt->fetchAll() as $tx) {
    $results[] = [
        'code' => $tx['code'],
        'name' => 'Transaksi: ' . $tx['item_name'],
        'meta' => 'Peminjam: ' . $tx['borrower_name'],
        'url'  => APP_URL . '/modules/transactions/index.php?search=' . urlencode($q),
    ];
}

echo json_encode($results);
