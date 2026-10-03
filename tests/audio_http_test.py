"""Exercise the real PHP response helper over HTTP, without an application DB."""
import pathlib
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

helper = pathlib.Path(__file__).resolve().parents[1] / 'includes/audio_response.php'
with tempfile.TemporaryDirectory() as directory:
    root = pathlib.Path(directory)
    payload = bytes(range(256)) * 1024
    (root / 'audio.bin').write_bytes(payload)
    (root / 'index.php').write_text(
        '<?php require ' + repr(str(helper)) + '; '
        'serve_audio_file(__DIR__ . "/audio.bin");'
    )
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', directory],
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        url = f'http://127.0.0.1:{port}/'
        for attempt in range(100):
            try:
                with urllib.request.urlopen(url, timeout=1):
                    break
            except OSError:
                time.sleep(0.05)
        else:
            raise RuntimeError('PHP server did not start')
        cases = [
            ('GET', {}, 200, payload, None),
            ('GET', {'Range': 'bytes=100-199'}, 206, payload[100:200], f'bytes 100-199/{len(payload)}'),
            ('GET', {'Range': 'bytes=262140-'}, 206, payload[-4:], f'bytes 262140-262143/{len(payload)}'),
            ('GET', {'Range': 'bytes=-4'}, 206, payload[-4:], f'bytes 262140-262143/{len(payload)}'),
            ('GET', {'Range': 'bytes=999999-'}, 416, b'', f'bytes */{len(payload)}'),
            ('GET', {'Range': 'bytes=0-1,3-4'}, 200, payload, None),
            ('GET', {'Range': 'bytes=0-1', 'If-Range': '"old"'}, 200, payload, None),
            ('HEAD', {'Range': 'bytes=0-1'}, 200, b'', None),
            ('POST', {}, 405, b'', None),
        ]
        for method, headers, status, body, content_range in cases:
            request = urllib.request.Request(url, method=method, headers=headers)
            try:
                response = urllib.request.urlopen(request, timeout=3)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                assert response.status == status, (method, headers, response.status)
                assert response.read() == body, (method, headers)
                assert response.headers.get('Content-Range') == content_range
                if method == 'HEAD':
                    assert int(response.headers['Content-Length']) == len(payload)
                elif status != 405:
                    assert int(response.headers['Content-Length']) == len(body)
        print('Audio HTTP tests passed.')
    finally:
        server.terminate()
        server.wait(timeout=5)
