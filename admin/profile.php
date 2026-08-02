<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
$user=current_user();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
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
render_header(t('profile.title'),true);
?>
<div class="row justify-content-center"><div class="col-lg-7">
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('profile.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('profile.help'))?></p></div><a class="btn btn-outline-secondary" href="index.php"><?=e(t('text.zuruck'))?></a></div>
<div class="card shadow-sm"><div class="card-body p-4">
<form method="post" class="row g-3">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<div class="col-md-6"><label class="form-label"><?=e(t('text.benutzername'))?></label><input class="form-control" name="username" value="<?=e($user['username'])?>" minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" required><div class="form-text"><?=e(t('profile.username_help'))?></div></div>
<div class="col-md-6"><label class="form-label"><?=e(t('user.email'))?></label><input type="email" class="form-control" name="email" value="<?=e($user['email']??'')?>" required></div>
<div class="col-md-6"><label class="form-label"><?=e(t('password_reset.new_password'))?></label><input type="password" class="form-control" name="password" minlength="8" placeholder="<?=e(t('text.unverandert.lassen'))?>"></div>
<div class="col-md-6"><label class="form-label"><?=e(t('password_reset.confirm_password'))?></label><input type="password" class="form-control" name="password_confirm" minlength="8" placeholder="<?=e(t('text.unverandert.lassen'))?>"></div>
<div class="col-12"><button class="btn btn-primary"><?=e(t('profile.save'))?></button></div>
</form></div></div></div></div>
<?php render_footer();