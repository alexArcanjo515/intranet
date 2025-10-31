<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = current_user();
$canManage = in_array('admin', $u['roles'] ?? [], true) || in_array('manage_documents', $u['perms'] ?? [], true);

$pdo = require __DIR__ . '/config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$c = isset($_GET['c']) ? trim($_GET['c']) : '';
$whereParts = [];
$params = [];
if ($q !== '') {
    $whereParts[] = '(d.title LIKE :q OR d.category LIKE :q)';
    $params[':q'] = "%$q%";
}
if ($c !== '') {
    $whereParts[] = 'd.category = :c';
    $params[':c'] = $c;
}
$where = empty($whereParts) ? '' : ('WHERE ' . implode(' AND ', $whereParts));

// categories list
$cats = $pdo->query("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT d.id, d.title, d.path, d.category, d.version, d.created_at, u.username AS author,
               (SELECT COUNT(*) FROM document_versions dv WHERE dv.document_id = d.id) AS versions_count
        FROM documents d
        LEFT JOIN users u ON u.id = d.created_by
        $where
        ORDER BY d.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentos - Intranet</title>
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
          <i class="bi bi-hurricane text-primary"></i><span>Intranet</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarColor02">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid me-1"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link active" href="documents.php"><i class="bi bi-folder2-open me-1"></i>Documentos</a></li>
          </ul>
          <div class="d-flex align-items-center gap-2">
            <?php if ($canManage): ?>
              <a href="document_upload.php" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Novo documento</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
          </div>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-0">Documentos</h1>
          <small class="text-secondary">Central de arquivos, versões e categorias</small>
        </div>
        <form class="d-flex gap-2" method="get">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" placeholder="Buscar por título ou categoria" value="<?= htmlspecialchars($q) ?>">
          </div>
          <select name="c" class="form-select" style="max-width:220px">
            <option value="">Todas categorias</option>
            <?php foreach ($cats as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $c===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-light" type="submit">Filtrar</button>
        </form>
      </div>

      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Título</th>
                <th>Categoria</th>
                <th>Versão</th>
                <th>Histórico</th>
                <th>Autor</th>
                <th>Data</th>
                <th style="width:260px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($docs as $d): ?>
              <tr>
                <td><strong><?= htmlspecialchars($d['title']) ?></strong></td>
                <td><?= htmlspecialchars($d['category'] ?? '—') ?></td>
                <td><span class="badge badge-soft">v<?= (int)$d['version'] ?></span></td>
                <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= (int)($d['versions_count'] ?? 0) ?></span></td>
                <td><?= htmlspecialchars($d['author'] ?? '—') ?></td>
                <td><span class="text-secondary small"><?= htmlspecialchars($d['created_at']) ?></span></td>
                <td>
                  <div class="btn-group">
                    <a href="document_preview.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Preview"><i class="bi bi-eye"></i></a>
                    <a href="document_download.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-light" title="Baixar atual"><i class="bi bi-download"></i></a>
                    <a href="document_versions.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Histórico de versões"><i class="bi bi-clock-history"></i></a>
                    <?php if ($canManage): ?>
                      <a href="document_upload.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-info" title="Enviar nova versão"><i class="bi bi-arrow-repeat"></i></a>
                      <a href="document_delete.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este documento?');"><i class="bi bi-trash"></i></a>
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
