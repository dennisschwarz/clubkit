# ClubKit

**ClubKit** ist eine selbst-hostbare, browserbasierte Vereinsverwaltungs-Software auf Basis von Laravel. Vereine installieren ClubKit auf einem eigenen Webserver und richten ihn per Browser ein – ohne Kommandozeile.

## Systemvoraussetzungen

| Anforderung | Minimum     |
|-------------|-------------|
| PHP         | 8.4+        |
| MySQL       | 8.0+        |
| Webserver   | Apache (mod_rewrite) oder Nginx |
| Composer    | 2.x         |
| Node.js     | 20+         |

## Installierte Module

| Modul           | Beschreibung                                              | Abhängig von       |
|-----------------|-----------------------------------------------------------|--------------------|
| `core`          | Pflicht: Auth, Nutzer, Rollen, Einstellungen, Admin-Panel | –                  |
| `members`       | Mitgliederverwaltung: Stammdaten, Foto, Spielberechtigung | core               |
| `teams`         | Mannschaften: Teams anlegen, Spieler zuordnen             | core, members      |
| `youth-club-mode` | Jugendmodus: Erziehungsberechtigte für Mitglieder       | core, members      |

> Neue Module werden unter `modules/{Name}/` abgelegt und über das Admin-Panel installiert.

## Lokale Entwicklung

### Voraussetzungen

- **Laragon** (Windows) oder Herd (Mac)
- PHP 8.4
- Node.js 20+
- Composer 2.x

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
# Unit-Tests (HookRegistry, ModuleLoader)
.\vendor\bin\pest tests/Unit

# Alle Tests
.\vendor\bin\pest
```

> Tests laufen gegen SQLite in-memory (`phpunit.xml`). Keine separate Test-DB nötig.

## Deployment (Server)

Das Projekt enthält ein fertiges Deploy-Skript:

```bash
bash deploy.sh
```

Das Skript:
1. Setzt Maintenance Mode
2. Führt `git pull` aus
3. Installiert Composer-Dependencies (ohne dev)
4. Baut das Frontend (`npm ci && npm run build`)
5. Führt Migrationen aus
6. Setzt `storage:link`
7. Optimiert den Cache
8. Setzt Berechtigungen
9. Startet den Queue Worker neu
10. Hebt Maintenance Mode auf

**Server:** `/var/www/clients/client_1/web_21/web/clubkit/`  
**Produktiv-URL:** https://orga2627.schwarzesnetz.de

## Web-Installer

Für Erstinstallationen ohne SSH-Zugang:

1. `public/install.php` im Browser aufrufen
2. Der Installer führt durch: System-Check, DB-Konfiguration, Admin-Account, Modul-Auswahl, Migrationen
3. `install.php` nach erfolgreicher Installation löschen

## Architektur

### Modulstruktur

```
modules/
├── Core/                        ← Pflichtmodul
│   ├── CoreServiceProvider.php
│   ├── Services/HookRegistry.php
│   ├── Http/Controllers/
│   ├── Models/Setting.php
│   ├── Database/Migrations/
│   ├── Resources/Views/
│   │   └── components/          ← <x-ck-*> Blade Components
│   ├── routes.php
│   └── module.json
├── Members/
├── Teams/
└── YouthClubMode/
```

Jedes Modul ist vollständig eigenständig. Abhängigkeiten werden über `module.json` deklariert:

```json
{
  "name": "Members",
  "slug": "members",
  "version": "1.0.0",
  "requires": ["core"],
  "tables": ["members"],
  "provides": { "migrations": true, "routes": true, "views": true, "nav": [] }
}
```

### Hook-System

Module erweitern andere Module **ohne direkte Kopplung** über das Hook-System:

```php
// In YouthClubModeServiceProvider::boot()
app('ck.hooks')->register('member.modal.tabs', 'youth-club-mode::member-modal-tab', 20);

