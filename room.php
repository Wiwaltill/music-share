<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/listening_rooms.php';
require_listening_rooms();
$token=(string)($_GET['token'] ?? '');
$room=public_room($token);
if (!$room) { http_response_code(404); exit(t('rooms.unavailable')); }
$error='';
if (!room_access_granted($room)) {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        try { $result=verify_share_password($room,(string)($_POST['password'] ?? ''),'room'); }
        catch(Throwable $e) { error_log('Listening room password check failed: '.$e->getMessage()); http_response_code(503); $result='unavailable'; }
        if ($result==='granted') redirect('room.php?token='.rawurlencode($token));
        if ($result==='limited') { http_response_code(429); header('Retry-After: 900'); }
        $error=t($result==='limited'?'security.login_limited':($result==='unavailable'?'share.password_unavailable':'security.login_failed'));
    }
    render_header($room['title'], false, 'listening-room-page');
    ?>
    <div class="row justify-content-center py-5"><div class="col-md-6 col-lg-4"><div class="card shadow-sm"><div class="card-body p-4"><div class="small text-body-secondary mb-2">Listening Room</div><h1 class="h4"><?=e($room['title'])?></h1><p><?=e(t('text.passwort.erforderlich'))?></p><?php if($error):?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label class="visually-hidden" for="roomPassword"><?=e(t('text.passwort'))?></label><input id="roomPassword" class="form-control mb-3" type="password" name="password" required autocomplete="current-password"><button class="btn btn-primary w-100"><?=e(t('text.album.offnen'))?></button></form></div></div></div></div>
    <?php render_footer(); exit;
}
$tracks=room_tracks($room);
render_header($room['title'], false, 'listening-room-page');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.css">
<?php
$artwork=[];foreach($tracks as $item){if(!isset($artwork[$item['album_id']]))$artwork[$item['album_id']]=$item['cover_file'];}
$albumCount=count($artwork);
?>
<div class="room-public mx-auto">
<header class="room-hero"><div class="room-artwork" aria-hidden="true">
<?php $covers=array_slice(array_values($artwork),0,4);while(count($covers)<4)$covers[]=null;foreach($covers as $image):?>
<?php if($image):?><img src="<?=e(base_url('uploads/covers/'.rawurlencode(basename((string)$image))))?>" alt="" width="160" height="160"><?php else:?><span>♪</span><?php endif?>
<?php endforeach?></div>
<div class="room-hero-copy"><div class="room-eyebrow"><i class="bi bi-headphones" aria-hidden="true"></i> Listening Room</div><h1><?=e($room['title'])?></h1><?php if($room['description']):?><p class="room-message"><?=nl2br(e($room['description']))?></p><?php endif?><div class="room-meta"><span><?=count($tracks)?> <?=e(t('text.titel'))?></span><span><?=$albumCount?> <?=e(t('albums'))?></span></div>
<?php if($tracks):?><button type="button" class="btn room-play-all" data-room-play><i class="bi bi-play-fill" aria-hidden="true"></i> <span><?=e(t('rooms.play_all'))?></span></button><?php endif?>
<?php if($tracks && !empty($room['allow_room_download'])):?><a class="btn btn-outline-secondary rounded-pill ms-2" href="<?=e(base_url('room_download_all.php?token='.rawurlencode($token)))?>"><i class="bi bi-download me-1" aria-hidden="true"></i><?=e(t('rooms.download_all'))?></a><?php endif?>
</div></header>
<div class="room-tracklist"><div class="room-list-heading"><h2><?=e(t('rooms.tracklist'))?></h2><span><?=e(t('rooms.listen_hint'))?></span></div>
<?php $trackIndex=0;?>
<?php foreach($tracks as $track):$cover=$track['cover_file']?base_url('uploads/covers/'.rawurlencode(basename((string)$track['cover_file']))):'';?>
<div class="room-list-track" data-row data-track-id="<?=(int)$track['id']?>">
<span class="room-track-number"><?=str_pad((string)++$trackIndex,2,'0',STR_PAD_LEFT)?></span><?php if($cover):?><img src="<?=e($cover)?>" width="56" height="56" class="rounded room-track-cover" alt="" loading="lazy"><?php else:?><span class="room-track-placeholder rounded" aria-hidden="true">♪</span><?php endif?>
<button type="button" class="play-button flex-shrink-0" data-play data-src="<?=e(base_url('room_stream.php?token='.rawurlencode($token).'&track='.(int)$track['id']))?>" data-title="<?=e($track['title'])?>" data-artist="<?=e($track['artist'])?>" data-cover="<?=e($cover)?>" aria-label="<?=e(t('text.titel.abspielen'))?>">▶</button>
<div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate"><?=e($track['title'])?></div><div class="small text-body-secondary text-truncate"><?=e($track['artist'])?> · <?=e($track['album_title'])?></div></div>
<?php if($room['allow_download']):?><a class="btn btn-outline-secondary btn-sm flex-shrink-0" href="<?=e(base_url('room_download.php?token='.rawurlencode($token).'&track='.(int)$track['id']))?>" aria-label="<?=e(t('text.titel.herunterladen'))?>"><i class="bi bi-download" aria-hidden="true"></i></a><?php endif?>
</div><?php endforeach?>
<?php if(!$tracks):?><div class="p-4 text-body-secondary"><?=e(t('rooms.no_tracks'))?></div><?php endif?>
</div><footer class="room-credit">Music Share · <?=e(t('rooms.personal_selection'))?></footer></div>
<div id="floatingPlayer" class="floating-player" hidden><div class="player-meta"><img data-room-now-cover alt="" hidden><div class="min-w-0"><div id="nowPlaying" class="fw-semibold text-truncate"></div><div class="small opacity-75 text-truncate" data-room-now-artist></div></div></div><audio id="mainPlayer" controls playsinline></audio><button id="closePlayer" class="player-close" type="button" aria-label="<?=e(t('text.player.schlieen'))?>">×</button></div>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.polyfilled.min.js"></script>
<script src="<?=e(asset_url('assets/js/room-public.js'))?>"></script>
<?php render_footer();
