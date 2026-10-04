<?php
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
session_start();
$root = dirname(__DIR__);
$configFile = $root . '/config.php';
if (!is_file($configFile)) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!str_contains($script, '/install/')) {
        header('Location: install/');
        exit;
    }
    return;
}
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Berlin');
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/functions.php';
set_exception_handler(static function(Throwable $error):void {
    error_log('Music Share request failed: '.$error->getMessage());
    app_error(500);
});
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['db']['host'], $config['db']['port'], $config['db']['name'], $config['db']['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Music Share database connection failed: '.$e->getMessage());
    app_error(503);
}

require_once __DIR__.'/migrations.php';
try {
    run_migrations($pdo);
} catch (Throwable $e) {
    error_log('Music Share migration failed: ' . $e->getMessage());
    header('Retry-After: 60');
    app_error(503);
}
sync_user_session();

