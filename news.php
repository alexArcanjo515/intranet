<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_news'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
$pub = isset($_GET['pub']) ? trim($_GET['pub']) : '';

$where = [];
$params = [];
if ($q !== '') { $where[] = '(n.title LIKE :q OR n.content LIKE :q)'; $params[':q'] = "%$q%"; }
if ($pin === '1') { $where[] = 'n.is_pinned = 1'; }
if ($pin === '0') { $where[] = 'n.is_pinned = 0'; }
if ($pub === '1') { $where[] = 'n.is_published = 1'; }
if ($pub === '0') { $where[] = 'n.is_published = 0'; }
$wsql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$sql = "SELECT n.id, n.title, n.content, n.is_pinned, COALESCE(n.is_published,1) AS is_published, n.created_at, u.username AS author
        FROM news n
        LEFT JOIN users u ON u.id = n.created_by
        $wsql
        ORDER BY n.is_pinned DESC, n.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notícias - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .badge-soft { background: #0d6efd22; color: #93c5fd; border: 1px solid #0d6efd55; }
      .content-trunc {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-clamp: 2;
      }
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
            <li class="nav-item"><a class="nav-link active" href="news.php"><i class="bi bi-megaphone me-1"></i>Notícias</a></li>
          </ul>
          <div class="d-flex align-items-center gap-2">
            <a href="news_form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Novo</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
          </div>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-0">Notícias</h1>
          <small class="text-secondary">Comunicados internos e avisos</small>
        </div>
        <form class="d-flex gap-2" method="get">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" placeholder="Buscar por título ou conteúdo" value="<?= htmlspecialchars($q) ?>">
          </div>
          <select name="pin" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="1" <?= $pin==='1'?'selected':'' ?>>Fixadas</option>
            <option value="0" <?= $pin==='0'?'selected':'' ?>>Não fixadas</option>
          </select>
          <select name="pub" class="form-select" style="max-width:180px">
            <option value="">Publicadas e rascunhos</option>
            <option value="1" <?= $pub==='1'?'selected':'' ?>>Publicadas</option>
            <option value="0" <?= $pub==='0'?'selected':'' ?>>Rascunhos</option>
          </select>
          <button class="btn btn-outline-light" type="submit">Filtrar</button>
        </form>
      </div>

      <div class="row g-3">
        <?php foreach ($items as $n): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card glass h-100">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h2 class="h5 mb-0">
                    <?= htmlspecialchars($n['title']) ?>
                    <?php if ((int)$n['is_pinned'] === 1): ?><i class="bi bi-pin-angle-fill text-warning ms-1" title="Fixada"></i><?php endif; ?>
                  </h2>
                  <span class="badge <?= (int)$n['is_published']===1 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?>">
                    <?= (int)$n['is_published']===1 ? 'Publicado' : 'Rascunho' ?>
                  </span>
                </div>
                <div class="content-trunc text-secondary mb-3"><?= htmlspecialchars(mb_substr(strip_tags($n['content']), 0, 180)) ?><?= mb_strlen(strip_tags($n['content']))>180?'…':'' ?></div>
                <div class="mt-auto d-flex justify-content-between align-items-center">
                  <small class="text-secondary">Por <?= htmlspecialchars($n['author'] ?? '—') ?> • <?= htmlspecialchars($n['created_at']) ?></small>
                  <div class="btn-group">
                    <a href="news_view.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                    <a href="news_form.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-light" title="Editar"><i class="bi bi-pencil-square"></i></a>
                    <a href="news_toggle.php?id=<?= (int)$n['id'] ?>&act=pin" class="btn btn-sm btn-outline-warning" title="Fixar/Desafixar"><i class="bi bi-pin"></i></a>
                    <a href="news_toggle.php?id=<?= (int)$n['id'] ?>&act=pub" class="btn btn-sm btn-outline-info" title="Publicar/Arquivar"><i class="bi bi-broadcast"></i></a>
                    <a href="news_delete.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta notícia?');" title="Excluir"><i class="bi bi-trash"></i></a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
