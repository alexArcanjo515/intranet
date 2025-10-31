<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['portal_user']['id'])) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit(); }
$pdo = require __DIR__ . '/../config/db.php';
$uid = (int)$_SESSION['portal_user']['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
  if ($method === 'GET' && $action === 'list') {
    $limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
    $stmt = $pdo->prepare('SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE user_id=:u ORDER BY is_read ASC, id DESC LIMIT :lim');
    $stmt->bindValue(':u', $uid, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();
    $unread = 0; foreach ($items as $it) { if ((int)$it['is_read'] === 0) $unread++; }
    // chat unread count
    try{
      $cstmt = $pdo->prepare('SELECT COUNT(*) FROM user_messages WHERE receiver_id=:u AND is_read=0');
      $cstmt->execute([':u'=>$uid]);
      $chatUnread = (int)$cstmt->fetchColumn();
    }catch(Throwable $e){ $chatUnread = 0; }
    echo json_encode(['items'=>$items,'unread'=>$unread,'chat_unread'=>$chatUnread]);
    exit();
  }
  if ($action === 'mark_read') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
      $up = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=:id AND user_id=:u');
      $up->execute([':id'=>$id, ':u'=>$uid]);
    } else {
      $up = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=:u');
      $up->execute([':u'=>$uid]);
    }
    echo json_encode(['ok'=>true]);
    exit();
  }
  http_response_code(400);
  echo json_encode(['error'=>'bad_request']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'server_error']);
}
