# 🎵 Music Share

A modern, self-hosted web application for sharing, streaming and managing music albums.

Music Share is built for musicians, producers, DJs and audio engineers who want to present albums professionally without relying on cloud storage services.

## Features

* 🎧 Beautiful responsive album page
* 🖼️ Full-screen cover artwork with optional dynamic album colors
* ▶️ Modern audio player powered by Plyr
* ⏭️ Automatic playback of the next track
* 📥 Download individual tracks or complete albums
* 🔗 Password-protected public share links
* 👥 Multi-user support with role-based permissions
* 🔒 Internal album sharing between users
* 📀 Multi-disc album support
* 📝 Automatic MP3 tag detection
* 📊 Album management with drag & drop track ordering
* 🌙 Light & Dark Mode backend
* ⚙️ Built-in installer
* 🔄 GitHub-based updater
* 💾 Automatic and manual backup system
* 📦 Backup import & restore
* 🚫 Search engine protection (robots & noindex)

## Requirements

* PHP 8.2+
* MySQL / MariaDB
* Apache with `mod_rewrite`
* PDO MySQL
* ZipArchive
* Fileinfo

## Installation

1. Clone or download the latest release.
2. Upload the files to your web server.
3. Create an empty MySQL database.
4. Open the application in your browser.
5. Follow the installation wizard.
6. Delete or disable the `install` directory after installation.

## Updating

Music Share includes a built-in GitHub updater.

Updates automatically:

* create a backup
* preserve your configuration
* keep uploaded albums and covers
* migrate the database when required

## Backups

Two backup types are available:

### Program Backup

Created automatically before every update.

### Full Backup

Includes:

* Database
* Users
* Albums
* Share links
* Covers
* Audio files
* Settings

Full backups can be downloaded, uploaded and restored on another Music Share installation.

## Permissions

### Administrator

* Full system access
* User management
* Settings
* All albums

### User

* Own albums
* Shared albums
* Public share management

## Privacy

Music Share is designed for private hosting.

The application is excluded from search engines by default using:

* robots.txt
* X-Robots-Tag
* noindex meta tags

## Contributing

Contributions, bug reports and feature requests are always welcome.

Please open an issue or submit a pull request.

## License

MIT License
