<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
header('Content-Type: application/json');

$pdo = require __DIR__ . '/config/db.php';

$serverId = isset($_GET['server_id']) ? (int)$_GET['server_id'] : 0;
$days = isset($_GET['days']) ? max(1, min(180, (int)$_GET['days'])) : 30;
if ($serverId <= 0) { echo json_encode(['error'=>'invalid server_id']); exit(); }

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare("SELECT DATE(created_at) AS d, AVG(NULLIF(latency_ms,0)) AS avg_ms, COUNT(*) AS checks, SUM(up) AS ups
                               FROM server_status_log
                               WHERE server_id = :sid AND created_at >= (CURDATE() - INTERVAL :days DAY)
                               GROUP BY DATE(created_at)
                               ORDER BY d");
        // MySQL doesn't allow binding INTERVAL directly as param in some versions; fallback to dynamic
        $days = (int)$days;
        $sql = "SELECT DATE(created_at) AS d, AVG(NULLIF(latency_ms,0)) AS avg_ms, COUNT(*) AS checks, SUM(up) AS ups
                FROM server_status_log
                WHERE server_id = :sid AND created_at >= (CURDATE() - INTERVAL $days DAY)
                GROUP BY DATE(created_at)
                ORDER BY d";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sid'=>$serverId]);
    } else {
        $stmt = $pdo->prepare("SELECT date(created_at) AS d, AVG(NULLIF(latency_ms,0)) AS avg_ms, COUNT(*) AS checks, SUM(up) AS ups
                               FROM server_status_log
                               WHERE server_id = :sid AND date(created_at) >= date('now', :q)
                               GROUP BY date(created_at)
                               ORDER BY d");
        $stmt->execute([':sid'=>$serverId, ':q'=>'-'.$days.' day']);
    }
    $rows = $stmt->fetchAll();
    $labels = array_map(fn($r)=> $r['d'], $rows);
    $avg = array_map(fn($r)=> $r['avg_ms'] !== null ? (int)round($r['avg_ms']) : null, $rows);
    $upt = array_map(fn($r)=> ($r['checks'] ?? 0) > 0 ? round(((int)$r['ups'] / (int)$r['checks'])*100,1) : null, $rows);
    echo json_encode(['labels'=>$labels,'avg_latency_ms'=>$avg,'uptime_pct'=>$upt]);
} catch (Throwable $e) {
    echo json_encode(['error'=>'query failed']);
}
