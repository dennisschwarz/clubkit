<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ── 1. View-Cache löschen ──────────────────────────────────────────────
        // Kompilierte Blade-Views können veraltete route()-Aufrufe enthalten.
        Artisan::call('view:clear');

        // ── 2. Modul-Migrationen registrieren + ausführen ──────────────────────
        // Die Module-ServiceProvider werden bereits beim App-Boot geladen
        // (ModuleLoader::boot() erkennt den Test-Modus via app()->environment('testing')
        // und lädt alle Module aus dem Dateisystem – keine DB-Abfrage nötig).
        // Hier werden nur noch die Modul-Migrationspfade nachgereicht.
        $migrator = app('migrator');
        foreach (glob(base_path('modules/*/Database/Migrations')) as $path) {
            $migrator->path($path);
        }
        Artisan::call('migrate');
    }

    /**
     * Route-Cache BEVOR die App erstellt wird löschen.
     * ACHTUNG: base_path() hier nicht verfügbar → dirname(__DIR__) nutzen.
     */
    protected function refreshApplication(): void
    {
        $projectRoot = dirname(__DIR__);

        foreach (glob($projectRoot . '/bootstrap/cache/routes*.php') ?: [] as $file) {
            @unlink($file);
        }

        parent::refreshApplication();
    }
}
