  </main><!-- end .page-content -->

  <footer style="padding: 16px 28px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
    <span style="font-size: 0.75rem; color: var(--text-muted);">
      &copy; <?= date('Y') ?> <?= sanitize(getSetting('university_name', 'Universitas Nusantara')) ?> &mdash; <?= sanitize(getSetting('app_name', 'Unistock')) ?> v<?= getSetting('app_version', '1.0.0') ?>
    </span>
    <span style="font-size: 0.75rem; color: var(--text-muted);">
      <?= sanitize($_SESSION['user_name'] ?? '') ?> &bull; <?= date('d M Y, H:i') ?>
    </span>
  </footer>

</div><!-- end .main-content -->
</div><!-- end .app-layout -->

<!-- ═══════════════════════════════════════════════════════
     LINKEDIN-STYLE FLOATING CHAT WIDGET
     ═══════════════════════════════════════════════════════ -->
<?php if (isLoggedIn()): ?>
<div id="cwRoot">

  <!-- Bubble stack (opens to the left, filled by JS) -->
  <div id="cwBubbles"></div>

  <!-- Conversation Panel -->
  <div id="cwPanel">
    <div class="cwp-header">
      <span class="cwp-title">Pesan</span>
      <div style="display:flex;gap:6px;align-items:center;">
        <button class="cwp-icon-btn" onclick="cwOpenCompose()" title="Pesan baru">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
        </button>
        <button class="cwp-icon-btn" onclick="cwTogglePanel()" title="Tutup">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>
    <div class="cwp-search">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="cwSearch" placeholder="Cari percakapan..." oninput="cwFilterConvs(this.value)" autocomplete="off">
    </div>
    <div class="cwp-list" id="cwConvList">
      <div class="cwp-loading">Memuat...</div>
    </div>
  </div>

  <!-- Compose overlay (inside panel) -->
  <div id="cwCompose" style="display:none; position:absolute; inset:0; background:var(--bg-card); border-radius:12px; flex-direction:column; z-index:1;">
    <div class="cwp-header">
      <button class="cwp-icon-btn" onclick="cwCloseCompose()" style="margin-right:6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
      </button>
      <span class="cwp-title">Pesan Baru</span>
    </div>
    <div style="padding:12px 14px; border-bottom:1px solid var(--border);">
      <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Ke:</div>
      <input type="text" id="cwComposeSearch" class="cwp-compose-input" placeholder="Cari nama..."
             oninput="cwSearchUsers(this.value)" autocomplete="off">
      <div id="cwUserList" style="max-height:180px; overflow-y:auto; margin-top:4px;"></div>
    </div>
    <textarea id="cwComposeMsg" class="cwp-compose-textarea" placeholder="Tulis pesan pertama..."></textarea>
    <div style="padding:10px 14px; border-top:1px solid var(--border); display:flex; justify-content:flex-end;">
      <button class="cw-send-btn" style="border-radius:20px; padding:7px 20px; font-size:0.8rem;" onclick="cwSendCompose()">Kirim</button>
    </div>
  </div>

</div><!-- end #cwRoot -->
<?php endif; ?>

<!-- Global Search Results -->
<div id="searchResults" style="
  position: fixed; top: 72px; left: 0;
  width: 420px; background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow); z-index: 500;
  display: none; max-height: 400px; overflow-y: auto;
"></div>

<script>
// ============================================
// UNISTOCK - Main JavaScript
// ============================================

// ---- SIDEBAR: restore preference ----
(function() {
  if (localStorage.getItem('sidebarUnpinned') === 'true') {
    document.body.classList.add('sidebar-unpinned');
  }
})();

// ---- SIDEBAR: hover management (debounced) ----
(function() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const pinBtn   = document.getElementById('sidebarPinBtn');
  if (!sidebar || window.innerWidth <= 768) return;

  let hideTimer;

  function isSidebarHoverArea(target) {
    return !!(target && (sidebar.contains(target) || (pinBtn && pinBtn.contains(target))));
  }

  function showSidebar(event) {
    if (!document.body.classList.contains('sidebar-unpinned')) return;
    // Don't open sidebar when hovering only the pin button while unpinned
    if (pinBtn && event) {
      var r = pinBtn.getBoundingClientRect();
      if (event.clientX >= r.left && event.clientX <= r.right &&
          event.clientY >= r.top  && event.clientY <= r.bottom) return;
    }
    clearTimeout(hideTimer);
    document.body.classList.add('sidebar-hovering');
  }

  function hideSidebar(event) {
    clearTimeout(hideTimer);
    if (isSidebarHoverArea(event && event.relatedTarget)) return;
    hideTimer = setTimeout(function() {
      document.body.classList.remove('sidebar-hovering');
    }, 80);
  }

  sidebar.addEventListener('mouseenter', showSidebar);
  sidebar.addEventListener('mouseleave', hideSidebar);

  // Klik overlay → tutup sidebar hover
  if (overlay) {
    overlay.addEventListener('click', function() {
      clearTimeout(hideTimer);
      document.body.classList.remove('sidebar-hovering');
    });
  }
})();

