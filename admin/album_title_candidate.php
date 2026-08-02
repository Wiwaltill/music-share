<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    verify_csrf();
    $albumId = (int)($_POST['album_id'] ?? 0);
    require_album_access($albumId);
    $title = trim((string)($_POST['title'] ?? ''));
    $accept = (string)($_POST['accept'] ?? '0') === '1';

    if ($albumId < 1 || $title === '') {
        throw new RuntimeException(t('album.invalid_title'));
    }

    if ($accept) {
        $stmt = $pdo->prepare('UPDATE albums SET title = ? WHERE id = ?');
        $stmt->execute([$title, $albumId]);
    }

    echo json_encode(['ok' => true, 'updated' => $accept, 'title' => $title]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
