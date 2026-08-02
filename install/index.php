<?php
declare(strict_types=1);
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);

$root = dirname(__DIR__);

if (is_file($root . '/config.php')) {
    header('Location: ../admin/login.php');
    exit;
}

/**
 * Ermittelt die öffentliche Basis-URL aus der aktuellen Installer-Adresse.
 * Funktioniert sowohl im Domain-Root als auch in Unterverzeichnissen.
 */
function detectBaseUrl(): string
{
    $forwardedProto = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]);
    $scheme = $forwardedProto !== ''
        ? strtolower($forwardedProto)
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

    if (!in_array($scheme, ['http', 'https'], true)) {
        $scheme = 'https';
    }

    $forwardedHost = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0]);
    $host = $forwardedHost !== '' ? $forwardedHost : (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?: 'localhost';

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $installPath = rtrim(dirname($scriptName), '/.');
    $appPath = rtrim(dirname($installPath), '/.');

    return rtrim($scheme . '://' . $host . ($appPath === '' ? '' : '/' . ltrim($appPath, '/')), '/');
}

$detectedBaseUrl = detectBaseUrl();
$installerLanguage = (string)($_POST['language'] ?? $_GET['language'] ?? 'de');
if (!in_array($installerLanguage, ['de','en','fr'], true)) $installerLanguage = 'de';
$itxt = require __DIR__ . '/lang/' . $installerLanguage . '.php';
$error = '';

$form = [
    'language' => $installerLanguage,
    'app_name' => 'Album Share',
    'base_url' => $detectedBaseUrl,
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'admin_user' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string)($_POST[$key] ?? $form[$key]));
    }

    $baseUrl = rtrim($form['base_url'], '/');
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $baseUrl)) {
        $error = $itxt['invalid_url'];
    } else {
        $cfg = [
            'app' => [
                'name' => $form['app_name'] !== '' ? $form['app_name'] : 'Album Share',
                'base_url' => $baseUrl,
                'timezone' => 'Europe/Berlin',
                'language' => $installerLanguage,
                'max_upload_mb' => 500,
            ],
            'db' => [
                'host' => $form['db_host'],
                'port' => (int)$form['db_port'],
                'name' => $form['db_name'],
                'user' => $form['db_user'],
                'pass' => (string)($_POST['db_pass'] ?? ''),
                'charset' => 'utf8mb4',
            ],
        ];

        try {
            if ($cfg['db']['port'] < 1 || $cfg['db']['port'] > 65535) {
                throw new RuntimeException('Der Datenbank-Port ist ungültig.');
            }

            $adminPassword = (string)($_POST['admin_pass'] ?? '');
            if (strlen($adminPassword) < 8) {
                throw new RuntimeException($itxt['admin_password_min']);
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $cfg['db']['host'],
                $cfg['db']['port'],
                $cfg['db']['name']
            );

            $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $sql = file_get_contents(__DIR__ . '/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('Das Datenbankschema konnte nicht gelesen werden.');
            }
            $pdo->exec($sql);

            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([
                $form['admin_user'],
                password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);

            $content = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($cfg, true) . ";\n";
            if (file_put_contents($root . '/config.php', $content, LOCK_EX) === false) {
                throw new RuntimeException('config.php konnte nicht geschrieben werden. Bitte die Schreibrechte prüfen.');
            }

            header('Location: ../admin/login.php?installed=1');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="<?=e($installerLanguage)?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
    <title><?=e($itxt['title'])?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 760px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-3"><?=e($itxt['title'])?></h1>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="mb-4">
                    <label class="form-label" for="language"><?=e($itxt['language'])?></label>
                    <select class="form-select" id="language" name="language" onchange="this.form.submit()">
                        <option value="de" <?=$installerLanguage==='de'?'selected':''?>>Deutsch</option>
                        <option value="en" <?=$installerLanguage==='en'?'selected':''?>>English</option>
                        <option value="fr" <?=$installerLanguage==='fr'?'selected':''?>>Français</option>
                    </select>
                </div>
                <h2 class="h5 mt-4"><?=e($itxt['application'])?></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="app_name"><?=e($itxt['name'])?></label>
                        <input class="form-control" id="app_name" name="app_name" value="<?= e($form['app_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="base_url"><?=e($itxt['base_url'])?></label>
                        <input class="form-control" type="url" id="base_url" name="base_url" value="<?= e($form['base_url']) ?>" required>
                        <div class="form-text"><?=e($itxt['base_help'])?></div>
                    </div>
                </div>

                <h2 class="h5 mt-4"><?=e($itxt['database'])?></h2>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="db_host"><?=e($itxt['host'])?></label>
                        <input class="form-control" id="db_host" name="db_host" value="<?= e($form['db_host']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="db_port"><?=e($itxt['port'])?></label>
                        <input class="form-control" type="number" min="1" max="65535" id="db_port" name="db_port" value="<?= e($form['db_port']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="db_name"><?=e($itxt['db'])?></label>
                        <input class="form-control" id="db_name" name="db_name" value="<?= e($form['db_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="db_user"><?=e($itxt['user'])?></label>
                        <input class="form-control" id="db_user" name="db_user" value="<?= e($form['db_user']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="db_pass"><?=e($itxt['password'])?></label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass">
                    </div>
                </div>

                <h2 class="h5 mt-4"><?=e($itxt['administrator'])?></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="admin_user"><?=e(t('text.benutzername'))?></label>
                        <input class="form-control" id="admin_user" name="admin_user" value="<?= e($form['admin_user']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin_pass"><?=e($itxt['password'])?></label>
                        <input type="password" minlength="8" class="form-control" id="admin_pass" name="admin_pass" required>
                    </div>
                </div>

                <button class="btn btn-primary mt-4"><?=e($itxt['start'])?></button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
