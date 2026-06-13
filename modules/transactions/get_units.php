<?php
declare(strict_types=1);
// AJAX: return available item_units for a given item_id as JSON
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

Auth::requireLogin();

header('Content-Type: application/json');

$itemId = (int)($_GET['item_id'] ?? 0);
if (!$itemId) { echo json_encode([]); exit; }

$pdo = Database::getInstance();

$stmt = $pdo->prepare("
    SELECT iu.id, iu.full_code, iu.unit_code, iu.status, iu.condition,
           iu.serial_number, iu.notes, l.name as loc_name
    FROM item_units iu
    LEFT JOIN locations l ON iu.location_id = l.id
    WHERE iu.item_id = ? AND iu.status = 'available'
    ORDER BY iu.unit_number ASC
");
$stmt->execute([$itemId]);
$units = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo json_encode($units);
