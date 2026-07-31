<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function base_url(string $path = ''): string {
    global $config;
    $base = rtrim((string)($config['app']['base_url'] ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $script = preg_replace('#/(admin|install)$#', '', $script);
        $base = $scheme . '://' . $host . rtrim($script, '/');
    }
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $path): never { header('Location: ' . base_url($path)); exit; }
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_login(): void { if (!is_logged_in()) redirect('admin/login.php'); }
function current_user(): ?array {
    global $pdo;
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = $pdo->prepare('SELECT id,username,display_name,role,is_active FROM users WHERE id=?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: false;
        if (!$user || !(int)$user['is_active']) { unset($_SESSION['user_id']); return null; }
    }
    return $user ?: null;
}
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function require_admin(): void { require_login(); if (!is_admin()) { http_response_code(403); exit('Keine Berechtigung.'); } }
function can_access_album(int $albumId): bool {
    global $pdo;
    if ($albumId < 1 || !is_logged_in()) return false;
    if (is_admin()) return true;
    $uid = (int)(current_user()['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT 1 FROM albums a LEFT JOIN album_collaborators ac ON ac.album_id=a.id AND ac.user_id=? WHERE a.id=? AND (a.owner_user_id=? OR ac.user_id IS NOT NULL) LIMIT 1');
    $stmt->execute([$uid,$albumId,$uid]);
    return (bool)$stmt->fetchColumn();
}
function can_manage_album_access(int $albumId): bool {
    global $pdo;
    if ($albumId < 1 || !is_logged_in()) return false;
    if (is_admin()) return true;
    $stmt=$pdo->prepare('SELECT 1 FROM albums WHERE id=? AND owner_user_id=?');
    $stmt->execute([$albumId,(int)(current_user()['id'] ?? 0)]);
    return (bool)$stmt->fetchColumn();
}
function require_album_access(int $albumId): void {
    require_login();
    if (!can_access_album($albumId)) { http_response_code(403); exit('Keine Berechtigung für dieses Album.'); }
}
function require_album_owner_or_admin(int $albumId): void {
    require_login();
    if (!can_manage_album_access($albumId)) { http_response_code(403); exit('Nur der Ersteller oder ein Administrator darf diese Aktion ausführen.'); }
}

function get_setting(string $key, ?string $default = null): ?string {
    global $pdo;
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $cache[$key] = ($value === false ? $default : (string)$value);
    } catch (Throwable $e) { return $default; }
}
function set_setting(string $key, string $value): void {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key,$value]);
}
function app_name(): string {
    global $config;
    return trim((string)get_setting('site_name', (string)($config['app']['name'] ?? 'Album Share'))) ?: 'Album Share';
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function ini_bytes(string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $last = strtolower($value[strlen($value) - 1]);
    $number = (float)$value;
    return match ($last) {
        'g' => (int)round($number * 1024 * 1024 * 1024),
        'm' => (int)round($number * 1024 * 1024),
        'k' => (int)round($number * 1024),
        default => (int)$number,
    };
}
function format_bytes(int $bytes): string {
    if ($bytes >= 1024 * 1024 * 1024) return rtrim(rtrim(number_format($bytes / (1024 ** 3), 2, ',', '.'), '0'), ',') . ' GB';
    if ($bytes >= 1024 * 1024) return rtrim(rtrim(number_format($bytes / (1024 ** 2), 2, ',', '.'), '0'), ',') . ' MB';
    if ($bytes >= 1024) return rtrim(rtrim(number_format($bytes / 1024, 2, ',', '.'), '0'), ',') . ' KB';
    return $bytes . ' B';
}
function verify_csrf(): void {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMax = ini_bytes((string)ini_get('post_max_size'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contentLength > 0 && empty($_POST) && empty($_FILES)) {
        http_response_code(413);
        $limit = $postMax > 0 ? ' Das PHP-Limit post_max_size liegt aktuell bei ' . format_bytes($postMax) . '.' : '';
        exit('Der Upload ist größer als vom Server erlaubt.' . $limit . ' Bitte post_max_size und upload_max_filesize erhöhen.');
    }
    $sessionToken = (string)($_SESSION['csrf'] ?? '');
    $postedToken = (string)($_POST['csrf'] ?? '');
    if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(419);
        exit('Die Sitzung ist abgelaufen oder das Formular ist nicht mehr gültig. Bitte die Seite neu laden und den Upload erneut starten.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type','message'); }
function get_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function random_token(int $bytes = 24): string { return bin2hex(random_bytes($bytes)); }

/** Creates a compact, URL-safe and database-unique share token. */
function create_short_share_token(PDO $pdo, int $length = 10): string {
    $alphabet = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $max = strlen($alphabet) - 1;
    do {
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }
        $check = $pdo->prepare('SELECT 1 FROM shares WHERE token = ? LIMIT 1');
        $check->execute([$token]);
    } while ($check->fetchColumn());
    return $token;
}
function slugify(string $value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
    return trim($value, '-') ?: 'album';
}
function upload_file(array $file, string $targetDir, array $allowedMime, int $maxBytes): string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE => 'Die Datei überschreitet upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE => 'Die Datei überschreitet die im Formular erlaubte Größe.',
            UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Es wurde keine Datei ausgewählt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Auf dem Server fehlt das temporäre Upload-Verzeichnis.',
            UPLOAD_ERR_CANT_WRITE => 'Die Datei konnte auf dem Server nicht gespeichert werden.',
            UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload abgebrochen.',
            default => 'Upload fehlgeschlagen (Fehlercode ' . $error . ').',
        };
        throw new RuntimeException($message);
    }
    if (($file['size'] ?? 0) > $maxBytes) throw new RuntimeException('Datei ist zu groß.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) throw new RuntimeException('Dateityp nicht erlaubt: ' . $mime);
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','audio/mpeg'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/flac'=>'flac','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/ogg'=>'ogg'];
    $ext = $extMap[$mime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($targetDir, '/') . '/' . $name)) throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    return $name;
}
function render_header(string $title, bool $admin = false): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    global $config;
    $app = e(app_name());
    $flashes = get_flashes();
    echo '<!doctype html><html lang="de"><head><script>(function(){var t=localStorage.getItem(\"musicshare-theme\")||\"auto\";var d=t===\"auto\"?(matchMedia(\"(prefers-color-scheme: dark)\").matches?\"dark\":\"light\"):t;document.documentElement.setAttribute(\"data-bs-theme\",d)})();</script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' – '.$app.'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="'.base_url('assets/css/app.css?v=' . rawurlencode(APP_VERSION)).'"></head><body class="bg-body-tertiary">';
    echo '<nav class="navbar navbar-expand-lg bg-dark navbar-dark"><div class="container"><a class="navbar-brand fw-semibold" href="'.base_url($admin?'admin/index.php':'').'">'.$app.'</a>';
    if ($admin && is_logged_in()) {
        echo '<div class="ms-auto d-flex gap-2 align-items-center"><button class="btn btn-sm btn-outline-light" type="button" id="themeToggle" title="Darstellung wechseln">◐</button><a class="btn btn-sm btn-outline-light" href="'.base_url('admin/index.php').'">Alben</a>';
        if (is_admin()) echo '<a class="btn btn-sm btn-outline-light" href="'.base_url('admin/settings.php').'">Einstellungen</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="'.base_url('admin/logout.php').'">Abmelden</a></div>';
    }
    echo '</div></nav><main class="container py-4">';
    foreach ($flashes as $f) echo '<div class="alert alert-'.e($f['type']).'">'.e($f['message']).'</div>';
}
function render_footer(): void {
    echo '</main>';
    if (is_admin_request()) {
        echo '<footer class="border-top bg-body py-3 mt-auto"><div class="container d-flex flex-wrap justify-content-between gap-2 small text-body-secondary">';
        echo '<span>Version ' . e(APP_VERSION) . '</span><span><a class="text-body-secondary" href="' . e(APP_GITHUB_URL) . '" target="_blank" rel="noopener noreferrer">GitHub</a>';
        if (is_logged_in() && is_admin()) echo ' · <a class="text-body-secondary" href="' . base_url('admin/update.php') . '">Nach Updates suchen</a>';
        echo '</span></div></footer>';
    }
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script>(function(){var k="musicshare-theme",m=["auto","light","dark"],q=window.matchMedia("(prefers-color-scheme: dark)");function a(v){var x=v==="auto"?(q.matches?"dark":"light"):v;document.documentElement.setAttribute("data-bs-theme",x);localStorage.setItem(k,v);var b=document.getElementById("themeToggle");if(b){b.textContent=v==="light"?"☀":v==="dark"?"☾":"◐";b.title="Darstellung: "+(v==="light"?"Hell":v==="dark"?"Dunkel":"Automatisch");}}var v=localStorage.getItem(k);if(m.indexOf(v)<0)v="auto";a(v);var b=document.getElementById("themeToggle");if(b)b.addEventListener("click",function(){v=m[(m.indexOf(v)+1)%m.length];a(v);});if(q.addEventListener)q.addEventListener("change",function(){if(v==="auto")a(v);});})();</script>';
    echo '<script src="'.base_url('assets/js/player.js?v=' . rawurlencode(APP_VERSION) . '-' . time()).'"></script></body></html>';
}

