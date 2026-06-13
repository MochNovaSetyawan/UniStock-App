<?php
declare(strict_types=1);
// AJAX: return units eligible for maintenance (available or damaged) for a given item_id
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

Auth::requireLogin();

header('Content-Type: application/json');

$itemId = (int)($_GET['item_id'] ?? 0);
if (!$itemId) { echo json_encode([]); exit; }

$pdo = Database::getInstance();

if (Auth::hasRole('worker')) {
    // Worker: hanya unit yang sedang aktif dipinjam olehnya
    $stmt = $pdo->prepare("
        SELECT iu.id, iu.full_code, iu.unit_code, iu.status, iu.`condition`,
               iu.serial_number, l.name as loc_name
        FROM item_units iu
        JOIN transaction_units tu ON tu.unit_id = iu.id
        JOIN transactions t ON t.id = tu.transaction_id
        LEFT JOIN locations l ON iu.location_id = l.id
        WHERE iu.item_id = ? AND t.requested_by = ? AND t.status = 'active' AND t.type = 'borrow'
        ORDER BY iu.unit_number ASC
    ");
    $stmt->execute([$itemId, Auth::id()]);
} else {
    $stmt = $pdo->prepare("
        SELECT iu.id, iu.full_code, iu.unit_code, iu.status, iu.`condition`,
               iu.serial_number, l.name as loc_name
        FROM item_units iu
        LEFT JOIN locations l ON iu.location_id = l.id
        WHERE iu.item_id = ? AND iu.status IN ('available', 'damaged')
        ORDER BY iu.unit_number ASC
    ");
    $stmt->execute([$itemId]);
}

echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
