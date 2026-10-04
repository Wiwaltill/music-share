<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
$stmt=$pdo->prepare('SELECT c.*,t.audio_file,t.album_id FROM track_comments c JOIN tracks t ON t.id=c.track_id WHERE c.id=?');
$stmt->execute([(int)($_GET['comment_id'] ?? 0)]);
$comment=$stmt->fetch();
if (!$comment) app_error(404);
if ($comment['room_id']) {
    require_once __DIR__.'/../includes/listening_rooms.php';
    $room=room_for_management((int)$comment['room_id']);
    $tracks=room_tracks($room);
    if (!in_array((int)$comment['track_id'],array_map('intval',array_column($tracks,'id')),true)) app_error(404);
} else {
    require_album_access((int)$comment['album_id']);
}
$file=dirname(__DIR__).'/uploads/audio/'.basename((string)$comment['audio_file']);
if (!is_file($file)) app_error(404);
session_write_close();
require_once __DIR__.'/../includes/audio_response.php';
serve_audio_file($file);
