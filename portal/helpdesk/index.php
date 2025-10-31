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
    <title>Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/index.php"><?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?> </a> | HELP DESK
        <div class="ms-auto"><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Voltar</a></div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/helpdesk/tickets.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-ticket-perforated"></i></div><div><div class="fw-bold">Tickets</div><div class="text-secondary small">Acompanhar e comentar</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/helpdesk/new_ticket.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-plus-circle"></i></div><div><div class="fw-bold">Abrir Ticket</div><div class="text-secondary small">Novo pedido/ocorrência</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/helpdesk/assets.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-pc-display"></i></div><div><div class="fw-bold">Ativos</div><div class="text-secondary small">Máquinas e servidores</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/helpdesk/servers.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-hdd-network"></i></div><div><div class="fw-bold">Servidores (Análises)</div><div class="text-secondary small">Execução de análises via SSH</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/helpdesk/url_analysis.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-link-45deg"></i></div><div><div class="fw-bold">Análise de URL</div><div class="text-secondary small">Headers, TLS, IP, achados</div></div></div></div>
          </a>
        </div>
      </div>
    </main>
  </body>
</html>
