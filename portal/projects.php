<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$w=[];$p=[];
if ($q!==''){ $w[]='(p.name LIKE :q OR p.objective LIKE :q)'; $p[':q']="%$q%"; }
if (in_array($status,['planned','in_progress','blocked','done','archived'],true)){ $w[]='p.status=:s'; $p[':s']=$status; }

// Política do portal: usuário só enxerga projetos dos quais é membro
$where = $w ? ('AND ' . implode(' AND ', $w)) : '';
$sql = "SELECT p.*, (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id=p.id AND t.status!='done') AS open_tasks
        FROM projects p
        WHERE EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id=p.id AND pm.user_id=:uid)
        $where
        ORDER BY p.created_at DESC";
$stmt=$pdo->prepare($sql);
$p[':uid']=(int)$me['id'];
$stmt->execute($p);
$rows=$stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projetos - Portal</title>
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
          <span class="text-secondary small">Olá,</span>
          <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($displayName) ?></span>
          <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
        </div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h5 mb-0">Projetos</h1>
      </div>

      <form class="row g-2 mb-3">
        <div class="col-12 col-md-6"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar projetos"></div>
        <div class="col-6 col-md-3">
          <select class="form-select" name="status">
            <option value="">Status</option>
            <?php foreach(['planned'=>'Planejado','in_progress'=>'Em andamento','blocked'=>'Bloqueado','done'=>'Concluído','archived'=>'Arquivado'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3"><button class="btn btn-outline-light w-100">Filtrar</button></div>
      </form>

      <div class="row g-3">
        <?php foreach ($rows as $r): ?>
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                  <strong><?= htmlspecialchars($r['name']) ?></strong>
                  <div class="text-secondary small text-uppercase">Status: <?= htmlspecialchars($r['status']) ?> · Vis: <?= htmlspecialchars($r['visibility']) ?></div>
                </div>
              </div>
              <div class="small text-secondary mb-2">Entrega: <?= htmlspecialchars($r['due_date'] ?: '—') ?></div>
              <div class="mb-2"><?= htmlspecialchars(mb_strimwidth((string)$r['objective'], 0, 160, '…')) ?></div>
              <div class="d-flex gap-3 small">
                <div><i class="bi bi-list-task me-1"></i>Abertas: <?= (int)$r['open_tasks'] ?></div>
              </div>
              <div class="mt-2"><a class="btn btn-sm btn-outline-light" href="project_view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-box-arrow-up-right"></i> Ver detalhes</a></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="col-12 text-secondary">Nenhum projeto disponível.</div><?php endif; ?>
      </div>
    </main>
  </body>
</html>
