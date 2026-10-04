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
    render_header($room['title']);
    ?>
    <div class="row justify-content-center py-5"><div class="col-md-6 col-lg-4"><div class="card shadow-sm"><div class="card-body p-4"><div class="small text-body-secondary mb-2">Listening Room</div><h1 class="h4"><?=e($room['title'])?></h1><p><?=e(t('text.passwort.erforderlich'))?></p><?php if($error):?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label class="visually-hidden" for="roomPassword"><?=e(t('text.passwort'))?></label><input id="roomPassword" class="form-control mb-3" type="password" name="password" required autocomplete="current-password"><button class="btn btn-primary w-100"><?=e(t('text.album.offnen'))?></button></form></div></div></div></div>
    <?php render_footer(); exit;
}
$tracks=room_tracks($room);
render_header($room['title']);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.css">
<div class="room-public mx-auto py-4">
<div class="mb-4"><span class="badge text-bg-primary mb-3">Listening Room</span><h1 class="display-5 fw-bold"><?=e($room['title'])?></h1><?php if($room['description']):?><p class="fs-5 text-body-secondary"><?=nl2br(e($room['description']))?></p><?php endif?><p class="small text-body-secondary"><?=count($tracks)?> <?=e(t('text.titel'))?></p></div>
<div class="card shadow-sm"><div class="list-group list-group-flush">
<?php foreach($tracks as $track):$cover=$track['cover_file']?base_url('uploads/covers/'.rawurlencode(basename((string)$track['cover_file']))):'';?>
<div class="list-group-item d-flex align-items-center gap-3 py-3" data-row data-track-id="<?=(int)$track['id']?>">
<?php if($cover):?><img src="<?=e($cover)?>" width="56" height="56" class="rounded room-track-cover" alt="" loading="lazy"><?php else:?><span class="room-track-placeholder rounded" aria-hidden="true">♪</span><?php endif?>
<button type="button" class="play-button flex-shrink-0" data-play data-src="<?=e(base_url('room_stream.php?token='.rawurlencode($token).'&track='.(int)$track['id']))?>" data-title="<?=e($track['title'])?>" data-artist="<?=e($track['artist'])?>" data-cover="<?=e($cover)?>" aria-label="<?=e(t('text.titel.abspielen'))?>">▶</button>
<div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate"><?=e($track['title'])?></div><div class="small text-body-secondary text-truncate"><?=e($track['artist'])?> · <?=e($track['album_title'])?></div></div>
<?php if($room['allow_download']):?><a class="btn btn-outline-secondary btn-sm flex-shrink-0" href="<?=e(base_url('room_download.php?token='.rawurlencode($token).'&track='.(int)$track['id']))?>" aria-label="<?=e(t('text.titel.herunterladen'))?>"><i class="bi bi-download" aria-hidden="true"></i></a><?php endif?>
</div><?php endforeach?>
<?php if(!$tracks):?><div class="list-group-item py-4 text-body-secondary"><?=e(t('rooms.no_tracks'))?></div><?php endif?>
</div></div></div>
<div id="floatingPlayer" class="floating-player" hidden><div class="player-meta"><div><div id="nowPlaying" class="fw-semibold text-truncate"></div></div></div><audio id="mainPlayer" controls playsinline></audio><button id="closePlayer" class="player-close" type="button" aria-label="<?=e(t('text.player.schlieen'))?>">×</button></div>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.polyfilled.min.js"></script>
<?php render_footer();
