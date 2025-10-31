<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function execSQL(PDO $pdo, string $sql): void {
    $pdo->exec($sql);
}

try {
    if ($driver === 'mysql') {
        // MySQL/MariaDB DDL
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(191) NULL,
            email VARCHAR(191) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // Chat: mensagens diretas entre usuários
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS user_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_um_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_um_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_um_pair (sender_id, receiver_id, created_at),
            INDEX idx_um_receiver (receiver_id, is_read, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // Garantir colunas de anexo em installs existentes
        try {
            $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_messages'")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('file_path', $cols, true)) { execSQL($pdo, "ALTER TABLE user_messages ADD COLUMN file_path TEXT NULL AFTER body"); }
            if (!in_array('file_name', $cols, true)) { execSQL($pdo, "ALTER TABLE user_messages ADD COLUMN file_name VARCHAR(255) NULL AFTER file_path"); }
            if (!in_array('file_type', $cols, true)) { execSQL($pdo, "ALTER TABLE user_messages ADD COLUMN file_type VARCHAR(191) NULL AFTER file_name"); }
            if (!in_array('file_size', $cols, true)) { execSQL($pdo, "ALTER TABLE user_messages ADD COLUMN file_size INT UNSIGNED NULL AFTER file_type"); }
        } catch (Throwable $e) { /* ignore */ }

        // Presença e digitação
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS user_presence (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('online','offline') NOT NULL DEFAULT 'offline',
            typing_to INT UNSIGNED NULL,
            typing_until TIMESTAMP NULL,
            CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(64) NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL UNIQUE,
            description VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS permissions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL UNIQUE,
            description VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT UNSIGNED NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, role_id),
            CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT UNSIGNED NOT NULL,
            permission_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS documents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            path VARCHAR(255) NOT NULL,
            category VARCHAR(191) NULL,
            version INT NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_documents_user FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS news (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_news_user FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS document_versions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_id INT UNSIGNED NOT NULL,
            version INT NOT NULL,
            path VARCHAR(255) NOT NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_docver_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_docver_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(191) NOT NULL PRIMARY KEY,
            `value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS servers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            ip VARCHAR(191) NULL,
            host VARCHAR(191) NULL,
            protocol ENUM('http','https','ssh') NOT NULL DEFAULT 'http',
            port INT NOT NULL DEFAULT 80,
            username VARCHAR(191) NULL,
            password VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
        
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS downloads_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_dlog_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_dlog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS server_status_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            server_id INT UNSIGNED NOT NULL,
            up TINYINT(1) NOT NULL,
            latency_ms INT NULL,
            http_code INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_slog_server FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS projects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            objective TEXT NULL,
            description TEXT NULL,
            status ENUM('planned','in_progress','blocked','done','archived') NOT NULL DEFAULT 'planned',
            visibility ENUM('private','team','org') NOT NULL DEFAULT 'team',
            start_date DATE NULL,
            due_date DATE NULL,
            repo_url TEXT NULL,
            repo_branch VARCHAR(191) NULL,
            ci_url TEXT NULL,
            build_status VARCHAR(64) NULL,
            build_updated_at TIMESTAMP NULL,
            request_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // Garantir colunas DevOps em installs existentes
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('repo_url', $cols, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN repo_url TEXT NULL AFTER due_date"); }
        if (!in_array('repo_branch', $cols, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN repo_branch VARCHAR(191) NULL AFTER repo_url"); }
        if (!in_array('ci_url', $cols, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN ci_url TEXT NULL AFTER repo_branch"); }
        if (!in_array('build_status', $cols, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN build_status VARCHAR(64) NULL AFTER ci_url"); }
        if (!in_array('build_updated_at', $cols, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN build_updated_at TIMESTAMP NULL AFTER build_status"); }
        if (!in_array('request_id', $cols, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN request_id INT UNSIGNED NULL AFTER build_updated_at"); }
        // Add FK to project_requests if table exists
        try { $pdo->exec("ALTER TABLE projects ADD CONSTRAINT fk_projects_request FOREIGN KEY (request_id) REFERENCES project_requests(id) ON DELETE SET NULL"); } catch (Throwable $e) { /* ignore if already exists or table absent */ }

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_envs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            name VARCHAR(64) NOT NULL,
            url VARCHAR(255) NULL,
            health_url VARCHAR(255) NULL,
            position INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_penv_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_env_status_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            env_id INT UNSIGNED NOT NULL,
            up TINYINT(1) NOT NULL,
            latency_ms INT NULL,
            http_code INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pesl_env FOREIGN KEY (env_id) REFERENCES project_envs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_members (
            project_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(64) NOT NULL DEFAULT 'member',
            position INT NOT NULL DEFAULT 0,
            PRIMARY KEY (project_id, user_id),
            CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_tasks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status ENUM('todo','doing','review','done','blocked') NOT NULL DEFAULT 'todo',
            assignee_id INT UNSIGNED NULL,
            due_date DATE NULL,
            position INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pt_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_pt_user FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // Project Requests (solicitações de projeto)
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            requester_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            category ENUM('programming','networks','iot','servers') NOT NULL,
            priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            status ENUM('pending','approved','rejected','in_progress','done') NOT NULL DEFAULT 'pending',
            summary TEXT NULL,
            details JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_preq_user FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // Comentários em solicitações (após project_requests)
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_request_comments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            is_internal TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_prc_req FOREIGN KEY (request_id) REFERENCES project_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_prc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    } else {
        // SQLite DDL
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            name TEXT,
            email TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS user_roles (
            user_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INTEGER NOT NULL,
            permission_id INTEGER NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            path TEXT NOT NULL,
            category TEXT,
            version INTEGER NOT NULL DEFAULT 1,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS document_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            version INTEGER NOT NULL,
            path TEXT NOT NULL,
            uploaded_by INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS settings (
            `key` TEXT NOT NULL PRIMARY KEY,
            `value` TEXT
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS servers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            ip TEXT,
            host TEXT,
            protocol TEXT NOT NULL DEFAULT 'http',
            port INTEGER NOT NULL DEFAULT 80,
            username TEXT,
            password TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS downloads_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            user_id INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT,
            title TEXT NOT NULL,
            body TEXT,
            link TEXT,
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS server_status_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            server_id INTEGER NOT NULL,
            up INTEGER NOT NULL,
            latency_ms INTEGER,
            http_code INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            objective TEXT,
            description TEXT,
            status TEXT NOT NULL DEFAULT 'planned',
            visibility TEXT NOT NULL DEFAULT 'team',
            start_date TEXT,
            due_date TEXT,
            repo_url TEXT,
            repo_branch TEXT,
            ci_url TEXT,
            build_status TEXT,
            build_updated_at TEXT,
            request_id INTEGER,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );
        SQL);

        // Garantir colunas DevOps em installs existentes (SQLite)
        $cols = $pdo->query("PRAGMA table_info(projects)")->fetchAll();
        $names = array_map(fn($c)=> $c['name'] ?? $c[1] ?? '', $cols);
        if (!in_array('repo_url', $names, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN repo_url TEXT"); }
        if (!in_array('repo_branch', $names, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN repo_branch TEXT"); }
        if (!in_array('ci_url', $names, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN ci_url TEXT"); }
        if (!in_array('build_status', $names, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN build_status TEXT"); }
        if (!in_array('build_updated_at', $names, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN build_updated_at TEXT"); }
        if (!in_array('request_id', $names, true)) { execSQL($pdo, "ALTER TABLE projects ADD COLUMN request_id INTEGER"); }

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_envs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            url TEXT,
            health_url TEXT,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_members (
            project_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT NOT NULL DEFAULT 'member',
            position INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (project_id, user_id),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        SQL);

        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT NOT NULL DEFAULT 'todo',
            assignee_id INTEGER,
            due_date TEXT,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
        );
        SQL);

        // Project Requests (solicitações de projeto)
        execSQL($pdo, <<<SQL
        CREATE TABLE IF NOT EXISTS project_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            requester_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            priority TEXT NOT NULL DEFAULT 'medium',
            status TEXT NOT NULL DEFAULT 'pending',
            summary TEXT,
            details TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
        );
        SQL);

    }

    if ($driver === 'mysql') {
        $colExists = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news' AND COLUMN_NAME = 'is_published'")->fetchColumn();
        if ((int)$colExists === 0) {
            execSQL($pdo, "ALTER TABLE news ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1 AFTER is_pinned");
        }
    } else {
        $cols = $pdo->query("PRAGMA table_info(news)")->fetchAll();
        $hasCol = false;
        foreach ($cols as $c) { if (isset($c['name']) && $c['name'] === 'is_published') { $hasCol = true; break; } }
        if (!$hasCol) {
            execSQL($pdo, "ALTER TABLE news ADD COLUMN is_published INTEGER NOT NULL DEFAULT 1");
        }
    }

    // Seed admin user
    $hasAdmin = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
    $hasAdmin->execute([':u' => 'admin']);
    if ((int)$hasAdmin->fetchColumn() === 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, name, email) VALUES (:u, :p, :n, :e)');
        $stmt->execute([
            ':u' => 'admin',
            ':p' => $hash,
            ':n' => 'Administrador',
            ':e' => 'admin@example.com'
        ]);
    }

    // Adicionar coluna must_change_password se não existir
    if ($driver === 'mysql') {
        $colExists = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password'")->fetchColumn();
        if ((int)$colExists === 0) {
            execSQL($pdo, "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
        }
    } else {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
        $hasCol = false;
        foreach ($cols as $c) { if (isset($c['name']) && $c['name'] === 'must_change_password') { $hasCol = true; break; } }
        if (!$hasCol) {
            execSQL($pdo, "ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0");
        }
    }

    // Seed role admin (ignore duplicates conforme driver)
    if ($driver === 'mysql') {
        $pdo->exec("INSERT IGNORE INTO roles (id, name, description) VALUES (1, 'admin', 'Acesso total à intranet')");
    } else {
        $pdo->exec("INSERT OR IGNORE INTO roles (id, name, description) VALUES (1, 'admin', 'Acesso total à intranet')");
    }

    // Relacionar admin user ao role admin
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    if ($adminId) {
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        } else {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        }
        $stmt->execute([':u' => $adminId, ':r' => 1]);
    }

    // Seed algumas permissions
    $perms = ['manage_users', 'manage_documents', 'manage_news', 'manage_permissions', 'view_reports', 'manage_settings', 'view_projects', 'manage_projects'];
    if ($driver === 'mysql') {
        $insertPerm = $pdo->prepare('INSERT IGNORE INTO permissions (name, description) VALUES (:n, :d)');
    } else {
        $insertPerm = $pdo->prepare('INSERT OR IGNORE INTO permissions (name, description) VALUES (:n, :d)');
    }
    foreach ($perms as $perm) {
        $insertPerm->execute([':n' => $perm, ':d' => ucfirst(str_replace('_', ' ', $perm))]);
    }

    // Vincular todas as permissions ao role admin
    $permIds = $pdo->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
    if ($driver === 'mysql') {
        $bind = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, :pid)');
    } else {
        $bind = $pdo->prepare('INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, :pid)');
    }
    foreach ($permIds as $pid) {
        $bind->execute([':pid' => $pid]);
    }

    $message = "Migração concluída com sucesso.";
} catch (Throwable $e) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    $message = 'Erro na migração: ' . $e->getMessage();
}

if (php_sapi_name() === 'cli') {
    echo $message . PHP_EOL;
} else {
    echo htmlspecialchars($message);
}
