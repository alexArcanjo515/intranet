<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_documents'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: documents.php'); exit(); }

try {
    $stmt = $pdo->prepare('SELECT id, path FROM documents WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch();
    if ($doc) {
        // Excluir arquivo
        $abs = __DIR__ . '/' . $doc['path'];
        if (is_file($abs)) { @unlink($abs); }
        // Excluir registro
        $del = $pdo->prepare('DELETE FROM documents WHERE id = :id');
        $del->execute([':id' => $id]);
    }
} catch (Throwable $e) {
    // silencioso
}
header('Location: documents.php');
exit();
