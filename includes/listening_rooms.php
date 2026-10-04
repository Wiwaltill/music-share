<?php
declare(strict_types=1);

function room_error_page(int $status, string $message, string $help = 'rooms.error_help'): never {
    http_response_code($status);
    render_header(t($message), false, 'listening-room-page');
    echo '<section class="room-error-page" aria-labelledby="roomErrorTitle"><div class="room-error-card">';
    echo '<div class="room-eyebrow"><i class="bi bi-headphones" aria-hidden="true"></i> Listening Room</div>';
    echo '<div class="room-error-icon" aria-hidden="true"><i class="bi bi-link-45deg"></i></div>';
    echo '<h1 id="roomErrorTitle">'.e(t($message)).'</h1><p>'.e(t($help)).'</p>';
    if (is_logged_in() && get_setting('listening_rooms_enabled','0') === '1') echo '<a class="btn btn-outline-secondary rounded-pill" href="'.e(base_url('admin/listening_rooms.php')).'">'.e(t('rooms.back_to_rooms')).'</a>';
    echo '<div class="room-credit">Music Share</div></div></section>';
    render_footer();
    exit;
}

function require_listening_rooms(): void {
    if (get_setting('listening_rooms_enabled', '0') !== '1') {
        if (is_admin_request() || basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'room.php') {
            room_error_page(404, 'rooms.disabled', 'rooms.disabled_help');
        }
        http_response_code(404);
        exit(t('rooms.disabled'));
    }
}
function room_for_management(int $id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM listening_rooms WHERE id=?');
    $stmt->execute([$id]);
    $room = $stmt->fetch();
    if (!$room || (!is_admin() && (int)$room['owner_user_id'] !== (int)(current_user()['id'] ?? 0))) {
        room_error_page(404, 'rooms.unavailable');
    }
    return $room;
}
function public_room(string $token): ?array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT r.* FROM listening_rooms r JOIN users u ON u.id=r.owner_user_id WHERE r.token=? AND u.is_active=1 AND (r.expires_at IS NULL OR r.expires_at>NOW())');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}
function room_access_granted(array $room): bool {
    if (!empty($room['expires_at']) && strtotime((string)$room['expires_at']) <= time()) return false;
    return empty($room['password_hash']) || hash_equals((string)$room['password_hash'], (string)($_SESSION['room_ok_'.$room['id']] ?? ''));
}
/** Recheck the room owner's album access on every request, including after collaboration is revoked. */
function room_tracks(array $room): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT t.*,a.title album_title,a.artist,a.cover_file FROM listening_room_tracks rt JOIN tracks t ON t.id=rt.track_id JOIN albums a ON a.id=t.album_id JOIN users u ON u.id=? AND u.is_active=1 WHERE rt.room_id=? AND a.deleted_at IS NULL AND (u.role='admin' OR a.owner_user_id=u.id OR EXISTS(SELECT 1 FROM album_collaborators c WHERE c.album_id=a.id AND c.user_id=u.id)) ORDER BY rt.position,t.id");
    $stmt->execute([(int)$room['owner_user_id'], (int)$room['id']]);
    return $stmt->fetchAll();
}
function room_media_track(string $token, int $trackId, bool $download = false): array {
    $room = public_room($token);
    if (!$room || !room_access_granted($room) || ($download && !$room['allow_download'])) {
        http_response_code(403); exit;
    }
    foreach (room_tracks($room) as $track) {
        if ((int)$track['id'] === $trackId) return $track;
    }
    http_response_code(404); exit;
}