// ---- SIDEBAR: pin / unpin (smooth, no grid-reflow jitter) ----
function toggleSidebarPin() {
  var DURATION = 300; // harus >= durasi CSS transition (0.28s)

  // 1. Kunci semua grid containers di computed column count sebelum layout berubah
  var grids = document.querySelectorAll(
    '.stats-grid, .dashboard-grid, .grid-2, .grid-3, .form-grid, .form-grid-2, .form-grid-3'
  );
  grids.forEach(function(g) {
    var cols = getComputedStyle(g).gridTemplateColumns;
    if (cols && cols !== 'none') g.style.gridTemplateColumns = cols;
  });

  // 2. Mark transisi aktif → CSS mematikan secondary transitions & contain layout
  document.body.classList.add('sidebar-transitioning');

  // 3. Toggle state
  var unpinned = document.body.classList.toggle('sidebar-unpinned');
  localStorage.setItem('sidebarUnpinned', unpinned);
  if (unpinned) {
    document.body.classList.remove('sidebar-hovering');
  }

  // 4. Setelah transisi selesai: unlock grid dan cabut class
  setTimeout(function() {
    grids.forEach(function(g) { g.style.gridTemplateColumns = ''; });
    document.body.classList.remove('sidebar-transitioning');
  }, DURATION);
}

// ---- SIDEBAR: animasi tutup saat nav link diklik (hover mode) ----
(function() {
  document.querySelectorAll('#sidebar .nav-item').forEach(function(link) {
    link.addEventListener('click', function(e) {
      if (!document.body.classList.contains('sidebar-unpinned')) return;
      if (!document.body.classList.contains('sidebar-hovering')) return;
      e.preventDefault();
      var href = this.href;
      document.body.classList.remove('sidebar-hovering');
      setTimeout(function() { window.location.href = href; }, 270);
    });
  });
})();

// Mobile sidebar toggle (menu-toggle button)
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// Close mobile sidebar on outside click
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.querySelector('.menu-toggle');
  if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
    if (!sidebar.contains(e.target) && (!toggle || !toggle.contains(e.target))) {
      sidebar.classList.remove('open');
    }
  }
});

// Dropdown toggle
function toggleDropdown(id) {
  const el = document.getElementById(id);
  const isOpen = el.classList.contains('open');
  document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  if (!isOpen) el.classList.add('open');
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  }
});

// Modal helpers
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('active');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('active');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
  }
});

// Global search (debounced)
let searchTimer;
function globalSearchHandler(q) {
  clearTimeout(searchTimer);
  const box   = document.getElementById('searchResults');
  const input = document.getElementById('globalSearch');
  if (q.length < 2) { box.style.display = 'none'; return; }

  // Position dropdown below the input, aligned to its left edge
  function positionDropdown() {
    const rect = input.getBoundingClientRect();
    box.style.top   = (rect.bottom + 6) + 'px';
    box.style.left  = rect.left + 'px';
    box.style.width = Math.max(rect.width, 360) + 'px';
  }

  searchTimer = setTimeout(() => {
    fetch('<?= APP_URL ?>/includes/search.php?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        if (!data.length) {
          box.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.83rem;">Tidak ada hasil ditemukan</div>';
        } else {
          box.innerHTML = data.map(item => `
            <a href="${item.url}" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text-primary);transition:background 0.15s;">
              <div style="width:36px;height:36px;background:var(--accent-glow);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.7rem;font-weight:700;color:var(--accent-light);">${item.code?.substring(0,4) || '??'}</div>
              <div>
                <div style="font-size:0.85rem;font-weight:500;">${item.name}</div>
                <div style="font-size:0.75rem;color:var(--text-muted);">${item.meta || ''}</div>
              </div>
            </a>
          `).join('');
        }
        positionDropdown();
        box.style.display = 'block';
      })
      .catch(() => { box.style.display = 'none'; });
  }, 300);
}

