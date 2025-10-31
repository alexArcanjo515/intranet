<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crypto.php';
require_login();
require_any(['manage_settings'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $protocol = in_array($_POST['protocol'] ?? 'http', ['http','https','ssh'], true) ? $_POST['protocol'] : 'http';
    $port = (int)($_POST['port'] ?? 0);
    if ($port <= 0) { $port = ($protocol === 'https' ? 443 : ($protocol === 'ssh' ? 22 : 80)); }
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || ($ip === '' && $host === '')) {
        $error = 'Nome e IP/Host são obrigatórios.';
    }

    if ($error === '') {
        try {
            if ($id > 0) {
                if ($password !== '') {
                    $enc = encrypt_secret($password);
                    $upd = $pdo->prepare('UPDATE servers SET name=:n, ip=:ip, host=:h, protocol=:pr, port=:pt, username=:u, password=:p WHERE id=:id');
                    $upd->execute([':n'=>$name, ':ip'=>$ip, ':h'=>$host, ':pr'=>$protocol, ':pt'=>$port, ':u'=>$username, ':p'=>$enc, ':id'=>$id]);
                } else {
                    $upd = $pdo->prepare('UPDATE servers SET name=:n, ip=:ip, host=:h, protocol=:pr, port=:pt, username=:u WHERE id=:id');
                    $upd->execute([':n'=>$name, ':ip'=>$ip, ':h'=>$host, ':pr'=>$protocol, ':pt'=>$port, ':u'=>$username, ':id'=>$id]);
                }
            } else {
                $enc = ($password!=='') ? encrypt_secret($password) : '';
                $ins = $pdo->prepare('INSERT INTO servers (name, ip, host, protocol, port, username, password) VALUES (:n,:ip,:h,:pr,:pt,:u,:p)');
                $ins->execute([':n'=>$name, ':ip'=>$ip, ':h'=>$host, ':pr'=>$protocol, ':pt'=>$port, ':u'=>$username, ':p'=>$enc]);
            }
            header('Location: settings.php?tab=servers');
            exit();
        } catch (Throwable $e) {
            $error = 'Erro ao salvar servidor.';
        }
    }
}

$server = ['name'=>'','ip'=>'','host'=>'','protocol'=>'http','port'=>80,'username'=>'','password'=>''];
if ($editing && $error === '') {
    $stmt = $pdo->prepare('SELECT * FROM servers WHERE id = :id');
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch();
    if ($row) { $server = $row; $server['password']=''; } else { $editing = false; $id = 0; }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing ? 'Editar Servidor' : 'Novo Servidor' ?> - Configurações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="settings.php?tab=servers"><i class="bi bi-hdd-network text-primary"></i><span>Servidores</span></a>
      </div>
    </nav>

    <main class="container py-4" style="max-width:820px;">
      <h1 class="h4 mb-3"><?= $editing ? 'Editar servidor' : 'Novo servidor' ?></h1>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post" novalidate>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="name">Nome</label>
                <input class="form-control" id="name" name="name" value="<?= htmlspecialchars($server['name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="protocol">Protocolo</label>
                <select class="form-select" id="protocol" name="protocol">
                  <option value="http" <?= $server['protocol']==='http'?'selected':'' ?>>HTTP</option>
                  <option value="https" <?= $server['protocol']==='https'?'selected':'' ?>>HTTPS</option>
                  <option value="ssh" <?= $server['protocol']==='ssh'?'selected':'' ?>>SSH</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="ip">IP</label>
                <input class="form-control" id="ip" name="ip" placeholder="Ex.: 192.168.1.10" value="<?= htmlspecialchars($server['ip']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="host">Host</label>
                <input class="form-control" id="host" name="host" placeholder="Ex.: server.local" value="<?= htmlspecialchars($server['host']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="port">Porta</label>
                <input type="number" class="form-control" id="port" name="port" value="<?= (int)$server['port'] ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="username">Usuário</label>
                <input class="form-control" id="username" name="username" value="<?= htmlspecialchars($server['username']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="password">Senha</label>
                <input type="password" class="form-control" id="password" name="password" value="<?= htmlspecialchars($server['password']) ?>">
              </div>
            </div>
            <div class="d-flex gap-2 mt-3">
              <a href="settings.php?tab=servers" class="btn btn-outline-light">Cancelar</a>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
