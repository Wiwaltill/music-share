<?php
require_once __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
$albumId=(int)($_GET['album_id']??0);
if($albumId>0){
    require_login();
    $s=$pdo->prepare('SELECT id album_id,title FROM albums WHERE id=?');
    $s->execute([$albumId]);
    $share=$s->fetch();
}else{
    $s=$pdo->prepare('SELECT s.*,a.title FROM shares s JOIN albums a ON a.id=s.album_id WHERE s.token=? AND s.allow_download=1 AND (s.expires_at IS NULL OR s.expires_at>NOW())');
    $s->execute([$token]);
    $share=$s->fetch();
    if(!$share || !share_access_granted($share)){http_response_code(403);exit;}
}
if(!$share){http_response_code(404);exit;}
$s=$pdo->prepare('SELECT * FROM tracks WHERE album_id=? ORDER BY disc_no,track_no,id');
$s->execute([$share['album_id']]);
$tracks=$s->fetchAll();
$tmp=tempnam(sys_get_temp_dir(),'album_');
$zip=new ZipArchive();
if($zip->open($tmp,ZipArchive::OVERWRITE)!==true){http_response_code(500);exit('ZIP konnte nicht erstellt werden.');}
$discCount=count(array_unique(array_map(static fn($t)=>(int)$t['disc_no'],$tracks)));
$used=[];
foreach($tracks as $t){
    $file=__DIR__.'/uploads/audio/'.$t['audio_file'];
    if(!is_file($file)) continue;
    $original=basename((string)$t['original_name']);
    if($original==='') $original=basename((string)$t['audio_file']);
    $entry=($discCount>1?'CD '.max(1,(int)$t['disc_no']).'/':'').$original;
    // Preserve the uploaded filename. Only identical duplicate names receive a folder suffix to avoid data loss.
    if(isset($used[$entry])){
        $n=2;
        $prefix=($discCount>1?'CD '.max(1,(int)$t['disc_no']).'/':'').'Duplikat '.$n.'/';
        while(isset($used[$prefix.$original])){$n++;$prefix=($discCount>1?'CD '.max(1,(int)$t['disc_no']).'/':'').'Duplikat '.$n.'/';}
        $entry=$prefix.$original;
    }
    $used[$entry]=true;
    $zip->addFile($file,$entry);
}
$zip->close();
$name=slugify($share['title']).'.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.filesize($tmp));
header('X-Content-Type-Options: nosniff');
readfile($tmp);
@unlink($tmp);
