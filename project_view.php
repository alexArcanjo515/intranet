<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['view_projects'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: projects.php'); exit(); }

// Load project
$stmt = $pdo->prepare('SELECT p.*, COALESCE(u.name,u.username) AS creator_name FROM projects p LEFT JOIN users u ON u.id=p.created_by WHERE p.id=:id');
$stmt->execute([':id'=>$id]);
$project = $stmt->fetch();
if (!$project) { header('Location: projects.php'); exit(); }

$u = $_SESSION['user'] ?? [];
$canManage = in_array('manage_projects', ($u['perms'] ?? []), true) || in_array('admin', ($u['roles'] ?? []), true);

// Helper: criar notificação para um usuário
function notify(PDO $pdo, int $userId, string $title, string $body = '', string $link = ''): void {
  if ($userId <= 0) return;
  try {
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u, :t, :ti, :b, :l)');
    $stmt->execute([':u'=>$userId, ':t'=>'project', ':ti'=>$title, ':b'=>$body, ':l'=>$link]);
  } catch (Throwable $e) { /* ignore */ }
}

// Users for assignment
$users = $pdo->query('SELECT id, COALESCE(name, username) AS label FROM users WHERE is_active=1 ORDER BY label')->fetchAll();

// Handle actions: add task, update status, assign, delete task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'add_task') {
      $title = trim($_POST['title'] ?? '');
      $desc = trim($_POST['description'] ?? '');
      $assignee = isset($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null;
      $due = $_POST['due_date'] ?? null;
      if ($title !== '') {
        $pos = (int)$pdo->query('SELECT COALESCE(MAX(position),0)+1 FROM project_tasks WHERE project_id=' . (int)$id)->fetchColumn();
        $ins = $pdo->prepare('INSERT INTO project_tasks (project_id, title, description, assignee_id, due_date, position) VALUES (:p,:t,:d,:a,:due,:pos)');
        $ins->execute([':p'=>$id, ':t'=>$title, ':d'=>$desc?:null, ':a'=>$assignee?:null, ':due'=>$due?:null, ':pos'=>$pos]);
        if ($assignee) {
          notify($pdo, (int)$assignee, 'Nova tarefa em ' . (string)$project['name'], $title, 'portal/project_view.php?id=' . (int)$id);
        }
      }
    } elseif ($action === 'update_status') {
      $taskId = (int)($_POST['task_id'] ?? 0);
      $status = $_POST['status'] ?? '';
      if (in_array($status, ['todo','doing','review','done','blocked'], true)) {
        $up = $pdo->prepare('UPDATE project_tasks SET status=:s WHERE id=:id AND project_id=:p');
        $up->execute([':s'=>$status, ':id'=>$taskId, ':p'=>$id]);
        // Notificar responsável atual da tarefa, se houver
        try {
          $aid = $pdo->prepare('SELECT assignee_id, title FROM project_tasks WHERE id=:id');
          $aid->execute([':id'=>$taskId]);
          $row = $aid->fetch();
          if ($row && !empty($row['assignee_id'])) {
            notify($pdo, (int)$row['assignee_id'], 'Status atualizado em ' . (string)$project['name'], ($row['title'] ?? '') . ' → ' . $status, 'portal/project_view.php?id=' . (int)$id);
          }
        } catch (Throwable $e) {}
      }
    } elseif ($action === 'assign_user') {
      $taskId = (int)($_POST['task_id'] ?? 0);
      $assignee = isset($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null;
      $up = $pdo->prepare('UPDATE project_tasks SET assignee_id=:a WHERE id=:id AND project_id=:p');
      $up->execute([':a'=>$assignee?:null, ':id'=>$taskId, ':p'=>$id]);
      if ($assignee) {
        try {
          $t = $pdo->prepare('SELECT title FROM project_tasks WHERE id=:id');
          $t->execute([':id'=>$taskId]);
          $tt = (string)($t->fetchColumn() ?: 'Tarefa');
          notify($pdo, (int)$assignee, 'Atribuído em ' . (string)$project['name'], $tt, 'portal/project_view.php?id=' . (int)$id);
        } catch (Throwable $e) {}
      }
    } elseif ($action === 'delete_task') {
      $taskId = (int)($_POST['task_id'] ?? 0);
      $pdo->prepare('DELETE FROM project_tasks WHERE id=:id AND project_id=:p')->execute([':id'=>$taskId, ':p'=>$id]);
    } elseif ($action === 'update_project') {
      $status = $_POST['p_status'] ?? $project['status'];
      $due = $_POST['p_due_date'] ?? $project['due_date'];
      $up = $pdo->prepare('UPDATE projects SET status=:s, due_date=:d WHERE id=:id');
      $up->execute([':s'=>$status, ':d'=>$due?:null, ':id'=>$id]);
    }
  } catch (Throwable $e) {}
  header('Location: project_view.php?id=' . $id);
  exit();
}

