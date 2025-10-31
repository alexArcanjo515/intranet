<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];

$q = trim($_GET['q'] ?? '');
$results = ['news'=>[], 'documents'=>[], 'projects'=>[], 'tasks'=>[]];

if ($q !== '') {
  try {
    // Notícias publicadas
    $st = $pdo->prepare("SELECT id, title, created_at FROM news WHERE COALESCE(is_published,1)=1 AND title LIKE :q ORDER BY is_pinned DESC, created_at DESC LIMIT 10");
    $st->execute([':q'=>"%$q%"]); $results['news'] = $st->fetchAll();
  } catch (Throwable $e) {}
  try {
    // Documentos por título/categoria
    $st = $pdo->prepare("SELECT id, title, category, created_at FROM documents WHERE (title LIKE :q OR category LIKE :q) ORDER BY created_at DESC LIMIT 10");
    $st->execute([':q'=>"%$q%"]); $results['documents'] = $st->fetchAll();
  } catch (Throwable $e) {}
  try {
    // Projetos (somente se membro)
    $st = $pdo->prepare("SELECT p.id, p.name, p.objective, p.status FROM projects p WHERE EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id=p.id AND pm.user_id=:u) AND (p.name LIKE :q OR p.objective LIKE :q) ORDER BY p.created_at DESC LIMIT 10");
    $st->execute([':u'=>(int)$me['id'], ':q'=>"%$q%"]); $results['projects'] = $st->fetchAll();
  } catch (Throwable $e) {}
  try {
    // Tarefas (somente projetos onde é membro)
    $st = $pdo->prepare("SELECT t.id, t.title, t.status, t.project_id, p.name AS project_name FROM project_tasks t JOIN projects p ON p.id=t.project_id WHERE (t.title LIKE :q OR t.description LIKE :q) AND EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id=t.project_id AND pm.user_id=:u) ORDER BY t.id DESC LIMIT 10");
    $st->execute([':q'=>"%$q%", ':u'=>(int)$me['id']]); $results['tasks'] = $st->fetchAll();
  } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Busca - Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-building"></i> <?= render_brand_html(); ?></a>
        <form class="d-flex ms-auto" method="get" action="search.php" style="max-width:340px">
          <input class="form-control form-control-sm me-2" type="search" placeholder="Pesquisar…" name="q" value="<?= htmlspecialchars($q) ?>">
          <button class="btn btn-sm btn-outline-light">Buscar</button>
        </form>
      </div>
    </nav>

    <main class="container py-4">
      <h1 class="h5 mb-3">Resultados da busca</h1>
      <form class="mb-3" method="get" action="search.php">
        <div class="input-group">
          <input class="form-control" name="q" placeholder="Digite para pesquisar notícias, documentos, projetos e tarefas" value="<?= htmlspecialchars($q) ?>">
          <button class="btn btn-outline-light">Buscar</button>
        </div>
      </form>

      <?php if ($q===''): ?>
        <div class="text-secondary">Digite um termo para pesquisar.</div>
      <?php else: ?>
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="card glass h-100"><div class="card-body">
              <h2 class="h6 mb-2">Notícias</h2>
              <?php if (!$results['news']): ?><div class="text-secondary small">Sem resultados.</div><?php endif; ?>
              <ul class="mb-0">
                <?php foreach ($results['news'] as $n): ?>
                  <li><a href="news_view.php?id=<?= (int)$n['id'] ?>" class="link-light text-decoration-none"><?= htmlspecialchars($n['title']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div></div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card glass h-100"><div class="card-body">
              <h2 class="h6 mb-2">Documentos</h2>
              <?php if (!$results['documents']): ?><div class="text-secondary small">Sem resultados.</div><?php endif; ?>
              <ul class="mb-0">
                <?php foreach ($results['documents'] as $d): ?>
                  <li><a href="documents.php?q=<?= urlencode($d['title']) ?>" class="link-light text-decoration-none"><?= htmlspecialchars($d['title']) ?></a> <span class="text-secondary small"><?= htmlspecialchars($d['category'] ?? '') ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div></div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card glass h-100"><div class="card-body">
              <h2 class="h6 mb-2">Projetos</h2>
              <?php if (!$results['projects']): ?><div class="text-secondary small">Sem resultados.</div><?php endif; ?>
              <ul class="mb-0">
                <?php foreach ($results['projects'] as $p): ?>
                  <li><a href="project_view.php?id=<?= (int)$p['id'] ?>" class="link-light text-decoration-none"><?= htmlspecialchars($p['name']) ?></a> <span class="text-secondary small"><?= htmlspecialchars($p['status']) ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div></div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card glass h-100"><div class="card-body">
              <h2 class="h6 mb-2">Tarefas</h2>
              <?php if (!$results['tasks']): ?><div class="text-secondary small">Sem resultados.</div><?php endif; ?>
              <ul class="mb-0">
                <?php foreach ($results['tasks'] as $t): ?>
                  <li><a href="project_view.php?id=<?= (int)$t['project_id'] ?>" class="link-light text-decoration-none"><?= htmlspecialchars($t['title']) ?></a> <span class="text-secondary small">[<?= htmlspecialchars($t['status']) ?> · <?= htmlspecialchars($t['project_name']) ?>]</span></li>
                <?php endforeach; ?>
              </ul>
            </div></div>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </body>
</html>
