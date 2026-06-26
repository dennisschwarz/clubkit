<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ModuleLoader
 *
 * Lädt aktive Module und bootstrappt ihre ServiceProvider.
 *
 * Produktiv: Liest aktive Module aus der installed_modules-Tabelle.
 * Test-Modus: Lädt alle verfügbaren Module aus dem Dateisystem (kein DB-Zugriff).
 *
 * Graceful: Fehler beim Laden (z.B. DB nicht bereit) werden still ignoriert.
 */
class ModuleLoader
{
    private string $modulesPath;
    private array $loaded = [];

    public function __construct()
    {
        $this->modulesPath = base_path('modules');
    }

    /**
     * Alle aktiven Module laden und ServiceProvider registrieren.
     * Wird in AppServiceProvider::boot() aufgerufen.
     *
     * Im Test-Modus werden alle verfügbaren Module direkt aus dem
     * Dateisystem geladen, damit Routen beim App-Boot registriert
     * werden – vor der ersten Auflösung des URL-Generators.
     */
    public function boot(): void
    {
        // ── Test-Modus: Alle Module aus dem Dateisystem laden ──────────────────
        // In Tests ist die DB beim App-Boot noch nicht migriert. Der URL-Generator
        // wird von Laravel's TestCase frühzeitig aufgelöst, daher müssen Routen
        // BEIM APP-BOOT registriert sein – nicht erst in setUp().
        if (app()->environment('testing')) {
            $this->bootAllModulesFromFilesystem();
            return;
        }

        // ── Produktiv-Modus: Module aus installed_modules laden ────────────────
        if (!$this->tableExists()) {
            return;
        }

        try {
            $slugs = DB::table('installed_modules')
                ->where('is_active', true)
                ->orderBy('installed_at')
                ->pluck('slug');

            foreach ($slugs as $slug) {
                $this->bootModule($slug);
            }
        } catch (\Throwable) {
            // DB nicht bereit (z.B. während Installation) → überspringen
        }
    }

    /**
     * Gibt alle verfügbaren Module zurück (filesystem-basiert).
     * Verwendet vom Installer und Admin-Panel.
     */
    public function detectAvailable(): array
    {
        $modules = [];

        if (!is_dir($this->modulesPath)) {
            return $modules;
        }

        foreach (glob($this->modulesPath . '/*/module.json') as $file) {
            $config = json_decode(file_get_contents($file), true);
            if (!$config || !isset($config['slug'])) {
                continue;
            }
            $modules[$config['slug']] = $config;
        }

        return $modules;
    }

    /**
     * Gibt installierte Module aus der DB zurück.
     */
    public function getInstalled(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        return DB::table('installed_modules')
            ->orderBy('installed_at')
            ->get()
            ->keyBy('slug')
            ->toArray();
    }

    /**
     * Gibt Nav-Items aller aktiven Module zurück (für das Admin-Layout).
     */
    public function getNavItems(): array
    {
        $items = [];

        if (!$this->tableExists()) {
            return $items;
        }

        try {
            $slugs = DB::table('installed_modules')
                ->where('is_active', true)
                ->pluck('slug');

            foreach ($slugs as $slug) {
                $path = $this->modulePath($slug) . '/module.json';
                if (!file_exists($path)) continue;

                $config = json_decode(file_get_contents($path), true);
                $nav    = $config['provides']['nav'] ?? [];

                foreach ($nav as $item) {
                    $item['module'] = $slug;
                    $items[]        = $item;
                }
            }
        } catch (\Throwable) {}

        return $items;
    }

    /**
     * Prüft ob ein Modul installiert + aktiv ist.
     */
    public function isActive(string $slug): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        return DB::table('installed_modules')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Löst Abhängigkeiten auf und gibt eine topologisch geordnete Liste zurück.
     *
     * Abhängigkeiten kommen zuerst ("Abhängigkeiten zuerst"-Semantik):
     * Wenn 'members' 'core' erfordert, lautet das Ergebnis ['core', 'members'].
     *
     * Implementiert als rekursiver DFS (Depth-First Search). Jeder Knoten
     * wird erst nach vollständiger Auflösung seiner Abhängigkeiten in das
     * Ergebnis-Array aufgenommen.
     *
     * @param  string[]  $selected  Slugs der gewählten Module
     * @param  array     $available Alle verfügbaren Module (aus detectAvailable())
     * @return string[]  Topologisch geordnete Liste (Abhängigkeiten zuerst)
     *
     * @throws \RuntimeException Wenn ein Modul oder eine Abhängigkeit nicht verfügbar ist,
     *                           oder wenn eine zyklische Abhängigkeit erkannt wird.
     */
    public function resolveDependencies(array $selected, array $available): array
    {
        $resolved = [];
        $visiting = []; // Zykluserkennung: aktuell auf dem DFS-Stack

        $visit = function (string $slug) use (&$visit, &$resolved, &$visiting, $available): void {
            // Bereits vollständig aufgelöst → überspringen
            if (in_array($slug, $resolved, true)) {
                return;
            }

            // Bereits im aktuellen DFS-Pfad → Zyklus erkannt
            if (in_array($slug, $visiting, true)) {
                throw new \RuntimeException(
                    "Zyklische Abhängigkeit erkannt: '$slug' hängt transitiv von sich selbst ab."
                );
            }

            if (!isset($available[$slug])) {
                throw new \RuntimeException(
                    "Modul '$slug' ist nicht verfügbar (Dateien fehlen)."
                );
            }

            $visiting[] = $slug; // Auf den DFS-Stack legen

            // Rekursiv alle Abhängigkeiten auflösen (Tiefensuche)
            foreach ($available[$slug]['requires'] ?? [] as $dep) {
                if (!isset($available[$dep])) {
                    throw new \RuntimeException(
                        "Abhängigkeit '$dep' für '$slug' ist nicht verfügbar."
                    );
                }
                $visit($dep);
            }

            // Vom DFS-Stack entfernen, in Ergebnis aufnehmen
            $visiting = array_values(array_filter($visiting, fn($v) => $v !== $slug));
            $resolved[] = $slug;
        };

        foreach ($selected as $slug) {
            $visit($slug);
        }

        return $resolved;
    }

