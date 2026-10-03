<?php
require_once __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
$albumId=(int)($_GET['album_id']??0);
if($albumId>0){
    require_album_access($albumId);
    $s=$pdo->prepare('SELECT id album_id,title FROM albums WHERE id=?');
    $s->execute([$albumId]);
    $share=$s->fetch();
}else{
    $s=$pdo->prepare('SELECT s.*,a.title FROM shares s JOIN albums a ON a.id=s.album_id WHERE a.deleted_at IS NULL AND s.token=? AND s.allow_download=1 AND (s.expires_at IS NULL OR s.expires_at>NOW())');
    $s->execute([$token]);
    $share=$s->fetch();
    if(!$share || !share_access_granted($share)){http_response_code(403);exit;}
}
if(!$share){http_response_code(404);exit;}
$s=$pdo->prepare('SELECT * FROM tracks WHERE album_id=? ORDER BY disc_no,track_no,id');
$s->execute([$share['album_id']]);
$tracks=$s->fetchAll();
session_write_close();
if (!$tracks) { http_response_code(404); exit; }
require_once __DIR__.'/includes/album_archive.php';
try {
    $archive = open_cached_album_archive(__DIR__, (int)$share['album_id'], $tracks);
} catch (Throwable $e) {
    error_log('Music Share album archive failed: '.$e->getMessage());
    http_response_code(503);
    header('Retry-After: 5');
    exit(t('download.archive_unavailable'));
}
record_statistic('album_download',(int)$share['album_id'],(int)($share['id']??0));
$name=slugify($share['title']).'.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.fstat($archive)['size']);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try { fpassthru($archive); } finally { fclose($archive); }
