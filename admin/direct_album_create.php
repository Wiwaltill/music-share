<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
try {
    verify_csrf();
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') throw new RuntimeException('Kein Albumtitel in den MP3-Tags gefunden.');
    $artist = trim((string)($_POST['artist'] ?? ''));
    $albumArtist = trim((string)($_POST['album_artist'] ?? ''));
    $year = max(0, (int)($_POST['release_year'] ?? 0));
    $genre = trim((string)($_POST['genre'] ?? ''));
    $copyright = trim((string)($_POST['copyright_text'] ?? ''));
    $cover = null;
    if (!empty($_FILES['cover']['name'])) {
        $cover = upload_file($_FILES['cover'], dirname(__DIR__).'/uploads/covers', ['image/jpeg','image/png','image/webp'], 15*1024*1024);
    }
    $stmt = $pdo->prepare('INSERT INTO albums(owner_user_id,title,artist,album_artist,release_year,genre,copyright_text,description,cover_file) VALUES(?,?,?,?,?,?,?,?,?)');
    $stmt->execute([(int)(current_user()['id']??0),$title,$artist,$albumArtist,$year?:null,$genre,$copyright,'',$cover]);
    echo json_encode(['ok'=>true,'album_id'=>(int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