// Close search on outside click
document.addEventListener('click', function(e) {
  const box = document.getElementById('searchResults');
  const input = document.getElementById('globalSearch');
  if (!box.contains(e.target) && e.target !== input) {
    box.style.display = 'none';
  }
});

// Confirm delete helper
function confirmDelete(msg, form) {
  if (confirm(msg || 'Yakin ingin menghapus data ini?')) {
    form.submit();
  }
}

// Auto-dismiss alerts
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity 0.5s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 500);
  });
}, 5000);

// Table row click to navigate (if data-href exists)
document.querySelectorAll('tr[data-href]').forEach(row => {
  row.style.cursor = 'pointer';
  row.addEventListener('click', function(e) {
    if (!e.target.closest('a, button, .dropdown')) {
      window.location.href = this.dataset.href;
    }
  });
});

// ============================================================
// UNISTOCK REALTIME — reminder checker + message badge poller
// ============================================================
const _APP_URL = '<?= APP_URL ?>';
let _notifKnownCount = <?= (int)countUnreadNotifications() ?>;

// ── Escape HTML untuk rendering JS ───────────────────────────
function _esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Notification: refresh dropdown list ──────────────────────
function refreshNotifDropdown(notifs, count) {
  _notifKnownCount = count;
  updateNotifBadge(count);
  const list = document.getElementById('notifList');
  if (!list) return;
  if (!notifs || !notifs.length) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.8rem;">Tidak ada notifikasi baru</div>';
    const btn = document.querySelector('#notifDropdown .dropdown-menu button');
    if (btn) btn.style.display = 'none';
    return;
  }
  const typeColors = {warning:'var(--warning)',danger:'var(--danger)',success:'var(--success)',info:'var(--info)'};
  list.innerHTML = notifs.map(n => {
    const dot  = typeColors[n.type] || 'var(--accent)';
    const href = n.link ? _esc(n.link) : '#';
    return `<a href="${href}" onclick="markOneRead(${parseInt(n.id)},event)"
       class="dropdown-item notif-item" style="flex-direction:column;align-items:flex-start;gap:3px;">
      <div style="display:flex;align-items:center;gap:8px;width:100%;">
        <span style="width:7px;height:7px;border-radius:50%;background:${dot};flex-shrink:0;"></span>
        <span style="font-size:0.83rem;font-weight:500;color:var(--text-primary);flex:1;">${_esc(n.title)}</span>
      </div>
      <span style="font-size:0.75rem;color:var(--text-muted);padding-left:15px;">${_esc(n.message)}</span>
    </a>`;
  }).join('');
  const btn = document.querySelector('#notifDropdown .dropdown-menu button');
  if (btn) btn.style.display = '';
}

// ── Notification: mark one read ──────────────────────────────
function markOneRead(id, e) {
  fetch(_APP_URL + '/includes/mark_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + id
  }).then(r => r.json()).then(data => {
    const c = data.unread || 0;
    _notifKnownCount = c;
    updateNotifBadge(c);
  }).catch(() => {});
  // tidak preventDefault — ikuti href link
}

// ── Notification: mark all read ──────────────────────────────
function markAllRead() {
  fetch(_APP_URL + '/includes/mark_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=0'
  }).then(r => r.json()).then(() => {
    refreshNotifDropdown([], 0);
  }).catch(() => {});
}

function updateNotifBadge(count) {
  const badge = document.getElementById('notifBadge');
  const dot   = document.getElementById('notifDot');
  if (badge) { badge.textContent = count; badge.style.display = count > 0 ? '' : 'none'; }
  if (dot)   { dot.style.display = count > 0 ? '' : 'none'; }
}

// ── Message badge updater ─────────────────────────────────────
function updateMsgBadge(count) {
  const dot = document.getElementById('msgDot');
  if (dot) dot.style.display = count > 0 ? '' : 'none';
}

// ── Global poll: pesan + notifikasi setiap 5 detik ───────────
(function startGlobalPoll() {
  function poll() {
    if (window.PEER_ID !== undefined) return; // halaman full-page chat handle sendiri
    fetch(_APP_URL + '/modules/messages/poll.php?counts_only=1')
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        updateMsgBadge(data.unread_msgs || 0);
        const nc = data.unread_notifs || 0;
        if (nc !== _notifKnownCount) {
          refreshNotifDropdown(data.notifications || [], nc);
        }
      })
      .catch(() => {});
  }
  setInterval(poll, 5000);
})();

