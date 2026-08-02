<?php
require_once __DIR__.'/../includes/bootstrap.php';
if(is_logged_in())redirect('admin/index.php');
$sent=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$email=strtolower(trim((string)($_POST['email']??'')));
 if(filter_var($email,FILTER_VALIDATE_EMAIL)){
  $s=$pdo->prepare('SELECT id,username,email FROM users WHERE email=? AND is_active=1 LIMIT 1');$s->execute([$email]);$u=$s->fetch();
  if($u){
   try{$token=create_password_reset((int)$u['id']);$url=base_url('admin/reset_password.php?token='.rawurlencode($token));send_system_mail($email,t('password_reset.subject'),password_reset_mail_html($u['username'],$url));}catch(Throwable $e){}
  }
 }
 $sent=true;
}
render_header(t('password_reset.forgot_title'));?>
<div class="row justify-content-center"><div class="col-md-5"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-3"><?=e(t('password_reset.forgot_title'))?></h1>
<?php if($sent):?><div class="alert alert-success"><?=e(t('password_reset.sent_generic'))?></div><?php else:?><p class="text-body-secondary"><?=e(t('password_reset.forgot_help'))?></p><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><label class="form-label"><?=e(t('user.email'))?></label><input type="email" class="form-control mb-3" name="email" required autofocus><button class="btn btn-primary w-100"><?=e(t('password_reset.send_link'))?></button></form><?php endif?>
<a class="btn btn-link w-100 mt-2" href="login.php"><?=e(t('password_reset.back_login'))?></a></div></div></div></div><?php render_footer();