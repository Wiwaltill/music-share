<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare('SELECT a.cover_file, s.expires_at FROM shares s JOIN albums a ON a.id=s.album_id WHERE s.token=? LIMIT 1');
$stmt->execute([$token]);
$share = $stmt->fetch();
if (!$share || empty($share['cover_file']) || ($share['expires_at'] && strtotime((string)$share['expires_at']) < time())) {
    http_response_code(404);
    exit;
}

$filename = basename((string)$share['cover_file']);
$path = __DIR__ . '/uploads/covers/' . $filename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$mime = function_exists('mime_content_type') ? (string)mime_content_type($path) : '';
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit;
}

header_remove('X-Robots-Tag');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
