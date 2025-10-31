<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$pdo = require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['error'=>'auth']); exit; }
$uid = (int)$_SESSION['portal_user']['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jerr($m,$c=400){ http_response_code($c); echo json_encode(['error'=>$m]); exit; }

try{
  if ($method==='POST') { if (!csrf_check_request()) { jerr('csrf',403); } }
  switch ($action){
    case 'list': {
      $scope = trim((string)($_GET['scope'] ?? 'global'));
      $sql = "SELECT id, scope, user_id, title, body FROM canned_replies WHERE scope IN ('global', :sc) AND (user_id IS NULL OR user_id=:u) ORDER BY scope='global' DESC, title ASC";
      try{ $st=$pdo->prepare($sql); $st->execute([':sc'=>$scope, ':u'=>$uid]); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db_list',500);}    
      echo json_encode(['ok'=>true,'items'=>$rows]);
      break;
    }
    case 'create': {
      if ($method!=='POST') jerr('method');
      $scope = trim((string)($_POST['scope'] ?? '')); if($scope==='') jerr('scope');
      $title = trim((string)($_POST['title'] ?? '')); if($title==='') jerr('title');
      $body  = trim((string)($_POST['body'] ?? '')); if($body==='') jerr('body');
      $userId = null;
      // Somente admin pode criar global; demais criam por usuário no escopo escolhido
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      $isAdmin = in_array('admin',(array)$roles,true);
      if ($scope==='global' && !$isAdmin) jerr('forbidden',403);
      if ($scope!=='global') { $userId = $uid; }
      try{ $st=$pdo->prepare('INSERT INTO canned_replies(scope, user_id, title, body) VALUES(:s,:u,:t,:b)'); $st->execute([':s'=>$scope, ':u'=>$userId, ':t'=>$title, ':b'=>$body]); $id=(int)$pdo->lastInsertId(); }catch(Throwable $e){ jerr('db_create',500);}    
      echo json_encode(['ok'=>true,'id'=>$id]);
      break;
    }
    case 'delete': {
      if ($method!=='POST') jerr('method');
      $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
      // Permitir deletar se for admin, ou dono do registro (user_id=uid), ou se global e admin
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      $isAdmin = in_array('admin',(array)$roles,true);
      try{ $st=$pdo->prepare('SELECT user_id, scope FROM canned_replies WHERE id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch(); if(!$r) jerr('not_found',404); $owner=(int)($r['user_id']??0); $scope=(string)($r['scope']??''); if(!$isAdmin && $owner!==$uid) jerr('forbidden',403); }catch(Throwable $e){ jerr('db_select',500);}    
      try{ $st=$pdo->prepare('DELETE FROM canned_replies WHERE id=:id'); $st->execute([':id'=>$id]); }catch(Throwable $e){ jerr('db_delete',500);}    
      echo json_encode(['ok'=>true]);
      break;
    }
    default: jerr('action');
  }
}catch(Throwable $e){ http_response_code(500); echo json_encode(['error'=>'server']); }
