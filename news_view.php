<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: news.php'); exit(); }

$stmt = $pdo->prepare('SELECT n.id, n.title, n.content, COALESCE(n.is_published,1) AS is_published, n.is_pinned, n.created_at, u.username AS author FROM news n LEFT JOIN users u ON u.id = n.created_by WHERE n.id = :id');
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();
if (!$item) { header('Location: news.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($item['title']) ?> - Notícias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .content-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,.06); }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="news.php">
          <i class="bi bi-megaphone text-primary"></i><span>Notícias</span>
        </a>
        <div class="d-flex ms-auto gap-2">
          <a class="btn btn-outline-light btn-sm" href="news.php"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="max-width: 920px;">
      <header class="mb-3 d-flex justify-content-between align-items-start">
        <div>
          <h1 class="h3 mb-1 d-flex align-items-center gap-2">
            <?= htmlspecialchars($item['title']) ?>
            <?php if ((int)$item['is_pinned'] === 1): ?><i class="bi bi-pin-angle-fill text-warning" title="Fixada"></i><?php endif; ?>
          </h1>
          <div class="text-secondary small">Por <?= htmlspecialchars($item['author'] ?? '—') ?> • <?= htmlspecialchars($item['created_at']) ?></div>
        </div>
        <span class="badge <?= (int)$item['is_published']===1 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?>">
          <?= (int)$item['is_published']===1 ? 'Publicado' : 'Rascunho' ?>
        </span>
      </header>

      <article class="content-box rounded p-4">
        <?= $item['content'] /* conteúdo em HTML (Quill) */ ?>
      </article>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
