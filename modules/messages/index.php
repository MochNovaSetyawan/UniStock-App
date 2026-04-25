<?php
// ============================================
// UNISTOCK - Personal Messages
// ============================================
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle   = 'Pesan';
$me          = (int)$_SESSION['user_id'];
$db          = getDB();
$withId      = (int)($_GET['with'] ?? 0);

// ── Daftar percakapan ─────────────────────────────────────────────────────────
$convStmt = $db->prepare("
    SELECT
        u.id, u.full_name, u.role, u.department,
        lm.message    AS last_message,
        lm.from_user_id AS last_from,
        DATE_FORMAT(lm.created_at, '%d %b %H:%i') AS last_time,
        (SELECT COUNT(*) FROM messages
         WHERE from_user_id = u.id AND to_user_id = :me AND is_read = 0) AS unread
    FROM (
        SELECT DISTINCT
            CASE WHEN from_user_id = :me2 THEN to_user_id ELSE from_user_id END AS partner_id
        FROM messages
        WHERE from_user_id = :me3 OR to_user_id = :me4
    ) AS pairs
    JOIN users u ON u.id = pairs.partner_id
    JOIN messages lm ON lm.id = (
        SELECT id FROM messages
        WHERE (from_user_id = :me5 AND to_user_id = u.id)
           OR (from_user_id = u.id AND to_user_id = :me6)
        ORDER BY id DESC LIMIT 1
    )
    ORDER BY lm.created_at DESC
");
$convStmt->execute([
    ':me' => $me, ':me2' => $me, ':me3' => $me,
    ':me4' => $me, ':me5' => $me, ':me6' => $me,
]);
$conversations = $convStmt->fetchAll();

// ── Percakapan aktif ──────────────────────────────────────────────────────────
$activePeer   = null;
$initMessages = [];
$lastMsgId    = 0;

if ($withId) {
    $peerStmt = $db->prepare("SELECT id, full_name, role, department FROM users WHERE id = ? AND is_active = 1");
    $peerStmt->execute([$withId]);
    $activePeer = $peerStmt->fetch();

    if ($activePeer) {
        // Tandai pesan masuk sebagai terbaca
        $db->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0")
           ->execute([$withId, $me]);

        // Ambil 60 pesan terakhir
        $msgStmt = $db->prepare("
            SELECT m.id, m.from_user_id, m.message,
                   DATE_FORMAT(m.created_at, '%d %b %Y %H:%i') AS created_at,
                   (m.from_user_id = :me) AS from_me
            FROM messages m
            WHERE (m.from_user_id = :me2 AND m.to_user_id = :peer)
               OR (m.from_user_id = :peer2 AND m.to_user_id = :me3)
            ORDER BY m.id DESC
            LIMIT 60
        ");
        $msgStmt->execute([':me' => $me, ':me2' => $me, ':me3' => $me, ':peer' => $withId, ':peer2' => $withId]);
        $initMessages = array_reverse($msgStmt->fetchAll());
        $lastMsgId    = !empty($initMessages) ? (int)end($initMessages)['id'] : 0;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Pesan</h2>
    <p class="page-subtitle">Komunikasi personal antar pengguna</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('composeModal')">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
    Pesan Baru
  </button>
</div>

<div class="chat-layout">

  <!-- ── Daftar Percakapan ──────────────────────────────────────────────── -->
  <div class="conv-panel" id="convPanel">
    <div class="conv-search">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="convSearch" placeholder="Cari percakapan..." oninput="filterConvs(this.value)">
    </div>

    <div class="conv-list" id="convList">
      <?php if (empty($conversations)): ?>
      <div class="conv-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
        <span>Belum ada percakapan</span>
      </div>
      <?php else: foreach ($conversations as $c):
        $isActive = ($withId === (int)$c['id']);
        $initials = strtoupper(substr($c['full_name'], 0, 1));
        $lastMsg  = strlen($c['last_message']) > 40 ? substr($c['last_message'], 0, 40) . '...' : $c['last_message'];
        $fromMe   = ((int)$c['last_from'] === $me);
      ?>
      <a href="<?= APP_URL ?>/modules/messages/index.php?with=<?= $c['id'] ?>"
         class="conv-item <?= $isActive ? 'active' : '' ?>"
         data-name="<?= strtolower(sanitize($c['full_name'])) ?>">
        <div class="conv-avatar"><?= $initials ?></div>
        <div class="conv-info">
          <div class="conv-name">
            <?= sanitize($c['full_name']) ?>
            <?php if ($c['unread'] > 0): ?>
              <span class="conv-badge"><?= $c['unread'] ?></span>
            <?php endif; ?>
          </div>
          <div class="conv-last">
            <?= $fromMe ? 'Anda: ' : '' ?><?= sanitize($lastMsg) ?>
          </div>
        </div>
        <div class="conv-time"><?= $c['last_time'] ?></div>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- ── Area Chat ──────────────────────────────────────────────────────── -->
  <div class="chat-panel" id="chatPanel">

    <?php if ($activePeer): ?>
    <!-- Chat Header -->
    <div class="chat-header">
      <div class="chat-peer-avatar"><?= strtoupper(substr($activePeer['full_name'], 0, 1)) ?></div>
      <div class="chat-peer-info">
        <div class="chat-peer-name"><?= sanitize($activePeer['full_name']) ?></div>
        <div class="chat-peer-role"><?= sanitize($activePeer['department'] ?? ucfirst($activePeer['role'])) ?></div>
      </div>
      <div class="chat-status" id="chatStatus">
        <span class="chat-status-dot"></span> Online
      </div>
    </div>

    <!-- Messages Area -->
    <div class="chat-messages" id="chatMessages">
      <?php if (empty($initMessages)): ?>
      <div class="chat-no-msg">Belum ada pesan. Mulai percakapan!</div>
      <?php else: foreach ($initMessages as $msg):
        $fromMe = ((int)$msg['from_me'] === 1);
      ?>
      <div class="msg-row <?= $fromMe ? 'sent' : 'received' ?>" data-id="<?= $msg['id'] ?>">
        <?php if (!$fromMe): ?>
        <div class="msg-avatar"><?= strtoupper(substr($activePeer['full_name'], 0, 1)) ?></div>
        <?php endif; ?>
        <div class="msg-bubble">
          <div class="msg-text"><?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?></div>
          <div class="msg-time"><?= $msg['created_at'] ?></div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Input Bar -->
    <div class="chat-input-bar">
      <textarea id="chatInput" class="chat-textarea" placeholder="Ketik pesan..." rows="1"
                onkeydown="chatKeydown(event)" oninput="autoResize(this)"></textarea>
      <button class="chat-send-btn" onclick="sendMessage()" title="Kirim (Enter)">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/></svg>
      </button>
    </div>

    <?php else: ?>
    <!-- No conversation selected -->
    <div class="chat-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
      <h3>Pilih percakapan</h3>
      <p>Klik nama di daftar kiri atau mulai pesan baru</p>
      <button class="btn btn-primary" style="margin-top:16px;" onclick="openModal('composeModal')">
        Mulai Pesan Baru
      </button>
    </div>
    <?php endif; ?>

  </div><!-- end .chat-panel -->
</div><!-- end .chat-layout -->

<!-- ── Modal Compose ──────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="composeModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">Pesan Baru</h3>
      <button class="modal-close" onclick="closeModal('composeModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Kirim ke</label>
        <input type="text" id="composeSearch" class="form-control" placeholder="Cari nama pengguna..."
               oninput="searchUsers(this.value)" autocomplete="off">
        <div id="userSuggest" style="display:none; background:var(--bg-elevated); border:1px solid var(--border); border-radius:var(--radius-sm); margin-top:4px; max-height:200px; overflow-y:auto;"></div>
        <input type="hidden" id="composeToId">
        <div id="composeToName" style="display:none; margin-top:8px; padding:8px 12px; background:var(--accent-glow); border-radius:var(--radius-sm); font-size:0.83rem; color:var(--accent-light);"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Pesan</label>
        <textarea id="composeMsg" class="form-control" rows="4" placeholder="Tulis pesan..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('composeModal')">Batal</button>
      <button class="btn btn-primary" onclick="sendCompose()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/></svg>
        Kirim
      </button>
    </div>
  </div>
</div>

<script>
const APP_URL   = '<?= APP_URL ?>';
const PEER_ID   = <?= $withId ?: 0 ?>;
let   lastMsgId = <?= $lastMsgId ?>;
let   pollTimer = null;

// ── Auto-scroll ke bawah saat halaman load ────────────────────────────────
(function() {
  const box = document.getElementById('chatMessages');
  if (box) box.scrollTop = box.scrollHeight;
})();

// ── Polling: ambil pesan baru setiap 3 detik ─────────────────────────────
function startPolling() {
  if (!PEER_ID) return;
  pollTimer = setInterval(fetchNewMessages, 3000);
}

function fetchNewMessages() {
  fetch(`${APP_URL}/modules/messages/poll.php?with=${PEER_ID}&after=${lastMsgId}`)
    .then(r => r.json())
    .then(data => {
      if (!data.ok) return;
      if (data.messages && data.messages.length) {
        appendMessages(data.messages);
      }
      // Update global unread badge di header
      if (typeof updateMsgBadge === 'function') updateMsgBadge(data.unread_msgs);
    })
    .catch(() => {});
}

function appendMessages(msgs) {
  const box = document.getElementById('chatMessages');
  if (!box) return;
  const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;

  msgs.forEach(m => {
    lastMsgId = Math.max(lastMsgId, m.id);
    const row = document.createElement('div');
    row.className = 'msg-row ' + (m.from_me ? 'sent' : 'received');
    row.dataset.id = m.id;

    let avatarHtml = '';
    if (!m.from_me) {
      const init = '<?= strtoupper(substr($activePeer['full_name'] ?? 'U', 0, 1)) ?>';
      avatarHtml = `<div class="msg-avatar">${init}</div>`;
    }
    row.innerHTML = `
      ${avatarHtml}
      <div class="msg-bubble">
        <div class="msg-text">${m.message.replace(/\n/g,'<br>')}</div>
        <div class="msg-time">${m.created_at}</div>
      </div>`;

    // Hapus placeholder "belum ada pesan" jika ada
    const noMsg = box.querySelector('.chat-no-msg');
    if (noMsg) noMsg.remove();

    box.appendChild(row);
  });

  if (atBottom) box.scrollTop = box.scrollHeight;
}

// ── Kirim pesan ───────────────────────────────────────────────────────────
function sendMessage() {
  const input = document.getElementById('chatInput');
  const text  = input.value.trim();
  if (!text || !PEER_ID) return;

  input.value = '';
  input.style.height = 'auto';

  fetch(`${APP_URL}/modules/messages/send.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `to_user_id=${PEER_ID}&message=${encodeURIComponent(text)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      appendMessages([data]);
      lastMsgId = Math.max(lastMsgId, data.id);
    }
  })
  .catch(() => {});
}

function chatKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── Filter daftar percakapan ──────────────────────────────────────────────
function filterConvs(q) {
  document.querySelectorAll('.conv-item').forEach(item => {
    const name = item.dataset.name || '';
    item.style.display = name.includes(q.toLowerCase()) ? '' : 'none';
  });
}

// ── Compose: cari user ────────────────────────────────────────────────────
let searchTimer;
function searchUsers(q) {
  clearTimeout(searchTimer);
  const suggest = document.getElementById('userSuggest');
  searchTimer = setTimeout(() => {
    fetch(`${APP_URL}/modules/messages/get_users.php?q=${encodeURIComponent(q)}`)
      .then(r => r.json())
      .then(users => {
        if (!users.length) { suggest.style.display = 'none'; return; }
        suggest.innerHTML = users.map(u => `
          <div onclick="selectUser(${u.id}, '${u.full_name.replace(/'/g,"\\'")}', '${u.role}')"
               style="padding:10px 14px; cursor:pointer; display:flex; align-items:center; gap:10px; transition:background .15s;"
               onmouseover="this.style.background='var(--bg-card-hover)'" onmouseout="this.style.background=''">
            <div style="width:30px;height:30px;background:var(--accent-glow);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:var(--accent-light);flex-shrink:0;">
              ${u.full_name.charAt(0).toUpperCase()}
            </div>
            <div>
              <div style="font-size:0.85rem;font-weight:500;">${u.full_name}</div>
              <div style="font-size:0.75rem;color:var(--text-muted);">${u.department || u.role}</div>
            </div>
          </div>`).join('');
        suggest.style.display = 'block';
      });
  }, 250);
}

function selectUser(id, name, role) {
  document.getElementById('composeToId').value   = id;
  document.getElementById('composeSearch').value = name;
  document.getElementById('composeToName').textContent = 'Kirim ke: ' + name;
  document.getElementById('composeToName').style.display = 'block';
  document.getElementById('userSuggest').style.display   = 'none';
}

function sendCompose() {
  const toId = document.getElementById('composeToId').value;
  const msg  = document.getElementById('composeMsg').value.trim();
  if (!toId) { alert('Pilih penerima terlebih dahulu.'); return; }
  if (!msg)  { alert('Pesan tidak boleh kosong.'); return; }

  fetch(`${APP_URL}/modules/messages/send.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `to_user_id=${toId}&message=${encodeURIComponent(msg)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      closeModal('composeModal');
      window.location.href = `${APP_URL}/modules/messages/index.php?with=${toId}`;
    } else {
      alert(data.error || 'Gagal mengirim pesan.');
    }
  });
}

// ── Mulai polling ─────────────────────────────────────────────────────────
startPolling();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
