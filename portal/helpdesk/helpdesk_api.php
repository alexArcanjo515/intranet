<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/crypto.php';
$pdo = require __DIR__ . '/../../config/db.php';
// Composer autoload (phpseclib, etc.)
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) { require_once $autoload; }
require_once __DIR__ . '/../../includes/settings.php';

$uid = (int)($_SESSION['portal_user']['id'] ?? 0);
if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['error'=>'auth']); exit(); }
$uid = (int)$_SESSION['portal_user']['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jerr($m,$c=400){ http_response_code($c); echo json_encode(['error'=>$m]); exit(); }
function notif($pdo,$userId,$title,$body,$link){ try{$s=$pdo->prepare('INSERT INTO notifications (user_id,type,title,body,link) VALUES (:u, :t, :ti, :b, :l)');$s->execute([':u'=>$userId,':t'=>'helpdesk',':ti'=>$title,':b'=>$body,':l'=>$link]);}catch(Throwable $e){} }

try {
  // CSRF para métodos POST
  if ($method === 'POST') {
    if (!csrf_check_request()) { jerr('csrf', 403); }
  }

  switch ($action) {
    case 'tickets_list': {
      $page = max(1, (int)($_GET['page'] ?? 1));
      $per = (int)($_GET['per_page'] ?? 20); if ($per<=0 || $per>100) $per = 20;
      $off = ($page-1)*$per;
      $sql = "SELECT t.id, t.subject, t.category, t.priority, t.status, t.created_at, COALESCE(u.name,u.username) as requester
              FROM helpdesk_tickets t JOIN users u ON u.id=t.requester_id
              ORDER BY CASE t.status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'resolved' THEN 3 WHEN 'closed' THEN 4 ELSE 5 END,
                       t.created_at DESC
              LIMIT :per OFFSET :off";
      try { $stmt = $pdo->prepare($sql); $stmt->bindValue(':per',$per,PDO::PARAM_INT); $stmt->bindValue(':off',$off,PDO::PARAM_INT); $stmt->execute(); $rows = $stmt->fetchAll(); } catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>'db_list']); exit(); }
      echo json_encode(['items'=>$rows,'page'=>$page,'per_page'=>$per]);
      break;
    }

    case 'ssh_console_exec': {
      // RBAC: somente admin
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      if (!in_array('admin', (array)$roles, true)) { jerr('forbidden', 403); }
      if ($method!=='POST') jerr('method');
      $sid = (int)($_POST['server_id'] ?? 0); if ($sid<=0) jerr('server_id');
      $cmdIn = (string)($_POST['cmd'] ?? ''); if ($cmdIn==='') jerr('cmd');
      try { $s = $pdo->prepare('SELECT * FROM servers WHERE id=:id'); $s->execute([':id'=>$sid]); $sv = $s->fetch(); } catch (Throwable $e) { jerr('db_server',500); }
      if (!$sv) jerr('not_found',404);
      $host = $sv['host'] ?: $sv['ip']; $port = (int)($sv['port'] ?: 22); $user = (string)($sv['username'] ?: '');
      $pw = (string)($sv['password'] ?: ''); if ($pw !== '') { $pw = decrypt_secret($pw) ?: $pw; }
      $pass = (string)($_POST['ssh_pass'] ?? ''); if ($pass!=='') $pw = $pass;
      if (!$host || !$user || !$pw) jerr('missing_credentials',400);
      $output=''; $error=''; $exitCode=null;
      try{
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($user, $pw)) { throw new Exception('auth_failed'); }
        $osOut = trim((string)$ssh->exec('uname -s 2>/dev/null || echo WINDOWS'));
        $isWindows = (stripos($osOut, 'windows') !== false) || (stripos($osOut, 'msys') !== false) || (stripos($osOut, 'mingw') !== false);
        if ($isWindows) {
          $cmd = 'powershell -NoProfile -Command ' . escapeshellarg($cmdIn);
        } else {
          $cmd = 'sh -lc ' . escapeshellarg($cmdIn);
        }
        $output = (string)$ssh->exec($cmd);
      }catch(Throwable $e){ $error=$e->getMessage(); }
      echo json_encode(['ok'=>$error==='', 'error'=>$error, 'output'=>$output]);
      break;
    }

    case 'system_cleanup': {
      // RBAC: helpdesk_operate para executar; algumas ações profundas exigem admin
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      if (!in_array('admin', (array)$roles, true) && !in_array('helpdesk_operate', (array)$roles, true)) { jerr('forbidden', 403); }
      if ($method!=='POST') jerr('method');
      $sid = (int)($_POST['server_id'] ?? 0); if ($sid<=0) jerr('server_id');
      $opts = array_filter(array_map('trim', explode(',', (string)($_POST['options'] ?? ''))));
      if (!$opts) jerr('options');
      try { $s = $pdo->prepare('SELECT * FROM servers WHERE id=:id'); $s->execute([':id'=>$sid]); $sv = $s->fetch(); } catch (Throwable $e) { jerr('db_server',500); }
      if (!$sv) jerr('not_found',404);
      $host = $sv['host'] ?: $sv['ip']; $port = (int)($sv['port'] ?: 22); $user = (string)($sv['username'] ?: '');
      $pw = (string)($sv['password'] ?: ''); if ($pw !== '') { $pw = decrypt_secret($pw) ?: $pw; }
      $pass = (string)($_POST['ssh_pass'] ?? ''); if ($pass!=='') $pw = $pass;
      if (!$host || !$user || !$pw) jerr('missing_credentials',400);
      $out = [];$error='';
      try{
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($user, $pw)) { throw new Exception('auth_failed'); }
        $osOut = trim((string)$ssh->exec('uname -s 2>/dev/null || echo WINDOWS'));
        $isWindows = (stripos($osOut, 'windows') !== false) || (stripos($osOut, 'msys') !== false) || (stripos($osOut, 'mingw') !== false);
        // admin-only deep clean
        $isAdmin = in_array('admin', (array)$roles, true);
        foreach ($opts as $op){
          switch($op){
            case 'clean_temp':
              $cmd = $isWindows ? 'powershell -NoProfile -Command "Remove-Item -Path $env:TEMP\* -Recurse -Force -ErrorAction SilentlyContinue"'
                                : 'sh -lc "rm -rf /tmp/* 2>/dev/null || true"';
              break;
            case 'clean_package_cache':
              $cmd = $isWindows ? 'powershell -NoProfile -Command "Start-Process cleanmgr -ArgumentList \"/sagerun:1\" -Verb runAs"'
                                : 'sh -lc "(which apt-get >/dev/null 2>&1 && sudo -n apt-get clean) || (which dnf >/dev/null 2>&1 && sudo -n dnf clean all) || (which yum >/dev/null 2>&1 && sudo -n yum clean all) || true"';
              break;
            case 'clean_logs':
              if (!$isAdmin) { $out['clean_logs'] = 'forbidden (admin required)'; continue 2; }
              $cmd = $isWindows ? 'powershell -NoProfile -Command "wevtutil el | ForEach-Object { wevtutil cl $_ }"'
                                : 'sh -lc "sudo -n journalctl --vacuum-time=7d || true"';
              break;
            case 'clean_thumbnails':
              $cmd = $isWindows ? 'powershell -NoProfile -Command "ie4uinit.exe -ClearIconCache; del /q /f /s %LocalAppData%\Microsoft\Windows\Explorer\thumbcache_* 2>$null"'
                                : 'sh -lc "rm -rf ~/.cache/thumbnails/* 2>/dev/null || true"';
              break;
            default:
              $out[$op] = 'unknown_option'; continue 2;
          }
          $o = $ssh->exec($cmd);
          $out[$op] = (string)$o;
        }
      }catch(Throwable $e){ $error=$e->getMessage(); }
      echo json_encode(['ok'=>$error==='', 'error'=>$error, 'outputs'=>$out]);
      break;
    }

    case 'network_diag': {
      // RBAC: helpdesk_operate
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      if (!in_array('admin', (array)$roles, true) && !in_array('helpdesk_operate', (array)$roles, true)) { jerr('forbidden', 403); }
      if ($method!=='POST') jerr('method');
      $sid = (int)($_POST['server_id'] ?? 0); if ($sid<=0) jerr('server_id');
      $tests = array_filter(array_map('trim', explode(',', (string)($_POST['tests'] ?? ''))));
      if (!$tests) jerr('tests');
      try { $s = $pdo->prepare('SELECT * FROM servers WHERE id=:id'); $s->execute([':id'=>$sid]); $sv = $s->fetch(); } catch (Throwable $e) { jerr('db_server',500); }
      if (!$sv) jerr('not_found',404);
      $host = $sv['host'] ?: $sv['ip']; $port = (int)($sv['port'] ?: 22); $user = (string)($sv['username'] ?: '');
      $pw = (string)($sv['password'] ?: ''); if ($pw !== '') { $pw = decrypt_secret($pw) ?: $pw; }
      $pass = (string)($_POST['ssh_pass'] ?? ''); if ($pass!=='') $pw = $pass;
      if (!$host || !$user || !$pw) jerr('missing_credentials',400);
      $out = [];$error='';
      try{
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($user, $pw)) { throw new Exception('auth_failed'); }
        $osOut = trim((string)$ssh->exec('uname -s 2>/dev/null || echo WINDOWS'));
        $isWindows = (stripos($osOut, 'windows') !== false) || (stripos($osOut, 'msys') !== false) || (stripos($osOut, 'mingw') !== false);
        $target = trim((string)($_POST['target'] ?? '8.8.8.8'));
        foreach ($tests as $t){
          switch($t){
            case 'dns':
              $cmd = $isWindows ? 'powershell -NoProfile -Command "Resolve-DnsName '.$target.' | Format-Table -AutoSize | Out-String"'
                                : 'sh -lc "(which dig >/dev/null 2>&1 && dig +short '.$target.') || getent hosts '.$target.' || nslookup '.$target.' 2>&1"';
              break;
            case 'traceroute':
              $cmd = $isWindows ? 'powershell -NoProfile -Command "tracert -d -h 20 '.$target.'"'
                                : 'sh -lc "(which mtr >/dev/null 2>&1 && mtr -rwc 10 '.$target.') || traceroute -n '.$target.' 2>&1"';
              break;
            case 'ping_jitter':
              $cmd = $isWindows ? 'powershell -NoProfile -Command "Test-NetConnection -ComputerName '.$target.' -InformationLevel Detailed | Out-String"'
                                : 'sh -lc "ping -c 10 '.$target.'; echo; echo [jitter]; ping -i 0.2 -c 20 '.$target.' 2>/dev/null || true"';
              break;
            default:
              $out[$t] = 'unknown_test'; continue 2;
          }
          $o = $ssh->exec($cmd);
          $out[$t] = (string)$o;
        }
      }catch(Throwable $e){ $error=$e->getMessage(); }
      echo json_encode(['ok'=>$error==='', 'error'=>$error, 'results'=>$out]);
      break;
    }

    case 'quick_playbook': {
      // RBAC: helpdesk_operate; algumas ações exigem admin
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      $isAdmin = in_array('admin', (array)$roles, true);
      if (!$isAdmin && !in_array('helpdesk_operate', (array)$roles, true)) { jerr('forbidden', 403); }
      if ($method!=='POST') jerr('method');
      $sid = (int)($_POST['server_id'] ?? 0); if ($sid<=0) jerr('server_id');
      $name = trim((string)($_POST['name'] ?? '')); if ($name==='') jerr('name');
      try { $s = $pdo->prepare('SELECT * FROM servers WHERE id=:id'); $s->execute([':id'=>$sid]); $sv = $s->fetch(); } catch (Throwable $e) { jerr('db_server',500); }
      if (!$sv) jerr('not_found',404);
      $host = $sv['host'] ?: $sv['ip']; $port = (int)($sv['port'] ?: 22); $user = (string)($sv['username'] ?: '');
      $pw = (string)($sv['password'] ?: ''); if ($pw !== '') { $pw = decrypt_secret($pw) ?: $pw; }
      $pass = (string)($_POST['ssh_pass'] ?? ''); if ($pass!=='') $pw = $pass;
      if (!$host || !$user || !$pw) jerr('missing_credentials',400);
      $output='';$error='';
      try{
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($user, $pw)) { throw new Exception('auth_failed'); }
        $osOut = trim((string)$ssh->exec('uname -s 2>/dev/null || echo WINDOWS'));
        $isWindows = (stripos($osOut, 'windows') !== false) || (stripos($osOut, 'msys') !== false) || (stripos($osOut, 'mingw') !== false);
        switch($name){
          case 'reset_print_spooler':
            $cmd = $isWindows ? 'powershell -NoProfile -Command "Stop-Service -Name Spooler -Force; Remove-Item -Path C:\\Windows\\System32\\spool\\PRINTERS\\* -Recurse -Force -ErrorAction SilentlyContinue; Start-Service -Name Spooler"'
                              : 'sh -lc "(sudo -n systemctl stop cups || true); rm -rf /var/spool/cups/* 2>/dev/null || true; (sudo -n systemctl start cups || true)"';
            break;
          case 'flush_dns':
            $cmd = $isWindows ? 'powershell -NoProfile -Command "ipconfig /flushdns"'
                              : 'sh -lc "(which systemd-resolve >/dev/null 2>&1 && sudo -n systemd-resolve --flush-caches) || (which resolvectl >/dev/null 2>&1 && sudo -n resolvectl flush-caches) || (which nscd >/dev/null 2>&1 && sudo -n service nscd restart) || echo \"no resolver cache\""';
            break;
          case 'repair_network_stack':
            if (!$isAdmin) { $output='forbidden (admin required)'; break; }
            $cmd = $isWindows ? 'powershell -NoProfile -Command "netsh winsock reset; netsh int ip reset; ipconfig /release; ipconfig /renew"'
                              : 'sh -lc "(sudo -n systemctl restart NetworkManager || sudo -n systemctl restart networking || sudo -n systemctl restart network) || true"';
            break;
          case 'clear_browser_cache':
            $cmd = $isWindows ? 'powershell -NoProfile -Command "Remove-Item -Path $env:LOCALAPPDATA\\Google\\Chrome\\User Data\\Default\\Cache -Recurse -Force -ErrorAction SilentlyContinue; Remove-Item -Path $env:LOCALAPPDATA\\Microsoft\\Edge\\User Data\\Default\\Cache -Recurse -Force -ErrorAction SilentlyContinue; RunDll32.exe InetCpl.cpl,ClearMyTracksByProcess 255"'
                              : 'sh -lc "rm -rf ~/.cache/google-chrome/Default/Cache ~/.cache/chromium/Default/Cache ~/.cache/mozilla/firefox/*.default-release/cache2/* 2>/dev/null || true"';
            break;
          case 'reset_proxy':
            $cmd = $isWindows ? 'powershell -NoProfile -Command "netsh winhttp reset proxy"'
                              : 'sh -lc "gsettings set org.gnome.system.proxy mode \"none\" 2>/dev/null || echo \"set proxy manually if needed\""';
            break;
          case 'winsock_reset':
            if (!$isWindows) { $output='unsupported on linux'; $cmd=''; break; }
            if (!$isAdmin) { $output='forbidden (admin required)'; $cmd=''; break; }
            $cmd = 'powershell -NoProfile -Command "netsh winsock reset"';
            break;
          case 'clean_orphan_packages':
            $cmd = $isWindows ? ''
                              : 'sh -lc "(which apt-get >/dev/null 2>&1 && sudo -n apt-get -y autoremove && sudo -n apt-get -y autoclean) || (which dnf >/dev/null 2>&1 && sudo -n dnf -y autoremove) || (which yum >/dev/null 2>&1 && sudo -n yum -y autoremove) || echo \"no package manager\""';
            break;
          default:
            $output = 'unknown_playbook'; $cmd='';
        }
        if (!empty($cmd)) { $output = (string)$ssh->exec($cmd); }
      }catch(Throwable $e){ $error=$e->getMessage(); }
      echo json_encode(['ok'=>$error==='', 'error'=>$error, 'output'=>$output]);
      break;
    }
    case 'servers_status': {
      // ids=1,2,3
      $idsParam = $_GET['ids'] ?? '';
      $ids = array_values(array_filter(array_map('intval', explode(',', (string)$idsParam)), fn($v)=>$v>0));
      if (empty($ids)) { echo json_encode(['items'=>[]]); break; }
      // fetch subset
      $in = implode(',', array_fill(0, count($ids), '?'));
      $s = $pdo->prepare("SELECT id, host, ip, protocol, port FROM servers WHERE id IN ($in)");
      $s->execute($ids);
      $rows = $s->fetchAll();
      $out = [];
      foreach ($rows as $sv) {
        $host = $sv['host'] ?: $sv['ip'];
        $port = (int)$sv['port'];
        $proto = $sv['protocol'];
        $entry = ['id'=>(int)$sv['id'], 'up'=>false, 'latency_ms'=>null, 'http_code'=>null];
        if ($proto==='http' || $proto==='https'){
          $url = $proto.'://'.$host.(($proto==='http'&&$port!=80)||($proto==='https'&&$port!=443)?(':'.$port):'').'/';
          $ch = curl_init($url);
          curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_TIMEOUT=>5,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>2,CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERAGENT=>'Intranet/1.0',CURLOPT_HEADER=>true]);
          $start = microtime(true); $resp=curl_exec($ch); $lat=(microtime(true)-$start)*1000; if($resp!==false){ $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $entry['up']=($code>=200&&$code<500); $entry['http_code']=$code; $entry['latency_ms']=(int)$lat; } curl_close($ch);
        } else {
          $start = microtime(true); $fp=@fsockopen($host,$port,$errno,$errstr,3); $lat=(microtime(true)-$start)*1000; if($fp){ fclose($fp); $entry['up']=true; $entry['latency_ms']=(int)$lat; }
        }
        $out[]=$entry;
      }
      echo json_encode(['items'=>$out]);
      break;
    }

    case 'server_harden': {
      // RBAC simples: requer papel admin no portal ou bloqueia
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      if (!in_array('admin', (array)$roles, true)) { jerr('forbidden', 403); }
      if ($method!=='POST') jerr('method');
      $sid = (int)($_POST['server_id'] ?? 0); if ($sid<=0) jerr('server_id');
      $actions = $_POST['actions'] ?? ''; $actions = array_filter(array_map('trim', explode(',', (string)$actions)));
      if (!$actions) jerr('actions');
      $confirm = trim((string)($_POST['confirm'] ?? ''));
      $dryRun = (int)($_POST['dry_run'] ?? 0) === 1;
      if (!$dryRun && strtoupper($confirm) !== 'YES') jerr('confirm_required');

      try { $s = $pdo->prepare('SELECT * FROM servers WHERE id=:id'); $s->execute([':id'=>$sid]); $sv = $s->fetch(); } catch (Throwable $e) { jerr('db_server',500); }
      if (!$sv) jerr('not_found',404);
      $host = $sv['host'] ?: $sv['ip']; $port = (int)($sv['port'] ?: 22); $user = (string)($sv['username'] ?: '');
      $pw = (string)(($_POST['ssh_pass'] ?? '') ?: ($sv['password'] ?? ''));
      if ($pw !== '') { $pw = decrypt_secret($pw) ?: $pw; }
      if (!$host || !$user || !$pw) jerr('missing_credentials',400);

      $plan = [];
      $outputs = [];
      $error = '';
      try {
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($user, $pw)) { throw new Exception('auth_failed'); }

        // Detect OS
        $osOut = trim((string)$ssh->exec('uname -s 2>/dev/null || echo WINDOWS'));
        $isWindows = (stripos($osOut, 'windows') !== false) || (stripos($osOut, 'msys') !== false) || (stripos($osOut, 'mingw') !== false);

        // Define action map
        if ($isWindows) {
          $map = [
            'win_firewall_enable' => 'powershell -NoProfile -Command "Set-NetFirewallProfile -Profile Domain,Public,Private -Enabled True"',
            'win_rdp_disable' => 'powershell -NoProfile -Command "Set-ItemProperty -Path \"HKLM: SYSTEM\\CurrentControlSet\\Control\\Terminal Server\" -Name fDenyTSConnections -Value 1"',
            'win_updates_check' => 'powershell -NoProfile -Command "Write-Output \"[Windows Update]\"; (Get-HotFix | Sort-Object InstalledOn -Descending | Select -First 20 | Format-Table -AutoSize | Out-String)"'
          ];
        } else {
          // Linux: try without interactive sudo; if requires sudo, command may fail
          $map = [
            'linux_enable_ufw' => 'sh -lc "(which ufw >/dev/null 2>&1 && sudo -n ufw enable) || echo \"ufw not available or sudo required\""',
            'linux_ufw_basic' => 'sh -lc "(which ufw >/dev/null 2>&1 && sudo -n ufw allow OpenSSH && sudo -n ufw allow 80 && sudo -n ufw allow 443) || echo \"ufw not available or sudo required\""',
            'linux_disable_root_ssh' => 'sh -lc "sudo -n sed -i.bak -E \"s/^#?PermitRootLogin .*/PermitRootLogin no/\" /etc/ssh/sshd_config && (sudo -n systemctl reload ssh || sudo -n service ssh reload || true) || echo \"sudo required\""',
            'linux_harden_sshd' => 'sh -lc "sudo -n bash -c \"cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak.$(date +%s); grep -q ^PasswordAuthentication /etc/ssh/sshd_config && sed -i \"s/^#\?PasswordAuthentication .*/PasswordAuthentication no/\" /etc/ssh/sshd_config || echo PasswordAuthentication no >> /etc/ssh/sshd_config; grep -q ^Protocol /etc/ssh/sshd_config || echo Protocol 2 >> /etc/ssh/sshd_config\" && (sudo -n systemctl reload ssh || sudo -n service ssh reload || true) || echo \"sudo required\""',
            'linux_update_packages' => 'sh -lc "(which apt-get >/dev/null 2>&1 && sudo -n apt-get update && sudo -n apt-get -y upgrade) || (which dnf >/dev/null 2>&1 && sudo -n dnf -y upgrade) || (which yum >/dev/null 2>&1 && sudo -n yum -y update) || echo \"package manager not available or sudo required\""'
          ];
        }

        foreach ($actions as $a) {
          if (!isset($map[$a])) { $plan[] = ['action'=>$a,'cmd'=>null,'status'=>'unknown_action']; continue; }
          $cmd = $map[$a];
          $plan[] = ['action'=>$a,'cmd'=>$cmd];
          if ($dryRun) { $outputs[$a] = '(dry-run)'; continue; }
          $out = $ssh->exec($cmd);
          $outputs[$a] = (string)$out;
        }
      } catch (Throwable $e) { $error = $e->getMessage(); }

      $ok = ($error==='');
      if ($ok) {
        $title = $dryRun ? 'Hardening (plano gerado)' : 'Hardening aplicado';
        notif($pdo, $uid, $title, 'Servidor #'.$sid, '/portal/helpdesk/server_analysis.php?id='.$sid);
      }
      echo json_encode(['ok'=> $ok, 'error'=>$error, 'dry_run'=>$dryRun, 'plan'=>$plan, 'outputs'=>$outputs]);
      break;
    }
    case 'tickets_create': {
      if ($method!=='POST') jerr('method');
      $subject = trim($_POST['subject'] ?? '');
      $priority = $_POST['priority'] ?? 'medium';
      $category = trim((string)($_POST['category'] ?? '')) ?: null;
      $description = trim($_POST['description'] ?? '');
      if ($subject==='') jerr('subject');
      try {
        // calcular SLA por prioridade
        $drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hours = ($priority==='urgent'?4:($priority==='high'?8:($priority==='medium'?24:72)));
        $slaExpr = ($drv==='mysql') ? "DATE_ADD(NOW(), INTERVAL ".$hours." HOUR)" : "datetime('now','+".$hours." hours')";
        $cols = ['requester_id','subject','priority','description'];
        $vals = [':r',':s',':p',':d'];
        $params = [':r'=>$uid, ':s'=>$subject, ':p'=>$priority, ':d'=>$description];
        if ($category!==null) { $cols[]='category'; $vals[]=':c'; $params[':c']=$category; }
        // sla_due_at se existir
        $hasSla = true; try { $pdo->query('SELECT sla_due_at FROM helpdesk_tickets WHERE 1=0'); } catch (Throwable $e) { $hasSla=false; }
        if ($hasSla) { $cols[]='sla_due_at'; $vals[]=$slaExpr; }
        $sql = 'INSERT INTO helpdesk_tickets('.implode(',', $cols).') VALUES('.implode(',', $vals).')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
      } catch (Throwable $e) { jerr('db_insert',500); }
      $tid = (int)$pdo->lastInsertId();
      notif($pdo, $uid, 'Ticket criado', $subject, '/portal/helpdesk/ticket_view.php?id='.$tid);
      echo json_encode(['ok'=>true,'id'=>$tid]);
      break;
    }
    case 'ticket_get': {
      $id = (int)($_GET['id'] ?? 0); if ($id<=0) jerr('id');
      $t = $pdo->prepare('SELECT t.*, COALESCE(u.name,u.username) as requester_name, a.name AS assignee_name FROM helpdesk_tickets t JOIN users u ON u.id=t.requester_id LEFT JOIN users a ON a.id=t.assignee_id WHERE t.id=:id');
      $t->execute([':id'=>$id]); $ticket = $t->fetch(); if(!$ticket) jerr('not_found',404);
      $c = $pdo->prepare('SELECT c.id,c.body,c.is_internal,c.created_at,COALESCE(u.name,u.username) as user FROM helpdesk_comments c JOIN users u ON u.id=c.user_id WHERE c.ticket_id=:id ORDER BY c.id ASC');
      $c->execute([':id'=>$id]); $comments = $c->fetchAll();
      echo json_encode(['ticket'=>$ticket,'comments'=>$comments]);
      break;
    }
    case 'users_list': {
      // lista básica de usuários para atribuição
      $q = trim((string)($_GET['q'] ?? ''));
      $sql = 'SELECT id, COALESCE(name,username) AS name FROM users';
      $params=[];
      if($q!==''){ $sql .= ' WHERE (name LIKE :q OR username LIKE :q OR email LIKE :q)'; $params[':q']='%'.$q.'%'; }
      $sql .= ' ORDER BY name ASC LIMIT 50';
      try{ $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(); }catch(Throwable $e){ jerr('db_users',500);}    
      echo json_encode(['items'=>$rows]);
      break;
    }
    case 'tickets_assign': {
      if ($method!=='POST') jerr('method');
      $id = (int)($_POST['id'] ?? 0); $ass = (int)($_POST['assignee_id'] ?? 0);
      if ($id<=0) jerr('id');
      try { $s=$pdo->prepare('UPDATE helpdesk_tickets SET assignee_id=:a, updated_at=CURRENT_TIMESTAMP WHERE id=:id'); $s->execute([':a'=>$ass?:null, ':id'=>$id]); } catch(Throwable $e){ jerr('db_assign',500); }
      // notificar atribuído e solicitante
      try{ $q=$pdo->prepare('SELECT subject, requester_id FROM helpdesk_tickets WHERE id=:id'); $q->execute([':id'=>$id]); $r=$q->fetch(); $sub=$r['subject']??('Ticket #'.$id);
        if($ass>0) notif($pdo, $ass, 'Ticket atribuído', $sub, '/portal/helpdesk/ticket_view.php?id='.$id);
        $req=(int)($r['requester_id']??0); if($req>0) notif($pdo, $req, 'Seu ticket foi atribuído', $sub, '/portal/helpdesk/ticket_view.php?id='.$id);
      }catch(Throwable $e){}
      echo json_encode(['ok'=>true]);
      break;
    }
    case 'tickets_update_status': {
      if ($method!=='POST') jerr('method');
      $id = (int)($_POST['id'] ?? 0); $st = trim((string)($_POST['status'] ?? ''));
      if ($id<=0 || $st==='') jerr('invalid');
      try{ $s=$pdo->prepare('UPDATE helpdesk_tickets SET status=:s, updated_at=CURRENT_TIMESTAMP WHERE id=:id'); $s->execute([':s'=>$st, ':id'=>$id]); }catch(Throwable $e){ jerr('db_status',500); }
      try{ $q=$pdo->prepare('SELECT subject, requester_id, assignee_id FROM helpdesk_tickets WHERE id=:id'); $q->execute([':id'=>$id]); $r=$q->fetch(); $sub=$r['subject']??('Ticket #'.$id); $req=(int)($r['requester_id']??0); $ass=(int)($r['assignee_id']??0);
        if($req>0) notif($pdo, $req, 'Status do ticket atualizado', $sub.' • '.$st, '/portal/helpdesk/ticket_view.php?id='.$id);
        if($ass>0) notif($pdo, $ass, 'Status do ticket atualizado', $sub.' • '.$st, '/portal/helpdesk/ticket_view.php?id='.$id);
      }catch(Throwable $e){}
      echo json_encode(['ok'=>true]);
      break;
    }
    case 'comment_add': {
      if ($method!=='POST') jerr('method');
      $id = (int)($_POST['ticket_id'] ?? 0); $body = trim($_POST['body'] ?? ''); $internal = (int)($_POST['is_internal'] ?? 0);
      if ($id<=0 || $body==='') jerr('invalid');
      $s = $pdo->prepare('INSERT INTO helpdesk_comments (ticket_id, user_id, body, is_internal) VALUES (:t,:u,:b,:i)');
      $s->execute([':t'=>$id, ':u'=>$uid, ':b'=>$body, ':i'=>$internal]);
      notif($pdo, $uid, 'Novo comentário', 'Ticket #'.$id, '/portal/helpdesk/ticket_view.php?id='.$id);
      echo json_encode(['ok'=>true]);
      break;
    }

    case 'assets_list': {
      // Servidores já são geridos no Admin (tabela servers)
      $page = max(1, (int)($_GET['page'] ?? 1));
      $per = (int)($_GET['per_page'] ?? 50); if ($per<=0 || $per>200) $per = 50;
      $off = ($page-1)*$per;
      try {
        $stmt = $pdo->prepare("SELECT id, name, host, ip, protocol, port FROM servers ORDER BY name ASC LIMIT :per OFFSET :off");
        $stmt->bindValue(':per',$per,PDO::PARAM_INT); $stmt->bindValue(':off',$off,PDO::PARAM_INT); $stmt->execute();
        $rows = $stmt->fetchAll();
      } catch (Throwable $e) { jerr('db_assets',500); }
      echo json_encode(['items'=>$rows,'page'=>$page,'per_page'=>$per]);
      break;
    }
    case 'assets_create': {
      // Bloqueado aqui: criação/edição de servidores é feita no Admin (settings)
      jerr('use_admin_servers', 403);
    }
    case 'asset_get': {
      $id = (int)($_GET['id'] ?? 0); if ($id<=0) jerr('id');
      $s = $pdo->prepare('SELECT * FROM helpdesk_assets WHERE id=:id'); $s->execute([':id'=>$id]); $a = $s->fetch(); if(!$a) jerr('not_found',404);
      echo json_encode(['asset'=>$a]);
      break;
    }

    case 'run_collect_ssh': {
      if ($method!=='POST') jerr('method');
      $id = (int)($_POST['asset_id'] ?? 0); if ($id<=0) jerr('asset_id');
      $pass = (string)($_POST['ssh_pass'] ?? '');
      $a = $pdo->prepare('SELECT * FROM helpdesk_assets WHERE id=:id'); $a->execute([':id'=>$id]); $asset = $a->fetch(); if(!$asset) jerr('not_found',404);

      $scanIns = $pdo->prepare("INSERT INTO helpdesk_scans (asset_id, type, status, started_at) VALUES (:aid,'ssh_collect','running', CURRENT_TIMESTAMP)");
      $scanIns->execute([':aid'=>$id]); $scanId = (int)$pdo->lastInsertId();

      $results = [];
      $error = '';
      try {
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($asset['ssh_host'] ?: $asset['ip'], (int)($asset['ssh_port'] ?: 22));
        if (!($asset['ssh_user'] ?? '') || !$pass) { throw new Exception('missing_credentials'); }
        if (!$ssh->login((string)$asset['ssh_user'], $pass)) { throw new Exception('auth_failed'); }
        $cmds = [
          'whoami' => 'whoami',
          'hostname' => 'hostname -f || hostname',
          'kernel' => 'uname -a',
          'os' => 'cat /etc/os-release || lsb_release -a',
          'cpu' => 'lscpu || cat /proc/cpuinfo',
          'mem' => 'free -h || cat /proc/meminfo',
          'disk' => 'df -h --output=source,fstype,size,used,avail,pcent,target || df -h',
          'net_if' => 'ip -br a || ifconfig -a',
          'net_route' => 'ip route || route -n',
          'ports' => 'ss -lntup || netstat -lntup',
          'packages' => 'which dpkg >/dev/null 2>&1 && dpkg -l || (which rpm >/dev/null 2>&1 && rpm -qa || echo "pkg list unsupported")',
          'public_ip' => 'curl -s https://ifconfig.me || wget -qO- https://ifconfig.me || dig +short myip.opendns.com @resolver1.opendns.com || echo "unknown"'
        ];
        foreach ($cmds as $k=>$c) {
          $out = $ssh->exec($c);
          $results[$k] = $out;
        }
      } catch (Throwable $e) {
        $error = $e->getMessage();
      }

      $sum = $error ? ('error: '.$error) : ('coleta concluída');
      $upd = $pdo->prepare("UPDATE helpdesk_scans SET status=:st, finished_at=CURRENT_TIMESTAMP, summary=:sm WHERE id=:id");
      $upd->execute([':st'=>$error?'error':'done', ':sm'=>$sum, ':id'=>$scanId]);

      if (!$error) {
        $insR = $pdo->prepare('INSERT INTO helpdesk_scan_results (scan_id, key_name, value_text) VALUES (:sid,:k,:v)');
        foreach ($results as $k=>$v) { $insR->execute([':sid'=>$scanId, ':k'=>$k, ':v'=>(string)$v]); }
      }

      echo json_encode(['ok'=> !$error, 'scan_id'=>$scanId, 'error'=>$error, 'summary'=>$sum]);
      break;
    }

    case 'scan_get': {
      $id = (int)($_GET['id'] ?? 0); if ($id<=0) jerr('id');
      $s = $pdo->prepare('SELECT * FROM helpdesk_scans WHERE id=:id'); $s->execute([':id'=>$id]); $scan = $s->fetch(); if(!$scan) jerr('not_found',404);
      $r = $pdo->prepare('SELECT key_name, value_text FROM helpdesk_scan_results WHERE scan_id=:id ORDER BY id ASC'); $r->execute([':id'=>$id]);
      echo json_encode(['scan'=>$scan,'results'=>$r->fetchAll()]);
      break;
    }

    case 'servers_list': {
      $page = max(1, (int)($_GET['page'] ?? 1));
      $per = (int)($_GET['per_page'] ?? 50); if ($per<=0 || $per>200) $per = 50;
      $off = ($page-1)*$per;
      try {
        $stmt = $pdo->prepare("SELECT id, name, host, ip, protocol, port, username FROM servers ORDER BY name ASC LIMIT :per OFFSET :off");
        $stmt->bindValue(':per',$per,PDO::PARAM_INT); $stmt->bindValue(':off',$off,PDO::PARAM_INT); $stmt->execute();
        $rows = $stmt->fetchAll();
      } catch (Throwable $e) { jerr('db_servers',500); }
      echo json_encode(['items'=>$rows,'page'=>$page,'per_page'=>$per]);
      break;
    }
    case 'server_analyze': {
      // RBAC: requer papel helpdesk_operate ou admin
      $roles = $_SESSION['portal_user']['roles'] ?? [];
      if (!in_array('admin', (array)$roles, true) && !in_array('helpdesk_operate', (array)$roles, true)) { jerr('forbidden', 403); }
      if ($method!=='POST') jerr('method');
      $sid = (int)($_POST['server_id'] ?? 0); if ($sid<=0) jerr('server_id');
      $types = $_POST['types'] ?? ''; // comma-separated e.g. packages,security_baseline,network,vulnerabilities
      $types = array_filter(array_map('trim', explode(',', (string)$types)));
      if (!$types) jerr('types');
      $pass = (string)($_POST['ssh_pass'] ?? '');
      try { $s = $pdo->prepare('SELECT * FROM servers WHERE id=:id'); $s->execute([':id'=>$sid]); $sv = $s->fetch(); } catch (Throwable $e) { jerr('db_server',500); }
      if (!$sv) jerr('not_found',404);

      $host = $sv['host'] ?: $sv['ip'];
      $port = (int)($sv['port'] ?: 22);
      $user = (string)($sv['username'] ?: '');
      $pw = (string)($sv['password'] ?: '');
      if ($pw !== '') { $pw = decrypt_secret($pw) ?: $pw; }
      if ($pass!=='') { $pw = $pass; }
      if (!$host || !$user || !$pw) jerr('missing_credentials',400);

      $results = [];
      $error = '';
      try {
        if (!class_exists('phpseclib3\\Net\\SSH2')) { throw new Exception('phpseclib not installed'); }
        $ssh = new phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($user, $pw)) { throw new Exception('auth_failed'); }

        // Detect OS
        $osOut = trim((string)$ssh->exec('uname -s 2>/dev/null || echo WINDOWS'));
        $isWindows = (stripos($osOut, 'windows') !== false) || (stripos($osOut, 'msys') !== false) || (stripos($osOut, 'mingw') !== false);

        if ($isWindows) {
          $map = [
            'packages' => [
              'cmd' => 'powershell -NoProfile -Command "(Get-Package | Select-Object Name,Version | Format-Table -AutoSize | Out-String) 2>$null; if(!$?) { (wmic product get name,version) }"',
              'key' => 'packages'
            ],
            'security_baseline' => [
              'cmd' => 'powershell -NoProfile -Command "Write-Output \"[systeminfo]\"; systeminfo; Write-Output \n\"[firewall]\"; (Get-NetFirewallProfile | Select Name,Enabled | Format-Table -AutoSize | Out-String); Write-Output \n\"[users]\"; (Get-LocalUser | Select Name,Enabled | Format-Table -AutoSize | Out-String) 2>$null; Write-Output \n\"[rdp]\"; (Get-ItemProperty -Path \"HKLM: SYSTEM\\CurrentControlSet\\Control\\Terminal Server\" -Name fDenyTSConnections).fDenyTSConnections"',
              'key' => 'security_baseline'
            ],
            'network' => [
              'cmd' => 'powershell -NoProfile -Command "Write-Output \"[interfaces]\"; (Get-NetIPConfiguration | Format-Table -AutoSize | Out-String); Write-Output \n\"[routes]\"; (route print | Out-String); Write-Output \n\"[ports]\"; (netstat -ano | Out-String)"',
              'key' => 'network'
            ],
            'vulnerabilities' => [
              'cmd' => 'powershell -NoProfile -Command "Write-Output \"[hotfix]\"; (Get-HotFix | Select HotFixID,InstalledOn | Format-Table -AutoSize | Out-String); Write-Output \n\"[os]\"; (Get-ComputerInfo | Select WindowsProductName,WindowsVersion,OsBuildNumber | Format-Table -AutoSize | Out-String)"',
              'key' => 'vulnerabilities'
            ]
          ];
        } else {
          $map = [
            'packages' => [
              'cmd' => "(which dpkg >/dev/null 2>&1 && dpkg -l) || (which rpm >/dev/null 2>&1 && rpm -qa) || echo 'packages: unsupported'",
              'key' => 'packages'
            ],
            'security_baseline' => [
              'cmd' => "echo '[kernel]'; uname -a; echo '\n[users]'; getent passwd | awk -F: '{print $1\":\"$7}' | head -n 200; echo '\n[sudoers]'; (sudo -n true >/dev/null 2>&1 && echo 'sudo: available') || echo 'sudo: require password'; echo '\n[firewall]'; (which ufw >/dev/null 2>&1 && sudo ufw status) || (which firewall-cmd >/dev/null 2>&1 && sudo firewall-cmd --state) || echo 'no firewall tool';",
              'key' => 'security_baseline'
            ],
            'network' => [
              'cmd' => "echo '[interfaces]'; (ip -br a || ifconfig -a); echo '\n[routes]'; (ip route || route -n); echo '\n[ports]'; (ss -lntup || netstat -lntup)",
              'key' => 'network'
            ],
            'vulnerabilities' => [
              'cmd' => "echo '[os]'; (cat /etc/os-release || lsb_release -a); echo '\n[packages_versions]'; (which dpkg >/dev/null 2>&1 && dpkg -l | awk '{print $2\" \"$3}' | head -n 500) || (which rpm >/dev/null 2>&1 && rpm -qa --queryformat '%{NAME} %{VERSION}-%{RELEASE}\n' | head -n 500) || echo 'pkg list unsupported'",
              'key' => 'vulnerabilities'
            ]
          ];
        }

        foreach ($types as $t) {
          if (!isset($map[$t])) continue;
          $out = $ssh->exec($map[$t]['cmd']);
          $results[$map[$t]['key']] = (string)$out;
        }
      } catch (Throwable $e) {
        $error = $e->getMessage();
      }
      $ok = ($error==='');
      if ($ok) { notif($pdo, $uid, 'Análise concluída', 'Servidor #'.$sid.' analisado', '/portal/helpdesk/server_analysis.php?id='.$sid); }
      echo json_encode(['ok'=> $ok, 'error'=>$error, 'results'=>$results]);
      break;
    }
    case 'server_analysis_pdf': {
      if ($method!=='POST') jerr('method');
      $payload = (string)($_POST['payload'] ?? ''); if ($payload==='') jerr('payload');
      if (!class_exists('Dompdf\\Dompdf')) { jerr('dompdf_not_installed', 400); }
      $data = json_decode($payload, true); if (!is_array($data)) jerr('bad_payload');
      $sid = (int)($_POST['server_id'] ?? 0);
      $html = '<html><head><meta charset="utf-8"><style>body{font-family:sans-serif;font-size:12px}h1{font-size:16px}h2{font-size:14px}pre{white-space:pre-wrap;border:1px solid #ddd;padding:6px;border-radius:4px}</style></head><body>';
      $html .= '<h1>Relatório de Análise - Servidor #'.htmlspecialchars((string)$sid).'</h1>';
      $sections = [ 'packages'=>'Pacotes', 'security_baseline'=>'Baseline de segurança', 'network'=>'Rede/Portas', 'vulnerabilities'=>'Pacotes (CVE)' ];
      foreach ($sections as $k=>$title) {
        if (!empty($data['results'][$k])) {
          $html .= '<h2>'.htmlspecialchars($title).'</h2><pre>'.htmlspecialchars((string)$data['results'][$k]).'</pre>';
        }
      }
      if (!headers_sent()) { header_remove('Content-Type'); }
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="server_analysis_'.$sid.'.pdf"');
      $dompdf = new Dompdf\Dompdf();
      $dompdf->loadHtml($html, 'UTF-8');
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();
      echo $dompdf->output();
      exit;
    }
    case 'url_analyze': {
      $url = trim($_POST['url'] ?? $_GET['url'] ?? ''); if ($url==='') jerr('url');
      if (!preg_match('~^https?://~i', $url)) { $url = 'http://' . $url; }
      $parts = @parse_url($url); if (!$parts || empty($parts['host'])) jerr('bad_url');
      $host = $parts['host'];

      $ips = [];
      try { $ips = gethostbynamel($host) ?: []; } catch (Throwable $e) {}
      if (function_exists('dns_get_record')) {
        try { $arec = dns_get_record($host, DNS_A); foreach (($arec?:[]) as $r){ if(!empty($r['ip'])) $ips[]=$r['ip']; } } catch (Throwable $e) {}
        try { $aaaa = dns_get_record($host, DNS_AAAA); foreach (($aaaa?:[]) as $r){ if(!empty($r['ipv6'])) $ips[]=$r['ipv6']; } } catch (Throwable $e) {}
      }
      $ips = array_values(array_unique($ips));

      $headers = [];
      $bodyLen = 0; $code = 0; $finalUrl = $url; $scheme = $parts['scheme'] ?? '';
      $tls = ['protocol'=>null, 'cipher'=>null];
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_NOBODY => false,
        CURLOPT_HEADERFUNCTION => function($ch,$str) use (&$headers){ $len = strlen($str); $line=trim($str); if($line!==''){ $headers[]=$line; } return $len; },
        CURLOPT_USERAGENT => 'IntranetHelpDesk/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CERTINFO => true,
      ]);
      $resp = curl_exec($ch);
      if ($resp !== false) { $bodyLen = strlen($resp); }
      $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
      $scheme = parse_url($finalUrl, PHP_URL_SCHEME) ?: $scheme;
      $pi = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
      if ($pi && !in_array($pi,$ips,true)) $ips[] = $pi;
      $sslResult = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
      $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);
      curl_close($ch);

      // Achados simples
      $findings = [];
      $hstr = implode("\n", $headers);
      if ($scheme === 'http') $findings[] = 'Sem HTTPS (URL final em HTTP)';
      if (!preg_match('/strict-transport-security/i', $hstr)) $findings[] = 'Sem HSTS';
      if (preg_match('/server:\s*(.+)/i', $hstr, $m)) { if (stripos($m[1],'apache')!==false || stripos($m[1],'nginx')!==false || stripos($m[1],'iis')!==false) { /* ok */ } }
      if (!preg_match('/content-security-policy/i', $hstr)) $findings[] = 'Sem Content-Security-Policy';
      if (!preg_match('/x-frame-options/i', $hstr)) $findings[] = 'Sem X-Frame-Options';
      if (!preg_match('/x-content-type-options/i', $hstr)) $findings[] = 'Sem X-Content-Type-Options';
      if (!preg_match('/referrer-policy/i', $hstr)) $findings[] = 'Sem Referrer-Policy';

      echo json_encode([
        'ok'=>true,
        'input_url'=>$url,
        'final_url'=>$finalUrl,
        'http_code'=>$code,
        'ips'=>$ips,
        'headers'=>$headers,
        'body_len'=>$bodyLen,
        'tls'=>['verify_result'=>$sslResult,'cert_info'=>$certInfo],
        'findings'=>$findings
      ]);
      break;
    }

    default: jerr('unknown',404);
  }
} catch (Throwable $e) {
  jerr('server',500);
}
