<?php
session_start();

// Se já estiver logado, vai direto para o dashboard
if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    try {
        $pdo = require __DIR__ . '/config/db.php';

        $stmt = $pdo->prepare('SELECT id, username, password_hash, name, is_active, must_change_password FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
            // Buscar roles do usuário
            $rolesStmt = $pdo->prepare('SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid');
            $rolesStmt->execute([':uid' => $user['id']]);
            $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            // Buscar permissões agregadas dos papéis
            $permsStmt = $pdo->prepare('SELECT DISTINCT p.name FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id INNER JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = :uid');
            $permsStmt->execute([':uid' => $user['id']]);
            $perms = $permsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'name' => $user['name'] ?: $user['username'],
                'roles' => $roles,
                'perms' => $perms,
                'must_change_password' => isset($user['must_change_password']) ? (int)$user['must_change_password'] : 0,
            ];
            if (!empty($_SESSION['user']['must_change_password'])) {
                header('Location: change_password.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            $error = 'Credenciais inválidas. Tente novamente.';
        }
    } catch (Throwable $e) {
        $error = 'Erro interno ao autenticar.';
    }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      html, body { height: 100%; }
      body {
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(1200px 600px at 10% 10%, rgba(99,102,241,.12), transparent 60%),
                    radial-gradient(800px 400px at 90% 20%, rgba(16,185,129,.12), transparent 60%),
                    radial-gradient(1000px 500px at 50% 100%, rgba(59,130,246,.12), transparent 60%);
      }
      .card {
        border: 1px solid rgba(255,255,255,.06);
        backdrop-filter: blur(6px);
      }
    </style>
  </head>
  <body>
    <main class="container" style="max-width: 420px;">
      <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill fs-1 text-primary"></i>
        <h1 class="h3 mt-2">Intranet</h1>
        <p class="text-secondary mb-0">Acesse com suas credenciais</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <div><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-body p-4">
          <form method="post" novalidate>
            <div class="mb-3">
              <label for="username" class="form-label">Usuário</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" id="username" name="username" placeholder="admin" required autofocus>
              </div>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Senha</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="admin" required>
              </div>
            </div>
            <button class="btn btn-primary w-100" type="submit">
              <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
            </button>
          </form>
        </div>
      </div>
      <p class="text-center text-secondary mt-3" style="font-size: .9rem;">Dica: usuário e senha são <code>admin</code>.</p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  </body>
</html>
