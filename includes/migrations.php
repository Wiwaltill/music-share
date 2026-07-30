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


        $albumCols = $pdo->query("SHOW COLUMNS FROM albums")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('owner_user_id', $albumCols, true)) {
            $pdo->exec("ALTER TABLE albums ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER id");
            $firstUser = (int)$pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($firstUser > 0) $pdo->exec("UPDATE albums SET owner_user_id=" . $firstUser . " WHERE owner_user_id IS NULL");
        }
        foreach ([
            'release_year' => "INT UNSIGNED NULL AFTER artist",
            'genre' => "VARCHAR(150) NOT NULL DEFAULT '' AFTER release_year",
            'label_name' => "VARCHAR(180) NOT NULL DEFAULT '' AFTER genre",
            'copyright_text' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER label_name"
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
    } catch (Throwable $e) {
        // Bestehende Installationen bleiben nutzbar, auch wenn der DB-Benutzer keine ALTER-Rechte besitzt.
    }
}
