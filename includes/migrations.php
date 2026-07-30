<?php
declare(strict_types=1);

function run_migrations(PDO $pdo): void {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM tracks")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('disc_no', $cols, true)) {
            $pdo->exec("ALTER TABLE tracks ADD COLUMN disc_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER title");
        }
        if (!in_array('duration_seconds', $cols, true)) {
            $pdo->exec("ALTER TABLE tracks ADD COLUMN duration_seconds INT UNSIGNED NULL AFTER file_size");
        }

        $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('display_name', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(150) NOT NULL DEFAULT '' AFTER username");
        }
        if (!in_array('role', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password_hash");
            $pdo->exec("UPDATE users SET role='admin' ORDER BY id ASC LIMIT 1");
        }
        if (!in_array('is_active', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        // Bestehende Installationen bleiben nutzbar, auch wenn der DB-Benutzer keine ALTER-Rechte besitzt.
    }
}
