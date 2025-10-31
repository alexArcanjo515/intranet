<?php
// CLI: php scripts/cron_project_env_health.php --timeout=5
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$pdo = require __DIR__ . '/../config/db.php';
$timeout = 5; // seconds
foreach ($argv as $a) {
  if (preg_match('/^--timeout=(\d+)/', $a, $m)) { $timeout = max(1, (int)$m[1]); }
}

function check_url(string $url, int $timeout): array {
  $start = microtime(true);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_NOBODY => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => min($timeout, 3),
    CURLOPT_USERAGENT => 'IntranetHealthBot/1.0'
  ]);
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
  $err = curl_error($ch);
  curl_close($ch);
  $lat = (int)round((microtime(true) - $start) * 1000);
  $ok = $code >= 200 && $code < 300;
  return ['ok'=>$ok, 'code'=>$code ?: null, 'latency_ms'=>$lat];
}

try {
  $envs = $pdo->query("SELECT e.id, e.name, e.health_url, e.project_id, p.name AS project_name FROM project_envs e JOIN projects p ON p.id=e.project_id WHERE e.health_url IS NOT NULL AND TRIM(e.health_url) <> ''")->fetchAll();
  $ins = $pdo->prepare('INSERT INTO project_env_status_log (env_id, up, latency_ms, http_code) VALUES (:e,:u,:l,:h)');
  $lastQ = $pdo->prepare('SELECT up FROM project_env_status_log WHERE env_id=:e ORDER BY id DESC LIMIT 1');
  $noti = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u,:t,:ti,:b,:l)');
  $membersQ = $pdo->prepare('SELECT user_id FROM project_members WHERE project_id=:p');
  foreach ($envs as $e) {
    $hid = (int)$e['id'];
    $url = (string)$e['health_url'];
    $res = check_url($url, $timeout);
    $ins->execute([
      ':e' => $hid,
      ':u' => $res['ok'] ? 1 : 0,
      ':l' => $res['latency_ms'],
      ':h' => $res['code']
    ]);
    // Detect transitions
    $lastQ->execute([':e'=>$hid]);
    $prev = $lastQ->fetchColumn();
    $prevUp = ($prev === false) ? null : (int)$prev;
    $nowUp = $res['ok'] ? 1 : 0;
    if ($prevUp !== null && $prevUp !== $nowUp) {
      // Transition occurred
      $membersQ->execute([':p'=>(int)$e['project_id']]);
      $uids = array_map('intval', $membersQ->fetchAll(PDO::FETCH_COLUMN));
      if ($uids) {
        foreach ($uids as $uid) {
          if ($uid <= 0) continue;
          $noti->execute([
            ':u'=>$uid,
            ':t'=>'env_health',
            ':ti'=> $nowUp ? ('Ambiente ONLINE: ' . (string)$e['name']) : ('Ambiente OFFLINE: ' . (string)$e['name']),
            ':b'=> ($e['project_name'] ?? 'Projeto') . ' · HTTP ' . ((string)($res['code'] ?? '-')) . ' · ' . (string)$res['latency_ms'] . 'ms',
            ':l'=> 'portal/project_view.php?id=' . (int)$e['project_id']
          ]);
        }
      }
    }
    echo sprintf("[%s] %s/%s -> %s (%d ms, HTTP %s)\n", date('c'), $e['project_name'] ?? 'Projeto', $e['name'], $res['ok']?'UP':'DOWN', $res['latency_ms'], $res['code'] ?? '-');
  }
  echo "Done.\n";
} catch (Throwable $e) {
  fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
  exit(1);
}
