<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['manage_projects'], ['admin']);
$pdo = require __DIR__ . '/config/db.php';

$st = trim($_GET['status'] ?? 'pending');
$pr = trim($_GET['priority'] ?? '');
$cat = trim($_GET['category'] ?? '');

$where = [];$params=[];
if ($st !== '') { $where[]='status=:st'; $params[':st']=$st; }
if ($pr !== '') { $where[]='priority=:pr'; $params[':pr']=$pr; }
if ($cat !== '') { $where[]='category=:cat'; $params[':cat']=$cat; }
$sql = 'SELECT pr.id, pr.title, pr.category, pr.priority, pr.status, pr.created_at, COALESCE(u.name,u.username) as requester, COALESCE(pc.c,0) AS comments_count,
        CASE pr.priority WHEN "urgent" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 WHEN "low" THEN 4 ELSE 5 END AS pord
        FROM project_requests pr 
        JOIN users u ON u.id=pr.requester_id
        LEFT JOIN (
          SELECT request_id, COUNT(*) AS c FROM project_request_comments GROUP BY request_id
        ) pc ON pc.request_id=pr.id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY pord ASC, pr.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

// Contagens para filtros rápidos
try {
  $cntPending = (int)$pdo->query("SELECT COUNT(*) FROM project_requests WHERE status='pending'")->fetchColumn();
  $cntApproved = (int)$pdo->query("SELECT COUNT(*) FROM project_requests WHERE status='approved'")->fetchColumn();
  $cntRejected = (int)$pdo->query("SELECT COUNT(*) FROM project_requests WHERE status='rejected'")->fetchColumn();
  $cntInProg  = (int)$pdo->query("SELECT COUNT(*) FROM project_requests WHERE status='in_progress'")->fetchColumn();
  $cntDone    = (int)$pdo->query("SELECT COUNT(*) FROM project_requests WHERE status='done'")->fetchColumn();
} catch (Throwable $e) {
  $cntPending=$cntApproved=$cntRejected=$cntInProg=$cntDone=0;
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitações de Projeto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-speedometer2"></i> <?= render_brand_html(); ?></a>
        <div class="ms-auto"><a class="btn btn-outline-light btn-sm" href="index.php">Voltar</a></div>
      </div>
    </nav>

    <main class="container py-4">
      <h1 class="h5 mb-3">Solicitações de Projeto</h1>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="project_requests.php?status=pending" class="btn btn-sm <?= $st==='pending'?'btn-primary':'btn-outline-light' ?>">Pendentes <span class="badge bg-danger ms-1"><?= (int)$cntPending ?></span></a>
        <a href="project_requests.php?status=approved" class="btn btn-sm <?= $st==='approved'?'btn-primary':'btn-outline-light' ?>">Aprovadas <span class="badge bg-secondary ms-1"><?= (int)$cntApproved ?></span></a>
        <a href="project_requests.php?status=rejected" class="btn btn-sm <?= $st==='rejected'?'btn-primary':'btn-outline-light' ?>">Rejeitadas <span class="badge bg-secondary ms-1"><?= (int)$cntRejected ?></span></a>
        <a href="project_requests.php?status=in_progress" class="btn btn-sm <?= $st==='in_progress'?'btn-primary':'btn-outline-light' ?>">Em andamento <span class="badge bg-secondary ms-1"><?= (int)$cntInProg ?></span></a>
        <a href="project_requests.php?status=done" class="btn btn-sm <?= $st==='done'?'btn-primary':'btn-outline-light' ?>">Concluídas <span class="badge bg-secondary ms-1"><?= (int)$cntDone ?></span></a>
        <a href="project_requests.php" class="btn btn-sm <?= $st===''?'btn-primary':'btn-outline-light' ?>">Todas</a>
      </div>

      <form class="row g-2 mb-3">
        <div class="col-12 col-md-3">
          <select class="form-select" name="status">
            <option value="">Todos os status</option>
            <?php foreach (['pending'=>'Pendente','approved'=>'Aprovado','rejected'=>'Rejeitado','in_progress'=>'Em andamento','done'=>'Concluído'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $st===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select class="form-select" name="priority">
            <option value="">Todas as prioridades</option>
            <?php foreach (['low'=>'Baixa','medium'=>'Média','high'=>'Alta','urgent'=>'Urgente'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $pr===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select class="form-select" name="category">
            <option value="">Todas as categorias</option>
            <?php foreach (['programming'=>'Programação','networks'=>'Redes','iot'=>'IoT','servers'=>'Servidores'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $cat===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3"><button class="btn btn-outline-light w-100">Filtrar</button></div>
      </form>

      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Comentários</th><th>Solicitante</th><th>Data</th><th></th></tr></thead>
            <tbody>
              <?php if (!$rows): ?><tr><td class="text-secondary" colspan="7">Nenhum registro.</td></tr><?php endif; ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['title']) ?></td>
                  <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($r['category']) ?></span></td>
                  <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= htmlspecialchars($r['priority']) ?></span></td>
                  <td><span class="badge bg-info-subtle text-info border border-info-subtle"><?= htmlspecialchars($r['status']) ?></span></td>
                  <td class="small"><?= (int)($r['comments_count'] ?? 0) ?></td>
                  <td class="small text-secondary"><?= htmlspecialchars($r['requester']) ?></td>
                  <td class="small text-secondary"><?= htmlspecialchars($r['created_at']) ?></td>
                  <td><a class="btn btn-sm btn-outline-primary" href="project_request_view.php?id=<?= (int)$r['id'] ?>">Abrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </body>
</html>
