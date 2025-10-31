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

$result = ['ok' => false, 'latency_ms' => null, 'http_code' => null, 'error' => '', 'checked' => date('c')];

$host = $s['host'] ?: $s['ip'];
$port = (int)$s['port'];
$protocol = $s['protocol'];

if ($protocol === 'http' || $protocol === 'https') {
    $url = $protocol . '://' . $host . (($protocol==='http' && $port!=80) || ($protocol==='https' && $port!=443) ? (":".$port) : '') . '/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Intranet/1.0',
        CURLOPT_HEADER => true,
    ]);
    $start = microtime(true);
    $resp = curl_exec($ch);
    $latency = (microtime(true) - $start) * 1000;
    if ($resp !== false) {
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $result['ok'] = ($code >= 200 && $code < 500);
        $result['http_code'] = $code;
        $result['latency_ms'] = (int)$latency;
    } else {
        $result['error'] = curl_error($ch);
    }
    curl_close($ch);
} elseif ($protocol === 'ssh') {
    $timeout = 6;
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $latency = (microtime(true) - $start) * 1000;
    if ($fp) {
        $result['ok'] = true;
        $result['latency_ms'] = (int)$latency;
        fclose($fp);
    } else {
        $result['error'] = trim($errstr ?: ('Erro '.$errno));
    }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste de servidor - Configurações</title>
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
      <h1 class="h4 mb-3">Teste de conectividade</h1>
      <div class="card glass">
        <div class="card-body">
          <div class="mb-3"><strong>Servidor:</strong> <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($protocol) ?>://<?= htmlspecialchars($host) ?>:<?= (int)$port ?>)</div>
          <?php if ($result['ok']): ?>
            <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle me-2"></i><div>Conexão OK<?= $result['http_code']? (', HTTP '.$result['http_code']) : '' ?><?= $result['latency_ms']? (' • ~'.$result['latency_ms'].'ms') : '' ?></div></div>
          <?php else: ?>
            <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div>Falha: <?= htmlspecialchars($result['error'] ?: 'desconhecida') ?></div></div>
          <?php endif; ?>
          <a href="settings.php?tab=servers" class="btn btn-outline-light">Voltar</a>
          <?php if (in_array($protocol, ['http','https'], true)): ?>
            <a href="server_view.php?id=<?= (int)$s['id'] ?>" class="btn btn-primary">Abrir visualização</a>
          <?php endif; ?>
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
