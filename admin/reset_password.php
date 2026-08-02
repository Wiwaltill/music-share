<?php
require_once __DIR__.'/../includes/bootstrap.php';
if(is_logged_in())redirect('admin/index.php');
$token=(string)($_GET['token']??$_POST['token']??'');$user=password_reset_user($token);$success=false;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 if(!$user){$error=t('password_reset.invalid');}
 else{$password=(string)($_POST['password']??'');$confirm=(string)($_POST['password_confirm']??'');
  if(strlen($password)<8)$error=t('password_reset.password_min');
  elseif($password!==$confirm)$error=t('password_reset.password_mismatch');
  else{$pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$user['id']]);$pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$user['token_id']]);$pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=? AND id<>?')->execute([(int)$user['id'],(int)$user['token_id']]);$pdo->commit();$success=true;}catch(Throwable $e){$pdo->rollBack();$error=t('password_reset.failed');}}
 }
}
render_header(t('password_reset.reset_title'));?>
<div class="row justify-content-center"><div class="col-md-5"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-3"><?=e(t('password_reset.reset_title'))?></h1>
<?php if($success):?><div class="alert alert-success"><?=e(t('password_reset.success'))?></div><a class="btn btn-primary w-100" href="login.php"><?=e(t('password_reset.to_login'))?></a>
<?php elseif(!$user):?><div class="alert alert-danger"><?=e(t('password_reset.invalid'))?></div><a class="btn btn-outline-primary w-100" href="forgot_password.php"><?=e(t('password_reset.request_new'))?></a>
<?php else:?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="token" value="<?=e($token)?>"><label class="form-label"><?=e(t('password_reset.new_password'))?></label><input type="password" class="form-control mb-3" name="password" minlength="8" required><label class="form-label"><?=e(t('password_reset.confirm_password'))?></label><input type="password" class="form-control mb-3" name="password_confirm" minlength="8" required><button class="btn btn-primary w-100"><?=e(t('password_reset.save_password'))?></button></form><?php endif?></div></div></div></div><?php render_footer();