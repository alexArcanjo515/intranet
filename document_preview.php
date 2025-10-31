<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); exit('Documento não encontrado.'); }

$stmt = $pdo->prepare('SELECT title, path FROM documents WHERE id = :id');
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Documento não encontrado.'); }

$rel = $doc['path'];
$abs = __DIR__ . '/' . $rel;
if (!is_file($abs)) { http_response_code(404); exit('Arquivo não encontrado.'); }

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$canInlineImg = in_array($ext, ['png','jpg','jpeg','gif','webp','bmp','svg'], true);
$canInlinePdf = ($ext === 'pdf');
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview - <?= htmlspecialchars($doc['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      /* Altura quase total da viewport, descontando cabeçalho/margens */
      .preview-box { height: calc(100vh - 160px); display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,.06); }
      .preview-box embed, .preview-box iframe { width: 100%; height: 100%; border: 0; }
      .preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="documents.php"><i class="bi bi-folder2-open text-primary"></i><span>Documentos</span></a>
        <div class="d-flex ms-auto gap-2">
          <a class="btn btn-outline-light btn-sm" href="document_download.php?id=<?= (int)$id ?>"><i class="bi bi-download me-1"></i>Baixar</a>
          <a class="btn btn-primary btn-sm" href="documents.php"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <h1 class="h5 mb-3">Preview: <?= htmlspecialchars($doc['title']) ?></h1>
      <div class="preview-box p-3 rounded">
        <?php if ($canInlineImg): ?>
          <img src="<?= htmlspecialchars($rel) ?>" alt="Preview">
        <?php elseif ($canInlinePdf): ?>
          <embed src="<?= htmlspecialchars($rel) ?>" type="application/pdf" />
        <?php else: ?>
          <div class="text-center">
            <p class="text-secondary">Preview não suportado para este tipo de arquivo (<?= htmlspecialchars(strtoupper($ext)) ?>).</p>
            <a class="btn btn-outline-light" href="document_download.php?id=<?= (int)$id ?>"><i class="bi bi-download me-1"></i>Baixar arquivo</a>
          </div>
        <?php endif; ?>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
