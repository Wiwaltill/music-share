<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
$user=current_user();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(($_POST['action']??'')==='revoke_other_sessions'){
        $token=(string)($_SESSION['session_token']??'');
        $pdo->prepare("UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_token<>? AND revoked_at IS NULL")->execute([(int)$user['id'],$token]);
        flash('success',t('sessions.revoked'));
        redirect('admin/profile.php#sessions');
    }
    $username=trim((string)($_POST['username']??''));
    $email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');
    $passwordConfirm=(string)($_POST['password_confirm']??'');
    if($username===''||!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$username)){
        flash('danger',t('profile.invalid_username'));
    }elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        flash('danger',t('user.invalid_email'));
    }elseif($password!==''&&strlen($password)<8){
        flash('danger',t('password_reset.password_min'));
    }elseif($password!==$passwordConfirm){
        flash('danger',t('password_reset.password_mismatch'));
    }else{
        try{
            if($password!==''){
                $stmt=$pdo->prepare('UPDATE users SET username=?,email=?,password_hash=? WHERE id=?');
                $stmt->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),(int)$user['id']]);
            }else{
                $stmt=$pdo->prepare('UPDATE users SET username=?,email=? WHERE id=?');
                $stmt->execute([$username,$email,(int)$user['id']]);
            }
            flash('success',t('profile.saved'));
            redirect('admin/profile.php');
        }catch(PDOException $e){
            flash('danger',t('user.duplicate'));
        }
    }
}
$stmt=$pdo->prepare('SELECT id,username,email,role FROM users WHERE id=?');
$stmt->execute([(int)$user['id']]);
$user=$stmt->fetch();
$sessionRows=[];
try{$stmt=$pdo->prepare('SELECT session_token,created_at,last_seen_at,expires_at,ip_hint,user_agent_hint FROM user_sessions WHERE user_id=? AND revoked_at IS NULL AND expires_at>NOW() ORDER BY last_seen_at DESC');$stmt->execute([(int)$user['id']]);$sessionRows=$stmt->fetchAll();}catch(Throwable $e){}
render_header(t('profile.title'),true);
?>
<div class="row justify-content-center"><div class="col-xl-9">
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('profile.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('profile.help'))?></p></div><a class="btn btn-outline-secondary" href="index.php"><?=e(t('text.zuruck'))?></a></div>
<div class="card shadow-sm"><div class="card-body p-4">
<form method="post" class="row g-3">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<div class="col-md-6"><label class="form-label"><?=e(t('text.benutzername'))?></label><input class="form-control" name="username" value="<?=e($user['username'])?>" minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" required><div class="form-text"><?=e(t('profile.username_help'))?></div></div>
<div class="col-md-6"><label class="form-label"><?=e(t('user.email'))?></label><input type="email" class="form-control" name="email" value="<?=e($user['email']??'')?>" required></div>
<div class="col-md-6"><label class="form-label"><?=e(t('password_reset.new_password'))?></label><input type="password" class="form-control" name="password" minlength="8" placeholder="<?=e(t('text.unverandert.lassen'))?>"></div>
<div class="col-md-6"><label class="form-label"><?=e(t('password_reset.confirm_password'))?></label><input type="password" class="form-control" name="password_confirm" minlength="8" placeholder="<?=e(t('text.unverandert.lassen'))?>"></div>
<div class="col-12"><button class="btn btn-primary"><?=e(t('profile.save'))?></button></div>
</form></div></div>
<div class="card shadow-sm mt-4" id="sessions"><div class="card-header bg-body"><h2 class="h5 mb-0"><i class="bi bi-laptop me-2"></i><?=e(t('sessions.title'))?></h2></div><div class="card-body">
<p class="text-body-secondary"><?=e(t('sessions.help'))?></p>
<?php if($sessionRows):?><div class="list-group list-group-flush"><?php foreach($sessionRows as $row):$current=hash_equals((string)($_SESSION['session_token']??''),(string)$row['session_token']);?><div class="list-group-item px-0 d-flex justify-content-between gap-3"><div><strong><?=e($current?t('sessions.current'):t('sessions.other'))?></strong><div class="small text-body-secondary"><?=e(mb_substr((string)$row['user_agent_hint'],0,110))?></div><div class="small text-body-secondary"><?=e(t('sessions.last_seen'))?> <?=e(date('d.m.Y H:i',strtotime($row['last_seen_at'])))?><?php if($row['ip_hint']):?> · <?=e($row['ip_hint'])?><?php endif?></div></div><?php if($current):?><span class="badge text-bg-success align-self-start"><?=e(t('sessions.current_badge'))?></span><?php endif?></div><?php endforeach?></div><?php else:?><p class="text-body-secondary"><?=e(t('sessions.none'))?></p><?php endif?>
<?php if(count($sessionRows)>1):?><form method="post" class="mt-3" data-confirm="<?=e(t('sessions.revoke_confirm'))?>"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="revoke_other_sessions"><button class="btn btn-outline-danger"><?=e(t('sessions.revoke_others'))?></button></form><?php endif?>
</div></div></div></div>
<?php render_footer();