// In members::index.blade.php
@ckHook('member.modal.tabs')
```

Definierte Extension Points:

| Extension Point          | Beschreibung                              |
|--------------------------|-------------------------------------------|
| `member.table.header`    | Extra `<th>`-Spalten in der Mitglieder-Tabelle |
| `member.table.row`       | Extra `<td>`-Zellen pro Zeile (`$member` verfügbar) |
| `member.modal.tabs`      | Zusätzliche Tab-Buttons im Member-Modal   |
| `member.modal.sections`  | Zusätzliche Tab-Inhalte im Member-Modal   |
| `member.page.scripts`    | Zusätzliche JS-Dateien am Seiten-Ende     |
| `dashboard.stats`        | Zusätzliche Kennzahlen-Kacheln            |
| `dashboard.quick-actions`| Zusätzliche Schnellaktions-Links          |
| `teams.index.toolbar`    | Toolbar-Erweiterungen auf der Team-Liste  |
| `teams.show.member-actions` | Aktionen pro Spieler im Kader-Tab      |

### CSS-Architektur

```
resources/css/
├── app.css               ← nur @import-Statements
├── base.css              ← CSS-Variablen + Reset
├── layout.css            ← Header, Navigation, Body
└── components/
    ├── buttons.css       ← .ck-btn, .ck-btn--primary etc.
    ├── badges.css
    ├── cards.css
    ├── tables.css
    ├── forms.css
    ├── modals.css
    └── auth.css
```

Farbwerte immer über CSS-Variablen:

```css
:root {
  --ck-primary: #0a1628;
  --ck-border:  #e2e8f0;
  --ck-radius:  8px;
}
```

**Regel: Niemals `style="..."` in Blade und niemals `el.style.*` in JavaScript.**

### Blade Components

Alle UI-Bausteine als anonyme Blade-Components unter `modules/Core/Resources/Views/components/`:

```blade
<x-ck-button variant="primary" type="submit">Speichern</x-ck-button>
<x-ck-button variant="danger" size="sm" :confirm="'Wirklich löschen?'">Löschen</x-ck-button>
<x-ck-badge color="green">Aktiv</x-ck-badge>
<x-ck-card><x-slot:header>Titel</x-slot:header>Inhalt</x-ck-card>
<x-ck-field label="Name" name="first_name" :required="true" />
<x-ck-field type="select" name="status" :options="['active' => 'Aktiv']" />
<x-ck-modal id="myModal" title="Titel" size="lg">...</x-ck-modal>
```

### JavaScript-Architektur

`resources/js/app.js` (Vite-Modul, `type="module"`) stellt globale Helfer bereit:

| Funktion | Beschreibung |
|----------|--------------|
| `ckModalOpen(id)` | Modal öffnen |
| `ckModalClose(e, id)` | Modal schließen |
| `ckModalTab(modalId, sectionId, btn)` | Tab wechseln |
| `ckTabEnable(tabBtnId, hintId, enabled)` | Tab aktivieren/deaktivieren |
| `ckEmit(event, data)` | Modul-Ereignis auslösen |
| `ckOn(event, handler)` | Auf Modul-Ereignis lauschen |

Externe JS-Dateien (`public/js/modules/*.js`) müssen `ckOn`-Aufrufe in `DOMContentLoaded` wrappen, da Vite-Module deferred laden:

```js
document.addEventListener('DOMContentLoaded', function () {
    ckOn('member.modal.open', function (detail) { ... });
});
```

### Data Bridge Pattern

Controller bereitet Daten für JS mit `foreach` auf (keine Arrow-Functions):

```php
$membersJs = [];
foreach ($members as $m) {
    $membersJs[$m->id] = ['id' => $m->id, 'name' => $m->last_name];
}
return view('members::index', compact('members', 'membersJs'));
```

In der View:

```blade
@push('scripts')
<script>
    window.CK_Members = {
        members: @json($membersJs),
        routes: { store: "{{ route('members.store') }}" }
    };
</script>
<script src="{{ asset('js/modules/members-modal.js') }}"></script>
@endpush
```

## Datenbankprinzip

**Für jedes reale Objekt gibt es EINEN Datenbank-Eintrag. Alles andere wird durch Relationen verknüpft.**

- Kein Duplizieren von Daten
- Verknüpfungen über Pivot-Tabellen oder Foreign Keys
- Beispiel: Ein Mitglied gehört mehreren Teams → `team_member` Pivot, NICHT `team_id` in `members`

## Lizenz

MIT License

## Beitragen

Pull Requests und Issues sind willkommen: [github.com/dennisschwarz/clubkit](https://github.com/dennisschwarz/clubkit)
