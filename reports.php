<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();

$pdo = require __DIR__ . '/config/db.php';

// KPIs
$kpis = [
  'users_active' => 0,
  'documents_total' => 0,
  'news_published' => 0,
  'servers_total' => 0,
];
try {
  $kpis['users_active'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
} catch (Throwable $e) {}
try {
  $kpis['documents_total'] = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
} catch (Throwable $e) {}
try {
  $kpis['news_published'] = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE COALESCE(is_published,1)=1")->fetchColumn();
} catch (Throwable $e) {}
try {
  $kpis['servers_total'] = (int)$pdo->query("SELECT COUNT(*) FROM servers")->fetchColumn();
} catch (Throwable $e) {}

// Documents by category
$cats = [];
try {
  $stmt = $pdo->query("SELECT COALESCE(category,'Sem categoria') AS category, COUNT(*) AS qty FROM documents GROUP BY category ORDER BY qty DESC");
  $cats = $stmt->fetchAll();
} catch (Throwable $e) {}

// Versions by month (last 6 months)
$verByMonth = [];
try {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'mysql') {
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS m, COUNT(*) AS c FROM document_versions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY 1 ORDER BY 1";
  } else {
    $sql = "SELECT strftime('%Y-%m-01', created_at) AS m, COUNT(*) AS c FROM document_versions WHERE date(created_at) >= date('now','-6 months') GROUP BY 1 ORDER BY 1";
  }
  $verByMonth = $pdo->query($sql)->fetchAll();
} catch (Throwable $e) {}

// Servers list for live status polling (limit 8)
$servers = [];
try {
  $servers = $pdo->query("SELECT id, name, ip, host, protocol, port FROM servers ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch (Throwable $e) {}

// Prepare chart data
$catLabels = array_map(fn($r)=> $r['category'], $cats);
$catValues = array_map(fn($r)=> (int)$r['qty'], $cats);
$monLabels = array_map(fn($r)=> $r['m'], $verByMonth);
$monValues = array_map(fn($r)=> (int)$r['c'], $verByMonth);

// Downloads Top 5 e série diária (30 dias)
$topDownloads = [];
$dailyDownloads = [];
$topUsers = [];
try {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'mysql') {
    $topDownloads = $pdo->query("SELECT d.id, d.title, COUNT(*) cnt FROM downloads_log l JOIN documents d ON d.id = l.document_id WHERE l.created_at >= (NOW() - INTERVAL 30 DAY) GROUP BY d.id, d.title ORDER BY cnt DESC LIMIT 5")->fetchAll();
    $dailyDownloads = $pdo->query("SELECT DATE(l.created_at) AS d, COUNT(*) c FROM downloads_log l WHERE l.created_at >= (CURDATE() - INTERVAL 30 DAY) GROUP BY DATE(l.created_at) ORDER BY d")->fetchAll();
    $topUsers = $pdo->query("SELECT COALESCE(u.name,u.username,'(desconhecido)') AS uname, COUNT(*) cnt FROM downloads_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.created_at >= (NOW() - INTERVAL 30 DAY) GROUP BY uname ORDER BY cnt DESC LIMIT 5")->fetchAll();
  } else {
    $topDownloads = $pdo->query("SELECT d.id, d.title, COUNT(*) cnt FROM downloads_log l JOIN documents d ON d.id = l.document_id WHERE date(l.created_at) >= date('now','-30 day') GROUP BY d.id, d.title ORDER BY cnt DESC LIMIT 5")->fetchAll();
    $dailyDownloads = $pdo->query("SELECT date(l.created_at) AS d, COUNT(*) c FROM downloads_log l WHERE date(l.created_at) >= date('now','-30 day') GROUP BY date(l.created_at) ORDER BY d")->fetchAll();
    $topUsers = $pdo->query("SELECT COALESCE(u.name,u.username,'(desconhecido)') AS uname, COUNT(*) cnt FROM downloads_log l LEFT JOIN users u ON u.id = l.user_id WHERE date(l.created_at) >= date('now','-30 day') GROUP BY uname ORDER BY cnt DESC LIMIT 5")->fetchAll();
  }
} catch (Throwable $e) {}

// Uptime por período selecionável (7/30/90 dias)
$uptime = [];
$upDays = isset($_GET['up_days']) ? (int)$_GET['up_days'] : 7;
if (!in_array($upDays, [7,30,90], true)) { $upDays = 7; }
try {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'mysql') {
    $sql = "SELECT s.id, s.name,
      SUM(CASE WHEN l.created_at >= (NOW() - INTERVAL $upDays DAY) THEN l.up ELSE NULL END) AS upN,
      SUM(CASE WHEN l.created_at >= (NOW() - INTERVAL $upDays DAY) THEN 1 ELSE NULL END) AS totN
      FROM servers s LEFT JOIN server_status_log l ON l.server_id = s.id GROUP BY s.id, s.name ORDER BY s.created_at DESC LIMIT 8";
  } else {
    $sql = "SELECT s.id, s.name,
      SUM(CASE WHEN datetime(l.created_at) >= datetime('now','-$upDays day') THEN l.up ELSE NULL END) AS upN,
      SUM(CASE WHEN datetime(l.created_at) >= datetime('now','-$upDays day') THEN 1 ELSE NULL END) AS totN
      FROM servers s LEFT JOIN server_status_log l ON l.server_id = s.id GROUP BY s.id, s.name ORDER BY s.id DESC LIMIT 8";
  }
  $uptime = $pdo->query($sql)->fetchAll();
} catch (Throwable $e) {}

