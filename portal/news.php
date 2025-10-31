<?php declare(strict_types=1);
session_start();

$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

if (empty($_SESSION['portal_user']['id'])) {
    header('Location: login.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '';

if ($q !== '') {
    $where = 'AND title LIKE :q';
    $params[':q'] = "%$q%";
}

$sql = "
    SELECT id, title, is_published, is_pinned, created_at
    FROM news
    WHERE (is_published = 1 OR is_published IS NULL)
    $where
    ORDER BY is_pinned DESC, created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title> Notícias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .glass {
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(17,25,40,.35);
            backdrop-filter: blur(8px);
        }
        .card:hover {
            background: rgba(255,255,255,.05);
            transition: .2s;
        }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">

<nav class="navbar navbar-expand-lg glass">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <?php echo render_brand_html(__DIR__ . '/../assets/logotipo.png'); ?></a>
        </a>
        <div class="ms-auto">
            <a class="btn btn-outline-danger btn-sm" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h5 mb-0">📰 Notícias</h1>
        <form class="d-flex" method="get">
            <input class="form-control form-control-sm me-2"
                   name="q"
                   value="<?= htmlspecialchars($q) ?>"
                   placeholder="Pesquisar por título...">
            <?php if ($q !== ''): ?>
                <a href="news.php" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-light">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <div class="row g-3">
        <?php foreach ($rows as $n): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <a class="text-decoration-none" href="news_view.php?id=<?= (int)$n['id'] ?>">
                    <div class="card glass h-100 p-2">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <strong class="me-2"><?= htmlspecialchars($n['title']) ?></strong>
                                <?php if ((int)$n['is_pinned'] === 1): ?>
                                    <i class="bi bi-pin-angle-fill text-warning" title="Fixada" aria-label="Notícia fixada"></i>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary small mt-2">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
            <div class="col-12 text-secondary fst-italic">Nenhuma notícia encontrada.</div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
