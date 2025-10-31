<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_news'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;

    if ($title === '' || $content === '') {
        $error = 'Título e conteúdo são obrigatórios.';
    }

    if ($error === '') {
        try {
            if ($id > 0) {
                $upd = $pdo->prepare('UPDATE news SET title=:t, content=:c, is_pinned=:p, is_published=:pub WHERE id=:id');
                $upd->execute([':t'=>$title, ':c'=>$content, ':p'=>$is_pinned, ':pub'=>$is_published, ':id'=>$id]);
            } else {
                $ins = $pdo->prepare('INSERT INTO news (title, content, is_pinned, is_published, created_by) VALUES (:t,:c,:p,:pub,:u)');
                $ins->execute([':t'=>$title, ':c'=>$content, ':p'=>$is_pinned, ':pub'=>$is_published, ':u'=>(int)$_SESSION['user']['id']]);
            }
            header('Location: news.php');
            exit();
        } catch (Throwable $e) {
            $error = 'Erro ao salvar notícia.';
        }
    }
}

$item = ['title'=>'','content'=>'','is_pinned'=>0,'is_published'=>1];
if ($editing && $error === '') {
    $stmt = $pdo->prepare('SELECT id, title, content, is_pinned, COALESCE(is_published,1) as is_published FROM news WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row) { $item = $row; } else { $editing = false; $id = 0; }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing ? 'Editar Notícia' : 'Nova Notícia' ?> - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
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

    <main class="container py-4" style="max-width:820px;">
      <h1 class="h4 mb-3"><?= $editing ? 'Editar notícia' : 'Nova notícia' ?></h1>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?= htmlspecialchars($error) ?></div></div>
      <?php endif; ?>

      <div class="card glass">
        <div class="card-body">
          <form method="post" novalidate>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="mb-3">
              <label class="form-label" for="title">Título</label>
              <input class="form-control" id="title" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="editor">Conteúdo</label>
              <div id="editor" style="background:#0b1220;" class="form-control p-0">
                <?= $item['content'] /* conteúdo já é html; não escapar para manter formatação */ ?>
              </div>
              <input type="hidden" id="content" name="content" value="<?= htmlspecialchars($item['content']) ?>">
            </div>
            <div class="row g-3 mb-3">
              <div class="col-auto form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="is_pinned" name="is_pinned" <?= ((int)$item['is_pinned']===1 ? 'checked' : '') ?>>
                <label class="form-check-label" for="is_pinned">Fixar</label>
              </div>
              <div class="col-auto form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" <?= ((int)$item['is_published']===1 ? 'checked' : '') ?>>
                <label class="form-check-label" for="is_published">Publicado</label>
              </div>
            </div>
            <div class="d-flex gap-2">
              <a href="news.php" class="btn btn-outline-light">Cancelar</a>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
    <script>
      (function(){
        var editorEl = document.getElementById('editor');
        var q = new Quill(editorEl, { theme: 'snow', modules: { toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          ['link', 'blockquote', 'code-block'],
          [{ 'align': [] }],
          ['clean']
        ]}});
        var hidden = document.getElementById('content');
        // initialize from hidden
        try { q.root.innerHTML = hidden.value; } catch(e) {}
        // on submit, push html to hidden
        var form = editorEl.closest('form');
        form.addEventListener('submit', function(){ hidden.value = q.root.innerHTML; });
      })();
    </script>
  </body>
</html>
