<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'site') {
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        if ($siteName === '') $siteName = 'Album Share';
        set_setting('site_name', mb_substr($siteName, 0, 120));
        set_setting('album_colors_enabled', isset($_POST['album_colors_enabled']) ? '1' : '0');
        set_setting('statistics_enabled', isset($_POST['statistics_enabled']) ? '1' : '0');
        set_setting('dashboard_statistics_cards', isset($_POST['dashboard_statistics_cards']) ? '1' : '0');
        $retention=(int)($_POST['statistics_retention_days']??0);
        if(!in_array($retention,[0,90,180,365],true))$retention=0;
        set_setting('statistics_retention_days',(string)$retention);
        $language = (string)($_POST['language'] ?? 'de');
        if (!array_key_exists($language, supported_languages())) $language = 'de';
        set_setting('language', $language);
        flash('success', t('text.seiteneinstellungen.gespeichert'));
        redirect('admin/settings.php');
    }


    if ($action === 'mail') {
        $method = ($_POST['mail_method'] ?? 'mail') === 'smtp' ? 'smtp' : 'mail';
        $enc = in_array(($_POST['smtp_encryption'] ?? 'tls'), ['none','tls','ssl'], true) ? (string)$_POST['smtp_encryption'] : 'tls';
        set_setting('mail_method',$method);
        set_setting('mail_from_name',trim((string)($_POST['mail_from_name'] ?? app_name())));
        set_setting('mail_from_email',strtolower(trim((string)($_POST['mail_from_email'] ?? ''))));
        set_setting('smtp_host',trim((string)($_POST['smtp_host'] ?? '')));
        set_setting('smtp_port',(string)max(1,(int)($_POST['smtp_port'] ?? 587)));
        set_setting('smtp_encryption',$enc);
        set_setting('smtp_username',trim((string)($_POST['smtp_username'] ?? '')));
        if (trim((string)($_POST['smtp_password'] ?? '')) !== '') set_setting('smtp_password',(string)$_POST['smtp_password']);
        flash('success',t('mail.saved'));
        redirect('admin/settings.php#mail');
    }
    if ($action === 'test_mail') {
        $to = strtolower(trim((string)($_POST['test_email'] ?? '')));
        try {
            if (!send_system_mail($to,t('mail.test_subject'),'<p>'.e(t('mail.test_body')).'</p>')) throw new RuntimeException(t('mail.send_failed'));
            flash('success',t('mail.test_sent'));
        } catch (Throwable $e) { flash('danger',$e->getMessage()); }
        redirect('admin/settings.php#mail');
    }

    if ($action === 'create_user') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($username === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('danger', t('user.validation_create'));
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users(username,email,password_hash,role,is_active) VALUES(?,?,?,?,1)');
                $stmt->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
                flash('success', 'Benutzer wurde angelegt.');
            } catch (PDOException $e) {
                flash('danger', t('user.duplicate'));
            }
        }
        redirect('admin/settings.php#users');
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');
        if ($id === (int)$_SESSION['user_id'] && (!$active || $role !== 'admin')) {
            flash('danger', 'Das eigene aktive Administratorkonto kann nicht deaktiviert oder herabgestuft werden.');
            redirect('admin/settings.php#users');
        }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
            flash('danger', t('user.invalid_email'));
            redirect('admin/settings.php#users');
        }
        if ($password !== '' && strlen($password) < 8) {
            flash('danger', 'Ein neues Passwort muss mindestens 8 Zeichen lang sein.');
            redirect('admin/settings.php#users');
        }
        if ($password !== '') {
            $stmt=$pdo->prepare('UPDATE users SET email=?,role=?,is_active=?,password_hash=? WHERE id=?');
            $stmt->execute([$email,$role,$active,password_hash($password,PASSWORD_DEFAULT),$id]);
        } else {
            $stmt=$pdo->prepare('UPDATE users SET email=?,role=?,is_active=? WHERE id=?');
            $stmt->execute([$email,$role,$active,$id]);
        }
        flash('success', 'Benutzer wurde aktualisiert.');
        redirect('admin/settings.php#users');
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            flash('danger', 'Das eigene Konto kann nicht gelöscht werden.');
        } else {
            $stmt=$pdo->prepare('DELETE FROM users WHERE id=?');
            $stmt->execute([$id]);
            flash('success', 'Benutzer wurde gelöscht.');
        }
        redirect('admin/settings.php#users');
    }
}

