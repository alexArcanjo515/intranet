<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];
$uid = (int)$me['id'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: my_requests.php'); exit(); }

$errors = [];$ok=false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add_comment') {
    $body = trim($_POST['body'] ?? '');
    if ($body === '') { $errors[] = 'Comentário vazio.'; }
    if (!$errors) {
      try{
        // is_internal = 0 no portal
        $c = $pdo->prepare('INSERT INTO project_request_comments (request_id, user_id, body, is_internal) VALUES (:r,:u,:b,0)');
        $c->execute([':r'=>$id, ':u'=>$uid, ':b'=>$body]);
        $ok = true;
      }catch(Throwable $e){ $errors[] = 'Falha ao comentar.'; }
    }
  }
}

$stmt = $pdo->prepare('SELECT pr.*, COALESCE(u.name,u.username) as requester_name FROM project_requests pr JOIN users u ON u.id=pr.requester_id WHERE pr.id=:id');
$stmt->execute([':id'=>$id]);
$pr = $stmt->fetch();
if (!$pr) { header('Location: my_requests.php'); exit(); }
if ((int)$pr['requester_id'] !== $uid) { header('Location: my_requests.php'); exit(); }

$details = [];
try { if (is_string($pr['details']) && $pr['details']!=='') { $details = json_decode((string)$pr['details'], true) ?: []; } } catch (Throwable $e) { $details = []; }

// Comentários (somente públicos no portal)
$comments = [];
try {
  $cs = $pdo->prepare('SELECT c.*, COALESCE(u.name,u.username) AS author FROM project_request_comments c JOIN users u ON u.id=c.user_id WHERE c.request_id=:r AND c.is_internal=0 ORDER BY c.id DESC');
  $cs->execute([':r'=>$id]);
  $comments = $cs->fetchAll();
} catch (Throwable $e) { $comments = []; }
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
  <body class="min-vh-100 d-flex flex-column">
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
          
          <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="max-width: 980px;">
      <h1 class="h5 mb-3">Solicitação #<?= (int)$pr['id'] ?></h1>
      <?php if ($ok): ?><div class="alert alert-success">Comentário publicado.</div><?php endif; ?>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

      <div class="card glass mb-3">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-8"><strong><?= htmlspecialchars($pr['title']) ?></strong></div>
            <div class="col-6 col-md-2"><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= htmlspecialchars($pr['priority']) ?></span></div>
            <div class="col-6 col-md-2"><span class="badge bg-info-subtle text-info border border-info-subtle"><?= htmlspecialchars($pr['status']) ?></span></div>
            <div class="col-12 small text-secondary">Criada em <?= htmlspecialchars($pr['created_at']) ?></div>
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

      <div class="card glass">
        <div class="card-body">
          <h2 class="h6 mb-3">Comentários</h2>
          <?php if (!$comments): ?><div class="text-secondary small">Sem comentários.</div><?php endif; ?>
          <ul class="list-unstyled">
            <?php foreach ($comments as $c): ?>
              <li class="mb-2">
                <div class="small"><strong><?= htmlspecialchars($c['author']) ?></strong> · <?= htmlspecialchars($c['created_at']) ?></div>
                <div><?= nl2br(htmlspecialchars((string)$c['body'])) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <form method="post" class="d-flex flex-column gap-2">
            <input type="hidden" name="action" value="add_comment">
            <textarea name="body" class="form-control" rows="3" placeholder="Adicionar comentário..."></textarea>
            <button class="btn btn-outline-light btn-sm" type="submit">Comentar</button>
          </form>
        </div>
      </div>
    </main>
  </body>
</html>
