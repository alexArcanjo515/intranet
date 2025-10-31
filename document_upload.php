<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_documents'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // se presente, submeter nova versão
$error = '';
$success = '';

$doc = ['title' => '', 'category' => ''];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id, title, category, version FROM documents WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $found = $stmt->fetch();
    if ($found) { $doc = $found; } else { $id = 0; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $docId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($title === '') {
        $error = 'Título é obrigatório.';
    }
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = $error ?: 'Arquivo é obrigatório.';
    }

    if ($error === '') {
        try {
            $uploadBase = __DIR__ . '/storage/uploads/documents';
            if (!is_dir($uploadBase)) {
                mkdir($uploadBase, 0775, true);
            }

            $orig = $_FILES['file']['name'];
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $safeTitle = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $title);
            $ts = date('Ymd_His');

            if ($docId > 0) {
                // nova versão
                $stmt = $pdo->prepare('SELECT id, version FROM documents WHERE id = :id');
                $stmt->execute([':id' => $docId]);
                $row = $stmt->fetch();
                if (!$row) { $error = 'Documento não encontrado.'; }
                $newVersion = ((int)($row['version'] ?? 0)) + 1;
                $filename = $safeTitle . '_v' . $newVersion . '_' . $ts . ($ext ? ('.' . $ext) : '');
                $dest = $uploadBase . '/' . $filename;
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    $error = 'Falha no upload do arquivo.';
                } else {
                    $relPath = 'storage/uploads/documents/' . $filename;
                    $upd = $pdo->prepare('UPDATE documents SET path = :p, version = :v, category = :c WHERE id = :id');
                    $upd->execute([':p' => $relPath, ':v' => $newVersion, ':c' => $category, ':id' => $docId]);

                    // registrar versão
                    $iv = $pdo->prepare('INSERT INTO document_versions (document_id, version, path, uploaded_by) VALUES (:d, :v, :p, :u)');
                    $iv->execute([':d' => $docId, ':v' => $newVersion, ':p' => $relPath, ':u' => (int)$_SESSION['user']['id']]);
                    header('Location: documents.php');
                    exit();
                }
            } else {
                // novo documento
                $filename = $safeTitle . '_v1_' . $ts . ($ext ? ('.' . $ext) : '');
                $dest = $uploadBase . '/' . $filename;
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    $error = 'Falha no upload do arquivo.';
                } else {
                    $relPath = 'storage/uploads/documents/' . $filename;
                    $ins = $pdo->prepare('INSERT INTO documents (title, path, category, version, created_by) VALUES (:t, :p, :c, 1, :u)');
                    $ins->execute([':t' => $title, ':p' => $relPath, ':c' => $category, ':u' => (int)$_SESSION['user']['id']]);
                    $docIdNew = (int)$pdo->lastInsertId();
                    // registrar versão 1
                    $iv = $pdo->prepare('INSERT INTO document_versions (document_id, version, path, uploaded_by) VALUES (:d, 1, :p, :u)');
                    $iv->execute([':d' => $docIdNew, ':p' => $relPath, ':u' => (int)$_SESSION['user']['id']]);
                    header('Location: documents.php');
                    exit();
                }
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar documento.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id>0 ? 'Nova versão' : 'Novo documento' ?> - Intranet</title>
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
      </div>
    </nav>

    <main class="container py-4" style="max-width:720px;">
      <h1 class="h4 mb-3"><?= $id>0 ? 'Enviar nova versão' : 'Novo documento' ?></h1>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="mb-3">
              <label class="form-label" for="title">Título</label>
              <input class="form-control" id="title" name="title" value="<?= htmlspecialchars($doc['title'] ?? '') ?>" required <?= $id>0 ? 'readonly' : '' ?>>
              <?php if ($id>0): ?><div class="form-text text-secondary">O título é fixo; envie apenas o arquivo e opcionalmente altere a categoria.</div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label" for="category">Categoria</label>
              <input class="form-control" id="category" name="category" value="<?= htmlspecialchars($doc['category'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="file">Arquivo</label>
              <input type="file" class="form-control" id="file" name="file" required>
            </div>
            <div class="d-flex gap-2">
              <a href="documents.php" class="btn btn-outline-light">Cancelar</a>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
