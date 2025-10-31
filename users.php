<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['manage_users'], ['admin']);
$pdo = require __DIR__ . '/config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (u.username LIKE :q OR u.name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = "%$q%";
}
$sql = "SELECT u.id, u.username, u.name, u.email, u.is_active, u.created_at,
               GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
        FROM users u
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN roles r ON r.id = ur.role_id
        $where
        GROUP BY u.id
        ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .table > :not(caption) > * > * { background-color: transparent; }
      .badge-soft { background: #0d6efd22; color: #93c5fd; border: 1px solid #0d6efd55; }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
          <i class="bi bi-hurricane text-primary"></i><?= render_brand_html(); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarColor02">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid me-1"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link active" href="users.php"><i class="bi bi-people me-1"></i>Usuários</a></li>
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
          <h1 class="h4 mb-0">Usuários</h1>
          <small class="text-secondary">Gerencie contas, status e papéis</small>
        </div>
        <div class="d-flex gap-2">
          <form class="d-flex" method="get">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" name="q" placeholder="Buscar por nome, usuário ou e-mail" value="<?= htmlspecialchars($q) ?>">
            </div>
          </form>
          <a href="user_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo usuário</a>
        </div>
      </div>

      <?php if (!empty($_SESSION['flash_password'])): $fp = $_SESSION['flash_password']; unset($_SESSION['flash_password']); ?>
        <div class="alert alert-warning d-flex align-items-start" role="alert">
          <i class="bi bi-key-fill me-2 mt-1"></i>
          <div>
            <div><strong>Usuário criado:</strong> <code><?= htmlspecialchars($fp['username']) ?></code></div>
            <div class="mt-1"><strong>Senha provisória:</strong> <code id="newUserPass"><?= htmlspecialchars($fp['password']) ?></code></div>
            <div class="mt-2">
              <button class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText(document.getElementById('newUserPass').innerText)"><i class="bi bi-clipboard me-1"></i>Copiar senha</button>
              <small class="text-secondary ms-2">Envie esta senha ao usuário. Ele poderá alterá-la após o primeiro login (quando implementarmos a troca de senha).</small>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Usuário</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Papéis</th>
                <th>Status</th>
                <th style="width:220px">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td class="text-secondary">#<?= (int)$u['id'] ?></td>
                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td>
                  <?php if (!empty($u['roles'])): ?>
                    <?php foreach (explode(',', $u['roles']) as $role): ?>
                      <span class="badge badge-soft me-1"><?= htmlspecialchars(trim($role)) ?></span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$u['is_active'] === 1): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Ativo</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inativo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="btn-group">
                    <a href="user_form.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil-square"></i></a>
                    <a href="user_roles.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-shield-lock"></i></a>
                    <a href="user_reset_password.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-dark" onclick="return confirm('Resetar a senha deste usuário? Ele precisará alterá-la no próximo login.');"><i class="bi bi-key"></i></a>
                    <?php if ((int)$u['is_active'] === 1): ?>
                      <a href="user_toggle.php?id=<?= (int)$u['id'] ?>&action=deactivate" class="btn btn-sm btn-outline-warning" onclick="return confirm('Desativar este usuário?');"><i class="bi bi-slash-circle"></i></a>
                    <?php else: ?>
                      <a href="user_toggle.php?id=<?= (int)$u['id'] ?>&action=activate" class="btn btn-sm btn-outline-success"><i class="bi bi-check-circle"></i></a>
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
