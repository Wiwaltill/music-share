<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/migrations.php';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=music_share_test', 'root', 'test', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$schema = file_get_contents(__DIR__ . '/../install/schema.sql');
$pdo->exec($schema);
run_migrations($pdo);
if (migration_version($pdo) !== MUSIC_SHARE_SCHEMA_VERSION) throw new RuntimeException('Missing version marker');
// Upgrading version 1 creates add-on tables without enabling the add-on.
$pdo->exec('DROP TABLE listening_room_tracks');
$pdo->exec('DROP TABLE listening_rooms');
$pdo->exec("UPDATE settings SET setting_value='1' WHERE setting_key='schema_version'");
run_migrations($pdo);
$pdo->query('SELECT 1 FROM listening_rooms LIMIT 1');
$pdo->query('SELECT 1 FROM listening_room_tracks LIMIT 1');
if (migration_version($pdo) !== MUSIC_SHARE_SCHEMA_VERSION) throw new RuntimeException('Room migration version missing');
// Simulate an older database, including absence of the settings table.
$pdo->exec('DROP TABLE settings');
$pdo->exec('ALTER TABLE tracks DROP COLUMN duration_seconds, DROP COLUMN disc_no');
run_migrations($pdo);
$columns = $pdo->query('SHOW COLUMNS FROM tracks')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('duration_seconds', $columns, true) || !in_array('disc_no', $columns, true)) {
    throw new RuntimeException('Legacy migration failed');
}
// The current version must bypass schema inspection, even if a table is unavailable.
$pdo->exec('RENAME TABLE tracks TO tracks_hidden');
run_migrations($pdo);
$pdo->exec("DELETE FROM settings WHERE setting_key='schema_version'");
try {
    run_migrations($pdo);
    throw new RuntimeException('Migration failure was swallowed');
} catch (PDOException $expected) {
    if (migration_version($pdo) !== 0) throw new RuntimeException('Failed migration was marked complete');
}
$pdo->exec('RENAME TABLE tracks_hidden TO tracks');
run_migrations($pdo);
if (migration_version($pdo) !== MUSIC_SHARE_SCHEMA_VERSION) throw new RuntimeException('Retry failed');
echo "Migration tests passed.\n";
