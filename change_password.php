<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { header('Location: login.php'); exit(); }
$pdo = require __DIR__ . '/config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd = trim($_POST['password'] ?? '');
    $pwd2 = trim($_POST['password_confirm'] ?? '');

    if ($pwd === '' || $pwd2 === '') {
        $error = 'Informe e confirme a nova senha.';
    } elseif ($pwd !== $pwd2) {
        $error = 'As senhas não conferem.';
    } elseif (strlen($pwd) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :p, must_change_password = 0 WHERE id = :id');
            $stmt->execute([':p' => $hash, ':id' => (int)$_SESSION['user']['id']]);
            $_SESSION['user']['must_change_password'] = 0;
            header('Location: index.php');
            exit();
        } catch (Throwable $e) {
            $error = 'Erro ao atualizar senha.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alterar Senha - Intranet</title>
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
      </div>
    </nav>

    <main class="container py-4" style="max-width: 560px;">
      <h1 class="h4 mb-3">Defina sua nova senha</h1>
      <p class="text-secondary">Por segurança, defina uma nova senha para continuar.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post" novalidate>
            <div class="mb-3">
              <label for="password" class="form-label">Nova senha</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
              <label for="password_confirm" class="form-label">Confirmar senha</label>
              <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
            </div>
            <div class="d-flex gap-2">
              <a href="logout.php" class="btn btn-outline-light">Sair</a>
              <button type="submit" class="btn btn-primary">Salvar e continuar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
