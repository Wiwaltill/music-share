<?php
require_once __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
$trackId=(int)($_GET['track']??0);
$albumId=(int)($_GET['album_id']??0);
if($albumId>0){
    require_album_access($albumId);
    $s=$pdo->prepare('SELECT t.* FROM tracks t WHERE t.album_id=? AND t.id=?');
    $s->execute([$albumId,$trackId]);
    $row=$s->fetch();
}else{
    $s=$pdo->prepare('SELECT s.id share_id,s.password_hash,s.expires_at,s.allow_download,t.* FROM tracks t JOIN shares s ON s.album_id=t.album_id JOIN albums a ON a.id=t.album_id WHERE a.deleted_at IS NULL AND s.token=? AND t.id=?');
    $s->execute([$token,$trackId]);
    $row=$s->fetch();
    if(!$row || !$row['allow_download'] || !share_access_granted(['id'=>$row['share_id'],'password_hash'=>$row['password_hash'],'expires_at'=>$row['expires_at']])){app_error(403);}
}
if(!$row){app_error(404);}
$file=__DIR__.'/uploads/audio/'.$row['audio_file'];
if(!is_file($file)){app_error(404);}
record_statistic('track_download',(int)$row['album_id'],(int)($row['share_id']??0),$trackId);
session_write_close();
$downloadName=basename((string)$row['original_name']);
$fallback=preg_replace('/[^A-Za-z0-9._-]/','_',$downloadName) ?: 'audio-download';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.str_replace('"','',$fallback).'"; filename*=UTF-8\'\''.rawurlencode($downloadName));
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