// ── Reminder checker: 3 detik setelah load, lalu setiap 90 detik ─────────────
(function startReminderCheck() {
  function check() {
    fetch(_APP_URL + '/includes/check_reminders.php')
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        if (data.created > 0 || data.unread !== _notifKnownCount) {
          refreshNotifDropdown(data.notifications || [], data.unread || 0);
        }
      })
      .catch(() => {});
  }
  setTimeout(check, 3000);
  setInterval(check, 90000);
})();

// ═══════════════════════════════════════════════════════════
// CHAT WIDGET — LinkedIn-style
// ═══════════════════════════════════════════════════════════
const CW = {
  panelOpen: false,
  bubbles:   [],       // [{userId,name,initials,lastId,minimized}]
  maxBubbles: 3,
  pollTimer: null,
  convData: [],
};

// ── Toggle panel ────────────────────────────────────────────
function cwTogglePanel() {
  const panel = document.getElementById('cwPanel');
  if (!panel) return;
  CW.panelOpen = !CW.panelOpen;
  if (CW.panelOpen) {
    panel.classList.add('cw-open');
    cwLoadConversations();
  } else {
    panel.classList.remove('cw-open');
  }
}

// ── Load conversation list ───────────────────────────────────
function cwLoadConversations() {
  fetch(_APP_URL + '/modules/messages/conversations.php')
    .then(r => r.json())
    .then(data => {
      CW.convData = data.conversations || [];
      cwRenderConvList(CW.convData);
    })
    .catch(() => {});
}

function cwRenderConvList(convs) {
  const list = document.getElementById('cwConvList');
  if (!list) return;
  if (!convs.length) {
    list.innerHTML = '<div class="cwp-empty"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg><span>Belum ada percakapan</span></div>';
    return;
  }
  list.innerHTML = convs.map(c => {
    const lastMsg = c.last_message.length > 38 ? c.last_message.substring(0,38)+'...' : c.last_message;
    return `<div class="cwp-item" onclick="cwOpenBubble(${c.id},'${c.full_name.replace(/'/g,"\\'")}','${c.initials}')">
      <div class="cwp-avatar">${c.initials}</div>
      <div class="cwp-info">
        <div class="cwp-name">${c.full_name}${c.unread>0?`<span class="cwp-badge">${c.unread}</span>`:''}</div>
        <div class="cwp-last">${c.from_me?'Anda: ':''}${lastMsg}</div>
      </div>
      <div class="cwp-time">${c.last_time}</div>
    </div>`;
  }).join('');
}

function cwFilterConvs(q) {
  const filtered = q
    ? CW.convData.filter(c => c.full_name.toLowerCase().includes(q.toLowerCase()))
    : CW.convData;
  cwRenderConvList(filtered);
}

// ── Open a chat bubble ───────────────────────────────────────
function cwOpenBubble(userId, name, initials) {
  userId = parseInt(userId);
  const existing = CW.bubbles.find(b => b.userId === userId);
  if (existing) {
    existing.minimized = false;
    cwRenderBubbles();
    cwScrollBubble(userId);
    return;
  }
  if (CW.bubbles.length >= CW.maxBubbles) CW.bubbles.shift();
  CW.bubbles.push({ userId, name, initials, lastId: 0, minimized: false });
  cwRenderBubbles();
  cwLoadBubbleMsgs(userId);
  // Close panel
  CW.panelOpen = false;
  document.getElementById('cwPanel').classList.remove('cw-open');
}

function cwCloseBubble(userId) {
  CW.bubbles = CW.bubbles.filter(b => b.userId !== userId);
  cwRenderBubbles();
}

function cwMinimizeBubble(userId) {
  const b = CW.bubbles.find(b => b.userId === userId);
  if (b) { b.minimized = !b.minimized; cwRenderBubbles(); }
}

