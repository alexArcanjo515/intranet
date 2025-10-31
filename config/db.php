<?php
// Simple PDO wrapper. Default: SQLite. To switch to MySQL, see the notes below.

// Ensure storage dir exists for SQLite
$storageDir = __DIR__ . '/../storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

// Choose driver via ENV or default to sqlite
$driver = getenv('DB_DRIVER') ?: 'mysql';

try {
    if ($driver === 'mysql') {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'intranet';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: 'developer';
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else { // sqlite
        $dbFile = __DIR__ . '/../storage/database.sqlite';
        if (!file_exists($dbFile)) {
            touch($dbFile);
            chmod($dbFile, 0664);
        }
        $dsn = 'sqlite:' . $dbFile;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Enable foreign keys for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao conectar no banco: ' . htmlspecialchars($e->getMessage());
    exit;
}

return $pdo;
