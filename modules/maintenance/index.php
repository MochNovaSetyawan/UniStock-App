<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Pemeliharaan';
$db = getDB();

$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$itemId   = (int)($_GET['item_id'] ?? 0);
$perPage  = (int)getSetting('items_per_page', 15);
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search)   { $where[] = '(m.title LIKE ? OR i.name LIKE ? OR m.code LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status)   { $where[] = 'm.status = ?'; $params[] = $status; }
if ($priority) { $where[] = 'm.priority = ?'; $params[] = $priority; }
if ($itemId)   { $where[] = 'm.item_id = ?'; $params[] = $itemId; }
if (hasRole('worker')) { $where[] = 'm.requested_by = ?'; $params[] = $_SESSION['user_id']; }

$whereStr = implode(' AND ', $where);

$cStmt = $db->prepare("SELECT COUNT(*) FROM maintenance m JOIN items i ON m.item_id=i.id WHERE $whereStr");
$cStmt->execute($params); $total = (int)$cStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT m.*, i.name as item_name, i.code as item_code,
           u1.full_name as requested_by_name, u2.full_name as assigned_to_name,
           iu.full_code as unit_full_code
    FROM maintenance m
    JOIN items i ON m.item_id = i.id
    LEFT JOIN users u1 ON m.requested_by = u1.id
    LEFT JOIN users u2 ON m.assigned_to = u2.id
    LEFT JOIN item_units iu ON m.unit_id = iu.id
    WHERE $whereStr
    ORDER BY
      CASE m.status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
      CASE m.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
      m.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$records = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M9 18l6-6-6-6"/></svg>
      Pemeliharaan
    </div>
    <h2>Pemeliharaan Barang</h2>
    <p>Kelola jadwal dan permintaan pemeliharaan inventaris</p>
  </div>
  <a href="form.php" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
    Lapor Kerusakan
  </a>
</div>

<div class="card">
  <div class="table-toolbar">
    <form method="GET" style="display:contents;">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari judul, barang, kode...">
      </div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="pending"     <?= $status==='pending'?'selected':'' ?>>Menunggu</option>
        <option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>Diproses</option>
        <option value="completed"   <?= $status==='completed'?'selected':'' ?>>Selesai</option>
        <option value="cancelled"   <?= $status==='cancelled'?'selected':'' ?>>Dibatalkan</option>
      </select>
      <select name="priority" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Prioritas</option>
        <option value="critical" <?= $priority==='critical'?'selected':'' ?>>Kritis</option>
        <option value="high"     <?= $priority==='high'?'selected':'' ?>>Tinggi</option>
        <option value="medium"   <?= $priority==='medium'?'selected':'' ?>>Sedang</option>
        <option value="low"      <?= $priority==='low'?'selected':'' ?>>Rendah</option>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Cari</button>
      <span style="margin-left:auto;font-size:0.78rem;color:var(--text-muted);"><?= $total ?> catatan</span>
    </form>
  </div>

  <div class="table-container">
    <?php if (empty($records)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <h3>Tidak ada catatan pemeliharaan</h3>
    </div>
    <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Barang</th>
          <th>Judul</th>
          <th>Unit</th>
          <th>Prioritas</th>
          <th>Jadwal</th>
          <th>Teknisi</th>
          <th>Status</th>
          <?php if (isAdmin()): ?><th style="text-align:right;">Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $m): ?>
        <tr>
          <td>
            <div class="table-item-name"><?= sanitize($m['item_name']) ?></div>
            <div class="table-item-code"><?= sanitize($m['item_code']) ?></div>
          </td>
          <td>
            <div class="table-item-name"><?= sanitize($m['title']) ?></div>
            <div class="table-item-code"><?= sanitize($m['code']) ?></div>
          </td>
          <td>
            <?php if ($m['unit_full_code']): ?>
            <span class="mono" style="font-size:0.78rem;color:var(--accent-light);background:var(--accent-glow);padding:2px 7px;border-radius:4px;">
              <?= sanitize($m['unit_full_code']) ?>
            </span>
            <?php else: ?>
            <span class="td-meta">—</span>
            <?php endif; ?>
          </td>
          <td><?= priorityBadge($m['priority']) ?></td>
          <td class="td-meta"><?= $m['scheduled_date'] ? formatDate($m['scheduled_date']) : '—' ?></td>
          <td><?= sanitize($m['technician'] ?: ($m['assigned_to_name'] ?: '—')) ?></td>
          <td><?= statusBadge($m['status']) ?></td>

          <?php if (isAdmin()): ?>
          <td style="text-align:right;">
            <div class="btn-group" style="justify-content:flex-end;gap:4px;">

              <?php if ($m['status'] === 'pending'): ?>
              <!-- Mulai Proses -->
              <form method="POST" action="action.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="action" value="start">
                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--info);border-color:var(--info);" title="Mulai Proses">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                  Proses
                </button>
              </form>
              <!-- Batalkan -->
              <form method="POST" action="action.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="button" class="btn btn-sm btn-ghost" style="color:var(--danger);" title="Batalkan"
                  onclick="confirmDelete('Batalkan pemeliharaan ini?', this.closest('form'))">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  Batalkan
                </button>
              </form>

              <?php elseif ($m['status'] === 'in_progress'): ?>
              <!-- Selesai -->
              <button type="button" class="btn btn-sm btn-outline" style="color:var(--success);border-color:var(--success);" title="Tandai Selesai"
                onclick="openSelesai(<?= $m['id'] ?>, '<?= sanitize($m['unit_full_code']) ?>', '<?= sanitize($m['title']) ?>')">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Selesai
              </button>
              <!-- Batalkan -->
              <form method="POST" action="action.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="button" class="btn btn-sm btn-ghost" style="color:var(--danger);" title="Batalkan"
                  onclick="confirmDelete('Batalkan pemeliharaan yang sedang diproses?', this.closest('form'))">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  Batalkan
                </button>
              </form>

              <?php endif; ?>

              <!-- Edit detail (selalu tampil) -->
              <a href="update.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Edit Detail">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </a>
              <!-- Hapus -->
              <form method="POST" action="delete.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger);" title="Hapus"
                  onclick="confirmDelete('Hapus catatan pemeliharaan ini?', this.closest('form'))">
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
  <?= paginate($total, $perPage, $page, '?' . http_build_query(array_filter(['search'=>$search,'status'=>$status,'priority'=>$priority]))) ?>
