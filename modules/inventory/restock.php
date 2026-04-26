<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('superadmin', 'admin');

$db = getDB();

// Load semua barang aktif untuk dropdown
$allItems = $db->query("
    SELECT i.id, i.name, i.code, i.quantity, i.condition, i.location_id,
           i.purchase_price, i.category_id, i.supplier, i.warranty_expiry,
           c.name as cat_name, c.code as cat_code,
           l.name as loc_name,
           (SELECT COUNT(*) FROM item_units WHERE item_id = i.id) as unit_count,
           (SELECT COUNT(*) FROM item_units WHERE item_id = i.id AND status = 'available') as unit_avail
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN locations  l ON i.location_id  = l.id
    WHERE i.status != 'disposed'
    ORDER BY i.name
")->fetchAll();

$locations = $db->query("SELECT id, name FROM locations ORDER BY name")->fetchAll();

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId     = (int)($_POST['item_id'] ?? 0);
    $addQty     = max(1, (int)($_POST['add_qty'] ?? 1));
    $condition  = in_array($_POST['condition'] ?? '', ['good','fair','poor']) ? $_POST['condition'] : 'good';
    $locationId = (int)($_POST['location_id'] ?? 0) ?: null;
    $price      = isset($_POST['purchase_price']) && $_POST['purchase_price'] !== ''
                  ? abs((float)$_POST['purchase_price']) : null;
    $acquiredDate  = $_POST['acquired_date']  ?: null;
    $supplier      = trim($_POST['supplier']  ?? '');
    $warrantyExpiry= $_POST['warranty_expiry']?: null;

    if (!$itemId) {
        flashMessage('error', 'Pilih barang terlebih dahulu.');
        header('Location: restock.php'); exit;
    }

    $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        flashMessage('error', 'Barang tidak ditemukan.');
        header('Location: restock.php'); exit;
    }

    $db->beginTransaction();
    try {
        $currentMax = (int)$db->query("SELECT MAX(unit_number) FROM item_units WHERE item_id = {$itemId}")->fetchColumn();
        $newTotal   = $currentMax + $addQty;

        generateMissingUnits($db, $itemId, $item['code'], $item['category_id'], $newTotal, $condition, $locationId);

        // Set acquired_date, purchase_price, supplier pada unit yang baru dibuat
        $db->prepare("
            UPDATE item_units
            SET acquired_date = ?, purchase_price = ?, supplier = ?
            WHERE item_id = ? AND unit_number > ? AND unit_number <= ?
        ")->execute([$acquiredDate, $price, $supplier ?: null, $itemId, $currentMax, $newTotal]);

        // Update items.quantity
        $db->prepare("UPDATE items SET quantity = ?, updated_at = NOW() WHERE id = ?")->execute([$newTotal, $itemId]);

        // Update warranty jika diisi
        if ($warrantyExpiry) {
            $db->prepare("UPDATE items SET warranty_expiry = COALESCE(?, warranty_expiry), updated_at = NOW() WHERE id = ?")
               ->execute([$warrantyExpiry, $itemId]);
        }

        syncItemAvailability($db, $itemId);
        $db->commit();

        auditLog('UPDATE', 'inventory', $itemId, "Restock +{$addQty} unit ke {$item['name']}");
        flashMessage('success', "{$addQty} unit berhasil ditambahkan ke \"{$item['name']}\". Total sekarang: {$newTotal} unit.");
        header('Location: index.php'); exit;

    } catch (\Exception $e) {
        $db->rollBack();
        flashMessage('error', 'Gagal menambah unit: ' . $e->getMessage());
        header('Location: restock.php'); exit;
    }
}

$pageTitle = 'Tambah Unit';
$itemsJson = json_encode(array_column($allItems, null, 'id'));
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      <a href="index.php">Inventaris</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Tambah Unit
    </div>
    <h2>Tambah Unit</h2>
    <p>Tambahkan unit baru ke barang yang sudah terdaftar</p>
  </div>
</div>

