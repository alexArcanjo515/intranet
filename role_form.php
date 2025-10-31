<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { header('Location: login.php'); exit(); }
$rolesSession = $_SESSION['user']['roles'] ?? [];
if (!in_array('admin', $rolesSession, true)) { http_response_code(403); echo 'Acesso negado.'; exit(); }

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') { $error = 'Nome do papel é obrigatório.'; }

    if ($error === '') {
        try {
            if ($id > 0) {
                if ($id === 1 && strtolower($name) !== 'admin') {
                    $error = 'O papel admin é protegido e não pode ser renomeado para outro nome.';
                } else {
                    // Unicidade
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = :n AND id <> :id');
                    $stmt->execute([':n'=>$name, ':id'=>$id]);
                    if ((int)$stmt->fetchColumn() > 0) {
                        $error = 'Já existe um papel com este nome.';
                    } else {
                        $upd = $pdo->prepare('UPDATE roles SET name=:n, description=:d WHERE id=:id');
                        $upd->execute([':n'=>$name, ':d'=>$description, ':id'=>$id]);
                        header('Location: roles.php');
                        exit();
                    }
                }
            } else {
                // Unicidade
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = :n');
                $stmt->execute([':n'=>$name]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $error = 'Já existe um papel com este nome.';
                } else {
                    $ins = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:n, :d)');
                    $ins->execute([':n'=>$name, ':d'=>$description]);
                    header('Location: roles.php');
                    exit();
                }
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar papel.';
        }
    }
}

$role = ['name'=>'','description'=>''];
if ($editing && $error === '') {
    $stmt = $pdo->prepare('SELECT id, name, description FROM roles WHERE id = :id');
    $stmt->execute([':id'=>$id]);
    $r = $stmt->fetch();
    if ($r) { $role = $r; } else { $editing = false; $id = 0; }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing ? 'Editar Papel' : 'Novo Papel' ?> - Intranet</title>
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
            <li class="nav-item"><a class="nav-link" href="roles.php"><i class="bi bi-shield-lock me-1"></i>Papéis</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="max-width:720px;">
      <h1 class="h4 mb-3"><?= $editing ? 'Editar papel' : 'Novo papel' ?></h1>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post" novalidate>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="mb-3">
              <label class="form-label" for="name">Nome</label>
              <input class="form-control" id="name" name="name" value="<?= htmlspecialchars($role['name']) ?>" required <?= ($id===1?'readonly':'') ?>>
            </div>
            <div class="mb-3">
              <label class="form-label" for="description">Descrição</label>
              <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($role['description'] ?? '') ?></textarea>
            </div>
            <div class="d-flex gap-2">
              <a href="roles.php" class="btn btn-outline-light">Cancelar</a>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
