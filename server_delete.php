<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_settings'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: settings.php?tab=servers'); exit(); }

try {
    $del = $pdo->prepare('DELETE FROM servers WHERE id = :id');
    $del->execute([':id' => $id]);
} catch (Throwable $e) {
    // silencioso
}
header('Location: settings.php?tab=servers');
exit();
