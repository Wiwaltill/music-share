<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

/**
 * Read the ID3v2 text frames needed by Music Share directly from an uploaded MP3.
 * This is intentionally small and dependency-free and supports ID3v2.2/2.3/2.4.
 */
function music_share_read_id3_order(string $path): array
{
    $result = ['title'=>'','disc'=>0,'track'=>0,'album'=>'','artist'=>'','album_artist'=>'','year'=>0,'genre'=>'','copyright'=>'','cover_mime'=>'','cover_data'=>''];
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return $result;
    }

    $header = fread($fh, 10);
    if (strlen($header) !== 10 || substr($header, 0, 3) !== 'ID3') {
        fclose($fh);
        return $result;
    }

    $version = ord($header[3]);
    $flags = ord($header[5]);
    $tagSize = ((ord($header[6]) & 0x7f) << 21)
        | ((ord($header[7]) & 0x7f) << 14)
        | ((ord($header[8]) & 0x7f) << 7)
        | (ord($header[9]) & 0x7f);
    $data = fread($fh, min($tagSize, 4 * 1024 * 1024));
    fclose($fh);

    // Remove unsynchronisation bytes when the tag-level flag is set.
    if (($flags & 0x80) !== 0) {
        $data = str_replace("\xFF\x00", "\xFF", $data);
    }

    $offset = 0;
    // Skip the ID3v2.3/v2.4 extended header if present.
    if (($flags & 0x40) !== 0 && strlen($data) >= 4) {
        if ($version === 4) {
            $size = ((ord($data[0]) & 0x7f) << 21)
                | ((ord($data[1]) & 0x7f) << 14)
                | ((ord($data[2]) & 0x7f) << 7)
                | (ord($data[3]) & 0x7f);
            $offset = max(0, $size);
        } else {
            $size = unpack('N', substr($data, 0, 4))[1] ?? 0;
            $offset = 4 + max(0, (int)$size);
        }
    }

    $length = strlen($data);
    while ($offset < $length) {
        $headerSize = $version === 2 ? 6 : 10;
        if ($offset + $headerSize > $length) {
            break;
        }

        $idLength = $version === 2 ? 3 : 4;
        $id = substr($data, $offset, $idLength);
        if ($id === str_repeat("\0", $idLength) || !preg_match('/^[A-Z0-9]{' . $idLength . '}$/', $id)) {
            break;
        }

        if ($version === 2) {
            $sizeBytes = substr($data, $offset + 3, 3);
            $frameSize = (ord($sizeBytes[0]) << 16) | (ord($sizeBytes[1]) << 8) | ord($sizeBytes[2]);
        } elseif ($version === 4) {
            $sizeBytes = substr($data, $offset + 4, 4);
            $frameSize = ((ord($sizeBytes[0]) & 0x7f) << 21)
                | ((ord($sizeBytes[1]) & 0x7f) << 14)
                | ((ord($sizeBytes[2]) & 0x7f) << 7)
                | (ord($sizeBytes[3]) & 0x7f);
        } else {
            $frameSize = (int)(unpack('N', substr($data, $offset + 4, 4))[1] ?? 0);
        }

        if ($frameSize <= 0 || $offset + $headerSize + $frameSize > $length) {
            break;
        }

        $payload = substr($data, $offset + $headerSize, $frameSize);
        if (in_array($id, ['TIT2','TT2','TRCK','TRK','TPOS','TPA','TALB','TAL','TPE1','TP1','TPE2','TP2','TDRC','TYER','TYE','TCON','TCO','TCOP','TCR'], true)) {
            $text = music_share_decode_id3_text($payload);
            if (($id === 'TIT2' || $id === 'TT2') && $text !== '') {
                $result['title'] = $text;
            } elseif ($id === 'TRCK' || $id === 'TRK') {
                $result['track'] = music_share_first_positive_number($text);
            } elseif ($id === 'TPOS' || $id === 'TPA') { $result['disc'] = music_share_first_positive_number($text);
            } elseif ($id === 'TALB' || $id === 'TAL') { $result['album']=$text;
            } elseif ($id === 'TPE1' || $id === 'TP1') { $result['artist']=$text;
            } elseif ($id === 'TPE2' || $id === 'TP2') { $result['album_artist']=$text;
            } elseif (in_array($id,['TDRC','TYER','TYE'],true)) { $result['year']=music_share_first_positive_number($text);
            } elseif ($id === 'TCON' || $id === 'TCO') { $result['genre']=$text;
            } elseif ($id === 'TCOP' || $id === 'TCR') { $result['copyright']=$text;
            }
        }

        if (($id === 'APIC' || $id === 'PIC') && $result['cover_data'] === '') {
            $enc = ord($payload[0] ?? "\0"); $rest = substr($payload,1);
            if ($id === 'APIC') { $z=strpos($rest,"\0"); if($z!==false){$mime=substr($rest,0,$z);$rest=substr($rest,$z+1);$rest=substr($rest,1);$term=$enc===0||$enc===3?"\0":"\0\0";$d=strpos($rest,$term);if($d!==false)$rest=substr($rest,$d+strlen($term));if(str_starts_with($mime,'image/')&&strlen($rest)>100){$result['cover_mime']=$mime;$result['cover_data']=$rest;}} }
        }
        $offset += $headerSize + $frameSize;
    }

    return $result;
}

