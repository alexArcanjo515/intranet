<?php
// CLI script to log server status for uptime metrics. Usage:
// php scripts/cron_server_status.php [--ids=1,2,3] [--timeout=5]
// Can be scheduled with cron.

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$pdo = require __DIR__ . '/../config/db.php';

$idsOpt = null; $timeout = 5; $connectTimeout = 3;
foreach ($argv as $arg) {
    if (strpos($arg, '--ids=') === 0) { $idsOpt = substr($arg, 6); }
    if (strpos($arg, '--timeout=') === 0) { $timeout = max(1, (int)substr($arg, 10)); }
}

if ($idsOpt) {
    $ids = array_values(array_filter(array_map('intval', explode(',', $idsOpt)), fn($v)=>$v>0));
    if (!$ids) { fwrite(STDERR, "No valid ids provided.\n"); exit(1); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, ip, host, protocol, port FROM servers WHERE id IN ($in)");
    $stmt->execute($ids);
    $servers = $stmt->fetchAll();
} else {
    $servers = $pdo->query("SELECT id, name, ip, host, protocol, port FROM servers")->fetchAll();
}

$ins = $pdo->prepare('INSERT INTO server_status_log (server_id, up, latency_ms, http_code) VALUES (:sid, :up, :lat, :code)');

$ok = 0; $fail = 0;
foreach ($servers as $s) {
    $host = $s['host'] ?: $s['ip'];
    $port = (int)$s['port'];
    $protocol = $s['protocol'];
    $entry = ['id'=>(int)$s['id'], 'up'=>false, 'latency_ms'=>null, 'http_code'=>null];
    if ($protocol === 'http' || $protocol === 'https') {
        $url = $protocol . '://' . $host . (($protocol==='http' && $port!=80) || ($protocol==='https' && $port!=443) ? (":".$port) : '') . '/';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'IntranetCron/1.0',
            CURLOPT_HEADER => true,
        ]);
        $start = microtime(true);
        $resp = curl_exec($ch);
        $latency = (microtime(true) - $start) * 1000;
        if ($resp !== false) {
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $entry['up'] = ($code >= 200 && $code < 500);
            $entry['http_code'] = $code;
            $entry['latency_ms'] = (int)$latency;
        }
        curl_close($ch);
    } else {
        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, $connectTimeout);
        $latency = (microtime(true) - $start) * 1000;
        if ($fp) { fclose($fp); $entry['up'] = true; $entry['latency_ms'] = (int)$latency; }
    }
    try {
        $ins->execute([':sid'=>$entry['id'], ':up'=>$entry['up']?1:0, ':lat'=>$entry['latency_ms'], ':code'=>$entry['http_code']]);
        $ok++;
    } catch (Throwable $e) { $fail++; }
}

echo "Logged: $ok, Failures: $fail\n";
