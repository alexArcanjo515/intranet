<?php
session_start();
$pdo = require __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit(); }
$uid = (int)$_SESSION['portal_user']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'method']); exit(); }
$peer = isset($_POST['user']) ? (int)$_POST['user'] : 0;
if ($peer <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid_user']); exit(); }

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) { http_response_code(400); echo json_encode(['error'=>'no_file']); exit(); }
$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['error'=>'upload_error']); exit(); }

$origName = basename($f['name']);
$size = (int)$f['size'];
$type = (string)($f['type'] ?? 'application/octet-stream');
if ($size <= 0) { http_response_code(400); echo json_encode(['error'=>'empty']); exit(); }

$ext = pathinfo($origName, PATHINFO_EXTENSION);
$dir = __DIR__ . '/../uploads/chat';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
$fname = uniqid('chat_', true) . ($ext ? ('.' . preg_replace('/[^a-zA-Z0-9_.-]/','', $ext)) : '');
$path = $dir . '/' . $fname;
if (!move_uploaded_file($f['tmp_name'], $path)) { http_response_code(500); echo json_encode(['error'=>'save_failed']); exit(); }

// public path
$relPath = '/uploads/chat/' . $fname;

try {
  $stmt = $pdo->prepare('INSERT INTO user_messages (sender_id, receiver_id, body, file_path, file_name, file_type, file_size) VALUES (:s,:r,:b,:fp,:fn,:ft,:fs)');
  $stmt->execute([
    ':s'=>$uid,
    ':r'=>$peer,
    ':b'=>trim((string)($_POST['body'] ?? '')) ?: null,
    ':fp'=>$relPath,
    ':fn'=>$origName,
    ':ft'=>$type,
    ':fs'=>$size
  ]);
  $mid = (int)$pdo->lastInsertId();
  // notify peer
  try{
    $u = $pdo->prepare('SELECT COALESCE(name,username) FROM users WHERE id=:id');
    $u->execute([':id'=>$uid]);
    $meName = (string)($u->fetchColumn() ?: '');
    $n = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u,:t,:ti,:b,:l)');
    $n->execute([':u'=>$peer, ':t'=>'chat', ':ti'=>'Nova mensagem', ':b'=>$meName, ':l'=>'/portal/chat.php?user='.$uid]);
  }catch(Throwable $e){}
  echo json_encode(['ok'=>true,'id'=>$mid,'file'=>['path'=>$relPath,'name'=>$origName,'type'=>$type,'size'=>$size]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'db']);
}
