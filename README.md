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
