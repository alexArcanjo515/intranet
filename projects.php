<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['view_projects'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$visibility = $_GET['visibility'] ?? '';

$w = [];$p=[];
if ($q !== '') { $w[] = '(name LIKE :q OR objective LIKE :q)'; $p[':q'] = "%$q%"; }
if (in_array($status, ['planned','in_progress','blocked','done','archived'], true)) { $w[] = 'status = :s'; $p[':s'] = $status; }
if (in_array($visibility, ['private','team','org'], true)) { $w[] = 'visibility = :v'; $p[':v'] = $visibility; }
$where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

$stmt = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id=p.id AND t.status!='done') AS open_tasks FROM projects p $where ORDER BY p.created_at DESC");
$stmt->execute($p);
$rows = $stmt->fetchAll();

$u = $_SESSION['user'] ?? [];
$canManage = in_array('manage_projects', ($u['perms'] ?? []), true) || in_array('admin', ($u['roles'] ?? []), true);
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projetos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)} .card-hover:hover{transform:translateY(-2px);transition:.2s} .hero-gradient{background:radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%)}
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php"><i class="bi bi-kanban"></i><?= render_brand_html(); ?></a>
        <div class="d-flex ms-auto gap-2">
          <?php if ($canManage): ?><a href="project_form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo projeto</a><?php endif; ?>
          <a class="btn btn-outline-light btn-sm" href="index.php"><i class="bi bi-grid"></i></a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Projetos</h1>
      </div>

      <form method="get" class="row g-2 mb-3">
        <div class="col-12 col-md-4"><input class="form-control" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nome/objetivo"></div>
        <div class="col-6 col-md-3">
          <select class="form-select" name="status">
            <option value="">Status</option>
            <?php foreach(['planned'=>'Planejado','in_progress'=>'Em andamento','blocked'=>'Bloqueado','done'=>'Concluído','archived'=>'Arquivado'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select class="form-select" name="visibility">
            <option value="">Visibilidade</option>
            <?php foreach(['private'=>'Privado','team'=>'Equipe','org'=>'Organização'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $visibility===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-light w-100">Filtrar</button></div>
      </form>

      <div class="row g-3">
        <?php foreach ($rows as $r): ?>
        <div class="col-12 col-lg-6">
          <div class="card glass card-hover h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                  <strong><?= htmlspecialchars($r['name']) ?></strong>
                  <div class="text-secondary small text-uppercase">Status: <?= htmlspecialchars($r['status']) ?> · Vis: <?= htmlspecialchars($r['visibility']) ?></div>
                </div>
                <div class="d-flex gap-1">
                  <a class="btn btn-sm btn-outline-secondary" href="project_view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-box-arrow-up-right"></i></a>
                  <?php if ($canManage): ?><a class="btn btn-sm btn-outline-light" href="project_form.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                </div>
              </div>
              <div class="small text-secondary mb-2">Entrega: <?= htmlspecialchars($r['due_date'] ?: '—') ?></div>
              <div class="mb-2"><?= htmlspecialchars(mb_strimwidth((string)$r['objective'], 0, 160, '…')) ?></div>
              <div class="d-flex gap-3 small">
                <div><i class="bi bi-list-task me-1"></i>Abertas: <?= (int)$r['open_tasks'] ?></div>
                <div><i class="bi bi-calendar-event me-1"></i>Início: <?= htmlspecialchars($r['start_date'] ?: '—') ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <div class="col-12 text-secondary">Nenhum projeto encontrado.</div>
        <?php endif; ?>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
