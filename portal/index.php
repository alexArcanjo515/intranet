<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) {
  header('Location: login.php');
  exit();
}
$displayName = $_SESSION['portal_user']['name'] ?? $_SESSION['portal_user']['username'] ?? 'usuário';

try {
  $news = $pdo->query("SELECT id, title, created_at FROM news WHERE COALESCE(is_published,1)=1 ORDER BY is_pinned DESC, created_at DESC LIMIT 4")->fetchAll();
  $uid = (int)($_SESSION['portal_user']['id'] ?? 0);
  $stmtUM = $pdo->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id=:u AND is_read=0");
  $stmtUM->execute([':u'=>$uid]);
  $unreadMsgs = (int)$stmtUM->fetchColumn();
} catch (Throwable $e) { $news = []; $unreadMsgs = 0; }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.4);backdrop-filter:blur(8px)}
      .hero{background: radial-gradient(1200px 600px at 0% -10%, rgba(99,102,241,.22), transparent 70%), radial-gradient(800px 400px at 100% 0%, rgba(16,185,129,.18), transparent 60%)}
      .tile:hover{transform:translateY(-2px);transition:.2s}
    </style>
  </head>
  <body class="hero min-vh-100 d-flex flex-column">
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

    <header class="container py-4">
      <h1 class="h4 mb-2">Intranet</h1>
      <p class="text-secondary mb-0">Acesse notícias, documentos e projetos.</p>
    </header>

    <main class="container pb-5">
      <?php if (!empty($news)): ?>
      <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Últimas notícias</h2>
          <a href="news.php" class="btn btn-sm btn-outline-light">Ver todas</a>
        </div>
        <div class="row g-3">
          <?php foreach ($news as $n): ?>
          <div class="col-12 col-md-6 col-lg-3">
            <a class="text-decoration-none" href="news_view.php?id=<?= (int)$n['id'] ?>">
              <div class="card glass tile h-100">
                <div class="card-body">
                  <strong><?= htmlspecialchars($n['title']) ?></strong>
                  <div class="text-secondary small mt-2"><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($n['created_at']) ?></div>
                </div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section>
        <div class="row g-3">
          <div class="col-12 col-md-6 col-lg-4">
            <a href="documents.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-folder2-open"></i></div>
                  <div>
                    <div class="fw-bold">Documentos</div>
                    <div class="text-secondary small">Central de arquivos</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="projects.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-kanban"></i></div>
                  <div>
                    <div class="fw-bold">Projetos</div>
                    <div class="text-secondary small">Equipe e tarefas</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="my_projects.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-collection"></i></div>
                  <div>
                    <div class="fw-bold">Meus Projetos</div>
                    <div class="text-secondary small">Projetos em que participo</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="my_tasks.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-list-task"></i></div>
                  <div>
                    <div class="fw-bold">Minhas Tarefas</div>
                    <div class="text-secondary small">Acompanhe e atualize o status</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="chat.php" class="text-decoration-none position-relative">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-chat-dots"></i></div>
                  <div>
                    <div class="fw-bold">Chat</div>
                    <div class="text-secondary small">Mensagens diretas entre usuários</div>
                  </div>
                </div>
              </div>
              <?php if (!empty($unreadMsgs)): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="transform: translate(-30%, -30%) !important;">
                <?= (int)$unreadMsgs ?>
              </span>
              <?php endif; ?>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="helpdesk/index.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-tools"></i></div>
                  <div>
                    <div class="fw-bold">Help Desk</div>
                    <div class="text-secondary small">Tickets, servidores e análises</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="rh/pin.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-shield-lock"></i></div>
                  <div>
                    <div class="fw-bold">RH</div>
                    <div class="text-secondary small">Acesso restrito com PIN</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <?php $pu = $_SESSION['portal_user'] ?? []; $canReq = in_array('manage_projects', ($pu['perms'] ?? []), true) || in_array('admin', ($pu['roles'] ?? []), true); if ($canReq): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="request_project.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-rocket-takeoff"></i></div>
                  <div>
                    <div class="fw-bold">Solicitar Projeto</div>
                    <div class="text-secondary small">Envie uma proposta detalhada</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="requests.php" class="text-decoration-none">
              <div class="card glass tile h-100">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="fs-3"><i class="bi bi-inbox"></i></div>
                  <div>
                    <div class="fw-bold">Minhas Solicitações</div>
                    <div class="text-secondary small">Acompanhe status e comentários</div>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <footer class="mt-auto py-3">
      <div class="container text-secondary small d-flex justify-content-between">
        <span>&copy; <?php echo date('Y'); ?> Intranet</span>
        <span>Portal</span>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      (function(){
        async function loadNotifs(){
          try{
            const r = await fetch('notifications_api.php?action=list&limit=10', {headers:{'Cache-Control':'no-cache'}});
            if(!r.ok) return;
            const data = await r.json();
            const list = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            const chatBadge = document.getElementById('chatBadge');
            list.innerHTML = '';
            const items = data.items||[];
            if(items.length===0){ list.innerHTML = '<div class="list-group-item bg-transparent text-secondary small">Sem notificações.</div>'; }
            items.forEach(it=>{
              const a = document.createElement('a');
              a.className = 'list-group-item list-group-item-action bg-transparent';
              a.href = it.link||'#';
              a.innerHTML = `<div class="d-flex justify-content-between"><strong class="small">${escapeHtml(it.title||'')}</strong><span class="small text-secondary">${escapeHtml(it.created_at||'')}</span></div><div class="small text-secondary">${escapeHtml(it.body||'')}</div>`;
              list.appendChild(a);
            });
            const unread = data.unread||0;
            if(unread>0){ badge.textContent = unread; badge.classList.remove('d-none'); } else { badge.classList.add('d-none'); }
            const cunread = data.chat_unread||0;
            if(cunread>0){ chatBadge.textContent = cunread; chatBadge.classList.remove('d-none'); } else { chatBadge.classList.add('d-none'); }
          }catch(e){ /* ignore */ }
        }
        function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m])); }
        document.getElementById('markAll').addEventListener('click', async (ev)=>{
          ev.preventDefault();
          try{ await fetch('notifications_api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=mark_read'}); loadNotifs(); }catch(e){}
        });
        loadNotifs();
        setInterval(loadNotifs, 15000);
      })();
    </script>
  </body>
</html>
