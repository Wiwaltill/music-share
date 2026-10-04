<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
$uid=(int)current_user()['id'];
$access=is_admin() ? '1=1' : '(s.id IS NOT NULL AND (a.owner_user_id=:album_owner OR EXISTS(SELECT 1 FROM album_collaborators ac WHERE ac.album_id=a.id AND ac.user_id=:collaborator))) OR (r.id IS NOT NULL AND r.owner_user_id=:room_owner)';
$params=is_admin() ? [] : ['album_owner'=>$uid,'collaborator'=>$uid,'room_owner'=>$uid];
$joins=' FROM track_comments c JOIN tracks t ON t.id=c.track_id JOIN albums ta ON ta.id=t.album_id LEFT JOIN shares s ON s.id=c.share_id LEFT JOIN albums a ON a.id=s.album_id LEFT JOIN listening_rooms r ON r.id=c.room_id';
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
$query=$pdo->prepare('SELECT c.*,t.title track_title,ta.title track_album,ta.artist track_artist,ta.cover_file track_cover,a.title album_title,s.label share_label,r.title room_title'.$joins.$where.' ORDER BY c.id DESC LIMIT 51 OFFSET '.$offset);
$query->execute($params);$comments=$query->fetchAll();$more=count($comments)>50;$comments=array_slice($comments,0,50);
render_header(t('comments.title'),true);
if ($comments) echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.css">';
?>
<div class="d-flex justify-content-between flex-wrap gap-3 mb-4"><div><h1 class="h2"><?=e(t('comments.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('comments.manage_help'))?></p></div><?php if($albumId || $roomId):?><a class="btn btn-outline-secondary align-self-start" href="<?=e(base_url('admin/comments.php'))?>"><?=e(t('comments.all'))?></a><?php endif?></div>
<div class="list-group shadow-sm">
<?php if($comments):?><div class="list-group-item bg-body-tertiary d-none d-md-block small text-body-secondary" aria-hidden="true"><div class="row g-3"><div class="col-md-4"><?=e(t('comments.track'))?></div><div class="col-md-6"><?=e(t('comments.text'))?></div><div class="col-md-2 text-end"><?=e(t('comments.time_short'))?></div></div></div><?php endif?>
<?php foreach($comments as $comment):?>
<article class="list-group-item py-3" data-row data-track-id="<?=(int)$comment['track_id']?>">
<button type="button" hidden data-play data-src="<?=e(base_url('admin/comment_stream.php?comment_id='.(int)$comment['id']))?>" data-title="<?=e($comment['track_title'])?>" data-artist="<?=e($comment['track_artist'])?>" data-album="<?=e($comment['track_album'])?>" data-cover="<?=e($comment['track_cover']?base_url('uploads/covers/'.rawurlencode(basename((string)$comment['track_cover']))):'')?>"></button>
<div class="row g-3 align-items-start"><div class="col-8 col-md-4 order-1"><div class="d-flex align-items-start gap-3">
<?php if($comment['track_cover']):?><img class="rounded object-fit-cover flex-shrink-0" src="<?=e(base_url('uploads/covers/'.rawurlencode(basename((string)$comment['track_cover']))))?>" width="44" height="44" alt="" loading="lazy"><?php else:?><span class="rounded bg-body-tertiary text-body-secondary d-inline-flex align-items-center justify-content-center p-2 flex-shrink-0" aria-hidden="true">♪</span><?php endif?>
<div class="text-break"><strong class="small"><?=e($comment['track_title'])?></strong><div class="small text-body-secondary"><?=e($comment['track_album'])?></div><div class="small text-body-secondary mt-1"><?php if($comment['room_id']):?><i class="bi bi-headphones" aria-hidden="true"></i> <?=e($comment['room_title'])?><?php else:?><i class="bi bi-link-45deg" aria-hidden="true"></i> <?=e($comment['share_label'] ?: '#'.$comment['share_id'])?><?php endif?></div></div></div></div>
<div class="col-12 col-md-6 order-3 order-md-2"><div class="d-flex flex-wrap align-items-center gap-2 small"><strong><?=e($comment['author'])?></strong><time class="small text-body-secondary" datetime="<?=e(str_replace(' ','T',$comment['created_at']))?>" title="<?=e($comment['created_at'])?>"><?=e(date('d.m.Y · H:i',strtotime($comment['created_at'])))?></time></div><p class="small text-break mb-0 mt-2"><?=nl2br(e($comment['body']))?></p></div>
<div class="col-4 col-md-2 order-2 order-md-3"><div class="d-flex align-items-center justify-content-end gap-2"><button type="button" class="btn btn-sm btn-outline-primary" data-comment-seek data-track-id="<?=(int)$comment['track_id']?>" data-seconds="<?=(int)$comment['position_seconds']?>" title="<?=e(t('comments.jump'))?>" aria-label="<?=e(t('comments.jump').' · '.$comment['track_title'])?>"><i class="bi bi-play-fill" aria-hidden="true"></i> <?=intdiv((int)$comment['position_seconds'],60)?>:<?=str_pad((string)((int)$comment['position_seconds']%60),2,'0',STR_PAD_LEFT)?></button>
<form method="post" data-confirm="<?=e(t('comments.delete_confirm'))?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="comment_id" value="<?=(int)$comment['id']?>"><button class="btn btn-sm btn-outline-danger" title="<?=e(t('comments.delete'))?>" aria-label="<?=e(t('comments.delete').' · '.$comment['author'].' · '.$comment['track_title'])?>"><i class="bi bi-trash3" aria-hidden="true"></i></button></form></div></div></div></article>
<?php endforeach?>
<?php if(!$comments):?><div class="list-group-item py-4 text-body-secondary"><?=e(t('comments.empty'))?></div><?php endif?>
</div>
<?php $filter=$albumId?'&album_id='.$albumId:($roomId?'&room_id='.$roomId:'');?>
<nav class="d-flex gap-2 mt-4" aria-label="Pagination"><?php if($page>1):?><a class="btn btn-outline-secondary" href="?page=<?=$page-1?><?=e($filter)?>">← <?=e(t('text.zuruck'))?></a><?php endif?><?php if($more):?><a class="btn btn-outline-secondary" href="?page=<?=$page+1?><?=e($filter)?>"><?=e(t('comments.next'))?> →</a><?php endif?></nav>
<?php if($comments):?><div id="floatingPlayer" class="floating-player comment-admin-player" hidden><div class="player-meta min-w-0"><img data-player-cover alt="" hidden><div class="min-w-0"><div id="nowPlaying" class="fw-semibold text-truncate"></div><div class="small opacity-75 text-truncate" data-player-artist></div><div class="small opacity-75 text-truncate" data-player-album></div></div></div><audio id="mainPlayer" controls playsinline></audio><button id="closePlayer" class="player-close" type="button" aria-label="<?=e(t('text.player.schlieen'))?>">×</button></div><?php endif?>
<?php if($comments):?><script src="https://cdn.jsdelivr.net/npm/plyr@3.8.4/dist/plyr.polyfilled.min.js"></script><?php endif?>
<?php render_footer();
