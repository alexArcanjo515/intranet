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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RH - Dossiê do Colaborador #<?= (int)$id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/rh/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | Dossiê
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/rh/employees.php">Colaboradores</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
          <a class="btn btn-outline-danger btn-sm" href="/portal/rh/pin_logout.php">Sair do RH</a>
        </div>
        
      </div>
    </nav>

    <main class="container py-4">
      <div id="empCard" class="card glass mb-3 d-none">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h1 class="h5 mb-0"><span class="text-secondary">#</span><span id="eId"></span> <span id="eName"></span></h1>
            <span id="eStatus" class="badge"></span>
          </div>
          <div class="text-secondary small mt-1">Email: <span id="eEmail"></span> • Depto: <span id="eDept"></span> • Cargo: <span id="eRole"></span></div>
        </div>
      </div>

      <ul class="nav nav-tabs mb-3" id="tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-basic" data-bs-toggle="tab" data-bs-target="#pane-basic" type="button" role="tab">Dados básicos</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-docs" data-bs-toggle="tab" data-bs-target="#pane-docs" type="button" role="tab">Documentos</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-pii" data-bs-toggle="tab" data-bs-target="#pane-pii" type="button" role="tab">PII</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-salary" data-bs-toggle="tab" data-bs-target="#pane-salary" type="button" role="tab">Salários</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-pay" data-bs-toggle="tab" data-bs-target="#pane-pay" type="button" role="tab">Pagamentos</button>
        </li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-basic" role="tabpanel">
          <div class="row g-3">
            <div class="col-12 col-lg-6">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Dados básicos</h2>
              <form id="fBasic" class="row g-2" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <div class="col-12"><label class="form-label">Nome</label><input class="form-control" name="name"></div>
                <div class="col-12"><label class="form-label">Email</label><input class="form-control" name="email"></div>
                <div class="col-12 col-md-6"><label class="form-label">Telefone</label><input class="form-control" name="phone" id="phone" placeholder="(+244) 9xx xxx xxx" inputmode="tel"></div>
                <div class="col-12 col-md-6"><label class="form-label">Departamento</label><select class="form-select" name="dept_id" id="dept"></select></div>
                <div class="col-12 col-md-6"><label class="form-label">Cargo</label><select class="form-select" name="role_id" id="role"></select></div>
                <div class="col-12 col-md-6"><label class="form-label">Gestor (user_id)</label><input class="form-control" name="manager_user_id" type="number" min="0"></div>
                <div class="col-12 col-md-6"><label class="form-label">Status</label>
                  <select class="form-select" name="status">
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
                    <option value="terminated">Desligado</option>
                  </select>
                </div>
                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-primary btn-sm" id="btnSave"><i class="bi bi-save"></i> Salvar</button>
                  <span id="basicMsg" class="small text-secondary"></span>
                </div>
              </form>
            </div>
          </div>
            </div>
          </div>
        </div>
        </div>
        <div class="tab-pane fade" id="pane-docs" role="tabpanel">
          <div class="card glass">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Documentos</h2>
                <div class="d-flex gap-2">
                  <button class="btn btn-outline-secondary btn-sm" id="btnNewDoc"><i class="bi bi-file-earmark-plus"></i> Novo</button>
                </div>
              </div>
              <form id="fDoc" class="row g-2 mb-3" enctype="multipart/form-data" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="employee_id" value="<?= (int)$id ?>">
                <div class="col-12 col-md-5"><input class="form-control" name="title" placeholder="Título do documento"></div>
                <div class="col-12 col-md-3"><select name="type" class="form-select"><option value="document">Documento</option><option value="policy">Política</option></select></div>
                <div class="col-12 col-md-4 d-flex gap-2">
                  <input class="form-control" name="file" type="file" accept="application/pdf,image/*">
                  <button class="btn btn-outline-primary btn-sm" id="btnUpload"><i class="bi bi-upload"></i></button>
                </div>
                <div class="col-12"><span id="docMsg" class="small text-secondary"></span></div>
              </form>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead><tr><th>#</th><th>Título</th><th>Tipo</th><th>Data</th><th class="text-end">Ações</th></tr></thead>
                  <tbody id="docRows"><tr><td colspan="5" class="text-secondary">Sem documentos.</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="pane-pii" role="tabpanel">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6 mb-2">Dados PII</h2>
              <div id="piiNote" class="small text-warning mb-2">Criptografia não configurada. Exibindo dados mascarados.</div>
              <div class="row g-2">
                <div class="col-12 col-md-4"><label class="form-label">Documento</label><input class="form-control" id="piiDoc" disabled></div>
                <div class="col-12 col-md-4"><label class="form-label">Telefone</label><input class="form-control" id="piiPhone" disabled></div>
                <div class="col-12 col-md-4"><label class="form-label">Endereço</label><input class="form-control" id="piiAddr" disabled></div>
              </div>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="pane-salary" role="tabpanel">
          <div class="card glass">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Salários</h2>
              </div>
              <form id="fSalary" class="row g-2 mb-3" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="employee_id" value="<?= (int)$id ?>">
                <div class="col-12 col-md-4"><label class="form-label">Valor</label><input class="form-control" name="amount" id="salAmount" placeholder="0,00"><div class="small text-secondary mt-1" id="amtPreview"></div></div>
                <div class="col-6 col-md-3"><label class="form-label">Moeda</label>
                  <select class="form-select" name="currency">
                    <option value="AOA" selected>AOA</option>
                    <option value="BRL">BRL</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                  </select>
                </div>
                <div class="col-6 col-md-3"><label class="form-label">Válido a partir</label><input class="form-control" name="valid_from" type="date"></div>
                <div class="col-12 col-md-2 d-flex align-items-end"><button id="btnAddSalary" class="btn btn-outline-primary w-100"><i class="bi bi-plus"></i> Adicionar</button></div>
                <div class="col-12"><span id="salMsg" class="small text-secondary"></span></div>
              </form>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead><tr><th>#</th><th>Vigência</th><th>Moeda</th><th>Valor</th></tr></thead>
                  <tbody id="salRows"><tr><td colspan="4" class="text-secondary">Carregando...</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="pane-pay" role="tabpanel">
          <div class="card glass">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Pagamentos</h2>
              </div>
              <form id="fPayCtl" class="row g-2 mb-3" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="col-6 col-md-3"><label class="form-label">Ano</label><input class="form-control" id="payYear" type="number" min="2000" max="2100"></div>
                <div class="col-6 col-md-3"><label class="form-label">Moeda</label>
                  <select class="form-select" id="payCurrency">
                    <option value="AOA" selected>AOA</option>
                    <option value="BRL">BRL</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                  </select>
                </div>
                <div class="col-6 col-md-3"><label class="form-label">Status</label>
                  <select class="form-select" id="payFilter">
                    <option value="all" selected>Todos</option>
                    <option value="paid">Pago</option>
                    <option value="pending">Pendente</option>
                  </select>
                </div>
                <div class="col-6 col-md-3 d-flex align-items-end justify-content-end gap-2">
                  <button class="btn btn-outline-secondary btn-sm" id="btnPayExport"><i class="bi bi-download"></i> Exportar CSV</button>
                  <button class="btn btn-primary btn-sm" id="btnPayReceipt"><i class="bi bi-printer"></i> Recibo (A4)</button>
                </div>
                <div class="col-12 d-flex align-items-center"><span id="payMsg" class="small text-secondary"></span></div>
              </form>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead><tr><th>Mês</th><th>Status</th><th>Pago em</th><th>Valor</th><th class="text-end">Ações</th></tr></thead>
                  <tbody id="payRows"><tr><td colspan="5" class="text-secondary">Carregando...</td></tr></tbody>
                  <tfoot>
                    <tr><th colspan="3" class="text-end">Total do ano</th><th id="payTotal" colspan="2" class="text-start">—</th></tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      const empId = <?= (int)$id ?>;
      async function api(path){ const r=await fetch('rh_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(data){ const r=await fetch('rh_api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      function h(s){return (s||'').replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}

      async function loadBasic(){
        const d = await api('?action=employee_get&id='+empId);
        const e = d.employee||{};
        document.getElementById('eId').textContent = e.id;
        document.getElementById('eName').textContent = ' ' + (e.name||'');
        document.getElementById('eEmail').textContent = e.email||'';
        document.getElementById('eDept').textContent = e.dept_name||'';
        document.getElementById('eRole').textContent = e.role_name||'';
        const b = document.getElementById('eStatus'); b.textContent = e.status||'active'; b.className='badge '+(e.status==='active'?'bg-success':(e.status==='inactive'?'bg-secondary':'bg-danger'));
        document.getElementById('empCard').classList.remove('d-none');
        const f = document.getElementById('fBasic');
        f.name.value = e.name||''; f.email.value = e.email||''; if(f.phone){ f.phone.value = formatAOAPhone(e.phone||''); } f.status.value = e.status||'active';
      }

      let lastPayments = {year: null, items: [], total: null};
      async function loadPayments(){
        const year = Number(document.getElementById('payYear').value)||new Date().getFullYear();
        const tb=document.getElementById('payRows');
        try{
          const d=await api('?action=payments_list&employee_id='+empId+'&year='+year);
          lastPayments.year = year; lastPayments.items = Array.isArray(d.items)? d.items : []; lastPayments.total = (typeof d.total_amount!== 'undefined')? d.total_amount : null;
          renderPaymentsTable();
        }catch(e){ tb.innerHTML='<tr><td colspan="5" class="text-danger">Falha ao carregar.</td></tr>'; }
      }

      function renderPaymentsTable(){
        const year = lastPayments.year || new Date().getFullYear();
        const filter = (document.getElementById('payFilter').value||'all');
        const tb=document.getElementById('payRows');
        const map = new Map(); (lastPayments.items||[]).forEach(r=>{ map.set(Number(r.month), r); });
        const months=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        const rows=[];
        for(let m=1;m<=12;m++){
          const r = map.get(m)||{status:'pending'};
          const status = r.status||'pending';
          if (filter!=='all' && status!==filter) continue;
          const paidAt = r.paid_at||'';
          const val = r.amount_masked||'';
          const who = (r.updated_by_name && r.updated_by_name.trim())? r.updated_by_name : (r.updated_by?('#'+r.updated_by):'n/d');
          const tip = paidAt? (`Atualizado por ${who} em ${paidAt}`) : '';
          rows.push(`<tr>
            <td class="small text-secondary">${months[m-1]} / ${year}</td>
            <td>${status==='paid'?'<span class="badge bg-success">Pago</span>':'<span class="badge bg-secondary">Pendente</span>'}</td>
            <td class="small text-secondary" ${tip?`data-bs-toggle="tooltip" title="${h(tip)}"`:''}>${h(paidAt||'')}</td>
            <td data-edit="amount" data-month="${m}" class="${status==='paid'?'':'text-secondary'}">${h(val||'')}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-success me-1" data-pay="paid" data-month="${m}"><i class="bi bi-cash-coin"></i> Pagar</button>
              <button class="btn btn-sm btn-outline-warning" data-pay="pending" data-month="${m}"><i class="bi bi-arrow-counterclockwise"></i> Pendente</button>
            </td>
          </tr>`);
        }
        tb.innerHTML = rows.join('') || '<tr><td colspan="5" class="text-secondary">Sem registros para o filtro.</td></tr>';
        // Total do ano
        const totEl = document.getElementById('payTotal');
        if (lastPayments.total!==null){
          try{
            const cur = document.getElementById('payCurrency').value||'AOA';
            const s = new Intl.NumberFormat(navigator.language||'pt-PT',{style:'currency', currency:cur}).format(Number(lastPayments.total));
            totEl.textContent = s;
          }catch(e){ totEl.textContent = String(lastPayments.total); }
        } else {
          totEl.textContent = '—';
        }
        // init tooltips
        if (window.bootstrap){ document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{ try{ new bootstrap.Tooltip(el); }catch(e){} }); }
      }

      function onlyDigits(s){ return String(s||'').replace(/\D+/g,''); }
      function formatAOAPhone(v){
        let s = String(v||'').trim();
        if (s.startsWith('+244')) s = s.slice(4);
        else if (s.startsWith('244')) s = s.slice(3);
        s = onlyDigits(s);
        // mantém no máximo 9 dígitos nacionais
        if (s.length > 9) s = s.slice(-9);
        if (s.length <= 3) return s;
        if (s.length <= 6) return s.slice(0,3)+' '+s.slice(3);
        return s.slice(0,3)+' '+s.slice(3,6)+' '+s.slice(6);
      }
      const phoneEl = document.getElementById('phone');
      if (phoneEl){
        phoneEl.addEventListener('input', (e)=>{ const pos = e.target.selectionStart; e.target.value = formatAOAPhone(e.target.value); e.target.setSelectionRange(pos,pos); });
        phoneEl.addEventListener('blur', (e)=>{ e.target.value = formatAOAPhone(e.target.value); });
      }

      async function loadPII(){
        try{ const d = await api('?action=employee_pii_get&employee_id='+empId); const it=d.item||{}; document.getElementById('piiDoc').value = it.doc_id||'•••••'; document.getElementById('piiPhone').value = it.phone||'•••••'; document.getElementById('piiAddr').value = it.address||'•••••'; if(d.masked!==true){ document.getElementById('piiNote').classList.add('d-none'); } }catch(e){}
      }
      async function loadSalary(){
        const tb=document.getElementById('salRows');
        try{ const d=await api('?action=employee_salary_list&employee_id='+empId); const it=d.items||[]; if(it.length===0){ tb.innerHTML='<tr><td colspan="4" class="text-secondary">Sem registros.</td></tr>'; return; } tb.innerHTML=it.map(r=>`<tr><td>${r.id||''}</td><td class="small text-secondary">${(r.valid_from||'')+(r.valid_to?(' — '+r.valid_to):'')}</td><td class="small text-secondary">${h(r.currency||'')}</td><td>${h(r.amount_masked||'')}</td></tr>`).join(''); }catch(e){ tb.innerHTML='<tr><td colspan="4" class="text-danger">Falha ao carregar.</td></tr>'; }
      }

      function parseAmount(str){ if(!str) return null; // normaliza vírgula para ponto
        const s=String(str).replace(/\./g,'').replace(',', '.').replace(/[^0-9.\-]/g,''); const n=Number(s); return isFinite(n)? n : null; }
      function fmtPreview(){
        try{
          const f=document.getElementById('fSalary'); const cur=(f.currency.value||'AOA'); const n=parseAmount(f.amount.value);
          const locales={AOA:'pt-AO', BRL:'pt-BR', USD:'en-US', EUR:'pt-PT'}; const loc=locales[cur]||'en-US';
          if(n===null){ document.getElementById('amtPreview').textContent=''; return; }
          const s=new Intl.NumberFormat(loc,{style:'currency', currency:cur}).format(n);
          document.getElementById('amtPreview').textContent = s;
        }catch(e){ document.getElementById('amtPreview').textContent=''; }
      }
      document.getElementById('salAmount').addEventListener('input', fmtPreview);
      document.querySelector('#fSalary select[name=currency]').addEventListener('change', fmtPreview);

      document.addEventListener('click', async (ev)=>{
        const add = ev.target.closest('#btnAddSalary');
        if(add){ ev.preventDefault(); const f=document.getElementById('fSalary'); const fd=new FormData(f); const amountRaw = fd.get('amount')||''; const norm = parseAmount(amountRaw); const data={action:'employee_salary_create', csrf:fd.get('csrf')||'', employee_id:String(empId), amount: (norm!==null? String(norm) : ''), currency:fd.get('currency')||'AOA', valid_from:fd.get('valid_from')||''}; document.getElementById('salMsg').textContent='Salvando...'; try{ const r=await post(data); if(!r.ok){ document.getElementById('salMsg').textContent='Falha (PIN ou criptografia)'; return; } document.getElementById('salMsg').textContent='Salvo'; f.reset(); document.getElementById('amtPreview').textContent=''; loadSalary(); }catch(e){ document.getElementById('salMsg').textContent='Erro'; } }
        const payBtn = ev.target.closest('button[data-pay]');
        if (payBtn){
          ev.preventDefault();
          const month = Number(payBtn.getAttribute('data-month'))||0; if(!month) return;
          const status = String(payBtn.getAttribute('data-pay'));
          const csrf = document.querySelector('#fPayCtl input[name=csrf]').value||'';
          const year = Number(document.getElementById('payYear').value)||new Date().getFullYear();
          let payload = {action:'payment_set', csrf: csrf, employee_id: String(empId), year: String(year), month: String(month), status: status};
          if (status==='paid'){
            const cur = document.getElementById('payCurrency').value||'AOA';
            const amountRaw = prompt('Valor pago (opcional):','');
            if (amountRaw!==null && amountRaw.trim()!==''){
              const norm = parseAmount(amountRaw);
              if (norm!==null){ payload.amount=String(norm); payload.currency=cur; }
            } else {
              payload.currency = cur;
            }
          }
          document.getElementById('payMsg').textContent='Salvando...';
          try{ const r = await post(payload); if(!r.ok){ document.getElementById('payMsg').textContent='Falha (PIN/Criptografia/CSRF)'; return; } document.getElementById('payMsg').textContent='Salvo'; loadPayments(); }catch(e){ document.getElementById('payMsg').textContent='Erro'; }
        }
      });

      // init defaults
      document.getElementById('payYear').value = new Date().getFullYear();
      document.getElementById('payYear').addEventListener('change', loadPayments);
      document.getElementById('payFilter').addEventListener('change', renderPaymentsTable);
      document.getElementById('btnPayExport').addEventListener('click', (ev)=>{
        ev.preventDefault();
        const year = lastPayments.year || new Date().getFullYear();
        const months=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        const map = new Map(); (lastPayments.items||[]).forEach(r=>{ map.set(Number(r.month), r); });
        const lines = [['Mes','Ano','Status','PagoEm','Valor']];
        for(let m=1;m<=12;m++){
          const r = map.get(m)||{status:'pending'};
          lines.push([months[m-1], String(year), r.status||'pending', r.paid_at||'', (r.amount||'')]);
        }
        const csv = lines.map(row=>row.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `pagamentos_${empId}_${year}.csv`; a.click(); URL.revokeObjectURL(a.href);
      });

      // Recibo (A4)
      document.getElementById('btnPayReceipt').addEventListener('click', async (ev)=>{
        ev.preventDefault();
        try{
          const year = lastPayments.year || new Date().getFullYear();
          // garantir dados atuais
          await loadBasic(); await loadSalary(); await loadPayments();
          // coletar dados
          const dEmp = await api('?action=employee_get&id='+empId);
          const emp = dEmp.employee||{};
          const dSal = await api('?action=employee_salary_list&employee_id='+empId);
          const sal = (dSal.items||[])[0]||{}; // último
          const months=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
          const map = new Map(); (lastPayments.items||[]).forEach(r=>{ map.set(Number(r.month), r); });
          let rows = '';
          for(let m=1;m<=12;m++){
            const r = map.get(m)||{status:'pending'};
            rows += `<tr><td>${months[m-1]}/${year}</td><td>${r.status||'pending'}</td><td>${r.paid_at||''}</td><td>${r.amount_masked||''}</td></tr>`;
          }
          const siteBrand = (document.querySelector('.navbar .navbar-brand')?.textContent||'').trim() || 'Intranet';
          const cur = document.getElementById('payCurrency').value||'AOA';
          const totalTxt = (lastPayments.total!==null)? (new Intl.NumberFormat(navigator.language||'pt-PT',{style:'currency',currency:cur}).format(Number(lastPayments.total))) : '—';
          const w = window.open('', '_blank');
          w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Recibo de Salário</title>
            <style>
              body{font-family:Arial,Helvetica,sans-serif;margin:20mm;}
              header, footer{display:flex;justify-content:space-between;align-items:center;}
              h1{font-size:18px;margin:0}
              table{width:100%;border-collapse:collapse;margin-top:10mm}
              th,td{border:1px solid #ccc;padding:6px;font-size:12px}
              .grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px}
              .small{font-size:11px;color:#555}
              @page{size:A4;margin:15mm}
            </style></head><body>
            <header><div><h1>Departamento | Recursos Humanos</h1><div class="small">Recibo de salário - ${new Date().toLocaleDateString()}</div></div><div class="small">Colaborador #${emp.id||''}</div></header>
            <section class="grid" style="margin-top:8mm">
              <div><strong>Nome:</strong> ${emp.name||''}</div>
              <div><strong>Email:</strong> ${emp.email||''}</div>
              <div><strong>Telefone:</strong> ${emp.phone||''}</div>
              <div><strong>Departamento:</strong> ${emp.dept_name||''}</div>
              <div><strong>Cargo:</strong> ${emp.role_name||''}</div>
              <div><strong>Salário:</strong> ${(sal.amount_masked||'')}</div>
            </section>
            <table><thead><tr><th>Mês</th><th>Status</th><th>Pago em</th><th>Valor</th></tr></thead><tbody>${rows}</tbody>
            <tfoot><tr><th colspan="3" style="text-align:right">Total ${year}</th><th>${totalTxt}</th></tr></tfoot></table>
            <footer style="margin-top:10mm" class="small"><div>${siteBrand}</div><div></div></footer>
          </body></html>`);
          w.document.close(); w.focus(); w.print();
        }catch(e){ alert('Falha ao gerar recibo.'); }
      });

      // Edição inline do valor pago (somente linhas "Pago")
      document.getElementById('payRows').addEventListener('click', (ev)=>{
        const cell = ev.target.closest('td[data-edit="amount"]');
        if(!cell) return;
        const row = cell.parentElement; const statusText = row.querySelector('span.badge');
        const isPaid = statusText && statusText.classList.contains('bg-success');
        if(!isPaid) return; // apenas edita quando Pago
        if(cell.querySelector('input')) return;
        const oldVal = cell.textContent.trim();
        const inp = document.createElement('input'); inp.type='text'; inp.className='form-control form-control-sm'; inp.value=oldVal.replace(/\s+/g,' ');
        cell.innerHTML=''; cell.appendChild(inp); inp.focus(); inp.select();
        const month = Number(cell.getAttribute('data-month'))||0;
        const save = async ()=>{
          const amountRaw = inp.value.trim();
          const norm = parseAmount(amountRaw);
          const csrf = document.querySelector('#fPayCtl input[name=csrf]').value||'';
          const year = Number(document.getElementById('payYear').value)||new Date().getFullYear();
          const cur = document.getElementById('payCurrency').value||'AOA';
          document.getElementById('payMsg').textContent='Salvando...';
          try{ const r = await post({action:'payment_set', csrf:csrf, employee_id:String(empId), year:String(year), month:String(month), status:'paid', amount:(norm!==null?String(norm):''), currency:cur}); if(!r.ok){ document.getElementById('payMsg').textContent='Falha'; cell.textContent=oldVal; return; } document.getElementById('payMsg').textContent='Salvo'; await loadPayments(); }catch(e){ document.getElementById('payMsg').textContent='Erro'; cell.textContent=oldVal; }
        };
        inp.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); save(); } if(e.key==='Escape'){ cell.textContent=oldVal; } });
        inp.addEventListener('blur', ()=>{ save(); });
      });

      async function loadRefs(){
        try{
          const d1 = await api('?action=departments_list');
          const d2 = await api('?action=roles_list');
          const dept = document.getElementById('dept');
          dept.innerHTML = '<option value="">(nenhum)</option>' + (d1.items||[]).map(x=>`<option value="${x.id}">${h(x.name||'')}</option>`).join('');
          const role = document.getElementById('role');
          role.innerHTML = '<option value="">(nenhum)</option>' + (d2.items||[]).map(x=>`<option value="${x.id}">${h(x.name||'')}</option>`).join('');
          // Preselect current
          const e = await api('?action=employee_get&id='+empId);
          if(e.employee){ if(e.employee.dept_id) dept.value = String(e.employee.dept_id); if(e.employee.role_id) role.value = String(e.employee.role_id); if(e.employee.manager_user_id) document.querySelector('#fBasic [name=manager_user_id]').value = e.employee.manager_user_id; }
        }catch(e){}
      }

      async function saveBasic(){
        const f = document.getElementById('fBasic');
        const fd = new FormData(f);
        const data = {action:'employee_update_basic', csrf: fd.get('csrf')||'', id: fd.get('id')||'', name: fd.get('name')||'', email: fd.get('email')||'', phone: fd.get('phone')||'', dept_id: fd.get('dept_id')||'', role_id: fd.get('role_id')||'', manager_user_id: fd.get('manager_user_id')||'', status: fd.get('status')||'active'};
        document.getElementById('basicMsg').textContent='Salvando...';
        try{
          const r = await post(data);
          if(!r.ok){ document.getElementById('basicMsg').textContent='Erro ao salvar'; return; }
          document.getElementById('basicMsg').textContent='Salvo';
          await loadBasic();
        }catch(e){ document.getElementById('basicMsg').textContent='Falha'; }
      }

      async function loadDocs(){
        const tb = document.getElementById('docRows');
        try{
          const d = await api('?action=employee_docs_list&employee_id='+empId);
          const it = d.items||[];
          if(it.length===0){ tb.innerHTML='<tr><td colspan="5" class="text-secondary">Sem documentos.</td></tr>'; return; }
          tb.innerHTML = it.map(x=>`<tr>
            <td>${x.id}</td>
            <td>${h(x.title||'')}</td>
            <td class="small text-secondary">${h(x.type||'')}</td>
            <td class="small text-secondary">${h(x.created_at||'')}</td>
            <td class="text-end d-flex justify-content-end gap-2">
              ${x.file_path?`<a class="btn btn-sm btn-outline-primary" href="${x.file_path}" target="_blank">Abrir</a>`:''}
              <button class="btn btn-sm btn-outline-warning" data-ren="${x.id}"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-outline-danger" data-del="${x.id}"><i class="bi bi-trash"></i></button>
            </td>
          </tr>`).join('');
        }catch(e){ tb.innerHTML='<tr><td colspan="5" class="text-danger">Falha ao carregar documentos.</td></tr>'; }
      }

      document.getElementById('btnSave').addEventListener('click', (ev)=>{ ev.preventDefault(); saveBasic(); });
      document.getElementById('btnUpload').addEventListener('click', async (ev)=>{
        ev.preventDefault();
        const form = document.getElementById('fDoc');
        const fdata = new FormData(form);
        fdata.append('action','employee_doc_upload');
        document.getElementById('docMsg').textContent='Enviando...';
        try{
          const r = await fetch('rh_api.php', {method:'POST', body: fdata});
          const d = await r.json();
          if(!d.ok){ document.getElementById('docMsg').textContent='Falha no upload'; return; }
          document.getElementById('docMsg').textContent='Enviado'; form.reset();
          loadDocs();
        }catch(e){ document.getElementById('docMsg').textContent='Erro no upload'; }
      });

      document.getElementById('btnNewDoc').addEventListener('click', async (ev)=>{
        ev.preventDefault();
        const title = prompt('Título do documento:', 'Documento');
        if (title===null) return;
        const csrf = document.querySelector('#fDoc input[name=csrf]').value||'';
        try{ const r = await post({action:'employee_doc_create', csrf: csrf, employee_id: String(empId), title: title, type: 'document'}); if(r.ok){ loadDocs(); } }catch(e){}
      });

      document.addEventListener('click', async (ev)=>{
        const ren = ev.target.closest('button[data-ren]');
        const del = ev.target.closest('button[data-del]');
        if (ren){
          ev.preventDefault();
          const id = ren.getAttribute('data-ren');
          const title = prompt('Novo título:'); if(title===null) return;
          const csrf = document.querySelector('#fDoc input[name=csrf]').value||'';
          try{ const r = await post({action:'employee_doc_rename', csrf: csrf, id: id, title: title}); if(r.ok){ loadDocs(); } }catch(e){}
          return;
        }
        if (del){
          ev.preventDefault();
          if(!confirm('Excluir este documento?')) return;
          const id = del.getAttribute('data-del');
          const csrf = document.querySelector('#fDoc input[name=csrf]').value||'';
          try{ const r = await post({action:'employee_doc_delete', csrf: csrf, id: id}); if(r.ok){ loadDocs(); } }catch(e){}
          return;
        }
      });

      (async function init(){ await loadBasic(); await loadRefs(); await loadDocs(); await loadPII(); await loadSalary(); document.getElementById('payYear').value = new Date().getFullYear(); await loadPayments(); })();
    </script>
  </body>
</html>
