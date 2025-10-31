<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); exit('Arquivo não encontrado.'); }

$stmt = $pdo->prepare('SELECT path FROM document_versions WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('Arquivo não encontrado.'); }

$abs = __DIR__ . '/' . $row['path'];
if (!is_file($abs)) { http_response_code(404); exit('Arquivo não encontrado.'); }

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
