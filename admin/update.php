<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$root = dirname(__DIR__);
$error = null;
$release = null;

if (isset($_GET['download_migration'])) {
    $backupName = basename((string)$_GET['download_migration']);
    $backupPath = migration_backup_directory($root) . '/' . $backupName;
    if ($backupName === '' || !is_file($backupPath) || !in_array($backupPath, migration_backups($root), true)) {
        http_response_code(404);
        exit('Migrationsbackup wurde nicht gefunden.');
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . addcslashes($backupName, '\"') . '"');
    header('Content-Length: ' . filesize($backupPath));
    header('X-Content-Type-Options: nosniff');
    readfile($backupPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create_migration_backup') {
            $backup = create_migration_backup($root, $pdo);
            flash('success', 'Migrationsbackup wurde erstellt: ' . basename($backup));
            redirect('admin/update.php#migration-backups');
        } elseif ($action === 'upload_migration_backup') {
            if (!isset($_FILES['migration_backup']) || !is_uploaded_file($_FILES['migration_backup']['tmp_name'] ?? '')) {
                throw new RuntimeException('Bitte wähle eine Migrationsbackup-ZIP aus.');
            }
            if ((int)($_FILES['migration_backup']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Migrationsbackup konnte nicht hochgeladen werden.');
            }
            $original = (string)($_FILES['migration_backup']['name'] ?? 'migration.zip');
            if (!str_ends_with(strtolower($original), '.zip')) throw new RuntimeException('Es sind nur ZIP-Dateien erlaubt.');
            validate_migration_backup((string)$_FILES['migration_backup']['tmp_name']);
            $dir = migration_backup_directory($root);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Backup-Verzeichnis ist nicht beschreibbar.');
            $target = $dir . '/music-share-migration-import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
            if (!move_uploaded_file((string)$_FILES['migration_backup']['tmp_name'], $target)) throw new RuntimeException('Migrationsbackup konnte nicht gespeichert werden.');
            flash('success', 'Migrationsbackup wurde hochgeladen und geprüft.');
            redirect('admin/update.php#migration-backups');
        } elseif ($action === 'restore_migration_backup') {
            $backupName = basename((string)($_POST['backup'] ?? ''));
            $backupPath = migration_backup_directory($root) . '/' . $backupName;
            if ($backupName === '' || !is_file($backupPath) || !in_array($backupPath, migration_backups($root), true)) throw new RuntimeException('Migrationsbackup wurde nicht gefunden.');
            restore_migration_backup($root, $pdo, $backupPath);
            flash('success', 'Migrationsbackup wurde wiederhergestellt. Der vorherige Zustand wurde automatisch gesichert.');
            redirect('admin/update.php#migration-backups');
        } elseif ($action === 'delete_migration_backup') {
            $backupName = basename((string)($_POST['backup'] ?? ''));
            $backupPath = migration_backup_directory($root) . '/' . $backupName;
            if ($backupName === '' || !is_file($backupPath) || !in_array($backupPath, migration_backups($root), true)) throw new RuntimeException('Migrationsbackup wurde nicht gefunden.');
            if (!@unlink($backupPath)) throw new RuntimeException('Migrationsbackup konnte nicht gelöscht werden.');
            flash('success', 'Migrationsbackup wurde gelöscht.');
            redirect('admin/update.php#migration-backups');
        } elseif ($action === 'delete_backup') {
            $backupName = basename((string)($_POST['backup'] ?? ''));
            $backupPath = $root . '/storage/backups/' . $backupName;
            if ($backupName === '' || !is_file($backupPath) || !in_array($backupPath, application_backups($root), true)) {
                throw new RuntimeException('Backup wurde nicht gefunden.');
            }
            if (!@unlink($backupPath)) {
                throw new RuntimeException('Backup konnte nicht vom Server gelöscht werden.');
            }
            flash('success', 'Backup wurde gelöscht.');
            redirect('admin/update.php');
        } elseif ($action === 'rollback') {
            $backupName = basename((string)($_POST['backup'] ?? ''));
            create_application_backup($root);
            restore_application_backup($root, $backupName);
            flash('success', 'Backup wurde wiederhergestellt. Vorher wurde eine zusätzliche Sicherung erstellt.');
            redirect('admin/update.php');
        } elseif ($action === 'check') {
            $release = latest_github_release(true);
        } elseif ($action === 'install') {
            $release = latest_github_release(true);
            if (!release_update_available($release)) throw new RuntimeException('Es ist kein neueres Release verfügbar.');
            $package = release_zip_source($release);
            if (!$package || empty($package['url'])) {
                throw new RuntimeException('Für dieses GitHub-Release konnte keine ZIP-Datei gefunden werden.');
            }
            if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZipArchive ist auf dem Server nicht verfügbar.');
            $tmpBase = $root . '/storage/update-' . bin2hex(random_bytes(6));
            $zipFile = $tmpBase . '.zip';
            $extractDir = $tmpBase . '-files';
            if (!is_dir($root . '/storage') && !mkdir($root . '/storage', 0775, true)) throw new RuntimeException('Storage-Verzeichnis ist nicht beschreibbar.');
            download_remote_file((string)$package['url'], $zipFile);
            mkdir($extractDir, 0775, true);
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) throw new RuntimeException('Update-ZIP konnte nicht geöffnet werden.');
            if (!$zip->extractTo($extractDir)) throw new RuntimeException('Update-ZIP konnte nicht entpackt werden.');
            $zip->close();
            $source = $extractDir;
            $entries = array_values(array_filter(scandir($extractDir) ?: [], fn($v) => !in_array($v, ['.','..'], true)));
            if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) $source = $extractDir . '/' . $entries[0];
            if (!is_file($source . '/includes/bootstrap.php') || !is_file($source . '/includes/version.php')) {
                throw new RuntimeException('Die Release-ZIP enthält keine gültige Music-Share-Installation.');
            }
            $newVersionFile = (string)file_get_contents($source . '/includes/version.php');
            if (!preg_match("/APP_VERSION\\s*=\\s*['\"]([^'\"]+)/", $newVersionFile, $m)) throw new RuntimeException('Versionsinformation fehlt in der Update-ZIP.');
            if (version_compare(normalized_version($m[1]), APP_VERSION, '<=')) throw new RuntimeException('Die Update-ZIP enthält keine neuere Version.');
            $backup = create_application_backup($root);
            recursive_copy_update($source, $root, ['config.php','uploads','storage','.git']);
            if (is_file($zipFile) && !@unlink($zipFile)) {
                throw new RuntimeException('Das Update wurde installiert, aber die heruntergeladene ZIP konnte nicht gelöscht werden.');
            }
            if (is_dir($extractDir)) {
                remove_directory_tree($extractDir);
            }
            flash('success', 'Update auf Version ' . normalized_version($m[1]) . ' wurde installiert. Backup: ' . basename($backup));
            redirect('admin/update.php');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($release === null) {
    try { $release = latest_github_release(false); } catch (Throwable $e) { $error = $error ?: $e->getMessage(); }
}

render_header('Updates', true);
$latest = normalized_version((string)($release['tag_name'] ?? ''));
$available = $release ? release_update_available($release) : false;
$package = $release ? release_zip_source($release) : null;
$backups = application_backups($root);
$migrationBackups = migration_backups($root);
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1">Updates</h1><p class="text-body-secondary mb-0">Neue Versionen direkt aus den GitHub-Releases installieren.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start gap-3"><div><div class="text-body-secondary small">Installierte Version</div><div class="fs-4 fw-semibold"><?=e(APP_VERSION)?></div></div>
      <form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="check"><button class="btn btn-outline-primary">Jetzt prüfen</button></form></div>
      <hr>
      <?php if ($release): ?>
        <div class="text-body-secondary small">Neuestes GitHub-Release</div><div class="fs-4 fw-semibold"><?=e($latest ?: 'Unbekannt')?></div>
        <?php if (!empty($release['name'])): ?><div class="mt-1"><?=e((string)$release['name'])?></div><?php endif; ?><?php if (!empty($release['body'])): ?><details class="mt-3"><summary class="fw-semibold">Changelog anzeigen</summary><div class="release-notes mt-2 p-3 rounded bg-body-tertiary"><?=nl2br(e((string)$release['body']))?></div></details><?php endif; ?>
        <?php if ($available): ?>
          <div class="alert alert-success mt-3 mb-3">Eine neue Version ist verfügbar.</div>
          <?php if ($package): ?>
          <form method="post" onsubmit="return confirm('Update jetzt installieren? Vorher wird automatisch ein Backup erstellt.')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="install"><button class="btn btn-primary">Version <?=e($latest)?> installieren</button></form><?php if (($package['type'] ?? '') === 'source'): ?><div class="small text-body-secondary mt-2">Verwendet die automatisch von GitHub erzeugte <strong>Source code (zip)</strong>-Datei.</div><?php else: ?><div class="small text-body-secondary mt-2">Verwendet das Release-Asset <strong><?=e((string)($package['name'] ?? 'ZIP'))?></strong>.</div><?php endif; ?>
          <?php else: ?><div class="alert alert-warning mt-3 mb-0">Für dieses Release konnte keine ZIP-Datei gefunden werden.</div><?php endif; ?>
        <?php else: ?><div class="alert alert-secondary mt-3 mb-0">Die Installation ist aktuell.</div><?php endif; ?>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-5"><div class="card shadow-sm"><div class="card-body p-4"><h2 class="h5">Update-Schutz</h2><p class="mb-2">Vor jeder Installation wird ein Backup der Programmdateien unter <code>storage/backups/</code> angelegt.</p><p class="mb-0"><code>config.php</code>, <code>uploads/</code> und <code>storage/</code> werden durch Updates nicht überschrieben.</p></div></div></div>
</div>
<div class="card shadow-sm mt-4"><div class="card-body p-4"><h2 class="h5">Backups und Rollback</h2><p class="text-body-secondary">Vor Updates und Wiederherstellungen wird automatisch gesichert.</p><div class="list-group"><?php foreach($backups as $backup):?><div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2"><div><div class="fw-semibold"><?=e(basename($backup))?></div><div class="small text-body-secondary"><?=date('d.m.Y H:i',filemtime($backup))?> · <?=format_bytes((int)filesize($backup))?></div></div><div class="d-flex gap-2"><form method="post" onsubmit="return confirm('Dieses Backup wiederherstellen? Aktuelle Programmdateien werden vorher zusätzlich gesichert.')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="rollback"><input type="hidden" name="backup" value="<?=e(basename($backup))?>"><button class="btn btn-sm btn-outline-warning">Wiederherstellen</button></form><form method="post" onsubmit="return confirm('Dieses Backup endgültig vom Server löschen?')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="backup" value="<?=e(basename($backup))?>"><button class="btn btn-sm btn-danger d-inline-flex align-items-center justify-content-center" title="Backup löschen" aria-label="Backup löschen" style="width:2rem;height:2rem;padding:0"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H6a2 2 0 0 1 4 0h3.5a1 1 0 0 1 1 1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM6.5 2h3a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1Z"/></svg></button></form></div></div><?php endforeach?><?php if(!$backups):?><div class="text-body-secondary">Noch keine Backups vorhanden.</div><?php endif?></div></div></div>

<div class="card shadow-sm mt-4" id="migration-backups">
  <div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div><h2 class="h5 mb-1">Migration und vollständige Datensicherung</h2><p class="text-body-secondary mb-0">Sichert Datenbank, Cover und Audiodateien für den Umzug auf eine andere installierte Music-Share-Instanz.</p></div>
      <form method="post" onsubmit="return confirm('Jetzt ein vollständiges Migrationsbackup erstellen? Je nach Umfang der Audiodateien kann die ZIP groß werden.')">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="create_migration_backup">
        <button class="btn btn-primary">Migrationsbackup erstellen</button>
      </form>
    </div>
    <div class="alert alert-warning small"><strong>Wiederherstellung ersetzt vorhandene Daten:</strong> Datenbank, Cover und Audiodateien der Zielinstanz werden durch den Inhalt des Backups ersetzt. Die lokale <code>config.php</code> und damit Basis-URL sowie Datenbankzugang bleiben erhalten. Vorher wird automatisch ein Sicherungspunkt erstellt.</div>
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-4">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="upload_migration_backup">
      <div class="col-md"><label class="form-label" for="migration_backup">Backup von einer anderen Instanz hochladen</label><input class="form-control" type="file" id="migration_backup" name="migration_backup" accept=".zip,application/zip" required></div>
      <div class="col-md-auto"><button class="btn btn-outline-primary w-100">ZIP hochladen und prüfen</button></div>
    </form>
    <div class="list-group">
      <?php foreach ($migrationBackups as $backup): ?>
      <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><div class="fw-semibold"><?=e(basename($backup))?></div><div class="small text-body-secondary"><?=date('d.m.Y H:i', filemtime($backup))?> · <?=format_bytes((int)filesize($backup))?></div></div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-sm btn-outline-primary" href="<?=e(base_url('admin/update.php?download_migration=' . rawurlencode(basename($backup))))?>">Download</a>
          <form method="post" onsubmit="return confirm('Dieses Migrationsbackup wiederherstellen? Vorhandene Datenbank und Uploads werden ersetzt.')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="restore_migration_backup"><input type="hidden" name="backup" value="<?=e(basename($backup))?>"><button class="btn btn-sm btn-warning">Wiederherstellen</button></form>
          <form method="post" onsubmit="return confirm('Dieses Migrationsbackup endgültig löschen?')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete_migration_backup"><input type="hidden" name="backup" value="<?=e(basename($backup))?>"><button class="btn btn-sm btn-danger d-inline-flex align-items-center justify-content-center" title="Backup löschen" aria-label="Backup löschen" style="width:2rem;height:2rem;padding:0"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H6a2 2 0 0 1 4 0h3.5a1 1 0 0 1 1 1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM6.5 2h3a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1Z"/></svg></button></form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$migrationBackups): ?><div class="text-body-secondary">Noch keine Migrationsbackups vorhanden.</div><?php endif; ?>
    </div>
  </div>
</div>
<?php render_footer();
