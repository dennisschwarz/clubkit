<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ModuleLoader
 *
 * Lädt aktive Module aus der DB und bootstrappt ihre ServiceProvider.
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
     * Alle aktiven Module aus der DB laden und ServiceProvider registrieren.
     * Wird in AppServiceProvider::boot() aufgerufen.
     */
    public function boot(): void
    {
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
     * Löst Abhängigkeiten auf und gibt eine geordnete Installations-Liste zurück.
     *
     * @param  string[]  $selected  Slugs der gewählten Module
     * @param  array     $available Alle verfügbaren Module (aus detectAvailable())
     * @return string[]  Geordnete Liste (Abhängigkeiten zuerst)
     */
    public function resolveDependencies(array $selected, array $available): array
    {
        $resolved  = [];
        $resolving = $selected;

        while (!empty($resolving)) {
            $slug = array_shift($resolving);

            if (in_array($slug, $resolved, true)) {
                continue;
            }

            if (!isset($available[$slug])) {
                throw new \RuntimeException(
                    "Modul '$slug' ist nicht verfügbar (Dateien fehlen)."
                );
            }

            $requires = $available[$slug]['requires'] ?? [];

            foreach ($requires as $dep) {
                if (!in_array($dep, $resolved, true)) {
                    if (!isset($available[$dep])) {
                        throw new \RuntimeException(
                            "Abhängigkeit '$dep' für '$slug' ist nicht verfügbar."
                        );
                    }
                    array_unshift($resolving, $dep);
                }
            }

            $resolved[] = $slug;
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
