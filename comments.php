<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/comments.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
$input=$_SERVER['REQUEST_METHOD']==='POST' ? $_POST : $_GET;
$scope=(string)($input['scope'] ?? '');$token=(string)($input['token'] ?? '');$trackId=(int)($input['track_id'] ?? 0);
[$entity,$track,$moderate]=comments_context($scope,$token,$trackId);
$column=$scope==='room'?'room_id':'share_id';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (($input['action'] ?? '')==='delete') {
        if (!$moderate) app_error(403);
        $pdo->prepare('DELETE FROM track_comments WHERE id=? AND '.$column.'=? AND track_id=?')->execute([(int)($input['id'] ?? 0),(int)$entity['id'],$trackId]);
    } else {
        $author=trim((string)($input['author'] ?? ''));$body=trim((string)($input['body'] ?? ''));$time=(string)($input['position_seconds'] ?? '');
        if ($author==='' || mb_strlen($author)>80 || $body==='' || mb_strlen($body)>2000 || !ctype_digit($time) || (int)$time>86400 || (!empty($track['duration_seconds']) && (int)$time>(int)$track['duration_seconds'])) app_error(422,'comments.invalid');
        $key=hash('sha256','comment|'.$scope.'|'.$entity['id'].'|'.($_SERVER['REMOTE_ADDR'] ?? ''));
        $lock='comment-'.substr($key,0,48);$stmt=$pdo->prepare('SELECT GET_LOCK(?,5)');$stmt->execute([$lock]);
        if ((int)$stmt->fetchColumn()!==1) app_error(429);
        $limited=false;
        try {
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE login_key=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$stmt->execute([$key]);
            $limited=(int)$stmt->fetchColumn()>=10;
            if (!$limited) {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO track_comments('.$column.',track_id,position_seconds,author,body) VALUES(?,?,?,?,?)')->execute([(int)$entity['id'],$trackId,(int)$time,$author,$body]);
            $pdo->prepare('INSERT INTO login_attempts(login_key,attempted_at,successful) VALUES(?,NOW(),0)')->execute([$key]);
            $pdo->commit();
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        } finally { $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lock]); }
        if ($limited) { header('Retry-After: 900'); app_error(429); }
    }
} elseif ($_SERVER['REQUEST_METHOD']!=='GET') { header('Allow: GET, POST'); app_error(405); }
$stmt=$pdo->prepare('SELECT id,position_seconds,author,body,created_at FROM track_comments WHERE '.$column.'=? AND track_id=? ORDER BY id DESC LIMIT 200');
$stmt->execute([(int)$entity['id'],$trackId]);
session_write_close();
echo json_encode(['ok'=>true,'comments'=>array_reverse($stmt->fetchAll()),'can_moderate'=>$moderate],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