function share_access_granted(array $share): bool {
    if (!empty($share['expires_at']) && strtotime((string)$share['expires_at']) < time()) return false;
    if (!empty($share['password_hash']) && empty($_SESSION['share_ok_'.$share['id']])) return false;
    return true;
}

function is_admin_request(): bool {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    return str_contains($script, '/admin/');
}

function github_api_request(string $url): array {
    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: Music-Share-Updater/' . APP_VERSION,
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('GitHub konnte nicht abgefragt werden' . ($error ? ': ' . $error : ' (HTTP ' . $status . ').'));
        }
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 25, 'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ]]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($body === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new RuntimeException('GitHub konnte nicht abgefragt werden. cURL ist nicht verfügbar und URL-Zugriffe sind möglicherweise deaktiviert.');
        }
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) throw new RuntimeException('GitHub hat keine gültige Antwort geliefert.');
    return $data;
}

function latest_github_release(bool $force = false): array {
    $cacheFile = dirname(__DIR__) . '/storage/github-release-cache.json';
    if (!$force && is_file($cacheFile) && filemtime($cacheFile) > time() - 900) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }
    $release = github_api_request('https://api.github.com/repos/' . APP_REPOSITORY . '/releases/latest');
    if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0775, true);
    @file_put_contents($cacheFile, json_encode($release, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    return $release;
}

function normalized_version(string $version): string {
    return ltrim(trim($version), "vV \t\n\r\0\x0B");
}

function release_update_available(array $release): bool {
    $latest = normalized_version((string)($release['tag_name'] ?? '0.0.0'));
    return $latest !== '' && version_compare($latest, APP_VERSION, '>');
}

function release_zip_source(array $release): ?array {
    $assets = is_array($release['assets'] ?? null) ? $release['assets'] : [];
    foreach ($assets as $asset) {
        $name = strtolower((string)($asset['name'] ?? ''));
        if (str_ends_with($name, '.zip') && (str_contains($name, 'music-share') || str_contains($name, 'album-share'))) {
            return ['url' => (string)($asset['browser_download_url'] ?? ''), 'type' => 'asset', 'name' => (string)($asset['name'] ?? 'Release-ZIP')];
        }
    }
    foreach ($assets as $asset) {
        if (str_ends_with(strtolower((string)($asset['name'] ?? '')), '.zip')) {
            return ['url' => (string)($asset['browser_download_url'] ?? ''), 'type' => 'asset', 'name' => (string)($asset['name'] ?? 'Release-ZIP')];
        }
    }
    $zipball = trim((string)($release['zipball_url'] ?? ''));
    if ($zipball !== '') {
        return ['url' => $zipball, 'type' => 'source', 'name' => 'GitHub Source code (zip)'];
    }
    return null;
}

function release_zip_asset(array $release): ?array {
    $source = release_zip_source($release);
    if (!$source || ($source['type'] ?? '') !== 'asset') return null;
    return ['browser_download_url' => $source['url'], 'name' => $source['name']];
}

function download_remote_file(string $url, string $target): void {
    $out = fopen($target, 'wb');
    if (!$out) throw new RuntimeException('Temporäre Updatedatei konnte nicht erstellt werden.');

    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    $isGithubApiArchive = $host === 'api.github.com' && (str_contains($url, '/zipball') || str_contains($url, '/tarball'));
    $headers = [
        'Accept: ' . ($isGithubApiArchive ? 'application/vnd.github+json' : 'application/octet-stream'),
        'User-Agent: Music-Share-Updater/' . APP_VERSION,
    ];
    if ($isGithubApiArchive) {
        $headers[] = 'X-GitHub-Api-Version: 2022-11-28';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $out,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FAILONERROR => false,
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($out);
        if (!$ok || $status < 200 || $status >= 300) {
            @unlink($target);
            throw new RuntimeException('Update-ZIP konnte nicht heruntergeladen werden' . ($error ? ': ' . $error : ' (HTTP ' . $status . ').'));
        }
        if ($contentType !== '' && str_contains(strtolower($contentType), 'application/json')) {
            @unlink($target);
            throw new RuntimeException('GitHub hat statt der ZIP-Datei eine API-Antwort geliefert.');
        }
    } else {
        fclose($out);
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 300,
            'follow_location' => 1,
            'max_redirects' => 10,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
        ]]);
        $data = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($data === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
            @unlink($target);
            $status = preg_match('/\s(\d{3})\s/', $statusLine, $m) ? ' (HTTP ' . $m[1] . ')' : '';
            throw new RuntimeException('Update-ZIP konnte nicht heruntergeladen werden' . $status . '.');
        }
        if (@file_put_contents($target, $data) === false) {
            @unlink($target);
            throw new RuntimeException('Update-ZIP konnte nicht gespeichert werden.');
        }
    }

    if (!is_file($target) || filesize($target) < 1000) {
        @unlink($target);
        throw new RuntimeException('Die heruntergeladene Updatedatei ist ungültig.');
    }

    $signature = (string)@file_get_contents($target, false, null, 0, 4);
    if ($signature !== "PK\x03\x04" && $signature !== "PK\x05\x06" && $signature !== "PK\x07\x08") {
        @unlink($target);
        throw new RuntimeException('Die heruntergeladene Datei ist kein gültiges ZIP-Archiv.');
    }
}

