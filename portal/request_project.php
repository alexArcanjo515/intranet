<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { header('Location: login.php'); exit(); }
$me = $_SESSION['portal_user'];
$canRequest = in_array('manage_projects', ($me['perms'] ?? []), true) || in_array('admin', ($me['roles'] ?? []), true);
if (!$canRequest) { header('Location: index.php'); exit(); }

$errors = [];
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $category = $_POST['category'] ?? '';
  $priority = $_POST['priority'] ?? 'medium';
  $summary = trim($_POST['summary'] ?? '');
  $funcs = $_POST['func'] ?? []; // array de checkboxes
  $extras = [
    'language' => trim($_POST['language'] ?? ''),
    'stack' => trim($_POST['stack'] ?? ''),
    'notes' => trim($_POST['notes'] ?? ''),
  ];
  if ($title === '') $errors[] = 'Título é obrigatório.';
  if (!in_array($category, ['programming','networks','iot','servers'], true)) $errors[] = 'Categoria inválida.';
  if (!in_array($priority, ['low','medium','high','urgent'], true)) $errors[] = 'Prioridade inválida.';

  if (!$errors) {
    $details = [
      'category' => $category,
      'selected' => array_values(array_unique(array_filter(array_map('strval',$funcs)))),
      'extras' => $extras,
    ];
    $json = json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    try {
      $stmt = $pdo->prepare('INSERT INTO project_requests (requester_id, title, category, priority, status, summary, details) VALUES (:u,:t,:c,:p,\'pending\',:s,:d)');
      $stmt->execute([':u'=>(int)$me['id'], ':t'=>$title, ':c'=>$category, ':p'=>$priority, ':s'=>$summary?:null, ':d'=>$json]);
      $ok = true;
      // Notificar admins/gestores de projetos
      try {
        // por role admin
        $adminIds = $pdo->query("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.name='admin'")->fetchAll(PDO::FETCH_COLUMN);
        // por perm manage_projects
        $pm = $pdo->query("SELECT DISTINCT ur.user_id FROM user_roles ur JOIN role_permissions rp ON rp.role_id=ur.role_id JOIN permissions p ON p.id=rp.permission_id WHERE p.name='manage_projects'")->fetchAll(PDO::FETCH_COLUMN);
        $targets = array_unique(array_map('intval', array_merge($adminIds?:[], $pm?:[])));
        if ($targets) {
          $insN = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u,:t,:ti,:b,:l)');
          foreach ($targets as $uid) {
            if ($uid <= 0) continue;
            $insN->execute([
              ':u'=>$uid,
              ':t'=>'project_request',
              ':ti'=>'Nova solicitação de projeto',
              ':b'=>$title,
              ':l'=>'/project_requests.php'
            ]);
          }
        }
      } catch (Throwable $e) { /* ignore */ }
    } catch (Throwable $e) {
      $errors[] = 'Falha ao enviar solicitação.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitar Projeto - Portal</title>
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
      <h1 class="h5 mb-3">Solicitar Projeto</h1>
      <?php if ($ok): ?>
        <div class="alert alert-success">Solicitação enviada com sucesso.</div>
      <?php endif; ?>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

      <form method="post" class="card glass p-3">
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label">Título</label>
            <input class="form-control" name="title" required placeholder="Ex.: Portal de Inventário de TI">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label">Prioridade</label>
            <select class="form-select" name="priority">
              <option value="low">Baixa</option>
              <option value="medium" selected>Média</option>
              <option value="high">Alta</option>
              <option value="urgent">Urgente</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Categoria</label>
            <select class="form-select" name="category" id="catSel" required>
              <option value="">Selecione…</option>
              <option value="programming">Programação</option>
              <option value="networks">Redes</option>
              <option value="iot">IoT</option>
              <option value="servers">Servidores</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Resumo</label>
            <textarea class="form-control" name="summary" rows="3" placeholder="Contexto, objetivos e beneficiários"></textarea>
          </div>

          <div class="col-12">
            <div id="dynProgramming" class="d-none">
              <div class="mb-2 fw-semibold">Funcionalidades (Programação)</div>
              <div class="row g-2">
                <?php $prog = ['web'=>'Web','backend'=>'Backend','api'=>'API','mobile'=>'Mobile','database'=>'Banco de Dados','auth'=>'Autenticação','cicd'=>'CI/CD']; foreach ($prog as $k=>$v): ?>
                  <div class="col-6 col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="func[]" value="prog:<?= $k ?>"><span class="form-check-label"><?= $v ?></span></label></div>
                <?php endforeach; ?>
              </div>
              <div class="row g-2 mt-2">
                <div class="col-12 col-md-6"><input class="form-control" name="language" placeholder="Linguagem/Framework (ex.: PHP/Laravel)"></div>
                <div class="col-12 col-md-6"><input class="form-control" name="stack" placeholder="Stack/Infra (ex.: LAMP, Docker)"></div>
              </div>
            </div>
            <div id="dynNetworks" class="d-none">
              <div class="mb-2 fw-semibold">Funcionalidades (Redes)</div>
              <div class="row g-2">
                <?php $net = ['vlan'=>'VLANs','firewall'=>'Firewall','vpn'=>'VPN','wifi'=>'Wi-Fi','monitoring'=>'Monitoramento','zerotrust'=>'Zero Trust']; foreach ($net as $k=>$v): ?>
                  <div class="col-6 col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="func[]" value="net:<?= $k ?>"><span class="form-check-label"><?= $v ?></span></label></div>
                <?php endforeach; ?>
              </div>
            </div>
            <div id="dynIoT" class="d-none">
              <div class="mb-2 fw-semibold">Funcionalidades (IoT)</div>
              <div class="row g-2">
                <?php $iot = ['sensors'=>'Sensores','gateway'=>'Gateway','protocols'=>'Protocolos (MQTT/HTTP)','dashboard'=>'Dashboard','alerts'=>'Alertas']; foreach ($iot as $k=>$v): ?>
                  <div class="col-6 col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="func[]" value="iot:<?= $k ?>"><span class="form-check-label"><?= $v ?></span></label></div>
                <?php endforeach; ?>
              </div>
            </div>
            <div id="dynServers" class="d-none">
              <div class="mb-2 fw-semibold">Funcionalidades (Servidores)</div>
              <div class="row g-2">
                <?php $srv = ['provision'=>'Provisionamento','containers'=>'Containers','backups'=>'Backups','monitoring'=>'Monitoramento','hardening'=>'Hardening']; foreach ($srv as $k=>$v): ?>
                  <div class="col-6 col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="func[]" value="srv:<?= $k ?>"><span class="form-check-label"><?= $v ?></span></label></div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Observações adicionais</label>
            <textarea class="form-control" name="notes" rows="3" placeholder="Requisitos não-funcionais, integrações, prazos"></textarea>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-send"></i> Enviar solicitação</button>
          <a class="btn btn-outline-light" href="index.php">Cancelar</a>
        </div>
      </form>
    </main>

    <script>
      (function(){
        const sel = document.getElementById('catSel');
        const blocks = {
          programming: document.getElementById('dynProgramming'),
          networks: document.getElementById('dynNetworks'),
          iot: document.getElementById('dynIoT'),
          servers: document.getElementById('dynServers')
        };
        function update(){
          for (const k in blocks){ blocks[k].classList.add('d-none'); }
          const v = sel.value; if (blocks[v]) blocks[v].classList.remove('d-none');
        }
        sel.addEventListener('change', update);
        update();
      })();
    </script>
  </body>
</html>
