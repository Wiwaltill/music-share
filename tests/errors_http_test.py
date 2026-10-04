"""Verify shared error responses without a database or frontend dependencies."""
import json
import pathlib
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

helper = pathlib.Path(__file__).resolve().parents[1] / 'includes/errors.php'
with tempfile.TemporaryDirectory() as directory:
    root = pathlib.Path(directory)
    php = ("<?php $config=['app'=>['language'=>'en']]; require " + repr(str(helper)) + "; app_error((int)($_GET['status'] ?? 404));")
    for name in ['share.php', 'stream.php', 'social_cover.php', 'track_upload.php']:
        (root / name).write_text(php)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    def request(path, accept='text/html'):
        req = urllib.request.Request(f'http://127.0.0.1:{port}/' + path, headers={'Accept': accept})
        try:
            response = urllib.request.urlopen(req, timeout=3)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.headers, response.read()
    try:
        for attempt in range(100):
            try:
                request('share.php')
                break
            except OSError:
                time.sleep(0.05)
        for status in [401, 403, 404, 413, 419, 429, 500, 503]:
            actual, headers, body = request('share.php?status=' + str(status))
            assert actual == status
            assert 'text/html' in headers['Content-Type']
            assert b'app-error-card' in body and b'<html lang="en">' in body
            assert headers['Cache-Control'] == 'private, no-store'
            actual, headers, body = request('track_upload.php?status=' + str(status))
            assert actual == status
            assert json.loads(body)['ok'] is False
            assert json.loads(body)['error'] == 'http_' + str(status)
            assert 'application/json' in headers['Content-Type']
        for path in ['stream.php', 'social_cover.php']:
            status, headers, body = request(path)
            assert status == 404 and body == b''
        status, headers, body = request('share.php?status=403', 'application/json')
        assert status == 403 and json.loads(body)['ok'] is False
        print('Shared error HTTP tests passed.')
    finally:
        server.terminate()
        server.wait(timeout=5)
