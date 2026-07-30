<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (is_file($root.'/config.php')) { header('Location: ../admin/login.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $cfg = [
      'app'=>['name'=>trim($_POST['app_name'] ?: 'Album Share'),'base_url'=>rtrim(trim($_POST['base_url']),'/'),'timezone'=>'Europe/Berlin','max_upload_mb'=>500],
      'db'=>['host'=>trim($_POST['db_host']),'port'=>(int)$_POST['db_port'],'name'=>trim($_POST['db_name']),'user'=>trim($_POST['db_user']),'pass'=>(string)$_POST['db_pass'],'charset'=>'utf8mb4']
    ];
    try {
      $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$cfg['db']['host'],$cfg['db']['port'],$cfg['db']['name']);
      $pdo=new PDO($dsn,$cfg['db']['user'],$cfg['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $sql=file_get_contents(__DIR__.'/schema.sql'); $pdo->exec($sql);
      $stmt=$pdo->prepare('INSERT INTO users (username,password_hash) VALUES (?,?)');
      $stmt->execute([trim($_POST['admin_user']),password_hash($_POST['admin_pass'],PASSWORD_DEFAULT)]);
      $content="<?php\nreturn ".var_export($cfg,true).";\n";
      if (file_put_contents($root.'/config.php',$content)===false) throw new RuntimeException('config.php konnte nicht geschrieben werden.');
      header('Location: ../admin/login.php?installed=1'); exit;
    } catch (Throwable $e) { $error=$e->getMessage(); }
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installation</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:760px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-3">Album Share installieren</h1><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif?><form method="post"><h2 class="h5 mt-4">Anwendung</h2><div class="row g-3"><div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="app_name" value="Album Share" required></div><div class="col-md-6"><label class="form-label">Basis-URL</label><input class="form-control" name="base_url" placeholder="https://audio.example.de"></div></div><h2 class="h5 mt-4">Datenbank</h2><div class="row g-3"><div class="col-md-8"><label class="form-label">Host</label><input class="form-control" name="db_host" value="localhost" required></div><div class="col-md-4"><label class="form-label">Port</label><input class="form-control" name="db_port" value="3306" required></div><div class="col-md-6"><label class="form-label">Datenbank</label><input class="form-control" name="db_name" required></div><div class="col-md-6"><label class="form-label">Benutzer</label><input class="form-control" name="db_user" required></div><div class="col-12"><label class="form-label">Passwort</label><input type="password" class="form-control" name="db_pass"></div></div><h2 class="h5 mt-4">Administrator</h2><div class="row g-3"><div class="col-md-6"><label class="form-label">Benutzername</label><input class="form-control" name="admin_user" required></div><div class="col-md-6"><label class="form-label">Passwort</label><input type="password" minlength="8" class="form-control" name="admin_pass" required></div></div><button class="btn btn-primary mt-4">Installation starten</button></form></div></div></main></body></html>
