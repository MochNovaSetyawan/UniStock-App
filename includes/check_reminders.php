<?php
// ============================================
// UNISTOCK - Reminder Checker (AJAX endpoint)
// Dipanggil otomatis dari footer setiap 60 detik
// ============================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['ok' => false]); exit; }

$db     = getDB();
$me     = (int)$_SESSION['user_id'];
$appUrl = APP_URL;
$created = 0;

// ── Helper: cek apakah notif sudah pernah dikirim dalam 23 jam terakhir ──────
function reminderExists($db, $userId, $transactionId, $titleLike) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = ?
          AND link LIKE ?
          AND title LIKE ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 23 HOUR)
    ");
    $stmt->execute([$userId, '%id=' . $transactionId, '%' . $titleLike . '%']);
    return (int)$stmt->fetchColumn() > 0;
}

// ── 1. Peminjaman jatuh tempo dalam 2 hari (untuk peminjam sendiri) ───────────
$stmt = $db->prepare("
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
        if (reminderExists($db, $me, $t['id'], 'Segera')) continue;
        $title = 'Segera Kembalikan Barang!';
        $msg   = 'Barang "' . $t['item_name'] . '" (#' . $t['code'] . ') harus dikembalikan dalam ' . $hoursLeft . ' jam.';
        $type  = 'danger';
    } else {
        if (reminderExists($db, $me, $t['id'], 'Pengingat Pengembalian')) continue;
        $title = 'Pengingat Pengembalian';
        $msg   = 'Barang "' . $t['item_name'] . '" (#' . $t['code'] . ') jatuh tempo ' . date('d M Y H:i', strtotime($t['expected_return'])) . '.';
        $type  = 'warning';
    }
    $link = $appUrl . '/modules/transactions/view.php?id=' . $t['id'];
    insertNotification($me, $title, $msg, $type, $link);
    $created++;
}

// ── 2. Peminjaman yang sudah terlambat (untuk peminjam sendiri) ───────────────
$stmt2 = $db->prepare("
    SELECT t.id, t.code, t.expected_return, i.name AS item_name
    FROM transactions t
    JOIN items i ON t.item_id = i.id
    WHERE t.status = 'active'
      AND t.requested_by = ?
      AND t.expected_return < NOW()
");
$stmt2->execute([$me]);
foreach ($stmt2->fetchAll() as $t) {
    if (reminderExists($db, $me, $t['id'], 'Terlambat Dikembalikan')) continue;
    $days  = max(1, (int)floor((time() - strtotime($t['expected_return'])) / 86400));
    $title = 'Barang Terlambat Dikembalikan!';
    $msg   = 'Barang "' . $t['item_name'] . '" (#' . $t['code'] . ') sudah terlambat ' . $days . ' hari. Segera kembalikan!';
    $link  = $appUrl . '/modules/transactions/view.php?id=' . $t['id'];
    insertNotification($me, $title, $msg, 'danger', $link);
    $created++;
}

// ── 3. Admin: semua peminjaman terlambat yang perlu ditindaklanjuti ───────────
if (isAdmin()) {
    $stmt3 = $db->prepare("
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
        if (reminderExists($db, $me, $t['id'], '[Admin]')) continue;
        $days     = max(1, (int)floor((time() - strtotime($t['expected_return'])) / 86400));
        $borrower = $t['borrower_name'] ?: 'Peminjam';
        $title    = '[Admin] Barang Belum Dikembalikan';
        $msg      = $borrower . ' terlambat mengembalikan "' . $t['item_name'] . '" (' . $days . ' hari).';
        $link     = $appUrl . '/modules/transactions/view.php?id=' . $t['id'];
        insertNotification($me, $title, $msg, 'danger', $link);
        $created++;
    }
}

$notifs = getUnreadNotifications(6);
foreach ($notifs as &$n) { $n['id'] = (int)$n['id']; }
unset($n);
echo json_encode(['ok' => true, 'created' => $created, 'unread' => countUnreadNotifications(), 'notifications' => $notifs]);
