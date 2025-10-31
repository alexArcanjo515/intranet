<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_settings'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: settings.php?tab=servers'); exit(); }

$stmt = $pdo->prepare('SELECT * FROM servers WHERE id = :id');
$stmt->execute([':id' => $id]);
$s = $stmt->fetch();
if (!$s) { header('Location: settings.php?tab=servers'); exit(); }

$host = $s['host'] ?: $s['ip'];
$port = (int)$s['port'];
$base = ($s['protocol'] ?: 'http') . '://' . $host . (($s['protocol']==='http' && $port!=80) || ($s['protocol']==='https' && $port!=443) ? (":".$port) : '');
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visualizar servidor - Configurações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .viewer { height: calc(100vh - 140px); border: 1px solid rgba(255,255,255,.06); }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="settings.php?tab=servers">
          <i class="bi bi-hdd-network text-primary"></i><span>Servidores</span>
        </a>
        <div class="d-flex ms-auto gap-2">
          <a class="btn btn-outline-light btn-sm" href="server_test.php?id=<?= (int)$s['id'] ?>"><i class="bi bi-speedometer2 me-1"></i>Testar</a>
          <a class="btn btn-primary btn-sm" href="settings.php?tab=servers"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
      </div>
    </nav>

    <main class="container py-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <div class="text-secondary small">Visualizando</div>
          <div class="h5 mb-0"><?= htmlspecialchars($s['name']) ?> <span class="text-secondary">(<?= htmlspecialchars($base) ?>)</span></div>
        </div>
        <form class="d-flex" method="get" action="server_view.php">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <div class="input-group">
            <span class="input-group-text">/</span>
            <input class="form-control" name="path" placeholder="caminho (ex.: /status)" value="<?= htmlspecialchars($_GET['path'] ?? '/') ?>">
            <button class="btn btn-outline-light" type="submit">Ir</button>
          </div>
        </form>
      </div>
      <div class="viewer rounded overflow-hidden">
        <iframe src="server_proxy.php?id=<?= (int)$s['id'] ?>&path=<?= urlencode($_GET['path'] ?? '/') ?>" width="100%" height="100%" style="border:0"></iframe>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
