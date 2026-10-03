<?php
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
if (!is_logged_in() || !current_user()) {
    http_response_code(401);
    echo json_encode(['error'=>'authentication_required']);
    exit;
}
$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 200);
if ($search === '') { echo json_encode(['albums'=>[]]); exit; }
[$access, $args] = accessible_album_condition('a');
// Treat SQL wildcard characters as literal search text.
$like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
$stmt = $pdo->prepare("SELECT a.id,a.title,a.artist,a.cover_file FROM albums a WHERE a.deleted_at IS NULL AND {$access} AND (a.title LIKE ? ESCAPE '!' OR a.artist LIKE ? ESCAPE '!') ORDER BY a.created_at DESC,a.id DESC LIMIT 8");
$stmt->execute([...$args, $like, $like]);
$albums = array_map(static fn(array $album): array => [
    'title'=>(string)$album['title'],
    'artist'=>(string)$album['artist'],
    'url'=>base_url('admin/album_edit.php?id='.(int)$album['id']),
    'cover'=>$album['cover_file'] ? base_url('uploads/covers/'.rawurlencode(basename((string)$album['cover_file']))) : null,
], $stmt->fetchAll());
session_write_close();
echo json_encode(['albums'=>$albums], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
