<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
// Leitura: permitir todos logados. Para restringir, poderíamos exigir permissão.

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); exit('Arquivo não encontrado.'); }

$stmt = $pdo->prepare('SELECT title, path FROM documents WHERE id = :id');
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Arquivo não encontrado.'); }

$abs = __DIR__ . '/' . $doc['path'];
if (!is_file($abs)) { http_response_code(404); exit('Arquivo não encontrado.'); }

$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
try {
    $ins = $pdo->prepare('INSERT INTO downloads_log (document_id, user_id) VALUES (:d, :u)');
    $ins->execute([':d' => $id, ':u' => $userId]);
} catch (Throwable $e) { /* ignore logging errors */ }

$filename = basename($abs);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit();