function music_share_decode_id3_text(string $payload): string
{
    if ($payload === '') {
        return '';
    }
    $encoding = ord($payload[0]);
    $text = substr($payload, 1);

    if ($encoding === 0) {
        $decoded = function_exists('iconv') ? @iconv('Windows-1252', 'UTF-8//IGNORE', $text) : $text;
    } elseif ($encoding === 3) {
        $decoded = $text;
    } elseif ($encoding === 1) {
        if (str_starts_with($text, "\xFE\xFF")) {
            $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($text, 2)) : '';
        } else {
            $body = str_starts_with($text, "\xFF\xFE") ? substr($text, 2) : $text;
            $decoded = function_exists('iconv') ? @iconv('UTF-16LE', 'UTF-8//IGNORE', $body) : '';
        }
    } else {
        $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', $text) : '';
    }

    return trim(str_replace("\0", '', (string)$decoded));
}

function music_share_first_positive_number(string $value): int
{
    return preg_match('/^\s*(\d+)/', $value, $match) ? max(0, (int)$match[1]) : 0;
}

function music_share_filename_order(string $filename): array
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/^(\d{1,2})\s*[-_.]\s*(\d{1,3})(?:\s*[-_. ]\s*|$)/u', $base, $match)) {
        return ['disc' => (int)$match[1], 'track' => (int)$match[2]];
    }
    return ['disc' => 0, 'track' => 0];
}

