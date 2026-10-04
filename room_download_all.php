<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/listening_rooms.php';
require_listening_rooms();
$room=public_room((string)($_GET['token'] ?? ''));
if (!$room || !room_access_granted($room) || empty($room['allow_room_download'])) { http_response_code(403); exit; }
$tracks=room_tracks($room);
if (!$tracks) { http_response_code(404); exit; }
session_write_close();
require_once __DIR__.'/includes/album_archive.php';
// Number the selected songs in listening order; source albums and files remain unchanged.
foreach($tracks as $index=>&$track) {
    $name=basename(str_replace('\\','/',(string)$track['original_name']));
    if ($name==='' || $name==='.' || $name==='..') $name=basename((string)$track['audio_file']);
    $track['original_name']=str_pad((string)($index+1),3,'0',STR_PAD_LEFT).' - '.$name;
    $track['disc_no']=1; $track['track_no']=$index+1;
}
unset($track);
try { $archive=open_cached_album_archive(__DIR__,(int)$room['id'],$tracks,'room'); }
catch(Throwable $e) {
    error_log('Room ZIP failed: '.$e->getMessage()); http_response_code(503); header('Retry-After: 5'); exit(t('download.archive_unavailable'));
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.slugify($room['title']).'.zip"');
header('Content-Length: '.fstat($archive)['size']);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try { fpassthru($archive); } finally { fclose($archive); }