// Load members
$members = $pdo->prepare('SELECT u.id, COALESCE(u.name,u.username) AS label FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=:p ORDER BY pm.position ASC');
$members->execute([':p'=>$id]);
$members = $members->fetchAll();

// Load environments
$envStmt = $pdo->prepare('SELECT name, url, health_url, position FROM project_envs WHERE project_id=:p ORDER BY position ASC, name ASC');
$envStmt->execute([':p'=>$id]);
$envs = $envStmt->fetchAll();

// Resumo de ambientes (X/Y online e média de latência, últimos logs)
$envSummary = ['online'=>0,'total'=>count($envs),'avg'=>null];
if ($envs) {
  try {
    $qLast = $pdo->prepare('SELECT up, latency_ms FROM project_env_status_log WHERE env_id = :e ORDER BY id DESC LIMIT 1');
    $sum = 0; $cnt = 0;
    // precisamos do id; recarregar com id
    $envIdStmt = $pdo->prepare('SELECT id FROM project_envs WHERE project_id=:p AND name=:n AND COALESCE(url,"")=:u AND COALESCE(health_url,"")=:h LIMIT 1');
    foreach ($envs as $e) {
      $envIdStmt->execute([':p'=>$id, ':n'=>$e['name'], ':u'=>($e['url']??''), ':h'=>($e['health_url']??'')]);
      $eid = (int)($envIdStmt->fetchColumn() ?: 0);
      if ($eid>0) {
        $qLast->execute([':e'=>$eid]);
        $row = $qLast->fetch();
        if ($row) {
          if ((int)$row['up']===1) $envSummary['online']++;
          if ($row['latency_ms'] !== null) { $sum += (int)$row['latency_ms']; $cnt++; }
        }
      }
    }
    if ($cnt>0) $envSummary['avg'] = (int)round($sum/$cnt);
  } catch (Throwable $e) {}
}