try {
    verify_csrf();
    $albumId = (int)($_POST['album_id'] ?? 0);
    require_album_access($albumId);

    $file = $_FILES['file'] ?? null;
    if (!$file || empty($file['tmp_name'])) {
        throw new RuntimeException(t('common.no_file_received'));
    }

    $serverTags = music_share_read_id3_order((string)$file['tmp_name']);
    $filenameOrder = music_share_filename_order((string)($file['name'] ?? ''));
    $albumStmt=$pdo->prepare('SELECT * FROM albums WHERE id=?');$albumStmt->execute([$albumId]);$album=$albumStmt->fetch();if(!$album)throw new RuntimeException(t('text.album.nicht.gefunden'));

    $stored = upload_file(
        $file,
        dirname(__DIR__).'/uploads/audio',
        ['audio/mpeg','audio/wav','audio/x-wav','audio/flac','audio/mp4','audio/x-m4a','audio/ogg','application/ogg'],
        500 * 1024 * 1024
    );

    $postedTitle = trim((string)($_POST['title'] ?? ''));
    $title = $serverTags['title'] !== ''
        ? $serverTags['title']
        : ($postedTitle !== '' ? $postedTitle : pathinfo((string)$file['name'], PATHINFO_FILENAME));

    $postedDisc = max(0, (int)($_POST['disc_no'] ?? 0));
    $postedTrack = max(0, (int)($_POST['track_no'] ?? 0));
    $disc = max(1, min(99, $serverTags['disc'] ?: $postedDisc ?: $filenameOrder['disc'] ?: 1));
    $requestedTrack = $serverTags['track'] ?: $postedTrack ?: $filenameOrder['track'];
    $duration = max(0, (int)($_POST['duration'] ?? 0));

    $metadataUpdates=[];$metadataParams=[];
    $map=['artist'=>'artist','album_artist'=>'album_artist','genre'=>'genre','copyright_text'=>'copyright'];
    foreach($map as $col=>$tagKey){if(trim((string)($album[$col]??''))===''&&trim((string)($serverTags[$tagKey]??''))!==''){$metadataUpdates[]="$col=?";$metadataParams[]=trim((string)$serverTags[$tagKey]);}}
    if(empty($album['release_year'])&&!empty($serverTags['year'])){$metadataUpdates[]='release_year=?';$metadataParams[]=(int)$serverTags['year'];}
    if($metadataUpdates){$metadataParams[]=$albumId;$pdo->prepare('UPDATE albums SET '.implode(',',$metadataUpdates).' WHERE id=?')->execute($metadataParams);}
    $albumTitleCandidate = '';
    $tagAlbumTitle = trim((string)($serverTags['album'] ?? ''));
    $currentAlbumTitle = trim((string)($album['title'] ?? ''));
    if ($tagAlbumTitle !== '' && strcasecmp($tagAlbumTitle, $currentAlbumTitle) !== 0) {
        $albumTitleCandidate = $tagAlbumTitle;
    }

    $coverCandidate='';
    if(empty($album['cover_file'])&&!empty($serverTags['cover_data'])){$mime=$serverTags['cover_mime'];$ext=$mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');$coverCandidate='pending-'.bin2hex(random_bytes(10)).'.'.$ext;file_put_contents(dirname(__DIR__).'/uploads/covers/'.$coverCandidate,$serverTags['cover_data']);}

    $pdo->beginTransaction();
    if ($requestedTrack > 0) {
        $trackNo = $requestedTrack;
        $shift = $pdo->prepare('UPDATE tracks SET track_no = track_no + 1 WHERE album_id = ? AND disc_no = ? AND track_no >= ?');
        $shift->execute([$albumId, $disc, $trackNo]);
    } else {
        $next = $pdo->prepare('SELECT COALESCE(MAX(track_no),0)+1 FROM tracks WHERE album_id=? AND disc_no=?');
        $next->execute([$albumId, $disc]);
        $trackNo = max(1, (int)$next->fetchColumn());
    }

    $insert = $pdo->prepare('INSERT INTO tracks(album_id,title,disc_no,track_no,audio_file,original_name,file_size,duration_seconds) VALUES(?,?,?,?,?,?,?,?)');
    $insert->execute([$albumId, $title, $disc, $trackNo, $stored, $file['name'], $file['size'], $duration]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'title' => $title,
        'disc_no' => $disc,
        'track_no' => $trackNo,
        'cover_candidate'=>$coverCandidate,'album_title_candidate'=>$albumTitleCandidate,'current_album_title'=>$currentAlbumTitle,'metadata_filled'=>count($metadataUpdates),'tag_source' => ($serverTags['disc'] || $serverTags['track']) ? 'id3' : (($filenameOrder['disc'] || $filenameOrder['track']) ? 'filename' : 'browser'),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($stored) && is_string($stored)) {
        $path = dirname(__DIR__).'/uploads/audio/'.basename($stored);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
