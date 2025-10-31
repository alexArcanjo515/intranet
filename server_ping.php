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
$timeout = 3;

$results = [];
$attempts = 4;
for ($i=0; $i<$attempts; $i++) {
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $latency = (microtime(true) - $start) * 1000;
    if ($fp) { fclose($fp); $results[] = ['ok'=>true,'ms'=>(int)$latency]; } else { $results[] = ['ok'=>false,'ms'=>null,'err'=>$errstr?:('Erro '.$errno)]; }
    usleep(120000); // 120ms entre tentativas
}

$okCount = count(array_filter($results, fn($r)=>$r['ok']));
$avg = null;
$okTimes = array_map(fn($r)=>$r['ms'], array_filter($results, fn($r)=>$r['ok']));
if (!empty($okTimes)) { $avg = (int)round(array_sum($okTimes)/count($okTimes)); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ping (TCP) - Configurações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="settings.php?tab=servers"><i class="bi bi-hdd-network text-primary"></i><span>Servidores</span></a>
      </div>
    </nav>
    <main class="container py-4" style="max-width:720px;">
      <h1 class="h4 mb-3">Ping TCP</h1>
      <div class="card glass">
        <div class="card-body">
          <div class="mb-2"><strong>Servidor:</strong> <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($host) ?>:<?= (int)$port ?>)</div>
          <ul class="list-group mb-3">
            <?php foreach ($results as $idx=>$r): ?>
              <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                Tentativa #<?= $idx+1 ?>
                <?php if ($r['ok']): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle"><?= $r['ms'] ?> ms</span>
                <?php else: ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Falha</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex justify-content-between">
            <div>
              Sucesso: <strong><?= $okCount ?>/<?= $attempts ?></strong>
              <?php if ($avg !== null): ?> • Média: <strong><?= $avg ?> ms</strong><?php endif; ?>
            </div>
            <div>
              <a href="server_ping.php?id=<?= (int)$id ?>" class="btn btn-outline-light btn-sm">Repetir</a>
              <a href="settings.php?tab=servers" class="btn btn-primary btn-sm">Voltar</a>
            </div>
          </div>
        </div>
      </div>
    </main>
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