$users = $pdo->query('SELECT id,username,email,role,is_active,created_at FROM users ORDER BY username')->fetchAll();
render_header(t('page.settings.title'), true);
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('page.settings.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('text.seitendarstellung.und.zugange.verwalten'))?></p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="system_status.php"><i class="bi bi-activity me-1"></i><?=e(t('status.title'))?></a><a class="btn btn-outline-primary" href="update.php"><?=e(t('text.updates'))?></a></div></div>
<div class="vstack gap-4">
  <section id="general">
    <div class="card shadow-sm border-0"><div class="card-header bg-body py-3"><h2 class="h5 mb-0"><i class="bi bi-sliders me-2"></i><?=e(t('text.allgemein'))?></h2></div><div class="card-body p-4">
      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="site">
        <label class="form-label" for="site_name"><?=e(t('text.site.name'))?></label>
        <input class="form-control" id="site_name" name="site_name" maxlength="120" value="<?=e(app_name())?>" required>
        <div class="form-text"><?=e(t('text.wird.im.backend.im.seitentitel.und.in.der.navigation'))?></div><div class="mt-3">
          <label class="form-label" for="language"><?=e(t('language'))?></label>
          <select class="form-select" id="language" name="language">
            <?php foreach(supported_languages() as $code=>$label): ?>
              <option value="<?=e($code)?>" <?=current_language()===$code?'selected':''?>><?=e($label)?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text"><?=e(t('text.die.systemsprache.gilt.fur.backend.offentliche.seiten.und.systemmeldungen'))?></div>
        </div><div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="album_colors_enabled" name="album_colors_enabled" <?=get_setting('album_colors_enabled','0')==='1'?'checked':''?>> <label class="form-check-label" for="album_colors_enabled"><?=e(t('text.albumfarben.aus.dem.cover.auf.offentlichen.seiten.verwenden'))?></label></div><div class="form-text"><?=e(t('text.ist.die.funktion.deaktiviert.wird.die.neutrale.glasdarstellung.verwendet'))?></div>
        <div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" role="switch" id="statistics_enabled" name="statistics_enabled" <?=statistics_enabled()?'checked':''?>> <label class="form-check-label" for="statistics_enabled"><?=e(t('stats.enable'))?></label></div><div class="form-text"><?=e(t('stats.privacy_help'))?></div><div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="dashboard_statistics_cards" name="dashboard_statistics_cards" <?=get_setting('dashboard_statistics_cards','1')==='1'?'checked':''?>><label class="form-check-label" for="dashboard_statistics_cards"><?=e(t('dashboard.cards_enable'))?></label></div><div class="form-text"><?=e(t('dashboard.cards_help'))?></div><div class="mt-3"><label class="form-label" for="statistics_retention_days"><?=e(t('stats.retention'))?></label><select class="form-select" id="statistics_retention_days" name="statistics_retention_days"><?php foreach([0=>'stats.retention_unlimited',90=>'stats.retention_90',180=>'stats.retention_180',365=>'stats.retention_365'] as $days=>$label):?><option value="<?=$days?>" <?=get_setting('statistics_retention_days','0')===(string)$days?'selected':''?>><?=e(t($label))?></option><?php endforeach?></select><div class="form-text"><?=e(t('stats.retention_help'))?></div></div><button class="btn btn-primary mt-3"><?=e(t('text.speichern'))?></button>
      </form>
    </div></div>
  </section>
  
  <section id="mail">
    <div class="card shadow-sm border-0"><div class="card-header bg-body py-3"><h2 class="h5 mb-0"><i class="bi bi-envelope-gear me-2"></i><?=e(t('mail.settings'))?></h2></div><div class="card-body p-4">
      <p class="text-body-secondary"><?=e(t('mail.settings_help'))?></p>
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="mail">
        <div class="col-md-3"><label class="form-label"><?=e(t('mail.method'))?></label><select class="form-select" name="mail_method"><option value="mail" <?=get_setting('mail_method','mail')==='mail'?'selected':''?>>PHP mail()</option><option value="smtp" <?=get_setting('mail_method','mail')==='smtp'?'selected':''?>>SMTP</option></select></div>
        <div class="col-md-4"><label class="form-label"><?=e(t('mail.from_name'))?></label><input class="form-control" name="mail_from_name" value="<?=e(get_setting('mail_from_name',app_name()))?>"></div>
        <div class="col-md-5"><label class="form-label"><?=e(t('mail.from_email'))?></label><input type="email" class="form-control" name="mail_from_email" value="<?=e(get_setting('mail_from_email',default_system_email()))?>" required></div>
        <div class="col-md-5"><label class="form-label"><?=e(t('mail.smtp_host'))?></label><input class="form-control" name="smtp_host" value="<?=e(get_setting('smtp_host',''))?>"></div>
        <div class="col-md-2"><label class="form-label"><?=e(t('mail.smtp_port'))?></label><input type="number" min="1" max="65535" class="form-control" name="smtp_port" value="<?=e(get_setting('smtp_port','587'))?>"></div>
        <div class="col-md-2"><label class="form-label"><?=e(t('mail.encryption'))?></label><select class="form-select" name="smtp_encryption"><?php foreach(['none'=>'–','tls'=>'STARTTLS','ssl'=>'SSL/TLS'] as $k=>$v):?><option value="<?=$k?>" <?=get_setting('smtp_encryption','tls')===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></div>
        <div class="col-md-3"><label class="form-label"><?=e(t('mail.smtp_username'))?></label><input class="form-control" name="smtp_username" value="<?=e(get_setting('smtp_username',''))?>"></div>
        <div class="col-md-4"><label class="form-label"><?=e(t('mail.smtp_password'))?></label><input type="password" class="form-control" name="smtp_password" placeholder="<?=e(t('text.unverandert.lassen'))?>"></div>
        <div class="col-12"><button class="btn btn-primary"><?=e(t('text.speichern'))?></button></div>
      </form>
      <hr>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="test_mail">
        <div class="col-md-6"><label class="form-label"><?=e(t('mail.test_recipient'))?></label><input type="email" class="form-control" name="test_email" required></div>
        <div class="col-md-auto"><button class="btn btn-outline-primary"><?=e(t('mail.send_test'))?></button></div>
      </form>
    </div></div>
  </section>
<section id="users">
    <div class="card shadow-sm border-0 mb-4"><div class="card-header bg-body py-3"><h2 class="h5 mb-0"><i class="bi bi-person-plus me-2"></i><?=e(t('text.benutzer.hinzufugen'))?></h2></div><div class="card-body p-4">
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="create_user">
        <div class="col-md-6"><label class="form-label"><?=e(t('text.benutzername'))?></label><input class="form-control" name="username" required></div>
        <div class="col-md-6"><label class="form-label"><?=e(t('user.email'))?></label><input type="email" class="form-control" name="email" required></div>
        <div class="col-md-6"><label class="form-label"><?=e(t('text.passwort'))?></label><input type="password" class="form-control" name="password" minlength="8" required></div>
        <div class="col-md-6"><label class="form-label"><?=e(t('text.rolle'))?></label><select class="form-select" name="role"><option value="user"><?=e(t('text.nutzer'))?></option><option value="admin">Administrator</option></select></div>
        <div class="col-12"><button class="btn btn-primary"><?=e(t('text.benutzer.anlegen'))?></button></div>
      </form>
    </div></div>
    <div class="d-flex align-items-center justify-content-between mb-3"><h2 class="h5 mb-0"><i class="bi bi-people me-2"></i><?=e(t('settings.users_existing'))?></h2><span class="badge text-bg-secondary"><?=count($users)?></span></div>
    <?php foreach($users as $u): ?>
    <div class="card shadow-sm border-0 mb-3"><div class="card-body p-4">
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?=$u['id']?>">
        <div class="col-md-4"><label class="form-label"><?=e(t('text.benutzername'))?></label><input class="form-control" value="<?=e($u['username'])?>" disabled></div>
        <div class="col-md-4"><label class="form-label"><?=e(t('user.email'))?></label><input type="email" class="form-control" name="email" value="<?=e($u['email']??'')?>" required></div>
        <div class="col-md-4"><label class="form-label"><?=e(t('text.rolle'))?></label><select class="form-select" name="role"><option value="user" <?=$u['role']==='user'?'selected':''?>><?=e(t('text.nutzer'))?></option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Administrator</option></select></div>
        <div class="col-md-5"><label class="form-label"><?=e(t('text.neues.passwort'))?></label><input type="password" class="form-control" name="password" minlength="8" placeholder="<?=e(t('text.unverandert.lassen'))?>"></div>
        <div class="col-md-3"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="active<?=$u['id']?>" <?=$u['is_active']?'checked':''?>><label class="form-check-label" for="active<?=$u['id']?>"><?=e(t('text.aktiv'))?></label></div></div>
        <div class="col-md-4 text-md-end"><button class="btn btn-outline-primary"><?=e(t('text.speichern'))?></button></div>
      </form>
      <?php if((int)$u['id'] !== (int)$_SESSION['user_id']):?>
      <form method="post" class="mt-3 text-end" data-confirm="<?=e(t('confirm.delete_user'))?>">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=$u['id']?>">
        <button class="btn btn-sm btn-outline-danger"><?=e(t('text.benutzer.loschen'))?></button>
      </form>
      <?php endif?>
      <div class="small text-body-secondary mt-2"><?=e(t('text.erstellt'))?> <?=e($u['created_at'])?></div>
    </div></div>
    <?php endforeach; ?>
  </div>
</section>
</div>
<?php render_footer();
