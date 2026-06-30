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

/**
 * Manages the ClubKit module lifecycle: install, activate, deactivate, remove.
 *
 * Install:    runs migrations, inserts into installed_modules, seeds permissions.
 * Activate:   sets is_active = true, no migration needed.
 * Deactivate: sets is_active = false, data is preserved.
 * Remove:     drops module tables, deletes migration records, removes permissions.
 *
 * The Core module is protected and can never be deactivated or removed.
 */
class ModuleController extends Controller
{
    /**
     * @param ModuleLoader $moduleLoader
     */
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    /**
     * @return View
     */
    public function index(): View
    {
        $installed = $this->moduleLoader->getInstalled();
        $available = $this->moduleLoader->detectAvailable();

        return view('core::admin.modules.index', compact('installed', 'available'));
    }

    /**
     * Installs a module by running its migrations, recording it in installed_modules,
     * and seeding its permissions.
     *
     * Validates that all required dependencies are already installed and active
     * before proceeding.
     *
     * @param  string $slug
     * @return RedirectResponse
     */
    public function install(string $slug): RedirectResponse
    {
        $available = $this->moduleLoader->detectAvailable();

        if (!isset($available[$slug])) {
            return back()->with('error', "Modul '$slug' nicht gefunden.");
        }

        if (DB::table('installed_modules')->where('slug', $slug)->exists()) {
            return back()->with('error', "Modul '$slug' ist bereits installiert.");
        }

        // Verify all declared dependencies are installed and active
        foreach ($available[$slug]['requires'] ?? [] as $dep) {
            if ($dep === 'core') {
                continue;
            }
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

            // Create the module's permissions in the database
            $this->moduleLoader->seedPermissions($slug);

            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            return back()->with('error', 'Fehler bei Installation: ' . $e->getMessage());
        }

        return back()->with('success', "Modul '{$available[$slug]['name']}' installiert.");
    }

    /**
     * Re-activates a previously deactivated module without running migrations.
     *
     * @param  string $slug
     * @return RedirectResponse
     */
    public function activate(string $slug): RedirectResponse
    {
        DB::table('installed_modules')
            ->where('slug', $slug)
            ->update(['is_active' => true, 'updated_at' => now()]);

        Artisan::call('optimize:clear');

        return back()->with('success', "Modul '$slug' aktiviert.");
    }

    /**
     * Deactivates a module without removing any data or migrations.
     *
     * The Core module cannot be deactivated.
     *
     * @param  string $slug
     * @return RedirectResponse
     */
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

    /**
     * Fully removes a module: drops its tables, deletes migration records,
     * removes permissions, and removes the installed_modules entry.
     *
     * Blocked when other active modules depend on the module being removed.
     * The Core module can never be removed.
     *
     * Tables listed in module.json are expected in creation order (main tables
     * first, pivot tables last). array_reverse() drops pivots before main tables,
     * preventing FK constraint violations.
     *
     * @param  string $slug
     * @return RedirectResponse
     */
    public function remove(string $slug): RedirectResponse
    {
        if ($slug === 'core') {
            return back()->with('error', 'Das Core-Modul kann nicht gelöscht werden.');
        }

        $installed = DB::table('installed_modules')->where('slug', $slug)->first();

        if (!$installed) {
            return back()->with('error', "Modul '$slug' ist nicht installiert.");
        }

        // Block removal if other active modules depend on this one
        $available  = $this->moduleLoader->detectAvailable();
        $dependents = [];

        foreach ($available as $s => $cfg) {
            if ($s === $slug) {
                continue;
            }
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
            // Step 1: Remove the module's permissions
            $this->moduleLoader->removePermissions($slug);

            // Step 2: Drop module tables (reversed to handle FK constraints)
            $tables = $available[$slug]['tables'] ?? [];

            Schema::disableForeignKeyConstraints();
            foreach (array_reverse($tables) as $table) {
                Schema::dropIfExists($table);
            }
            Schema::enableForeignKeyConstraints();

            // Step 3: Remove migration records from the migrations table
            $migrationPath = base_path('modules/' . $this->slugToFolder($slug) . '/Database/Migrations');
            if (File::isDirectory($migrationPath)) {
                foreach (File::files($migrationPath) as $file) {
                    DB::table('migrations')
                        ->where('migration', $file->getFilenameWithoutExtension())
                        ->delete();
                }
            }

            // Step 4: Remove from installed_modules
            DB::table('installed_modules')->where('slug', $slug)->delete();

            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            // Ensure FK constraints are always re-enabled even on failure
            Schema::enableForeignKeyConstraints();
            return back()->with('error', 'Fehler beim Entfernen: ' . $e->getMessage());
        }

        return back()->with('success', "Modul '$slug' und alle zugehörigen Daten wurden entfernt.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Converts a module slug to its folder name by PascalCasing each hyphen-separated segment.
     *
     * Examples: 'members' → 'Members', 'youth-club-mode' → 'YouthClubMode'
     *
     * @param  string $slug
     * @return string
     */
    private function slugToFolder(string $slug): string
    {
        return implode('', array_map('ucfirst', explode('-', $slug)));
    }
}