// ── Render all bubbles ───────────────────────────────────────
function cwRenderBubbles() {
  const root = document.getElementById('cwBubbles');
  if (!root) return;
  root.innerHTML = '';
  CW.bubbles.forEach((b, i) => {
    const div = document.createElement('div');
    div.className = 'cw-bubble' + (b.minimized ? ' minimized' : '');
    div.id = 'cwb-' + b.userId;
    div.innerHTML = `
      <div class="cwb-header" onclick="cwMinimizeBubble(${b.userId})">
        <div class="cwb-hinfo">
          <div class="cwb-avatar">${b.initials}</div>
          <span class="cwb-name">${b.name}</span>
        </div>
        <div style="display:flex;gap:4px;align-items:center;">
          <span class="cwb-unread-badge" id="cwbadge-${b.userId}" style="display:none;"></span>
          <button class="cwb-btn" onclick="event.stopPropagation();cwMinimizeBubble(${b.userId})" title="${b.minimized?'Perluas':'Perkecil'}">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="${b.minimized?'M4.5 15.75l7.5-7.5 7.5 7.5':'M19.5 8.25l-7.5 7.5-7.5-7.5'}"/></svg>
          </button>
          <button class="cwb-btn" onclick="event.stopPropagation();cwCloseBubble(${b.userId})" title="Tutup">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <div class="cwb-body">
        <div class="cwb-msgs" id="cwmsgs-${b.userId}">
          <div style="padding:16px;text-align:center;color:var(--text-muted);font-size:0.8rem;">Memuat...</div>
        </div>
        <div class="cwb-input-row">
          <input type="text" class="cwb-input" id="cwinput-${b.userId}"
                 placeholder="Ketik pesan..."
                 onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();cwSendMsg(${b.userId});}">
          <button class="cw-send-btn" onclick="cwSendMsg(${b.userId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/></svg>
          </button>
        </div>
      </div>`;
    root.appendChild(div);
  });
}

// ── Load messages into bubble ────────────────────────────────
function cwLoadBubbleMsgs(userId) {
  fetch(_APP_URL + '/modules/messages/load.php?with=' + userId)
    .then(r => r.json())
    .then(data => {
      if (!data.ok) return;
      const b = CW.bubbles.find(b => b.userId === userId);
      if (b) b.lastId = data.last_id;
      cwRenderMsgs(userId, data.messages, false);
      cwScrollBubble(userId);
      updateMsgBadge(0); // after reading, update global
    })
    .catch(() => {});
}

function cwRenderMsgs(userId, msgs, append) {
  const box = document.getElementById('cwmsgs-' + userId);
  if (!box) return;
  const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
  const html = msgs.map(m => `
    <div class="cwm-row ${m.from_me?'sent':'received'}">
      <div class="cwm-bubble">
        <div class="cwm-text">${m.message.replace(/\n/g,'<br>')}</div>
        <div class="cwm-time">${m.created_at}</div>
      </div>
    </div>`).join('');
  if (append) {
    if (!msgs.length) return;
    box.insertAdjacentHTML('beforeend', html);
  } else {
    box.innerHTML = html || '<div style="padding:16px;text-align:center;color:var(--text-muted);font-size:0.8rem;">Mulai percakapan!</div>';
  }
  if (!append || atBottom) box.scrollTop = box.scrollHeight;
}

function cwScrollBubble(userId) {
  setTimeout(() => {
    const box = document.getElementById('cwmsgs-' + userId);
    if (box) box.scrollTop = box.scrollHeight;
  }, 50);
}

// ── Send message from bubble ─────────────────────────────────
function cwSendMsg(userId) {
  const input = document.getElementById('cwinput-' + userId);
  const text  = input ? input.value.trim() : '';
  if (!text) return;
  input.value = '';

  fetch(_APP_URL + '/modules/messages/send.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'to_user_id=' + userId + '&message=' + encodeURIComponent(text)
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) return;
    const b = CW.bubbles.find(b => b.userId === userId);
    if (b) b.lastId = Math.max(b.lastId, data.id);
    cwRenderMsgs(userId, [data], true);
  })
  .catch(() => {});
}

// ── Poll for new messages in open bubbles ────────────────────
function cwPoll() {
  if (!CW.bubbles.length) return;
  CW.bubbles.forEach(b => {
    if (b.minimized) return;
    fetch(`${_APP_URL}/modules/messages/poll.php?with=${b.userId}&after=${b.lastId}`)
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        if (data.messages && data.messages.length) {
          data.messages.forEach(m => { b.lastId = Math.max(b.lastId, m.id); });
          const incoming = data.messages.filter(m => !m.from_me);
          if (incoming.length) {
            cwRenderMsgs(b.userId, incoming, true);
            // Refresh panel conversation list agar last_message terupdate
            if (CW.panelOpen) cwLoadConversations();
          }
        }
        updateMsgBadge(data.unread_msgs || 0);
        // Sinkron notif jika berubah
        if (typeof data.unread_notifs !== 'undefined' && data.unread_notifs !== _notifKnownCount) {
          _notifKnownCount = data.unread_notifs;
          updateNotifBadge(data.unread_notifs);
        }
      })
      .catch(() => {});
  });
}

