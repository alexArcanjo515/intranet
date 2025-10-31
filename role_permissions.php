<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { header('Location: login.php'); exit(); }
$rolesSession = $_SESSION['user']['roles'] ?? [];
if (!in_array('admin', $rolesSession, true)) { http_response_code(403); echo 'Acesso negado.'; exit(); }

$pdo = require __DIR__ . '/config/db.php';

$roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roleId <= 0) { header('Location: roles.php'); exit(); }

// Buscar papel
$stmt = $pdo->prepare('SELECT id, name, description FROM roles WHERE id = :id');
$stmt->execute([':id' => $roleId]);
$role = $stmt->fetch();
if (!$role) { header('Location: roles.php'); exit(); }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $perms = $_POST['perms'] ?? [];
    if (!is_array($perms)) { $perms = []; }
    $permIds = array_values(array_filter(array_map('intval', $perms), fn($v) => $v > 0));

    try {
        $pdo->beginTransaction();
        $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :rid');
        $del->execute([':rid' => $roleId]);
        if (!empty($permIds)) {
            $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)');
            foreach ($permIds as $pid) {
                $ins->execute([':rid' => $roleId, ':pid' => $pid]);
            }
        }
        $pdo->commit();
        $message = 'Permissões atualizadas com sucesso.';
    } catch (Throwable $e) {
        $pdo->rollBack();
        $message = 'Erro ao atualizar permissões.';
    }
}

$allPerms = $pdo->query('SELECT id, name, description FROM permissions ORDER BY name')->fetchAll();
$rPermsStmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :rid');
$rPermsStmt->execute([':rid' => $roleId]);
$rolePermIds = array_map('intval', $rPermsStmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permissões do Papel - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .perm-card { border: 1px dashed rgba(255,255,255,.15); }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php"><i class="bi bi-hurricane text-primary"></i><span>Intranet</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarColor02">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="roles.php"><i class="bi bi-shield-lock me-1"></i>Papéis</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="max-width: 820px;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 class="h4 mb-1">Permissões do papel</h1>
          <div class="text-secondary small">Papel: <strong><?= htmlspecialchars($role['name']) ?></strong></div>
        </div>
        <a href="roles.php" class="btn btn-outline-light">Voltar</a>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle me-2"></i><div><?= htmlspecialchars($message) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post">
            <div class="row g-3">
              <?php foreach ($allPerms as $perm): ?>
                <div class="col-12 col-md-6">
                  <div class="p-3 rounded perm-card h-100">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="perm_<?= (int)$perm['id'] ?>" name="perms[]" value="<?= (int)$perm['id'] ?>" <?= in_array((int)$perm['id'], $rolePermIds, true) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="perm_<?= (int)$perm['id'] ?>">
                        <strong><?= htmlspecialchars($perm['name']) ?></strong>
                      </label>
                    </div>
                    <?php if (!empty($perm['description'])): ?>
                      <div class="text-secondary small mt-1"><?= htmlspecialchars($perm['description']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar</button>
              <a href="roles.php" class="btn btn-outline-light">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
