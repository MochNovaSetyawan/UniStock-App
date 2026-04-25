<?php
// ============================================
// UNISTOCK - Auto Code Generator (AJAX endpoint)
// GET params: category_id | prefix | table
// Returns: next available code string
// ============================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$db     = getDB();
$table  = $_GET['table'] ?? 'items';
$catId  = (int)($_GET['category_id'] ?? 0);

// Resolve prefix
if ($catId) {
    $stmt = $db->prepare("SELECT code FROM categories WHERE id = ?");
    $stmt->execute([$catId]);
    $row    = $stmt->fetch();
    $prefix = $row ? strtoupper($row['code']) : 'ITEM';
} else {
    $raw    = strtoupper(trim($_GET['prefix'] ?? 'ITEM'));
    $prefix = preg_replace('/[^A-Z0-9\-]/', '', $raw) ?: 'ITEM';
}

// Whitelist allowed tables
$allowedTables = ['items', 'transactions', 'maintenance'];
if (!in_array($table, $allowedTables, true)) {
    $table = 'items';
}
$col = 'code';

// Find the highest sequential number for this prefix
$stmt = $db->prepare("SELECT `{$col}` FROM `{$table}` WHERE `{$col}` LIKE ?");
$stmt->execute(["{$prefix}-%"]);
$codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$max = 0;
$pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/i';
foreach ($codes as $c) {
    if (preg_match($pattern, $c, $m)) {
        $max = max($max, (int)$m[1]);
    }
}

$next = str_pad($max + 1, 3, '0', STR_PAD_LEFT);
echo "{$prefix}-{$next}";
