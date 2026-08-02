<?php
require_once __DIR__.'/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'||!statistics_enabled()){echo '{"ok":true}';exit;}
$input=json_decode(file_get_contents('php://input'),true)?:[];
$type=(string)($input['type']??'');$trackId=(int)($input['track_id']??0);$token=(string)($input['token']??'');$albumId=(int)($input['album_id']??0);$shareId=0;
if($token!==''){$q=$pdo->prepare('SELECT id,album_id,password_hash,expires_at FROM shares WHERE token=?');$q->execute([$token]);$s=$q->fetch();if(!$s||!share_access_granted($s)){echo '{"ok":false}';exit;}$albumId=(int)$s['album_id'];$shareId=(int)$s['id'];}
elseif($albumId>0){if(!can_access_album($albumId)){echo '{"ok":false}';exit;}}
if($trackId>0){$q=$pdo->prepare('SELECT 1 FROM tracks WHERE id=? AND album_id=?');$q->execute([$trackId,$albumId]);if(!$q->fetchColumn())$trackId=0;}
record_statistic($type,$albumId,$shareId,$trackId);echo '{"ok":true}';