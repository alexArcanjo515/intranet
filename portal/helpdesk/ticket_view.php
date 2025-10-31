<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #<?= (int)$id ?> - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a>
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/tickets.php">Tickets</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div id="ticketCard" class="card glass mb-3 d-none">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h1 class="h5 mb-0"><span class="text-secondary">#</span><span id="tId"></span> <span id="tSubject"></span></h1>
            <div class="d-flex align-items-center gap-2">
              <span id="tPriority" class="badge"></span>
              <span id="tStatus" class="badge"></span>
            </div>
          </div>
          <div class="text-secondary small mt-1">Aberto por <span id="tRequester"></span> em <span id="tCreated"></span> • Categoria: <span id="tCategory"></span> • SLA: <span id="tSla"></span></div>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <div class="input-group input-group-sm" style="max-width: 320px;">
              <label class="input-group-text" for="assigneeSel">Atribuir</label>
              <select id="assigneeSel" class="form-select"></select>
              <button id="btnAssign" class="btn btn-outline-primary">Salvar</button>
            </div>
            <div class="input-group input-group-sm" style="max-width: 280px;">
              <label class="input-group-text" for="statusSel">Status</label>
              <select id="statusSel" class="form-select">
                <option value="open">open</option>
                <option value="in_progress">in_progress</option>
                <option value="resolved">resolved</option>
                <option value="closed">closed</option>
              </select>
              <button id="btnStatus" class="btn btn-outline-primary">Salvar</button>
            </div>
          </div>
          <div class="mt-3" id="tDesc"></div>
        </div>
      </div>

      <div class="card glass">
        <div class="card-body">
          <h2 class="h6">Comentários</h2>
          <div id="comments" class="d-flex flex-column gap-3 mb-3"></div>
          <form id="fComment" class="row g-2 align-items-center" onsubmit="return false;">
            <input type="hidden" name="ticket_id" value="<?= (int)$id ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-12">
              <div class="d-flex gap-2 align-items-start flex-wrap">
                <textarea class="form-control" name="body" rows="4" placeholder="Escreva um comentário..." style="min-width:280px;"></textarea>
                <select id="cannedHd" class="form-select form-select-sm" style="max-width:280px; min-width:220px;">
                  <option value="">Respostas rápidas...</option>
                  <option value="Obrigado, estamos analisando seu ticket e retornaremos em breve.">Agradecimento + análise</option>
                  <option value="Poderia fornecer mais detalhes, prints ou mensagens de erro?">Solicitar detalhes</option>
                  <option value="Encaminhamos ao responsável. Retornaremos assim que houver atualização.">Encaminhado</option>
                  <option value="A intervenção foi realizada. Poderia confirmar se o problema foi resolvido?">Resolvido – confirmar</option>
                  <option value="Vamos agendar um contacto para melhor entender a questão.">Agendar contacto</option>
                </select>
              </div>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="isint" name="is_internal">
                <label class="form-check-label" for="isint">Comentário interno</label>
              </div>
              <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Enviar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script>
      const ticketId = <?= (int)$id ?>;
      const CSRF = '<?= htmlspecialchars(csrf_token()) ?>';
      function h(s){return (s||'').replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function api(path){ const r=await fetch('helpdesk_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(path,data){ const r=await fetch('helpdesk_api.php'+path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }

      function badge(el, type, text){
        el.textContent = text;
        el.className = 'badge';
        if(type==='priority'){
          const map = {urgent:'bg-danger',high:'bg-warning text-dark',medium:'bg-info text-dark',low:'bg-secondary'};
          el.classList.add(map[text]||'bg-secondary');
        } else {
          const map = {open:'bg-primary',in_progress:'bg-warning text-dark',resolved:'bg-success',closed:'bg-secondary'};
          el.classList.add(map[text]||'bg-secondary');
        }
      }

      async function load(){
        const d = await api('?action=ticket_get&id='+ticketId);
        const t = d.ticket;
        document.getElementById('tId').textContent = t.id;
        document.getElementById('tSubject').textContent = ' ' + (t.subject||'');
        document.getElementById('tRequester').textContent = t.requester_name||'';
        document.getElementById('tCreated').textContent = t.created_at||'';
        document.getElementById('tDesc').textContent = t.description||'';
        document.getElementById('tCategory').textContent = t.category||'';
        document.getElementById('tSla').textContent = t.sla_due_at||'-';
        badge(document.getElementById('tPriority'), 'priority', t.priority||'medium');
        badge(document.getElementById('tStatus'), 'status', t.status||'open');
        // preencher assignee select
        try{
          const u = await api('?action=users_list');
          const sel = document.getElementById('assigneeSel');
          sel.innerHTML = '<option value="">(ninguém)</option>' + (u.items||[]).map(x=>`<option value="${x.id}">${h(x.name||'')}</option>`).join('');
          if(t.assignee_id){ sel.value = String(t.assignee_id); }
        }catch(e){}
        document.getElementById('ticketCard').classList.remove('d-none');
        const cwrap = document.getElementById('comments'); cwrap.innerHTML='';
        (d.comments||[]).forEach(c=>{
          const div = document.createElement('div');
          div.className = 'p-2 border border-secondary-subtle rounded';
          div.innerHTML = `<div class="small text-secondary d-flex justify-content-between"><span>${h(c.user||'')}</span><span>${h(c.created_at||'')}</span></div><div class="mt-1">${h(c.body||'')}</div>` + (c.is_internal?`<div class="mt-1"><span class="badge bg-secondary">interno</span></div>`:'');
          cwrap.appendChild(div);
        });
      }

      document.addEventListener('click', async (ev)=>{
        if(ev.target && ev.target.id==='btnAssign'){
          ev.preventDefault();
          const ass = document.getElementById('assigneeSel').value||'';
          try{ await post('', {action:'tickets_assign', csrf: CSRF, id: ticketId, assignee_id: ass}); await load(); }catch(e){ alert('Falha ao atribuir'); }
        }
        if(ev.target && ev.target.id==='btnStatus'){
          ev.preventDefault();
          const st = document.getElementById('statusSel').value||'';
          try{ await post('', {action:'tickets_update_status', csrf: CSRF, id: ticketId, status: st}); await load(); }catch(e){ alert('Falha ao atualizar status'); }
        }
      });

      document.getElementById('fComment').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const data = { action:'comment_add', csrf: fd.get('csrf')||'', ticket_id: ticketId, body: fd.get('body')||'', is_internal: fd.get('is_internal')?1:0 };
        if(!data.body){ return; }
        try{ await post('', data); ev.target.reset(); load(); }catch(e){ alert('Falha ao enviar'); }
      });

      document.getElementById('cannedHd').addEventListener('change', (ev)=>{
        const v = ev.target.value||''; if(!v) return; const f=document.getElementById('fComment');
        const cur = f.body.value||''; f.body.value = cur ? (cur + (cur.endsWith(' ')?'':' ') + v) : v;
        ev.target.value=''; f.body.focus();
      });

      load();
    </script>
  </body>
</html>
