<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/listening_rooms.php';
require_login(); require_listening_rooms();
if (is_admin()) {
    $rooms = $pdo->query('SELECT r.*,u.username FROM listening_rooms r JOIN users u ON u.id=r.owner_user_id ORDER BY r.created_at DESC,r.id DESC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT r.*,u.username FROM listening_rooms r JOIN users u ON u.id=r.owner_user_id WHERE r.owner_user_id=? ORDER BY r.created_at DESC,r.id DESC');
    $stmt->execute([(int)current_user()['id']]); $rooms=$stmt->fetchAll();
}
render_header('Listening Rooms', true);
?>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="h2">Listening Rooms</h1><p class="text-body-secondary mb-0"><?=e(t('rooms.intro'))?></p></div><a class="btn btn-primary align-self-start" href="listening_room_edit.php"><?=e(t('rooms.new'))?></a></div>
<div class="row g-3"><?php foreach($rooms as $room):?><div class="col-md-6 col-xl-4"><div class="card h-100 shadow-sm"><div class="card-body"><h2 class="h5"><?=e($room['title'])?></h2><p class="small text-body-secondary"><?=e($room['username'])?><?php if($room['expires_at']):?> · <?=e($room['expires_at'])?><?php endif?></p><a class="btn btn-primary btn-sm" href="listening_room_edit.php?id=<?=(int)$room['id']?>"><?=e(t('text.verwalten'))?></a> <a class="btn btn-outline-secondary btn-sm" href="<?=e(base_url('room.php?token='.rawurlencode($room['token'])))?>" target="_blank" rel="noopener"><?=e(t('text.offnen'))?></a></div></div></div><?php endforeach?><?php if(!$rooms):?><p class="text-body-secondary"><?=e(t('rooms.empty'))?></p><?php endif?></div>
<?php render_footer();
