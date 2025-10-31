<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$roles = $_SESSION['portal_user']['roles'] ?? [];
$canView = in_array('admin', (array)$roles, true) || in_array('rh_view', (array)$roles, true) || in_array('rh_operate', (array)$roles, true) || in_array('rh_manage', (array)$roles, true);
if (!$canView) { http_response_code(403); echo 'Acesso negado'; exit(); }
// PIN guard + expiração
$ttl = 900; // 15 minutos
if (empty($_SESSION['rh_pin_ok'])) { header('Location: /portal/rh/pin.php'); exit(); }
if (!empty($_SESSION['rh_pin_last']) && (time() - (int)$_SESSION['rh_pin_last']) > $ttl) {
  unset($_SESSION['rh_pin_ok'], $_SESSION['rh_pin_at'], $_SESSION['rh_pin_last']);
  header('Location: /portal/rh/pin.php');
  exit();
}
$_SESSION['rh_pin_last'] = time();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RH - Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
          <a class="btn btn-outline-danger btn-sm" href="/portal/rh/pin_logout.php">Sair do RH</a>
        </div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/rh/employees.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-people"></i></div><div><div class="fw-bold">Colaboradores</div><div class="text-secondary small">Cadastros e dossiês</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/rh/requests_inbox.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-inboxes"></i></div><div><div class="fw-bold">Solicitações</div><div class="text-secondary small">Férias, atestados, reembolsos</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/rh/policies.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-journal-text"></i></div><div><div class="fw-bold">Políticas</div><div class="text-secondary small">Publicações e aceite</div></div></div></div>
          </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="/portal/rh/publish.php">
            <div class="card glass h-100"><div class="card-body d-flex align-items-center gap-3"><div class="fs-3"><i class="bi bi-megaphone"></i></div><div><div class="fw-bold">Publicar</div><div class="text-secondary small">Notícias e documentos</div></div></div></div>
          </a>
        </div>
      </div>
    </main>
  </body>
</html>
