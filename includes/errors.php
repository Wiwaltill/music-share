<?php
declare(strict_types=1);

function error_response_kind(): string {
    $path=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $script=basename($path);
    if (in_array($script,['stream.php','room_stream.php','comment_stream.php','social_cover.php'],true)) return 'empty';
    if (($script==='comments.php' && !str_contains($path,'/admin/'))
        || in_array($script,['search.php','statistics_event.php','track_upload.php','track_delete.php','track_bulk_delete.php','track_update.php','direct_album_create.php','cover_candidate.php','album_title_candidate.php'],true)
        || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''),'application/json')) return 'json';
    return 'html';
}

/** Does not depend on database settings, so it also works during connection failures. */
function app_error(int $status, ?string $message = null, ?string $help = null, string $context = 'Music Share'): never {
    global $config;
    $language=(string)($config['app']['language'] ?? 'de');
    if (isset($GLOBALS['pdo'])) {
        try { $language=current_language(); } catch(Throwable $ignored) {}
    }
    if (!in_array($language,['de','en','fr'],true)) $language='de';
    $catalogue=require __DIR__.'/lang/'.$language.'.php';
    $key=in_array($status,[401,403,404,413,419,429,503],true) ? (string)$status : '500';
    $title=$catalogue[$message ?? 'error.title.'.$key] ?? $catalogue['error.title.'.$key];
    $description=$catalogue[$help ?? 'error.help.'.$key] ?? $catalogue['error.help.'.$key];
    http_response_code($status);
    if (headers_sent()) exit;
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    $kind=error_response_kind();
    if ($kind==='empty') { header('Content-Length: 0'); exit; }
    if ($kind==='json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'http_'.$status,'message'=>$title],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
    ?>
<!doctype html><html lang="<?=$escape($language)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=$escape($title)?> · Music Share</title>
<style>
:root{color-scheme:light dark;--bg:#f4f5f8;--card:#fff;--text:#252733;--muted:#697080;--line:#e5e7ed;--accent:#8060ed}
@media(prefers-color-scheme:dark){:root{--bg:#17191e;--card:#22252c;--text:#f2f3f7;--muted:#a9aebc;--line:#353943}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px}.app-error-card{width:100%;max-width:540px;border:1px solid var(--line);background:var(--card);border-radius:24px;padding:clamp(24px,6vw,48px);text-align:center;box-shadow:0 20px 65px #00000012}.eyebrow{font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--accent);font-weight:700}.icon{width:80px;height:80px;margin:28px auto;display:grid;place-items:center;border-radius:50%;background:#8060ed18;color:var(--accent);font-size:34px}h1{font-size:clamp(24px,4vw,32px);line-height:1.3;letter-spacing:-.025em;margin:0 0 16px}p{color:var(--muted);line-height:1.75;margin:0}.credit{font-size:12px;color:var(--muted);margin-top:32px}
</style></head><body><main class="app-error-card room-error-card" aria-labelledby="errorTitle"><div class="eyebrow"><?=$escape($context)?></div><div class="icon" aria-hidden="true">♫</div><h1 id="errorTitle"><?=$escape($title)?></h1><p><?=$escape($description)?></p><div class="credit">Music Share</div></main></body></html>
<?php
    exit;
}
