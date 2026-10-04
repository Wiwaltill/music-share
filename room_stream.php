<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/listening_rooms.php';
require_listening_rooms();
$track = room_media_track((string)($_GET['token'] ?? ''), (int)($_GET['track'] ?? 0));
$file = __DIR__.'/uploads/audio/'.basename((string)$track['audio_file']);
if (!is_file($file)) { app_error(404); }
session_write_close();
require_once __DIR__.'/includes/audio_response.php';
serve_audio_file($file);
