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
    <title>RH - Colaboradores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/rh/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | Colaboradores
        <div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="/portal/rh/index.php">RH</a><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a></div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="card glass">
        <div class="card-body">
          <form id="fSearch" class="row g-2 align-items-center" onsubmit="return false;">
            <div class="col-12 col-md-6">
              <input id="q" class="form-control form-control-sm" placeholder="Buscar por nome ou email">
            </div>
            <div class="col-12 col-md-6 d-flex gap-2 justify-content-md-end">
              <button id="btnSearch" class="btn btn-outline-light btn-sm"><i class="bi bi-search"></i> Buscar</button>
              <button id="btnNew" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Novo</button>
            </div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Nome</th><th>Email</th><th>Departamento</th><th>Cargo</th></tr></thead>
            <tbody id="rows"><tr><td colspan="5" class="text-secondary">Carregando...</td></tr></tbody>
          </table>
        </div>
      </div>
    </main>
    <!-- Modal Novo -->
    <div class="modal fade" id="mNew" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content glass">
          <div class="modal-header"><h5 class="modal-title">Novo Colaborador</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body">
            <form id="fNew" class="row g-2" onsubmit="return false;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <div class="col-12"><label class="form-label">Nome</label><input class="form-control" name="name" required></div>
              <div class="col-12"><label class="form-label">Email</label><input class="form-control" name="email"></div>
              <div class="col-12 col-md-6"><label class="form-label">Departamento</label><select class="form-select" name="dept_id" id="deptNew"></select></div>
              <div class="col-12 col-md-6"><label class="form-label">Cargo</label><select class="form-select" name="role_id" id="roleNew"></select></div>
              <div class="col-12"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Ativo</option><option value="inactive">Inativo</option></select></div>
              <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" id="btnCreate">Criar</button><span id="newMsg" class="small text-secondary"></span></div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      let modalNew;
      async function api(path){ const r=await fetch('rh_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(data){ const r=await fetch('rh_api.php',{method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      function h(s){return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function load(){
        const tb=document.getElementById('rows');
        const q = (document.getElementById('q').value||'').trim();
        const qp = q? ('&q='+encodeURIComponent(q)) : '';
        try{ const d=await api('?action=employees_list'+qp); const it=d.items||[];
          if(it.length===0){ tb.innerHTML='<tr><td colspan="5" class="text-secondary">Nenhum colaborador.</td></tr>'; return; }
          tb.innerHTML = it.map(e=>`<tr>
            <td>${e.id}</td>
            <td><a href="/portal/rh/employee_view.php?id=${e.id}">${h(e.name||'')}</a></td>
            <td class=\"small text-secondary\">${h(e.email||'')}</td>
            <td class=\"small text-secondary\">${h(e.dept||'')}</td>
            <td class=\"small text-secondary\">${h(e.role||'')}</td>
          </tr>`).join('');
        }catch(e){ tb.innerHTML='<tr><td colspan="5" class="text-danger">Falha ao carregar.</td></tr>'; }
      }
      document.getElementById('btnSearch').addEventListener('click', (ev)=>{ ev.preventDefault(); load(); });
      document.getElementById('fSearch').addEventListener('submit', (ev)=>{ ev.preventDefault(); load(); });
      document.getElementById('btnNew').addEventListener('click', async (ev)=>{ ev.preventDefault(); try{ const d1=await api('?action=departments_list'); const d2=await api('?action=roles_list'); const dept=document.getElementById('deptNew'); dept.innerHTML='<option value="">(nenhum)</option>'+(d1.items||[]).map(x=>`<option value="${x.id}">${h(x.name||'')}</option>`).join(''); const role=document.getElementById('roleNew'); role.innerHTML='<option value="">(nenhum)</option>'+(d2.items||[]).map(x=>`<option value="${x.id}">${h(x.name||'')}</option>`).join(''); if(!modalNew && window.bootstrap){ modalNew = new bootstrap.Modal(document.getElementById('mNew')); } if(modalNew){ modalNew.show(); } }catch(e){} });
      document.getElementById('btnCreate').addEventListener('click', async (ev)=>{ ev.preventDefault(); const f=document.getElementById('fNew'); const fd=new FormData(f); const data={action:'employee_create', csrf: fd.get('csrf')||'', name: fd.get('name')||'', email: fd.get('email')||'', dept_id: fd.get('dept_id')||'', role_id: fd.get('role_id')||'', status: fd.get('status')||'active'}; document.getElementById('newMsg').textContent='Criando...'; try{ const r=await post(data); if(!r.ok){ document.getElementById('newMsg').textContent='Falha'; return; } const id = r.id; document.getElementById('newMsg').textContent='Criado'; f.reset(); modalNew && modalNew.hide(); if (id) { window.location.href = '/portal/rh/employee_view.php?id='+id; } else { load(); } }catch(e){ document.getElementById('newMsg').textContent='Erro'; } });
      load();
    </script>
    
  </body>
</html>