// ── Compose (new message) ─────────────────────────────────────
function cwOpenCompose() {
  document.getElementById('cwCompose').style.display = 'flex';
  document.getElementById('cwComposeSearch').focus();
}
function cwCloseCompose() {
  document.getElementById('cwCompose').style.display = 'none';
  document.getElementById('cwUserList').innerHTML = '';
  document.getElementById('cwComposeSearch').value = '';
  document.getElementById('cwComposeMsg').value = '';
  CW._composeTo = null;
}

let _cwUserTimer;
function cwSearchUsers(q) {
  clearTimeout(_cwUserTimer);
  _cwUserTimer = setTimeout(() => {
    fetch(_APP_URL + '/modules/messages/get_users.php?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(users => {
        const ul = document.getElementById('cwUserList');
        if (!users.length) { ul.innerHTML = ''; return; }
        ul.innerHTML = users.map(u => `
          <div onclick="cwSelectComposeUser(${u.id},'${u.full_name.replace(/'/g,"\\'")}')"
               style="padding:8px 12px;cursor:pointer;display:flex;align-items:center;gap:10px;border-radius:6px;transition:background .15s;"
               onmouseover="this.style.background='var(--bg-elevated)'" onmouseout="this.style.background=''">
            <div class="cwp-avatar" style="width:30px;height:30px;font-size:0.75rem;">${u.full_name.charAt(0).toUpperCase()}</div>
            <div><div style="font-size:0.83rem;font-weight:500;">${u.full_name}</div>
            <div style="font-size:0.72rem;color:var(--text-muted);">${u.department||u.role}</div></div>
          </div>`).join('');
      });
  }, 200);
}

function cwSelectComposeUser(id, name) {
  CW._composeTo = { id, name };
  document.getElementById('cwComposeSearch').value = name;
  document.getElementById('cwUserList').innerHTML   = '';
  document.getElementById('cwComposeMsg').focus();
}

function cwSendCompose() {
  if (!CW._composeTo) { alert('Pilih penerima terlebih dahulu.'); return; }
  const msg = document.getElementById('cwComposeMsg').value.trim();
  if (!msg) { alert('Pesan tidak boleh kosong.'); return; }

  // Simpan sebelum cwCloseCompose() menghapus _composeTo
  const toId       = parseInt(CW._composeTo.id);
  const toName     = CW._composeTo.name;
  const toInitials = toName.charAt(0).toUpperCase();

  fetch(_APP_URL + '/modules/messages/send.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'to_user_id=' + toId + '&message=' + encodeURIComponent(msg)
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.error||'Gagal.'); return; }
    cwCloseCompose();

    // Buka bubble — jika sudah ada, pakai yang existing
    const existing = CW.bubbles.find(b => b.userId === toId);
    if (existing) {
      existing.minimized = false;
      cwRenderBubbles();
    } else {
      if (CW.bubbles.length >= CW.maxBubbles) CW.bubbles.shift();
      CW.bubbles.push({ userId: toId, name: toName, initials: toInitials, lastId: 0, minimized: false });
      cwRenderBubbles();
    }
    // Tutup panel
    CW.panelOpen = false;
    const panel = document.getElementById('cwPanel');
    if (panel) panel.classList.remove('cw-open');

    // Tampilkan pesan yang baru dikirim langsung tanpa reload
    const sentMsg = {
      id:         data.id,
      from_me:    true,
      message:    data.message,
      created_at: data.created_at
    };
    const b = CW.bubbles.find(b => b.userId === toId);
    if (b) b.lastId = data.id;
    cwRenderMsgs(toId, [sentMsg], !!existing); // append jika bubble sudah ada, replace jika baru
    cwScrollBubble(toId);
    if (CW.panelOpen) cwLoadConversations();
  });
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  // Tombol chat di topbar
  const cwBtn = document.getElementById('cwTopbarBtn');
  if (cwBtn) {
    cwBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      cwTogglePanel();
    });
  }

  // Ketika dropdown notifikasi dibuka → refresh list dari server
  const notifBell = document.querySelector('#notifDropdown > .topbar-btn');
  if (notifBell) {
    notifBell.addEventListener('click', function() {
      fetch(_APP_URL + '/modules/messages/poll.php?counts_only=1')
        .then(r => r.json())
        .then(data => {
          if (!data.ok) return;
          refreshNotifDropdown(data.notifications || [], data.unread_notifs || 0);
        })
        .catch(() => {});
    });
  }

  // Poll chat bubble setiap 4 detik
  if (document.getElementById('cwPanel')) {
    setInterval(cwPoll, 4000);
  }
});
</script>
</body>
</html>
