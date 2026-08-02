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
        flash('success', 'Seiteneinstellungen gespeichert.');
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
render_header('Einstellungen', true);
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1">Einstellungen</h1><p class="text-body-secondary mb-0">Seitendarstellung und Zugänge verwalten.</p></div><a class="btn btn-outline-primary" href="update.php">Updates</a></div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-body p-4">
      <h2 class="h5">Allgemein</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="site">
        <label class="form-label" for="site_name">Site Name</label>
        <input class="form-control" id="site_name" name="site_name" maxlength="120" value="<?=e(app_name())?>" required>
        <div class="form-text">Wird im Backend, im Seitentitel und in der Navigation verwendet.</div><div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="album_colors_enabled" name="album_colors_enabled" <?=get_setting('album_colors_enabled','0')==='1'?'checked':''?>> <label class="form-check-label" for="album_colors_enabled">Albumfarben aus dem Cover auf öffentlichen Seiten verwenden</label></div><div class="form-text">Ist die Funktion deaktiviert, wird die neutrale Glasdarstellung verwendet.</div>
        <button class="btn btn-primary mt-3">Speichern</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-7" id="users">
    <div class="card shadow-sm mb-4"><div class="card-body p-4">
      <h2 class="h5">Benutzer hinzufügen</h2>
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="create_user">
        <div class="col-md-6"><label class="form-label">Benutzername</label><input class="form-control" name="username" required></div>
        <div class="col-md-6"><label class="form-label">Anzeigename</label><input class="form-control" name="display_name"></div>
        <div class="col-md-6"><label class="form-label">Passwort</label><input type="password" class="form-control" name="password" minlength="8" required></div>
        <div class="col-md-6"><label class="form-label">Rolle</label><select class="form-select" name="role"><option value="user">Nutzer</option><option value="admin">Administrator</option></select></div>
        <div class="col-12"><button class="btn btn-primary">Benutzer anlegen</button></div>
      </form>
    </div></div>
    <?php foreach($users as $u): ?>
    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?=$u['id']?>">
        <div class="col-md-4"><label class="form-label">Benutzername</label><input class="form-control" value="<?=e($u['username'])?>" disabled></div>
        <div class="col-md-4"><label class="form-label">Anzeigename</label><input class="form-control" name="display_name" value="<?=e($u['display_name'])?>"></div>
        <div class="col-md-4"><label class="form-label">Rolle</label><select class="form-select" name="role"><option value="user" <?=$u['role']==='user'?'selected':''?>>Nutzer</option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Administrator</option></select></div>
        <div class="col-md-5"><label class="form-label">Neues Passwort</label><input type="password" class="form-control" name="password" minlength="8" placeholder="Unverändert lassen"></div>
        <div class="col-md-3"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="active<?=$u['id']?>" <?=$u['is_active']?'checked':''?>><label class="form-check-label" for="active<?=$u['id']?>">Aktiv</label></div></div>
        <div class="col-md-4 text-md-end"><button class="btn btn-outline-primary">Speichern</button></div>
      </form>
      <?php if((int)$u['id'] !== (int)$_SESSION['user_id']):?>
      <form method="post" class="mt-3 text-end" data-confirm="Benutzer wirklich löschen?">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=$u['id']?>">
        <button class="btn btn-sm btn-outline-danger">Benutzer löschen</button>
      </form>
      <?php endif?>
      <div class="small text-body-secondary mt-2">Erstellt: <?=e($u['created_at'])?></div>
    </div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php render_footer();
