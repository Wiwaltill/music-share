# Album Share

Selbst gehostete PHP-WebApp zum Hochladen, Vorhören, Teilen und Herunterladen gemischter Musikalben.

## Voraussetzungen
- PHP 8.2+ mit PDO MySQL, Fileinfo und ZipArchive
- MySQL/MariaDB
- Schreibrechte für Hauptverzeichnis (Installer), `uploads/` und Unterordner
- Ausreichend hohe Werte für `upload_max_filesize`, `post_max_size`, `max_execution_time`

## Installation
1. Dateien auf den Webserver laden.
2. Leere MySQL-Datenbank und Benutzer anlegen.
3. Webadresse aufrufen und Installer ausfüllen. Die Basis-URL wird automatisch aus der aktuellen Domain und einem möglichen Unterverzeichnis vorausgefüllt.
4. Nach erfolgreicher Installation den Ordner `install/` löschen oder serverseitig sperren.

## Update-Konzept
`config.sample.php` enthält die jeweils aktuelle Beispielstruktur. Die echte `config.php` wird nicht mitgeliefert und bleibt bei Updates erhalten. Neue Konfigurationswerte können aus der Sample-Datei ergänzt werden.

## Sicherheitshinweis
Für Produktion HTTPS verwenden. Große Audiodateien sollten idealerweise außerhalb des öffentlich erreichbaren Webroots gespeichert oder über einen geschützten Download-Controller ausgeliefert werden. Diese erste Version schützt Uploads vor PHP-Ausführung, verwendet Tokens und optional Passwörter.


## Upload-Limits

Bei größeren Alben müssen `upload_max_filesize` und insbesondere `post_max_size` ausreichend hoch eingestellt sein. `post_max_size` muss größer als die Summe aller gleichzeitig hochgeladenen Dateien sein. Beispiel:

```ini
upload_max_filesize = 500M
post_max_size = 2G
max_file_uploads = 50
max_execution_time = 600
```

Seit Version 1.2 zeigt die Anwendung bei überschrittenem `post_max_size` eine verständliche Fehlermeldung statt „Ungültige Sitzung“.

## Änderungen in Version 1.4
- Titel-Upload und Reihenfolge sind getrennte Kacheln.
- Titelnummern werden automatisch aus der Position innerhalb der CD gebildet.
- CDs werden einmal angelegt; Titel werden ausschließlich per Drag-and-drop zwischen CDs verschoben.
- Öffentlicher Audioplayer nutzt Plyr 3.8.4 und spielt nach Titelende automatisch den nächsten Titel.
- Freigabelinks können direkt im Album bearbeitet und gelöscht werden.


## Änderungen in Version 1.5
- Öffentliche aktive Wiedergabe-Buttons haben immer weißen Text.
- Einzel- und Albumdownloads behalten die ursprünglichen Upload-Dateinamen.
- Der Track-Upload ist standardmäßig eingeklappt und öffnet sich über „Neue Titel hochladen“.


## Version 1.6

- Konfigurierbarer Site Name unter **Einstellungen**
- Multi-User-Verwaltung mit Rollen Administrator und Nutzer
- Benutzer aktivieren, deaktivieren, Passwort ändern und löschen
- Öffentlicher Album-Download-Button als kontrastreicher Glas-Button
- Bestehender erster Benutzer wird automatisch Administrator

## Änderung in Version 1.9

- Vorhandene Albumcover können in der Albumverwaltung mit Bestätigung gelöscht werden.
- Beim Löschen wird auch die zugehörige Bilddatei aus dem Upload-Verzeichnis entfernt.


## Version 1.9
- Cover und Cover-Platzhalter in der Albumübersicht öffnen direkt die Albumverwaltung.


## Änderung in Version 1.9
- MP3-Titel werden zuverlässig aus ID3v2.2, ID3v2.3, ID3v2.4 oder ID3v1 übernommen.
- Aktualisierte jsmediatags-Einbindung und lokaler Fallback bei blockiertem CDN.

## Änderungen in Version 1.11

- Aktive Titel zeigen ein Pause-Symbol; bei Pause wieder das Play-Symbol.
- Kompakterer mobiler Plyr-Player mit Titelzeile oberhalb der Steuerung.


## Kurzlinks und Suchmaschinen

Neue Freigaben werden als kompakte URL im Format `/s/Ab3xK9pQ2m` ausgegeben. Bereits vorhandene lange Links über `share.php?token=...` bleiben gültig. Für Kurzlinks muss Apache `mod_rewrite` aktiviert sein und `.htaccess`-Dateien zulassen.

Die gesamte Installation ist für Suchmaschinen gesperrt durch:

- `robots.txt` mit `Disallow: /`
- HTTP-Header `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex`
- zusätzliche Robots-Meta-Tags auf öffentlichen Seiten

Der HTTP-Header ist die maßgebliche Sperre, auch wenn eine konkrete URL bereits bekannt ist.


## Version 1.12
- Separater Freigabe-Button aus der Albumübersicht entfernt.
- Angemeldete Benutzer können jedes Album direkt und ohne Freigabelink in der öffentlichen Darstellung ansehen.
- Interne Vorschau unterstützt Streaming sowie Einzel- und Albumdownload und ist durch die Anmeldung geschützt.

## GitHub-Updates

Im Backend zeigt der Footer die installierte Version und verlinkt auf das Repository:
`https://github.com/Wiwaltill/music-share`

Administratoren können unter **Einstellungen → Updates** das neueste GitHub-Release prüfen und installieren.
Damit die direkte Installation funktioniert, muss das Release eine vollständige ZIP-Datei der Anwendung als **Release Asset** enthalten. Der Dateiname sollte `album-share-app-vX.Y.Z.zip` lauten.

Vor dem Update wird ein Backup der Programmdateien unter `storage/backups/` erstellt. `config.php`, `uploads/`, `storage/` und die Datenbank werden nicht überschrieben. Erforderliche PHP-Erweiterungen: cURL oder `allow_url_fopen`, außerdem ZipArchive.


## Version 1.14
- Sticky Backend-Footer
- Alben sind standardmäßig nur für Ersteller und Administratoren sichtbar
- Interne Mitverwaltung pro Album
- Vollständige Albumlöschung inklusive Dateien und Freigaben


## Version 1.16.0

- Dezente GitHub-Signatur „Music Share · Open Source“ auf der öffentlichen Albumansicht.

## Version 1.17
- Cover-Zoom in der öffentlichen Albumansicht
- optionale Cover-Farbakzente unter Einstellungen
- Albuminformationen: Erscheinungsjahr, Genre, Label und Copyright
- Backend-Darstellung Auto/Hell/Dunkel, lokal pro Browser gespeichert
- Updater mit Release-Changelog, Backupübersicht und Rollback


## GitHub-Updater

Der Updater bevorzugt eine als Release-Asset hochgeladene Anwendungs-ZIP. Ist kein ZIP-Asset vorhanden, verwendet er automatisch die von GitHub erzeugte **Source code (zip)**-Datei (`zipball_url`). Der zusätzliche Repository-Ordner im Archiv wird automatisch erkannt. `config.php`, `uploads/`, `storage/` und `.git/` bleiben geschützt.


## Version 1.19.0

Der GitHub-Updater verwendet für die automatisch erzeugte Source-Code-ZIP nun den von GitHub empfohlenen API-Medientyp, folgt Weiterleitungen bis zum Archivserver und prüft die heruntergeladene Datei auf eine gültige ZIP-Signatur. Damit wird der HTTP-415-Fehler beim Abruf von `zipball_url` behoben.
