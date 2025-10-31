<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$roles = $_SESSION['portal_user']['roles'] ?? [];
$canView = in_array('admin',(array)$roles,true) || in_array('rh_view',(array)$roles,true) || in_array('rh_operate',(array)$roles,true) || in_array('rh_manage',(array)$roles,true);
if (!$canView) { http_response_code(403); echo 'Acesso negado'; exit(); }
if (empty($_SESSION['rh_pin_ok'])) { header('Location: /portal/rh/pin.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RH - Solicitações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | Solicitações
        <div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="/portal/rh/index.php">RH</a><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a></div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="card glass mb-3">
        <div class="card-body">
          <form id="fFilter" class="row g-2 align-items-end" onsubmit="return false;">
            <div class="col-12 col-md-4"><label class="form-label">Buscar colaborador</label><input id="q" class="form-control form-control-sm" placeholder="Nome ou email"></div>
            <div class="col-6 col-md-3"><label class="form-label">Tipo</label><select id="fType" class="form-select form-select-sm"><option value="">Todos</option><option value="vacation">Férias</option><option value="medical">Atestado</option><option value="reimbursement">Reembolso</option><option value="other">Outro</option></select></div>
            <div class="col-6 col-md-3"><label class="form-label">Status</label><select id="fStatus" class="form-select form-select-sm"><option value="">Todos</option><option value="open">Aberto</option><option value="approved">Aprovado</option><option value="rejected">Rejeitado</option><option value="closed">Fechado</option></select></div>
            <div class="col-12 col-md-2 d-flex gap-2 justify-content-md-end"><button id="btnFilter" class="btn btn-outline-light btn-sm"><i class="bi bi-search"></i> Buscar</button><button id="btnNew" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo</button></div>
          </form>
        </div>
      </div>
      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Colaborador</th><th>Tipo</th><th>Período/Valor</th><th>Status</th><th>Detalhes</th><th class="text-end">Ações</th></tr></thead>
            <tbody id="rows"><tr><td colspan="7" class="text-secondary">Carregando...</td></tr></tbody>
          </table>
        </div>
      </div>
    </main>

    <!-- Modal Create/Edit -->
    <div class="modal fade" id="mReq" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content glass">
          <div class="modal-header"><h5 class="modal-title" id="mTitle">Nova Solicitação</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body">
            <form id="fReq" class="row g-2" onsubmit="return false;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="id" value="">
              <div class="col-12 col-md-6"><label class="form-label">Colaborador</label><select class="form-select" name="employee_id" id="empSel"></select></div>
              <div class="col-12 col-md-6"><label class="form-label">Tipo</label><select class="form-select" name="type"><option value="vacation">Férias</option><option value="medical">Atestado</option><option value="reimbursement">Reembolso</option><option value="other">Outro</option></select></div>
              <div class="col-6 col-md-3"><label class="form-label">Início</label><input class="form-control" name="start_date" type="date"></div>
              <div class="col-6 col-md-3"><label class="form-label">Fim</label><input class="form-control" name="end_date" type="date"></div>
              <div class="col-12 col-md-3"><label class="form-label">Valor</label><input class="form-control" name="amount" type="number" step="0.01"></div>
              <div class="col-12 col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="open">Aberto</option><option value="approved">Aprovado</option><option value="rejected">Rejeitado</option><option value="closed">Fechado</option></select></div>
              <div class="col-12"><label class="form-label">Detalhes</label><textarea class="form-control" name="details" rows="3"></textarea></div>
              <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" id="btnSave"><i class="bi bi-save"></i> Salvar</button><span id="reqMsg" class="small text-secondary"></span></div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      let modalReq;
      async function api(path){ const r=await fetch('rh_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(data){ const r=await fetch('rh_api.php',{method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      function h(s){return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      function fmtPeriod(it){
        const hasDates = (it.start_date||it.end_date);
        const hasAmount = (it.amount!=null && it.amount!=='');
        let parts=[];
        if(hasDates){ parts.push(`${h(it.start_date||'')} — ${h(it.end_date||'')}`); }
        if(hasAmount){ parts.push('R$ '+Number(it.amount).toFixed(2)); }
        return parts.length? parts.join(' | ') : '-';
      }
      async function load(){
        const tb=document.getElementById('rows');
        const q=(document.getElementById('q').value||'').trim(); const t=document.getElementById('fType').value||''; const s=document.getElementById('fStatus').value||'';
        const qp=`?action=requests_list${q?('&q='+encodeURIComponent(q)):''}${t?('&type='+encodeURIComponent(t)):''}${s?('&status='+encodeURIComponent(s)):''}`;
        try{ const d=await api(qp); const it=d.items||[];
          if(it.length===0){ tb.innerHTML='<tr><td colspan="7" class="text-secondary">Sem solicitações.</td></tr>'; return; }
          tb.innerHTML = it.map(e=>`<tr>
            <td>${e.id}</td>
            <td>${h(e.employee_name||('#'+e.employee_id))}</td>
            <td>${h(e.type||'')}</td>
            <td class="small text-secondary">${fmtPeriod(e)}</td>
            <td class="small text-secondary">${h(e.status||'')}</td>
            <td class="small" title="${h(e.details||'')}">${h((e.details||'').length>40 ? (e.details||'').slice(0,40)+'…' : (e.details||''))}</td>
            <td class="text-end d-flex justify-content-end gap-2">
              <button class="btn btn-sm btn-outline-warning" data-edit="${e.id}"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-outline-danger" data-del="${e.id}"><i class="bi bi-trash"></i></button>
            </td>
          </tr>`).join('');
        }catch(e){ tb.innerHTML='<tr><td colspan="6" class="text-danger">Falha ao carregar.</td></tr>'; }
      }
      async function loadEmployeesSelect(){ try{ const d=await api('?action=employees_list&per_page=1000'); const sel=document.getElementById('empSel'); sel.innerHTML=(d.items||[]).map(x=>`<option value="${x.id}">${h(x.name||'')}</option>`).join(''); }catch(e){} }
      function fillForm(it){ const f=document.getElementById('fReq'); f.id.value=it.id||''; f.employee_id.value=it.employee_id||''; f.type.value=it.type||'vacation'; f.start_date.value=(it.start_date||''); f.end_date.value=(it.end_date||''); f.amount.value=(it.amount!=null && it.amount!==''? it.amount : ''); f.status.value=it.status||'open'; f.details.value=(it.details||''); }

      document.getElementById('btnFilter').addEventListener('click', (ev)=>{ ev.preventDefault(); load(); });
      document.getElementById('fFilter').addEventListener('submit', (ev)=>{ ev.preventDefault(); load(); });
      document.getElementById('btnNew').addEventListener('click', async (ev)=>{ ev.preventDefault(); if(!modalReq && window.bootstrap){ modalReq=new bootstrap.Modal(document.getElementById('mReq')); } await loadEmployeesSelect(); fillForm({}); document.getElementById('mTitle').textContent='Nova Solicitação'; modalReq && modalReq.show(); });
      document.addEventListener('click', async (ev)=>{
        const ed=ev.target.closest('button[data-edit]'); const de=ev.target.closest('button[data-del]');
        if(ed){ ev.preventDefault(); const id=ed.getAttribute('data-edit'); try{ const d=await api('?action=request_get&id='+id); if(!modalReq && window.bootstrap){ modalReq=new bootstrap.Modal(document.getElementById('mReq')); } await loadEmployeesSelect(); fillForm(d.item||{}); document.getElementById('mTitle').textContent='Editar Solicitação #'+id; modalReq && modalReq.show(); }catch(e){} return; }
        if(de){ ev.preventDefault(); const id=de.getAttribute('data-del'); if(!confirm('Excluir solicitação #'+id+'?')) return; const csrf=document.querySelector('#fReq [name=csrf]').value||''; try{ const r=await post({action:'request_delete', csrf:csrf, id:id}); if(r.ok){ load(); } }catch(e){} return; }
      });
      document.getElementById('btnSave').addEventListener('click', async (ev)=>{ ev.preventDefault(); const f=document.getElementById('fReq'); const fd=new FormData(f); const data={ csrf:fd.get('csrf')||'', id:fd.get('id')||'', employee_id:fd.get('employee_id')||'', type:fd.get('type')||'', start_date:fd.get('start_date')||'', end_date:fd.get('end_date')||'', amount:fd.get('amount')||'', status:fd.get('status')||'', details:fd.get('details')||''}; const creating = !data.id; const action = creating? 'request_create':'request_update'; document.getElementById('reqMsg').textContent='Salvando...'; try{ const r=await post(Object.assign({action}, data)); if(!r.ok){ document.getElementById('reqMsg').textContent='Falha'; return; } document.getElementById('reqMsg').textContent='Salvo'; modalReq && modalReq.hide(); load(); }catch(e){ document.getElementById('reqMsg').textContent='Erro'; } });
      load();
    </script>
  </body>
</html>
