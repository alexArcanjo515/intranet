<?php
session_start(); if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit; }
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/security_headers.php';
?><!doctype html>
<html lang="pt-br" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RH | Inbox de Solicitações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<nav class="navbar navbar-expand-lg glass">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/rh/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | Inbox
    <div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="/portal/rh/index.php">RH</a><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a></div>
  </div>
</nav>
<main class="container py-4">
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card glass">
        <div class="card-body">
          <form id="fFilter" class="row g-2 align-items-end" onsubmit="return false;">
            <div class="col-12"><label class="form-label">Buscar</label><input class="form-control form-control-sm" id="q" placeholder="Nome ou email"></div>
            <div class="col-6 col-md-4"><label class="form-label">Tipo</label>
              <select class="form-select form-select-sm" id="type">
                <option value="" selected>Todos</option>
                <option value="vacation">Férias/Ausência</option>
                <option value="medical">Atestado</option>
                <option value="reimbursement">Reembolso</option>
                <option value="other">Outros</option>
              </select>
            </div>
            <div class="col-6 col-md-4"><label class="form-label">Status</label>
              <select class="form-select form-select-sm" id="status">
                <option value="" selected>Todos</option>
                <option value="pending">Pendente</option>
                <option value="in_progress">Em andamento</option>
                <option value="approved">Aprovado</option>
                <option value="rejected">Rejeitado</option>
                <option value="done">Concluído</option>
              </select>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2"><button class="btn btn-outline-light btn-sm" id="btnSearch"><i class="bi bi-search"></i> Buscar</button></div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Colaborador</th><th>Tipo</th><th>Assunto</th><th>Status</th><th>Data</th></tr></thead>
            <tbody id="rows"><tr><td colspan="6" class="text-secondary">Carregando...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-7">
      <div class="card glass">
        <div class="card-header"><strong>Detalhes</strong></div>
        <div class="card-body">
          <div id="detailEmpty" class="text-secondary">Selecione uma solicitação</div>
          <div id="detail" class="d-none">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small text-secondary">Colaborador</div>
                <div><strong id="dEmp"></strong></div>
              </div>
              <div>
                <div class="small text-secondary">Status</div>
                <div>
                  <select id="dStatus" class="form-select form-select-sm" style="min-width:160px">
                    <option value="pending">Pendente</option>
                    <option value="in_progress">Em andamento</option>
                    <option value="approved">Aprovado</option>
                    <option value="rejected">Rejeitado</option>
                    <option value="done">Concluído</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="mt-3"><div class="small text-secondary">Assunto</div><div id="dSubject" class="border rounded p-2 bg-white text-dark"></div></div>
            <div class="mt-3"><div class="small text-secondary">Descrição</div><div id="dDetails" class="border rounded p-2 bg-white text-dark"></div></div>
            <div class="mt-3"><div class="small text-secondary">Criado em</div><div id="dCreated"></div></div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary btn-sm" id="btnSave"><i class="bi bi-save"></i> Guardar</button>
              <button class="btn btn-outline-secondary btn-sm" id="btnRefresh"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
            </div>
            <hr>
            <div><strong>Comentários</strong></div>
            <div id="comments" class="border rounded bg-white text-dark p-2" style="max-height:40vh; overflow:auto;"></div>
            <form id="fComment" class="mt-2 d-flex flex-wrap gap-2 align-items-center" onsubmit="return false;">
              <div class="flex-grow-1 d-flex gap-2 align-items-center" style="min-width:260px;">
                <input class="form-control" name="body" placeholder="Responder...">
              </div>
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
function h(s){return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));}
async function api(url){ const sep=url.includes('?')?'&':'?'; const r=await fetch(url+sep+'_ts='+(Date.now()), {cache:'no-store'}); if(!r.ok) throw new Error('net'); return await r.json(); }
async function post(data){ const fd=new FormData(); for(const k in data) fd.append(k, data[k]); const r=await fetch('/portal/rh/rh_api.php?_ts='+(Date.now()),{method:'POST', body:fd, cache:'no-store'}); if(!r.ok) throw new Error('net'); return await r.json(); }

let selId=null; let selEmpName=''; let refreshTimer=null;

