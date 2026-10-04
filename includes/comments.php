<?php
declare(strict_types=1);
function comments_context(string $scope, string $token, int $trackId): array {
    global $pdo;
    if (get_setting('timestamp_comments_enabled','0')!=='1') app_error(404);
    if ($scope==='room') {
        require_once __DIR__.'/listening_rooms.php';
        require_listening_rooms();
        $entity=public_room($token);
        if (!$entity || !room_access_granted($entity) || empty($entity['allow_comments'])) app_error(403);
        $tracks=room_tracks($entity);
        $moderate=is_logged_in() && (is_admin() || (int)current_user()['id']===(int)$entity['owner_user_id']);
    } elseif ($scope==='share') {
        $stmt=$pdo->prepare('SELECT s.* FROM shares s JOIN albums a ON a.id=s.album_id WHERE s.token=? AND a.deleted_at IS NULL');
        $stmt->execute([$token]);$entity=$stmt->fetch();
        if (!$entity || !share_access_granted($entity) || empty($entity['allow_comments'])) app_error(403);
        $stmt=$pdo->prepare('SELECT * FROM tracks WHERE album_id=? AND id=?');
        $stmt->execute([(int)$entity['album_id'],$trackId]);$tracks=$stmt->fetchAll();
        $moderate=is_logged_in() && can_access_album((int)$entity['album_id']);
    } else app_error(400);
    foreach($tracks as $track) if ((int)$track['id']===$trackId) return [$entity,$track,$moderate];
    app_error(404);
}
function render_comments(string $scope, string $token, array $tracks): void {
    if (!$tracks) return;
    ?>
<section id="timestampComments" class="timestamp-comments" data-comments data-scope="<?=e($scope)?>" data-token="<?=e($token)?>" data-endpoint="<?=e(base_url('comments.php'))?>">
<header class="comments-panel-header"><span class="comments-panel-icon" aria-hidden="true"><i class="bi bi-chat-left-text"></i></span><div><h2><?=e(t('comments.title'))?></h2><p><?=e(t('comments.visibility'))?></p></div></header>
<div class="comments-track-picker"><label for="commentTrack"><?=e(t('comments.track'))?></label><select id="commentTrack" class="form-select" data-comment-track><?php foreach($tracks as $track):?><option value="<?=(int)$track['id']?>"><?=e($track['title'])?></option><?php endforeach?></select></div>
<div class="comment-list" data-comment-list></div>
<form data-comment-form class="comment-compose"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label class="visually-hidden" for="commentName"><?=e(t('comments.name'))?></label><input id="commentName" class="form-control" name="author" placeholder="<?=e(t('comments.name'))?>" maxlength="80" required autocomplete="name">
<label class="visually-hidden" for="commentBody"><?=e(t('comments.text'))?></label><textarea id="commentBody" class="form-control" name="body" placeholder="<?=e(t('comments.placeholder'))?>" rows="3" maxlength="2000" required></textarea>
<div class="comment-time-row"><label for="commentTime"><?=e(t('comments.time_short'))?></label><input id="commentTime" class="form-control" name="timestamp" value="0:00" pattern="[0-9]+:[0-5][0-9]" required aria-label="<?=e(t('comments.time'))?>"><button class="comment-capture" type="button" data-comment-now title="<?=e(t('comments.now'))?>"><i class="bi bi-clock" aria-hidden="true"></i> <?=e(t('comments.capture'))?></button></div>
<div class="comment-compose-footer"><div class="comment-status" role="status" aria-live="polite" data-comment-status></div><button class="btn comment-submit" type="submit"><i class="bi bi-send" aria-hidden="true"></i> <?=e(t('comments.send_short'))?></button></div>
</form></section>
<?php
}
