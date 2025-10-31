<?php
// CLI script to cleanup old chat attachments
declare(strict_types=1);

if (php_sapi_name() !== 'cli') { echo "Run from CLI.\n"; exit(1); }

$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Args: --days=N, --dry-run
$days = 30; $dryRun = false;
foreach ($argv as $arg) {
  if (preg_match('/^--days=(\d{1,4})$/', $arg, $m)) { $days = max(1, (int)$m[1]); }
  if ($arg === '--dry-run') { $dryRun = true; }
}

$baseUploadDir = realpath(__DIR__ . '/../uploads/chat') ?: (__DIR__ . '/../uploads/chat');
if (!is_dir($baseUploadDir)) { echo "No uploads directory found at $baseUploadDir\n"; exit(0); }

try {
  if ($driver === 'mysql') {
    $stmt = $pdo->prepare("SELECT id, file_path, file_name, created_at, body FROM user_messages WHERE file_path IS NOT NULL AND created_at < (CURRENT_TIMESTAMP - INTERVAL :d DAY) ORDER BY id ASC");
    $stmt->bindValue(':d', $days, PDO::PARAM_INT);
    $stmt->execute();
  } else {
    $cut = sprintf("-%d days", $days);
    $stmt = $pdo->prepare("SELECT id, file_path, file_name, created_at, body FROM user_messages WHERE file_path IS NOT NULL AND datetime(created_at) < datetime('now', :cut) ORDER BY id ASC");
    $stmt->execute([':cut'=>$cut]);
  }
  $rows = $stmt->fetchAll();

  $delCount = 0; $updCount = 0; $errCount = 0;
  foreach ($rows as $r) {
    $rel = (string)$r['file_path'];
    $file = $rel;
    if (str_starts_with($rel, '/')) { $file = $baseUploadDir . '/' . ltrim(basename($rel), '/'); }
    else { $file = $baseUploadDir . '/' . $rel; }

    if ($dryRun) {
      echo "Would remove message #{$r['id']} file: {$rel}\n";
      continue;
    }

    $okFs = true;
    if (is_file($file)) {
      $okFs = @unlink($file);
    } else {
      // file already missing; continue to cleanup DB
      $okFs = true;
    }

    if ($okFs) {
      $upd = $pdo->prepare("UPDATE user_messages SET file_path=NULL, file_name=NULL, file_type=NULL, file_size=NULL WHERE id=:id");
      $upd->execute([':id'=>(int)$r['id']]);
      $updCount++;
      if (isset($r['file_name'])) $delCount++;
      echo "Cleaned message #{$r['id']} ({$rel})\n";
    } else {
      $errCount++;
      fwrite(STDERR, "Failed to remove {$file} for message #{$r['id']}\n");
    }
  }

  echo "Done. Files removed: {$delCount}. Rows updated: {$updCount}. Errors: {$errCount}.\n";
  exit(0);
} catch (Throwable $e) {
  fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
  exit(1);
}
