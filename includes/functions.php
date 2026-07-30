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
    global $config;
    $app = e($config['app']['name'] ?? 'Album Share');
    $flashes = get_flashes();
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' – '.$app.'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="'.base_url('assets/css/app.css').'"></head><body class="bg-body-tertiary">';
    echo '<nav class="navbar navbar-expand-lg bg-dark navbar-dark"><div class="container"><a class="navbar-brand fw-semibold" href="'.base_url($admin?'admin/index.php':'').'">'.$app.'</a>';
    if ($admin && is_logged_in()) echo '<div class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-outline-light" href="'.base_url('admin/index.php').'">Alben</a><a class="btn btn-sm btn-outline-light" href="'.base_url('admin/logout.php').'">Abmelden</a></div>';
    echo '</div></nav><main class="container py-4">';
    foreach ($flashes as $f) echo '<div class="alert alert-'.e($f['type']).'">'.e($f['message']).'</div>';
}
function render_footer(): void { echo '</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="'.base_url('assets/js/player.js').'"></script></body></html>'; }

function share_access_granted(array $share): bool {
    if (!empty($share['expires_at']) && strtotime((string)$share['expires_at']) < time()) return false;
    if (!empty($share['password_hash']) && empty($_SESSION['share_ok_'.$share['id']])) return false;
    return true;
}