function recursive_copy_update(string $source, string $destination, array $protectedTopLevel): void {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        $top = explode('/', $relative, 2)[0];
        if (in_array($top, $protectedTopLevel, true)) continue;
        $target = $destination . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) throw new RuntimeException('Verzeichnis konnte nicht erstellt werden: ' . $relative);
        } else {
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
            if (!copy($item->getPathname(), $target)) throw new RuntimeException('Datei konnte nicht aktualisiert werden: ' . $relative);
        }
    }
}

function create_application_backup(string $root): string {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('Für Updates wird die PHP-Erweiterung ZipArchive benötigt.');
    $backupDir = $root . '/storage/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) throw new RuntimeException('Backup-Verzeichnis ist nicht beschreibbar.');
    $file = $backupDir . '/before-update-' . APP_VERSION . '-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Backup konnte nicht erstellt werden.');
    $skip = ['uploads', 'storage', '.git'];
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($items as $item) {
        if (!$item->isFile()) continue;
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if (in_array(explode('/', $relative, 2)[0], $skip, true)) continue;
        $zip->addFile($item->getPathname(), $relative);
    }
    $zip->close();
    return $file;
}


function restore_application_backup(string $root, string $backupName): void {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZipArchive ist nicht verfügbar.');
    $safe = basename($backupName);
    $file = $root . '/storage/backups/' . $safe;
    if (!is_file($file) || !str_ends_with(strtolower($safe), '.zip')) throw new RuntimeException('Backup wurde nicht gefunden.');
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('Backup konnte nicht geöffnet werden.');
    $tmp = $root . '/storage/restore-' . bin2hex(random_bytes(5));
    if (!mkdir($tmp, 0775, true) || !$zip->extractTo($tmp)) throw new RuntimeException('Backup konnte nicht entpackt werden.');
    $zip->close();
    recursive_copy_update($tmp, $root, ['config.php','uploads','storage','.git']);
}

