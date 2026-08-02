<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    verify_csrf();
    $albumId = (int)($_POST['album_id'] ?? 0);
    require_album_access($albumId);

    $rawIds = json_decode((string)($_POST['ids'] ?? '[]'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($rawIds)) {
        throw new RuntimeException(t('track.invalid_selection'));
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        throw new RuntimeException(t('track.none_selected'));
    }
    if (count($ids) > 500) {
        throw new RuntimeException(t('track.max_bulk_delete'));
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$albumId], $ids);
    $stmt = $pdo->prepare("SELECT id,audio_file FROM tracks WHERE album_id=? AND id IN ($placeholders)");
    $stmt->execute($params);
    $tracks = $stmt->fetchAll();
    if (count($tracks) !== count($ids)) {
        throw new RuntimeException(t('track.selection_mismatch'));
    }

    $pdo->beginTransaction();
    $delete = $pdo->prepare("DELETE FROM tracks WHERE album_id=? AND id IN ($placeholders)");
    $delete->execute($params);
    $pdo->commit();

    foreach ($tracks as $track) {
        $file = dirname(__DIR__).'/uploads/audio/'.basename((string)$track['audio_file']);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    echo json_encode(['ok' => true, 'deleted_ids' => array_column($tracks, 'id')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
