<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];
$uid = (int)$me['id'];

$st = trim($_GET['status'] ?? '');
$where = 'WHERE requester_id = :u';
$params = [':u'=>$uid];
if ($st !== '') { $where .= ' AND status = :s'; $params[':s']=$st; }

$stmt = $pdo->prepare("SELECT id, title, category, priority, status, created_at FROM project_requests $where ORDER BY created_at DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Solicitações</title>
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

    <main class="container py-4">
      <h1 class="h5 mb-3">Minhas Solicitações</h1>
      <form class="row g-2 mb-3">
        <div class="col-12 col-md-3">
          <select class="form-select" name="status" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <?php foreach (['pending'=>'Pendente','approved'=>'Aprovado','rejected'=>'Rejeitado','in_progress'=>'Em andamento','done'=>'Concluído'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $st===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Data</th><th></th></tr></thead>
            <tbody>
              <?php if (!$rows): ?><tr><td class="text-secondary" colspan="6">Nenhum registro.</td></tr><?php endif; ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['title']) ?></td>
                  <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($r['category']) ?></span></td>
                  <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= htmlspecialchars($r['priority']) ?></span></td>
                  <td><span class="badge bg-info-subtle text-info border border-info-subtle"><?= htmlspecialchars($r['status']) ?></span></td>
                  <td class="small text-secondary"><?= htmlspecialchars($r['created_at']) ?></td>
                  <td><a class="btn btn-sm btn-outline-primary" href="request_view.php?id=<?= (int)$r['id'] ?>">Abrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </body>
</html>