function application_backups(string $root): array {
    $files = glob($root . '/storage/backups/*.zip') ?: [];
    usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
    return $files;
}

function remove_directory_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($path);
}

function validate_backup_archive(string $file): array {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZipArchive ist nicht verfügbar.');
    if (!is_file($file) || !str_ends_with(strtolower($file), '.zip')) throw new RuntimeException('Die Backupdatei ist ungültig.');
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('Das Backup-ZIP konnte nicht geöffnet werden.');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
        if ($name === '' || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)~', $name)) {
            $zip->close();
            throw new RuntimeException('Das Backup enthält einen unsicheren Dateipfad.');
        }
    }
    $manifestRaw = $zip->getFromName('music-share-backup.json');
    $zip->close();
    if ($manifestRaw === false) throw new RuntimeException('Dies ist kein vollständiges Music-Share-Datenbackup.');
    try {
        $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        throw new RuntimeException('Das Backup-Manifest ist beschädigt.');
    }
    if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'music-share-full-backup' || (int)($manifest['format_version'] ?? 0) !== 1) {
        throw new RuntimeException('Das Backup-Format wird nicht unterstützt.');
    }
    return $manifest;
}

function create_full_data_backup(string $root, PDO $pdo, string $prefix = 'manual'): string {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('Für Backups wird die PHP-Erweiterung ZipArchive benötigt.');
    $backupDir = $root . '/storage/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) throw new RuntimeException('Backup-Verzeichnis ist nicht beschreibbar.');

    $safePrefix = preg_replace('/[^a-z0-9-]+/i', '-', $prefix) ?: 'manual';
    $file = $backupDir . '/' . $safePrefix . '-full-' . APP_VERSION . '-' . date('Ymd-His') . '.zip';
    $tmpDb = $root . '/storage/database-' . bin2hex(random_bytes(6)) . '.json';

    $tables = [];
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
        $table = (string)$row[0];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
        $createRow = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        if (!$createRow || empty($createRow[1])) continue;
        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        $tables[] = ['name' => $table, 'create_sql' => (string)$createRow[1], 'rows' => $rows];
    }

    $database = [
        'format' => 'music-share-database-json',
        'format_version' => 1,
        'created_at' => date(DATE_ATOM),
        'tables' => $tables,
    ];
    try {
        file_put_contents($tmpDb, json_encode($database, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $e) {
        @unlink($tmpDb);
        throw new RuntimeException('Datenbank konnte nicht für das Backup exportiert werden: ' . $e->getMessage());
    }

    $manifest = [
        'format' => 'music-share-full-backup',
        'format_version' => 1,
        'app_version' => APP_VERSION,
        'created_at' => date(DATE_ATOM),
        'includes' => ['database', 'uploads'],
    ];

    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpDb);
        throw new RuntimeException('Backup konnte nicht erstellt werden.');
    }
    $zip->addFromString('music-share-backup.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->addFile($tmpDb, 'database.json');

    $uploads = $root . '/uploads';
    if (is_dir($uploads)) {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if (!$item->isFile()) continue;
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            $zip->addFile($item->getPathname(), $relative);
        }
    }
    $zip->close();
    @unlink($tmpDb);
    return $file;
}

