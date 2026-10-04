<?php
declare(strict_types=1);

/** Include actual file metadata so changes outside the UI also invalidate the ZIP. */
function album_archive_fingerprint(string $root, array $tracks): string {
    $manifest = [];
    foreach ($tracks as $track) {
        $path = $root . '/uploads/audio/' . basename((string)$track['audio_file']);
        clearstatcache(true, $path);
        $stat = @stat($path);
        if ($stat === false || !is_file($path)) throw new RuntimeException('Album audio file unavailable.');
        $manifest[] = [
            (int)$track['id'], (int)$track['disc_no'], (int)$track['track_no'],
            (string)$track['audio_file'], (string)$track['original_name'],
            $stat['size'], $stat['mtime'], $stat['ctime'], $stat['ino'],
        ];
    }
    return hash('sha256', serialize(['format' => 1, 'tracks' => $manifest]));
}

function build_album_archive(string $root, array $tracks, string $target): void {
    $zip = new ZipArchive();
    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Album ZIP could not be created.');
    }
    $discCount = count(array_unique(array_column($tracks, 'disc_no')));
    $used = [];
    try {
        foreach ($tracks as $track) {
            $file = $root . '/uploads/audio/' . basename((string)$track['audio_file']);
            $original = basename(str_replace('\\', '/', (string)$track['original_name']));
            if ($original === '' || $original === '.' || $original === '..') $original = basename((string)$track['audio_file']);
            $folder = $discCount > 1 ? 'CD ' . max(1, (int)$track['disc_no']) . '/' : '';
            $entry = $folder . $original;
            for ($n = 2; isset($used[$entry]); $n++) $entry = $folder . 'Duplikat ' . $n . '/' . $original;
            $used[$entry] = true;
            if (!$zip->addFile($file, $entry)) throw new RuntimeException('Audio file could not be added to ZIP.');
            // Audio is already compressed or expensive to compress; prioritize fast downloads.
            if (!$zip->setCompressionName($entry, ZipArchive::CM_STORE)) throw new RuntimeException('ZIP compression setup failed.');
        }
    } catch (Throwable $e) {
        $zip->close();
        throw $e;
    }
    if (!$zip->close()) throw new RuntimeException('Album ZIP could not be finalized.');
}

/** Return an open file handle; replacing a cache entry cannot change an active download. */
function open_cached_album_archive(string $root, int $albumId, array $tracks, string $scope = 'album') {
    if (!in_array($scope, ['album','room'], true)) throw new InvalidArgumentException('Invalid archive scope.');
    if ($albumId < 1 || !$tracks) throw new RuntimeException('Album has no tracks.');
    $directory = $root . '/storage/'.$scope.'-cache';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Album cache directory is not writable.');
    }
    $lock = fopen($directory . '/'.$scope.'-' . $albumId . '.lock', 'c');
    if ($lock === false) throw new RuntimeException('Album cache lock unavailable.');
    $temporary = null;
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Album cache lock unavailable.');
        $fingerprint = album_archive_fingerprint($root, $tracks);
        $target = $directory . '/'.$scope.'-' . $albumId . '-' . $fingerprint . '.zip';
        if (!is_file($target)) {
            $temporary = tempnam($directory, 'building-');
            if ($temporary === false) throw new RuntimeException('Album cache temporary file unavailable.');
            build_album_archive($root, $tracks, $temporary);
            // Never publish an archive built while audio files were being replaced.
            if (album_archive_fingerprint($root, $tracks) !== $fingerprint) throw new RuntimeException('Album changed during ZIP creation; please retry.');
            if (!rename($temporary, $target)) throw new RuntimeException('Album cache could not be published.');
            $temporary = null;
        }
        $handle = fopen($target, 'rb');
        if ($handle === false) throw new RuntimeException('Cached album ZIP unavailable.');
        // Keep one version per album. Open handles keep serving their original bytes.
        foreach (glob($directory . '/'.$scope.'-' . $albumId . '-*.zip') ?: [] as $old) {
            if ($old !== $target) @unlink($old);
        }
        return $handle;
    } finally {
        if (is_string($temporary)) @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
