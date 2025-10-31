<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['manage_projects'], ['admin']);
$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: project_requests.php'); exit(); }

// Ações: alterar status
$errors = [];$ok=false;$createdProjectId=0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $new = $_POST['new_status'] ?? '';
  if ($action === 'change_status' && in_array($new, ['pending','approved','rejected','in_progress','done'], true)) {
    try{
      $stmt = $pdo->prepare('UPDATE project_requests SET status=:s WHERE id=:id');
      $stmt->execute([':s'=>$new, ':id'=>$id]);
      $ok = true;
      // Notificar solicitante
      try{
        $rqUser = $pdo->prepare('SELECT requester_id, title FROM project_requests WHERE id=:id');
        $rqUser->execute([':id'=>$id]);
        $rqi = $rqUser->fetch();
        if ($rqi && !empty($rqi['requester_id'])) {
          $n = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u,:t,:ti,:b,:l)');
          $n->execute([':u'=>(int)$rqi['requester_id'], ':t'=>'project_request', ':ti'=>'Solicitação atualizada', ':b'=>$rqi['title'] . ' → ' . $new, ':l'=>'portal/my_requests.php']);
        }
      }catch(Throwable $e){}
    }catch(Throwable $e){ $errors[] = 'Falha ao atualizar status.'; }
  }
  if ($action === 'add_comment') {
    $body = trim($_POST['body'] ?? '');
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    if ($body === '') { $errors[] = 'Comentário vazio.'; }
    if (!$errors) {
      try{
        $c = $pdo->prepare('INSERT INTO project_request_comments (request_id, user_id, body, is_internal) VALUES (:r,:u,:b,:i)');
        $c->execute([':r'=>$id, ':u'=>($_SESSION['user']['id'] ?? 0), ':b'=>$body, ':i'=>$is_internal]);
        $ok = true;
      }catch(Throwable $e){ $errors[] = 'Falha ao comentar.'; }
    }
  }
  if ($action === 'create_project') {
    try{
      $rq = $pdo->prepare('SELECT title, summary FROM project_requests WHERE id=:id');
      $rq->execute([':id'=>$id]);
      $rqv = $rq->fetch();
      if ($rqv) {
        $ins = $pdo->prepare('INSERT INTO projects (name, objective, description, status, visibility, repo_url, repo_branch, ci_url, created_by, request_id) VALUES (:n,:o,:d,\'planned\',\'team\', NULL, NULL, NULL, :cb, :rid)');
        $ins->execute([':n'=>$rqv['title'], ':o'=>$rqv['summary']??null, ':d'=>null, ':cb'=>($_SESSION['user']['id'] ?? null), ':rid'=>$id]);
        $createdProjectId = (int)$pdo->lastInsertId();
        // Atualiza status da solicitação
        $pdo->prepare('UPDATE project_requests SET status=\'approved\' WHERE id=:id')->execute([':id'=>$id]);
        // Notificar solicitante
        try{
          $rqUser = $pdo->prepare('SELECT requester_id, title FROM project_requests WHERE id=:id');
          $rqUser->execute([':id'=>$id]);
          $rqi = $rqUser->fetch();
          if ($rqi && !empty($rqi['requester_id'])) {
            $n = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u,:t,:ti,:b,:l)');
            $n->execute([':u'=>(int)$rqi['requester_id'], ':t'=>'project', ':ti'=>'Projeto criado', ':b'=>$rqi['title'], ':l'=>'portal/project_view.php?id=' . $createdProjectId]);
          }
        }catch(Throwable $e){}
        header('Location: project_view.php?id=' . $createdProjectId);
        exit();
      }
    }catch(Throwable $e){ $errors[] = 'Falha ao criar projeto.'; }
  }
}

