<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_users'], ['admin']);
$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '') {
        $error = 'Usuário é obrigatório.';
    }

    if ($error === '') {
        try {
            if ($id > 0) {
                // Verificar unicidade do username/email para outros usuários
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username = :u OR email = :e) AND id <> :id');
                $stmt->execute([':u' => $username, ':e' => $email, ':id' => $id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $error = 'Usuário ou e-mail já utilizado por outro registro.';
                } else {
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare('UPDATE users SET username=:u, name=:n, email=:e, is_active=:a, password_hash=:p WHERE id=:id');
                        $upd->execute([':u'=>$username, ':n'=>$name, ':e'=>$email, ':a'=>$is_active, ':p'=>$hash, ':id'=>$id]);
                    } else {
                        $upd = $pdo->prepare('UPDATE users SET username=:u, name=:n, email=:e, is_active=:a WHERE id=:id');
                        $upd->execute([':u'=>$username, ':n'=>$name, ':e'=>$email, ':a'=>$is_active, ':id'=>$id]);
                    }
                    header('Location: users.php');
                    exit();
                }
            } else {
                // Verificar unicidade
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e');
                $stmt->execute([':u' => $username, ':e' => $email]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $error = 'Usuário ou e-mail já existe.';
                } else {
                    $auto = ($password === '');
                    $plain = $auto ? substr(str_replace(['+','/','='], '', base64_encode(random_bytes(9))), 0, 12) : $password;
                    $hash = password_hash($plain, PASSWORD_DEFAULT);
                    $mustChange = $auto ? 1 : 0;
                    $ins = $pdo->prepare('INSERT INTO users (username, password_hash, name, email, is_active, must_change_password) VALUES (:u,:p,:n,:e,:a,:m)');
                    $ins->execute([':u'=>$username, ':p'=>$hash, ':n'=>$name, ':e'=>$email, ':a'=>$is_active, ':m'=>$mustChange]);
                    $_SESSION['flash_password'] = ['username' => $username, 'password' => $plain];
                    header('Location: users.php');
                    exit();
                }
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar usuário.';
        }
    }
}

$user = ['username'=>'','name'=>'','email'=>'','is_active'=>1];
if ($editing && $error === '') {
    $stmt = $pdo->prepare('SELECT id, username, name, email, is_active FROM users WHERE id = :id');
    $stmt->execute([':id'=>$id]);
    $u = $stmt->fetch();
    if ($u) { $user = $u; } else { $editing = false; $id = 0; }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing ? 'Editar Usuário' : 'Novo Usuário' ?> - Intranet</title>
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
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php"><i class="bi bi-hurricane text-primary"></i><span>Intranet</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarColor02">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-people me-1"></i>Usuários</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="max-width:720px;">
      <h1 class="h4 mb-3"><?= $editing ? 'Editar usuário' : 'Novo usuário' ?></h1>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post" novalidate>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="mb-3">
              <label class="form-label" for="username">Usuário</label>
              <input class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="name">Nome</label>
              <input class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="email">E-mail</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Senha <?= $editing ? '<small class="text-secondary">(deixe em branco para não alterar)</small>' : '' ?></label>
              <input type="password" class="form-control" id="password" name="password" placeholder="<?= $editing ? '••••••' : '' ?>">
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" <?= ((int)($user['is_active'] ?? 1) === 1 ? 'checked' : '') ?>>
              <label class="form-check-label" for="is_active">Ativo</label>
            </div>
            <div class="d-flex gap-2">
              <a href="users.php" class="btn btn-outline-light">Cancelar</a>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
