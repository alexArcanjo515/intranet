<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];

$filter = $_GET['status'] ?? '';
$valid = ['todo','doing','review','blocked','done'];

// Ação: mudar status (somente se membro do projeto da tarefa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $taskId = (int)($_POST['task_id'] ?? 0);
  $newStatus = $_POST['status'] ?? '';
  if ($taskId > 0 && in_array($newStatus, $valid, true)) {
    try {
      $chk = $pdo->prepare('SELECT t.project_id FROM project_tasks t WHERE t.id=:id');
      $chk->execute([':id'=>$taskId]);
      $pid = (int)$chk->fetchColumn();
      if ($pid) {
        $isMember = $pdo->prepare('SELECT 1 FROM project_members WHERE project_id=:p AND user_id=:u');
        $isMember->execute([':p'=>$pid, ':u'=>(int)$me['id']]);
        if ($isMember->fetchColumn()) {
          $up = $pdo->prepare('UPDATE project_tasks SET status=:s WHERE id=:id');
          $up->execute([':s'=>$newStatus, ':id'=>$taskId]);
        }
      }
    } catch (Throwable $e) {}
  }
  header('Location: my_tasks.php');
  exit();
}

$where = '';$params=[];
if (in_array($filter, $valid, true)) { $where = 'AND t.status=:s'; $params[':s']=$filter; }

$sql = "SELECT t.*, p.name AS project_name, COALESCE(u.name,u.username) AS assignee_name
        FROM project_tasks t
        JOIN projects p ON p.id=t.project_id
        LEFT JOIN users u ON u.id=t.assignee_id
        WHERE EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id=t.project_id AND pm.user_id=:uid)
        $where
        ORDER BY t.status, t.due_date IS NULL, t.due_date, t.id DESC";
$stmt = $pdo->prepare($sql);
$params[':uid']=(int)$me['id'];
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Tarefas - Portal</title>
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
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h5 mb-0">Minhas Tarefas</h1>
        <form class="d-flex" method="get">
          <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
            <option value="">Todos status</option>
            <?php foreach ($valid as $s): ?>
              <option value="<?= $s ?>" <?= $filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div class="row g-3">
        <?php foreach ($tasks as $t): ?>
        <div class="col-12">
          <div class="card glass">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <strong><?= htmlspecialchars($t['title']) ?></strong>
                  <div class="text-secondary small">Projeto: <?= htmlspecialchars($t['project_name']) ?> · Responsável: <?= htmlspecialchars($t['assignee_name'] ?: '—') ?> · Entrega: <?= htmlspecialchars($t['due_date'] ?: '—') ?></div>
                </div>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($t['status']) ?></span>
              </div>
              <div class="mt-2 d-flex gap-2">
                <form method="post" class="d-inline-flex gap-1">
                  <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                  <select class="form-select form-select-sm" name="status">
                    <?php foreach ($valid as $s): ?>
                      <option value="<?= $s ?>" <?= $s===$t['status']?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-outline-primary">Atualizar</button>
                </form>
                <a class="btn btn-sm btn-outline-light" href="project_view.php?id=<?= (int)$t['project_id'] ?>">Abrir projeto</a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><div class="col-12 text-secondary">Nenhuma tarefa.</div><?php endif; ?>
      </div>
    </main>
  </body>
</html>