$stmt = $pdo->prepare('SELECT pr.*, COALESCE(u.name,u.username) as requester_name FROM project_requests pr JOIN users u ON u.id=pr.requester_id WHERE pr.id=:id');
$stmt->execute([':id'=>$id]);
$pr = $stmt->fetch();
if (!$pr) { header('Location: project_requests.php'); exit(); }

$details = [];
try {
  if (is_string($pr['details']) && $pr['details'] !== '') { $details = json_decode((string)$pr['details'], true) ?: []; }
} catch (Throwable $e) { $details = []; }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitação #<?= (int)$id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-speedometer2"></i> <?= render_brand_html(); ?></a>
        <div class="ms-auto"><a class="btn btn-outline-light btn-sm" href="project_requests.php">Voltar</a></div>
      </div>
    </nav>

    <main class="container py-4" style="max-width: 980px;">
      <h1 class="h5 mb-3">Solicitação #<?= (int)$pr['id'] ?></h1>
      <?php if ($ok): ?><div class="alert alert-success">Status atualizado.</div><?php endif; ?>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

      <div class="card glass mb-3">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-8"><strong><?= htmlspecialchars($pr['title']) ?></strong></div>
            <div class="col-6 col-md-2"><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= htmlspecialchars($pr['priority']) ?></span></div>
            <div class="col-6 col-md-2"><span class="badge bg-info-subtle text-info border border-info-subtle"><?= htmlspecialchars($pr['status']) ?></span></div>
            <div class="col-12 small text-secondary">Solicitante: <?= htmlspecialchars($pr['requester_name']) ?> · <?= htmlspecialchars($pr['created_at']) ?></div>
          </div>
          <hr class="text-secondary">
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="mb-2"><span class="text-secondary">Categoria:</span> <strong><?= htmlspecialchars($pr['category']) ?></strong></div>
              <div class="mb-2"><span class="text-secondary">Resumo:</span><br><?= nl2br(htmlspecialchars((string)$pr['summary'])) ?></div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="mb-2"><span class="text-secondary">Detalhes:</span></div>
              <pre class="small bg-dark text-light p-2 rounded" style="white-space:pre-wrap"><?= htmlspecialchars(json_encode($details ?: new stdClass(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre>
            </div>
          </div>
        </div>
      </div>

      <form method="post" class="card glass p-3 d-flex gap-2 align-items-start">
        <input type="hidden" name="action" value="change_status">
        <select class="form-select" name="new_status" style="max-width:240px">
          <?php foreach (['pending'=>'Pendente','approved'=>'Aprovado','rejected'=>'Rejeitado','in_progress'=>'Em andamento','done'=>'Concluído'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $pr['status']===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Atualizar Status</button>
        <button class="btn btn-success" name="action" value="create_project"><i class="bi bi-plus-circle"></i> Criar Projeto</button>
      </form>

      <div class="card glass mt-3">
        <div class="card-body">
          <h2 class="h6 mb-3">Comentários</h2>
          <?php if (!$comments): ?><div class="text-secondary small">Sem comentários.</div><?php endif; ?>
          <ul class="list-unstyled">
            <?php foreach ($comments as $c): ?>
              <li class="mb-2">
                <div class="small"><strong><?= htmlspecialchars($c['author']) ?></strong> · <?= htmlspecialchars($c['created_at']) ?> <?= (int)$c['is_internal']===1?'<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Interno</span>':'' ?></div>
                <div><?= nl2br(htmlspecialchars((string)$c['body'])) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <form method="post" class="d-flex flex-column gap-2">
            <input type="hidden" name="action" value="add_comment">
            <textarea name="body" class="form-control" rows="3" placeholder="Adicionar comentário..."></textarea>
            <label class="form-check small"><input class="form-check-input" type="checkbox" name="is_internal" checked> <span class="form-check-label">Marcar como interno (visível apenas no Admin)</span></label>
            <button class="btn btn-outline-light btn-sm" type="submit">Comentar</button>
          </form>
        </div>
      </div>

    </main>
  </body>
</html>
