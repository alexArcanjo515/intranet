<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_permissions'], ['admin']);
$pdo = require __DIR__ . '/config/db.php';

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) { header('Location: users.php'); exit(); }

// Buscar usuário
$stmt = $pdo->prepare('SELECT id, username, name, email FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) { header('Location: users.php'); exit(); }

// Processar POST: atualizar papéis
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roles = $_POST['roles'] ?? [];
    if (!is_array($roles)) { $roles = []; }
    // Filtrar inteiros
    $roleIds = array_values(array_filter(array_map('intval', $roles), fn($v) => $v > 0));

    try {
        $pdo->beginTransaction();
        // Limpar existentes
        $del = $pdo->prepare('DELETE FROM user_roles WHERE user_id = :uid');
        $del->execute([':uid' => $userId]);
        // Inserir novos
        if (!empty($roleIds)) {
            $ins = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)');
            foreach ($roleIds as $rid) {
                $ins->execute([':uid' => $userId, ':rid' => $rid]);
            }
        }
        $pdo->commit();
        $message = 'Papéis atualizados com sucesso.';
    } catch (Throwable $e) {
        $pdo->rollBack();
        $message = 'Erro ao atualizar papéis.';
    }
}

// Buscar todos os papéis e os papéis do usuário
$allRoles = $pdo->query('SELECT id, name, description FROM roles ORDER BY name')->fetchAll();
$uRolesStmt = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id = :uid');
$uRolesStmt->execute([':uid' => $userId]);
$userRoleIds = array_map('intval', $uRolesStmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Papéis do Usuário - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .role-card { border: 1px dashed rgba(255,255,255,.15); }
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

    <main class="container py-4" style="max-width: 820px;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 class="h4 mb-1">Papéis do usuário</h1>
          <div class="text-secondary small">Para: <strong><?= htmlspecialchars($user['name'] ?: $user['username']) ?></strong> <span class="text-secondary">(<?= htmlspecialchars($user['username']) ?>)</span></div>
        </div>
        <a href="users.php" class="btn btn-outline-light">Voltar</a>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle me-2"></i><div><?= htmlspecialchars($message) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post">
            <div class="row g-3">
              <?php foreach ($allRoles as $role): ?>
                <div class="col-12 col-md-6">
                  <div class="p-3 rounded role-card h-100">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="role_<?= (int)$role['id'] ?>" name="roles[]" value="<?= (int)$role['id'] ?>" <?= in_array((int)$role['id'], $userRoleIds, true) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="role_<?= (int)$role['id'] ?>">
                        <strong><?= htmlspecialchars($role['name']) ?></strong>
                      </label>
                    </div>
                    <?php if (!empty($role['description'])): ?>
                      <div class="text-secondary small mt-1"><?= htmlspecialchars($role['description']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar</button>
              <a href="users.php" class="btn btn-outline-light">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
