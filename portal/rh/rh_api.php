<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/security_headers.php';

if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['error'=>'auth']); exit; }

// ===== Notificações =====
function notify_user(PDO $pdo, int $toUserId, string $type, string $title, string $body, string $link=''): void {
  global $nowExpr;
  if ($toUserId<=0) return;
  try{
    $st=$pdo->prepare('INSERT INTO notifications(user_id, type, title, body, link, is_read, created_at) VALUES(:u,:t,:ti,:b,:l,0,'.$nowExpr.')');
    $st->execute([':u'=>$toUserId, ':t'=>$type, ':ti'=>$title, ':b'=>$body, ':l'=>$link]);
  }catch(Throwable $e){ /* ignore */ }
}
function employee_user_id(PDO $pdo, int $employee_id): int {
  if($employee_id<=0) return 0;
  try{ $st=$pdo->prepare('SELECT user_id FROM hr_employees WHERE id=:id'); $st->execute([':id'=>$employee_id]); return (int)($st->fetchColumn()?:0); }catch(Throwable $e){ return 0; }
}
function requester_user_id_by_request(PDO $pdo, int $request_id): int {
  try{ $st=$pdo->prepare('SELECT requester_user_id FROM hr_requests WHERE id=:id'); $st->execute([':id'=>$request_id]); return (int)($st->fetchColumn()?:0); }catch(Throwable $e){ return 0; }
}