// Crescimento de usuários por mês (últimos 6 meses)
$usersByMonth = [];
try {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'mysql') {
    $usersByMonth = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS m, COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY 1 ORDER BY 1")->fetchAll();
  } else {
    $usersByMonth = $pdo->query("SELECT strftime('%Y-%m-01', created_at) AS m, COUNT(*) AS c FROM users WHERE date(created_at) >= date('now','-6 months') GROUP BY 1 ORDER BY 1")->fetchAll();
  }
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatórios - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .kpi { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,.06); }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
          <i class="bi bi-bar-chart text-primary"></i><?= render_brand_html(); ?>
        </a>
        <div class="d-flex ms-auto gap-2">
          <a class="btn btn-outline-light btn-sm" href="index.php"><i class="bi bi-grid me-1"></i>Dashboard</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-0">Relatórios</h1>
          <small class="text-secondary">KPIs, documentos e servidores</small>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="p-3 rounded kpi">
            <div class="text-secondary small">Usuários ativos</div>
            <div class="display-6 fw-bold"><?= (int)$kpis['users_active'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="p-3 rounded kpi">
            <div class="text-secondary small">Documentos</div>
            <div class="display-6 fw-bold"><?= (int)$kpis['documents_total'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="p-3 rounded kpi">
            <div class="text-secondary small">Notícias publicadas</div>
            <div class="display-6 fw-bold"><?= (int)$kpis['news_published'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="p-3 rounded kpi">
            <div class="text-secondary small">Servidores</div>
            <div class="display-6 fw-bold"><?= (int)$kpis['servers_total'] ?></div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-3">Documentos por categoria</h2>
              <canvas id="chartCats" height="160"></canvas>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-3">Uploads (versões) por mês</h2>
              <canvas id="chartMonths" height="160"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-3">Top 5 downloads (30 dias)</h2>
              <?php if (!empty($topDownloads)): ?>
              <ol class="mb-0">
                <?php foreach ($topDownloads as $td): ?>
                  <li class="mb-1 d-flex justify-content-between"><span><?= htmlspecialchars($td['title']) ?></span><span class="text-secondary"><?= (int)$td['cnt'] ?></span></li>
                <?php endforeach; ?>
              </ol>
              <?php else: ?>
                <div class="text-secondary">Sem dados recentes.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-3">Downloads por dia (30 dias)</h2>
              <canvas id="chartDownloads" height="160"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-3">Top usuários por downloads (30 dias)</h2>
              <?php if (!empty($topUsers)): ?>
              <ol class="mb-0">
                <?php foreach ($topUsers as $tu): ?>
                  <li class="mb-1 d-flex justify-content-between"><span><?= htmlspecialchars($tu['uname']) ?></span><span class="text-secondary"><?= (int)$tu['cnt'] ?></span></li>
                <?php endforeach; ?>
              </ol>
              <?php else: ?>
                <div class="text-secondary">Sem dados recentes.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12 col-lg-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6 mb-3">Crescimento de usuários (6 meses)</h2>
              <canvas id="chartUsers" height="160"></canvas>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($servers)): ?>
      <section class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Status dos servidores (live)</h2>
          <a href="settings.php?tab=servers" class="btn btn-sm btn-outline-light">Gerenciar</a>
        </div>
        <div class="row g-3">
          <?php foreach ($servers as $sv): $sid=(int)$sv['id']; $host=$sv['host']?:$sv['ip']; ?>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="card glass h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <strong><?= htmlspecialchars($sv['name']) ?></strong>
                    <div class="text-secondary small"><?= htmlspecialchars($sv['protocol']) ?> • <?= htmlspecialchars($host) ?>:<?= (int)$sv['port'] ?></div>
                  </div>
                  <a href="server_view.php?id=<?= $sid ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i></a>
                </div>
                <div class="mt-2 d-flex justify-content-between align-items-center">
                  <span id="r-status-<?= $sid ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Checando…</span>
                  <small class="text-secondary"><span id="r-lat-<?= $sid ?>">—</span> <span id="r-http-<?= $sid ?>" class="ms-1"></span></small>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($uptime)): ?>
      <section class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Uptime</h2>
          <form method="get" class="d-inline-flex align-items-center gap-2">
            <input type="hidden" name="_" value="1">
            <label class="text-secondary small">Período</label>
            <select class="form-select form-select-sm" name="up_days" onchange="this.form.submit()">
              <option value="7" <?= $upDays===7?'selected':'' ?>>7 dias</option>
              <option value="30" <?= $upDays===30?'selected':'' ?>>30 dias</option>
              <option value="90" <?= $upDays===90?'selected':'' ?>>90 dias</option>
            </select>
          </form>
        </div>
        <div class="card glass">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Servidor</th>
                    <th class="text-end">Uptime (<?= (int)$upDays ?>d)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($uptime as $u): 
                    $pN = ($u['totN'] ?? 0) > 0 ? round(((int)$u['upN'] / (int)$u['totN']) * 100, 1) : null;
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td class="text-end">
                      <?php if ($pN !== null): ?>
                        <span class="badge <?= $pN>=99? 'bg-success-subtle text-success border border-success-subtle':'bg-secondary-subtle text-secondary border border-secondary-subtle' ?>"><?= $pN ?>%</span>
                      <?php else: ?>
                        <span class="text-secondary">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($servers)): ?>
      <section class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Latência média diária</h2>
          <form class="d-inline-flex align-items-center gap-2" id="latencyForm" onsubmit="return false;">
            <label class="text-secondary small">Servidor</label>
            <select class="form-select form-select-sm" id="latServer">
              <?php foreach ($servers as $sv): ?>
                <option value="<?= (int)$sv['id'] ?>"><?= htmlspecialchars($sv['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="text-secondary small">Período</label>
            <select class="form-select form-select-sm" id="latDays">
              <option value="7">7 dias</option>
              <option value="30" selected>30 dias</option>
              <option value="90">90 dias</option>
            </select>
            <button class="btn btn-sm btn-outline-light" id="latRefresh">Atualizar</button>
          </form>
        </div>
        <div class="card glass">
          <div class="card-body">
            <canvas id="chartLatency" height="160"></canvas>
          </div>
        </div>
      </section>
      <?php endif; ?>

    </main>

    <script>
      const catLabels = <?= json_encode($catLabels) ?>;
      const catValues = <?= json_encode($catValues) ?>;
      const monLabels = <?= json_encode($monLabels) ?>;
      const monValues = <?= json_encode($monValues) ?>;
      const ctx1 = document.getElementById('chartCats');
      const ctx2 = document.getElementById('chartMonths');
      const ctx3 = document.getElementById('chartDownloads');
      const ctx4 = document.getElementById('chartUsers');
      const ctxLatency = document.getElementById('chartLatency');
      if (ctx1) {
        new Chart(ctx1, {
          type: 'bar',
          data: { labels: catLabels, datasets: [{ label: 'Docs', data: catValues, backgroundColor: 'rgba(13,110,253,.4)', borderColor: 'rgba(13,110,253,.8)' }] },
          options: { scales: { y: { beginAtZero: true } } }
        });
      }
      if (ctx2) {
        new Chart(ctx2, {
          type: 'line',
          data: { labels: monLabels, datasets: [{ label: 'Versões', data: monValues, fill: true, tension:.25, borderColor: 'rgba(16,185,129,.9)', backgroundColor: 'rgba(16,185,129,.2)' }] },
          options: { scales: { y: { beginAtZero: true } } }
        });
      }
      if (ctx3) {
        const dLabels = <?= json_encode(array_map(fn($r)=> $r['d'], $dailyDownloads)) ?>;
        const dValues = <?= json_encode(array_map(fn($r)=> (int)$r['c'], $dailyDownloads)) ?>;
        new Chart(ctx3, {
          type: 'bar',
          data: { labels: dLabels, datasets: [{ label: 'Downloads', data: dValues, backgroundColor: 'rgba(99,102,241,.4)', borderColor: 'rgba(99,102,241,.8)' }] },
          options: { scales: { y: { beginAtZero: true } } }
        });
      }
      if (ctx4) {
        const uLabels = <?= json_encode(array_map(fn($r)=> $r['m'], $usersByMonth)) ?>;
        const uValues = <?= json_encode(array_map(fn($r)=> (int)$r['c'], $usersByMonth)) ?>;
        new Chart(ctx4, {
          type: 'line',
          data: { labels: uLabels, datasets: [{ label: 'Usuários criados', data: uValues, fill: true, tension:.25, borderColor: 'rgba(234,179,8,.9)', backgroundColor: 'rgba(234,179,8,.2)' }] },
          options: { scales: { y: { beginAtZero: true } } }
        });
      }
      let latencyChart = null;
      async function loadLatency() {
        const sid = document.getElementById('latServer')?.value;
        const days = document.getElementById('latDays')?.value || 30;
        if (!sid || !ctxLatency) return;
        try {
          const r = await fetch('server_latency_api.php?server_id=' + sid + '&days=' + days);
          if (!r.ok) return; const data = await r.json();
          const labels = data.labels || [];
          const avg = data.avg_latency_ms || [];
          const upt = data.uptime_pct || [];
          if (latencyChart) { latencyChart.destroy(); }
          latencyChart = new Chart(ctxLatency, {
            type: 'line',
            data: { labels, datasets: [
              { label: 'Latência média (ms)', data: avg, borderColor: 'rgba(59,130,246,.9)', backgroundColor: 'rgba(59,130,246,.15)', tension:.25, yAxisID: 'y1', spanGaps: true },
              { label: 'Uptime (%)', data: upt, borderColor: 'rgba(34,197,94,.9)', backgroundColor: 'rgba(34,197,94,.15)', tension:.25, yAxisID: 'y2', spanGaps: true }
            ]},
            options: { scales: { y1: { type: 'linear', position: 'left', beginAtZero: true }, y2: { type: 'linear', position: 'right', beginAtZero: true, suggestedMax: 100 } } }
          });
        } catch (e) {}
      }
      document.getElementById('latRefresh')?.addEventListener('click', loadLatency);
      if (ctxLatency) { loadLatency(); }
    </script>

    <?php if (!empty($servers)): ?>
    <script>
      (function(){
        const ids = [<?= implode(',', array_map(fn($s)=> (int)$s['id'], $servers)) ?>];
        async function tick(){
          try {
            const r = await fetch('server_status_api.php?log=1&ids=' + ids.join(','));
            if (!r.ok) return; const data = await r.json();
            (data.items||[]).forEach(it => {
              const st = document.getElementById('r-status-' + it.id);
              const lt = document.getElementById('r-lat-' + it.id);
              const hc = document.getElementById('r-http-' + it.id);
              if (st){ st.className = 'badge ' + (it.up ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'); st.textContent = it.up ? 'Online' : 'Offline'; }
              if (lt){ lt.textContent = it.latency_ms ? (it.latency_ms + ' ms') : '—'; }
              if (hc){ hc.textContent = it.http_code ? ('HTTP ' + it.http_code) : ''; }
            });
          } catch(e) {}
        }
        tick();
        setInterval(tick, 10000);
      })();
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
