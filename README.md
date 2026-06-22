# ClubKit

**ClubKit** ist eine selbst-hostbare, browserbasierte Vereinsverwaltungs-Software auf Basis von Laravel. Vereine können ClubKit auf einem eigenen Webserver installieren und per Browser einrichten – ohne Kommandozeile.

## Features (Sprint 1)

- 🏟️ **Teams & Saisons** – Mehrere Teams pro Saison, flexible Team-Typen (Liga, Probe, Virtuell)
- 👥 **Mitgliederverwaltung** – Mitglieder, Kontaktdaten, optionale Logins
- 🪪 **Externe IDs** – DFBnet, Handball.net und weitere Verbände
- ⚙️ **Admin-Panel** – System-Übersicht, Versions-Info, Browser-basierte Migrations
- 🔐 **Rollen & Rechte** – Spatie Laravel Permission (Admin, Trainer, Mitglied, …)
- 🧪 **Unit Tests** – PHPUnit-Testsuite für alle Core-Models

## Systemvoraussetzungen

| Anforderung | Minimum |
|---|---|
| PHP | 8.3+ (empfohlen: 8.5) |
| MySQL | 8.0+ |
| Webserver | Apache (mod_rewrite) oder Nginx |
| Composer | 2.x |

## Installation

### 1. Dateien auf den Server laden

Klone das Repository oder lade es als ZIP herunter und lade den Inhalt per FTP auf deinen Server.

```bash
git clone https://github.com/dennisschwarz/clubkit.git
```

### 2. Abhängigkeiten installieren

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Web-Installer ausführen

Lade die `install.php` aus dem [Releases-Bereich](https://github.com/dennisschwarz/clubkit/releases) herunter und lege sie in `public/install.php`. Dann im Browser aufrufen:

```
https://deine-domain.de/install.php
```

Der Installer führt automatisch durch:
- System-Check (PHP, Extensions, Schreibrechte)
- Datenbankverbindung konfigurieren
- App-URL und Vereinsname setzen
- Administrator-Account anlegen
- Module auswählen
- Datenbank-Migrationen ausführen (kein CLI nötig)

### 4. Webserver konfigurieren

**Apache (ISPConfig):** In den Apache Direktiven eintragen:

```apache
DocumentRoot /var/www/.../web/clubkit/public

<Directory /var/www/.../web/clubkit/public>
    AllowOverride All
    Require all granted
    Options -Indexes +FollowSymLinks
</Directory>
```

**Nginx:** Document Root auf `public/` setzen, alle Requests an `index.php` weiterleiten.

### 5. Installer entfernen

Nach erfolgreicher Installation die `install.php` vom Server löschen.

## Lokale Entwicklung

### Voraussetzungen

- [Laravel Herd](https://herd.laravel.com) (Windows/Mac)
- Node.js 20+
- PHP 8.3+

### Setup

```bash
# Abhängigkeiten installieren
composer install
npm install

# .env anlegen
cp .env.example .env
php artisan key:generate

# Datenbank migrieren
php artisan migrate

# Frontend bauen
npm run dev       # Entwicklung (hot reload)
npm run build     # Produktion
```

### Tests ausführen

```bash
php artisan test
# oder
./vendor/bin/phpunit
```

## Deployment (Server)

Nach jeder Änderung:

```bash
# Lokal: Build + Push
npm run build
git add .
git commit -m "..."
git push

# Server (SSH):
cd /pfad/zu/clubkit
git pull

# Neue Migrations: Admin-Panel → System → Migrations ausführen
```

## Architektur

```
app/
├── Http/Controllers/Admin/   # Admin-Panel Controller
├── Models/                   # Eloquent Models
│   ├── Contact.php           # Personendaten
│   ├── Member.php            # Vereinsmitglied
│   ├── Season.php            # Saison
│   ├── Team.php              # Team
│   └── ExternalId.php        # Verbands-IDs (DFBnet, etc.)
├── Services/
│   └── SystemInfoService.php # System-Infos, Migrations-Status
database/
├── migrations/               # Migrations (per Browser ausführbar)
resources/views/
├── admin/                    # Admin-Panel Blade Views
tests/Unit/                   # PHPUnit Unit Tests
```

## Modul-System

ClubKit ist modular aufgebaut. Module werden beim Setup ausgewählt und können in den Einstellungen aktiviert/deaktiviert werden:

| Modul | Beschreibung |
|---|---|
| `core` | Pflicht: Auth, Nutzer, Rollen, Einstellungen |
| `teams` | Teams & Mitglieder, DFBnet-Import |
| `fixtures` | Spieltage, Aufgaben, WhatsApp-Export |
| `training` | Trainingsplanung |
| `guardians` | Erziehungsberechtigte (Jugendvereine) |
| `finances` | Einnahmen, Auslagen, Kassenbuch |
| `events` | Veranstaltungen, Elternabend-Präsentation |

## Lizenz

MIT License – siehe [LICENSE](LICENSE)

## Beitragen

Pull Requests und Issues sind willkommen. Bitte Issues für Bugs und Feature-Requests erstellen.