// Load tasks grouped
$tasks = $pdo->prepare("SELECT t.*, COALESCE(u.name,u.username) AS assignee_name FROM project_tasks t LEFT JOIN users u ON u.id=t.assignee_id WHERE t.project_id=:p ORDER BY t.position ASC, t.id DESC");
$tasks->execute([':p'=>$id]);
$tasks = $tasks->fetchAll();
$cols = ['todo'=>'A fazer','doing'=>'Fazendo','review'=>'Revisão','blocked'=>'Bloqueado','done'=>'Concluído'];
$by = ['todo'=>[], 'doing'=>[], 'review'=>[], 'blocked'=>[], 'done'=>[]];
foreach ($tasks as $t) { $by[$t['status']][] = $t; }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projeto - <?= htmlspecialchars($project['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}
      .kanban-col{min-height:220px}
      .task{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08)}
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="projects.php"><i class="bi bi-kanban"></i><?= render_brand_html(); ?></a>
        <div class="ms-auto"><a href="projects.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a></div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-0"><?= htmlspecialchars($project['name']) ?></h1>
          <small class="text-secondary">Status: <?= htmlspecialchars($project['status']) ?> · Visibilidade: <?= htmlspecialchars($project['visibility']) ?></small>
        </div>
        <?php if ($canManage): ?>
        <form method="post" class="d-flex align-items-center gap-2">
          <input type="hidden" name="action" value="update_project">
          <select class="form-select form-select-sm" name="p_status">
            <?php foreach(['planned'=>'Planejado','in_progress'=>'Em andamento','blocked'=>'Bloqueado','done'=>'Concluído','archived'=>'Arquivado'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $project['status']===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <input class="form-control form-control-sm" type="date" name="p_due_date" value="<?= htmlspecialchars((string)$project['due_date']) ?>">
          <button class="btn btn-sm btn-primary">Atualizar</button>
        </form>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Objetivo</h2>
              <p class="mb-2"><?= nl2br(htmlspecialchars((string)$project['objective'] ?: '—')) ?></p>
              <h2 class="h6">Descrição</h2>
              <p class="mb-0"><?= nl2br(htmlspecialchars((string)$project['description'] ?: '—')) ?></p>
            </div>
          </div>
          <div class="card glass mt-3">
            <div class="card-body">
              <h2 class="h6 mb-2">DevOps</h2>
              <?php if ($envSummary['total']>0): ?>
              <div class="small text-secondary mb-2">Ambientes: <strong><?= (int)$envSummary['online'] ?>/<?= (int)$envSummary['total'] ?></strong> online<?= $envSummary['avg']!==null? ' · média ' . (int)$envSummary['avg'] . 'ms' : '' ?></div>
              <?php endif; ?>
              <div class="row g-3">
                <div class="col-12 col-md-6 small text-secondary">Repositório: <?php if (!empty($project['repo_url'])): ?><a class="link-light" href="<?= htmlspecialchars((string)$project['repo_url']) ?>" target="_blank" rel="noopener">Abrir</a><?php else: ?>—<?php endif; ?></div>
                <div class="col-6 col-md-3 small text-secondary">Branch: <?= htmlspecialchars((string)($project['repo_branch'] ?: '—')) ?></div>
                <div class="col-6 col-md-3 small text-secondary">CI: <?php if (!empty($project['ci_url'])): ?><a class="link-light" href="<?= htmlspecialchars((string)$project['ci_url']) ?>" target="_blank" rel="noopener">Pipeline</a><?php else: ?>—<?php endif; ?></div>
              </div>
              <?php $bs = (string)($project['build_status'] ?? ''); $bu = (string)($project['build_updated_at'] ?? '');
                $cls = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                if ($bs==='success') $cls = 'bg-success-subtle text-success border border-success-subtle';
                elseif ($bs==='failed') $cls = 'bg-danger-subtle text-danger border border-danger-subtle';
                elseif ($bs==='running' || $bs==='queued') $cls = 'bg-warning-subtle text-warning border border-warning-subtle';
              ?>
              <div class="mt-2 small d-flex align-items-center gap-2">
                <span class="text-secondary">Build:</span>
                <span class="badge <?= $cls ?>"><?= $bs ? htmlspecialchars($bs) : '—' ?></span>
                <?php if ($bu): ?><span class="text-secondary">atualizado: <?= htmlspecialchars($bu) ?></span><?php endif; ?>
              </div>
              <?php if (!empty($envs)): ?>
              <div class="mt-3">
                <div class="row g-2">
                  <?php foreach ($envs as $e): $eName=(string)$e['name']; $eUrl=(string)($e['url']??''); $hUrl=(string)($e['health_url']??''); $idAttr='env-'.md5($eName.$eUrl.$hUrl); ?>
                  <div class="col-12 col-md-6">
                    <div class="p-2 rounded border border-secondary-subtle">
                      <div class="d-flex justify-content-between align-items-center">
                        <strong><?= htmlspecialchars($eName ?: 'env') ?></strong>
                        <span id="<?= $idAttr ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">—</span>
                      </div>
                      <div class="small text-secondary mt-1">
                        URL: <?php if ($eUrl): ?><a class="link-light" href="<?= htmlspecialchars($eUrl) ?>" target="_blank" rel="noopener">Abrir</a><?php else: ?>—<?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <script>window.__envHealth = (window.__envHealth||[]); window.__envHealth.push({el:'<?= $idAttr ?>', url:'<?= htmlspecialchars($hUrl, ENT_QUOTES) ?>'});</script>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php else: ?>
                <div class="text-secondary small mt-2">Sem ambientes configurados.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-2">Equipe</h2>
              <?php if ($members): ?>
                <ul class="mb-3">
                  <?php foreach ($members as $m): ?>
                    <li><?= htmlspecialchars($m['label']) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="text-secondary mb-3">Sem membros.</div>
              <?php endif; ?>
              <div class="small text-secondary">Início: <?= htmlspecialchars($project['start_date'] ?: '—') ?></div>
              <div class="small text-secondary">Entrega: <?= htmlspecialchars($project['due_date'] ?: '—') ?></div>
              <div class="small text-secondary">Criado por: <?= htmlspecialchars($project['creator_name'] ?: '—') ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php if ($canManage): ?>
      <div class="card glass mb-3">
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="add_task">
            <div class="col-12 col-md-4"><input class="form-control" name="title" placeholder="Nova tarefa (título)" required></div>
            <div class="col-12 col-md-3"><input class="form-control" name="description" placeholder="Descrição opcional"></div>
            <div class="col-6 col-md-2">
              <select class="form-select" name="assignee_id">
                <option value="">Sem responsável</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2"><input class="form-control" type="date" name="due_date"></div>
            <div class="col-12 col-md-1 d-grid"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i></button></div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <div class="row g-3">
        <?php foreach ($cols as $key=>$label): ?>
        <div class="col-12 col-lg-4">
          <div class="card glass h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0"><?= htmlspecialchars($label) ?></h2>
              </div>
              <div class="kanban-col">
                <?php foreach ($by[$key] as $t): ?>
                <div class="p-2 rounded mb-2 task">
                  <div class="d-flex justify-content-between align-items-start">
                    <strong><?= htmlspecialchars($t['title']) ?></strong>
                    <?php if ($canManage): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="delete_task">
                      <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover tarefa?')"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($t['description'])): ?><div class="small text-secondary mb-1"><?= htmlspecialchars($t['description']) ?></div><?php endif; ?>
                  <div class="small d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-secondary"><i class="bi bi-person"></i> <?= htmlspecialchars($t['assignee_name'] ?: '—') ?></span>
                    <span class="text-secondary"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($t['due_date'] ?: '—') ?></span>
                  </div>
                  <?php if ($canManage): ?>
                  <div class="mt-2 d-flex flex-wrap gap-2">
                    <form method="post" class="d-inline-flex gap-1">
                      <input type="hidden" name="action" value="assign_user">
                      <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                      <select class="form-select form-select-sm" name="assignee_id">
                        <option value="">Sem responsável</option>
                        <?php foreach ($users as $u): ?>
                          <option value="<?= (int)$u['id'] ?>" <?= ((int)$t['assignee_id']===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['label']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-outline-light">Atribuir</button>
                    </form>
                    <form method="post" class="d-inline-flex gap-1">
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                      <select class="form-select form-select-sm" name="status">
                        <?php foreach ($cols as $sk=>$sl): ?>
                          <option value="<?= $sk ?>" <?= $sk===$t['status']?'selected':'' ?>><?= $sl ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-outline-primary">Mover</button>
                    </form>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($by[$key])): ?><div class="text-secondary">Sem tarefas.</div><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      (function(){
        async function ping(h){
          if(!h.url) { const el=document.getElementById(h.el); if(el){ el.textContent='—'; el.className='badge bg-secondary-subtle text-secondary border border-secondary-subtle'; } return; }
          const el = document.getElementById(h.el); if(!el) return;
          try{
            const r = await fetch(h.url, {method:'GET', cache:'no-store'});
            const ok = r.ok;
            el.textContent = ok ? 'Online' : 'Offline';
            el.className = 'badge ' + (ok ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle');
          }catch(e){ el.textContent='Offline'; el.className='badge bg-danger-subtle text-danger border border-danger-subtle'; }
        }
        (window.__envHealth||[]).forEach(ping);
      })();
    </script>
  </body>
</html>
