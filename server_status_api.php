<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json');

$pdo = require __DIR__ . '/config/db.php';

// ids param as comma-separated list
$idsParam = $_GET['ids'] ?? '';
$ids = array_values(array_filter(array_map('intval', explode(',', (string)$idsParam)), fn($v)=>$v>0));
if (empty($ids)) { echo json_encode(['error' => 'no ids']); exit(); }

// fetch servers
$in = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, name, ip, host, protocol, port FROM servers WHERE id IN ($in)");
$stmt->execute($ids);
$servers = $stmt->fetchAll();

$result = [];
$doLog = isset($_GET['log']) && (string)$_GET['log'] === '1';
if ($doLog) {
    // prepare insert
    $ins = $pdo->prepare('INSERT INTO server_status_log (server_id, up, latency_ms, http_code) VALUES (:sid, :up, :lat, :code)');
}
foreach ($servers as $s) {
    $host = $s['host'] ?: $s['ip'];
    $port = (int)$s['port'];
    $protocol = $s['protocol'];
    $entry = [
        'id' => (int)$s['id'],
        'name' => (string)$s['name'],
        'protocol' => (string)$protocol,
        'host' => (string)$host,
        'port' => $port,
        'up' => false,
        'latency_ms' => null,
        'http_code' => null,
        'checked_at' => date('c')
    ];

    if ($protocol === 'http' || $protocol === 'https') {
        $url = $protocol . '://' . $host . (($protocol==='http' && $port!=80) || ($protocol==='https' && $port!=443) ? (":".$port) : '') . '/';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Intranet/1.0',
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
    } else { // ssh or others -> TCP
        $timeout = 3;
        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $latency = (microtime(true) - $start) * 1000;
        if ($fp) { fclose($fp); $entry['up'] = true; $entry['latency_ms'] = (int)$latency; }
    }

    $result[] = $entry;
    if ($doLog) {
        try { $ins->execute([':sid'=>$entry['id'], ':up'=>$entry['up']?1:0, ':lat'=>$entry['latency_ms'], ':code'=>$entry['http_code']]); } catch (Throwable $e) {}
    }
}

echo json_encode(['items' => $result]);
