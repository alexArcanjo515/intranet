<?php
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'CLI only'; exit; }
$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function execSQLCR(PDO $pdo, string $sql): void { if ($sql!=='') { $pdo->exec($sql); } }

try{
  if ($driver==='mysql'){
    execSQLCR($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS canned_replies (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      scope VARCHAR(32) NOT NULL,
      user_id INT UNSIGNED NULL,
      title VARCHAR(191) NOT NULL,
      body TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_cr_scope (scope),
      INDEX idx_cr_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);
  } else {
    execSQLCR($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS canned_replies (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      scope TEXT NOT NULL,
      user_id INTEGER,
      title TEXT NOT NULL,
      body TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);
  }
  echo "Canned replies migration OK\n";
} catch (Throwable $e){
  fwrite(STDERR, 'Migration error: '.$e->getMessage()."\n");
  exit(1);
}
