<?php
session_start(); if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit; }
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/security_headers.php';
?><!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minhas Solicitações RH</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>.glass{border:1px solid rgba(0,0,0,.15);background:rgba(255,255,255,.7);backdrop-filter:blur(8px)}</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/index.php"><?php echo render_brand_html(__DIR__ . '/../assets/logotipo.png'); ?></a> | Minhas Solicitações RH
    <div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a></div>
  </div>
</nav>
<main class="container py-4">
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card glass">
        <div class="card-header d-flex align-items-center justify-content-between"><strong>Nova Solicitação</strong></div>
        <div class="card-body">
          <form id="fNew" class="row g-2" onsubmit="return false;">
            <div class="col-12">
              <label class="form-label">Tipo</label>
              <select class="form-select" name="type" required>
                <option value="" selected>(selecione)</option>
                <option value="vacation">Férias/Ausência</option>
                <option value="medical">Atestado/Justificativa</option>
                <option value="reimbursement">Reembolso</option>
                <option value="other">Outros</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Assunto</label>
              <input class="form-control" name="subject" placeholder="Resumo do pedido">
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" name="details" rows="4"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button id="btnCreate" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
            </div>
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          </form>
        </div>
      </div>
      <div class="card glass mt-3">
        <div class="card-header d-flex align-items-center justify-content-between"><strong>Minhas Solicitações</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>#</th><th>Tipo</th><th>Assunto</th><th>Status</th><th>Data</th></tr></thead>
              <tbody id="rows"><tr><td colspan="5" class="text-secondary">Carregando...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-7">
      <div class="card glass">
        <div class="card-header d-flex justify-content-between align-items-center"><strong>Detalhes</strong><button id="btnRefresh" class="btn btn-sm btn-outline-secondary">Atualizar</button></div>
        <div class="card-body">
          <div id="detailEmpty" class="text-secondary">Selecione uma solicitação para ver detalhes e conversar com a RH.</div>
          <div id="detail" class="d-none">
            <div class="mb-2"><strong id="dSubject"></strong></div>
            <div class="small text-secondary">Tipo: <span id="dType"></span> • Status: <span id="dStatus" class="badge bg-secondary"></span> • Criado em: <span id="dCreated"></span></div>
            <div class="mt-3"><strong>Descrição</strong><div id="dDetails" class="border rounded p-2 bg-white"></div></div>
            <hr>
            <div><strong>Comentários</strong></div>
            <div id="comments" class="border rounded bg-white p-2" style="max-height:40vh; overflow:auto;"></div>
            <form id="fComment" class="mt-2 d-flex gap-2" onsubmit="return false;">
              <input class="form-control" name="body" placeholder="Escreva uma mensagem...">
              <button class="btn btn-outline-primary" id="btnComment"><i class="bi bi-chat"></i> Enviar</button>
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedId = null; let detailTimer = null;
function h(s){return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));}
async function api(url){
  const sep = url.includes('?') ? '&' : '?';
  const r=await fetch(url+sep+'_ts='+(Date.now()), {cache:'no-store'});
  let data=null; try{ data=await r.json(); }catch(e){}
  if(!r.ok || (data && data.ok===false)){
    const msg = (data && data.error) ? data.error : ('HTTP '+r.status);
    const err = new Error(msg); err.code=r.status; throw err;
  }
  return data;
}
async function post(url, fd){
  const sep = url.includes('?') ? '&' : '?';
  const r=await fetch(url+sep+'_ts='+(Date.now()),{method:'POST',body:fd, cache:'no-store'});
  let data=null; try{ data=await r.json(); }catch(e){}
  if(!r.ok || (data && data.ok===false)){
    const msg = (data && data.error) ? data.error : ('HTTP '+r.status);
    const err = new Error(msg); err.code=r.status; throw err;
  }
  return data;
}
async function loadList(){
  const tb=document.getElementById('rows');
  try{
    const d=await api('/portal/requests_api.php?action=my_requests_list');
    const rows=(d.items||[]).map(it=>`<tr data-id="${it.id}"><td>${it.id}</td><td>${h(it.type||'')}</td><td>${h(it.subject||'')}</td><td>${h(it.status||'')}</td><td>${h(it.created_at||'')}</td></tr>`).join('');
    tb.innerHTML = rows||'<tr><td colspan="5" class="text-secondary">Sem dados.</td></tr>';
  }catch(e){ tb.innerHTML='<tr><td colspan="5" class="text-danger">Falha: '+h(e.message||'erro')+'</td></tr>'; }
}
async function loadDetail(id){
  selectedId = id;
  document.getElementById('detailEmpty').classList.add('d-none');
  document.getElementById('detail').classList.remove('d-none');
  try{
    const d=await api('/portal/requests_api.php?action=my_request_get&id='+id);
    const it=d.item||{};
    document.getElementById('dSubject').textContent = it.subject||'(sem assunto)';
    document.getElementById('dType').textContent = it.type||'';
    const st=document.getElementById('dStatus'); st.textContent=it.status||''; st.className='badge '+(it.status==='pending'?'bg-secondary':(it.status==='in_progress'?'bg-warning':(it.status==='done'?'bg-success':'bg-secondary')));
    document.getElementById('dCreated').textContent = it.created_at||'';
    document.getElementById('dDetails').textContent = it.details||'';
  }catch(e){}
  await loadComments(id);
  if (detailTimer) { clearInterval(detailTimer); }
  detailTimer = setInterval(async ()=>{ if(!selectedId) return; try{ await loadDetailOnce(selectedId); await loadComments(selectedId); }catch(e){} }, 5000);
}

