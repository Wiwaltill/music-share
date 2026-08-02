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
        $language = (string)($_POST['language'] ?? 'de');
        if (!array_key_exists($language, supported_languages())) $language = 'de';
        set_setting('language', $language);
        flash('success', t('text.seiteneinstellungen.gespeichert'));
        redirect('admin/settings.php');
    }

    if ($action === 'create_user') {
        $username = trim((string)($_POST['username'] ?? ''));
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($username === '' || strlen($password) < 8) {
            flash('danger', 'Benutzername und ein Passwort mit mindestens 8 Zeichen sind erforderlich.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users(username,display_name,password_hash,role,is_active) VALUES(?,?,?,?,1)');
                $stmt->execute([$username,$displayName,password_hash($password,PASSWORD_DEFAULT),$role]);
                flash('success', 'Benutzer wurde angelegt.');
            } catch (PDOException $e) {
                flash('danger', 'Der Benutzername ist bereits vergeben.');
            }
        }
        redirect('admin/settings.php#users');
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');
        if ($id === (int)$_SESSION['user_id'] && (!$active || $role !== 'admin')) {
            flash('danger', 'Das eigene aktive Administratorkonto kann nicht deaktiviert oder herabgestuft werden.');
            redirect('admin/settings.php#users');
        }
        if ($password !== '' && strlen($password) < 8) {
            flash('danger', 'Ein neues Passwort muss mindestens 8 Zeichen lang sein.');
            redirect('admin/settings.php#users');
        }
        if ($password !== '') {
            $stmt=$pdo->prepare('UPDATE users SET display_name=?,role=?,is_active=?,password_hash=? WHERE id=?');
            $stmt->execute([$displayName,$role,$active,password_hash($password,PASSWORD_DEFAULT),$id]);
        } else {
            $stmt=$pdo->prepare('UPDATE users SET display_name=?,role=?,is_active=? WHERE id=?');
            $stmt->execute([$displayName,$role,$active,$id]);
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

$users = $pdo->query('SELECT id,username,display_name,role,is_active,created_at FROM users ORDER BY username')->fetchAll();
render_header(t('page.settings.title'), true);
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('page.settings.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('text.seitendarstellung.und.zugange.verwalten'))?></p></div><a class="btn btn-outline-primary" href="update.php"><?=e(t('text.updates'))?></a></div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-body p-4">
      <h2 class="h5"><?=e(t('text.allgemein'))?></h2>
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
        <div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" role="switch" id="statistics_enabled" name="statistics_enabled" <?=statistics_enabled()?'checked':''?>> <label class="form-check-label" for="statistics_enabled"><?=e(t('stats.enable'))?></label></div><div class="form-text"><?=e(t('stats.privacy_help'))?></div><button class="btn btn-primary mt-3"><?=e(t('text.speichern'))?></button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-7" id="users">
    <div class="card shadow-sm mb-4"><div class="card-body p-4">
      <h2 class="h5"><?=e(t('text.benutzer.hinzufugen'))?></h2>
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="create_user">
        <div class="col-md-6"><label class="form-label"><?=e(t('text.benutzername'))?></label><input class="form-control" name="username" required></div>
        <div class="col-md-6"><label class="form-label"><?=e(t('text.anzeigename'))?></label><input class="form-control" name="display_name"></div>
        <div class="col-md-6"><label class="form-label"><?=e(t('text.passwort'))?></label><input type="password" class="form-control" name="password" minlength="8" required></div>
        <div class="col-md-6"><label class="form-label"><?=e(t('text.rolle'))?></label><select class="form-select" name="role"><option value="user"><?=e(t('text.nutzer'))?></option><option value="admin">Administrator</option></select></div>
        <div class="col-12"><button class="btn btn-primary"><?=e(t('text.benutzer.anlegen'))?></button></div>
      </form>
    </div></div>
    <?php foreach($users as $u): ?>
    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?=$u['id']?>">
        <div class="col-md-4"><label class="form-label"><?=e(t('text.benutzername'))?></label><input class="form-control" value="<?=e($u['username'])?>" disabled></div>
        <div class="col-md-4"><label class="form-label"><?=e(t('text.anzeigename'))?></label><input class="form-control" name="display_name" value="<?=e($u['display_name'])?>"></div>
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
</div>
<?php render_footer();