    /**
     * Gibt den Pfad zu einem Modul zurück.
     */
    public function modulePath(string $slug): string
    {
        $folder = implode('', array_map('ucfirst', explode('-', $slug)));
        return $this->modulesPath . '/' . $folder;
    }

    // ── Permissions ──────────────────────────────────────────────────────────

    /**
     * Permissions eines Moduls aus module.json in der DB anlegen.
     * Wird von ModuleController::install() aufgerufen.
     * Die Admin-Rolle erhält automatisch alle neuen Permissions.
     */
    public function seedPermissions(string $slug): void
    {
        // Spatie-Klassen nur laden wenn verfügbar
        if (!class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }

        $path = $this->modulePath($slug) . '/module.json';
        if (!file_exists($path)) {
            return;
        }

        $config      = json_decode(file_get_contents($path), true);
        $permissions = $config['provides']['permissions'] ?? [];

        if (empty($permissions)) {
            return;
        }

        // Permissions anlegen (idempotent – findOrCreate)
        foreach ($permissions as $permName) {
            \Spatie\Permission\Models\Permission::findOrCreate($permName, 'web');
        }

        // Admin-Rolle bekommt automatisch alle neuen Permissions
        try {
            $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'web');
            $adminRole->givePermissionTo($permissions);
        } catch (\Throwable) {
            // Admin-Rolle noch nicht angelegt → wird beim Seeder nachgeholt
        }

        // Spatie Cache leeren
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Permissions eines Moduls aus der DB entfernen.
     * Wird von ModuleController::remove() aufgerufen.
     */
    public function removePermissions(string $slug): void
    {
        if (!class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }

        $path = $this->modulePath($slug) . '/module.json';
        if (!file_exists($path)) {
            return;
        }

        $config      = json_decode(file_get_contents($path), true);
        $permissions = $config['provides']['permissions'] ?? [];

        foreach ($permissions as $permName) {
            try {
                $perm = \Spatie\Permission\Models\Permission::findByName($permName, 'web');
                $perm->delete();
            } catch (\Throwable) {
                // Permission existiert nicht → ignorieren
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Alle verfügbaren Module aus dem Dateisystem laden.
     * Nur im Test-Modus verwendet (kein DB-Zugriff nötig).
     *
     * Die Module werden alphabetisch geladen – 'Core' kommt vor allen
     * anderen, was der Abhängigkeitsreihenfolge entspricht.
     */
    private function bootAllModulesFromFilesystem(): void
    {
        if (!is_dir($this->modulesPath)) {
            return;
        }

        $slugs = [];

        foreach (glob($this->modulesPath . '/*/module.json') as $file) {
            $config = json_decode(file_get_contents($file), true);
            if ($config && isset($config['slug'])) {
                $slugs[] = $config['slug'];
            }
        }

        // Core zuerst laden (andere Module können davon abhängen)
        usort($slugs, function (string $a, string $b): int {
            if ($a === 'core') return -1;
            if ($b === 'core') return  1;
            return strcmp($a, $b);
        });

        foreach ($slugs as $slug) {
            $this->bootModule($slug);
        }
    }

    /**
     * Lädt den ServiceProvider eines Moduls.
     */
    private function bootModule(string $slug): void
    {
        if (in_array($slug, $this->loaded, true)) {
            return;
        }

        $folder        = implode('', array_map('ucfirst', explode('-', $slug)));
        $providerClass = 'Modules\\' . $folder . '\\' . $folder . 'ServiceProvider';

        if (class_exists($providerClass)) {
            app()->register($providerClass);
            $this->loaded[] = $slug;
        }
    }

    /**
     * Prüft ob installed_modules Tabelle existiert.
     */
    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('installed_modules');
        } catch (\Throwable) {
            return false;
        }
    }
}
