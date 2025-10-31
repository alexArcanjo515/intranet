<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_users'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: users.php'); exit(); }

try {
    // Não permitir resetar a si mesmo opcionalmente? Aqui permitimos.
    $stmt = $pdo->prepare('SELECT id, username, is_active FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user) { header('Location: users.php'); exit(); }

    $plain = substr(str_replace(['+','/','='], '', base64_encode(random_bytes(9))), 0, 12);
    $hash = password_hash($plain, PASSWORD_DEFAULT);

    $upd = $pdo->prepare('UPDATE users SET password_hash = :p, must_change_password = 1 WHERE id = :id');
    $upd->execute([':p' => $hash, ':id' => $id]);

    $_SESSION['flash_password'] = ['username' => $user['username'], 'password' => $plain];
} catch (Throwable $e) {
    // opcional: definir outra flash de erro
}
header('Location: users.php');
exit();
