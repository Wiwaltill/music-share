<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    app_error(404);
}

$stmt = $pdo->prepare('SELECT a.cover_file, s.expires_at FROM shares s JOIN albums a ON a.id=s.album_id WHERE a.deleted_at IS NULL AND s.token=? LIMIT 1');
$stmt->execute([$token]);
$share = $stmt->fetch();
if (!$share || empty($share['cover_file']) || ($share['expires_at'] && strtotime((string)$share['expires_at']) <= time())) {
    app_error(404);
}

$filename = basename((string)$share['cover_file']);
$path = __DIR__ . '/uploads/covers/' . $filename;
if (!is_file($path) || !is_readable($path)) {
    app_error(404);
}

$mime = function_exists('mime_content_type') ? (string)mime_content_type($path) : '';
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    app_error(415);
}

header_remove('X-Robots-Tag');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
session_write_close();
readfile($path);
