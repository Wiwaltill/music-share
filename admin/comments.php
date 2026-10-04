<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
$uid=(int)current_user()['id'];
$access=is_admin() ? '1=1' : '(s.id IS NOT NULL AND (a.owner_user_id=:album_owner OR EXISTS(SELECT 1 FROM album_collaborators ac WHERE ac.album_id=a.id AND ac.user_id=:collaborator))) OR (r.id IS NOT NULL AND r.owner_user_id=:room_owner)';
$params=is_admin() ? [] : ['album_owner'=>$uid,'collaborator'=>$uid,'room_owner'=>$uid];
$joins=' FROM track_comments c JOIN tracks t ON t.id=c.track_id LEFT JOIN shares s ON s.id=c.share_id LEFT JOIN albums a ON a.id=s.album_id LEFT JOIN listening_rooms r ON r.id=c.room_id';
$albumId=max(0,(int)($_GET['album_id'] ?? 0));$roomId=max(0,(int)($_GET['room_id'] ?? 0));
$where=' WHERE ('.$access.')';
if ($albumId) {require_album_access($albumId);$where.=' AND s.album_id=:album_filter';$params['album_filter']=$albumId;}
if ($roomId) {require_once __DIR__.'/../includes/listening_rooms.php';room_for_management($roomId);$where.=' AND c.room_id=:room_filter';$params['room_filter']=$roomId;}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $query=$pdo->prepare('SELECT c.id'.$joins.$where.' AND c.id=:comment_id');
    $query->execute([...$params,'comment_id'=>(int)($_POST['comment_id'] ?? 0)]);
    if (!$query->fetchColumn()) app_error(403);
    $pdo->prepare('DELETE FROM track_comments WHERE id=?')->execute([(int)$_POST['comment_id']]);
    flash('success',t('comments.deleted'));
    redirect('admin/comments.php'.($albumId?'?album_id='.$albumId:($roomId?'?room_id='.$roomId:'')));
}
$page=max(1,(int)($_GET['page'] ?? 1));$offset=($page-1)*50;
$query=$pdo->prepare('SELECT c.*,t.title track_title,a.title album_title,s.label share_label,r.title room_title'.$joins.$where.' ORDER BY c.id DESC LIMIT 51 OFFSET '.$offset);
$query->execute($params);$comments=$query->fetchAll();$more=count($comments)>50;$comments=array_slice($comments,0,50);
render_header(t('comments.title'),true);
?>
<div class="d-flex justify-content-between flex-wrap gap-3 mb-4"><div><h1 class="h2"><?=e(t('comments.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('comments.manage_help'))?></p></div><?php if($albumId || $roomId):?><a class="btn btn-outline-secondary align-self-start" href="<?=e(base_url('admin/comments.php'))?>"><?=e(t('comments.all'))?></a><?php endif?></div>
<div class="vstack gap-3">
<?php foreach($comments as $comment):?>
<article class="card" data-row data-track-id="<?=(int)$comment['track_id']?>"><button type="button" hidden data-play data-src="<?=e(base_url('admin/comment_stream.php?comment_id='.(int)$comment['id']))?>" data-title="<?=e($comment['track_title'])?>"></button><div class="card-body"><div class="d-flex flex-wrap align-items-center gap-2 mb-2"><strong><?=e($comment['track_title'])?></strong><button type="button" class="btn btn-sm btn-outline-primary" data-comment-seek data-track-id="<?=(int)$comment['track_id']?>" data-seconds="<?=(int)$comment['position_seconds']?>" title="<?=e(t('comments.jump'))?>" aria-label="<?=e(t('comments.jump').' · '.$comment['track_title'])?>"><i class="bi bi-play-fill" aria-hidden="true"></i> <?=intdiv((int)$comment['position_seconds'],60)?>:<?=str_pad((string)((int)$comment['position_seconds']%60),2,'0',STR_PAD_LEFT)?></button><span class="small text-body-secondary ms-auto"><?=e($comment['created_at'])?></span></div><div class="small text-body-secondary mb-2"><?php if($comment['room_id']):?>Listening Room · <?=e($comment['room_title'])?><?php else:?><?=e($comment['album_title'])?> · <?=e($comment['share_label'] ?: '#'.$comment['share_id'])?><?php endif?></div><strong><?=e($comment['author'])?></strong><p class="comment-body mt-2"><?=nl2br(e($comment['body']))?></p><form method="post" data-confirm="<?=e(t('comments.delete_confirm'))?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="comment_id" value="<?=(int)$comment['id']?>"><button class="btn btn-sm btn-outline-danger"><?=e(t('comments.delete'))?></button></form></div></article>
<?php endforeach?>
<?php if(!$comments):?><div class="alert alert-light border"><?=e(t('comments.empty'))?></div><?php endif?>
</div>
<?php $filter=$albumId?'&album_id='.$albumId:($roomId?'&room_id='.$roomId:'');?>
<nav class="d-flex gap-2 mt-4" aria-label="Pagination"><?php if($page>1):?><a class="btn btn-outline-secondary" href="?page=<?=$page-1?><?=e($filter)?>">← <?=e(t('text.zuruck'))?></a><?php endif?><?php if($more):?><a class="btn btn-outline-secondary" href="?page=<?=$page+1?><?=e($filter)?>"><?=e(t('comments.next'))?> →</a><?php endif?></nav>
<?php if($comments):?><div id="floatingPlayer" class="floating-player comment-admin-player" hidden><div class="min-w-0"><div id="nowPlaying" class="fw-semibold text-truncate"></div></div><audio id="mainPlayer" controls playsinline></audio><button id="closePlayer" class="player-close" type="button" aria-label="<?=e(t('text.player.schlieen'))?>">×</button></div><?php endif?>
<?php render_footer();
