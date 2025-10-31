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
    <title>RH - Políticas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | Políticas
        <div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="/portal/rh/index.php">RH</a><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a></div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="card glass mb-3">
        <div class="card-body">
          <div class="d-flex gap-2 justify-content-between align-items-end">
            <div class="flex-grow-1"></div>
            <div><button id="btnNew" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova política</button></div>
          </div>
        </div>
      </div>
      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Título</th><th>Versão</th><th>Publicada em</th><th class="text-end">Ações</th></tr></thead>
            <tbody id="rows"><tr><td colspan="5" class="text-secondary">Carregando...</td></tr></tbody>
          </table>
        </div>
      </div>
    </main>

    <div class="modal fade" id="mPol" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content glass">
          <div class="modal-header"><h5 class="modal-title" id="mTitle">Nova política</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body">
            <form id="fPol" class="row g-2" onsubmit="return false;" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="id" value="">
              <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="title"></div>
              <div class="col-6"><label class="form-label">Versão</label><input class="form-control" name="version"></div>
              <div class="col-6"><label class="form-label">Publicada em</label><input class="form-control" name="published_at" type="date"></div>
              <div class="col-12"><label class="form-label">Arquivo (PDF, opcional)</label><input class="form-control" name="file" type="file" accept="application/pdf"></div>
              <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" id="btnSave"><i class="bi bi-save"></i> Salvar</button><span id="msg" class="small text-secondary"></span></div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      let modalPol;
      async function api(path){ const r=await fetch('rh_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function postUrl(data){ const r=await fetch('rh_api.php',{method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      function h(s){return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function load(){ const tb=document.getElementById('rows'); try{ const d=await api('?action=policies_list'); const it=d.items||[]; if(it.length===0){ tb.innerHTML='<tr><td colspan="5" class="text-secondary">Sem políticas.</td></tr>'; return; } tb.innerHTML = it.map(e=>`<tr>
        <td>${e.id}</td>
        <td>${h(e.title||'')}</td>
        <td class="small text-secondary">${h(e.version||'')}</td>
        <td class="small text-secondary">${h(e.published_at||'')}</td>
        <td class="text-end d-flex justify-content-end gap-2">
          ${e.file_path?`<a class="btn btn-sm btn-outline-primary" href="${e.file_path}" target="_blank">Abrir</a>`:''}
          <button class="btn btn-sm btn-outline-warning" data-edit="${e.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" data-del="${e.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join(''); }catch(e){ tb.innerHTML='<tr><td colspan="5" class="text-danger">Falha ao carregar.</td></tr>'; } }

      function fillForm(it){ const f=document.getElementById('fPol'); f.id.value=it.id||''; f.title.value=it.title||''; f.version.value=it.version||''; f.published_at.value=(it.published_at||''); f.file.value=null; }
      document.getElementById('btnNew').addEventListener('click', (ev)=>{ ev.preventDefault(); if(!modalPol && window.bootstrap){ modalPol = new bootstrap.Modal(document.getElementById('mPol')); } fillForm({}); document.getElementById('mTitle').textContent='Nova política'; modalPol && modalPol.show(); });
      document.addEventListener('click', async (ev)=>{ const ed=ev.target.closest('button[data-edit]'); const de=ev.target.closest('button[data-del]'); if(ed){ ev.preventDefault(); const id=ed.getAttribute('data-edit'); try{ const d=await api('?action=policy_get&id='+id); if(!modalPol && window.bootstrap){ modalPol=new bootstrap.Modal(document.getElementById('mPol')); } fillForm(d.item||{}); document.getElementById('mTitle').textContent='Editar política #'+id; modalPol && modalPol.show(); }catch(e){} return; } if(de){ ev.preventDefault(); const id=de.getAttribute('data-del'); if(!confirm('Excluir política #'+id+'?')) return; const csrf=document.querySelector('#fPol [name=csrf]').value||''; try{ const r=await postUrl({action:'policy_delete', csrf:csrf, id:id}); if(r.ok){ load(); } }catch(e){} return; } });
      document.getElementById('btnSave').addEventListener('click', async (ev)=>{ ev.preventDefault(); const f=document.getElementById('fPol'); const fd=new FormData(f); const creating = !(fd.get('id')||''); const action = creating? 'policy_create' : 'policy_update'; fd.append('action', action); document.getElementById('msg').textContent='Salvando...'; try{ const r = await fetch('rh_api.php', { method:'POST', body: fd }); const j = await r.json(); if(!j.ok){ document.getElementById('msg').textContent='Falha'; return; } document.getElementById('msg').textContent='Salvo'; modalPol && modalPol.hide(); load(); }catch(e){ document.getElementById('msg').textContent='Erro'; } });
      load();
    </script>
  </body>
</html>
