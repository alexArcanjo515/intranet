<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/security_headers.php';

if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['error'=>'auth']); exit; }

function notify_user(PDO $pdo, int $toUserId, string $type, string $title, string $body, string $link=''): void {
  global $nowExpr; if ($toUserId<=0) return;
  try{
    $st=$pdo->prepare('INSERT INTO notifications(user_id, type, title, body, link, is_read, created_at) VALUES(:u,:t,:ti,:b,:l,0,'.$nowExpr.')');
    $st->execute([':u'=>$toUserId, ':t'=>$type, ':ti'=>$title, ':b'=>$body, ':l'=>$link]);
  }catch(Throwable $e){ /* ignore */ }
}

function notify_roles(PDO $pdo, array $roles, string $type, string $title, string $body, string $link=''): void {
  $candidates=[];
  try { // user_roles(user_id, role)
    $in = implode(',', array_fill(0, count($roles), '?'));
    $st=$pdo->prepare('SELECT DISTINCT user_id FROM user_roles WHERE role IN ('.$in.')');
    $st->execute($roles);
    $candidates = array_map(fn($r)=>(int)$r['user_id'], $st->fetchAll());
  } catch(Throwable $e) {
    try { // users.roles JSON
      $conds = array_map(fn($r)=>"JSON_CONTAINS(roles, '\"".$r."\"')", $roles);
      $sql='SELECT id FROM users WHERE '.implode(' OR ', $conds);
      $st=$pdo->query($sql);
      $candidates = array_map(fn($r)=>(int)$r['id'], $st->fetchAll());
    } catch(Throwable $e2) {
      try { // users.roles texto
        $conds = array_map(fn($r)=>"roles LIKE '%".$r."%'", $roles);
        $sql='SELECT id FROM users WHERE '.implode(' OR ', $conds);
        $st=$pdo->query($sql);
        $candidates = array_map(fn($r)=>(int)$r['id'], $st->fetchAll());
      } catch(Throwable $e3) { $candidates=[]; }
    }
  }
  $candidates = array_values(array_unique(array_filter($candidates)));
  foreach($candidates as $uid){ notify_user($pdo, $uid, $type, $title, $body, $link); }
}
$userId = (int)$_SESSION['portal_user']['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$nowExpr = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

function jerr($m='bad_request', $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function require_csrf(){ if(($_POST['csrf'] ?? '') !== csrf_token()){ jerr('csrf', 403); } }
function has_col(PDO $pdo, string $table, string $col): bool {
  try{
    $drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($drv === 'sqlite') {
      $st = $pdo->query("PRAGMA table_info(".$table.")");
      foreach (($st? $st->fetchAll():[]) as $r) { if (isset($r['name']) && strcasecmp($r['name'], $col)===0) return true; }
      return false;
    } else {
      $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
      $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1');
      $st->execute([':db'=>$db, ':t'=>$table, ':c'=>$col]);
      return (bool)$st->fetchColumn();
    }
  }catch(Throwable $e){ return false; }
}

// helper: obter employee_id do usuário
function my_employee_id(PDO $pdo, int $userId): int {
  try{ $st=$pdo->prepare('SELECT id FROM hr_employees WHERE user_id=:u'); $st->execute([':u'=>$userId]); $eid=(int)$st->fetchColumn(); return $eid?:0; }catch(Throwable $e){ return 0; }
}

function can_access_request(PDO $pdo, int $rid, int $userId): bool {
  try{
    // acesso se for requester_user_id ou se employee_id pertencer ao user
    $sql = 'SELECT r.requester_user_id, r.employee_id, e.user_id AS emp_user_id
            FROM hr_requests r LEFT JOIN hr_employees e ON e.id = r.employee_id
            WHERE r.id=:id LIMIT 1';
    $st=$pdo->prepare($sql); $st->execute([':id'=>$rid]); $row=$st->fetch(); if(!$row) return false;
    if ((int)($row['requester_user_id']??0) === $userId) return true;
    if ((int)($row['emp_user_id']??0) === $userId) return true;
    return false;
  }catch(Throwable $e){ return false; }
}

switch($action){
  case 'my_requests_list': {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
    $off = ($page-1)*$per;
    $eid = my_employee_id($pdo, $userId);
    $where = 'WHERE requester_user_id = :u';
    if ($eid>0) { $where = 'WHERE (requester_user_id = :u OR employee_id = :e)'; }
    $sql = 'SELECT id, type, subject, status, created_at FROM hr_requests ' . $where . ' ORDER BY created_at DESC LIMIT :per OFFSET :off';
    try{ $st=$pdo->prepare($sql); $st->bindValue(':u',$userId,PDO::PARAM_INT); if($eid>0){ $st->bindValue(':e',$eid,PDO::PARAM_INT);} $st->bindValue(':per',$per,PDO::PARAM_INT); $st->bindValue(':off',$off,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows,'page'=>$page,'per_page'=>$per]);
    break;
  }
  case 'my_request_get': {
    $id = (int)($_GET['id'] ?? 0); if($id<=0) jerr('id');
    if (!can_access_request($pdo, $id, $userId)) jerr('forbidden',403);
    try{ $st=$pdo->prepare('SELECT * FROM hr_requests WHERE id=:id'); $st->execute([':id'=>$id]); $row=$st->fetch(); if(!$row) jerr('not_found',404);}catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'item'=>$row]);
    break;
  }
  case 'my_request_create': {
    if ($method!=='POST') jerr('method'); require_csrf();
    $eid = my_employee_id($pdo, $userId); // pode ser 0
    $type = trim((string)($_POST['type'] ?? '')); if($type==='') jerr('type');
    $subject = trim((string)($_POST['subject'] ?? ''));
    $details = trim((string)($_POST['details'] ?? ''));
    $cols=['employee_id','type','status','created_at']; $vals=[':e',':t',':s', $nowExpr]; $params=[':e'=>($eid>0?$eid:0), ':t'=>$type, ':s'=>'pending'];
    if (has_col($pdo,'hr_requests','subject')){ $cols[]='subject'; $vals[]=':sub'; $params[':sub']=$subject; }
    if (has_col($pdo,'hr_requests','requester_user_id')){ $cols[]='requester_user_id'; $vals[]=':u'; $params[':u']=$userId; }
    if (has_col($pdo,'hr_requests','requester_name')){
      $reqName = (string)($_SESSION['portal_user']['name'] ?? $_SESSION['portal_user']['username'] ?? '');
      if($reqName===''){
        try{ $stn=$pdo->prepare('SELECT name FROM users WHERE id=:id'); $stn->execute([':id'=>$userId]); $reqName=(string)($stn->fetchColumn() ?: ''); }catch(Throwable $e){ $reqName=''; }
      }
      $cols[]='requester_name'; $vals[]=':run'; $params[':run']=$reqName;
    }
    if (has_col($pdo,'hr_requests','details')){ $cols[]='details'; $vals[]=':d'; $params[':d']=$details; }
    $sql='INSERT INTO hr_requests('.implode(',', $cols).') VALUES('.implode(',', $vals).')';
    try{ $st=$pdo->prepare($sql); $st->execute($params); $id=(int)$pdo->lastInsertId(); }catch(Throwable $e){ jerr('db: '.($e->getMessage()?:'fail'),500);}    
    // Notificar RH sobre nova solicitação
    $title = 'Nova solicitação RH #'.$id;
    $body = ($subject!==''?$subject:$type);
    notify_roles($pdo, ['rh_view','rh_operate','rh_manage','admin'], 'hr_request_new', $title, $body, '/portal/rh/requests_inbox.php');
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;
  }
  case 'my_request_comments_list': {
    $rid = (int)($_GET['request_id'] ?? 0); if($rid<=0) jerr('request_id');
    if (!can_access_request($pdo, $rid, $userId)) jerr('forbidden',403);
    try{ $st=$pdo->prepare('SELECT c.id, c.user_id, u.name AS user_name, c.body, c.created_at FROM hr_request_comments c LEFT JOIN users u ON u.id=c.user_id WHERE c.request_id=:r ORDER BY c.created_at ASC, c.id ASC'); $st->execute([':r'=>$rid]); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows]);
    break;
  }
  case 'my_request_comment_add': {
    if ($method!=='POST') jerr('method'); require_csrf();
    $rid = (int)($_POST['request_id'] ?? 0); if($rid<=0) jerr('request_id');
    if (!can_access_request($pdo, $rid, $userId)) jerr('forbidden',403);
    $body = trim((string)($_POST['body'] ?? '')); if($body==='') jerr('body');
    try{ $st=$pdo->prepare('INSERT INTO hr_request_comments(request_id, user_id, body, created_at) VALUES(:r,:u,:b,'.$nowExpr.')'); $st->execute([':r'=>$rid, ':u'=>$userId, ':b'=>$body]); }catch(Throwable $e){ jerr('db',500);}    
    // Notificar RH sobre novo comentário do solicitante
    notify_roles($pdo, ['rh_view','rh_operate','rh_manage','admin'], 'hr_request_comment', 'Nova resposta do colaborador', mb_substr($body,0,140), '/portal/rh/requests_inbox.php');
    echo json_encode(['ok'=>true]);
    break;
  }
  default: jerr('not_found',404);
}
