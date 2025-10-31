<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
// Visualizar histórico: qualquer logado (ou poderíamos exigir permissão específica)

$pdo = require __DIR__ . '/config/db.php';

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($docId <= 0) { header('Location: documents.php'); exit(); }

$doc = $pdo->prepare('SELECT id, title, category FROM documents WHERE id = :id');
$doc->execute([':id' => $docId]);
$docRow = $doc->fetch();
if (!$docRow) { header('Location: documents.php'); exit(); }

$stmt = $pdo->prepare('SELECT id, version, path, uploaded_by, created_at FROM document_versions WHERE document_id = :id ORDER BY version DESC');
$stmt->execute([':id' => $docId]);
$versions = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Histórico de versões - Intranet</title>
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
            <li class="nav-item"><a class="nav-link" href="documents.php"><i class="bi bi-folder2-open me-1"></i>Documentos</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-0">Histórico de versões</h1>
          <small class="text-secondary">Documento: <strong><?= htmlspecialchars($docRow['title']) ?></strong></small>
        </div>
        <a href="documents.php" class="btn btn-outline-light">Voltar</a>
      </div>

      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Versão</th>
                <th>Arquivo</th>
                <th>Autor</th>
                <th>Data</th>
                <th style="width:220px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($versions as $v): ?>
              <tr>
                <td><span class="badge badge-soft">v<?= (int)$v['version'] ?></span></td>
                <td><code><?= htmlspecialchars(basename($v['path'])) ?></code></td>
                <td>
                  <?php if ($v['uploaded_by']): ?>
                    <?php $un = $pdo->query('SELECT username FROM users WHERE id='.(int)$v['uploaded_by'])->fetchColumn(); echo htmlspecialchars($un ?: '—'); ?>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td><span class="text-secondary small"><?= htmlspecialchars($v['created_at']) ?></span></td>
                <td>
                  <div class="btn-group">
                    <a href="download_version.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-download"></i></a>
                    <a href="restore_version.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-info" onclick="return confirm('Restaurar esta versão como a atual?');"><i class="bi bi-arrow-counterclockwise"></i></a>
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