// Fase 2: criptografia, auditoria e PIN step-up
function mask_text($s){ if($s===null||$s==='') return ''; $len=strlen((string)$s); return str_repeat('•', min(6,max(3, (int)floor($len*0.6)))); }
function get_crypto_key(): ?string {
  $b64 = getenv('HR_CRYPTO_KEY') ?: '';
  if ($b64==='') return null;
  $key = base64_decode($b64, true);
  if ($key===false || strlen($key)!==32) return null;
  return $key;
}
function crypto_ready(): bool { return get_crypto_key()!==null; }
function enc_secure(string $plaintext): string {
  $key = get_crypto_key(); if($key===null) throw new RuntimeException('crypto_key');
  $iv = random_bytes(12);
  $tag = '';
  $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
  if($cipher===false) throw new RuntimeException('enc');
  return base64_encode($iv.$tag.$cipher);
}
function dec_secure(string $b64): string {
  $key = get_crypto_key(); if($key===null) throw new RuntimeException('crypto_key');
  $raw = base64_decode($b64, true); if($raw===false || strlen($raw)<28) throw new RuntimeException('b64');
  $iv = substr($raw,0,12); $tag = substr($raw,12,16); $cipher = substr($raw,28);
  $pt = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
  if($pt===false) throw new RuntimeException('dec');
  return $pt;
}
function pin_fresh(int $minutes=5): bool {
  $last = $_SESSION['rh_pin_last'] ?? null;
  if(!$last) return false;
  return (time() - (int)$last) <= ($minutes*60);
}
function audit_log(PDO $pdo, $employee_id, $action, $area, $meta=''){
  try{
    $drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowExpr = ($drv === 'mysql') ? 'NOW()' : "datetime('now')";
    $sql = "INSERT INTO hr_audit_log(employee_id, action, area, meta, created_at) VALUES(:e,:a,:r,:m, $nowExpr)";
    $st=$pdo->prepare($sql);
    $st->execute([':e'=>$employee_id, ':a'=>$action, ':r'=>$area, ':m'=>$meta]);
  }catch(Throwable $e){}
}
$userId = (int)$_SESSION['portal_user']['id'];
$roles = $_SESSION['portal_user']['roles'] ?? [];
$perms = $_SESSION['portal_user']['perms'] ?? [];
$pinOk = !empty($_SESSION['rh_pin_ok']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$nowExpr = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

function jerr($m='bad_request', $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function require_csrf(){ if(($_POST['csrf'] ?? '') !== csrf_token()){ jerr('csrf', 403); } }
function can_view(){
  global $roles, $perms;
  $r = (array)$roles; $p=(array)$perms;
  return in_array('admin',$r,true) || in_array('admin',$p,true)
      || in_array('rh_view',$r,true) || in_array('rh_view',$p,true)
      || in_array('rh_operate',$r,true) || in_array('rh_operate',$p,true)
      || in_array('rh_manage',$r,true) || in_array('rh_manage',$p,true);
}
function can_operate(){
  global $roles, $perms;
  $r = (array)$roles; $p=(array)$perms;
  return in_array('admin',$r,true) || in_array('admin',$p,true)
      || in_array('rh_operate',$r,true) || in_array('rh_operate',$p,true)
      || in_array('rh_manage',$r,true) || in_array('rh_manage',$p,true);
}
function can_manage(){
  global $roles, $perms;
  $r = (array)$roles; $p=(array)$perms;
  return in_array('admin',$r,true) || in_array('admin',$p,true)
      || in_array('rh_manage',$r,true) || in_array('rh_manage',$p,true);
}
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

if (!can_view()) { jerr('forbidden', 403); }
if (!$pinOk) { jerr('pin_required', 403); }

switch ($action) {
  case 'employees_list': {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
    $off = ($page-1)*$per;
    $q = trim((string)($_GET['q'] ?? ''));
    $where = '';$params=[];
    if ($q !== '') { $where = 'WHERE (e.name LIKE :q OR e.email LIKE :q)'; $params[':q'] = "%$q%"; }
    $sql = "SELECT e.id, e.name, e.email, d.name as dept, r.name as role
            FROM hr_employees e
            LEFT JOIN hr_departments d ON d.id=e.dept_id
            LEFT JOIN hr_roles_catalog r ON r.id=e.role_id
            $where
            ORDER BY e.name ASC
            LIMIT :per OFFSET :off";
    try { $st=$pdo->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k,$v); } $st->bindValue(':per',$per,PDO::PARAM_INT); $st->bindValue(':off',$off,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(); } catch (Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows,'page'=>$page,'per_page'=>$per]);
    break;
  }
  case 'policy_get': {
    $id = (int)($_GET['id'] ?? 0); if($id<=0) jerr('id');
    try{ $st=$pdo->prepare('SELECT * FROM hr_policies WHERE id=:id'); $st->execute([':id'=>$id]); $row=$st->fetch(); if(!$row) jerr('not_found',404);}catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'item'=>$row]);
    break;
  }
  case 'policy_create': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    // aceita multipart; CSRF no corpo
    require_csrf();
    $title = trim((string)($_POST['title'] ?? '')); if($title==='') jerr('title');
    $version = trim((string)($_POST['version'] ?? ''));
    $published_at = trim((string)($_POST['published_at'] ?? '')) ?: null;
    $file_path = null;
    if (isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      $f = $_FILES['file'];
      $ext = pathinfo($f['name'] ?? '', PATHINFO_EXTENSION);
      $safeExt = preg_replace('/[^a-zA-Z0-9]/','', $ext);
      $dir = __DIR__ . '/../../uploads/hr_policies'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $name = 'policy_'.date('Ymd_His').'_' . bin2hex(random_bytes(4)) . ($safeExt?('.'.$safeExt):'');
      $dest = $dir . '/' . $name; if (!move_uploaded_file($f['tmp_name'], $dest)) jerr('upload_failed',500);
      $file_path = '/uploads/hr_policies/' . $name;
    }
    $cols=['title','version']; $vals=[':t',':v']; $params=[':t'=>$title, ':v'=>$version];
    if (has_col($pdo,'hr_policies','file_path')){ $cols[]='file_path'; $vals[]=':p'; $params[':p']=$file_path; }
    if (has_col($pdo,'hr_policies','published_at')){ $cols[]='published_at'; $vals[]=':pa'; $params[':pa']=$published_at; }
    try{ $st=$pdo->prepare('INSERT INTO hr_policies('.implode(',', $cols).') VALUES('.implode(',', $vals).')'); $st->execute($params); $id=(int)$pdo->lastInsertId(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;
  }
  case 'policy_update': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    $fields=[]; $params=[':id'=>$id];
    if (isset($_POST['title'])){ $fields[]='title=:t'; $params[':t']=trim((string)$_POST['title']); }
    if (isset($_POST['version'])){ $fields[]='version=:v'; $params[':v']=trim((string)$_POST['version']); }
    if (has_col($pdo,'hr_policies','published_at') && array_key_exists('published_at', $_POST)){ $fields[]='published_at=:pa'; $params[':pa']=trim((string)$_POST['published_at']) ?: null; }
    // upload opcional para substituir arquivo
    if (isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      // buscar antigo
      try{ $old=$pdo->prepare('SELECT file_path FROM hr_policies WHERE id=:id'); $old->execute([':id'=>$id]); $fp=$old->fetchColumn(); }catch(Throwable $e){ jerr('db',500);}    
      // salvar novo
      $f = $_FILES['file']; $ext = pathinfo($f['name'] ?? '', PATHINFO_EXTENSION); $safeExt = preg_replace('/[^a-zA-Z0-9]/','', $ext);
      $dir = __DIR__ . '/../../uploads/hr_policies'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $name = 'policy_'.date('Ymd_His').'_' . bin2hex(random_bytes(4)) . ($safeExt?('.'.$safeExt):'');
      $dest = $dir . '/' . $name; if (!move_uploaded_file($f['tmp_name'], $dest)) jerr('upload_failed',500);
      $newPath = '/uploads/hr_policies/' . $name;
      $fields[]='file_path=:p'; $params[':p']=$newPath;
      // apagar antigo com segurança
      if ($fp){ $root = realpath(__DIR__.'/../../'); $full = realpath($root.$fp); if ($full && strpos($full, $root.'/uploads/hr_policies/')===0 && is_file($full)) { @unlink($full); } }
    }
    if(!$fields){ echo json_encode(['ok'=>true]); break; }
    try{ $st=$pdo->prepare('UPDATE hr_policies SET '.implode(',', $fields).' WHERE id=:id'); $st->execute($params);}catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'news_create': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $title = trim((string)($_POST['title'] ?? '')); if($title==='') jerr('title');
    $content = (string)($_POST['content'] ?? '');
    $is_published = (int)($_POST['is_published'] ?? 1) ? 1 : 0;
    $is_pinned = (int)($_POST['is_pinned'] ?? 0) ? 1 : 0;
    try{
      $st=$pdo->prepare('INSERT INTO news(title, content, is_published, is_pinned, created_at) VALUES(:t,:c,:pub,:pin, '.$nowExpr.')');
      $st->execute([':t'=>$title, ':c'=>$content, ':pub'=>$is_published, ':pin'=>$is_pinned]);
      $id=(int)$pdo->lastInsertId();
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;
  }
  case 'public_doc_upload': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) jerr('file');
    $title = trim((string)($_POST['title'] ?? 'Documento'));
    $category = trim((string)($_POST['category'] ?? '')) ?: null;
    $version = (int)($_POST['version'] ?? 1);
    $f = $_FILES['file'];
    $ext = pathinfo($f['name'] ?? '', PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/','', $ext);
    $dir = __DIR__ . '/../../uploads/docs'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'doc_'.date('Ymd_His').'_' . bin2hex(random_bytes(4)) . ($safeExt?('.'.$safeExt):'');
    $dest = $dir . '/' . $name; if (!move_uploaded_file($f['tmp_name'], $dest)) jerr('upload_failed',500);
    $rel = '/uploads/docs/' . $name;
    try{
      // alguns ambientes possuem created_by
      $hasCreatedBy = has_col($pdo,'documents','created_by');
      if ($hasCreatedBy){
        $st=$pdo->prepare('INSERT INTO documents(title, path, category, version, created_by, created_at) VALUES(:t,:p,:c,:v,:u, '.$nowExpr.')');
        $st->execute([':t'=>$title, ':p'=>$rel, ':c'=>$category, ':v'=>$version, ':u'=>$userId]);
      } else {
        $st=$pdo->prepare('INSERT INTO documents(title, path, category, version, created_at) VALUES(:t,:p,:c,:v, '.$nowExpr.')');
        $st->execute([':t'=>$title, ':p'=>$rel, ':c'=>$category, ':v'=>$version]);
      }
      $id=(int)$pdo->lastInsertId();
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'id'=>$id,'path'=>$rel]);
    break;
  }
  // ===== Pagamentos mensais =====
  case 'payments_list': {
    if (!can_manage()) jerr('forbidden',403);
    $eid = (int)($_GET['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    $year = (int)($_GET['year'] ?? 0);
    $params = [':e'=>$eid];
    $sql = 'SELECT p.id, p.employee_id, p.year, p.month, p.status, p.paid_at, p.amount_encrypted, p.currency, p.updated_by';
    // tentar incluir nome do utilizador
    if (has_col($pdo,'hr_payments','updated_by')){ $sql .= ', u.name AS updated_by_name'; }
    $sql .= ' FROM hr_payments p';
    if (has_col($pdo,'hr_payments','updated_by')){ $sql .= ' LEFT JOIN users u ON u.id = p.updated_by'; }
    $sql .= ' WHERE p.employee_id=:e';
    if($year>0){ $sql.=' AND year=:y'; $params[':y']=$year; }
    $sql.=' ORDER BY year DESC, month DESC, id DESC';
    try{ $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    $canShow = crypto_ready() && pin_fresh();
    $out=[]; $total=0.0; $hasAny=false;
    foreach($rows as $r){
      $amt=''; if($canShow && !empty($r['amount_encrypted'])){ try{ $amt=dec_secure($r['amount_encrypted']); }catch(Throwable $e){ $amt=''; } }
      if(($r['status']??'')==='paid' && $amt!==''){ $hasAny=true; $total += (float)$amt; }
      $out[]=[ 'id'=>$r['id'], 'year'=>$r['year'], 'month'=>$r['month'], 'status'=>$r['status'], 'paid_at'=>$r['paid_at'], 'currency'=>$r['currency'], 'amount'=>$amt!==''?$amt:null, 'amount_masked'=>($amt!==''? $amt : (($r['currency']??'').' •••')), 'updated_by'=>($r['updated_by']??null), 'updated_by_name'=>($r['updated_by_name']??null) ];
    }
    $resp = ['ok'=>true,'items'=>$out];
    if($canShow && $hasAny){ $resp['total_amount'] = $total; }
    echo json_encode($resp);
    break;
  }
  case 'payment_set': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $eid=(int)($_POST['employee_id']??0); if($eid<=0) jerr('employee_id');
    $year=(int)($_POST['year']??0); $month=(int)($_POST['month']??0); if($year<=0||$month<1||$month>12) jerr('period');
    $status=trim((string)($_POST['status']??'pending')); if(!in_array($status,['pending','paid'],true)) jerr('status');
    $currency = isset($_POST['currency'])? trim((string)$_POST['currency']) : null; if($currency!==null){ $allowed=['AOA','BRL','USD','EUR']; if(!in_array($currency,$allowed,true)) jerr('currency'); }
    $amount = isset($_POST['amount'])? trim((string)$_POST['amount']) : null;
    // Se marcar como pago sem informar valor, tenta usar o salário atual como default
    if($status==='paid' && ($amount===null || $amount==='')){
      try{
        $st=$pdo->prepare('SELECT amount_encrypted, currency FROM hr_salaries WHERE employee_id=:e ORDER BY COALESCE(valid_from, created_at) DESC, id DESC LIMIT 1');
        $st->execute([':e'=>$eid]); $s=$st->fetch();
        if($s){
          if($currency===null){ $currency = $s['currency'] ?? 'AOA'; }
          if(crypto_ready() && pin_fresh() && !empty($s['amount_encrypted'])){
            try{ $amount = dec_secure($s['amount_encrypted']); }catch(Throwable $e){ $amount=null; }
          }
        }
      }catch(Throwable $e){}
    }
    $enc = null; if($amount!==null && $amount!==''){ if(!crypto_ready()||!pin_fresh()) jerr('crypto_or_pin'); $enc = enc_secure($amount); }
    try{
      $pdo->beginTransaction();
      $sel=$pdo->prepare('SELECT id FROM hr_payments WHERE employee_id=:e AND year=:y AND month=:m'); $sel->execute([':e'=>$eid,':y'=>$year,':m'=>$month]); $pid=$sel->fetchColumn();
      if($pid){
        $fields=['status=:s']; $pp=[':s'=>$status, ':id'=>$pid];
        if($status==='paid'){ $fields[]='paid_at='.$nowExpr; } else { $fields[]='paid_at=NULL'; }
        if($enc!==null){ $fields[]='amount_encrypted=:a'; $pp[':a']=$enc; }
        if($currency!==null){ $fields[]='currency=:c'; $pp[':c']=$currency; }
        if(has_col($pdo,'hr_payments','updated_by')){ $fields[]='updated_by=:u'; $pp[':u']=$userId; }
        $sql='UPDATE hr_payments SET '.implode(',', $fields).' WHERE id=:id'; $pdo->prepare($sql)->execute($pp);
      } else {
        if(has_col($pdo,'hr_payments','updated_by')){
          $sql='INSERT INTO hr_payments(employee_id, year, month, status, paid_at, amount_encrypted, currency, updated_by) VALUES(:e,:y,:m,:s,'.($status==='paid'?$nowExpr:'NULL').', :a, :c, :u)';
          $pdo->prepare($sql)->execute([':e'=>$eid,':y'=>$year,':m'=>$month,':s'=>$status,':a'=>$enc,':c'=>$currency, ':u'=>$userId]);
        } else {
          $sql='INSERT INTO hr_payments(employee_id, year, month, status, paid_at, amount_encrypted, currency) VALUES(:e,:y,:m,:s,'.($status==='paid'?$nowExpr:'NULL').', :a, :c)';
          $pdo->prepare($sql)->execute([':e'=>$eid,':y'=>$year,':m'=>$month,':s'=>$status,':a'=>$enc,':c'=>$currency]);
        }
      }
      $pdo->commit();
      audit_log($pdo, $eid, 'update','payments', json_encode(['year'=>$year,'month'=>$month,'status'=>$status]));
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); jerr('db',500);}    
    // Notificar colaborador quando houver pagamento marcado como pago
    if($status==='paid'){
      $to = employee_user_id($pdo, $eid);
      if($to>0){ notify_user($pdo, $to, 'hr_payment_paid', 'Pagamento disponível', 'Seu pagamento de '.sprintf('%04d-%02d',$year,$month).' foi marcado como pago.', '/portal/index.php'); }
    }
    echo json_encode(['ok'=>true]);
    break;
  }
  // ===== Assiduidade =====
  case 'attendance_list': {
    if (!can_manage()) jerr('forbidden',403);
    $eid=(int)($_GET['employee_id']??0); if($eid<=0) jerr('employee_id');
    $year=(int)($_GET['year']??0); $month=(int)($_GET['month']??0);
    $sql='SELECT id, employee_id, date, status, note FROM hr_attendance WHERE employee_id=:e'; $pp=[':e'=>$eid];
    if($year>0){ $sql.=' AND strftime("%Y", date)=:y'; $pp[':y']=sprintf('%04d',$year); }
    if($month>0){ $sql.=' AND strftime("%m", date)=:m'; $pp[':m']=sprintf('%02d',$month); }
    $sql.=' ORDER BY date DESC, id DESC';
    // MySQL compat: usar YEAR(date), MONTH(date)
    if($driver==='mysql'){
      $sql='SELECT id, employee_id, date, status, note FROM hr_attendance WHERE employee_id=:e'; $conds=[]; if($year>0){ $conds[]='YEAR(date)=:y'; $pp[':y']=$year; } if($month>0){ $conds[]='MONTH(date)=:m'; $pp[':m']=$month; } if($conds){ $sql.=' AND '.implode(' AND ',$conds);} $sql.=' ORDER BY date DESC, id DESC';
    }
    try{ $st=$pdo->prepare($sql); $st->execute($pp); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows]);
    break;
  }
  case 'attendance_set': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $eid=(int)($_POST['employee_id']??0); if($eid<=0) jerr('employee_id');
    $date=trim((string)($_POST['date']??'')); if($date==='') jerr('date');
    $status=trim((string)($_POST['status']??'')); if($status==='') jerr('status');
    $note=trim((string)($_POST['note']??''));
    try{
      $pdo->beginTransaction();
      $sel=$pdo->prepare('SELECT id FROM hr_attendance WHERE employee_id=:e AND date=:d'); $sel->execute([':e'=>$eid,':d'=>$date]); $aid=$sel->fetchColumn();
      if($aid){ $pdo->prepare('UPDATE hr_attendance SET status=:s, note=:n WHERE id=:id')->execute([':s'=>$status,':n'=>$note,':id'=>$aid]); }
      else { $pdo->prepare('INSERT INTO hr_attendance(employee_id, date, status, note) VALUES(:e,:d,:s,:n)')->execute([':e'=>$eid,':d'=>$date,':s'=>$status,':n'=>$note]); }
      $pdo->commit();
      audit_log($pdo, $eid, 'update','attendance', json_encode(['date'=>$date,'status'=>$status]));
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  // ===== Fase 1: PII e Salários (esqueleto com máscara) =====
  case 'employee_pii_get': {
    if (!can_manage()) jerr('forbidden',403);
    $eid = (int)($_GET['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    if (!crypto_ready() || !pin_fresh()){
      echo json_encode(['ok'=>true,'masked'=>true,'item'=>['doc_id'=>mask_text('XXXXXXXXXXX'),'address'=>mask_text('endereco'),'phone'=>mask_text('telefone'),'iban'=>mask_text('IBAN')]]); break;
    }
    try{ $st=$pdo->prepare('SELECT pii_encrypted FROM hr_employees_pii WHERE employee_id=:e'); $st->execute([':e'=>$eid]); $row=$st->fetch(); }catch(Throwable $e){ jerr('db',500);}    
    if(!$row || empty($row['pii_encrypted'])){ echo json_encode(['ok'=>true,'masked'=>false,'item'=>['doc_id'=>'','address'=>'','phone'=>'','iban'=>'']]); break; }
    try{ $obj = json_decode(dec_secure($row['pii_encrypted']), true) ?: []; }catch(Throwable $e){ $obj=[]; }
    audit_log($pdo, $eid, 'view', 'pii');
    echo json_encode(['ok'=>true,'masked'=>false,'item'=>['doc_id'=>$obj['doc_id']??'','address'=>$obj['address']??'','phone'=>$obj['phone']??'','iban'=>$obj['iban']??'']]);
    break;
  }
  case 'employee_pii_update': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $eid = (int)($_POST['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    if (!crypto_ready() || !pin_fresh()){ echo json_encode(['ok'=>false,'error'=>'crypto_disabled_or_pin']); break; }
    $obj = [
      'doc_id'=>trim((string)($_POST['doc_id'] ?? '')),
      'address'=>trim((string)($_POST['address'] ?? '')),
      'phone'=>trim((string)($_POST['phone'] ?? '')),
      'iban'=>trim((string)($_POST['iban'] ?? '')),
    ];
    $enc = enc_secure(json_encode($obj, JSON_UNESCAPED_UNICODE));
    try{
      $pdo->beginTransaction();
      $st=$pdo->prepare('SELECT id FROM hr_employees_pii WHERE employee_id=:e'); $st->execute([':e'=>$eid]); $id=$st->fetchColumn();
      if($id){ $up=$pdo->prepare('UPDATE hr_employees_pii SET pii_encrypted=:p, updated_at='.$nowExpr.', updated_by=:u WHERE employee_id=:e'); $up->execute([':p'=>$enc, ':u'=>$userId, ':e'=>$eid]); }
      else { $in=$pdo->prepare('INSERT INTO hr_employees_pii(employee_id, pii_encrypted, updated_at, updated_by) VALUES(:e,:p,'.$nowExpr.',:u)'); $in->execute([':e'=>$eid, ':p'=>$enc, ':u'=>$userId]); }
      $pdo->commit();
      audit_log($pdo, $eid, 'update', 'pii');
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'employee_salary_list': {
    if (!can_manage()) jerr('forbidden',403);
    $eid = (int)($_GET['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    try{ $st=$pdo->prepare('SELECT id, amount_encrypted, currency, valid_from, valid_to, created_at FROM hr_salaries WHERE employee_id=:e ORDER BY COALESCE(valid_from, created_at) DESC, id DESC'); $st->execute([':e'=>$eid]); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    $out=[]; $canShow = crypto_ready() && pin_fresh();
    foreach($rows as $r){
      $masked = '•••'; $amount='';
      if($canShow && !empty($r['amount_encrypted'])){
        try{ $amount = dec_secure($r['amount_encrypted']); }catch(Throwable $e){ $amount=''; }
      }
      $out[] = [
        'id'=>$r['id'],
        'valid_from'=>$r['valid_from'], 'valid_to'=>$r['valid_to'],
        'currency'=>$r['currency'],
        'amount'=>$amount!==''? $amount : null,
        'amount_masked'=>($amount!==''? $amount : ($r['currency']? ($r['currency'].' '):'').'•••')
      ];
    }
    if($canShow) audit_log($pdo, $eid, 'view', 'salary');
    echo json_encode(['ok'=>true,'items'=>$out]);
    break;
  }
  case 'employee_salary_create': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $eid = (int)($_POST['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    if (!crypto_ready() || !pin_fresh()){ echo json_encode(['ok'=>false,'error'=>'crypto_disabled_or_pin']); break; }
    $amount = trim((string)($_POST['amount'] ?? '')); if($amount==='') jerr('amount');
    $currency = trim((string)($_POST['currency'] ?? 'AOA')); $allowed=['AOA','BRL','USD','EUR']; if(!in_array($currency,$allowed,true)) jerr('currency');
    $valid_from = trim((string)($_POST['valid_from'] ?? '')) ?: null;
    $enc = enc_secure($amount);
    try{ $st=$pdo->prepare('INSERT INTO hr_salaries(employee_id, amount_encrypted, currency, valid_from, created_by, created_at) VALUES(:e,:a,:c,:vf,:u,'.$nowExpr.')'); $st->execute([':e'=>$eid, ':a'=>$enc, ':c'=>$currency, ':vf'=>$valid_from, ':u'=>$userId]); audit_log($pdo, $eid, 'create','salary', json_encode(['currency'=>$currency])); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'employee_salary_update': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    if (!crypto_ready() || !pin_fresh()){ echo json_encode(['ok'=>false,'error'=>'crypto_disabled_or_pin']); break; }
    $fields=[]; $params=[':id'=>$id];
    if(isset($_POST['valid_to'])){ $fields[]='valid_to=:vt'; $params[':vt'] = (trim((string)$_POST['valid_to'])?:null); }
    if(isset($_POST['amount'])){ $enc = enc_secure(trim((string)$_POST['amount'])); $fields[]='amount_encrypted=:a'; $params[':a']=$enc; }
    if(isset($_POST['currency'])){ $currency=trim((string)$_POST['currency']); $allowed=['AOA','BRL','USD','EUR']; if(!in_array($currency,$allowed,true)) jerr('currency'); $fields[]='currency=:c'; $params[':c']=$currency; }
    if(!$fields){ echo json_encode(['ok'=>true]); break; }
    try{ $st=$pdo->prepare('UPDATE hr_salaries SET '.implode(',', $fields).' WHERE id=:id'); $st->execute($params); }catch(Throwable $e){ jerr('db',500);}    
    // Notificar solicitante/colaborador sobre mudança de salário
    try{
      $st=$pdo->prepare('SELECT employee_id FROM hr_salaries WHERE id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch();
      $to1 = (int)($r['employee_id']??0);
      $title='Salário atualizado';
      $body='Salário atualizado';
      if($to1>0) notify_user($pdo, $to1, 'hr_salary_update', $title, $body, '/portal/index.php');
    }catch(Throwable $e){ /* ignore */ }
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'policy_delete': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    // apagar arquivo
    try{ $st=$pdo->prepare('SELECT file_path FROM hr_policies WHERE id=:id'); $st->execute([':id'=>$id]); $fp=$st->fetchColumn(); }catch(Throwable $e){ jerr('db',500);}    
    if ($fp){ $root = realpath(__DIR__.'/../../'); $full = realpath($root.$fp); if ($full && strpos($full, $root.'/uploads/hr_policies/')===0 && is_file($full)) { @unlink($full); } }
    try{ $pdo->prepare('DELETE FROM hr_policies WHERE id=:id')->execute([':id'=>$id]); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'employee_doc_create': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $eid = (int)($_POST['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    $title = trim((string)($_POST['title'] ?? 'Documento'));
    $type = trim((string)($_POST['type'] ?? 'document'));
    try{ $st=$pdo->prepare("INSERT INTO hr_documents(employee_id,type,title,file_path,created_at) VALUES(:e,:t,:ti,:p,$nowExpr)"); $st->execute([':e'=>$eid, ':t'=>$type, ':ti'=>$title, ':p'=>'']); $id=(int)$pdo->lastInsertId(); }
    catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;
  }
  case 'employee_doc_rename': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    $title = trim((string)($_POST['title'] ?? ''));
    try{ $st=$pdo->prepare('UPDATE hr_documents SET title=:t WHERE id=:id'); $st->execute([':t'=>$title, ':id'=>$id]); }
    catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'employee_doc_delete': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    try{
      $st=$pdo->prepare('SELECT file_path FROM hr_documents WHERE id=:id'); $st->execute([':id'=>$id]); $fp=$st->fetchColumn();
      if ($fp) {
        // only delete if within /uploads/hr_docs
        $root = realpath(__DIR__.'/../../'); // /var/www/html/intranet
        $full = realpath($root.$fp);
        if ($full && strpos($full, $root.'/uploads/hr_docs/') === 0 && is_file($full)) { @unlink($full); }
      }
      $pdo->prepare('DELETE FROM hr_documents WHERE id=:id')->execute([':id'=>$id]);
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'employee_create': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $dept_id = (int)($_POST['dept_id'] ?? 0); $dept_id = $dept_id>0? $dept_id:null;
    $role_id = (int)($_POST['role_id'] ?? 0); $role_id = $role_id>0? $role_id:null;
    $status = trim((string)($_POST['status'] ?? 'active'));
    try{
      if (has_col($pdo,'hr_employees','phone')){
        $st=$pdo->prepare("INSERT INTO hr_employees(name,email,phone,dept_id,role_id,status,created_at) VALUES(:n,:e,:ph,:d,:r,:s, $nowExpr)");
        $st->execute([':n'=>$name, ':e'=>$email, ':ph'=>$phone, ':d'=>$dept_id, ':r'=>$role_id, ':s'=>$status]);
      } else {
        $st=$pdo->prepare("INSERT INTO hr_employees(name,email,dept_id,role_id,status,created_at) VALUES(:n,:e,:d,:r,:s, $nowExpr)");
        $st->execute([':n'=>$name, ':e'=>$email, ':d'=>$dept_id, ':r'=>$role_id, ':s'=>$status]);
      }
      $id = (int)$pdo->lastInsertId();
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;
  }
  case 'employee_get': {
    $id = (int)($_GET['id'] ?? 0); if($id<=0) jerr('id');
    try{
      $st=$pdo->prepare('SELECT e.*, d.name AS dept_name, r.name AS role_name FROM hr_employees e LEFT JOIN hr_departments d ON d.id=e.dept_id LEFT JOIN hr_roles_catalog r ON r.id=e.role_id WHERE e.id=:id');
      $st->execute([':id'=>$id]);
      $e=$st->fetch(); if(!$e) jerr('not_found',404);
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'employee'=>$e]);
    break;
  }
  case 'employee_update_basic': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $dept_id = (int)($_POST['dept_id'] ?? 0); $dept_id = $dept_id>0? $dept_id:null;
    $role_id = (int)($_POST['role_id'] ?? 0); $role_id = $role_id>0? $role_id:null;
    $manager_user_id = (int)($_POST['manager_user_id'] ?? 0); $manager_user_id = $manager_user_id>0? $manager_user_id:null;
    $status = trim((string)($_POST['status'] ?? 'active'));
    try{
      if (has_col($pdo,'hr_employees','phone')){
        $st=$pdo->prepare('UPDATE hr_employees SET name=:n, email=:e, phone=:ph, dept_id=:d, role_id=:r, manager_user_id=:m, status=:s WHERE id=:id');
        $st->execute([':n'=>$name, ':e'=>$email, ':ph'=>$phone, ':d'=>$dept_id, ':r'=>$role_id, ':m'=>$manager_user_id, ':s'=>$status, ':id'=>$id]);
      } else {
        $st=$pdo->prepare('UPDATE hr_employees SET name=:n, email=:e, dept_id=:d, role_id=:r, manager_user_id=:m, status=:s WHERE id=:id');
        $st->execute([':n'=>$name, ':e'=>$email, ':d'=>$dept_id, ':r'=>$role_id, ':m'=>$manager_user_id, ':s'=>$status, ':id'=>$id]);
      }
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'departments_list': {
    try{ $rows=$pdo->query('SELECT id,name,parent_id FROM hr_departments ORDER BY name ASC')->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows]);
    break;
  }
  case 'roles_list': {
    try{ $rows=$pdo->query('SELECT id,name FROM hr_roles_catalog ORDER BY name ASC')->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows]);
    break;
  }
  case 'employee_docs_list': {
    $eid = (int)($_GET['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    try{ $st=$pdo->prepare('SELECT id,type,title,file_path,created_at,accepted_at FROM hr_documents WHERE employee_id=:e ORDER BY created_at DESC'); $st->execute([':e'=>$eid]); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows]);
    break;
  }
  case 'employee_doc_upload': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    // For multipart, CSRF comes in POST body as usual
    require_csrf();
    $eid = (int)($_POST['employee_id'] ?? 0); if($eid<=0) jerr('employee_id');
    $title = trim((string)($_POST['title'] ?? 'Documento'));
    $type = trim((string)($_POST['type'] ?? 'document'));
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) jerr('file');
    $f = $_FILES['file'];
    $ext = pathinfo($f['name'] ?? '', PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/','', $ext);
    // salvar sob raiz pública /uploads/hr_docs (raiz do site: /var/www/html/intranet)
    $dir = __DIR__ . '/../../uploads/hr_docs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'emp'.$eid.'_'.date('Ymd_His').'_' . bin2hex(random_bytes(4)) . ($safeExt?('.'.$safeExt):'');
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) jerr('upload_failed',500);
    // URL pública absoluta
    $rel = '/uploads/hr_docs/' . $name;
    try{ $st=$pdo->prepare("INSERT INTO hr_documents(employee_id,type,title,file_path,created_at) VALUES(:e,:t,:ti,:p, $nowExpr)"); $st->execute([':e'=>$eid, ':t'=>$type, ':ti'=>$title, ':p'=>$rel]); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true, 'file'=>$rel]);
    break;
  }
  case 'requests_list': {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
    $off = ($page-1)*$per;
    $q = trim((string)($_GET['q'] ?? ''));
    $type = trim((string)($_GET['type'] ?? '')); $status = trim((string)($_GET['status'] ?? ''));
    $where='WHERE 1=1'; $params=[];
    if($q!==''){ $where .= " AND (e.name LIKE :q OR e.email LIKE :q)"; $params[':q'] = '%'.$q.'%'; }
    if($type!==''){ $where .= " AND r.type = :type"; $params[':type'] = $type; }
    if($status!==''){ $where .= " AND r.status = :status"; $params[':status'] = $status; }
    $sel = [
      'r.id', 'r.employee_id', 'e.name AS employee_name', 'r.type', 'r.status', 'r.created_at'
    ];
    $sel[] = has_col($pdo,'hr_requests','requester_user_id') ? 'r.requester_user_id' : 'NULL AS requester_user_id';
    $sel[] = has_col($pdo,'users','id') ? 'u.name AS requester_user_name' : "'' AS requester_user_name";
    $sel[] = has_col($pdo,'hr_requests','subject') ? 'r.subject' : "'' AS subject";
    $sel[] = has_col($pdo,'hr_requests','start_date') ? 'r.start_date' : 'NULL AS start_date';
    $sel[] = has_col($pdo,'hr_requests','end_date') ? 'r.end_date' : 'NULL AS end_date';
    $sel[] = has_col($pdo,'hr_requests','amount') ? 'r.amount' : 'NULL AS amount';
    $sel[] = has_col($pdo,'hr_requests','details') ? 'r.details' : 'NULL AS details';
    $sql = "SELECT ".implode(', ', $sel)." FROM hr_requests r LEFT JOIN hr_employees e ON e.id=r.employee_id LEFT JOIN users u ON u.id = r.requester_user_id $where ORDER BY r.created_at DESC LIMIT :per OFFSET :off";
    try { $st=$pdo->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k,$v); } $st->bindValue(':per',$per,PDO::PARAM_INT); $st->bindValue(':off',$off,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(); } catch(Throwable $e){ jerr('db',500); }
    echo json_encode(['ok'=>true,'items'=>$rows,'page'=>$page,'per_page'=>$per]);
    break;
  }
  case 'request_get': {
    $id = (int)($_GET['id'] ?? 0); if($id<=0) jerr('id');
    try{ 
      $st=$pdo->prepare('SELECT r.*, e.name AS employee_name, u.name AS requester_user_name FROM hr_requests r LEFT JOIN hr_employees e ON e.id=r.employee_id LEFT JOIN users u ON u.id=r.requester_user_id WHERE r.id=:id'); 
      $st->execute([':id'=>$id]); 
      $row=$st->fetch(); if(!$row) jerr('not_found',404); 
    }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'item'=>$row]);
    break;
  }
  case 'request_create': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $employee_id = (int)($_POST['employee_id'] ?? 0); if($employee_id<=0) jerr('employee_id');
    $type = trim((string)($_POST['type'] ?? '')); $allowed=['vacation','medical','reimbursement','other']; if(!in_array($type,$allowed,true)) jerr('type');
    $cols = ['employee_id','type'];
    $vals = [':e',':t'];
    $params = [':e'=>$employee_id, ':t'=>$type];
    if (has_col($pdo,'hr_requests','details')){ $cols[]='details'; $vals[]=':d'; $params[':d'] = trim((string)($_POST['details'] ?? '')); }
    if (has_col($pdo,'hr_requests','start_date')){ $cols[]='start_date'; $vals[]=':sd'; $params[':sd'] = trim((string)($_POST['start_date'] ?? '')) ?: null; }
    if (has_col($pdo,'hr_requests','end_date')){ $cols[]='end_date'; $vals[]=':ed'; $params[':ed'] = trim((string)($_POST['end_date'] ?? '')) ?: null; }
    if (has_col($pdo,'hr_requests','amount')){ $cols[]='amount'; $vals[]=':am'; $params[':am'] = (isset($_POST['amount']) && $_POST['amount']!=='') ? (float)$_POST['amount'] : null; }
    if (has_col($pdo,'hr_requests','status')){ $cols[]='status'; $vals[]=':s'; $params[':s'] = 'open'; }
    if (has_col($pdo,'hr_requests','created_at')){ $cols[]='created_at'; $vals[]=$nowExpr; }
    $sql = 'INSERT INTO hr_requests(' . implode(',', $cols) . ') VALUES(' . implode(',', $vals) . ')';
    try{ $st=$pdo->prepare($sql); $st->execute($params); $id=(int)$pdo->lastInsertId(); }
    catch(Throwable $e){ jerr('db',500);}    
    // Notificar solicitante/colaborador sobre nova solicitação
    try{
      $st=$pdo->prepare('SELECT employee_id FROM hr_requests WHERE id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch();
      $to1 = (int)($r['employee_id']??0);
      $title='Nova solicitação RH #'.$id;
      $body='Nova solicitação RH';
      if($to1>0) notify_user($pdo, $to1, 'hr_request_new', $title, $body, '/portal/requests.php');
    }catch(Throwable $e){ /* ignore */ }
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;
  }
  case 'request_update': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    $fields = [];$params=[':id'=>$id];
    if (has_col($pdo,'hr_requests','type') && array_key_exists('type', $_POST)){
      $t = trim((string)$_POST['type']); if($t!==''){ $fields[]='type=:type'; $params[':type']=$t; }
    }
    if (has_col($pdo,'hr_requests','subject') && array_key_exists('subject', $_POST)){
      $fields[]='subject=:subject'; $params[':subject']=trim((string)$_POST['subject']);
    }
    if (has_col($pdo,'hr_requests','status') && array_key_exists('status', $_POST)){
      $st = trim((string)$_POST['status']); $fields[]='status=:status'; $params[':status']=$st;
    }
    if (has_col($pdo,'hr_requests','details') && array_key_exists('details', $_POST)){ $fields[] = 'details=:details'; $params[':details'] = trim((string)$_POST['details']); }
    if (has_col($pdo,'hr_requests','start_date') && array_key_exists('start_date', $_POST)){ $fields[]='start_date=:sd'; $params[':sd'] = trim((string)$_POST['start_date']) ?: null; }
    if (has_col($pdo,'hr_requests','end_date') && array_key_exists('end_date', $_POST)){ $fields[]='end_date=:ed'; $params[':ed'] = trim((string)$_POST['end_date']) ?: null; }
    if (has_col($pdo,'hr_requests','amount') && array_key_exists('amount', $_POST)){ $fields[]='amount=:am'; $params[':am'] = (isset($_POST['amount']) && $_POST['amount']!=='') ? (float)$_POST['amount'] : null; }
    if(!$fields){ echo json_encode(['ok'=>true]); break; }
    try{ $st=$pdo->prepare('UPDATE hr_requests SET '.implode(',', $fields).' WHERE id=:id'); $st->execute($params); }catch(Throwable $e){ jerr('db',500);}    
    // Notificar solicitante/colaborador sobre mudança de status/assunto/detalhes
    try{
      $st=$pdo->prepare('SELECT employee_id, requester_user_id, status, subject FROM hr_requests WHERE id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch();
      $to1 = (int)($r['requester_user_id']??0);
      $to2 = employee_user_id($pdo, (int)($r['employee_id']??0));
      $title='Solicitação RH #'.$id.' atualizada';
      $body='Status: '.($r['status']??'') . (($r['subject']??'')? (' • '.$r['subject']):'');
      if($to1>0) notify_user($pdo, $to1, 'hr_request_update', $title, $body, '/portal/requests.php');
      if($to2>0) notify_user($pdo, $to2, 'hr_request_update', $title, $body, '/portal/requests.php');
    }catch(Throwable $e){ /* ignore */ }
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'request_comments_list': {
    if (!can_operate()) jerr('forbidden',403);
    $id = (int)($_GET['request_id'] ?? 0); if($id<=0) jerr('request_id');
    try{ $st=$pdo->prepare('SELECT c.id, c.user_id, u.name AS user_name, c.body, c.created_at FROM hr_request_comments c LEFT JOIN users u ON u.id=c.user_id WHERE c.request_id=:r ORDER BY c.created_at ASC, c.id ASC'); $st->execute([':r'=>$id]); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true,'items'=>$rows]);
    break;
  }
  case 'request_comment_add': {
    if (!can_operate()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method'); require_csrf();
    $id = (int)($_POST['request_id'] ?? 0); if($id<=0) jerr('request_id');
    $body = trim((string)($_POST['body'] ?? '')); if($body==='') jerr('body');
    try{ $st=$pdo->prepare('INSERT INTO hr_request_comments(request_id, user_id, body, created_at) VALUES(:r,:u,:b,'.$nowExpr.')'); $st->execute([':r'=>$id, ':u'=>$userId, ':b'=>$body]); }catch(Throwable $e){ jerr('db',500);}    
    // Notificar solicitante/colaborador de nova resposta do RH
    try{
      $st=$pdo->prepare('SELECT employee_id, requester_user_id FROM hr_requests WHERE id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch();
      $to1 = (int)($r['requester_user_id']??0);
      $to2 = employee_user_id($pdo, (int)($r['employee_id']??0));
      $msg = mb_substr($body,0,140);
      if($to1>0) notify_user($pdo, $to1, 'hr_request_comment', 'Resposta da RH na sua solicitação', $msg, '/portal/requests.php');
      if($to2>0) notify_user($pdo, $to2, 'hr_request_comment', 'Resposta da RH na sua solicitação', $msg, '/portal/requests.php');
    }catch(Throwable $e){ /* ignore */ }
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'request_delete': {
    if (!can_manage()) jerr('forbidden',403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $id = (int)($_POST['id'] ?? 0); if($id<=0) jerr('id');
    try{ $st=$pdo->prepare('DELETE FROM hr_requests WHERE id=:id'); $st->execute([':id'=>$id]); }catch(Throwable $e){ jerr('db',500);}    
    echo json_encode(['ok'=>true]);
    break;
  }
  case 'policies_list': {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
    $off = ($page-1)*$per;
    // Compatível com SQLite/MySQL: ordenar nulos por último manualmente
    $sql = "SELECT id, title, version, file_path, published_at FROM hr_policies ORDER BY (published_at IS NULL), published_at DESC LIMIT :per OFFSET :off";
    try { $st=$pdo->prepare($sql); $st->bindValue(':per',$per,PDO::PARAM_INT); $st->bindValue(':off',$off,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(); } catch(Throwable $e){ jerr('db',500); }
    echo json_encode(['ok'=>true,'items'=>$rows,'page'=>$page,'per_page'=>$per]);
    break;
  }
  case 'policy_accept': {
    if (!can_operate()) jerr('forbidden', 403);
    if ($method!=='POST') jerr('method');
    require_csrf();
    $pid = (int)($_POST['policy_id'] ?? 0); if($pid<=0) jerr('policy_id');
    try { $st=$pdo->prepare("INSERT INTO hr_policy_accepts(policy_id,user_id,accepted_at,ip) VALUES(:p,:u,$nowExpr,:ip)"); $st->execute([':p'=>$pid, ':u'=>$userId, ':ip'=>($_SERVER['REMOTE_ADDR'] ?? '')]); }
    catch(Throwable $e){ jerr('db',500); }
    echo json_encode(['ok'=>true]);
    break;
  }
  default:
    jerr('unknown_action', 400);
}
