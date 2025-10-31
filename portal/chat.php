<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];
$uid = (int)$me['id'];
$peerId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}
      .sidebar{width:320px;max-width:100%;}
      .msg{max-width:75%;}
      .msg.me{margin-left:auto}
      .msg .bubble{border:1px solid rgba(255,255,255,.08);padding:.5rem .75rem;border-radius:.75rem}
      .msg.me .bubble{background:rgba(25,135,84,.2);border-color:rgba(25,135,84,.35)}
      .msg.other .bubble{background:rgba(99,102,241,.12);border-color:rgba(99,102,241,.25)}
      .list-hover:hover{background:rgba(255,255,255,.06)}
      .scroll-y{overflow:auto}
      .dot{width:8px;height:8px;border-radius:999px;display:inline-block}
      .dot.online{background:#16a34a}
      .dot.offline{background:#64748b}
    </style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
     <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="./index.php"><i class="bi bi-building"></i><?php echo render_brand_html(__DIR__ . '/../assets/logotipo.png'); ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
          <a href="helpdesk/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tools"></i> Help Desk
          </a>
          <div class="position-relative">
            <a href="chat.php" class="btn btn-outline-secondary btn-sm position-relative">
              <i class="bi bi-chat-dots"></i>
              <span id="chatBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary d-none">0</span>
            </a>
          </div>
          <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm position-relative" id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell"></i>
              <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="notifBtn" style="min-width: 320px;">
              <div class="p-2 d-flex justify-content-between align-items-center border-bottom border-secondary-subtle">
                <strong class="small">Notificações</strong>
                <button class="btn btn-sm btn-link text-decoration-none" id="markAll">Marcar todas como lidas</button>
              </div>
              <div id="notifList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto">
                <div class="list-group-item bg-transparent text-secondary small">Carregando…</div>
              </div>
            </div>
          </div>
          
          <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
        </div>
      </div>
    </nav>

    <main class="container-fluid py-3 flex-grow-1 d-flex gap-3" style="min-height:0">
      <div class="glass rounded sidebar d-flex flex-column">
        <div class="p-2 border-bottom border-secondary-subtle">
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="q" class="form-control" placeholder="Procurar usuários...">
          </div>
        </div>
        <div id="users" class="scroll-y flex-grow-1"></div>
      </div>
      <div class="glass rounded flex-grow-1 d-flex flex-column">
        <div id="header" class="p-2 border-bottom border-secondary-subtle d-flex align-items-center gap-2">
          <div class="fw-semibold" id="peerName">Selecione um usuário</div>
          <div id="typing" class="small text-secondary ms-auto d-none">digitando…</div>
        </div>
        <div id="messages" class="scroll-y flex-grow-1 p-3 d-flex flex-column gap-2"></div>
        <form id="sendForm" class="p-2 border-top border-secondary-subtle d-flex flex-wrap gap-2 align-items-center" onsubmit="return false;">
          <input type="hidden" id="peerId" value="<?= $peerId ?>">
          <input id="msgBody" class="form-control" placeholder="Escreva uma mensagem..." autocomplete="off">
          <label class="btn btn-outline-secondary mb-0">
            <i class="bi bi-paperclip"></i> <input id="fileInp" type="file" class="d-none">
          </label>
          <button class="btn btn-primary"><i class="bi bi-send"></i></button>
        </form>
      </div>
    </main>

    <script>
      const U = {
        peer: <?= (int)$peerId ?>,
        lastId: 0,
      };
      const elUsers = document.getElementById('users');
      const elMsgs = document.getElementById('messages');
      const elPeer = document.getElementById('peerId');
      const elPeerName = document.getElementById('peerName');
      const elBody = document.getElementById('msgBody');
      const elTyping = document.getElementById('typing');
      const elFile = document.getElementById('fileInp');

      async function api(path){ const r = await fetch('chat_api.php'+path, {cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(path, data){ const r = await fetch('chat_api.php'+path, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function uploadFile(user, file, body=''){ const fd = new FormData(); fd.append('user', user); fd.append('file', file); if(body) fd.append('body', body); const r = await fetch('chat_upload.php', {method:'POST', body: fd}); if(!r.ok) throw new Error('upload'); return r.json(); }

      async function loadUsers(q=''){
        try{
          const data = await api('?action=list_users'+(q?'&q='+encodeURIComponent(q):''));
          elUsers.innerHTML = '';
          (data.items||[]).forEach(u=>{
            const a = document.createElement('a');
            a.href = '#'; a.className = 'd-flex align-items-center justify-content-between p-2 text-decoration-none text-light list-hover';
            a.innerHTML = `<div class=\"d-flex align-items-center gap-2\"><span class=\"dot ${u.presence==='online'?'online':'offline'}\"></span><div>${escapeHtml(u.label||('Usuário #'+u.id))}</div></div>` + (u.unread>0?`<span class=\"badge bg-danger\">${u.unread}</span>`:'');
            a.addEventListener('click', ev=>{ ev.preventDefault(); selectPeer(u.id, u.label); });
            elUsers.appendChild(a);
          });
        }catch(e){ elUsers.innerHTML = '<div class="p-2 text-secondary small">Falha ao carregar usuários.</div>'; }
      }

      function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m])); }

      async function selectPeer(id, name){
        U.peer = id; U.lastId = 0; elPeer.value = id; elPeerName.textContent = name || ('Usuário #'+id);
        elMsgs.innerHTML = '';
        await loadMessages();
        markRead();
      }

      function renderAttachment(m){
        if(!m.file_path) return null;
        const link = document.createElement('a'); link.href = m.file_path; link.target = '_blank'; link.rel = 'noopener'; link.textContent = m.file_name || 'arquivo';
        if ((m.file_type||'').startsWith('image/')){
          const img = document.createElement('img'); img.src = m.file_path; img.alt = m.file_name||''; img.style.maxWidth = '240px'; img.className='img-fluid rounded border border-secondary-subtle d-block mb-1';
          const wrap = document.createElement('div'); wrap.appendChild(img); wrap.appendChild(link); return wrap;
        }
        return link;
      }

      async function loadMessages(){
        if(!U.peer) return;
        try{
          const data = await api('?action=convo&user='+U.peer+(U.lastId?('&since_id='+U.lastId):''));
          const items = data.items||[];
          items.forEach(m=>{
            U.lastId = Math.max(U.lastId, m.id);
            const me = m.sender_id !== U.peer; // if not peer, it's me
            const wrap = document.createElement('div'); wrap.className = 'msg '+(me?'me':'other');
            const b = document.createElement('div'); b.className = 'bubble';
            const text = (m.body||''); if (text){ const span = document.createElement('div'); span.textContent = text; b.appendChild(span); }
            const att = renderAttachment(m); if(att) b.appendChild(att);
            const meta = document.createElement('div'); meta.className = 'small text-secondary mt-1'; meta.textContent = (m.created_at||'');
            wrap.appendChild(b); wrap.appendChild(meta);
            elMsgs.appendChild(wrap);
          });
          if(items.length>0){ elMsgs.scrollTop = elMsgs.scrollHeight; }
        }catch(e){ /* ignore */ }
      }

      async function sendMessage(){
        const body = elBody.value.trim(); if(!body || !U.peer) return;
        try{
          await post('?action=send', {user: U.peer, body});
          elBody.value = '';
          await loadMessages();
        }catch(e){ /* ignore */ }
      }

      async function markRead(){
        if(!U.peer) return;
        try{ await post('?action=mark_read', {user: U.peer}); }catch(e){}
      }

      // uploads
      elFile.addEventListener('change', async (ev)=>{
        const f = ev.target.files && ev.target.files[0]; if (!f || !U.peer) return;
        try{
          const body = elBody.value.trim(); elBody.value='';
          await uploadFile(U.peer, f, body);
          await loadMessages();
        }catch(e){}
        ev.target.value = '';
      });

      // typing
      let typingT = null; function pingTyping(){ if(!U.peer) return; post('?action=typing_set', {user: U.peer}).catch(()=>{}); }
      elBody.addEventListener('input', ()=>{ clearTimeout(typingT); typingT = setTimeout(pingTyping, 100); });
      async function pollTyping(){ if (!U.peer) return; try{ const d = await api('?action=typing_get&user='+U.peer); if (d.typing){ elTyping.classList.remove('d-none'); } else { elTyping.classList.add('d-none'); } }catch(e){} }

      // presence
      async function pingPresence(){ try{ await api('?action=presence_ping'); }catch(e){} }

      document.getElementById('sendForm').addEventListener('submit', (ev)=>{ ev.preventDefault(); sendMessage(); });
      document.getElementById('q').addEventListener('input', (ev)=>{ loadUsers(ev.target.value||''); });

      loadUsers('');
      if (U.peer>0) { // preselect from query
        (async()=>{
          try{
            const data = await api('?action=list_users&q=');
            const found = (data.items||[]).find(x=>x.id===U.peer);
            selectPeer(U.peer, found?found.label:('Usuário #'+U.peer));
          }catch(e){}
        })();
      }
      setInterval(()=>{ loadMessages(); if(document.hasFocus()) markRead(); pollTyping(); }, 2000);
      setInterval(()=>{ loadUsers(''); pingPresence(); }, 10000);
    </script>
  </body>
</html>