async function loadList(){
  const tb=document.getElementById('rows'); tb.innerHTML='<tr><td colspan="6" class="text-secondary">Carregando...</td></tr>';
  const q = encodeURIComponent(document.getElementById('q').value||'');
  const type = encodeURIComponent(document.getElementById('type').value||'');
  const status = encodeURIComponent(document.getElementById('status').value||'');
  try{
    const d=await api('/portal/rh/rh_api.php?action=requests_list&q='+q+'&type='+type+'&status='+status);
    const rows=(d.items||[]).map(it=>{
      const colab = (it.employee_name && it.employee_name.trim()) ? it.employee_name : (it.requester_user_name||'');
      return `<tr data-id="${it.id}" data-emp="${h(colab)}"><td>${it.id}</td><td>${h(colab)}</td><td>${h(it.type||'')}</td><td>${h(it.subject||'')}</td><td>${h(it.status||'')}</td><td>${h(it.created_at||'')}</td></tr>`;
    }).join('');
    tb.innerHTML= rows||'<tr><td colspan="6" class="text-secondary">Sem resultados.</td></tr>';
  }catch(e){ tb.innerHTML='<tr><td colspan="6" class="text-danger">Falha.</td></tr>'; }
}

async function loadDetail(id){
  selId=id; document.getElementById('detailEmpty').classList.add('d-none'); document.getElementById('detail').classList.remove('d-none');
  try{
    const d=await api('/portal/rh/rh_api.php?action=request_get&id='+id);
    const it=d.item||{}; const colab = (it.employee_name && it.employee_name.trim()) ? it.employee_name : (it.requester_user_name||''); selEmpName = colab || (document.querySelector('tr[data-id="'+id+'"]')?.getAttribute('data-emp')||'');
    document.getElementById('dEmp').textContent = selEmpName;
    document.getElementById('dSubject').textContent = it.subject||'';
    document.getElementById('dDetails').textContent = it.details||'';
    document.getElementById('dCreated').textContent = it.created_at||'';
    document.getElementById('dStatus').value = it.status||'pending';
  }catch(e){}
  await loadComments(id);
  if (refreshTimer) { clearInterval(refreshTimer); }
  refreshTimer = setInterval(async ()=>{ if(!selId) return; try{ await loadDetailOnce(selId); await loadComments(selId); }catch(e){} }, 5000);
}

async function loadDetailOnce(id){
  try{
    const d=await api('/portal/rh/rh_api.php?action=request_get&id='+id);
    const it=d.item||{}; const colab = (it.employee_name && it.employee_name.trim()) ? it.employee_name : (it.requester_user_name||''); selEmpName = colab || (document.querySelector('tr[data-id="'+id+'"]')?.getAttribute('data-emp')||'');
    document.getElementById('dEmp').textContent = selEmpName;
    document.getElementById('dSubject').textContent = it.subject||'';
    document.getElementById('dDetails').textContent = it.details||'';
    document.getElementById('dCreated').textContent = it.created_at||'';
    document.getElementById('dStatus').value = it.status||'pending';
  }catch(e){}
}

async function loadComments(id){
  const box=document.getElementById('comments'); box.innerHTML='Carregando...';
  try{
    const d=await api('/portal/rh/rh_api.php?action=request_comments_list&request_id='+id);
    box.innerHTML=(d.items||[]).map(c=>`<div class="mb-2"><div class="small text-secondary">${h(c.user_name||('#'+c.user_id))} • ${h(c.created_at||'')}</div><div>${h(c.body||'')}</div></div>`).join('');
  }catch(e){ box.innerHTML='<span class="text-danger">Falha.</span>'; }
}

document.getElementById('btnSearch').addEventListener('click', (ev)=>{ev.preventDefault(); loadList();});

document.getElementById('rows').addEventListener('click', (ev)=>{
  const tr=ev.target.closest('tr[data-id]'); if(!tr) return; loadDetail(Number(tr.getAttribute('data-id')));
});

document.getElementById('btnRefresh').addEventListener('click', (ev)=>{ ev.preventDefault(); if(selId) loadDetail(selId); });

document.getElementById('btnSave').addEventListener('click', async (ev)=>{
  ev.preventDefault(); if(!selId) return; const csrf='<?php echo htmlspecialchars(csrf_token()); ?>';
  try{ const r=await post({action:'request_update', csrf:csrf, id:String(selId), status:document.getElementById('dStatus').value}); if(!r.ok){ alert('Falha'); return;} await loadDetailOnce(selId); await loadComments(selId); }catch(e){ alert('Erro ao guardar.'); }
});

document.getElementById('btnComment').addEventListener('click', async (ev)=>{
  ev.preventDefault(); if(!selId) return; const f=document.getElementById('fComment'); const body=f.body.value.trim(); if(!body) return; const csrf=f.csrf.value;
  try{ const r=await post({action:'request_comment_add', csrf:csrf, request_id:String(selId), body}); if(!r.ok){ alert('Falha'); return;} f.reset(); await loadComments(selId); }catch(e){ alert('Erro ao comentar.'); }
});



loadList();
</script>
</body>
</html>
