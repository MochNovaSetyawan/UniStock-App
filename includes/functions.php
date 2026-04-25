<?php
// ============================================
// UNISTOCK - Helper Functions
// ============================================

function generateCode($prefix, $table, $column = 'code') {
    $db = getDB();
    $year = date('Y');
    $month = date('m');
    $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` LIKE ?");
    $stmt->execute(["{$prefix}-{$year}{$month}%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return "{$prefix}-{$year}{$month}" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function formatDate($date, $format = 'd M Y') {
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '-';
    return date($format, strtotime($date));
}

function formatDateTime($date) {
    return formatDate($date, 'd M Y H:i');
}

function formatRupiah($amount) {
    if ($amount === null || $amount === '') return '-';
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function conditionBadge($condition) {
    $badges = [
        'good'    => ['class' => 'badge-success', 'label' => 'Baik'],
        'fair'    => ['class' => 'badge-warning', 'label' => 'Cukup Baik'],
        'poor'    => ['class' => 'badge-danger',  'label' => 'Kurang Baik'],
        'damaged' => ['class' => 'badge-danger',  'label' => 'Rusak'],
        'lost'    => ['class' => 'badge-dark',    'label' => 'Hilang'],
    ];
    $b = $badges[$condition] ?? ['class' => 'badge-secondary', 'label' => ucfirst($condition)];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function statusBadge($status) {
    $badges = [
        'active'      => ['class' => 'badge-success',   'label' => 'Aktif'],
        'inactive'    => ['class' => 'badge-secondary', 'label' => 'Tidak Aktif'],
        'disposed'    => ['class' => 'badge-dark',      'label' => 'Dibuang'],
        'pending'     => ['class' => 'badge-warning',   'label' => 'Menunggu'],
        'approved'    => ['class' => 'badge-info',      'label' => 'Disetujui'],
        'returned'    => ['class' => 'badge-success',   'label' => 'Dikembalikan'],
        'overdue'     => ['class' => 'badge-danger',    'label' => 'Terlambat'],
        'rejected'    => ['class' => 'badge-danger',    'label' => 'Ditolak'],
        'cancelled'   => ['class' => 'badge-secondary', 'label' => 'Dibatalkan'],
        'in_progress' => ['class' => 'badge-info',      'label' => 'Diproses'],
        'completed'   => ['class' => 'badge-success',   'label' => 'Selesai'],
        'good'        => ['class' => 'badge-success',   'label' => 'Baik'],
    ];
    $b = $badges[$status] ?? ['class' => 'badge-secondary', 'label' => ucfirst($status)];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function unitStatusBadge($status) {
    $badges = [
        'available'   => ['badge-success',   'Tersedia'],
        'reserved'    => ['badge-warning',   'Direservasi'],
        'borrowed'    => ['badge-info',      'Dipinjam'],
        'maintenance' => ['badge-warning',   'Maintenance'],
        'damaged'     => ['badge-danger',    'Rusak'],
        'disposed'    => ['badge-secondary', 'Dibuang'],
        'lost'        => ['badge-warning',   'Hilang'],
    ];
    [$class, $label] = $badges[$status] ?? ['badge-secondary', ucfirst($status)];
    return "<span class=\"badge {$class}\">{$label}</span>";
}

function roleBadge($role) {
    $badges = [
        'superadmin' => ['class' => 'badge-danger',  'label' => 'Super Admin'],
        'admin'      => ['class' => 'badge-info',    'label' => 'Admin'],
        'worker'     => ['class' => 'badge-success', 'label' => 'Pekerja'],
    ];
    $b = $badges[$role] ?? ['class' => 'badge-secondary', 'label' => ucfirst($role)];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function priorityBadge($priority) {
    $badges = [
        'low'      => ['class' => 'badge-secondary', 'label' => 'Rendah'],
        'medium'   => ['class' => 'badge-warning',   'label' => 'Sedang'],
        'high'     => ['class' => 'badge-danger',    'label' => 'Tinggi'],
        'critical' => ['class' => 'badge-dark',      'label' => 'Kritis'],
    ];
    $b = $badges[$priority] ?? ['class' => 'badge-secondary', 'label' => ucfirst($priority)];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function syncItemAvailability($db, $itemId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM item_units WHERE item_id = ? AND status = 'available'");
    $stmt->execute([$itemId]);
    $available = (int)$stmt->fetchColumn();
    $db->prepare("UPDATE items SET quantity_available = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$available, $itemId]);
}

/**
 * Generate item_units from (existing_max + 1) up to $qty.
 * Uses category code prefix so full_code always matches item code + category.
 */
function generateMissingUnits($db, $itemId, $itemCode, $catId, $qty, $condition, $locationId) {
    $catCode = '';
    if ($catId) {
        $cs = $db->prepare("SELECT code FROM categories WHERE id = ?");
        $cs->execute([$catId]);
        $row = $cs->fetch();
        $catCode = $row ? strtoupper($row['code']) : '';
    }
    $prefix = $catCode ? "{$catCode}-{$itemCode}" : $itemCode;

    $maxStmt = $db->prepare("SELECT MAX(unit_number) FROM item_units WHERE item_id = ?");
    $maxStmt->execute([$itemId]);
    $existing = (int)$maxStmt->fetchColumn();

    if ($existing >= $qty) return 0;

    $ins = $db->prepare("INSERT IGNORE INTO item_units
        (item_id, unit_number, unit_code, full_code, status, `condition`, location_id)
        VALUES (?,?,?,?,?,?,?)");

    $created = 0;
    for ($n = $existing + 1; $n <= $qty; $n++) {
        $unitCode = 'U' . str_pad($n, 3, '0', STR_PAD_LEFT);
        $ins->execute([$itemId, $n, $unitCode, "{$prefix}-{$unitCode}", 'available', $condition ?: 'good', $locationId]);
        $created++;
    }
    return $created;
}

/**
 * Rebuild full_code for ALL units of an item.
 * Call this whenever item code or category changes.
 */
function rebuildUnitFullCodes($db, $itemId, $itemCode, $catId) {
    $catCode = '';
    if ($catId) {
        $cs = $db->prepare("SELECT code FROM categories WHERE id = ?");
        $cs->execute([$catId]);
        $row = $cs->fetch();
        $catCode = $row ? strtoupper($row['code']) : '';
    }
    $prefix = $catCode ? "{$catCode}-{$itemCode}" : $itemCode;

    $units = $db->prepare("SELECT id, unit_code FROM item_units WHERE item_id = ?");
    $units->execute([$itemId]);
    $upd = $db->prepare("UPDATE item_units SET full_code = ? WHERE id = ?");
    foreach ($units->fetchAll() as $u) {
        $upd->execute(["{$prefix}-{$u['unit_code']}", $u['id']]);
    }
}

function sanitize($value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function uploadImage($file, $subfolder = 'items') {
    $uploadDir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return ['error' => 'Format file tidak didukung'];
    if ($file['size'] > 5 * 1024 * 1024) return ['error' => 'Ukuran file maksimal 5MB'];

    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $subfolder . '/' . $filename];
    }
    return ['error' => 'Gagal mengupload file'];
}

function paginate($total, $perPage, $currentPage, $url) {
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1) return '';

    $html = '<div class="pagination">';
    $separator = strpos($url, '?') !== false ? '&' : '?';

    if ($currentPage > 1) {
        $html .= "<a href=\"{$url}{$separator}page=" . ($currentPage - 1) . "\" class=\"page-btn\"><i data-feather=\"chevron-left\"></i></a>";
    }

    for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html .= "<a href=\"{$url}{$separator}page={$i}\" class=\"page-btn {$active}\">{$i}</a>";
    }

    if ($currentPage < $totalPages) {
        $html .= "<a href=\"{$url}{$separator}page=" . ($currentPage + 1) . "\" class=\"page-btn\"><i data-feather=\"chevron-right\"></i></a>";
    }

    $html .= '</div>';
    return $html;
}

function getDashboardStats() {
    $db = getDB();
    $stats = [];

    $stats['total_items']     = $db->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn();
    $stats['total_categories'] = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $stats['total_locations']  = $db->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    $stats['total_users']      = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $stats['active_borrows']      = $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active'")->fetchColumn();
    $stats['pending_borrows']     = $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='pending'")->fetchColumn();
    $stats['overdue_borrows']     = $db->query("SELECT COUNT(*) FROM transactions WHERE type='borrow' AND status='active' AND expected_return < NOW()")->fetchColumn();
    $stats['pending_maintenance'] = $db->query("SELECT COUNT(*) FROM maintenance m JOIN items i ON m.item_id = i.id WHERE m.status IN ('pending','in_progress')")->fetchColumn();
    $stats['low_stock_items']     = $db->query("SELECT COUNT(*) FROM items WHERE quantity_available <= min_stock AND status='active'")->fetchColumn();

    return $stats;
}

function getRecentTransactions($limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, i.name as item_name, i.code as item_code,
               u.full_name as requested_by_name
        FROM transactions t
        JOIN items i ON t.item_id = i.id
        LEFT JOIN users u ON t.requested_by = u.id
        ORDER BY t.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function insertNotification($userId, $title, $message, $type = 'info', $link = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, is_read)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        return $stmt->execute([$userId, $title, $message, $type, $link]);
    } catch (Exception $e) {
        return false;
    }
}

function countUnreadMessages() {
    if (!isLoggedIn()) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getItemsByCategory() {
    $db = getDB();
    return $db->query("
        SELECT c.name, c.color, COUNT(i.id) as total
        FROM categories c
        LEFT JOIN items i ON c.id = i.category_id AND i.status = 'active'
        GROUP BY c.id
        ORDER BY total DESC
    ")->fetchAll();
}
