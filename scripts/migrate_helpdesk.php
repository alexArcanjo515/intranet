<?php
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'CLI only'; exit; }
$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function execSQLHD(PDO $pdo, string $sql): void {
  if ($sql === '') return;
  $pdo->exec($sql);
}

try {
  if ($driver === 'mysql') {
    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_tickets (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      requester_id INT UNSIGNED NOT NULL,
      assignee_id INT UNSIGNED NULL,
      subject VARCHAR(255) NOT NULL,
      priority ENUM('urgent','high','medium','low') NOT NULL DEFAULT 'medium',
      status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
      description TEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT NULL,
      CONSTRAINT fk_hdt_req FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT fk_hdt_ass FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
      INDEX idx_hdt_status (status),
      INDEX idx_hdt_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    // Add category and sla_due_at if missing
    try { $pdo->exec("ALTER TABLE helpdesk_tickets ADD COLUMN category VARCHAR(191) NULL AFTER subject"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE helpdesk_tickets ADD COLUMN sla_due_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at"); } catch (Throwable $e) {}

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_comments (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      ticket_id INT UNSIGNED NOT NULL,
      user_id INT UNSIGNED NOT NULL,
      body TEXT NOT NULL,
      is_internal TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_hdc_ticket FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets(id) ON DELETE CASCADE,
      CONSTRAINT fk_hdc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      INDEX idx_hdc_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_attachments (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      ticket_id INT UNSIGNED NOT NULL,
      comment_id INT UNSIGNED NULL,
      file_path TEXT NOT NULL,
      file_name VARCHAR(255) NOT NULL,
      file_type VARCHAR(191) NULL,
      file_size INT UNSIGNED NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_hda_ticket FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets(id) ON DELETE CASCADE,
      CONSTRAINT fk_hda_comment FOREIGN KEY (comment_id) REFERENCES helpdesk_comments(id) ON DELETE SET NULL,
      INDEX idx_hda_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_assets (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(191) NOT NULL,
      hostname VARCHAR(191) NULL,
      ip VARCHAR(64) NULL,
      os_name VARCHAR(191) NULL,
      os_version VARCHAR(191) NULL,
      tags JSON NULL,
      ssh_host VARCHAR(191) NULL,
      ssh_port INT UNSIGNED NULL,
      ssh_user VARCHAR(191) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT NULL,
      INDEX idx_hda_ip (ip),
      INDEX idx_hda_host (hostname)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_scans (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      asset_id INT UNSIGNED NULL,
      network_cidr VARCHAR(64) NULL,
      type ENUM('ssh_collect','nmap') NOT NULL,
      status ENUM('queued','running','done','error') NOT NULL DEFAULT 'queued',
      started_at TIMESTAMP NULL,
      finished_at TIMESTAMP NULL,
      summary TEXT NULL,
      CONSTRAINT fk_hds_asset FOREIGN KEY (asset_id) REFERENCES helpdesk_assets(id) ON DELETE SET NULL,
      INDEX idx_hds_type (type),
      INDEX idx_hds_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_scan_results (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      scan_id INT UNSIGNED NOT NULL,
      key_name VARCHAR(191) NOT NULL,
      value_text LONGTEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_hdr_scan FOREIGN KEY (scan_id) REFERENCES helpdesk_scans(id) ON DELETE CASCADE,
      INDEX idx_hdr_scan (scan_id),
      INDEX idx_hdr_key (key_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

  } else {
    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_tickets (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      requester_id INTEGER NOT NULL,
      assignee_id INTEGER NULL,
      subject TEXT NOT NULL,
      priority TEXT NOT NULL DEFAULT 'medium',
      status TEXT NOT NULL DEFAULT 'open',
      description TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT
    );
    SQL);

    // Add category and sla_due_at if missing (SQLite)
    try { $pdo->exec("ALTER TABLE helpdesk_tickets ADD COLUMN category TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE helpdesk_tickets ADD COLUMN sla_due_at TEXT"); } catch (Throwable $e) {}

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_comments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      ticket_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      body TEXT NOT NULL,
      is_internal INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_attachments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      ticket_id INTEGER NOT NULL,
      comment_id INTEGER NULL,
      file_path TEXT NOT NULL,
      file_name TEXT NOT NULL,
      file_type TEXT,
      file_size INTEGER,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_assets (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      hostname TEXT,
      ip TEXT,
      os_name TEXT,
      os_version TEXT,
      tags TEXT,
      ssh_host TEXT,
      ssh_port INTEGER,
      ssh_user TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT
    );
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_scans (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      asset_id INTEGER,
      network_cidr TEXT,
      type TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'queued',
      started_at TEXT,
      finished_at TEXT,
      summary TEXT
    );
    SQL);

    execSQLHD($pdo, <<<SQL
    CREATE TABLE IF NOT EXISTS helpdesk_scan_results (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      scan_id INTEGER NOT NULL,
      key_name TEXT NOT NULL,
      value_text TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);
  }

  echo "Help Desk migration OK\n";
} catch (Throwable $e) {
  fwrite(STDERR, 'Migration error: '.$e->getMessage()."\n");
  exit(1);
}
