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
    if ($isEdit) {
        // Edit mode: only update identitas + foto & catatan fields
        // All other fields (klasifikasi, stok, pengadaan) keep existing values
        $data = [
            'code'           => strtoupper(trim($_POST['code'] ?? '')),
            'name'           => trim($_POST['name'] ?? ''),
            'brand'          => trim($_POST['brand'] ?? ''),
            'model'          => trim($_POST['model'] ?? ''),
            'serial_number'  => $item['serial_number'] ?? null,
            'description'    => trim($_POST['description'] ?? ''),
            'unit'           => trim($_POST['unit'] ?? ($item['unit'] ?? 'unit')),
            'notes'          => trim($_POST['notes'] ?? ''),
            // keep existing values
            'category_id'    => $item['category_id'],
            'location_id'    => $item['location_id'],
            'condition'      => $item['condition'],
            'status'         => $item['status'],
            'quantity'       => $item['quantity'],
            'min_stock'      => $item['min_stock'],
            'purchase_date'  => $item['purchase_date'],
            'purchase_price' => $item['purchase_price'],
            'supplier'       => $item['supplier'],
            'warranty_expiry'=> $item['warranty_expiry'],
        ];
    } else {
        $data = [
            'code'          => strtoupper(trim($_POST['code'] ?? '')),
            'name'          => trim($_POST['name'] ?? ''),
            'brand'         => trim($_POST['brand'] ?? ''),
            'model'         => trim($_POST['model'] ?? ''),
            'serial_number' => null,
            'category_id'   => (int)($_POST['category_id'] ?? 0) ?: null,
            'location_id'   => (int)($_POST['location_id'] ?? 0) ?: null,
            'description'   => trim($_POST['description'] ?? ''),
            'quantity'      => max(1, (int)($_POST['quantity'] ?? 1)),
            'unit'          => trim($_POST['unit'] ?? 'unit'),
            'condition'     => $_POST['condition'] ?? 'good',
            'status'        => $_POST['status'] ?? 'active',
            'purchase_date' => $_POST['purchase_date'] ?: null,
            'purchase_price'=> isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? abs((float)$_POST['unit_price']) : null,
            'supplier'      => trim($_POST['supplier'] ?? ''),
            'warranty_expiry'=> $_POST['warranty_expiry'] ?: null,
            'notes'         => trim($_POST['notes'] ?? ''),
            'min_stock'     => max(0, (int)($_POST['min_stock'] ?? 0)),
        ];
    }

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
            $data['quantity_available'] = $data['quantity'];
            $data['created_by']         = $_SESSION['user_id'];

            $sql  = "INSERT INTO items (" . implode(', ', array_map(fn($k) => "`$k`", array_keys($data))) . ") VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            $newId = $db->lastInsertId();

            $created = generateMissingUnits($db, $newId, $data['code'], $data['category_id'], $data['quantity'], $data['condition'], $data['location_id']);

            // Simpan harga, tanggal beli, dan supplier ke semua unit yang baru dibuat
            $unitPrice    = isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? abs((float)$_POST['unit_price']) : null;
            $unitSupplier = trim($_POST['supplier'] ?? '') ?: null;
            $unitAcquired = $_POST['purchase_date'] ?: null;
            $db->prepare("UPDATE item_units SET purchase_price = ?, acquired_date = ?, supplier = ? WHERE item_id = ?")
               ->execute([$unitPrice, $unitAcquired, $unitSupplier, $newId]);

            syncItemAvailability($db, $newId);

            auditLog('CREATE', 'inventory', $newId, 'Item created: ' . $data['name']);
            flashMessage('success', 'Barang berhasil ditambahkan dengan ' . $created . ' unit.');
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
  <div class="grid-2" style="gap:24px;align-items:start;">

    <!-- ── Kolom kiri ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <!-- Identitas Barang -->
      <div class="card">
        <div class="card-header"><div class="card-title">Identitas Barang</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

          <!-- Kode Produk -->
          <div class="form-group" style="margin:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
              <label class="form-label" style="margin:0;">Kode Produk <span class="required">*</span></label>
              <?php if (!$isEdit): ?>
              <div style="display:flex;border:1px solid var(--border);border-radius:6px;overflow:hidden;">
                <button type="button" id="btnModeAuto" onclick="setCodeMode('auto')"
                  style="padding:3px 12px;border:none;cursor:pointer;background:var(--accent);color:#fff;font-size:0.72rem;font-weight:600;">Auto</button>
                <button type="button" id="btnModeManual" onclick="setCodeMode('manual')"
                  style="padding:3px 12px;border:none;cursor:pointer;background:transparent;color:var(--text-muted);font-size:0.72rem;font-weight:600;">Manual</button>
              </div>
              <?php endif; ?>
            </div>
            <input type="text" name="code" id="fieldCode" class="form-control" required
                   value="<?= sanitize($item['code'] ?? ($_POST['code'] ?? '')) ?>"
                   placeholder="Otomatis dari nama &amp; merek"
                   style="text-transform:uppercase;font-family:var(--font-mono,monospace);"
                   oninput="onCodeInput()" <?= $isEdit ? '' : 'readonly' ?>>
            <span class="form-hint" id="codeHint"><?= $isEdit ? 'Kode unik barang ini' : 'Diisi otomatis dari nama, merek, dan model' ?></span>
          </div>

          <div style="height:1px;background:var(--border);"></div>

          <!-- Nama, Merek, Model -->
          <div class="form-grid">
            <div class="form-group" style="margin:0;">
              <label class="form-label">Nama Barang <span class="required">*</span></label>
              <input type="text" name="name" id="fieldName" class="form-control" required
                     value="<?= sanitize($item['name'] ?? ($_POST['name'] ?? '')) ?>"
                     placeholder="Laptop, Meja Kerja, ..."
                     <?= !$isEdit ? 'oninput="onNameBrandModelInput()"' : '' ?>>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Merek / Brand</label>
              <input type="text" name="brand" id="fieldBrand" class="form-control"
                     value="<?= sanitize($item['brand'] ?? ($_POST['brand'] ?? '')) ?>"
                     placeholder="Dell, HP, Samsung..."
                     <?= !$isEdit ? 'oninput="onNameBrandModelInput()"' : '' ?>>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Model</label>
              <input type="text" name="model" id="fieldModel" class="form-control"
                     value="<?= sanitize($item['model'] ?? ($_POST['model'] ?? '')) ?>"
                     placeholder="Latitude 5520, X-Series..."
                     <?= !$isEdit ? 'oninput="onNameBrandModelInput()"' : '' ?>>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Satuan</label>
              <select name="unit" class="form-control">
                <?php foreach (['unit'=>'Unit','pcs'=>'Pcs','set'=>'Set','buah'=>'Buah','lembar'=>'Lembar','rim'=>'Rim','botol'=>'Botol','liter'=>'Liter','kg'=>'Kg','meter'=>'Meter'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($item['unit'] ?? 'unit') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group" style="margin:0;">
            <label class="form-label">Deskripsi</label>
            <textarea name="description" class="form-control" rows="3"
                      placeholder="Deskripsi singkat barang..."><?= sanitize($item['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
          </div>
        </div>
      </div>

      <?php if (!$isEdit): ?>
      <!-- Klasifikasi & Lokasi (create only) -->
      <div class="card">
        <div class="card-header"><div class="card-title">Klasifikasi &amp; Lokasi</div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Kategori</label>
              <select name="category_id" id="fieldCategory" class="form-control" onchange="updateCodePreview()">
                <option value="">— Pilih Kategori —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($item['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Lokasi</label>
              <select name="location_id" class="form-control">
                <option value="">— Pilih Lokasi —</option>
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
      <?php endif; ?>

    </div>

    <!-- ── Kolom kanan ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <?php if (!$isEdit): ?>
      <!-- Preview Kode Unit (create only) -->
      <div class="card" id="unitPreviewCard">
        <div class="card-header">
          <div class="card-title">Preview Kode Unit</div>
          <span id="unitPreviewCount" style="font-size:0.78rem;color:var(--text-muted);"></span>
        </div>
        <div class="card-body">
          <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:8px;">
            Format: <code style="color:var(--accent-light);">{kat}-{kode}-U001</code>
          </div>
          <div id="unitPreviewBox" style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:12px 14px;font-family:monospace;font-size:0.82rem;line-height:2;min-height:52px;color:var(--text-secondary);">
            <span style="color:var(--text-disabled);font-size:0.78rem;">Isi nama barang untuk melihat preview...</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($isEdit):
        $unitStats2 = $db->prepare("SELECT status, COUNT(*) as cnt FROM item_units WHERE item_id = ? GROUP BY status");
        $unitStats2->execute([$id]);
        $uStats = ['available'=>0,'borrowed'=>0,'reserved'=>0,'maintenance'=>0,'damaged'=>0,'disposed'=>0,'lost'=>0];
        foreach ($unitStats2->fetchAll() as $r) $uStats[$r['status']] = (int)$r['cnt'];
        $uTotal = array_sum($uStats);
      ?>
      <!-- Kode Unit (edit only) -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Kode Unit</div>
          <span style="font-size:0.78rem;color:var(--text-muted);"><?= $uTotal ?> unit</span>
        </div>
        <div class="card-body" style="padding-bottom:14px;">
          <div id="unitPreviewBox" style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:12px 14px;font-family:monospace;font-size:0.82rem;line-height:2;min-height:52px;color:var(--text-secondary);">
            <span style="color:var(--text-disabled);font-size:0.78rem;">Memuat preview...</span>
          </div>
          <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= $uTotal ?> unit terdaftar</span>
            <a href="units.php?item_id=<?= $id ?>" class="btn btn-ghost btn-sm">Kelola Unit</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$isEdit): ?>
      <!-- Stok & Pengadaan (create only) -->
      <div class="card">
        <div class="card-header"><div class="card-title">Stok &amp; Pengadaan</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
          <div class="form-grid">
            <div class="form-group" style="margin:0;">
              <label class="form-label">Jumlah Total <span class="required">*</span></label>
              <input type="number" name="quantity" id="fieldQty" class="form-control" required min="1"
                     value="1" oninput="updateCodePreview()">
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Batas Minimum</label>
              <input type="number" name="min_stock" class="form-control" min="0" value="0">
              <span class="form-hint">Alert jika stok di bawah ini</span>
            </div>
            <div class="form-group full" style="margin:0;">
              <label class="form-label">Harga Beli per Unit (Rp)</label>
              <input type="number" name="unit_price" id="unitPrice" class="form-control" min="0" step="1" placeholder="Opsional">
              <span class="form-hint">Disimpan ke setiap unit yang dibuat</span>
            </div>
          </div>

          <div style="height:1px;background:var(--border);"></div>

          <div class="form-grid">
            <div class="form-group" style="margin:0;">
              <label class="form-label">Tanggal Pembelian</label>
              <input type="date" name="purchase_date" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Garansi Hingga</label>
              <input type="date" name="warranty_expiry" class="form-control">
            </div>
            <div class="form-group full" style="margin:0;">
              <label class="form-label">Supplier / Vendor</label>
              <input type="text" name="supplier" class="form-control" placeholder="Nama supplier...">
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Foto & Catatan -->
      <div class="card">
        <div class="card-header"><div class="card-title">Foto &amp; Catatan</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
          <div style="display:flex;gap:16px;align-items:flex-start;">
            <div style="width:120px;flex-shrink:0;">
              <div id="imgPreview" style="width:120px;height:90px;background:var(--bg-elevated);border-radius:var(--radius-sm);overflow:hidden;display:flex;align-items:center;justify-content:center;border:2px dashed var(--border);margin-bottom:8px;">
                <?php if (!empty($item['image'])): ?>
                <img src="<?= UPLOAD_URL . sanitize($item['image']) ?>" style="width:100%;height:100%;object-fit:cover;" id="previewImg">
                <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:var(--text-disabled);"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/></svg>
                <?php endif; ?>
              </div>
              <label class="btn btn-outline btn-sm" style="cursor:pointer;width:100%;justify-content:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Upload
                <input type="file" name="image" accept="image/*" style="display:none;" onchange="previewImage(this)">
              </label>
              <p class="form-hint" style="margin-top:6px;text-align:center;">JPG/PNG &le;5MB</p>
            </div>
            <div style="flex:1;">
              <label class="form-label">Catatan</label>
              <textarea name="notes" class="form-control" rows="5"
                        placeholder="Catatan tambahan..."><?= sanitize($item['notes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end;padding-top:4px;">
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

function setCodeMode(mode) {
  _codeIsAuto = (mode === 'auto');
  const el      = document.getElementById('fieldCode');
  const hint    = document.getElementById('codeHint');
  const btnAuto = document.getElementById('btnModeAuto');
  const btnMan  = document.getElementById('btnModeManual');

  if (_codeIsAuto) {
    el.readOnly        = true;
    el.style.color     = 'var(--accent-light)';
    btnAuto.style.background = 'var(--accent)';
    btnAuto.style.color      = '#fff';
    btnMan.style.background  = 'transparent';
    btnMan.style.color       = 'var(--text-muted)';
    hint.textContent = 'Diisi otomatis dari nama, merek, dan model';
    generateAutoCode();
  } else {
    el.readOnly        = false;
    el.style.color     = '';
    el.value           = '';
    el.placeholder     = 'Ketik kode produk...';
    el.focus();
    btnMan.style.background  = 'var(--accent)';
    btnMan.style.color       = '#fff';
    btnAuto.style.background = 'transparent';
    btnAuto.style.color      = 'var(--text-muted)';
    hint.textContent = 'Ketik kode produk secara manual';
    updateCodePreview();
  }
}

function onCodeInput() {
  updateCodePreview();
}

// Ambil 3 huruf pertama dari kata pertama (huruf saja, tanpa angka/simbol)
function _abbrev(str) {
  const word = (str || '').trim().split(/\s+/)[0] || '';
  return word.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase();
}

function generateAutoCode() {
  if (!_codeIsAuto) return;
  const name  = document.getElementById('fieldName')?.value  || '';
  const brand = document.getElementById('fieldBrand')?.value || '';
  const model = document.getElementById('fieldModel')?.value || '';

  const parts = [_abbrev(name), _abbrev(brand), _abbrev(model)].filter(p => p.length > 0);
  const el    = document.getElementById('fieldCode');
  el.value    = parts.join('-');
  updateCodePreview();
}

function onNameBrandModelInput() {
  if (!_codeIsAuto) return;
  clearTimeout(_codeGenTimer);
  _codeGenTimer = setTimeout(generateAutoCode, 150);
}
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

  const qty = Math.max(1, Math.min(parseInt(document.getElementById('fieldQty')?.value) || 1, 9999));

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

document.getElementById('fieldQty')?.addEventListener('input', updateCodePreview);


// Run on load
updateCodePreview();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
