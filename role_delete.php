<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { header('Location: login.php'); exit(); }
$rolesSession = $_SESSION['user']['roles'] ?? [];
if (!in_array('admin', $rolesSession, true)) { http_response_code(403); echo 'Acesso negado.'; exit(); }

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: roles.php'); exit(); }

try {
    if ($id === 1) { // papel admin protegido
        header('Location: roles.php');
        exit();
    }

    // verificar se há usuários vinculados
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = :id');
    $cnt->execute([':id' => $id]);
    if ((int)$cnt->fetchColumn() > 0) {
        // opcional: definir flash informando que não pode excluir
        header('Location: roles.php');
        exit();
    }

    // excluir role (FK em role_permissions apaga vínculos)
    $del = $pdo->prepare('DELETE FROM roles WHERE id = :id');
    $del->execute([':id' => $id]);
} catch (Throwable $e) {
    // silenciar
}
header('Location: roles.php');
exit();