</div>

<!-- ── Modal: Konfirmasi Selesai ──────────────────────────────────────────── -->
<?php if (isAdmin()): ?>
<div class="modal-overlay" id="modalSelesai">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Selesaikan Pemeliharaan</h3>
      <button class="modal-close" onclick="closeModal('modalSelesai')">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <form method="POST" action="action.php">
      <input type="hidden" name="action" value="complete">
      <input type="hidden" name="id" id="selesaiId">

      <div class="modal-body">
        <p id="selesaiTitle" style="font-weight:500;margin-bottom:16px;"></p>

        <!-- Tanggal selesai + Resolusi -->
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" name="completed_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group full">
            <label class="form-label">Resolusi / Tindakan yang Dilakukan</label>
            <textarea name="resolution" class="form-control" rows="3" placeholder="Jelaskan tindakan yang sudah dilakukan..."></textarea>
          </div>
        </div>

        <!-- Hasil akhir unit (hanya tampil jika ada unit) -->
        <div id="selesaiUnitBlock" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
          <div style="font-size:0.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:12px;">
            Hasil Akhir Unit &mdash; <span id="selesaiUnitCode" style="color:var(--accent-light);font-family:monospace;"></span>
          </div>

          <!-- Berhasil diperbaiki -->
          <label id="optRepaired" class="result-opt" style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:var(--radius);border:2px solid var(--border);cursor:pointer;margin-bottom:8px;transition:all .15s;">
            <input type="radio" name="unit_result" value="repaired" checked onchange="onResultOpt()" style="margin-top:2px;accent-color:var(--success);">
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.85rem;color:var(--success);">✓ Berhasil Diperbaiki</div>
              <div style="font-size:0.77rem;color:var(--text-muted);">Unit kembali <strong>Tersedia</strong>. Pilih kondisi akhir:</div>
              <div id="condSelect" style="margin-top:8px;">
                <select name="result_condition" class="form-control" style="max-width:200px;font-size:0.83rem;">
                  <option value="good">Baik</option>
                  <option value="fair">Cukup Baik</option>
                  <option value="poor">Kurang Baik</option>
                </select>
              </div>
            </div>
          </label>

          <!-- Masih rusak -->
          <label id="optDamaged" class="result-opt" style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:var(--radius);border:2px solid var(--border);cursor:pointer;margin-bottom:8px;transition:all .15s;">
            <input type="radio" name="unit_result" value="still_damaged" onchange="onResultOpt()" style="margin-top:2px;accent-color:var(--warning);">
            <div>
              <div style="font-weight:600;font-size:0.85rem;color:var(--warning);">⚠ Masih Rusak</div>
              <div style="font-size:0.77rem;color:var(--text-muted);">Perbaikan belum berhasil. Unit tetap berstatus <strong>Rusak</strong>.</div>
            </div>
          </label>

          <!-- Dibuang -->
          <label id="optDisposed" class="result-opt" style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:var(--radius);border:2px solid var(--border);cursor:pointer;transition:all .15s;">
            <input type="radio" name="unit_result" value="disposed" onchange="onResultOpt()" style="margin-top:2px;accent-color:var(--danger);">
            <div>
              <div style="font-weight:600;font-size:0.85rem;color:var(--danger);">✕ Dibuang / Tidak Dapat Diperbaiki</div>
              <div style="font-size:0.77rem;color:var(--text-muted);">Unit rusak permanen dan akan dibuang (<strong>Disposed</strong>) secara permanen.</div>
            </div>
          </label>
        </div>
      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalSelesai')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Konfirmasi Selesai
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openSelesai(id, unitCode, title) {
  document.getElementById('selesaiId').value    = id;
  document.getElementById('selesaiTitle').textContent = title;

  const unitBlock = document.getElementById('selesaiUnitBlock');
  if (unitCode) {
    document.getElementById('selesaiUnitCode').textContent = unitCode;
    unitBlock.style.display = '';
    // Reset to default
    document.querySelector('input[name="unit_result"][value="repaired"]').checked = true;
    onResultOpt();
  } else {
    unitBlock.style.display = 'none';
  }

  // Reset textarea & date
  document.querySelector('#modalSelesai textarea[name="resolution"]').value = '';
  document.querySelector('#modalSelesai input[name="completed_date"]').value =
    new Date().toISOString().split('T')[0];

  openModal('modalSelesai');
}

function onResultOpt() {
  const val  = document.querySelector('input[name="unit_result"]:checked')?.value;
  const opts = {
    repaired:      { el: 'optRepaired', color: 'var(--success)', bg: 'rgba(16,185,129,.07)' },
    still_damaged: { el: 'optDamaged',  color: 'var(--warning)', bg: 'rgba(245,158,11,.07)' },
    disposed:      { el: 'optDisposed', color: 'var(--danger)',  bg: 'rgba(239,68,68,.07)' },
  };
  Object.entries(opts).forEach(([k, o]) => {
    const el = document.getElementById(o.el);
    if (!el) return;
    const active = (k === val);
    el.style.borderColor = active ? o.color : 'var(--border)';
    el.style.background  = active ? o.bg    : '';
  });
  const condSelect = document.getElementById('condSelect');
  if (condSelect) condSelect.style.display = (val === 'repaired') ? '' : 'none';
}

// Init highlight on page load
document.addEventListener('DOMContentLoaded', function() {
  const r = document.querySelector('input[name="unit_result"]:checked');
  if (r) onResultOpt();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
