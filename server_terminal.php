<?php
session_start();
@set_time_limit(90);
ini_set('default_socket_timeout', '12');
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
if (($s['protocol'] ?? 'ssh') !== 'ssh') { header('Location: settings.php?tab=servers'); exit(); }

$host = $s['host'] ?: $s['ip'];
$port = (int)($s['port'] ?: 22);
$user = (string)($s['username'] ?: '');
$pass = (string)($s['password'] ?: '');

$error = '';
$diag = '';
$output = '';
$cmd = trim($_POST['cmd'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('ssh2_connect')) {
        $error = 'Extensão php-ssh2 não instalada. Instale para usar o terminal (ex.: sudo apt-get install php-ssh2 e reinicie o servidor).';
    } else {
        try {
            // Pré-checagem TCP para diagnóstico
            $tcpErr = '';
            $fp = @fsockopen($host, $port, $errno, $errstr, 8);
            if ($fp) { fclose($fp); }
            else { $tcpErr = trim($errstr ?: ('errno '.$errno)); }
            $fn_connect = 'ssh2_connect';
            $fn_auth_password = 'ssh2_auth_password';
            $fn_exec = 'ssh2_exec';

            $conn = @$fn_connect($host, $port);
            if (!$conn) { throw new Exception('Falha em ssh2_connect'); }
            if ($user !== '') {
                if (!@$fn_auth_password($conn, $user, $pass)) {
                    throw new Exception('Autenticação SSH falhou. Verifique usuário/senha.');
                }
            } else {
                throw new Exception('Usuário SSH não definido nas configurações do servidor.');
            }
            // Forçar ambiente não interativo; executar comando e capturar stdout/stderr
            $stream = @$fn_exec($conn, $cmd . ' 2>&1');
            if (!$stream) { throw new Exception('Falha ao executar comando.'); }
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);
            if ($tcpErr) { $diag = 'Aviso TCP: ' . htmlspecialchars($tcpErr); }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            if (!empty($tcpErr)) { $diag = 'TCP: ' . htmlspecialchars($tcpErr); }
        }
    }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminal SSH - Configurações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .term { background: #0b1220; color: #e5e7eb; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
      textarea.term { min-height: 120px; }
      pre.term { min-height: 300px; white-space: pre-wrap; word-break: break-word; }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="settings.php?tab=servers">
          <i class="bi bi-terminal text-primary"></i><span>Terminal SSH</span>
        </a>
        <div class="d-flex ms-auto gap-2">
          <a class="btn btn-outline-light btn-sm" href="server_test.php?id=<?= (int)$s['id'] ?>"><i class="bi bi-speedometer2 me-1"></i>Testar</a>
          <a class="btn btn-primary btn-sm" href="settings.php?tab=servers"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="max-width: 920px;">
      <h1 class="h5 mb-3">Conectado a: <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($host) ?>:<?= (int)$port ?>)</h1>
      <?php if (!function_exists('ssh2_connect')): ?>
        <div class="alert alert-warning">Extensão <code>php-ssh2</code> não está disponível. Instale-a para habilitar o terminal SSH.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>
      <?php if ($diag): ?>
        <div class="alert alert-warning d-flex align-items-center"><i class="bi bi-info-circle me-2"></i><div><?= $diag ?></div></div>
      <?php endif; ?>

      <div class="card glass mb-3">
        <div class="card-body">
          <form method="post" class="d-flex flex-column gap-2">
            <div>
              <label class="form-label" for="cmd">Comando</label>
              <textarea class="form-control term" id="cmd" name="cmd" placeholder="ex.: uname -a && whoami" required><?= htmlspecialchars($cmd ?: '') ?></textarea>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary"><i class="bi bi-play-circle me-1"></i>Executar</button>
              <a class="btn btn-outline-light" href="server_terminal.php?id=<?= (int)$id ?>">Limpar</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card glass">
        <div class="card-body">
          <label class="form-label">Saída</label>
          <pre class="term p-3 rounded mb-0"><?php echo htmlspecialchars($output ?: ''); ?></pre>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
