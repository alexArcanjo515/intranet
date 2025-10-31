<?php
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'CLI only'; exit; }
$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function addIndex(PDO $pdo, string $sql): void { if ($sql!=='') { try{ $pdo->exec($sql); }catch(Throwable $e){} } }

try {
  if ($driver === 'mysql') {
    // notifications: (user_id, created_at)
    addIndex($pdo, "ALTER TABLE notifications ADD INDEX idx_notifications_user_created (user_id, created_at)");
    // servers: name, host, ip
    addIndex($pdo, "ALTER TABLE servers ADD INDEX idx_servers_name (name)");
    addIndex($pdo, "ALTER TABLE servers ADD INDEX idx_servers_host (host)");
    addIndex($pdo, "ALTER TABLE servers ADD INDEX idx_servers_ip (ip)");
    // server_status_log: (server_id, created_at)
    addIndex($pdo, "ALTER TABLE server_status_log ADD INDEX idx_ssl_server_created (server_id, created_at)");
    echo "Indexes migration (MySQL) OK\n";
  } else {
    // SQLite: create indexes if not exist
    addIndex($pdo, "CREATE INDEX IF NOT EXISTS idx_notifications_user_created ON notifications(user_id, created_at)");
    addIndex($pdo, "CREATE INDEX IF NOT EXISTS idx_servers_name ON servers(name)");
    addIndex($pdo, "CREATE INDEX IF NOT EXISTS idx_servers_host ON servers(host)");
    addIndex($pdo, "CREATE INDEX IF NOT EXISTS idx_servers_ip ON servers(ip)");
    addIndex($pdo, "CREATE INDEX IF NOT EXISTS idx_ssl_server_created ON server_status_log(server_id, created_at)");
    echo "Indexes migration (SQLite) OK\n";
  }
} catch (Throwable $e) {
  fwrite(STDERR, 'Indexes migration error: '.$e->getMessage()."\n");
  exit(1);
}
