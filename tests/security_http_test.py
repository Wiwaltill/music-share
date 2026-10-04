"""Check actual share, stream, download and password-change routes in a temp copy."""
import base64
import io
import zipfile
import http.cookiejar
import json
import pathlib
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

repo = pathlib.Path(__file__).resolve().parents[1]

def fixture(action, id=0):
    return subprocess.check_output(['php', str(repo / 'tests/security_fixture.php'), action, str(id)], text=True).strip()

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(browser, path, fields=None, json_body=None):
    data = urllib.parse.urlencode(fields, doseq=True).encode() if fields is not None else None
    headers = {}
    if json_body is not None:
        data = json.dumps(json_body).encode()
        headers['Content-Type'] = 'application/json'
    try:
        response = browser.open(urllib.request.Request(url + path, data=data, headers=headers), timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read(), response.url

def csrf(browser, path):
    status, body, _ = request(browser, path)
    assert status == 200, (path, status, body)
    return re.search(rb'name="csrf" value="([^"]+)"', body).group(1).decode()

def login(browser, password):
    return request(browser, '/admin/login.php', {'csrf': csrf(browser, '/admin/login.php'), 'username': 'security_test', 'password': password})

ids = json.loads(fixture('seed'))
with tempfile.TemporaryDirectory() as directory:
    root = pathlib.Path(directory)
    for folder in ['includes', 'admin', 'install']:
        shutil.copytree(repo / folder, root / folder)
    for source in repo.glob('*.php'):
        shutil.copyfile(source, root / source.name)
    (root / 'uploads/audio').mkdir(parents=True)
    (root / 'uploads/covers').mkdir(parents=True)
    (root / 'storage').mkdir()
    (root / 'uploads/audio/test.mp3').write_bytes(b'audio fixture')
    (root / 'uploads/covers/test.png').write_bytes(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6ZAAAAABJRU5ErkJggg=='))
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    (root / 'config.php').write_text("<?php return ['app'=>['base_url'=>'" + url + "','language'=>'en'],'db'=>['host'=>'127.0.0.1','port'=>3306,'name'=>'music_share_test','user'=>'root','pass'=>'test']];")
    with (root / 'server.log').open('w+') as log:
        server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', directory], stdout=log, stderr=log)
        try:
            visitor = client()
            for _ in range(100):
                try:
                    request(visitor, '/share.php?token=security-share')
                    break
                except OSError:
                    time.sleep(0.05)
            assert request(visitor, '/admin/search.php?q=album')[0] == 401
            searcher = client()
            result = request(searcher, '/admin/login.php', {'csrf': csrf(searcher, '/admin/login.php'), 'username': 'search_user', 'password': 'search-password'})
            assert result[2].endswith('/admin/index.php'), result
            search_results = json.loads(request(searcher, '/admin/search.php?q=album')[1])['albums']
            assert [item['title'] for item in search_results] == ['Security album'], search_results
            literal_results = json.loads(request(searcher, '/admin/search.php?q=%25_')[1])['albums']
            assert [item['title'] for item in literal_results] == ['100%_Mix'], literal_results
            assert json.loads(request(searcher, '/admin/search.php?q=')[1])['albums'] == []
            # Optional room add-on is isolated from basic album-share routes.
            assert request(visitor, '/room.php?token=room-test')[0] == 404
            rooms = json.loads(fixture('room-seed', ids['track']))
            room_page = '/room.php?token=room-test'
            room_stream = f'/room_stream.php?token=room-test&track={ids["track"]}'
            room_download = f'/room_download.php?token=room-test&track={ids["track"]}'
            listener = client()
            assert request(listener, room_stream)[0] == 403
            assert request(listener, room_page, {'csrf': csrf(listener, room_page), 'password': 'room-password'})[0] == 200
            assert request(listener, room_stream)[1] == b'audio fixture'
            assert request(listener, '/room_stream.php?token=room-test&track=999999')[0] == 404
            assert request(listener, room_download)[0] == 403
            fixture('comments-enable')
            room_comments = '/comments.php?scope=room&token=room-test&track_id=' + str(ids['track'])
            assert request(client(), room_comments)[0] == 403
            room_fields = {'csrf': csrf(listener, room_page), 'scope': 'room', 'token': 'room-test',
                           'track_id': ids['track'], 'position_seconds': 5, 'author': 'Room guest', 'body': 'Room feedback'}
            assert request(listener, '/comments.php', room_fields)[0] == 200
            assert len(json.loads(request(listener, room_comments)[1])['comments']) == 1
            assert request(client(), '/admin/comments.php')[2].endswith('/admin/login.php')
            status, overview, _ = request(searcher, '/admin/comments.php?room_id=' + str(rooms['room']))
            assert status == 200 and b'Room feedback' in overview
            assert request(listener, '/comments.php?scope=room&token=room-test&track_id=999999')[0] == 404
            fixture('comments-disable')
            # Exercise creation and deletion through the actual editor.
            create_path = '/admin/listening_room_edit.php'
            created = request(searcher, create_path, {'csrf': csrf(searcher, create_path), 'title': 'Created room', 'tracks[]': str(ids['track'])})
            assert 'listening_room_edit.php?id=' in created[2], created
            new_link = re.search(rb'id="roomLink"[^>]*value="([^"]+)"', created[1]).group(1).decode()
            public_path = urllib.parse.urlsplit(new_link).path + '?' + urllib.parse.urlsplit(new_link).query
            assert request(client(), public_path)[0] == 200
            new_editor = urllib.parse.urlsplit(created[2]).path + '?' + urllib.parse.urlsplit(created[2]).query
            assert request(searcher, new_editor, {'csrf': csrf(searcher, new_editor), 'action': 'delete'})[0] == 200
            assert request(client(), public_path)[0] == 404
            edit_room = f'/admin/listening_room_edit.php?id={rooms["room"]}'
            full_download = '/room_download_all.php?token=room-test'
            assert request(listener, full_download)[0] == 403
            assert request(client(), full_download)[0] == 403
            result = request(searcher, edit_room, {'csrf': csrf(searcher, edit_room), 'title': 'Client Room', 'allow_room_download': '1', 'tracks[]': [ids['track'], rooms['ownTrack']], 'track_order[]': [rooms['ownTrack'], ids['track']]})
            assert result[0] == 200, result
            public_body = request(listener, room_page)[1]
            public_order = re.findall(rb'data-track-id="(\d+)"', public_body)
            assert public_order == [str(rooms['ownTrack']).encode(), str(ids['track']).encode()], public_order
            assert request(listener, room_download)[0] == 403  # Independent of the ZIP permission.
            archive_result = request(listener, full_download)
            assert archive_result[0] == 200, archive_result
            with zipfile.ZipFile(io.BytesIO(archive_result[1])) as archive:
                assert archive.namelist() == ['001 - test.mp3', '002 - test.mp3']
                assert all(archive.read(name) == b'audio fixture' for name in archive.namelist())
            assert request(client(), full_download)[0] == 403  # Cached ZIP still requires password.
            assert request(listener, full_download)[1] == archive_result[1]
            result = request(searcher, edit_room, {'csrf': csrf(searcher, edit_room), 'title': 'Client Room', 'description': 'Personal selection', 'allow_download': '1', 'tracks[]': str(ids['track'])})
            assert result[0] == 200, result
            assert request(listener, room_download)[1] == b'audio fixture'
            # Injecting a nonexistent/unauthorized track cannot overwrite the room.
            result = request(searcher, edit_room, {'csrf': csrf(searcher, edit_room), 'title': 'Bad selection', 'tracks[]': '999999'})
            assert b'The selection contains unavailable tracks.' in result[1], result
            assert request(listener, room_stream)[0] == 200
            fixture('room-revoke-album', ids['album'])
            assert request(listener, room_stream)[0] == 404
            fixture('room-restore-album', ids['album'])
            assert request(listener, room_stream)[0] == 200
            fixture('room-disable')
            assert request(listener, room_stream)[0] == 404
            assert request(listener, room_page)[0] == 404
            fixture('room-enable')
            assert request(listener, room_stream)[0] == 200
            fixture('trash', ids['album'])
            assert request(listener, room_stream)[0] == 404
            fixture('restore', ids['album'])
            assert request(listener, room_stream)[0] == 200
            fixture('room-expire')
            assert request(listener, room_stream)[0] == 403
            expired_room = request(listener, room_page)
            assert expired_room[0] == 404
            assert b'room-error-card' in expired_room[1]
            assert b'ask the sender for a new link' in expired_room[1]
            stream = f'/stream.php?token=security-share&track={ids["track"]}'
            track_download = f'/download_track.php?token=security-share&track={ids["track"]}'
            album_download = '/download_album.php?token=security-share'
            share_page = '/share.php?token=security-share'
            assert request(visitor, stream)[0] == 403
            assert request(visitor, track_download)[0] == 403
            assert request(visitor, album_download)[0] == 403
            assert request(visitor, share_page, {'password': 'share-password'})[0] == 419
            # New cookie jars cannot reset the IP-based failure budget.
            for _ in range(5):
                attacker = client()
                assert request(attacker, share_page, {'csrf': csrf(attacker, share_page), 'password': 'wrong'})[0] == 200
            attacker = client()
            assert request(attacker, share_page, {'csrf': csrf(attacker, share_page), 'password': 'share-password'})[0] == 429
            fixture('clear-attempts')
            assert request(visitor, share_page, {'csrf': csrf(visitor, share_page), 'password': 'share-password'})[0] == 200
            assert request(visitor, stream)[1] == b'audio fixture'
            assert request(visitor, album_download)[1].startswith(b'PK')
            # Timestamp feedback is opt-in and inherits the share's access checks.
            comments = '/comments.php?scope=share&token=security-share&track_id=' + str(ids['track'])
            assert request(visitor, comments)[0] == 404
            fixture('comments-enable')
            assert request(client(), comments)[0] == 403
            locked_page = request(client(), share_page)[1]
            assert b'data-comment-form' not in locked_page
            public_page = request(visitor, share_page)[1]
            assert b'data-comment-form' in public_page and b'data-comment-open' in public_page
            assert b'href="#timestampComments"' not in public_page
            assert public_page.index(b'data-comment-form') > public_page.index(b'track-public-list')
            preview = request(searcher, '/share.php?album_id=' + str(ids['album']))[1]
            assert b'data-comment-form' not in preview
            assert b'class="public-album"' in preview and b'track-public-list' in preview
            assert b'data-comment-open' not in preview
            assert json.loads(request(visitor, comments)[1])['comments'] == []
            fields = {'scope': 'share', 'token': 'security-share', 'track_id': ids['track'],
                      'author': 'Listener', 'body': '<script>literal feedback</script>', 'position_seconds': 12}
            assert request(visitor, '/comments.php', fields)[0] == 419
            fields['csrf'] = csrf(visitor, share_page)
            assert request(visitor, '/comments.php', dict(fields, position_seconds=31))[0] == 422
            status, body, _ = request(visitor, '/comments.php', fields)
            assert status == 200, body
            comment = json.loads(body)['comments'][0]
            assert comment['body'] == fields['body'] and int(comment['position_seconds']) == 12
            comment_audio = '/admin/comment_stream.php?comment_id=' + str(comment['id'])
            assert request(client(), comment_audio)[2].endswith('/admin/login.php')
            assert request(searcher, comment_audio)[1] == b'audio fixture'
            overview_path = '/admin/comments.php?album_id=' + str(ids['album'])
            status, overview, _ = request(searcher, overview_path)
            assert status == 200 and b'&lt;script&gt;literal feedback&lt;/script&gt;' in overview
            assert b'data-comment-seek' in overview and b'id="mainPlayer"' in overview
            assert b'Room feedback' not in overview
            assert request(searcher, overview_path, {'comment_id': comment['id']})[0] == 419
            assert request(visitor, '/comments.php', dict(fields, action='delete', id=comment['id']))[0] == 403
            for _ in range(9):
                assert request(visitor, '/comments.php', fields)[0] == 200
            assert request(visitor, '/comments.php', fields)[0] == 429
            delete_fields = {'csrf': csrf(searcher, overview_path), 'comment_id': comment['id']}
            assert request(searcher, overview_path, delete_fields)[0] == 200
            remaining = json.loads(request(visitor, comments)[1])['comments']
            assert all(int(item['id']) != int(comment['id']) for item in remaining)
            assert request(searcher, comment_audio)[0] == 404
            fixture('comments-disable')
            assert request(visitor, comments)[0] == 404
            fixture('comments-enable')
            fixture('clear-attempts')
            fixture('trash', ids['album'])
            assert json.loads(request(searcher, '/admin/search.php?q=album')[1])['albums'] == []
            assert request(visitor, share_page)[0] == 404
            for path in [stream, track_download, album_download]:
                assert request(visitor, path)[0] == 403, path
            assert request(visitor, '/social_cover.php?token=security-share')[0] == 404
            stats = request(visitor, '/statistics_event.php', json_body={'type': 'track_play', 'token': 'security-share', 'track_id': ids['track']})
            assert json.loads(stats[1])['ok'] is False
            fixture('restore', ids['album'])
            assert request(visitor, stream)[0] == 200
            fixture('rotate-share-password')
            assert request(visitor, stream)[0] == 403
            assert request(visitor, share_page, {'csrf': csrf(visitor, share_page), 'password': 'changed-share-password'})[0] == 200
            fixture('expire')
            assert request(visitor, share_page)[0] == 404
            for path in [stream, track_download, album_download]:
                assert request(visitor, path)[0] == 403
            # Profile password changes revoke both the current and another device.
            first, second = client(), client()
            assert login(first, 'initial-password')[2].endswith('/admin/index.php')
            admin_results = json.loads(request(first, '/admin/search.php?q=album')[1])['albums']
            assert {item['title'] for item in admin_results} == {'Security album', 'Private search album'}, admin_results
            assert login(second, 'initial-password')[2].endswith('/admin/index.php')
            result = request(first, '/admin/profile.php', {'csrf': csrf(first, '/admin/profile.php'), 'username': 'security_test', 'email': 'security@example.com', 'password': 'changed-password', 'password_confirm': 'changed-password'})
            assert result[2].endswith('/admin/login.php'), result
            assert request(second, '/admin/profile.php')[2].endswith('/admin/login.php')
            assert login(second, 'changed-password')[2].endswith('/admin/index.php')
            reset = fixture('reset-token', ids['user'])
            reset_browser = client()
            reset_path = '/admin/reset_password.php?token=' + reset
            result = request(reset_browser, reset_path, {'csrf': csrf(reset_browser, reset_path), 'token': reset, 'password': 'reset-password', 'password_confirm': 'reset-password'})
            assert b'alert-success' in result[1], result
            assert request(second, '/admin/profile.php')[2].endswith('/admin/login.php')
            assert login(second, 'reset-password')[2].endswith('/admin/index.php')
            # Changing another account's password through user management also revokes sessions.
            request(second, '/admin/settings.php', {'csrf': csrf(second, '/admin/settings.php'), 'action': 'update_user', 'user_id': ids['user'], 'email': 'security@example.com', 'role': 'admin', 'is_active': '1', 'password': 'admin-changed-password'})
            assert request(second, '/admin/profile.php')[2].endswith('/admin/login.php')
            assert login(second, 'admin-changed-password')[2].endswith('/admin/index.php')
            print('Security HTTP tests passed.')
        except BaseException:
            log.flush()
            print((root / 'server.log').read_text())
            raise
        finally:
            server.terminate()
            server.wait(timeout=5)
