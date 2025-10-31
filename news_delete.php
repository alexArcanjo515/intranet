<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_news'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: news.php'); exit(); }

try {
    $del = $pdo->prepare('DELETE FROM news WHERE id = :id');
    $del->execute([':id' => $id]);
} catch (Throwable $e) {
    // silenciar
}
header('Location: news.php');
exit();
