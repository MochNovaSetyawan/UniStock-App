<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$item = null;
$isEdit = false;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) { flashMessage('error', 'Barang tidak ditemukan.'); header('Location: index.php'); exit; }
    $isEdit = true;
}

$pageTitle = $isEdit ? 'Edit Barang' : 'Tambah Barang';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'code'          => strtoupper(trim($_POST['code'] ?? '')),
        'name'          => trim($_POST['name'] ?? ''),
        'brand'         => trim($_POST['brand'] ?? ''),
        'model'         => trim($_POST['model'] ?? ''),
        'serial_number' => trim($_POST['serial_number'] ?? ''),
        'category_id'   => (int)($_POST['category_id'] ?? 0) ?: null,
        'location_id'   => (int)($_POST['location_id'] ?? 0) ?: null,
        'description'   => trim($_POST['description'] ?? ''),
        'quantity'      => max(1, (int)($_POST['quantity'] ?? 1)),
        'unit'          => trim($_POST['unit'] ?? 'unit'),
        'condition'     => $_POST['condition'] ?? 'good',
        'status'        => $_POST['status'] ?? 'active',
        'purchase_date' => $_POST['purchase_date'] ?: null,
        'purchase_price'=> $_POST['purchase_price'] !== '' ? (float)str_replace(['.', ','], ['', '.'], $_POST['purchase_price']) : null,
        'supplier'      => trim($_POST['supplier'] ?? ''),
        'warranty_expiry'=> $_POST['warranty_expiry'] ?: null,
        'notes'         => trim($_POST['notes'] ?? ''),
        'min_stock'     => max(0, (int)($_POST['min_stock'] ?? 0)),
    ];

    // Validation
    if (!$data['code']) $errors[] = 'Kode barang wajib diisi.';
    if (!$data['name']) $errors[] = 'Nama barang wajib diisi.';

    // Check unique code
    $codeCheck = $db->prepare("SELECT id FROM items WHERE code = ? AND id != ?");
    $codeCheck->execute([$data['code'], $id]);
    if ($codeCheck->fetch()) $errors[] = 'Kode barang sudah digunakan.';

    // When editing: quantity cannot go below units that are actively in use
    if ($isEdit) {
        $activeStmt = $db->prepare("
            SELECT COUNT(*) FROM item_units
            WHERE item_id = ? AND status IN ('reserved','borrowed','maintenance')
        ");
        $activeStmt->execute([$id]);
        $activeCount = (int)$activeStmt->fetchColumn();
        if ($data['quantity'] < $activeCount) {
            $errors[] = "Jumlah tidak boleh kurang dari {$activeCount} karena ada unit yang sedang dipinjam atau dalam pemeliharaan.";
        }
    }

    // Handle image upload
    $imagePath = $item['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'items');
        if (isset($upload['error'])) {
            $errors[] = $upload['error'];
        } else {
            $imagePath = $upload['path'];
        }
    }

    if (empty($errors)) {
        $data['image'] = $imagePath;

        if ($isEdit) {
            $oldData  = $item;
            $codeChanged = $data['code'] !== $item['code'];
            $catChanged  = (int)$data['category_id'] !== (int)$item['category_id'];

            // Keep existing quantity_available as placeholder; syncItemAvailability will correct it
            $data['quantity_available'] = (int)$item['quantity_available'];
            $data['updated_by'] = $_SESSION['user_id'];

            $sql = "UPDATE items SET " . implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data))) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge(array_values($data), [$id]));

            // 1. If item code or category changed, rebuild all existing unit full_codes first
            if ($codeChanged || $catChanged) {
                rebuildUnitFullCodes($db, $id, $data['code'], $data['category_id']);
            }

            // 2. Generate new units if quantity increased (uses updated prefix)
            generateMissingUnits($db, $id, $data['code'], $data['category_id'], $data['quantity'], $data['condition'], $data['location_id']);

            // 3. Sync quantity_available from actual unit statuses
            syncItemAvailability($db, $id);

            auditLog('UPDATE', 'inventory', $id, 'Item updated: ' . $data['name'], $oldData, $data);
            flashMessage('success', 'Barang berhasil diperbarui.');
        } else {
            // Parse batch data
            $batches = [];
            if (!empty($_POST['unit_batches'])) {
                $decoded = json_decode($_POST['unit_batches'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $b) {
                        $bQty = max(1, (int)($b['qty'] ?? 0));
                        $batches[] = [
                            'qty'         => $bQty,
                            'price'       => isset($b['price']) && $b['price'] !== '' ? abs((float)$b['price']) : null,
                            'condition'   => in_array($b['condition'] ?? '', ['good','fair','poor']) ? $b['condition'] : 'good',
                            'location_id' => (int)($b['location_id'] ?? 0) ?: null,
                        ];
                    }
                }
            }

            if (!empty($batches)) {
                $data['quantity'] = array_sum(array_column($batches, 'qty'));
            }
            $data['quantity_available'] = $data['quantity'];
            $data['created_by']         = $_SESSION['user_id'];

            $sql  = "INSERT INTO items (" . implode(', ', array_map(fn($k) => "`$k`", array_keys($data))) . ") VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            $newId = $db->lastInsertId();

            if (!empty($batches)) {
                $offset = 0;
                foreach ($batches as $b) {
                    $targetTotal = $offset + $b['qty'];
                    generateMissingUnits($db, $newId, $data['code'], $data['category_id'], $targetTotal, $b['condition'], $b['location_id']);
                    if ($b['price'] !== null) {
                        $db->prepare("UPDATE item_units SET purchase_price = ? WHERE item_id = ? AND unit_number > ? AND unit_number <= ?")
                           ->execute([$b['price'], $newId, $offset, $targetTotal]);
                    }
                    $offset = $targetTotal;
                }
            } else {
                generateMissingUnits($db, $newId, $data['code'], $data['category_id'], $data['quantity'], $data['condition'], $data['location_id']);
            }
            syncItemAvailability($db, $newId);

            auditLog('CREATE', 'inventory', $newId, 'Item created: ' . $data['name']);
            $totalUnits = array_sum(array_column($batches, 'qty')) ?: $data['quantity'];
            flashMessage('success', 'Barang berhasil ditambahkan dengan ' . $totalUnits . ' unit.');
        }

        header('Location: index.php');
        exit;
    }
}


