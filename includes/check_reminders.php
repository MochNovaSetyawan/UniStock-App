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

$pdo     = Database::getInstance();
$me      = Auth::id();
$appUrl  = APP_URL;
$created = 0;

// ── Helper: check if reminder was already sent in the last 23 hours ───────────
$reminderExists = static function (int $userId, int $transactionId, string $titleLike) use ($pdo): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = ?
          AND link LIKE ?
          AND title LIKE ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 23 HOUR)
    ");
    $stmt->execute([$userId, '%id=' . $transactionId, '%' . $titleLike . '%']);
    return (int)$stmt->fetchColumn() > 0;
};

// ── 1. Borrows due in 2 days (for the borrower) ──────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.id, t.code, t.expected_return, i.name AS item_name
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    WHERE t.status = 'active'
      AND t.requested_by = ?
      AND t.expected_return BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)
");
$stmt->execute([$me]);

foreach ($stmt->fetchAll() as $t) {
    $hoursLeft = round((strtotime($t['expected_return']) - time()) / 3600);
    if ($hoursLeft <= 24) {
        if ($reminderExists($me, $t['id'], 'Segera')) {
            continue;
        }
        $title = 'Segera Kembalikan Barang!';
        $msg   = 'Barang "' . $t['item_name'] . '" (#' . $t['code'] . ') harus dikembalikan dalam ' . $hoursLeft . ' jam.';
        $type  = 'danger';
    } else {
        if ($reminderExists($me, $t['id'], 'Pengingat Pengembalian')) {
            continue;
        }
        $title = 'Pengingat Pengembalian';
        $msg   = 'Barang "' . $t['item_name'] . '" (#' . $t['code'] . ') jatuh tempo ' . date('d M Y H:i', strtotime($t['expected_return'])) . '.';
        $type  = 'warning';
    }
    $link = $appUrl . '/modules/transactions/view.php?id=' . $t['id'];
    NotificationService::create($me, $title, $msg, $type, $link);
    $created++;
}

// ── 2. Overdue borrows (for the borrower) ────────────────────────────────────
$stmt2 = $pdo->prepare("
    SELECT t.id, t.code, t.expected_return, i.name AS item_name
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    WHERE t.status = 'active'
      AND t.requested_by = ?
      AND t.expected_return < NOW()
");
$stmt2->execute([$me]);

foreach ($stmt2->fetchAll() as $t) {
    if ($reminderExists($me, $t['id'], 'Terlambat Dikembalikan')) {
        continue;
    }
    $days  = max(1, (int)floor((time() - strtotime($t['expected_return'])) / 86400));
    $title = 'Barang Terlambat Dikembalikan!';
    $msg   = 'Barang "' . $t['item_name'] . '" (#' . $t['code'] . ') sudah terlambat ' . $days . ' hari. Segera kembalikan!';
    $link  = $appUrl . '/modules/transactions/view.php?id=' . $t['id'];
    NotificationService::create($me, $title, $msg, 'danger', $link);
    $created++;
}

// ── 3. Admin: all overdue borrows ────────────────────────────────────────────
if (Auth::isAdmin()) {
    $stmt3 = $pdo->prepare("
        SELECT t.id, t.code, t.expected_return, t.borrower_name, i.name AS item_name
        FROM transactions t
        JOIN items i ON t.item_id = i.id
        WHERE t.status = 'active'
          AND t.expected_return < NOW()
        ORDER BY t.expected_return ASC
        LIMIT 15
    ");
    $stmt3->execute();
    foreach ($stmt3->fetchAll() as $t) {
        if ($reminderExists($me, $t['id'], '[Admin]')) {
            continue;
        }
        $days     = max(1, (int)floor((time() - strtotime($t['expected_return'])) / 86400));
        $borrower = $t['borrower_name'] ?: 'Peminjam';
        $title    = '[Admin] Barang Belum Dikembalikan';
        $msg      = $borrower . ' terlambat mengembalikan "' . $t['item_name'] . '" (' . $days . ' hari).';
        $link     = $appUrl . '/modules/transactions/view.php?id=' . $t['id'];
        NotificationService::create($me, $title, $msg, 'danger', $link);
        $created++;
    }
}

$notifs = NotificationService::getUnread(6);
foreach ($notifs as &$n) {
    $n['id'] = (int)$n['id'];
}
unset($n);

echo json_encode([
    'ok'            => true,
    'created'       => $created,
    'unread'        => NotificationService::countUnread(),
    'notifications' => $notifs,
]);
