<?php
require_once __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
$previewAlbumId=(int)($_GET['album_id']??0);
$isPreview=$previewAlbumId>0;
if($isPreview){
    require_album_access($previewAlbumId);
    $s=$pdo->prepare('SELECT id album_id,title,artist,album_artist,release_year,genre,label_name,copyright_text,description,cover_file FROM albums WHERE id=?');
    $s->execute([$previewAlbumId]);
    $album=$s->fetch();
    if(!$album){http_response_code(404);exit('Album nicht gefunden.');}
    $share=[
        'id'=>0,
        'album_id'=>(int)$album['album_id'],
        'title'=>$album['title'],
        'artist'=>$album['artist'],
        'release_year'=>$album['release_year'],'genre'=>$album['genre'],'label_name'=>$album['label_name'],'copyright_text'=>$album['copyright_text'],
        'description'=>$album['description'],
        'cover_file'=>$album['cover_file'],
        'allow_download'=>1,
        'password_hash'=>null,
        'expires_at'=>null,
    ];
}else{
    $s=$pdo->prepare('SELECT s.*,a.title,a.artist,a.album_artist,a.release_year,a.genre,a.label_name,a.copyright_text,a.description,a.cover_file FROM shares s JOIN albums a ON a.id=s.album_id WHERE s.token=?');
    $s->execute([$token]);
    $share=$s->fetch();
    if(!$share||($share['expires_at']&&strtotime($share['expires_at'])<time())){http_response_code(404);exit('Dieser Freigabelink ist ungültig oder abgelaufen.');}
}
$socialTitle = trim((string)$share['title']) . ' – ' . trim((string)$share['artist']);
$socialDescription = trim((string)($share['description'] ?? ''));
if ($socialDescription === '') $socialDescription = 'Listen to ' . trim((string)$share['title']) . ' by ' . trim((string)$share['artist']) . '.';
$socialUrl = $isPreview ? base_url('share.php?album_id='.(int)$share['album_id']) : base_url('s/'.rawurlencode($token));
$socialImage = (!$isPreview && !empty($share['cover_file'])) ? base_url('social_cover.php?token='.rawurlencode($token)) : '';
$ogTags = '<meta property="og:type" content="music.album">'
    . '<meta property="og:site_name" content="'.e(app_name()).'">'
    . '<meta property="og:title" content="'.e($socialTitle).'">'
    . '<meta property="og:description" content="'.e($socialDescription).'">'
    . '<meta property="og:url" content="'.e($socialUrl).'">'
    . ($socialImage !== '' ? '<meta property="og:image" content="'.e($socialImage).'"><meta property="og:image:alt" content="Cover von '.e((string)$share['title']).'">' : '')
    . '<meta name="twitter:card" content="'.($socialImage !== '' ? 'summary_large_image' : 'summary').'">'
    . '<meta name="twitter:title" content="'.e($socialTitle).'">'
    . '<meta name="twitter:description" content="'.e($socialDescription).'">'
    . ($socialImage !== '' ? '<meta name="twitter:image" content="'.e($socialImage).'">' : '');
