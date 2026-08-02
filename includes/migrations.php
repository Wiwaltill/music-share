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
        if (!in_array('email', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER username");
            try { $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_email(email)"); } catch (Throwable $e) {}
        }
        if (!in_array('role', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password_hash");
            $pdo->exec("UPDATE users SET role='admin' ORDER BY id ASC LIMIT 1");
        }
        if (!in_array('is_active', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }


        $albumCols = $pdo->query("SHOW COLUMNS FROM albums")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('owner_user_id', $albumCols, true)) {
            $pdo->exec("ALTER TABLE albums ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER id");
            $firstUser = (int)$pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($firstUser > 0) $pdo->exec("UPDATE albums SET owner_user_id=" . $firstUser . " WHERE owner_user_id IS NULL");
        }
        foreach ([
            'release_year' => "INT UNSIGNED NULL AFTER artist",
            'album_artist' => "VARCHAR(180) NOT NULL DEFAULT '' AFTER artist",
            'genre' => "VARCHAR(150) NOT NULL DEFAULT '' AFTER release_year",
            'label_name' => "VARCHAR(180) NOT NULL DEFAULT '' AFTER genre",
            'copyright_text' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER label_name",
            'deleted_at' => "DATETIME NULL AFTER cover_file"
        ] as $column => $definition) {
            if (!in_array($column, $albumCols, true)) $pdo->exec("ALTER TABLE albums ADD COLUMN {$column} {$definition}");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS album_collaborators (
            album_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(album_id,user_id),
            CONSTRAINT fk_album_collab_album FOREIGN KEY(album_id) REFERENCES albums(id) ON DELETE CASCADE,
            CONSTRAINT fk_album_collab_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS statistics_daily (
            event_date DATE NOT NULL,
            album_id INT UNSIGNED NOT NULL,
            share_id INT UNSIGNED NOT NULL DEFAULT 0,
            track_id INT UNSIGNED NOT NULL DEFAULT 0,
            event_type ENUM('album_view','track_play','track_download','album_download') NOT NULL,
            event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY(event_date,album_id,share_id,track_id,event_type),
            KEY idx_stats_album_date(album_id,event_date),
            KEY idx_stats_type_date(event_type,event_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_password_reset_user(user_id),
            KEY idx_password_reset_expiry(expires_at),
            CONSTRAINT fk_password_reset_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        // Bestehende Installationen bleiben nutzbar, auch wenn der DB-Benutzer keine ALTER-Rechte besitzt.
    }
}
