<?php
// CI-only fixture CLI. Never copy this script into the web root.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/functions.php';
$pdo = new PDO('mysql:host=127.0.0.1;dbname=music_share_test', 'root', 'test', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$action = $argv[1] ?? 'seed';
$id = (int)($argv[2] ?? 0);
if ($action === 'seed') {
    $pdo->exec(file_get_contents(__DIR__.'/../install/schema.sql'));
    $pdo->prepare('INSERT INTO users(username,email,password_hash,role) VALUES(?,?,?,?)')->execute(['security_test','security@example.com',password_hash('initial-password', PASSWORD_DEFAULT),'admin']);
    $user = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO albums(owner_user_id,title,cover_file) VALUES(?,?,?)')->execute([$user,'Security album','test.png']);
    $album = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tracks(album_id,title,audio_file,original_name,disc_no,track_no,duration_seconds) VALUES(?,?,?,?,1,1,30)')->execute([$album,'Track','test.mp3','test.mp3']);
    $track = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO shares(album_id,token,password_hash,allow_download) VALUES(?,?,?,1)')->execute([$album,'security-share',password_hash('share-password', PASSWORD_DEFAULT)]);
    $pdo->exec("INSERT INTO settings(setting_key,setting_value) VALUES('statistics_enabled','1') ON DUPLICATE KEY UPDATE setting_value='1'");
    $pdo->prepare('INSERT INTO users(username,email,password_hash,role) VALUES(?,?,?,?)')->execute(['search_user','search@example.com',password_hash('search-password', PASSWORD_DEFAULT),'user']);
    $searchUser = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO album_collaborators(album_id,user_id) VALUES(?,?)')->execute([$album,$searchUser]);
    $pdo->prepare('INSERT INTO albums(owner_user_id,title) VALUES(?,?)')->execute([$searchUser,'100%_Mix']);
    $pdo->prepare('INSERT INTO albums(owner_user_id,title) VALUES(?,?)')->execute([$user,'Private search album']);
    $pdo->prepare('INSERT INTO albums(owner_user_id,title,deleted_at) VALUES(?,?,NOW())')->execute([$searchUser,'Trashed search album']);
    echo json_encode(compact('user','album','track'));
} elseif ($action === 'trash') {
    $pdo->prepare('UPDATE albums SET deleted_at=NOW() WHERE id=?')->execute([$id]);
} elseif ($action === 'restore') {
    $pdo->prepare('UPDATE albums SET deleted_at=NULL WHERE id=?')->execute([$id]);
} elseif ($action === 'expire') {
    $pdo->exec("UPDATE shares SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE token='security-share'");
} elseif ($action === 'reset-token') {
    echo create_password_reset($id);
} elseif ($action === 'clear-attempts') {
    $pdo->exec('DELETE FROM login_attempts');
} elseif ($action === 'rotate-share-password') {
    $pdo->prepare("UPDATE shares SET password_hash=? WHERE token='security-share'")->execute([password_hash('changed-share-password', PASSWORD_DEFAULT)]);
}