if(!$isPreview&&$share['password_hash']&&!($_SESSION['share_ok_'.$share['id']]??false)){if($_SERVER['REQUEST_METHOD']==='POST'&&password_verify($_POST['password']??'',$share['password_hash'])){$_SESSION['share_ok_'.$share['id']]=true;header('Location: '.$_SERVER['REQUEST_URI']);exit;}?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex"><?=$ogTags?><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?=base_url('assets/css/app.css')?>"><title>Geschützte Freigabe</title></head><body class="public-album"><main class="container min-vh-100 d-grid align-items-center"><div class="row justify-content-center"><div class="col-md-5"><div class="glass-card p-4"><h1 class="h4">Passwort erforderlich</h1><form method="post"><input type="password" class="form-control my-3" name="password" required><button class="btn btn-light">Album öffnen</button></form></div></div></div></main></body></html><?php exit;}
$s=$pdo->prepare('SELECT * FROM tracks WHERE album_id=? ORDER BY disc_no,track_no,id');$s->execute([$share['album_id']]);$tracks=$s->fetchAll();music_share_backfill_track_durations($pdo,$tracks);$totalSeconds=array_sum(array_map(fn($t)=>(int)($t['duration_seconds']??0),$tracks));$hours=intdiv($totalSeconds,3600);$minutes=intdiv($totalSeconds%3600,60);$seconds=$totalSeconds%60;$durationText=$hours>0?sprintf('%d:%02d:%02d',$hours,$minutes,$seconds):sprintf('%d:%02d',$minutes,$seconds);$cover=$share['cover_file']?base_url('uploads/covers/'.$share['cover_file']):'';
$accessQuery=$isPreview?'album_id='.(int)$share['album_id']:'token='.urlencode($token);
?><!doctype html><html lang="<?=e(current_language())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex"><?=$ogTags?><title><?=e($share['title'])?> – <?=e(app_name())?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.css"><link rel="stylesheet" href="<?=base_url('assets/css/app.css')?>"></head><body class="public-album" data-cover="<?=e($cover)?>" data-album-colors="<?=get_setting('album_colors_enabled','0')==='1'?'1':'0'?>"><?php if($cover):?><div class="cover-bg" style="background-image:url('<?=e($cover)?>')"></div><?php endif?><div class="cover-overlay"></div><main class="container py-5 position-relative"><div class="row g-5 align-items-start"><div class="col-md-4 col-lg-4"><?php if($cover):?><button class="cover-zoom-button" type="button" data-bs-toggle="modal" data-bs-target="#coverModal" aria-label="Cover vergrößern"><img id="coverImage" class="album-cover public-cover shadow-lg" src="<?=e($cover)?>" alt="Cover"></button><?php else:?><div class="cover-placeholder shadow-lg">♪</div><?php endif?></div><div class="col-md-8 col-lg-7"><h1 class="display-5 fw-bold mb-2"><?=e($share['title'])?></h1><p class="fs-4 opacity-75"><?=e($share['artist'])?></p><?php $meta=array_filter([$share['release_year']??'', $share['genre']??'', $share['label_name']??'']); if($meta):?><div class="album-meta mb-3"><?=e(implode(' · ',$meta))?></div><?php endif?><?php if(!empty($share['copyright_text'])):?><div class="small opacity-75 mb-3"><?=e($share['copyright_text'])?></div><?php endif?><?php if($share['description']):?><p class="opacity-75 mb-4"><?=nl2br(e($share['description']))?></p><?php endif?><div class="small opacity-75 mb-3"><?=count($tracks)?> <?=current_language()==='en'?'tracks':(current_language()==='fr'?'pistes':'Titel')?><?php if($totalSeconds>0):?> · <?=e($durationText)?><?php endif?></div><div class="d-flex flex-wrap gap-2 mb-4"><?php if($share['allow_download']):?><a class="btn btn-contrast" href="<?=e(base_url('download_album.php?'.$accessQuery))?>">Album herunterladen</a><?php endif?><button class="btn btn-contrast" type="button" id="shareAlbumButton" data-share-url="<?=e($socialUrl)?>" data-share-title="<?=e($socialTitle)?>"><i class="bi bi-share me-1"></i>Teilen</button></div>
<div class="track-public-list"><?php $lastDisc=null;foreach($tracks as $t):if($lastDisc!==$t['disc_no']):$lastDisc=$t['disc_no'];if(count(array_unique(array_column($tracks,'disc_no')))>1):?><div class="disc-heading mt-4 mb-2">CD <?=$lastDisc?></div><?php endif;endif;?><div class="public-track" data-row><button class="play-button" data-play data-src="<?=base_url('stream.php?'.$accessQuery.'&track='.$t['id'])?>" data-title="<?=e($t['title'])?>" data-artist="<?=e($share['artist'])?>" data-cover="<?=e($cover)?>">▶</button><div class="track-index"><?=str_pad((string)$t['track_no'],2,'0',STR_PAD_LEFT)?></div><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate"><?=e($t['title'])?></div></div><?php if($share['allow_download']):?><a class="download-link" href="<?=e(base_url('download_track.php?'.$accessQuery.'&track='.$t['id']))?>" aria-label="Titel herunterladen">↓</a><?php endif?></div><?php endforeach?><?php if(!$tracks):?><div class="opacity-75">Noch keine Titel vorhanden.</div><?php endif?></div></div></div></main>
<footer class="public-project-footer position-relative">
  <a href="https://github.com/Wiwaltill/music-share" target="_blank" rel="noopener noreferrer" aria-label="Music Share auf GitHub öffnen">
    <svg class="github-octicon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M8 0C3.58 0 0 3.64 0 8.13c0 3.59 2.29 6.63 5.47 7.71.4.08.55-.18.55-.39 0-.19-.01-.83-.01-1.51-2.01.38-2.53-.5-2.69-.96-.09-.23-.48-.96-.82-1.15-.28-.15-.68-.53-.01-.54.63-.01 1.08.59 1.23.83.72 1.23 1.87.88 2.33.67.07-.53.28-.88.51-1.08-1.78-.21-3.64-.91-3.64-4.02 0-.89.31-1.62.82-2.19-.08-.21-.36-1.04.08-2.16 0 0 .67-.22 2.2.84A7.45 7.45 0 0 1 8 3.91c.68 0 1.36.09 2 .27 1.53-1.06 2.2-.84 2.2-.84.44 1.12.16 1.95.08 2.16.51.57.82 1.3.82 2.19 0 3.12-1.87 3.81-3.65 4.02.29.25.54.74.54 1.5 0 1.08-.01 1.95-.01 2.22 0 .22.15.47.55.39A8.14 8.14 0 0 0 16 8.13C16 3.64 12.42 0 8 0Z"/></svg>
    <span>Music Share · Open Source</span>
  </a>
</footer>
<?php if($cover):?><div class="modal fade" id="coverModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content bg-transparent border-0"><div class="modal-body p-0 text-center position-relative"><button type="button" class="btn-close btn-close-white cover-modal-close" data-bs-dismiss="modal" aria-label="Schließen"></button><img class="cover-modal-image" src="<?=e($cover)?>" alt="Cover groß"></div></div></div></div><?php endif?>
<div id="floatingPlayer" class="floating-player" hidden><div class="player-meta"><img src="<?=e($cover)?>" alt=""><div class="min-w-0"><div id="nowPlaying" class="fw-semibold text-truncate"></div><div class="small opacity-75"><?=e($share['artist'])?></div></div></div><audio id="mainPlayer" controls playsinline></audio><button id="closePlayer" class="player-close" aria-label="Player schließen">×</button></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.polyfilled.min.js"></script><script src="<?=base_url('assets/js/player.js?v='.rawurlencode(APP_VERSION))?>"></script><script src="<?=base_url('assets/js/album-colors.js?v='.rawurlencode(APP_VERSION))?>"></script></body></html>
