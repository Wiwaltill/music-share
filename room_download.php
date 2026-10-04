<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/listening_rooms.php';
require_listening_rooms();
$track = room_media_track((string)($_GET['token'] ?? ''), (int)($_GET['track'] ?? 0), true);
$file = __DIR__.'/uploads/audio/'.basename((string)$track['audio_file']);
if (!is_file($file)) { http_response_code(404); exit; }
session_write_close();
$name=basename((string)$track['original_name']);
$fallback=preg_replace('/[^A-Za-z0-9._-]/','_',$name) ?: 'audio-download';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($name));
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
