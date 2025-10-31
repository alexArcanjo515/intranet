<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_login();
require_any(['manage_settings'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

// Helpers settings CRUD
function get_setting(PDO $pdo, string $key, $default = '') {
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :k');
    $stmt->execute([':k' => $key]);
    $v = $stmt->fetchColumn();
    return $v !== false ? $v : $default;
}
function set_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('INSERT INTO settings(`key`,`value`) VALUES(:k,:v)
        ON CONFLICT(`key`) DO UPDATE SET value = excluded.value');
    // MySQL fallback if ON CONFLICT not supported
    try { $stmt->execute([':k'=>$key, ':v'=>$value]); }
    catch (Throwable $e) {
        $stmt2 = $pdo->prepare('INSERT INTO settings(`key`,`value`) VALUES(:k,:v)
            ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        $stmt2->execute([':k'=>$key, ':v'=>$value]);
    }
}

$tab = $_GET['tab'] ?? 'identity';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($tab === 'identity') {
        set_setting($pdo, 'site_name', trim($_POST['site_name'] ?? 'Intranet'));
        set_setting($pdo, 'logo_url', trim($_POST['logo_url'] ?? ''));
        $msg = 'Identidade visual salva.';
    } elseif ($tab === 'theme') {
        set_setting($pdo, 'language', trim($_POST['language'] ?? 'pt-BR'));
        set_setting($pdo, 'theme', trim($_POST['theme'] ?? 'dark'));
        $msg = 'Idioma e tema salvos.';
    } elseif ($tab === 'params') {
        set_setting($pdo, 'timezone', trim($_POST['timezone'] ?? 'UTC'));
        set_setting($pdo, 'items_per_page', (string)max(5, (int)($_POST['items_per_page'] ?? 10)));
        $msg = 'Parâmetros do sistema salvos.';
    }
}

// Load settings values
$site_name = get_setting($pdo, 'site_name', 'Intranet');
$logo_url = get_setting($pdo, 'logo_url', '');
$language = get_setting($pdo, 'language', 'pt-BR');
$theme = get_setting($pdo, 'theme', 'dark');
$timezone = get_setting($pdo, 'timezone', 'UTC');
$items_per_page = get_setting($pdo, 'items_per_page', '10');

// Servers list
$servers = $pdo->query('SELECT id, name, ip, host, protocol, port, username FROM servers ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurações - Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass { border: 1px solid rgba(255,255,255,.08); background: rgba(17, 25, 40, .35); backdrop-filter: blur(8px); }
      .hero-gradient { background: radial-gradient(1000px 500px at 10% -10%, rgba(99,102,241,.18), transparent 70%), radial-gradient(800px 400px at 110% 10%, rgba(16,185,129,.16), transparent 60%); }
      .nav-pills .nav-link.active { background: rgba(13,110,253,.15); border: 1px solid rgba(13,110,253,.35); }
    </style>
  </head>
  <body class="hero-gradient min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php"><i class="bi bi-gear text-primary"></i><?= render_brand_html(); ?></a>
        <div class="d-flex ms-auto gap-2">
          <a class="btn btn-outline-light btn-sm" href="index.php"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <?php if ($msg): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle me-2"></i><div><?= htmlspecialchars($msg) ?></div></div>
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-12 col-md-3">
          <div class="card glass">
            <div class="list-group list-group-flush nav nav-pills flex-column">
              <a class="list-group-item list-group-item-action <?= $tab==='identity'?'active':'' ?>" href="?tab=identity"><i class="bi bi-palette me-2"></i>Identidade Visual</a>
              <a class="list-group-item list-group-item-action <?= $tab==='theme'?'active':'' ?>" href="?tab=theme"><i class="bi bi-translate me-2"></i>Idioma & Tema</a>
              <a class="list-group-item list-group-item-action <?= $tab==='params'?'active':'' ?>" href="?tab=params"><i class="bi bi-sliders me-2"></i>Parâmetros</a>
              <a class="list-group-item list-group-item-action <?= $tab==='servers'?'active':'' ?>" href="?tab=servers"><i class="bi bi-hdd-network me-2"></i>Servidores</a>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-9">
          <div class="card glass">
            <div class="card-body">
              <?php if ($tab === 'identity'): ?>
                <h2 class="h5 mb-3">Identidade Visual</h2>
                <form method="post">
                  <div class="mb-3">
                    <label class="form-label" for="site_name">Nome do sistema</label>
                    <input class="form-control" id="site_name" name="site_name" value="<?= htmlspecialchars($site_name) ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="logo_url">URL do logo</label>
                    <input class="form-control" id="logo_url" name="logo_url" value="<?= htmlspecialchars($logo_url) ?>">
                    <div class="form-text text-secondary">Use uma URL acessível. Ex.: /assets/logo.png</div>
                  </div>
                  <button class="btn btn-primary" type="submit">Salvar</button>
                </form>
              <?php elseif ($tab === 'theme'): ?>
                <h2 class="h5 mb-3">Idioma & Tema</h2>
                <form method="post">
                  <div class="mb-3">
                    <label class="form-label" for="language">Idioma</label>
                    <select class="form-select" id="language" name="language">
                      <option value="pt-BR" <?= $language==='pt-BR'?'selected':'' ?>>Português (Brasil)</option>
                      <option value="pt-PT" <?= $language==='pt-PT'?'selected':'' ?>>Português (Portugal)</option>
                      <option value="en-US" <?= $language==='en-US'?'selected':'' ?>>English</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="theme">Tema</label>
                    <select class="form-select" id="theme" name="theme">
                      <option value="dark" <?= $theme==='dark'?'selected':'' ?>>Escuro</option>
                      <option value="light" <?= $theme==='light'?'selected':'' ?>>Claro</option>
                    </select>
                  </div>
                  <button class="btn btn-primary" type="submit">Salvar</button>
                </form>
              <?php elseif ($tab === 'params'): ?>
                <h2 class="h5 mb-3">Parâmetros do Sistema</h2>
                <form method="post">
                  <div class="mb-3">
                    <label class="form-label" for="timezone">Timezone</label>
                    <input class="form-control" id="timezone" name="timezone" value="<?= htmlspecialchars($timezone) ?>" placeholder="Ex.: Europe/Lisbon">
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="items_per_page">Itens por página</label>
                    <input type="number" min="5" class="form-control" id="items_per_page" name="items_per_page" value="<?= (int)$items_per_page ?>">
                  </div>
                  <button class="btn btn-primary" type="submit">Salvar</button>
                </form>
              <?php elseif ($tab === 'servers'): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h2 class="h5 mb-0">Servidores acessíveis</h2>
                  <a href="server_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo servidor</a>
                </div>
                <div class="table-responsive">
                  <table class="table align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Nome</th><th>IP/Host</th><th>Protocolo</th><th>Porta</th><th>Usuário</th><th style="width:240px">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($servers as $s): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                          <td><?= htmlspecialchars($s['ip'] ?: $s['host'] ?: '—') ?></td>
                          <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($s['protocol']) ?></span></td>
                          <td><?= (int)$s['port'] ?></td>
                          <td><?= htmlspecialchars($s['username'] ?? '—') ?></td>
                          <td>
                            <div class="btn-group">
                              <a href="server_form.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-light" title="Editar"><i class="bi bi-pencil-square"></i></a>
                              <a href="server_test.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-info" title="Testar"><i class="bi bi-speedometer2"></i></a>
                              <a href="server_ping.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ping"><i class="bi bi-activity"></i></a>
                              <?php if (in_array($s['protocol'], ['http','https'], true)): ?>
                                <a href="server_view.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Visualizar"><i class="bi bi-box-arrow-up-right"></i></a>
                              <?php endif; ?>
                              <?php if ($s['protocol'] === 'ssh'): ?>
                                <a href="server_terminal.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-light" title="Terminal SSH"><i class="bi bi-terminal"></i></a>
                              <?php endif; ?>
                              <a href="server_delete.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este servidor?');" title="Excluir"><i class="bi bi-trash"></i></a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
