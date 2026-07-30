<?php
require_once __DIR__.'/../includes/bootstrap.php';require_login();header('Content-Type: application/json; charset=utf-8');
try{verify_csrf();$id=(int)($_POST['id']??0);$s=$pdo->prepare('SELECT audio_file,album_id FROM tracks WHERE id=?');$s->execute([$id]);$t=$s->fetch();if($t){require_album_access((int)$t['album_id']);@unlink(dirname(__DIR__).'/uploads/audio/'.$t['audio_file']);$pdo->prepare('DELETE FROM tracks WHERE id=?')->execute([$id]);}echo json_encode(['ok'=>true]);}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