<form method="POST">
  <div class="grid-2" style="gap:24px;align-items:start;">

    <!-- ── Kolom kiri: Pilih Barang ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card">
        <div class="card-header"><div class="card-title">Pilih Barang</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Barang <span class="required">*</span></label>
            <select name="item_id" id="itemSelect" class="form-control" required onchange="onItemChange(this.value)">
              <option value="">— Cari atau pilih barang —</option>
              <?php foreach ($allItems as $it): ?>
              <option value="<?= $it['id'] ?>"><?= sanitize($it['name']) ?> — <?= sanitize($it['code']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Info barang terpilih -->
          <div id="itemInfoCard" style="display:none;">
            <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:14px 16px;">
              <div style="margin-bottom:12px;">
                <div id="infoName" style="font-weight:700;color:var(--text-primary);font-size:0.95rem;"></div>
                <div id="infoCode" style="font-family:monospace;font-size:0.78rem;color:var(--accent-light);margin-top:2px;"></div>
              </div>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center;margin-bottom:12px;">
                <div style="background:var(--bg-card);border-radius:var(--radius-sm);padding:10px 6px;">
                  <div id="infoTotal" style="font-size:1.2rem;font-weight:800;color:var(--text-primary);"></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);margin-top:2px;">Total</div>
                </div>
                <div style="background:var(--bg-card);border-radius:var(--radius-sm);padding:10px 6px;">
                  <div id="infoAvail" style="font-size:1.2rem;font-weight:800;color:var(--success);"></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);margin-top:2px;">Tersedia</div>
                </div>
                <div style="background:var(--bg-card);border-radius:var(--radius-sm);padding:10px 6px;border:1px solid var(--accent-dim,rgba(99,102,241,0.25));">
                  <div id="infoAfter" style="font-size:1.2rem;font-weight:800;color:var(--accent-light);"></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);margin-top:2px;">Setelah</div>
                </div>
              </div>
              <div style="display:flex;gap:16px;font-size:0.78rem;color:var(--text-muted);">
                <span>Kategori: <strong id="infoCat" style="color:var(--text-secondary);"></strong></span>
                <span>Lokasi: <strong id="infoLoc" style="color:var(--text-secondary);"></strong></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Kolom kanan: Detail Penambahan ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card" style="border:1px solid var(--accent-dim,rgba(99,102,241,0.3));">
        <div class="card-header" style="border-bottom:1px solid var(--accent-dim,rgba(99,102,241,0.2));">
          <div class="card-title" style="color:var(--accent-light);">Detail Penambahan</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

          <!-- Jumlah -->
          <div class="form-group" style="margin:0;">
            <label class="form-label">Jumlah Unit Ditambahkan <span class="required">*</span></label>
            <input type="number" name="add_qty" id="addQtyInput" class="form-control"
                   min="1" max="9999" value="1" required oninput="updateAfter()">
            <span class="form-hint" id="startHint">Pilih barang terlebih dahulu</span>
          </div>

          <div style="height:1px;background:var(--border);"></div>

          <!-- Kondisi & Lokasi -->
          <div class="form-grid">
            <div class="form-group" style="margin:0;">
              <label class="form-label">Kondisi</label>
              <select name="condition" class="form-control">
                <option value="good">Baik</option>
                <option value="fair">Cukup Baik</option>
                <option value="poor">Kurang Baik</option>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Lokasi</label>
              <select name="location_id" id="locationSelect" class="form-control">
                <option value="">— Ikut barang —</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>"><?= sanitize($loc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="height:1px;background:var(--border);"></div>

          <!-- Info Pengadaan -->
          <div class="form-grid">
            <div class="form-group" style="margin:0;">
              <label class="form-label">Tanggal Pembelian</label>
              <input type="date" name="acquired_date" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Harga Beli / Unit (Rp)</label>
              <input type="number" name="purchase_price" class="form-control" min="0" step="1" placeholder="Opsional">
              <span class="form-hint" id="priceHint"></span>
            </div>
            <div class="form-group full" style="margin:0;">
              <label class="form-label">Supplier / Vendor</label>
              <input type="text" name="supplier" id="supplierInput" class="form-control" placeholder="Nama supplier...">
            </div>
            <div class="form-group full" style="margin:0;">
              <label class="form-label">Garansi Hingga</label>
              <input type="date" name="warranty_expiry" class="form-control">
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end;">
    <a href="index.php" class="btn btn-outline">Batal</a>
    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Tambah Unit
    </button>
  </div>
</form>

<script>
const itemsData    = <?= $itemsJson ?>;
let selectedItem   = null;

function onItemChange(id) {
  selectedItem = itemsData[id] || null;
  const card   = document.getElementById('itemInfoCard');
  const btn    = document.getElementById('submitBtn');

  if (!selectedItem) {
    card.style.display = 'none';
    btn.disabled = true;
    return;
  }

  // Isi info card
  document.getElementById('infoName').textContent  = selectedItem.name;
  document.getElementById('infoCode').textContent  = selectedItem.code;
  document.getElementById('infoTotal').textContent = selectedItem.unit_count;
  document.getElementById('infoAvail').textContent = selectedItem.unit_avail;
  document.getElementById('infoCat').textContent   = selectedItem.cat_name || '—';
  document.getElementById('infoLoc').textContent   = selectedItem.loc_name || '—';

  // Default lokasi ke lokasi barang
  const locSel = document.getElementById('locationSelect');
  if (selectedItem.location_id) {
    locSel.value = selectedItem.location_id;
  }

  // Hint harga
  const ph = document.getElementById('priceHint');
  if (selectedItem.purchase_price) {
    ph.textContent = 'Harga barang saat ini: Rp ' +
      parseInt(selectedItem.purchase_price).toLocaleString('id-ID');
  } else {
    ph.textContent = '';
  }

  card.style.display = 'block';
  btn.disabled = false;
  updateAfter();
}

function updateAfter() {
  if (!selectedItem) return;
  const qty   = Math.max(1, parseInt(document.getElementById('addQtyInput').value) || 0);
  const after = parseInt(selectedItem.unit_count) + qty;
  document.getElementById('infoAfter').textContent = after;

  const hint = document.getElementById('startHint');
  hint.textContent = 'Unit baru mulai dari nomor ' + (parseInt(selectedItem.unit_count) + 1);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
