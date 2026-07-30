<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$root = dirname(__DIR__);
$error = null;
$release = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'check') {
            $release = latest_github_release(true);
        } elseif ($action === 'install') {
            $release = latest_github_release(true);
            if (!release_update_available($release)) throw new RuntimeException('Es ist kein neueres Release verfügbar.');
            $asset = release_zip_asset($release);
            if (!$asset || empty($asset['browser_download_url'])) {
                throw new RuntimeException('Dem GitHub-Release ist keine ZIP-Datei angehängt. Bitte eine vollständige Update-ZIP als Release Asset hochladen.');
            }
            if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZipArchive ist auf dem Server nicht verfügbar.');
            $tmpBase = $root . '/storage/update-' . bin2hex(random_bytes(6));
            $zipFile = $tmpBase . '.zip';
            $extractDir = $tmpBase . '-files';
            if (!is_dir($root . '/storage') && !mkdir($root . '/storage', 0775, true)) throw new RuntimeException('Storage-Verzeichnis ist nicht beschreibbar.');
            download_remote_file((string)$asset['browser_download_url'], $zipFile);
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
            @unlink($zipFile);
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
$asset = $release ? release_zip_asset($release) : null;
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
        <?php if (!empty($release['name'])): ?><div class="mt-1"><?=e((string)$release['name'])?></div><?php endif; ?>
        <?php if ($available): ?>
          <div class="alert alert-success mt-3 mb-3">Eine neue Version ist verfügbar.</div>
          <?php if ($asset): ?>
          <form method="post" onsubmit="return confirm('Update jetzt installieren? Vorher wird automatisch ein Backup erstellt.')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="install"><button class="btn btn-primary">Version <?=e($latest)?> installieren</button></form>
          <?php else: ?><div class="alert alert-warning mt-3 mb-0">Das Release enthält kein ZIP-Asset. Hänge beim Erstellen des Releases eine vollständige ZIP der Anwendung an.</div><?php endif; ?>
        <?php else: ?><div class="alert alert-secondary mt-3 mb-0">Die Installation ist aktuell.</div><?php endif; ?>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-5"><div class="card shadow-sm"><div class="card-body p-4"><h2 class="h5">Update-Schutz</h2><p class="mb-2">Vor jeder Installation wird ein Backup der Programmdateien unter <code>storage/backups/</code> angelegt.</p><p class="mb-0"><code>config.php</code>, <code>uploads/</code> und <code>storage/</code> werden durch Updates nicht überschrieben.</p></div></div></div>
</div>
<?php render_footer();
