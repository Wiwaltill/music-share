<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/album_archive.php';
$root = sys_get_temp_dir().'/music-share-cache-test-'.bin2hex(random_bytes(8));
mkdir($root.'/uploads/audio', 0775, true);
file_put_contents($root.'/uploads/audio/a.mp3', 'first audio');
file_put_contents($root.'/uploads/audio/b.mp3', 'second audio');
$tracks = [
    ['id'=>1,'disc_no'=>1,'track_no'=>1,'audio_file'=>'a.mp3','original_name'=>'song.mp3'],
    ['id'=>2,'disc_no'=>2,'track_no'=>1,'audio_file'=>'b.mp3','original_name'=>'song.mp3'],
];
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
try {
    $first = open_cached_album_archive($root, 1, $tracks);
    $bytes = stream_get_contents($first);
    $files = glob($root.'/storage/album-cache/*.zip');
    check(count($files) === 1, 'Expected one cached ZIP');
    $oldInode = stat($files[0])['ino'];
    $second = open_cached_album_archive($root, 1, $tracks);
    check(stream_get_contents($second) === $bytes, 'Cache hit changed bytes');
    fclose($second);
    clearstatcache();
    check(stat($files[0])['ino'] === $oldInode, 'Cache hit rebuilt archive');
    $zip = new ZipArchive();
    $zip->open($files[0]);
    check($zip->getFromName('CD 1/song.mp3') === 'first audio', 'Disc one content missing');
    check($zip->getFromName('CD 2/song.mp3') === 'second audio', 'Disc two content missing');
    $zip->close();
    $tracks[1]['disc_no'] = 1;
    $third = open_cached_album_archive($root, 1, $tracks);
    check(stream_get_contents($third) !== $bytes, 'Disc change did not invalidate cache');
    fclose($third);
    $zip->open(glob($root.'/storage/album-cache/*.zip')[0]);
    check($zip->getFromName('Duplikat 2/song.mp3') === 'second audio', 'Duplicate filename lost');
    $zip->close();
    // Existing download handles survive replacement of the cached archive.
    rewind($first);
    check(stream_get_contents($first) === $bytes, 'Active download changed');
    fclose($first);
    $previous = album_archive_fingerprint($root, $tracks);
    file_put_contents($root.'/uploads/audio/a.mp3', 'replacement audio with new size');
    check(album_archive_fingerprint($root, $tracks) !== $previous, 'Audio change did not invalidate cache');
    $tracks[0]['original_name'] = 'renamed.mp3';
    $fourth = open_cached_album_archive($root, 1, $tracks);
    fclose($fourth);
    check(count(glob($root.'/storage/album-cache/*.zip')) === 1, 'Stale archives accumulated');
    $zip->open(glob($root.'/storage/album-cache/*.zip')[0]);
    check($zip->getFromName('renamed.mp3') === 'replacement audio with new size', 'Renamed file missing');
    $zip->close();
    unlink($root.'/uploads/audio/a.mp3');
    try {
        open_cached_album_archive($root, 1, $tracks);
        throw new LogicException('Missing audio must not return a stale archive');
    } catch (RuntimeException $expected) {}
    echo "Album cache tests passed.\n";
} finally {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
