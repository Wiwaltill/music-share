<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/listening_rooms.php';
require_login(); require_listening_rooms();
$id = (int)($_GET['id'] ?? 0);
$room = $id ? room_for_management($id) : ['title'=>'','description'=>'','expires_at'=>null,'password_hash'=>null,'allow_download'=>0,'allow_room_download'=>0,'owner_user_id'=>(int)current_user()['id']];
// Even admins select only tracks available to the room's owner.
$stmt = $pdo->prepare("SELECT t.id,t.title,a.id album_id,a.title album_title,a.artist FROM tracks t JOIN albums a ON a.id=t.album_id JOIN users u ON u.id=? AND u.is_active=1 WHERE a.deleted_at IS NULL AND (u.role='admin' OR a.owner_user_id=u.id OR EXISTS(SELECT 1 FROM album_collaborators c WHERE c.album_id=a.id AND c.user_id=u.id)) ORDER BY a.title,a.id,t.disc_no,t.track_no,t.id");
$stmt->execute([(int)$room['owner_user_id']]); $available=$stmt->fetchAll();
$selected = $id ? array_map('intval', $pdo->query('SELECT track_id FROM listening_room_tracks WHERE room_id='.$id.' ORDER BY position')->fetchAll(PDO::FETCH_COLUMN)) : [];
$error='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete' && $id) {
        $pdo->prepare('DELETE FROM listening_rooms WHERE id=?')->execute([$id]);
        flash('success', t('rooms.deleted')); redirect('admin/listening_rooms.php');
    }
    $room['title']=trim((string)($_POST['title'] ?? ''));
    $room['description']=trim((string)($_POST['description'] ?? ''));
    $room['allow_room_download']=isset($_POST['allow_room_download']) ? 1 : 0;
    $room['allow_download']=isset($_POST['allow_download']) ? 1 : 0;
    $expiry=trim((string)($_POST['expires_at'] ?? ''));
    $date=$expiry === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $expiry);
    $room['expires_at']=$date ? $date->format('Y-m-d H:i:s') : null;
    $posted=is_array($_POST['tracks'] ?? null) ? $_POST['tracks'] : [];
    $selected=array_values(array_unique(array_map('intval', $posted)));
    $valid=array_map('intval', array_column($available,'id'));
    $order=is_array($_POST['track_order'] ?? null) ? array_map('intval',$_POST['track_order']) : [];
    $order=array_values(array_unique(array_filter($order,static fn(int $trackId):bool=>in_array($trackId,$selected,true))));
    $selected=[...$order,...array_values(array_diff($selected,$order))];
    $password=(string)($_POST['password'] ?? '');
    if ($room['title']==='' || mb_strlen($room['title'])>255 || ($expiry!=='' && (!$date || $date->format('Y-m-d\TH:i')!==$expiry))) $error=t('rooms.invalid');
    elseif (array_diff($selected,$valid)) $error=t('rooms.invalid_tracks');
    else {
        $hash=isset($_POST['remove_password']) ? null : ($password!=='' ? password_hash($password,PASSWORD_DEFAULT) : $room['password_hash']);
        try {
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare('UPDATE listening_rooms SET title=?,description=?,expires_at=?,password_hash=?,allow_download=?,allow_room_download=? WHERE id=?')->execute([$room['title'],$room['description'],$room['expires_at'],$hash,$room['allow_download'],$room['allow_room_download'],$id]);
            } else {
                $pdo->prepare('INSERT INTO listening_rooms(owner_user_id,title,description,expires_at,password_hash,allow_download,allow_room_download,token) VALUES(?,?,?,?,?,?,?,?)')->execute([$room['owner_user_id'],$room['title'],$room['description'],$room['expires_at'],$hash,$room['allow_download'],$room['allow_room_download'],random_token(24)]);
                $id=(int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM listening_room_tracks WHERE room_id=?')->execute([$id]);
            $insert=$pdo->prepare('INSERT INTO listening_room_tracks(room_id,track_id,position) VALUES(?,?,?)');
            foreach($selected as $position=>$trackId) $insert->execute([$id,$trackId,$position]);
            $pdo->commit(); flash('success',t('rooms.saved')); redirect('admin/listening_room_edit.php?id='.$id);
        } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            error_log('Listening room save failed: '.$e->getMessage()); $error=t('rooms.failed');
        }
    }
}
render_header($id?$room['title']:t('rooms.new'), true);
?>
<div class="d-flex justify-content-between gap-3 mb-4"><h1 class="h2"><?=e($id?$room['title']:t('rooms.new'))?></h1><a href="listening_rooms.php" class="btn btn-outline-secondary align-self-start"><?=e(t('text.zuruck'))?></a></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?>
<?php if($id && isset($room['token'])):$link=base_url('room.php?token='.rawurlencode($room['token']));?><div class="card mb-4"><div class="card-body"><label class="form-label" for="roomLink"><?=e(t('rooms.link'))?></label><div class="input-group"><input id="roomLink" class="form-control" readonly value="<?=e($link)?>"><button class="btn btn-outline-secondary" type="button" data-room-copy-link><?=e(t('text.link.kopieren'))?></button><a class="btn btn-outline-primary" href="<?=e($link)?>" target="_blank" rel="noopener"><?=e(t('text.offnen'))?></a></div></div></div><?php endif?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="card mb-4"><div class="card-body"><div class="row g-3">
<div class="col-md-7"><label class="form-label" for="roomTitle"><?=e(t('rooms.title'))?></label><input id="roomTitle" class="form-control" name="title" maxlength="255" required value="<?=e($room['title'])?>"></div>
<div class="col-md-5"><label class="form-label" for="roomExpiry"><?=e(t('text.ablaufdatum'))?></label><input id="roomExpiry" class="form-control" type="datetime-local" name="expires_at" value="<?=e($room['expires_at']?date('Y-m-d\TH:i',strtotime($room['expires_at'])):'')?>"></div>
<div class="col-12"><label class="form-label" for="roomDescription"><?=e(t('rooms.description'))?></label><textarea id="roomDescription" class="form-control" name="description" rows="3"><?=e($room['description'])?></textarea></div>
<div class="col-md-6"><label class="form-label" for="roomPassword"><?=e(t('text.optionales.passwort'))?></label><input id="roomPassword" class="form-control" type="password" name="password" autocomplete="new-password" placeholder="<?=e(t('text.unverandert.lassen'))?>"><?php if($room['password_hash']):?><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_password"><span class="form-check-label"><?=e(t('rooms.remove_password'))?></span></label><?php endif?></div>
<div class="col-md-6 align-self-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="allow_download" <?=$room['allow_download']?'checked':''?>><span class="form-check-label"><?=e(t('rooms.downloads'))?></span></label><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="allow_room_download" <?=!empty($room['allow_room_download'])?'checked':''?>><span class="form-check-label"><?=e(t('rooms.download_all_option'))?></span></label></div>
</div></div></div>
<div data-room-picker data-order="<?=e(json_encode($selected))?>" class="room-picker mb-4">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h2 class="h4 mb-1"><?=e(t('rooms.selection'))?></h2><p class="text-body-secondary mb-0"><?=e(t('rooms.picker_help'))?></p></div><span class="badge text-bg-primary align-self-start" data-room-count aria-live="polite"></span></div>
<div class="row g-3"><div class="col-lg-7"><div class="card"><div class="card-body">
<label class="form-label" for="roomTrackSearch"><?=e(t('rooms.search'))?></label><input id="roomTrackSearch" type="search" class="form-control mb-3" autocomplete="off" data-room-filter placeholder="<?=e(t('rooms.search'))?>">
<div class="room-library">
<?php $groups=[];foreach($available as $track)$groups[$track['album_id']][]=$track;foreach($groups as $albumId=>$tracks):?>
<details class="room-album" data-room-album data-search="<?=e(mb_strtolower($tracks[0]['album_title'].' '.$tracks[0]['artist']))?>">
<summary><span><strong><?=e($tracks[0]['album_title'])?></strong><small class="d-block text-body-secondary"><?=e($tracks[0]['artist'])?> · <?=count($tracks)?> <?=e(t('text.titel'))?></small></span><span class="badge text-bg-secondary" data-album-count></span></summary>
<div class="room-album-tracks"><button class="btn btn-sm btn-outline-secondary mb-3" type="button" data-room-select-album><?=e(t('rooms.select_album'))?></button>
<?php foreach($tracks as $track):?><label class="room-picker-track" data-search="<?=e(mb_strtolower($track['title']))?>"><input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="tracks[]" value="<?=(int)$track['id']?>" data-title="<?=e($track['title'])?>" data-album="<?=e($track['album_title'])?>" <?=in_array((int)$track['id'],$selected,true)?'checked':''?>><span><?=e($track['title'])?></span></label><?php endforeach?></div></details><?php endforeach?>
<p class="text-body-secondary py-3 mb-0" data-room-no-matches hidden><?=e(t('search.empty'))?></p>
<?php if(!$available):?><p class="text-body-secondary"><?=e(t('rooms.no_tracks'))?></p><?php endif?>
</div></div></div></div>
<div class="col-lg-5"><div class="card room-selected-card"><div class="card-body"><div class="d-flex align-items-center justify-content-between gap-2 mb-3"><h3 class="h6 mb-0"><?=e(t('rooms.selected'))?></h3><button type="button" class="btn btn-sm btn-outline-secondary" data-room-clear><?=e(t('rooms.clear'))?></button></div><p class="small text-body-secondary" data-room-selection-empty><?=e(t('rooms.selection_empty'))?></p><p class="small text-body-secondary"><?=e(t('rooms.sort_help'))?></p><div data-room-order-inputs><?php foreach($selected as $trackId):?><input type="hidden" name="track_order[]" value="<?=(int)$trackId?>"><?php endforeach?></div><div class="room-selected-list" data-room-selected></div></div></div></div>
</div></div>
<button class="btn btn-primary"><?=e(t('rooms.save'))?></button>
</form>
<?php if($id):?><form method="post" class="mt-4" data-confirm="<?=e(t('rooms.delete_confirm'))?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger"><?=e(t('rooms.delete'))?></button></form><?php endif?>
<?php render_footer();
