<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
if (!is_admin()) { http_response_code(403); exit('Nicht erlaubt.'); }
function purge_album_files(PDO $pdo, int $id): void {
    $s=$pdo->prepare('SELECT cover_file FROM albums WHERE id=?');$s->execute([$id]);$cover=(string)$s->fetchColumn();
    $s=$pdo->prepare('SELECT audio_file FROM tracks WHERE album_id=?');$s->execute([$id]);
    foreach($s->fetchAll(PDO::FETCH_COLUMN) as $audio){$f=dirname(__DIR__).'/uploads/audio/'.basename((string)$audio);if(is_file($f))@unlink($f);}
    if($cover!==''){$f=dirname(__DIR__).'/uploads/covers/'.basename($cover);if(is_file($f))@unlink($f);}
    $pdo->prepare('DELETE FROM albums WHERE id=?')->execute([$id]);
}
// Automatic cleanup after 30 days.
$old=$pdo->query("SELECT id FROM albums WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchAll(PDO::FETCH_COLUMN);
foreach($old as $oldId) purge_album_files($pdo,(int)$oldId);
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$id=(int)($_POST['id']??0);$action=$_POST['action']??'';
    if($action==='restore'){$pdo->prepare('UPDATE albums SET deleted_at=NULL WHERE id=?')->execute([$id]);flash('success','Album wurde wiederhergestellt.');}
    elseif($action==='purge'){purge_album_files($pdo,$id);flash('success','Album wurde endgültig gelöscht.');}
    redirect('admin/trash.php');
}
$albums=$pdo->query("SELECT a.*,u.username,u.display_name,DATEDIFF(DATE_ADD(a.deleted_at,INTERVAL 30 DAY),NOW()) days_left FROM albums a LEFT JOIN users u ON u.id=a.owner_user_id WHERE a.deleted_at IS NOT NULL ORDER BY a.deleted_at DESC")->fetchAll();
render_header(t('nav.trash'),true);
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 mb-1"><?=e(t('nav.trash'))?></h1><p class="text-body-secondary mb-0"><?=e(t('text.alben.werden.nach.30.tagen.automatisch.endgultig.geloscht'))?></p></div><a class="btn btn-outline-secondary" href="index.php"><?=e(t('text.zuruck'))?></a></div>
<div class="vstack gap-3"><?php foreach($albums as $a):?><div class="card shadow-sm"><div class="card-body d-flex flex-wrap align-items-center gap-3"><div class="flex-grow-1"><h2 class="h5 mb-1"><?=e($a['title'])?></h2><div class="text-body-secondary"><?=e($a['artist'])?> · <?=e(t('text.geloscht.am'))?> <?=e(date('d.m.Y H:i',strtotime($a['deleted_at'])))?> · <?=max(0,(int)$a['days_left'])?> Tage verbleibend</div></div><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$a['id']?>"><input type="hidden" name="action" value="restore"><button class="btn btn-primary"><i class="bi bi-arrow-counterclockwise me-1"></i><?=e(t('text.wiederherstellen'))?></button></form><form method="post" data-confirm="<?=e(t('confirm.purge_album'))?>"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$a['id']?>"><input type="hidden" name="action" value="purge"><button class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?=e(t('text.endgultig.loschen'))?></button></form></div></div><?php endforeach?><?php if(!$albums):?><div class="alert alert-info"><?=e(t('text.papierkorb.ist.leer'))?></div><?php endif?></div>
<?php render_footer();
