<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }

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

$cats = $pdo->query("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT d.id, d.title, d.path, d.category, d.version, d.created_at, u.username AS author
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
    <title>Documentos - Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .table > :not(caption) > * > * { background-color: transparent; }
      .badge-soft { background: #0d6efd22; color: #93c5fd; border: 1px solid #0d6efd55; }
    </style>
  </head>
  <body class="hero min-vh-100 d-flex flex-column">
     <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="./index.php"><i class="bi bi-building"></i><?php echo render_brand_html(__DIR__ . '/../assets/logotipo.png'); ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
          <a href="helpdesk/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tools"></i> Help Desk
          </a>
          <div class="position-relative">
            <a href="chat.php" class="btn btn-outline-secondary btn-sm position-relative">
              <i class="bi bi-chat-dots"></i>
              <span id="chatBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary d-none">0</span>
            </a>
          </div>
          <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm position-relative" id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell"></i>
              <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="notifBtn" style="min-width: 320px;">
              <div class="p-2 d-flex justify-content-between align-items-center border-bottom border-secondary-subtle">
                <strong class="small">Notificações</strong>
                <button class="btn btn-sm btn-link text-decoration-none" id="markAll">Marcar todas como lidas</button>
              </div>
              <div id="notifList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto">
                <div class="list-group-item bg-transparent text-secondary small">Carregando…</div>
              </div>
            </div>
          </div>
          <span class="text-secondary small">Olá,</span>
          <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($displayName) ?></span>
          <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h5 mb-0">Documentos</h1>
          <small class="text-secondary">Central de arquivos</small>
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
                <th>Autor</th>
                <th>Data</th>
                <th style="width:160px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($docs as $d): ?>
              <tr>
                <td><strong><?= htmlspecialchars($d['title']) ?></strong></td>
                <td><?= htmlspecialchars($d['category'] ?? '—') ?></td>
                <td><span class="badge badge-soft">v<?= (int)$d['version'] ?></span></td>
                <td><?= htmlspecialchars($d['author'] ?? '—') ?></td>
                <td><span class="text-secondary small"><?= htmlspecialchars($d['created_at']) ?></span></td>
                <td>
                  <div class="btn-group">
                    <a href="document_download.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-light" title="Baixar"><i class="bi bi-download"></i></a>
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
