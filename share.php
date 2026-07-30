<?php
require_once __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
$previewAlbumId=(int)($_GET['album_id']??0);
$isPreview=$previewAlbumId>0;
if($isPreview){
    require_album_access($previewAlbumId);
    $s=$pdo->prepare('SELECT id album_id,title,artist,description,cover_file FROM albums WHERE id=?');
    $s->execute([$previewAlbumId]);
    $album=$s->fetch();
    if(!$album){http_response_code(404);exit('Album nicht gefunden.');}
    $share=[
        'id'=>0,
        'album_id'=>(int)$album['album_id'],
        'title'=>$album['title'],
        'artist'=>$album['artist'],
        'description'=>$album['description'],
        'cover_file'=>$album['cover_file'],
        'allow_download'=>1,
        'password_hash'=>null,
        'expires_at'=>null,
    ];
}else{
    $s=$pdo->prepare('SELECT s.*,a.title,a.artist,a.description,a.cover_file FROM shares s JOIN albums a ON a.id=s.album_id WHERE s.token=?');
    $s->execute([$token]);
    $share=$s->fetch();
    if(!$share||($share['expires_at']&&strtotime($share['expires_at'])<time())){http_response_code(404);exit('Dieser Freigabelink ist ungültig oder abgelaufen.');}
}
if(!$isPreview&&$share['password_hash']&&!($_SESSION['share_ok_'.$share['id']]??false)){if($_SERVER['REQUEST_METHOD']==='POST'&&password_verify($_POST['password']??'',$share['password_hash'])){$_SESSION['share_ok_'.$share['id']]=true;header('Location: '.$_SERVER['REQUEST_URI']);exit;}?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?=base_url('assets/css/app.css')?>"><title>Geschützte Freigabe</title></head><body class="public-album"><main class="container min-vh-100 d-grid align-items-center"><div class="row justify-content-center"><div class="col-md-5"><div class="glass-card p-4"><h1 class="h4">Passwort erforderlich</h1><form method="post"><input type="password" class="form-control my-3" name="password" required><button class="btn btn-light">Album öffnen</button></form></div></div></div></main></body></html><?php exit;}
$s=$pdo->prepare('SELECT * FROM tracks WHERE album_id=? ORDER BY disc_no,track_no,id');$s->execute([$share['album_id']]);$tracks=$s->fetchAll();$cover=$share['cover_file']?base_url('uploads/covers/'.$share['cover_file']):'';
$accessQuery=$isPreview?'album_id='.(int)$share['album_id']:'token='.urlencode($token);
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex"><title><?=e($share['title'])?> – <?=e(app_name())?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.css"><link rel="stylesheet" href="<?=base_url('assets/css/app.css')?>"></head><body class="public-album" data-cover="<?=e($cover)?>"><?php if($cover):?><div class="cover-bg" style="background-image:url('<?=e($cover)?>')"></div><?php endif?><div class="cover-overlay"></div><main class="container py-5 position-relative"><div class="row g-5 align-items-start"><div class="col-md-4 col-lg-4"><?php if($cover):?><img id="coverImage" class="album-cover public-cover shadow-lg" src="<?=e($cover)?>" alt="Cover"><?php else:?><div class="cover-placeholder shadow-lg">♪</div><?php endif?></div><div class="col-md-8 col-lg-7"><h1 class="display-5 fw-bold mb-2"><?=e($share['title'])?></h1><p class="fs-4 opacity-75"><?=e($share['artist'])?></p><?php if($share['description']):?><p class="opacity-75 mb-4"><?=nl2br(e($share['description']))?></p><?php endif?><?php if($share['allow_download']):?><a class="btn btn-contrast mb-4" href="download_album.php?<?=e($accessQuery)?>">Album herunterladen</a><?php endif?>
<div class="track-public-list"><?php $lastDisc=null;foreach($tracks as $t):if($lastDisc!==$t['disc_no']):$lastDisc=$t['disc_no'];if(count(array_unique(array_column($tracks,'disc_no')))>1):?><div class="disc-heading mt-4 mb-2">CD <?=$lastDisc?></div><?php endif;endif;?><div class="public-track" data-row><button class="play-button" data-play data-src="<?=base_url('stream.php?'.$accessQuery.'&track='.$t['id'])?>" data-title="<?=e($t['title'])?>" data-artist="<?=e($share['artist'])?>">▶</button><div class="track-index"><?=str_pad((string)$t['track_no'],2,'0',STR_PAD_LEFT)?></div><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate"><?=e($t['title'])?></div></div><?php if($share['allow_download']):?><a class="download-link" href="download_track.php?<?=e($accessQuery)?>&track=<?=$t['id']?>" aria-label="Titel herunterladen">↓</a><?php endif?></div><?php endforeach?><?php if(!$tracks):?><div class="opacity-75">Noch keine Titel vorhanden.</div><?php endif?></div></div></div></main>
<footer class="public-project-footer position-relative">
  <a href="https://github.com/Wiwaltill/music-share" target="_blank" rel="noopener noreferrer" aria-label="Music Share auf GitHub öffnen">
    <svg class="github-octicon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M8 0C3.58 0 0 3.64 0 8.13c0 3.59 2.29 6.63 5.47 7.71.4.08.55-.18.55-.39 0-.19-.01-.83-.01-1.51-2.01.38-2.53-.5-2.69-.96-.09-.23-.48-.96-.82-1.15-.28-.15-.68-.53-.01-.54.63-.01 1.08.59 1.23.83.72 1.23 1.87.88 2.33.67.07-.53.28-.88.51-1.08-1.78-.21-3.64-.91-3.64-4.02 0-.89.31-1.62.82-2.19-.08-.21-.36-1.04.08-2.16 0 0 .67-.22 2.2.84A7.45 7.45 0 0 1 8 3.91c.68 0 1.36.09 2 .27 1.53-1.06 2.2-.84 2.2-.84.44 1.12.16 1.95.08 2.16.51.57.82 1.3.82 2.19 0 3.12-1.87 3.81-3.65 4.02.29.25.54.74.54 1.5 0 1.08-.01 1.95-.01 2.22 0 .22.15.47.55.39A8.14 8.14 0 0 0 16 8.13C16 3.64 12.42 0 8 0Z"/></svg>
    <span>Music Share · Open Source</span>
  </a>
</footer>
<div id="floatingPlayer" class="floating-player" hidden><div class="player-meta"><img src="<?=e($cover)?>" alt=""><div class="min-w-0"><div id="nowPlaying" class="fw-semibold text-truncate"></div><div class="small opacity-75"><?=e($share['artist'])?></div></div></div><audio id="mainPlayer" controls playsinline></audio><button id="closePlayer" class="player-close" aria-label="Player schließen">×</button></div><script src="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.polyfilled.min.js"></script><script src="<?=base_url('assets/js/player.js')?>"></script></body></html>
