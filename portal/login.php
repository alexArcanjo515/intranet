<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

// If already logged in, go to portal home
if (isset($_SESSION['portal_user']['id'])) {
  header('Location: index.php');
  exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  try {
    $stmt = $pdo->prepare('SELECT id, username, COALESCE(name, username) AS name, password_hash, is_active FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $u = $stmt->fetch();
    if ($u && (int)$u['is_active'] === 1 && password_verify($password, (string)$u['password_hash'])) {
      // carregar roles e perms
      $roles = [];
      $perms = [];
      try {
        $roles = $pdo->prepare('SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=:id');
        $roles->execute([':id'=>(int)$u['id']]);
        $roles = array_map(fn($x)=> $x['name'], $roles->fetchAll());
      } catch (Throwable $e) { $roles = []; }
      try {
        $sqlP = 'SELECT p.name FROM user_roles ur JOIN role_permissions rp ON rp.role_id=ur.role_id JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=:id';
        $pp = $pdo->prepare($sqlP); $pp->execute([':id'=>(int)$u['id']]);
        $perms = array_unique(array_map(fn($x)=> $x['name'], $pp->fetchAll()));
      } catch (Throwable $e) { $perms = []; }
      $_SESSION['portal_user'] = [ 'id' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['name'], 'roles'=>$roles, 'perms'=>$perms ];
      header('Location: index.php');
      exit();
    } else {
      $error = 'Credenciais inválidas ou usuário inativo.';
    }
  } catch (Throwable $e) {
    $error = 'Falha ao efetuar login.';
  }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(900px 400px at -10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(1000px 600px at 110% 10%, rgba(16,185,129,.16), transparent 60%)}
      .glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.45);backdrop-filter:blur(8px)}
    </style>
  </head>
  <body>
    <div class="container" style="max-width:440px;">
      <div class="card glass shadow">
        <div class="card-body p-4">
          <div class="text-center mb-4">
  <div class="d-flex flex-column align-items-center">
    <div class="mb-2" style="max-width: 180px;">
      <?= render_brand_html(__DIR__ . '/../assets/logotipo.png', ['class' => 'img-fluid']); ?>
    </div>
    <div class="fw-semibold fs-5 mt-1">Acesso à Intranet</div>
  </div>
</div>

          <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Usuário</label>
              <input type="text" name="username" class="form-control" required autocomplete="username">
            </div>
            <div class="mb-3">
              <label class="form-label">Senha</label>
              <input type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary w-100">Entrar</button>
          </form>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>