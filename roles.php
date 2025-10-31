<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['manage_permissions'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (r.name LIKE :q OR r.description LIKE :q)";
    $params[':q'] = "%$q%";
}
$sql = "SELECT r.id, r.name, r.description,
               (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS users_count,
               (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perms_count
        FROM roles r
        $where
        ORDER BY r.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$roles = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Papéis - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .table > :not(caption) > * > * { background-color: transparent; }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
          <i class="bi bi-hurricane text-primary"></i>
          <?= render_brand_html(); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarColor02">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid me-1"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link active" href="roles.php"><i class="bi bi-shield-lock me-1"></i>Papéis</a></li>
          </ul>
          <div class="d-flex align-items-center gap-3">
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
          </div>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-0">Papéis</h1>
          <small class="text-secondary">Gerencie papéis e permissões</small>
        </div>
        <div class="d-flex gap-2">
          <form class="d-flex" method="get">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" name="q" placeholder="Buscar por nome/descrição" value="<?= htmlspecialchars($q) ?>">
            </div>
          </form>
          <a href="role_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo papel</a>
        </div>
      </div>

      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Usuários</th>
                <th>Permissões</th>
                <th style="width:220px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($roles as $r): ?>
              <tr>
                <td class="text-secondary">#<?= (int)$r['id'] ?></td>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                <td><?= htmlspecialchars($r['description'] ?? '') ?></td>
                <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= (int)$r['users_count'] ?></span></td>
                <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= (int)$r['perms_count'] ?></span></td>
                <td>
                  <div class="btn-group">
                    <a href="role_form.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil-square"></i></a>
                    <a href="role_permissions.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-list-check"></i></a>
                    <?php if ((int)$r['id'] === 1): ?>
                      <button class="btn btn-sm btn-outline-secondary" disabled title="Papel protegido"><i class="bi bi-shield-lock"></i></button>
                    <?php elseif ((int)$r['users_count'] > 0): ?>
                      <button class="btn btn-sm btn-outline-secondary" disabled title="Não é possível excluir: há usuários vinculados"><i class="bi bi-trash"></i></button>
                    <?php else: ?>
                      <a href="role_delete.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este papel? Esta ação é irreversível.');"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
