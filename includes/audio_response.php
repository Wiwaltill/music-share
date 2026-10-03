<?php
declare(strict_types=1);

/** null means ignore the range; false means a valid but unsatisfiable range. */
function audio_byte_range(string $header, int $size): array|false|null {
    // Multiple ranges are deliberately ignored: respond with the full representation.
    if (!preg_match('/^bytes=(\d*)-(\d*)$/D', trim($header), $match)) return null;
    if ($match[1] === '' && $match[2] === '') return null;
    if ($match[1] !== '' && $match[2] !== '' && (float)$match[1] > (float)$match[2]) return null;
    if ($size === 0) return false;
    if ($match[1] === '') {
        $suffix = (int)min((float)$match[2], $size);
        if ($suffix === 0) return false;
        return [$size - $suffix, $size - 1];
    }
    if ((float)$match[1] >= $size) return false;
    $start = (int)$match[1];
    $end = $match[2] === '' ? $size - 1 : (int)min((float)$match[2], $size - 1);
    return [$start, $end];
}

function serve_audio_file(string $file): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        return;
    }
    $handle = fopen($file, 'rb');
    if ($handle === false) { http_response_code(404); return; }
    try {
        $stat = fstat($handle);
        $size = (int)$stat['size'];
        header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream'));
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        // Always recheck authorization, including expired or revoked share links.
        header('Cache-Control: private, no-store');
        $range = null;
        // Without validators, If-Range falls back to the complete representation.
        if ($method === 'GET' && !isset($_SERVER['HTTP_IF_RANGE'])) {
            $range = audio_byte_range((string)($_SERVER['HTTP_RANGE'] ?? ''), $size);
        }
        if ($range === false) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            header('Content-Length: 0');
            return;
        }
        [$start, $end] = $range ?? [0, $size - 1];
        if ($range !== null) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        $remaining = $size === 0 ? 0 : $end - $start + 1;
        header('Content-Length: ' . $remaining);
        if ($method === 'HEAD') return;
        if ($start > 0 && fseek($handle, $start) !== 0) {
            http_response_code(500);
            return;
        }
        while ($remaining > 0 && !connection_aborted()) {
            $chunk = fread($handle, min(65536, $remaining));
            if ($chunk === false || $chunk === '') break;
            echo $chunk;
            $remaining -= strlen($chunk);
        }
    } finally {
        fclose($handle);
    }
}
