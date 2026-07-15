# ClubKit — Installationsanleitung

## Voraussetzungen

| Anforderung | Minimum |
|---|---|
| PHP | 8.2+ (empfohlen: 8.4) |
| MySQL / MariaDB | 8.0+ / 10.6+ |
| Composer | 2.x |
| Node.js + npm | für Build (nur Entwicklung) |

---

## Installation (Standard)

```bash
# 1. Repository klonen
git clone https://github.com/dennisschwarz/clubkit.git
cd clubkit

# 2. Installer ausführen
bash install.sh
```

Der Installer:
- prüft PHP-Version
- installiert Composer-Abhängigkeiten
- erstellt `.env` (beim ersten Aufruf bitte ausfüllen, dann erneut starten)
- generiert den App-Key
- setzt Schreibrechte auf `storage/` und `bootstrap/cache/`
- erstellt den Storage-Symlink
- führt alle Migrationen aus
- leert alle Caches

---

## Berechtigungen — häufige Szenarien

### Shared Hosting (Plesk, cPanel, ISPConfig)
PHP-FPM läuft hier üblicherweise **als derselbe User** wie der Datei-Eigentümer.
Der Installer setzt `chmod 775` automatisch — kein manueller Eingriff nötig.

### VPS / Dedicated Server mit `www-data` oder `nginx` als Webserver-User

Wenn PHP-FPM als **anderen User** als den Datei-Eigentümer läuft, zwei Optionen:

**Option A — Gruppe setzen (empfohlen):**
```bash
# Webserver-User zur Gruppe des Datei-Eigentümers hinzufügen (einmalig, als root):
usermod -aG DEIN_USER www-data

# Dann ohne root:
chmod -R 775 storage bootstrap/cache
chgrp -R www-data storage bootstrap/cache
```

**Option B — ACL (kein root nötig, wenn ACL aktiviert):**
```bash
setfacl -R -m u:www-data:rwx storage bootstrap/cache
setfacl -dR -m u:www-data:rwx storage bootstrap/cache
```

**Option C — Notlösung (nur wenn nichts anderes geht):**
```bash
chmod -R 777 storage bootstrap/cache
```

### Docker
Nutze die mitgelieferte `docker-compose.yml` (in Arbeit).
PHP-FPM und Webserver laufen im selben Container als `www-data`.

---

## Update

```bash
git pull
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

Oder Kurzform:
```bash
bash update.sh
```

---

## Artisan-Befehle (Übersicht)

| Befehl | Beschreibung |
|---|---|
| `php artisan ck:create-admin` | Admin-Account anlegen |
| `php artisan migrate` | Datenbank-Migrationen ausführen |
| `php artisan module:install {slug}` | Einzelnes Modul installieren |
| `php artisan module:uninstall {slug}` | Einzelnes Modul deinstallieren |
| `php artisan config:clear` | Konfiguration-Cache leeren |
| `php artisan view:clear` | View-Cache leeren |

---

## Fehlerbehebung

### `permission denied` beim Ausführen von install.sh
```bash
chmod +x install.sh
bash install.sh
```

### `storage/logs` nicht beschreibbar
```bash
chmod -R 775 storage
```

### 500-Fehler nach der Installation
```bash
php artisan config:clear
php artisan view:clear
# Dann Logfile prüfen:
tail -f storage/logs/laravel.log
```

### Migrations-Fehler: `Table already exists`
Die Migrationen haben `hasTable()`-Guards — sie überspringen bereits existierende Tabellen automatisch. Sollte trotzdem ein Fehler auftreten:
```bash
php artisan migrate:status
```
