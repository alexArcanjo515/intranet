<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tickets - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | HELP DESK | Tickets
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/new_ticket.php"><i class="bi bi-plus-circle"></i> Novo</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Assunto</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Solicitante</th><th>Data</th><th></th></tr></thead>
            <tbody id="rows"><tr><td colspan="8" class="text-secondary">Carregando...</td></tr></tbody>
          </table>
        </div>
      </div>
    </main>
    <script>
      async function api(path){ const r=await fetch('helpdesk_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      function h(s){return (s||'').replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function load(){
        const tb = document.getElementById('rows');
        try{
          const d = await api('?action=tickets_list');
          const it = d.items||[];
          if(it.length===0){ tb.innerHTML = '<tr><td colspan="8" class="text-secondary">Sem tickets.</td></tr>'; return; }
          tb.innerHTML = it.map(t=>`<tr>
            <td>${t.id}</td>
            <td>${h(t.subject)}</td>
            <td class="small text-secondary">${h(t.category||'')}</td>
            <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle">${h(t.priority)}</span></td>
            <td><span class="badge bg-info-subtle text-info border border-info-subtle">${h(t.status)}</span></td>
            <td class="small text-secondary">${h(t.requester)}</td>
            <td class="small text-secondary">${h(t.created_at||'')}</td>
            <td><a class="btn btn-sm btn-outline-primary" href="ticket_view.php?id=${t.id}">Abrir</a></td>
          </tr>`).join('');
        }catch(e){ tb.innerHTML = '<tr><td colspan="8" class="text-danger">Falha ao carregar.</td></tr>'; }
      }
      load();
    </script>
  </body>
</html>
