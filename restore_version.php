<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_documents'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: documents.php'); exit(); }

try {
    // Obter versão
    $stmt = $pdo->prepare('SELECT dv.id, dv.path, dv.version, dv.document_id, d.title, d.category FROM document_versions dv INNER JOIN documents d ON d.id = dv.document_id WHERE dv.id = :id');
    $stmt->execute([':id' => $id]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: documents.php'); exit(); }

    // Atualizar documento para apontar para o path da versão escolhida e ajustar version
    $upd = $pdo->prepare('UPDATE documents SET path = :p, version = :v, category = :c WHERE id = :did');
    $upd->execute([':p' => $v['path'], ':v' => (int)$v['version'], ':c' => $v['category'], ':did' => (int)$v['document_id']]);

    // Registrar nova entrada de version (duplicamos a versão escolhida como última? Melhor: criar nova versão +1 referenciando o arquivo antigo?)
    // Para manter coerência, criaremos nova versão incrementando +1 e copiando o arquivo para manter trilha imutável
    $uploadBase = __DIR__ . '/storage/uploads/documents';
    if (!is_dir($uploadBase)) { mkdir($uploadBase, 0775, true); }

    $srcAbs = __DIR__ . '/' . $v['path'];
    if (!is_file($srcAbs)) { header('Location: document_versions.php?id='.(int)$v['document_id']); exit(); }

    // calcular nova versão (+1)
    $curV = (int)$pdo->query('SELECT version FROM documents WHERE id='.(int)$v['document_id'])->fetchColumn();
    $newV = $curV + 1;

    $ext = pathinfo($srcAbs, PATHINFO_EXTENSION);
    $safeTitle = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $v['title']);
    $ts = date('Ymd_His');
    $filename = $safeTitle . '_v' . $newV . '_' . $ts . ($ext ? ('.' . $ext) : '');
    $destAbs = $uploadBase . '/' . $filename;
    if (!copy($srcAbs, $destAbs)) { header('Location: document_versions.php?id='.(int)$v['document_id']); exit(); }
    $relPath = 'storage/uploads/documents/' . $filename;

    // Atualiza documento com novo path e versão
    $upd2 = $pdo->prepare('UPDATE documents SET path = :p, version = :v WHERE id = :did');
    $upd2->execute([':p' => $relPath, ':v' => $newV, ':did' => (int)$v['document_id']]);

    // Registra nova versão
    $iv = $pdo->prepare('INSERT INTO document_versions (document_id, version, path, uploaded_by) VALUES (:d, :v, :p, :u)');
    $iv->execute([':d' => (int)$v['document_id'], ':v' => $newV, ':p' => $relPath, ':u' => (int)$_SESSION['user']['id']]);

    header('Location: document_versions.php?id='.(int)$v['document_id']);
    exit();
} catch (Throwable $e) {
    header('Location: documents.php');
    exit();
}
