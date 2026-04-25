<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Kategori';
$db = getDB();

// Handle add/edit via modal POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $data = [
            'name'        => trim($_POST['name'] ?? ''),
            'code'        => strtoupper(trim($_POST['code'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'color'       => $_POST['color'] ?? '#6366f1',
            'icon'        => trim($_POST['icon'] ?? 'box'),
        ];
        if (!$data['name'] || !$data['code']) { flashMessage('error', 'Nama dan kode wajib diisi.'); }
        else {
            if ($id) {
                $db->prepare("UPDATE categories SET name=?,code=?,description=?,color=?,icon=? WHERE id=?")
                   ->execute([$data['name'],$data['code'],$data['description'],$data['color'],$data['icon'],$id]);
                auditLog('UPDATE', 'categories', $id, 'Category updated: '.$data['name']);
                flashMessage('success', 'Kategori berhasil diperbarui.');
            } else {
                $check = $db->prepare("SELECT id FROM categories WHERE code=?"); $check->execute([$data['code']]);
                if ($check->fetch()) { flashMessage('error', 'Kode kategori sudah digunakan.'); }
                else {
                    $data['created_by'] = $_SESSION['user_id'];
                    $db->prepare("INSERT INTO categories (name,code,description,color,icon,created_by) VALUES (?,?,?,?,?,?)")
                       ->execute(array_values($data));
                    auditLog('CREATE', 'categories', $db->lastInsertId(), 'Category created: '.$data['name']);
                    flashMessage('success', 'Kategori berhasil ditambahkan.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $items = $db->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?"); $items->execute([$id]);
        if ($items->fetchColumn() > 0) { flashMessage('error', 'Kategori tidak dapat dihapus karena masih memiliki barang.'); }
        else {
            $cat = $db->prepare("SELECT name FROM categories WHERE id=?"); $cat->execute([$id]); $catData = $cat->fetch();
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            auditLog('DELETE', 'categories', $id, 'Category deleted: '.($catData['name']??''));
            flashMessage('success', 'Kategori berhasil dihapus.');
        }
    }
    header('Location: index.php'); exit;
}

$categories = $db->query("
    SELECT c.*, COUNT(i.id) as item_count
    FROM categories c
    LEFT JOIN items i ON c.id = i.category_id AND i.status = 'active'
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Kategori
    </div>
    <h2>Kategori Barang</h2>
    <p>Kelola kategori inventaris</p>
  </div>
  <?php if (isAdmin()): ?>
  <button class="btn btn-primary" onclick="openModal('modalCategory')">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
    Tambah Kategori
  </button>
  <?php endif; ?>
</div>

<div class="cat-grid">
  <?php foreach ($categories as $cat): ?>
  <div class="card" style="transition: all 0.2s;" onmouseover="this.style.borderColor=this.dataset.color" onmouseout="this.style.borderColor=''" data-color="<?= sanitize($cat['color']) ?>">
    <div class="card-body">
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: var(--radius); background: <?= sanitize($cat['color']) ?>22; border: 1px solid <?= sanitize($cat['color']) ?>44; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <div style="width: 16px; height: 16px; border-radius: 3px; background: <?= sanitize($cat['color']) ?>;"></div>
          </div>
          <div>
            <div class="table-item-name" style="font-size:0.95rem;font-weight:600;"><?= sanitize($cat['name']) ?></div>
            <div class="table-item-code mono"><?= sanitize($cat['code']) ?></div>
          </div>
        </div>
        <?php if (isAdmin()): ?>
        <div class="dropdown" id="catDrop<?= $cat['id'] ?>">
          <button class="btn btn-ghost btn-icon btn-sm" onclick="toggleDropdown('catDrop<?= $cat['id'] ?>')">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
          </button>
          <div class="dropdown-menu">
            <div class="dropdown-item" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Edit
            </div>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $cat['id'] ?>">
              <div class="dropdown-item danger" onclick="confirmDelete('Hapus kategori <?= sanitize(addslashes($cat['name'])) ?>?', this.closest('form'))">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Hapus
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($cat['description']): ?>
      <p class="table-item-sub" style="margin-top: 12px; line-height: 1.5;"><?= sanitize($cat['description']) ?></p>
      <?php endif; ?>
      <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
        <span class="table-item-sub">Total Barang</span>
        <a href="<?= APP_URL ?>/modules/inventory/index.php?category=<?= $cat['id'] ?>" style="font-size: 1.1rem; font-weight: 700; color: <?= sanitize($cat['color']) ?>;"><?= $cat['item_count'] ?></a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($categories)): ?>
  <div class="card" style="grid-column: 1/-1;">
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
      <h3>Belum ada kategori</h3>
      <p>Tambah kategori untuk mengorganisir barang inventaris</p>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<?php if (isAdmin()): ?>
<div class="modal-overlay" id="modalCategory">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modalCatTitle">Tambah Kategori</h3>
      <button class="modal-close" onclick="closeModal('modalCategory')">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="catId" value="0">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full">
            <label class="form-label">Nama Kategori <span class="required">*</span></label>
            <input type="text" name="name" id="catName" class="form-control" required placeholder="Elektronik, Furnitur...">
          </div>
          <div class="form-group">
            <label class="form-label">Kode <span class="required">*</span></label>
            <input type="text" name="code" id="catCode" class="form-control" required placeholder="ELEC" style="text-transform:uppercase;" maxlength="20">
          </div>
          <div class="form-group">
            <label class="form-label">Warna</label>
            <input type="color" name="color" id="catColor" class="form-control" value="#6366f1" style="height: 42px; padding: 4px;">
          </div>
          <div class="form-group full">
            <label class="form-label">Deskripsi</label>
            <textarea name="description" id="catDesc" class="form-control" rows="2" placeholder="Deskripsi kategori..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalCategory')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<script>
function editCategory(cat) {
  document.getElementById('modalCatTitle').textContent = 'Edit Kategori';
  document.getElementById('catId').value = cat.id;
  document.getElementById('catName').value = cat.name;
  document.getElementById('catCode').value = cat.code;
  document.getElementById('catColor').value = cat.color || '#6366f1';
  document.getElementById('catDesc').value = cat.description || '';
  openModal('modalCategory');
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
