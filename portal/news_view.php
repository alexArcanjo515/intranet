<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: news.php'); exit(); }

$stmt = $pdo->prepare("SELECT id, title, content, created_at FROM news WHERE id=:id AND COALESCE(is_published,1)=1");
$stmt->execute([':id'=>$id]);
$news = $stmt->fetch();
if (!$news) { header('Location: news.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($news['title']) ?> - Notícias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"> <?= render_brand_html(__DIR__ . '/../assets/logotipo.png'); ?></a>
        <div class="ms-auto"><a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a></div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="mb-3"><a class="btn btn-outline-light btn-sm" href="news.php"><i class="bi bi-arrow-left"></i> Voltar</a></div>
      <div class="card glass">
        <div class="card-body">
          <h1 class="h4 mb-2"><?= htmlspecialchars($news['title']) ?></h1>
          <div class="text-secondary small mb-3"><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($news['created_at']) ?></div>
          <div><?= $news['content'] ?></div>
        </div>
      </div>
    </main>
  </body>
</html>
