<?php
require_once __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
$trackId=(int)($_GET['track']??0);
$albumId=(int)($_GET['album_id']??0);
if($albumId>0){
    require_login();
    $s=$pdo->prepare('SELECT t.* FROM tracks t WHERE t.album_id=? AND t.id=?');
    $s->execute([$albumId,$trackId]);
    $row=$s->fetch();
}else{
    $s=$pdo->prepare('SELECT s.id share_id,s.password_hash,s.expires_at,s.allow_download,t.* FROM tracks t JOIN shares s ON s.album_id=t.album_id WHERE s.token=? AND t.id=?');
    $s->execute([$token,$trackId]);
    $row=$s->fetch();
    if(!$row || !$row['allow_download'] || !share_access_granted(['id'=>$row['share_id'],'password_hash'=>$row['password_hash'],'expires_at'=>$row['expires_at']])){http_response_code(403);exit;}
}
if(!$row){http_response_code(404);exit;}
$file=__DIR__.'/uploads/audio/'.$row['audio_file'];
if(!is_file($file)){http_response_code(404);exit;}
$downloadName=basename((string)$row['original_name']);
$fallback=preg_replace('/[^A-Za-z0-9._-]/','_',$downloadName) ?: 'audio-download';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.str_replace('"','',$fallback).'"; filename*=UTF-8\'\''.rawurlencode($downloadName));
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
