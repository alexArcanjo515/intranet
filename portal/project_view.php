<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: projects.php'); exit(); }

// Carregar projeto e verificar se o usuário é membro
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id=:id');
$stmt->execute([':id'=>$id]);
$project = $stmt->fetch();
if (!$project) { header('Location: projects.php'); exit(); }

$member = (bool)$pdo->prepare('SELECT 1 FROM project_members WHERE project_id=:p AND user_id=:u LIMIT 1')
  ->execute([':p'=>$id, ':u'=>(int)$me['id']]) || false;
// fetch correctly
$chk = $pdo->prepare('SELECT 1 FROM project_members WHERE project_id=:p AND user_id=:u LIMIT 1');
$chk->execute([':p'=>$id, ':u'=>(int)$me['id']]);
$member = (bool)$chk->fetchColumn();

// Permite ações apenas a membros (ou admins com manage_projects)
$canAct = $member || in_array('manage_projects', ($me['perms'] ?? []), true) || in_array('admin', ($me['roles'] ?? []), true);

// Ações: mover status de tarefa, (opcional) atribuir a si mesmo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canAct) {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'update_status') {
      $taskId = (int)($_POST['task_id'] ?? 0);
      $status = $_POST['status'] ?? '';
      if (in_array($status, ['todo','doing','review','done','blocked'], true)) {
        $up = $pdo->prepare('UPDATE project_tasks SET status=:s WHERE id=:id AND project_id=:p');
        $up->execute([':s'=>$status, ':id'=>$taskId, ':p'=>$id]);
      }
    } elseif ($action === 'assign_self') {
      $taskId = (int)($_POST['task_id'] ?? 0);
      $up = $pdo->prepare('UPDATE project_tasks SET assignee_id=:a WHERE id=:id AND project_id=:p');
      $up->execute([':a'=>(int)$me['id'], ':id'=>$taskId, ':p'=>$id]);
    }
  } catch (Throwable $e) {}
  header('Location: project_view.php?id='.(int)$id);
  exit();
}

// Membros
$mm = $pdo->prepare('SELECT u.id, COALESCE(u.name,u.username) AS label FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=:p ORDER BY pm.position ASC');
$mm->execute([':p'=>$id]);
$members = $mm->fetchAll();

// Ambientes
$envStmt = $pdo->prepare('SELECT id, name, url, health_url, position FROM project_envs WHERE project_id=:p ORDER BY position ASC, name ASC');
$envStmt->execute([':p'=>$id]);
$envs = $envStmt->fetchAll();

// Resumo ambientes (X/Y online, média de latência)
$envSummary = ['online'=>0,'total'=>count($envs),'avg'=>null];
if ($envs) {
  try {
    $qLast = $pdo->prepare('SELECT up, latency_ms FROM project_env_status_log WHERE env_id = :e ORDER BY id DESC LIMIT 1');
    $sum = 0; $cnt = 0;
    foreach ($envs as $e) {
      $eid = (int)$e['id'];
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

// Tarefas
$tasks = $pdo->prepare('SELECT t.*, COALESCE(u.name,u.username) AS assignee_name FROM project_tasks t LEFT JOIN users u ON u.id=t.assignee_id WHERE t.project_id=:p ORDER BY t.position ASC, t.id DESC');
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
    <title>Projeto - Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-building"></i> <?= render_brand_html(); ?></a>
        <div class="ms-auto"><a href="projects.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a></div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h5 mb-0"><?= htmlspecialchars($project['name']) ?></h1>
          <small class="text-secondary">Status: <?= htmlspecialchars($project['status']) ?> · Entrega: <?= htmlspecialchars($project['due_date'] ?: '—') ?></small>
        </div>
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
                  <?php foreach ($envs as $e): $eName=(string)$e['name']; $eUrl=(string)($e['url']??''); $hUrl=(string)($e['health_url']??''); $idAttr='penv-'.md5($eName.$eUrl.$hUrl); ?>
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
                  <script>window.__pEnvHealth = (window.__pEnvHealth||[]); window.__pEnvHealth.push({el:'<?= $idAttr ?>', url:'<?= htmlspecialchars($hUrl, ENT_QUOTES) ?>'});</script>
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
              <ul class="mb-0">
                <?php foreach ($members as $m): ?>
                  <li><?= htmlspecialchars($m['label']) ?></li>
                <?php endforeach; ?>
              </ul>
              <?php else: ?><div class="text-secondary">Sem membros.</div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <?php foreach ($cols as $key=>$label): ?>
        <div class="col-12 col-lg-4">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-2"><?= htmlspecialchars($label) ?></h2>
              <?php if (empty($by[$key])): ?><div class="text-secondary">Sem tarefas.</div><?php endif; ?>
              <?php foreach ($by[$key] as $t): ?>
              <div class="p-2 rounded mb-2" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08)">
                <strong><?= htmlspecialchars($t['title']) ?></strong>
                <?php if (!empty($t['description'])): ?><div class="small text-secondary mb-1"><?= htmlspecialchars($t['description']) ?></div><?php endif; ?>
                <div class="small d-flex flex-wrap gap-3 text-secondary">
                  <span><i class="bi bi-person"></i> <?= htmlspecialchars($t['assignee_name'] ?: '—') ?></span>
                  <span><i class="bi bi-calendar3"></i> <?= htmlspecialchars($t['due_date'] ?: '—') ?></span>
                </div>
                <?php if ($canAct): ?>
                <div class="mt-2 d-flex flex-wrap gap-2">
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
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="assign_self">
                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-outline-light"><i class="bi bi-person-check"></i> Assumir</button>
                  </form>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
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
        (window.__pEnvHealth||[]).forEach(ping);
      })();
    </script>
  </body>
</html>
