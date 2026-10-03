<div align="center">

# 🎵 Music Share

**Your music. Your server. One share link.**

A self-hosted platform for presenting, streaming and sharing music albums.
Built for musicians, producers, DJs and audio engineers.

[Installation](#installation) · [Features](#features) · [Backups](#updates--backups) · [Contributing](CONTRIBUTING.md)

**PHP 8.2+ · MySQL / MariaDB · MIT License**

</div>

---

## Features

| | What you can do |
| --- | --- |
| 🎧 Listen | Responsive album pages, Plyr audio player, automatic next-track playback and a floating player. |
| 🎨 Present | Cover artwork, optional colors derived from the cover, album metadata and social sharing previews. |
| 📀 Organize | Multi-disc albums, drag-and-drop track ordering, automatic MP3 metadata and embedded-cover detection. |
| 🔗 Share | Public links with optional passwords, expiration dates and download permissions. |
| 📥 Download | Individual tracks or complete albums as ZIP files, with disc folders for multi-disc releases. |
| 👥 Collaborate | Administrator and user roles, album ownership and internal album sharing. |
| 📊 Measure | Optional statistics for album views, plays and downloads, with configurable retention. |
| ⚙️ Manage | Browser-based installation, GitHub release updates, backups, restore and an album trash. |
| 🌍 Personalize | German, English and French interfaces, plus light, dark and automatic backend themes. |

Uploads accept MP3, WAV, FLAC, M4A and OGG. Playback support depends on the listener's browser and codec; files are served without transcoding.

## Requirements

| Component | Requirement |
| --- | --- |
| Runtime | PHP 8.2 or newer |
| Database | MySQL or MariaDB, with permission to create and alter tables |
| Web server | Apache with `mod_rewrite`, `.htaccess` overrides and access-control support; `mod_headers` for the supplied Apache headers |
| PHP extensions | PDO MySQL, Fileinfo, ZipArchive and Mbstring |
| File access | Writable `uploads/` and `storage/`; permission to create `config.php` during installation |
| Updates | Outbound HTTPS to GitHub, using cURL or PHP URL streams; writable application files for the built-in updater |
| Email | Working PHP `mail()` or configured SMTP for password-reset emails |

The frontend loads Bootstrap, Bootstrap Icons and Plyr from jsDelivr. Browser access to that CDN is needed with the current setup. No Node.js or Composer build step is required.

## Installation

1. Download a [release](https://github.com/Wiwaltill/music-share/releases) or clone the repository:

   ```sh
   git clone https://github.com/Wiwaltill/music-share.git
   ```

2. Upload the application to your Apache web root or a subdirectory, including the `.htaccess` files.
3. Create an empty MySQL or MariaDB database and a database user with schema-management permissions.
4. Allow PHP to write to `uploads/` and `storage/`, and to create `config.php` in the application root. The built-in updater also needs write access to application files.
5. Open the application URL. The installer asks for database details, the public base URL, application name, system email and initial administrator account.
6. Sign in at `admin/login.php`, then configure email and other preferences in **Settings**.
7. Remove or block `install/` after installation and apply the storage protections below.

Use HTTPS for the public URL. For installations in a subdirectory, include that path in the base URL, for example `https://music.example.com/music-share`.

### Upload limits

PHP and the web server can reject a file before Music Share receives it. Align their limits with the application's upload limit, including any reverse-proxy body limit.

For example, for uploads up to 500 MB per file:

```ini
upload_max_filesize = 500M
post_max_size = 512M
```

`post_max_size` must cover the entire request, including form data and all files sent together. Large uploads and backups also need sufficient execution time, temporary disk space and free storage.

## First album

1. Create an album or use the direct album upload in the dashboard.
2. Add audio files, check the detected metadata and cover, then arrange tracks and discs.
3. Preview the album page.
4. Create a share link and choose its password, expiration date and download permissions.
5. Send the link to your listeners; they do not need an application account.

Administrators can manage all albums and system settings. Users can access their own albums and albums shared internally with them.

### Album download cache

Album ZIPs are cached in `storage/album-cache/` and reused after each request passes the access checks. Track additions, removals, ordering, original filenames and audio-file metadata changes invalidate the cache. Archives preserve uploaded filenames and multi-disc folders; only one cached version is retained per album after a download. Cache files are disposable and can be removed to reclaim space; removing the entire directory should be done while downloads are stopped. Backups exclude this cache.

## Updates & backups

The built-in updater checks GitHub releases, downloads the release archive and creates a program backup before replacing application files. It preserves `config.php`, `uploads/`, `storage/` and `.git/`. Database migrations run once per schema version when the application is loaded. Concurrent migrations are serialized; failures are logged and return HTTP 503 until resolved.

| Backup type | Contents | Purpose |
| --- | --- | --- |
| Program backup | Application files, including the existing configuration; excludes uploads and storage | Restore application code. Program rollback preserves the current configuration, uploads and storage. |
| Full data backup | Database data and schema, plus uploads | Restore users, albums, shares, settings and media on an installed instance. Excludes application code and `config.php`. |
| Migration backup | SQL database dump and uploads | Move or restore data through the migration-backup controls. Excludes application code and `config.php`. |

Create a data backup before updating. A program backup alone cannot reverse database changes. Keep downloaded backups outside the web root and retain a separate copy of `config.php` securely. Restoring a data backup replaces existing data, so verify the target installation first.

## Privacy & access

Music Share sends `noindex` directives through robots metadata and HTTP headers, and includes a restrictive `robots.txt`. These request that search engines avoid indexing the application; they do not enforce access control.

Share passwords and expiration dates are checked by the PHP share, stream and download endpoints. Albums in the trash are unavailable through their share links, including streaming, downloads and social-cover previews. Share passwords allow five failed attempts per link and client IP within 15 minutes; changing a share password invalidates previous unlocks. Account password changes and resets revoke all login sessions, including the current one. Disabling downloads hides and blocks the download endpoints, but listeners can still save audio delivered for playback.

Before hosting private material:

- The supplied Apache rules deny direct HTTP access to `storage/` and `.git/`. Confirm that these URLs return HTTP 403; backups can contain database contents and configuration secrets.
- The supplied Apache rules also deny direct HTTP access to `uploads/audio/`. Audio is served through the authorized streaming and download endpoints. Streaming supports single byte ranges for seeking.
- Keep `uploads/covers/` accessible for album artwork and social previews.
- Confirm that Apache honors the included `.htaccess` rules, including the block on `config.php`. For another web server, configure equivalent access restrictions explicitly.

## Project layout

```text
admin/          Album management, accounts, settings and updates
assets/         Stylesheets and browser scripts
includes/       Shared PHP functions, translations and migrations
install/        Installation wizard and initial database schema
storage/        Backups, update files and release cache
uploads/        Audio files and cover artwork
share.php       Public album page and internal preview
stream.php      Audio streaming endpoint
config.php      Local configuration generated by the installer
```

## Development checks

GitHub Actions checks PHP syntax and runs range-streaming, ZIP-cache, migration and share/session security regression tests on PHP 8.2–8.4. The database tests require a disposable MySQL database named `music_share_test` on `127.0.0.1:3306`, with user `root` and password `test`; they modify its contents. Run them only against a test database.

## Contributing

Found a bug or have an idea? [Open an issue](https://github.com/Wiwaltill/music-share/issues) or submit a pull request. See the [contribution guide](CONTRIBUTING.md) and [code of conduct](CODE_OF_CONDUCT.md).

## License

Music Share is released under the [MIT License](LICENSE).