$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$locations  = $db->query("SELECT * FROM locations ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Inventaris</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <?= $isEdit ? 'Edit Barang' : 'Tambah Barang' ?>
    </div>
    <h2><?= $isEdit ? 'Edit Barang' : 'Tambah Barang Baru' ?></h2>
    <p><?= $isEdit ? 'Perbarui informasi barang: ' . sanitize($item['name']) : 'Tambahkan barang baru ke inventaris' ?></p>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="margin-bottom: 20px;">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div>
    <strong>Terdapat <?= count($errors) ?> kesalahan:</strong>
    <ul style="margin: 6px 0 0 16px;"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
  </div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <div class="grid-2" style="gap: 20px; align-items: start;">
    <div style="display: flex; flex-direction: column; gap: 20px;">

      <!-- Basic Info -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Informasi Dasar</div>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Kode Produk <span class="required">*</span></label>
              <div style="position:relative;">
                <input type="text" name="code" id="fieldCode" class="form-control" required
                       value="<?= sanitize($item['code'] ?? ($_POST['code'] ?? '')) ?>"
                       placeholder="LAP-001" style="text-transform:uppercase;font-family:var(--font-mono,monospace);padding-right:72px;"
                       oninput="onCodeInput()" <?= $isEdit ? '' : 'data-auto="1"' ?>>
                <?php if (!$isEdit): ?>
                <span id="codeAutoTag" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:0.68rem;color:var(--accent-light);background:var(--bg-elevated);padding:2px 6px;border-radius:4px;pointer-events:none;">AUTO</span>
                <?php endif; ?>
              </div>
              <span class="form-hint">Kode produk unik — kode unit dibuat otomatis dari ini</span>
            </div>
            <div class="form-group">
              <label class="form-label">Nama Barang <span class="required">*</span></label>
              <input type="text" name="name" class="form-control" required
                     value="<?= sanitize($item['name'] ?? ($_POST['name'] ?? '')) ?>"
                     placeholder="Laptop Dell Latitude 5520">
            </div>
            <div class="form-group">
              <label class="form-label">Merek / Brand</label>
              <input type="text" name="brand" class="form-control"
                     value="<?= sanitize($item['brand'] ?? ($_POST['brand'] ?? '')) ?>"
                     placeholder="Dell, HP, Samsung...">
            </div>
            <div class="form-group">
              <label class="form-label">Model</label>
              <input type="text" name="model" class="form-control"
                     value="<?= sanitize($item['model'] ?? ($_POST['model'] ?? '')) ?>"
                     placeholder="Latitude 5520">
            </div>
            <div class="form-group">
              <label class="form-label">Nomor Seri</label>
              <input type="text" name="serial_number" class="form-control"
                     value="<?= sanitize($item['serial_number'] ?? ($_POST['serial_number'] ?? '')) ?>"
                     placeholder="SN-XXXXXXXX">
            </div>
            <div class="form-group">
              <label class="form-label">Satuan</label>
              <select name="unit" class="form-control">
                <?php foreach (['unit'=>'Unit','pcs'=>'Pcs','set'=>'Set','buah'=>'Buah','lembar'=>'Lembar','rim'=>'Rim','botol'=>'Botol','liter'=>'Liter','kg'=>'Kg','meter'=>'Meter'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($item['unit'] ?? 'unit') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full">
              <label class="form-label">Deskripsi</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi lengkap barang..."><?= sanitize($item['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Classification -->
      <div class="card">
        <div class="card-header"><div class="card-title">Klasifikasi &amp; Lokasi</div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Kategori</label>
              <select name="category_id" id="fieldCategory" class="form-control" onchange="updateCodePreview()">
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($item['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Lokasi</label>
              <select name="location_id" class="form-control">
                <option value="">-- Pilih Lokasi --</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>" <?= ($item['location_id'] ?? 0) == $loc['id'] ? 'selected' : '' ?>><?= sanitize($loc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Kondisi</label>
              <select name="condition" class="form-control">
                <option value="good"    <?= ($item['condition'] ?? 'good') === 'good'    ? 'selected' : '' ?>>Baik</option>
                <option value="fair"    <?= ($item['condition'] ?? '') === 'fair'    ? 'selected' : '' ?>>Cukup Baik</option>
                <option value="poor"    <?= ($item['condition'] ?? '') === 'poor'    ? 'selected' : '' ?>>Kurang Baik</option>
                <option value="damaged" <?= ($item['condition'] ?? '') === 'damaged' ? 'selected' : '' ?>>Rusak</option>
                <option value="lost"    <?= ($item['condition'] ?? '') === 'lost'    ? 'selected' : '' ?>>Hilang</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <option value="active"   <?= ($item['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= ($item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
                <option value="disposed" <?= ($item['status'] ?? '') === 'disposed' ? 'selected' : '' ?>>Dibuang</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Stock -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><?= $isEdit ? 'Jumlah &amp; Stok' : 'Unit &amp; Stok' ?></div>
          <?php if (!$isEdit): ?><span id="batchTotalBadge" style="font-size:0.78rem;color:var(--text-muted);">0 unit</span><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($isEdit): ?>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Jumlah Total <span class="required">*</span></label>
              <input type="number" name="quantity" id="fieldQty" class="form-control" required min="1"
                     value="<?= (int)($item['quantity'] ?? 1) ?>" oninput="updateCodePreview()">
            </div>
            <div class="form-group">
              <label class="form-label">Batas Stok Minimum</label>
              <input type="number" name="min_stock" class="form-control" min="0"
                     value="<?= (int)($item['min_stock'] ?? 0) ?>">
              <span class="form-hint">Alert ketika stok di bawah nilai ini</span>
            </div>
          </div>
          <?php else: ?>
          <!-- Batch table untuk tambah barang baru -->
          <input type="hidden" name="unit_batches" id="unitBatchesJson">
          <input type="hidden" name="quantity" id="fieldQty" value="1">
          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;" id="batchTable">
              <thead>
                <tr style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">
                  <th style="padding:6px 8px;text-align:left;font-weight:600;">Jumlah</th>
                  <th style="padding:6px 8px;text-align:left;font-weight:600;">Harga Beli (Rp)</th>
                  <th style="padding:6px 8px;text-align:left;font-weight:600;">Kondisi</th>
                  <th style="padding:6px 8px;text-align:left;font-weight:600;">Lokasi</th>
                  <th style="padding:6px 8px;width:32px;"></th>
                </tr>
              </thead>
              <tbody id="batchBody"></tbody>
            </table>
          </div>
          <button type="button" onclick="addBatchRow()" class="btn btn-ghost btn-sm" style="margin-top:10px;width:100%;border:1px dashed var(--border);">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Batch
          </button>
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:0.82rem;color:var(--text-muted);">Total unit:</span>
            <span id="batchTotalText" style="font-size:1rem;font-weight:700;color:var(--accent-light);">0 unit</span>
          </div>
          <div class="form-group" style="margin-top:14px;">
            <label class="form-label">Batas Stok Minimum</label>
            <input type="number" name="min_stock" class="form-control" min="0" value="0">
            <span class="form-hint">Alert ketika stok di bawah nilai ini</span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px;">

      <!-- Unit Code Preview -->
      <div class="card" id="unitPreviewCard">
        <div class="card-header">
          <div class="card-title">Preview Kode Unit</div>
          <span id="unitPreviewCount" style="font-size:0.78rem;color:var(--text-muted);"></span>
        </div>
        <div class="card-body" style="padding-bottom:16px;">
          <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:10px;">Format: <code style="color:var(--accent-light);">{kode kategori}-{kode produk}-{kode unit}</code></div>
          <div id="unitPreviewBox" style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:14px 16px;font-family:monospace;font-size:0.82rem;line-height:2;min-height:60px;color:var(--text-secondary);">
            <span style="color:var(--text-disabled);">Isi Kode Produk dan pilih Kategori untuk melihat preview...</span>
          </div>
          <?php if ($isEdit): ?>
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <?php
            $existingUnits = $db->prepare("SELECT COUNT(*) FROM item_units WHERE item_id = ?");
            $existingUnits->execute([$id]);
            $unitTotal = $existingUnits->fetchColumn();
            ?>
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= $unitTotal ?> unit sudah ada</span>
            <a href="units.php?item_id=<?= $id ?>" class="btn btn-outline btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
              Kelola Unit
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($isEdit): ?>
      <?php
        $unitStats = $db->prepare("SELECT status, COUNT(*) as cnt FROM item_units WHERE item_id = ? GROUP BY status");
        $unitStats->execute([$id]);
        $uStats = ['available'=>0,'borrowed'=>0,'reserved'=>0,'maintenance'=>0,'damaged'=>0,'disposed'=>0,'lost'=>0];
        foreach ($unitStats->fetchAll() as $r) $uStats[$r['status']] = (int)$r['cnt'];
        $uTotal = array_sum($uStats);
        $uMax   = (int)$db->prepare("SELECT MAX(unit_number) FROM item_units WHERE item_id = ?")->execute([$id]) ? $db->query("SELECT MAX(unit_number) FROM item_units WHERE item_id = $id")->fetchColumn() : 0;
      ?>
      <!-- Tambah Unit Card (edit mode only) -->
      <div class="card" style="border:1px solid var(--accent-dim, rgba(99,102,241,0.3));">
        <div class="card-header" style="border-bottom:1px solid var(--accent-dim, rgba(99,102,241,0.2));">
          <div class="card-title" style="color:var(--accent-light);">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="vertical-align:-2px;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Unit
          </div>
        </div>
        <div class="card-body">
          <!-- Stats ringkas -->
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;text-align:center;">
            <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:10px;">
              <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);"><?= $uTotal ?></div>
              <div style="font-size:0.7rem;color:var(--text-muted);">Total Unit</div>
            </div>
            <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:10px;">
              <div style="font-size:1.1rem;font-weight:700;color:var(--success);"><?= $uStats['available'] ?></div>
              <div style="font-size:0.7rem;color:var(--text-muted);">Tersedia</div>
            </div>
            <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:10px;">
              <div style="font-size:1.1rem;font-weight:700;color:var(--accent-light);" id="addUnitPreview"><?= $uTotal ?></div>
              <div style="font-size:0.7rem;color:var(--text-muted);">Total Setelah</div>
            </div>
          </div>

          <form method="POST" action="unit_save.php" id="addUnitForm">
            <input type="hidden" name="action"    value="add_units">
            <input type="hidden" name="item_id"   value="<?= $id ?>">
            <input type="hidden" name="_redirect" value="form.php?id=<?= $id ?>">

            <div style="display:flex;flex-direction:column;gap:14px;">
              <div class="form-group">
                <label class="form-label">Jumlah Unit Ditambahkan <span class="required">*</span></label>
                <input type="number" name="add_qty" id="addQtyField" class="form-control"
                       min="1" max="999" value="1" required
                       oninput="document.getElementById('addUnitPreview').textContent = <?= $uTotal ?> + (parseInt(this.value)||0)">
                <span class="form-hint">Mulai dari nomor <?= $uTotal + 1 ?> — setelah disimpan</span>
              </div>

              <div class="form-group">
                <label class="form-label">Harga Beli Batch Ini (Rp)</label>
                <input type="number" name="purchase_price" class="form-control" min="0" step="1"
                       placeholder="Kosongkan jika sama dengan harga barang">
                <span class="form-hint">Harga barang saat ini: Rp <?= number_format($item['purchase_price'] ?? 0, 0, ',', '.') ?></span>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="form-group">
                  <label class="form-label">Kondisi</label>
                  <select name="condition" class="form-control">
                    <option value="good">Baik</option>
                    <option value="fair">Cukup Baik</option>
                    <option value="poor">Kurang Baik</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Lokasi</label>
                  <select name="location_id" class="form-control">
                    <option value="">— Ikut barang —</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $item['location_id'] == $loc['id'] ? 'selected' : '' ?>><?= sanitize($loc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Unit
              </button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Image -->
      <div class="card">
        <div class="card-header"><div class="card-title">Foto Barang</div></div>
        <div class="card-body" style="text-align: center;">
          <div id="imgPreview" style="width:100%;aspect-ratio:4/3;background:var(--bg-elevated);border-radius:var(--radius);overflow:hidden;display:flex;align-items:center;justify-content:center;margin-bottom:12px;border:2px dashed var(--border);">
            <?php if (!empty($item['image'])): ?>
            <img src="<?= UPLOAD_URL . sanitize($item['image']) ?>" style="width:100%;height:100%;object-fit:cover;" id="previewImg">
            <?php else: ?>
            <div id="noImg" style="color:var(--text-muted);text-align:center;padding:20px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:block;margin:0 auto 8px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
              Belum ada foto
            </div>
            <?php endif; ?>
          </div>
          <label class="btn btn-outline" style="cursor:pointer;width:100%;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Upload Foto
            <input type="file" name="image" accept="image/*" style="display:none;" onchange="previewImage(this)">
          </label>
          <p class="form-hint" style="margin-top: 8px;">JPG, PNG, WEBP. Maks 5MB</p>
        </div>
      </div>

      <!-- Purchase Info -->
      <div class="card">
        <div class="card-header"><div class="card-title">Info Pengadaan</div></div>
        <div class="card-body">
          <div style="display: flex; flex-direction: column; gap: 16px;">
            <div class="form-group">
              <label class="form-label">Tanggal Pembelian</label>
              <input type="date" name="purchase_date" class="form-control"
                     value="<?= $item['purchase_date'] ?? '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Harga Beli (Rp)</label>
              <input type="number" name="purchase_price" class="form-control" min="0"
                     value="<?= $item['purchase_price'] ?? '' ?>"
                     placeholder="0">
            </div>
            <div class="form-group">
              <label class="form-label">Supplier / Vendor</label>
              <input type="text" name="supplier" class="form-control"
                     value="<?= sanitize($item['supplier'] ?? '') ?>"
                     placeholder="Nama supplier...">
            </div>
            <div class="form-group">
              <label class="form-label">Garansi Hingga</label>
              <input type="date" name="warranty_expiry" class="form-control"
                     value="<?= $item['warranty_expiry'] ?? '' ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="card">
        <div class="card-header"><div class="card-title">Catatan</div></div>
        <div class="card-body">
          <textarea name="notes" class="form-control" rows="3" placeholder="Catatan tambahan..."><?= sanitize($item['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
    <a href="index.php" class="btn btn-outline">Batal</a>
    <button type="submit" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Barang' ?>
    </button>
  </div>
</form>


<script>
// ── Image preview ────────────────────────────────────────────
function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('imgPreview').innerHTML =
        `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// ── Category code map ─────────────────────────────────────────
const catCodes = <?= json_encode(
    array_column(
        $db->query("SELECT id, code FROM categories")->fetchAll(PDO::FETCH_ASSOC),
        'code', 'id'
    )
) ?>;

// ── Real-time code generation ─────────────────────────────────
<?php if (!$isEdit): ?>
let _codeGenTimer = null;
let _codeIsAuto   = true;

function onCodeInput() {
  const el  = document.getElementById('fieldCode');
  const tag = document.getElementById('codeAutoTag');
  _codeIsAuto = (el.value.trim() === '');
  if (tag) tag.style.display = _codeIsAuto ? '' : 'none';
  updateCodePreview();
}

function fetchAutoCode() {
  if (!_codeIsAuto) return;
  const catId = document.getElementById('fieldCategory')?.value || '';
  const url   = '<?= APP_URL ?>/includes/generate_code.php?' +
                (catId ? 'category_id=' + catId : 'prefix=ITEM');
  fetch(url).then(r => r.text()).then(code => {
    if (!_codeIsAuto) return;
    const el  = document.getElementById('fieldCode');
    const tag = document.getElementById('codeAutoTag');
    el.value  = code.trim().toUpperCase();
    if (tag) tag.style.display = '';
    updateCodePreview();
  });
}

document.getElementById('fieldCategory')?.addEventListener('change', () => {
  clearTimeout(_codeGenTimer);
  _codeGenTimer = setTimeout(fetchAutoCode, 150);
});

fetchAutoCode();
<?php endif; ?>

// ── Code preview ──────────────────────────────────────────────
function updateCodePreview() {
  const codeEl = document.getElementById('fieldCode');
  const catEl  = document.getElementById('fieldCategory');
  const box    = document.getElementById('unitPreviewBox');
  const cnt    = document.getElementById('unitPreviewCount');
  if (!codeEl || !box) return;

  const code    = codeEl.value.trim().toUpperCase();
  const catId   = catEl ? catEl.value : '';
  const catCode = catCodes[catId] || '';
  const prefix  = catCode ? `${catCode}-${code}` : code;

  <?php if ($isEdit): ?>
  const qty = Math.max(1, Math.min(parseInt(document.getElementById('fieldQty')?.value) || 1, 9999));
  <?php else: ?>
  const qty = Math.max(1, getBatchTotal());
  <?php endif; ?>

  if (!code) {
    box.innerHTML = '<span style="color:var(--text-disabled);">Isi Kode Produk dan pilih Kategori untuk melihat preview...</span>';
    if (cnt) cnt.textContent = '';
    return;
  }

  const u = n => `U${String(n).padStart(3,'0')}`;
  let html = '';
  const show = Math.min(qty, 5);
  for (let i = 1; i <= show; i++) {
    html += `<span style="color:var(--accent-light);">${prefix}-${u(i)}</span><br>`;
  }
  if (qty > 5) {
    html += `<span style="color:var(--text-disabled);">  &bull;&bull;&bull; (${qty - 5} lagi)</span><br>`;
    html += `<span style="color:var(--accent-light);">${prefix}-${u(qty)}</span>`;
  }
  box.innerHTML = html;
  if (cnt) cnt.textContent = qty + ' unit';
}

<?php if ($isEdit): ?>
document.getElementById('fieldQty')?.addEventListener('input', updateCodePreview);
<?php endif; ?>

// ── Batch table (hanya mode tambah baru) ─────────────────────
<?php if (!$isEdit): ?>
const locationOptions = <?= json_encode(array_map(fn($l) => ['id' => $l['id'], 'name' => $l['name']], $locations)) ?>;

let batchRowId = 0;

function addBatchRow(qty = 1, price = '', condition = 'good', locationId = '') {
  const id   = ++batchRowId;
  const locOpts = locationOptions.map(l =>
    `<option value="${l.id}" ${l.id == locationId ? 'selected' : ''}>${l.name}</option>`
  ).join('');

  const tr = document.createElement('tr');
  tr.id = 'brow-' + id;
  tr.style.cssText = 'border-bottom:1px solid var(--border);';
  tr.innerHTML = `
    <td style="padding:6px 4px;">
      <input type="number" class="form-control form-control-sm batch-qty" min="1" max="999"
             value="${qty}" style="width:80px;" oninput="onBatchChange()">
    </td>
    <td style="padding:6px 4px;">
      <input type="number" class="form-control form-control-sm batch-price" min="0" step="1"
             value="${price}" placeholder="—" style="width:130px;">
    </td>
    <td style="padding:6px 4px;">
      <select class="form-control form-control-sm batch-cond" style="width:120px;">
        <option value="good"  ${condition==='good' ?'selected':''}>Baik</option>
        <option value="fair"  ${condition==='fair' ?'selected':''}>Cukup Baik</option>
        <option value="poor"  ${condition==='poor' ?'selected':''}>Kurang Baik</option>
      </select>
    </td>
    <td style="padding:6px 4px;">
      <select class="form-control form-control-sm batch-loc" style="width:140px;">
        <option value="">— Ikut barang —</option>
        ${locOpts}
      </select>
    </td>
    <td style="padding:6px 4px;text-align:center;">
      <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick="removeBatchRow(${id})"
              title="Hapus batch" style="color:var(--danger);">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </td>`;
  document.getElementById('batchBody').appendChild(tr);
  onBatchChange();
}

function removeBatchRow(id) {
  document.getElementById('brow-' + id)?.remove();
  onBatchChange();
}

function getBatchTotal() {
  let total = 0;
  document.querySelectorAll('.batch-qty').forEach(el => {
    total += Math.max(1, parseInt(el.value) || 0);
  });
  return total;
}

function onBatchChange() {
  const total = getBatchTotal();
  const txt   = document.getElementById('batchTotalText');
  const badge = document.getElementById('batchTotalBadge');
  const hid   = document.getElementById('fieldQty');
  if (txt)   txt.textContent   = total + ' unit';
  if (badge) badge.textContent = total + ' unit';
  if (hid)   hid.value         = total;
  updateCodePreview();
}

// Serialisasi batch ke JSON sebelum submit
document.querySelector('form').addEventListener('submit', function(e) {
  const rows = document.querySelectorAll('#batchBody tr');
  if (rows.length === 0) {
    e.preventDefault();
    alert('Tambahkan minimal 1 batch unit sebelum menyimpan barang.');
    return;
  }
  const batches = [];
  rows.forEach(tr => {
    batches.push({
      qty:         parseInt(tr.querySelector('.batch-qty')?.value)  || 1,
      price:       tr.querySelector('.batch-price')?.value.trim()   || '',
      condition:   tr.querySelector('.batch-cond')?.value           || 'good',
      location_id: tr.querySelector('.batch-loc')?.value            || '',
    });
  });
  document.getElementById('unitBatchesJson').value = JSON.stringify(batches);
});

// Inisialisasi dengan 1 baris
addBatchRow();
<?php endif; ?>

// Run on load
updateCodePreview();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