async function loadDetailOnce(id){
  try{
    const d=await api('/portal/requests_api.php?action=my_request_get&id='+id);
    const it=d.item||{};
    document.getElementById('dSubject').textContent = it.subject||'(sem assunto)';
    document.getElementById('dType').textContent = it.type||'';
    const st=document.getElementById('dStatus'); st.textContent=it.status||''; st.className='badge '+(it.status==='pending'?'bg-secondary':(it.status==='in_progress'?'bg-warning':(it.status==='done'?'bg-success':'bg-secondary')));
    document.getElementById('dCreated').textContent = it.created_at||'';
    document.getElementById('dDetails').textContent = it.details||'';
  }catch(e){}
}
async function loadComments(id){
  const box=document.getElementById('comments'); box.innerHTML='Carregando...';
  try{
    const d=await api('/portal/requests_api.php?action=my_request_comments_list&request_id='+id);
    box.innerHTML=(d.items||[]).map(c=>`<div class="mb-2"><div class="small text-secondary">${h(c.user_name||('#'+c.user_id))} • ${h(c.created_at||'')}</div><div>${h(c.body||'')}</div></div>`).join('');
  }catch(e){ box.innerHTML='<span class="text-danger">Falha.</span>'; }
}

document.getElementById('btnCreate').addEventListener('click', async (ev)=>{
  ev.preventDefault();
  const f=document.getElementById('fNew'); const fd=new FormData(f);
  try{ const r=await post('/portal/requests_api.php?action=my_request_create', fd); if(!r.ok){ alert('Falha: '+(r.error||'')); return;} f.reset(); await loadList(); }catch(e){ alert('Erro ao criar: '+(e.message||'falha')); }
});

document.getElementById('rows').addEventListener('click', (ev)=>{
  const tr=ev.target.closest('tr[data-id]'); if(!tr) return; loadDetail(Number(tr.getAttribute('data-id')));
});

document.getElementById('btnRefresh').addEventListener('click', async (ev)=>{ ev.preventDefault(); if(!selectedId) return; await loadDetailOnce(selectedId); await loadComments(selectedId); });

document.getElementById('btnComment').addEventListener('click', async (ev)=>{
  ev.preventDefault(); if(!selectedId) return;
  const f=document.getElementById('fComment'); const fd=new FormData(f); fd.append('request_id', String(selectedId));
  try{ const r=await post('/portal/requests_api.php?action=my_request_comment_add', fd); if(!r.ok){ alert('Falha'); return;} f.reset(); await loadComments(selectedId); }catch(e){ alert('Erro ao comentar.'); }
});

loadList();
</script>
</body>
</html>
