<?php
session_start();
header('Content-Type: application/json');
$pdo = require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit(); }
$me = $_SESSION['portal_user'];
$uid = (int)$me['id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function jerr($msg, $code=400){ http_response_code($code); echo json_encode(['error'=>$msg]); exit(); }

try {
  switch ($action) {
    case 'list_users': {
      $q = trim($_GET['q'] ?? '');
      $sql = "SELECT u.id, COALESCE(u.name,u.username) AS label,
              (SELECT COUNT(*) FROM user_messages m WHERE m.receiver_id = :me AND m.sender_id = u.id AND m.is_read=0) AS unread,
              COALESCE(p.status, 'offline') AS presence
              FROM users u LEFT JOIN user_presence p ON p.user_id=u.id
              WHERE u.id<>:me";
      $params = [':me'=>$uid];
      if ($q !== '') { $sql .= " AND (u.username LIKE :q OR u.name LIKE :q)"; $params[':q'] = '%'.$q.'%'; }
      $sql .= " ORDER BY label ASC LIMIT 50";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll();
      echo json_encode(['items'=>$rows]);
      break;
    }
    case 'unread_total': {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_messages WHERE receiver_id=:u AND is_read=0');
      $stmt->execute([':u'=>$uid]);
      echo json_encode(['count'=>(int)$stmt->fetchColumn()]);
      break;
    }
    case 'convo': {
      $peer = (int)($_GET['user'] ?? 0);
      if ($peer<=0) jerr('user required');
      $sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
      if ($sinceId>0) {
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, body, file_path, file_name, file_type, file_size, is_read, created_at FROM user_messages WHERE ((sender_id=:u AND receiver_id=:p) OR (sender_id=:p AND receiver_id=:u)) AND id>:sid ORDER BY id ASC LIMIT 200");
        $stmt->execute([':u'=>$uid, ':p'=>$peer, ':sid'=>$sinceId]);
      } else {
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, body, file_path, file_name, file_type, file_size, is_read, created_at FROM user_messages WHERE (sender_id=:u AND receiver_id=:p) OR (sender_id=:p AND receiver_id=:u) ORDER BY id DESC LIMIT 100");
        $stmt->execute([':u'=>$uid, ':p'=>$peer]);
      }
      $rows = $stmt->fetchAll();
      if ($sinceId<=0) { $rows = array_reverse($rows); }
      echo json_encode(['items'=>$rows]);
      break;
    }
    case 'send': {
      if ($_SERVER['REQUEST_METHOD']!=='POST') jerr('method');
      $peer = (int)($_POST['user'] ?? 0);
      $body = trim($_POST['body'] ?? '');
      if ($peer<=0 || $body==='') jerr('invalid');
      $ins = $pdo->prepare('INSERT INTO user_messages (sender_id, receiver_id, body) VALUES (:s,:r,:b)');
      $ins->execute([':s'=>$uid, ':r'=>$peer, ':b'=>$body]);
      $mid = (int)$pdo->lastInsertId();
      // notify peer
      try{
        $u = $pdo->prepare('SELECT COALESCE(name,username) FROM users WHERE id=:id');
        $u->execute([':id'=>$uid]);
        $meName = (string)($u->fetchColumn() ?: '');
        $n = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u,:t,:ti,:b,:l)');
        $n->execute([':u'=>$peer, ':t'=>'chat', ':ti'=>'Nova mensagem', ':b'=>$meName, ':l'=>'/portal/chat.php?user='.$uid]);
      }catch(Throwable $e){}
      echo json_encode(['ok'=>true,'id'=>$mid]);
      break;
    }
    case 'presence_ping': {
      // update presence as online and last_seen
      $up = $pdo->prepare("INSERT INTO user_presence (user_id,last_seen,status) VALUES (:u, CURRENT_TIMESTAMP, 'online')
                           ON DUPLICATE KEY UPDATE last_seen=CURRENT_TIMESTAMP, status='online'");
      try { $up->execute([':u'=>$uid]); } catch (Throwable $e) {
        // SQLite fallback
        try { $pdo->prepare('INSERT OR REPLACE INTO user_presence (user_id,last_seen,status) VALUES (:u, datetime(\'now\'), \"online\")')->execute([':u'=>$uid]); } catch (Throwable $e2){}
      }
      echo json_encode(['ok'=>true]);
      break;
    }
    case 'typing_set': {
      $to = (int)($_POST['user'] ?? 0);
      if ($to<=0) jerr('invalid');
      // set typing for ~5s
      try {
        $stmt = $pdo->prepare("UPDATE user_presence SET typing_to=:to, typing_until=DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 SECOND) WHERE user_id=:u");
        $stmt->execute([':to'=>$to, ':u'=>$uid]);
      } catch (Throwable $e) {
        try { $pdo->prepare("UPDATE user_presence SET typing_to=:to, typing_until=datetime('now','+5 seconds') WHERE user_id=:u")
                  ->execute([':to'=>$to, ':u'=>$uid]); } catch (Throwable $e2) {}
      }
      echo json_encode(['ok'=>true]);
      break;
    }
    case 'typing_get': {
      $peer = (int)($_GET['user'] ?? 0);
      if ($peer<=0) jerr('invalid');
      $isTyping = false;
      try {
        $stmt = $pdo->prepare("SELECT typing_to, typing_until FROM user_presence WHERE user_id=:p");
        $stmt->execute([':p'=>$peer]);
        $row = $stmt->fetch();
        if ($row && (int)$row['typing_to'] === $uid) {
          // check expiry
          $stmt2 = $pdo->prepare("SELECT typing_until > CURRENT_TIMESTAMP FROM user_presence WHERE user_id=:p");
          $stmt2->execute([':p'=>$peer]);
          $isTyping = (bool)$stmt2->fetchColumn();
        }
      } catch (Throwable $e) { /* ignore */ }
      echo json_encode(['typing'=>$isTyping]);
      break;
    }
    case 'mark_read': {
      if ($_SERVER['REQUEST_METHOD']!=='POST') jerr('method');
      $peer = (int)($_POST['user'] ?? 0);
      if ($peer<=0) jerr('invalid');
      $upd = $pdo->prepare('UPDATE user_messages SET is_read=1 WHERE receiver_id=:me AND sender_id=:peer AND is_read=0');
      $upd->execute([':me'=>$uid, ':peer'=>$peer]);
      echo json_encode(['ok'=>true]);
      break;
    }
    default: jerr('unknown',404);
  }
} catch (Throwable $e) {
  jerr('server');
}
