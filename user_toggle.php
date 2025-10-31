<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { header('Location: login.php'); exit(); }
// Permissão: manage_users OU papel admin
$roles = $_SESSION['user']['roles'] ?? [];
$perms = $_SESSION['user']['perms'] ?? [];
if (!in_array('admin', $roles, true) && !in_array('manage_users', $perms, true)) { http_response_code(403); echo 'Acesso negado.'; exit(); }
$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id > 0 && in_array($action, ['activate','deactivate'], true)) {
    try {
        // opcional: impedir desativar a si mesmo
        if ($id === (int)$_SESSION['user']['id'] && $action === 'deactivate') {
            header('Location: users.php');
            exit();
        }
        $value = $action === 'activate' ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE users SET is_active = :v WHERE id = :id');
        $stmt->execute([':v'=>$value, ':id'=>$id]);
    } catch (Throwable $e) {
        // silencioso; manter simples
    }
}
header('Location: users.php');
exit();
