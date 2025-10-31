<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo Ticket - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | HELP DESK | Novo Ticket
        <div class="ms-auto"><a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/tickets.php">Tickets</a></div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="card glass">
        <div class="card-body">
          <h1 class="h5 mb-3">Abrir Ticket</h1>
          <form id="f" class="row g-3" onsubmit="return false;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-12">
              <label class="form-label">Assunto</label>
              <input class="form-control" name="subject" required>
            </div>
            <div class="col-12">
              <label class="form-label">Categoria</label>
              <select class="form-select" name="category">
                <option value="Acesso">Acesso</option>
                <option value="Hardware">Hardware</option>
                <option value="Software">Software</option>
                <option value="Rede">Rede</option>
                <option value="Impressão">Impressão</option>
                <option value="Outros" selected>Outros</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Prioridade</label>
              <select class="form-select" name="priority">
                <option value="urgent">Urgente</option>
                <option value="high">Alta</option>
                <option value="medium" selected>Média</option>
                <option value="low">Baixa</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" rows="6" name="description"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary"><i class="bi bi-check2"></i> Criar</button>
              <a href="/portal/helpdesk/tickets.php" class="btn btn-outline-light">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
    </main>
    <script>
      async function post(path, data){ const r = await fetch('helpdesk_api.php'+path, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      const f = document.getElementById('f');
      f.addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(f);
        const data = {action:'tickets_create', csrf: fd.get('csrf')||'', subject:fd.get('subject')||'', category:fd.get('category')||'', priority:fd.get('priority')||'medium', description:fd.get('description')||''};
        try{
          const res = await post('', data);
          if(res.ok){ window.location.href = 'ticket_view.php?id='+res.id; }
        }catch(e){ alert('Falha ao criar ticket'); }
      });
    </script>
  </body>
</html>
