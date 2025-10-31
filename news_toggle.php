<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_news'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$act = isset($_GET['act']) ? trim($_GET['act']) : '';
if ($id <= 0 || !in_array($act, ['pin','pub'], true)) { header('Location: news.php'); exit(); }

try {
    if ($act === 'pin') {
        $pdo->exec('UPDATE news SET is_pinned = CASE WHEN is_pinned=1 THEN 0 ELSE 1 END WHERE id = '.(int)$id);
    } elseif ($act === 'pub') {
        $pdo->exec('UPDATE news SET is_published = CASE WHEN COALESCE(is_published,1)=1 THEN 0 ELSE 1 END WHERE id = '.(int)$id);
    }
} catch (Throwable $e) {
    // silenciar
}
header('Location: news.php');
exit();