function restore_full_data_backup(string $root, PDO $pdo, string $backupName): void {
    $safe = basename($backupName);
    $file = $root . '/storage/backups/' . $safe;
    validate_backup_archive($file);

    $tmp = $root . '/storage/full-restore-' . bin2hex(random_bytes(6));
    if (!mkdir($tmp, 0775, true) && !is_dir($tmp)) throw new RuntimeException('Temporäres Wiederherstellungsverzeichnis konnte nicht erstellt werden.');
    $zip = new ZipArchive();
    if ($zip->open($file) !== true || !$zip->extractTo($tmp)) {
        remove_directory_tree($tmp);
        throw new RuntimeException('Backup konnte nicht entpackt werden.');
    }
    $zip->close();

    try {
        $dbFile = $tmp . '/database.json';
        if (!is_file($dbFile)) throw new RuntimeException('Im Backup fehlt die Datenbank.');
        $database = json_decode((string)file_get_contents($dbFile), true, 512, JSON_THROW_ON_ERROR);
        if (($database['format'] ?? '') !== 'music-share-database-json' || !is_array($database['tables'] ?? null)) {
            throw new RuntimeException('Der Datenbankexport im Backup ist ungültig.');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($database['tables'] as $tableData) {
            $table = (string)($tableData['name'] ?? '');
            $create = (string)($tableData['create_sql'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || $create === '') throw new RuntimeException('Ungültige Tabellendefinition im Backup.');
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            $pdo->exec($create);
            $rows = $tableData['rows'] ?? [];
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
                if (!is_array($row) || !$row) continue;
                $columns = array_keys($row);
                foreach ($columns as $column) {
                    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) throw new RuntimeException('Ungültiger Spaltenname im Backup.');
                }
                $quotedColumns = implode(',', array_map(fn($column) => '`' . $column . '`', $columns));
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $insert = $pdo->prepare('INSERT INTO `' . $table . '` (' . $quotedColumns . ') VALUES (' . $placeholders . ')');
                $insert->execute(array_values($row));
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $restoredUploads = $tmp . '/uploads';
        if (is_dir($restoredUploads)) {
            $targetUploads = $root . '/uploads';
            if (is_dir($targetUploads)) remove_directory_tree($targetUploads);
            if (!mkdir($targetUploads, 0775, true) && !is_dir($targetUploads)) throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
            recursive_copy_update($restoredUploads, $targetUploads, []);
        }
    } catch (Throwable $e) {
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable) {}
        throw $e;
    } finally {
        remove_directory_tree($tmp);
    }
}

function delete_stored_backup(string $root, string $backupName): void {
    $safe = basename($backupName);
    if ($safe !== $backupName || !str_ends_with(strtolower($safe), '.zip')) throw new RuntimeException('Ungültiger Backupname.');
    $file = $root . '/storage/backups/' . $safe;
    if (!is_file($file)) throw new RuntimeException('Backup wurde nicht gefunden.');
    if (!unlink($file)) throw new RuntimeException('Backup konnte nicht vom Server gelöscht werden.');
}

function stored_backup_type(string $file): string {
    $name = strtolower(basename($file));
    if (str_starts_with($name, 'before-update-')) return 'application';
    if (str_contains($name, '-full-') || str_starts_with($name, 'imported-')) {
        try {
            validate_backup_archive($file);
            return 'full';
        } catch (Throwable) {
            return 'invalid';
        }
    }
    try {
        validate_backup_archive($file);
        return 'full';
    } catch (Throwable) {
        return 'application';
    }
}
