<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Lokasi';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $data = [
            'name'        => trim($_POST['name'] ?? ''),
            'code'        => strtoupper(trim($_POST['code'] ?? '')),
            'building'    => trim($_POST['building'] ?? ''),
            'floor'       => trim($_POST['floor'] ?? ''),
            'room'        => trim($_POST['room'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'pic_name'    => trim($_POST['pic_name'] ?? ''),
            'pic_phone'   => trim($_POST['pic_phone'] ?? ''),
        ];
        if (!$data['name'] || !$data['code']) { flashMessage('error', 'Nama dan kode wajib diisi.'); }
        else {
            if ($id) {
                $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
                $db->prepare("UPDATE locations SET $sets WHERE id=?")->execute(array_merge(array_values($data), [$id]));
                auditLog('UPDATE','locations',$id,'Location updated: '.$data['name']);
                flashMessage('success','Lokasi berhasil diperbarui.');
            } else {
                $check=$db->prepare("SELECT id FROM locations WHERE code=?"); $check->execute([$data['code']]);
                if ($check->fetch()) { flashMessage('error','Kode lokasi sudah digunakan.'); }
                else {
                    $data['created_by']=$_SESSION['user_id'];
                    $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($data)));
                    $vals=implode(',',array_fill(0,count($data),'?'));
                    $db->prepare("INSERT INTO locations ($cols) VALUES ($vals)")->execute(array_values($data));
                    auditLog('CREATE','locations',$db->lastInsertId(),'Location created: '.$data['name']);
                    flashMessage('success','Lokasi berhasil ditambahkan.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $items=$db->prepare("SELECT COUNT(*) FROM items WHERE location_id=?"); $items->execute([$id]);
        if ($items->fetchColumn()>0){ flashMessage('error','Lokasi tidak dapat dihapus karena masih memiliki barang.'); }
        else {
            $loc=$db->prepare("SELECT name FROM locations WHERE id=?"); $loc->execute([$id]); $locData=$loc->fetch();
            $db->prepare("DELETE FROM locations WHERE id=?")->execute([$id]);
            auditLog('DELETE','locations',$id,'Location deleted: '.($locData['name']??''));
            flashMessage('success','Lokasi berhasil dihapus.');
        }
    }
    header('Location: index.php'); exit;
}

$locations = $db->query("
    SELECT l.*, COUNT(i.id) as item_count
    FROM locations l
    LEFT JOIN items i ON l.id = i.location_id AND i.status='active'
    GROUP BY l.id
    ORDER BY l.building, l.floor, l.name
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg> Lokasi</div>
    <h2>Lokasi &amp; Ruangan</h2>
    <p>Kelola lokasi penyimpanan inventaris</p>
  </div>
  <?php if (isAdmin()): ?>
  <button class="btn btn-primary" onclick="openModal('modalLocation')">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
    Tambah Lokasi
  </button>
  <?php endif; ?>
</div>

<div class="card">
  <div class="table-container">
    <?php if (empty($locations)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
      <h3>Belum ada lokasi</h3>
    </div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Lokasi</th><th>Gedung</th><th>Lantai / Ruang</th><th>PIC</th><th>Jumlah Barang</th><?php if(isAdmin()):?><th style="text-align:right;">Aksi</th><?php endif;?></tr></thead>
      <tbody>
        <?php foreach ($locations as $loc): ?>
        <tr>
          <td>
            <div class="table-item-name"><?= sanitize($loc['name']) ?></div>
            <div class="table-item-code mono"><?= sanitize($loc['code']) ?></div>
          </td>
          <td><?= sanitize($loc['building'] ?: '-') ?></td>
          <td><?= sanitize(($loc['floor'] ? 'Lt. '.$loc['floor'] : '') . ($loc['room'] ? ', '.$loc['room'] : '') ?: '-') ?></td>
          <td>
            <?php if ($loc['pic_name']): ?>
              <div><?= sanitize($loc['pic_name']) ?></div>
              <?php if ($loc['pic_phone']): ?><div class="table-item-code"><?= sanitize($loc['pic_phone']) ?></div><?php endif; ?>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td>
            <a href="<?= APP_URL ?>/modules/inventory/index.php?location=<?= $loc['id'] ?>" class="badge badge-accent"><?= $loc['item_count'] ?> barang</a>
          </td>
          <?php if (isAdmin()): ?>
          <td style="text-align:right;">
            <div class="btn-group" style="justify-content:flex-end;">
              <button class="btn btn-ghost btn-icon btn-sm" onclick='editLocation(<?= htmlspecialchars(json_encode($loc)) ?>)' title="Edit">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $loc['id'] ?>">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger);" title="Hapus"
                  onclick="confirmDelete('Hapus lokasi <?= sanitize(addslashes($loc['name'])) ?>?', this.closest('form'))">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                </button>
              </form>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php if (isAdmin()): ?>
<div class="modal-overlay" id="modalLocation">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title" id="modalLocTitle">Tambah Lokasi</h3>
      <button class="modal-close" onclick="closeModal('modalLocation')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="locId" value="0">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full"><label class="form-label">Nama Lokasi <span class="required">*</span></label><input type="text" name="name" id="locName" class="form-control" required placeholder="Lab Komputer FT"></div>
          <div class="form-group"><label class="form-label">Kode <span class="required">*</span></label><input type="text" name="code" id="locCode" class="form-control" required placeholder="FT-LK" style="text-transform:uppercase;" maxlength="20"></div>
          <div class="form-group"><label class="form-label">Gedung / Bangunan</label><input type="text" name="building" id="locBuilding" class="form-control" placeholder="Gedung Fakultas Teknik"></div>
          <div class="form-group"><label class="form-label">Lantai</label><input type="text" name="floor" id="locFloor" class="form-control" placeholder="1, 2, G, B1..."></div>
          <div class="form-group"><label class="form-label">Ruangan / Nomor</label><input type="text" name="room" id="locRoom" class="form-control" placeholder="Lab. 101"></div>
          <div class="form-group"><label class="form-label">PIC / Penanggung Jawab</label><input type="text" name="pic_name" id="locPic" class="form-control" placeholder="Nama PIC"></div>
          <div class="form-group"><label class="form-label">No. Telepon PIC</label><input type="text" name="pic_phone" id="locPicPhone" class="form-control" placeholder="08xx-xxxx-xxxx"></div>
          <div class="form-group full"><label class="form-label">Keterangan</label><textarea name="description" id="locDesc" class="form-control" rows="2" placeholder="Deskripsi lokasi..."></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalLocation')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<script>
function editLocation(loc) {
  document.getElementById('modalLocTitle').textContent = 'Edit Lokasi';
  document.getElementById('locId').value = loc.id;
  document.getElementById('locName').value = loc.name || '';
  document.getElementById('locCode').value = loc.code || '';
  document.getElementById('locBuilding').value = loc.building || '';
  document.getElementById('locFloor').value = loc.floor || '';
  document.getElementById('locRoom').value = loc.room || '';
  document.getElementById('locPic').value = loc.pic_name || '';
  document.getElementById('locPicPhone').value = loc.pic_phone || '';
  document.getElementById('locDesc').value = loc.description || '';
  openModal('modalLocation');
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
