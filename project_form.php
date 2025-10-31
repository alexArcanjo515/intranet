<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['manage_projects'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$project = [
  'name' => '',
  'objective' => '',
  'description' => '',
  'status' => 'planned',
  'visibility' => 'team',
  'start_date' => '',
  'due_date' => '',
  'repo_url' => '',
  'repo_branch' => '',
  'ci_url' => ''
];
$members = [];
$envs = [];

if ($editing) {
  $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch();
  if (!$row) { header('Location: projects.php'); exit(); }
  $project = $row;
  $mm = $pdo->prepare('SELECT user_id FROM project_members WHERE project_id = :id ORDER BY position ASC');
  $mm->execute([':id' => $id]);
  $members = array_map('intval', $mm->fetchAll(PDO::FETCH_COLUMN));
  $qe = $pdo->prepare('SELECT id, name, url, health_url, position FROM project_envs WHERE project_id=:p ORDER BY position ASC, id ASC');
  $qe->execute([':p'=>$id]);
  $envs = $qe->fetchAll();
}

// Users to assign
$users = $pdo->query('SELECT id, COALESCE(name, username) AS label FROM users WHERE is_active = 1 ORDER BY label')->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $objective = trim($_POST['objective'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $status = $_POST['status'] ?? 'planned';
  $visibility = $_POST['visibility'] ?? 'team';
  $start_date = $_POST['start_date'] ?? null;
  $due_date = $_POST['due_date'] ?? null;
  $repo_url = trim($_POST['repo_url'] ?? '');
  $repo_branch = trim($_POST['repo_branch'] ?? '');
  $ci_url = trim($_POST['ci_url'] ?? '');
  $build_status = trim($_POST['build_status'] ?? '');
  $sel_members = isset($_POST['members']) && is_array($_POST['members']) ? array_values(array_unique(array_map('intval', $_POST['members']))) : [];
  // ambientes
  $env_name = $_POST['env_name'] ?? [];
  $env_url = $_POST['env_url'] ?? [];
  $env_health = $_POST['env_health'] ?? [];

  if ($name === '') { $errors[] = 'Nome é obrigatório.'; }
  if (!in_array($status, ['planned','in_progress','blocked','done','archived'], true)) { $errors[] = 'Status inválido.'; }
  if (!in_array($visibility, ['private','team','org'], true)) { $errors[] = 'Visibilidade inválida.'; }

  if (!$errors) {
    $pdo->beginTransaction();
    try {
      if ($editing) {
        $stmt = $pdo->prepare('UPDATE projects SET name=:n, objective=:o, description=:d, status=:s, visibility=:v, start_date=:sd, due_date=:dd, repo_url=:ru, repo_branch=:rb, ci_url=:ci, build_status=:bs, build_updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute([':n'=>$name, ':o'=>$objective, ':d'=>$description, ':s'=>$status, ':v'=>$visibility, ':sd'=>$start_date?:null, ':dd'=>$due_date?:null, ':ru'=>$repo_url?:null, ':rb'=>$repo_branch?:null, ':ci'=>$ci_url?:null, ':bs'=>$build_status?:null, ':id'=>$id]);
        $pdo->prepare('DELETE FROM project_members WHERE project_id = :id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO project_members (project_id, user_id, role, position) VALUES (:p,:u, :r, :pos)');
        $pos = 0; foreach ($sel_members as $uid) { $ins->execute([':p'=>$id, ':u'=>$uid, ':r'=>'member', ':pos'=>$pos++]); }
        // ambientes
        $pdo->prepare('DELETE FROM project_envs WHERE project_id=:p')->execute([':p'=>$id]);
        $pei = $pdo->prepare('INSERT INTO project_envs (project_id, name, url, health_url, position) VALUES (:p,:n,:u,:h,:pos)');
        $pos = 0;
        for ($i=0; $i < max(count($env_name), count($env_url), count($env_health)); $i++) {
          $n = trim($env_name[$i] ?? ''); $uurl = trim($env_url[$i] ?? ''); $hh = trim($env_health[$i] ?? '');
          if ($n !== '' || $uurl !== '' || $hh !== '') {
            $pei->execute([':p'=>$id, ':n'=>$n?:'env', ':u'=>$uurl?:null, ':h'=>$hh?:null, ':pos'=>$pos++]);
          }
        }
      } else {
        $stmt = $pdo->prepare('INSERT INTO projects (name, objective, description, status, visibility, start_date, due_date, repo_url, repo_branch, ci_url, build_status, build_updated_at, created_by) VALUES (:n,:o,:d,:s,:v,:sd,:dd,:ru,:rb,:ci,:bs,CURRENT_TIMESTAMP,:cb)');
        $stmt->execute([':n'=>$name, ':o'=>$objective, ':d'=>$description, ':s'=>$status, ':v'=>$visibility, ':sd'=>$start_date?:null, ':dd'=>$due_date?:null, ':ru'=>$repo_url?:null, ':rb'=>$repo_branch?:null, ':ci'=>$ci_url?:null, ':bs'=>$build_status?:null, ':cb'=>($_SESSION['user']['id'] ?? null)]);
        $newId = (int)$pdo->lastInsertId();
        if ($sel_members) {
          $ins = $pdo->prepare('INSERT INTO project_members (project_id, user_id, role, position) VALUES (:p,:u, :r, :pos)');
          $pos = 0; foreach ($sel_members as $uid) { $ins->execute([':p'=>$newId, ':u'=>$uid, ':r'=>'member', ':pos'=>$pos++]); }
        }
        // ambientes
        $pei = $pdo->prepare('INSERT INTO project_envs (project_id, name, url, health_url, position) VALUES (:p,:n,:u,:h,:pos)');
        $pos = 0;
        for ($i=0; $i < max(count($env_name), count($env_url), count($env_health)); $i++) {
          $n = trim($env_name[$i] ?? ''); $uurl = trim($env_url[$i] ?? ''); $hh = trim($env_health[$i] ?? '');
          if ($n !== '' || $uurl !== '' || $hh !== '') {
            $pei->execute([':p'=>$newId, ':n'=>$n?:'env', ':u'=>$uurl?:null, ':h'=>$hh?:null, ':pos'=>$pos++]);
          }
        }
        $id = $newId;
      }
      $pdo->commit();
      header('Location: project_view.php?id=' . $id);
      exit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Falha ao salvar.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing? 'Editar':'Novo' ?> projeto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 hero-gradient">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="projects.php"><i class="bi bi-kanban"></i><?= render_brand_html(); ?></a>
        <div class="ms-auto"><a class="btn btn-outline-light btn-sm" href="projects.php"><i class="bi bi-arrow-left"></i> Voltar</a></div>
      </div>
    </nav>

    <main class="container py-4" style="max-width: 920px;">
      <h1 class="h4 mb-3"><?= $editing? 'Editar':'Novo' ?> projeto</h1>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      <form method="post" class="card glass p-3">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Nome</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($project['name']) ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach(['planned'=>'Planejado','in_progress'=>'Em andamento','blocked'=>'Bloqueado','done'=>'Concluído','archived'=>'Arquivado'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $project['status']===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Visibilidade</label>
            <select name="visibility" class="form-select">
              <?php foreach(['private'=>'Privado','team'=>'Equipe','org'=>'Organização'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $project['visibility']===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Início</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars((string)$project['start_date']) ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Entrega</label>
            <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars((string)$project['due_date']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Objetivo</label>
            <input type="text" name="objective" class="form-control" value="<?= htmlspecialchars((string)$project['objective']) ?>" placeholder="Objetivo principal do projeto">
          </div>
          <div class="col-12">
            <label class="form-label">Descrição</label>
            <textarea name="description" class="form-control" rows="5" placeholder="Detalhes, escopo, critérios de aceite"><?= htmlspecialchars((string)$project['description']) ?></textarea>
          </div>
          <div class="col-12">
            <hr class="text-secondary">
            <h2 class="h6">DevOps</h2>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Repositório (URL)</label>
            <input type="url" name="repo_url" class="form-control" value="<?= htmlspecialchars((string)($project['repo_url'] ?? '')) ?>" placeholder="https://github.com/org/repo">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Branch</label>
            <input type="text" name="repo_branch" class="form-control" value="<?= htmlspecialchars((string)($project['repo_branch'] ?? '')) ?>" placeholder="main">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Pipeline/CI URL</label>
            <input type="url" name="ci_url" class="form-control" value="<?= htmlspecialchars((string)($project['ci_url'] ?? '')) ?>" placeholder="https://ci.example.com/job/...">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Build Status</label>
            <?php $bs = (string)($project['build_status'] ?? ''); ?>
            <select name="build_status" class="form-select">
              <?php foreach (['success'=>'Sucesso','failed'=>'Falhou','running'=>'Executando','queued'=>'Na fila','unknown'=>'Desconhecido',''=>'(não informar)'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $bs===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Ambientes</label>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead><tr><th>Nome</th><th>URL</th><th>Health URL</th></tr></thead>
                <tbody>
                  <?php $rows = $envs ?: []; $rows = array_values($rows); for ($i=0; $i < max(3, count($rows)+1); $i++): $e=$rows[$i] ?? ['name'=>'','url'=>'','health_url'=>'']; ?>
                  <tr>
                    <td><input class="form-control" name="env_name[]" value="<?= htmlspecialchars((string)$e['name']) ?>" placeholder="dev/stg/prod"></td>
                    <td><input class="form-control" name="env_url[]" value="<?= htmlspecialchars((string)$e['url']) ?>" placeholder="https://app.example.com"></td>
                    <td><input class="form-control" name="env_health[]" value="<?= htmlspecialchars((string)$e['health_url']) ?>" placeholder="https://app.example.com/health"></td>
                  </tr>
                  <?php endfor; ?>
                </tbody>
              </table>
            </div>
            <div class="form-text">Deixe linhas em branco se não usar.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Membros</label>
            <select name="members[]" class="form-select" multiple size="8">
              <?php foreach ($users as $u): $uid=(int)$u['id']; $lbl=$u['label']; ?>
                <option value="<?= $uid ?>" <?= in_array($uid, $members, true)?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Selecione os colaboradores (Ctrl/Cmd para múltiplos).</div>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-check2"></i> Salvar</button>
          <a class="btn btn-outline-light" href="projects.php">Cancelar</a>
        </div>
      </form>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
