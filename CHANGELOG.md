# Changelog

## 2.0.0 — 2026-10-04

- Optional modules under Settings: Listening Rooms and comments. Existing basic album shares retain their familiar behavior; modules require explicit activation.
- Listening Rooms with tracks from multiple albums, searchable album selection, drag-and-drop ordering, password protection, expiration dates and optional complete-room ZIP downloads.
- Comments per share or room with track timestamps, direct playback from the referenced position, per-link access checks and posting limits.
- Bootstrap comment management with album/room filters, deletion and the same Plyr player used on public pages, including cover, artist and album metadata.
- Refined public comments sidebar, per-track comment buttons and responsive layouts.
- Live album search, a roomier backend search field and icon links for profile and settings.
- Shared error presentation, improved streaming and archive caching, versioned database migrations and expanded CI regression coverage.

### Updating

Database migrations run automatically when the updated application is loaded. Back up application data and the database before updating. Listening Rooms and comments remain optional; enable them under Settings → Modules and configure permissions for each link.
