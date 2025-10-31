<?php
session_start();
require_once __DIR__ . '/includes/settings.php';
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit();
}
$displayName = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'usuário';
// Carregar últimas notícias publicadas (prioriza fixadas)
try {
    $pdo = require __DIR__ . '/config/db.php';
    $newsStmt = $pdo->query("SELECT id, title, COALESCE(is_published,1) AS is_published, is_pinned, created_at FROM news WHERE COALESCE(is_published,1)=1 ORDER BY is_pinned DESC, created_at DESC LIMIT 3");
    $latestNews = $newsStmt->fetchAll();
    // Carregar até 6 servidores recentes
    $servers = $pdo->query("SELECT id, name, ip, host, protocol, port FROM servers ORDER BY created_at DESC LIMIT 6")->fetchAll();
    // Contar solicitações pendentes
    try {
        $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM project_requests WHERE status='pending'")->fetchColumn();
    } catch (Throwable $e) { $pendingCount = 0; }
} catch (Throwable $e) {
    $latestNews = [];
    $servers = [];
    $pendingCount = 0;
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .card-hover { transition: transform .15s ease, box-shadow .15s ease; }
      .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,.25); }
      .avatar { width: 36px; height: 36px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #0d6efd33; border: 1px solid #0d6efd55; }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
          <i class="bi bi-hurricane text-primary"></i>
          <?= render_brand_html(); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02" aria-controls="navbarColor02" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarColor02">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-grid me-1"></i>Dashboard</a></li>
          </ul>
          <div class="d-flex align-items-center gap-3">
            <span class="text-secondary small">Logado como</span>
            <span class="avatar text-primary"><i class="bi bi-person"></i></span>
            <span><?php echo htmlspecialchars($displayName); ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
          </div>
        </div>
      </div>
    </nav>

    <header class="container py-4">
      <div class="row g-3 align-items-center">
        <div class="col">
          <h1 class="h3 mb-1">Dashboard</h1>
          <p class="text-secondary mb-0">Bem-vindo à sua intranet. Selecione um módulo abaixo.</p>
        </div>
        <div class="col-auto">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" placeholder="Pesquisar módulos..." aria-label="Pesquisar módulos">
          </div>
        </div>
      </div>
    </header>

    <main class="container pb-5">
      <?php if (!empty($latestNews)): ?>
      <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h5 mb-0">Últimas notícias</h2>
          <a href="news.php" class="btn btn-sm btn-outline-light">Ver todas</a>
        </div>
        <div class="row g-3">
          <?php foreach ($latestNews as $n): ?>
          <div class="col-12 col-md-4">
            <a href="news_view.php?id=<?= (int)$n['id'] ?>" class="text-decoration-none">
              <div class="card glass card-hover h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start justify-content-between">
                    <strong class="me-2"><?= htmlspecialchars($n['title']) ?></strong>
                    <?php if ((int)$n['is_pinned']===1): ?><i class="bi bi-pin-angle-fill text-warning" title="Fixada"></i><?php endif; ?>
                  </div>
                  <div class="text-secondary small mt-2"><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($n['created_at']) ?></div>
                </div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
      <?php if (!empty($servers)): ?>
      <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h5 mb-0">Servidores</h2>
          <a href="settings.php?tab=servers" class="btn btn-sm btn-outline-light">Gerenciar</a>
        </div>
        <div class="row g-3">
          <?php foreach ($servers as $sv): $sid=(int)$sv['id']; $host=$sv['host']?:$sv['ip']; ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card glass h-100" data-server-id="<?= $sid ?>">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <strong><?= htmlspecialchars($sv['name']) ?></strong>
                      <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase"><?= htmlspecialchars($sv['protocol']) ?></span>
                    </div>
                    <div class="text-secondary small"><?= htmlspecialchars($host) ?>:<?= (int)$sv['port'] ?></div>
                  </div>
                  <a class="btn btn-sm btn-outline-secondary" href="server_view.php?id=<?= $sid ?>" title="Visualizar"><i class="bi bi-box-arrow-up-right"></i></a>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                  <div>
                    <span id="sv-status-<?= $sid ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Checando…</span>
                  </div>
                  <div class="text-secondary small">
                    <span id="sv-lat-<?= $sid ?>">—</span>
                    <span id="sv-http-<?= $sid ?>" class="ms-2"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
      <div class="row g-4">
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="users.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-people"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Usuários</h2>
                      <small class="text-secondary">Gerir contas e perfis</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Adicionar, editar e desativar usuários. Controle de acesso e grupos.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="documents.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-folder2-open"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Documentos</h2>
                      <small class="text-secondary">Central de arquivos</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Uploads, versões, categorias e permissões de leitura/edição.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="news.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-megaphone"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Notícias</h2>
                      <small class="text-secondary">Comunicados internos</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Criar e destacar notícias e avisos para a organização.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="roles.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-shield-lock"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Permissões</h2>
                      <small class="text-secondary">Papéis e regras</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Defina papéis, permissões detalhadas e auditoria de ações.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="settings.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-gear"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Configurações</h2>
                      <small class="text-secondary">Preferências gerais</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Ajustes de layout, identidade visual, e parâmetros do sistema.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="reports.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-bar-chart"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Relatórios</h2>
                      <small class="text-secondary">KPIs e estatísticas</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">KPIs, atividade de usuários e uso de módulos.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="projects.php">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-kanban"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Projetos</h2>
                      <small class="text-secondary">Gestão e DevOps</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Planejamento, equipe, tarefas e status do projeto.</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="text-decoration-none position-relative" href="project_requests.php?status=pending">
            <div class="card glass card-hover h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar"><i class="bi bi-inbox"></i></div>
                    <div>
                      <h2 class="h5 mb-0">Solicitações de Projeto</h2>
                      <small class="text-secondary">Aprovar ou rejeitar pedidos</small>
                    </div>
                  </div>
                  <i class="bi bi-chevron-right text-secondary"></i>
                </div>
                <p class="mb-0 text-secondary">Avalie solicitações enviadas pelo portal e crie projetos.</p>
              </div>
            </div>
            <?php if (!empty($pendingCount)): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="transform: translate(-30%, -30%) !important;">
              <?= (int)$pendingCount ?>
            </span>
            <?php endif; ?>
          </a>
        </div>
      </div>
    </main>

    <footer class="mt-auto py-3">
      <div class="container text-secondary small d-flex justify-content-between">
        <span>&copy; <?php echo date('Y'); ?> Intranet</span>
        <span>Alexio Mango</span>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($servers)): ?>
    <script>
      (function(){
        const ids = [<?= implode(',', array_map(fn($s)=> (int)$s['id'], $servers)) ?>];
        async function refresh(){
          try {
            const resp = await fetch('server_status_api.php?ids=' + ids.join(','));
            if (!resp.ok) return;
            const data = await resp.json();
            (data.items||[]).forEach(it => {
              const st = document.getElementById('sv-status-' + it.id);
              const lt = document.getElementById('sv-lat-' + it.id);
              const hc = document.getElementById('sv-http-' + it.id);
              if (st){
                st.className = 'badge ' + (it.up ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle');
                st.textContent = it.up ? 'Online' : 'Offline';
              }
              if (lt){ lt.textContent = it.latency_ms ? (it.latency_ms + ' ms') : '—'; }
              if (hc){ hc.textContent = it.http_code ? ('HTTP ' + it.http_code) : ''; }
            });
          } catch(e) { /* ignore */ }
        }
        refresh();
        setInterval(refresh, 10000);
      })();
    </script>
    <?php endif; ?>
  </body>
</html>
