<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    public function index(): View
    {
        $installed = $this->moduleLoader->getInstalled();
        $available = $this->moduleLoader->detectAvailable();

        return view('core::admin.modules.index', compact('installed', 'available'));
    }

    public function install(string $slug): RedirectResponse
    {
        $available = $this->moduleLoader->detectAvailable();

        if (!isset($available[$slug])) {
            return back()->with('error', "Modul '$slug' nicht gefunden.");
        }

        if (DB::table('installed_modules')->where('slug', $slug)->exists()) {
            return back()->with('error', "Modul '$slug' ist bereits installiert.");
        }

        // Abhängigkeiten prüfen
        foreach ($available[$slug]['requires'] ?? [] as $dep) {
            if ($dep === 'core') continue;
            if (!DB::table('installed_modules')->where('slug', $dep)->where('is_active', true)->exists()) {
                return back()->with('error', "Abhängigkeit '$dep' muss zuerst installiert werden.");
            }
        }

        try {
            Artisan::call('migrate', [
                '--path'  => 'modules/' . $this->slugToFolder($slug) . '/Database/Migrations',
                '--force' => true,
            ]);

            DB::table('installed_modules')->insert([
                'slug'         => $slug,
                'name'         => $available[$slug]['name'],
                'version'      => $available[$slug]['version'] ?? '1.0.0',
                'is_active'    => true,
                'installed_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Permissions des neuen Moduls in der DB anlegen
            $this->moduleLoader->seedPermissions($slug);

            Artisan::call('optimize:clear');

        } catch (\Throwable $e) {
            return back()->with('error', 'Fehler bei Installation: ' . $e->getMessage());
        }

        return back()->with('success', "Modul '{$available[$slug]['name']}' installiert.");
    }

    public function activate(string $slug): RedirectResponse
    {
        DB::table('installed_modules')
            ->where('slug', $slug)
            ->update(['is_active' => true, 'updated_at' => now()]);

        Artisan::call('optimize:clear');

        return back()->with('success', "Modul '$slug' aktiviert.");
    }

    public function deactivate(string $slug): RedirectResponse
    {
        if ($slug === 'core') {
            return back()->with('error', 'Das Core-Modul kann nicht deaktiviert werden.');
        }

        DB::table('installed_modules')
            ->where('slug', $slug)
            ->update(['is_active' => false, 'updated_at' => now()]);

        Artisan::call('optimize:clear');

        return back()->with('success', "Modul '$slug' deaktiviert. Daten bleiben erhalten.");
    }

    public function remove(string $slug): RedirectResponse
    {
        if ($slug === 'core') {
            return back()->with('error', 'Das Core-Modul kann nicht gelöscht werden.');
        }

        $installed = DB::table('installed_modules')->where('slug', $slug)->first();

        if (!$installed) {
            return back()->with('error', "Modul '$slug' ist nicht installiert.");
        }

        // Abhängige aktive Module prüfen
        $available  = $this->moduleLoader->detectAvailable();
        $dependents = [];

        foreach ($available as $s => $cfg) {
            if ($s === $slug) continue;
            if (in_array($slug, $cfg['requires'] ?? [], true)) {
                if (DB::table('installed_modules')->where('slug', $s)->where('is_active', true)->exists()) {
                    $dependents[] = $cfg['name'];
                }
            }
        }

        if (!empty($dependents)) {
            return back()->with('error',
                'Kann nicht entfernt werden. Folgende Module sind abhängig: ' . implode(', ', $dependents)
            );
        }

        try {
            // 1. Permissions des Moduls entfernen
            $this->moduleLoader->removePermissions($slug);

            // 2. Tabellen droppen
            //    Tabellen in module.json müssen in Erstellungs-Reihenfolge gelistet sein
            //    (Haupttabellen zuerst, Pivot-Tabellen danach).
            //    array_reverse() kehrt die Reihenfolge um → Pivot/FK-Tabellen werden zuerst gedroppt.
            //    FK-Constraints werden zusätzlich deaktiviert als Sicherheitsnetz.
            $tables = $available[$slug]['tables'] ?? [];

            Schema::disableForeignKeyConstraints();
            foreach (array_reverse($tables) as $table) {
                Schema::dropIfExists($table);
            }
            Schema::enableForeignKeyConstraints();

            // 3. Migrations-Einträge entfernen
            $migrationPath = base_path('modules/' . $this->slugToFolder($slug) . '/Database/Migrations');
            if (File::isDirectory($migrationPath)) {
                foreach (File::files($migrationPath) as $file) {
                    DB::table('migrations')
                        ->where('migration', $file->getFilenameWithoutExtension())
                        ->delete();
                }
            }

            // 4. Aus installed_modules entfernen
            DB::table('installed_modules')->where('slug', $slug)->delete();

            Artisan::call('optimize:clear');

        } catch (\Throwable $e) {
            // Sicherstellen dass FK-Constraints wieder aktiv sind
            Schema::enableForeignKeyConstraints();
            return back()->with('error', 'Fehler beim Entfernen: ' . $e->getMessage());
        }

        return back()->with('success', "Modul '$slug' und alle zugehörigen Daten wurden entfernt.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function slugToFolder(string $slug): string
    {
        return implode('', array_map('ucfirst', explode('-', $slug)));
    }
}
