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
 *
 * Dependency enforcement:
 *   - install()  checks that all required modules are installed AND active.
 *   - activate() checks that all required modules are currently active.
 *   - remove()   checks that no currently active module depends on this one.
 *   - index()    pre-computes $depsStatus so the view can disable buttons proactively.
 */
class ModuleController extends Controller
{
    /**
     * @param ModuleLoader $moduleLoader
     */
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    /**
     * Lists all installed and available modules.
     *
     * Pre-computes $depsStatus: a map of slug → list of missing dependency slugs.
     * An empty list means all dependencies are satisfied.
     * The view uses this to disable install/activate buttons proactively.
     *
     * @return View
     */
    public function index(): View
    {
        $installed = $this->moduleLoader->getInstalled();
        $available = $this->moduleLoader->detectAvailable();

        // Collect slugs of modules that are currently installed and active
        $activeSlugs = array_keys(array_filter(
            (array) $installed,
            static fn (object $m): bool => (bool) $m->is_active,
        ));

        // Pre-compute missing dependencies per module slug (used by the view).
        // 'core' is excluded because it is always active and cannot be deactivated.
        $depsStatus = [];

        foreach ($available as $slug => $config) {
            $missing = [];

            foreach ($config['requires'] ?? [] as $dep) {
                if ($dep === 'core') {
                    continue;
                }
                if (! in_array($dep, $activeSlugs, true)) {
                    $missing[] = $dep;
                }
            }

            $depsStatus[$slug] = $missing;
        }

        return view('core::admin.modules.index', compact('installed', 'available', 'depsStatus'));
    }

    /**
     * Installs a module by running its migrations, recording it in installed_modules,
     * and seeding its permissions.
     *
     * All required dependencies must be installed AND active before proceeding.
     *
     * @param  string $slug
     * @return RedirectResponse
     */
    public function install(string $slug): RedirectResponse
    {
        $available = $this->moduleLoader->detectAvailable();

        if (! isset($available[$slug])) {
            return back()->with('error', __('modules.flash.not_found', ['slug' => $slug]));
        }

        if (DB::table('installed_modules')->where('slug', $slug)->exists()) {
            return back()->with('error', __('modules.flash.already_installed', ['slug' => $slug]));
        }

        // All required dependencies must be installed and active first.
        // 'core' is always active and therefore excluded from this check.
        foreach ($available[$slug]['requires'] ?? [] as $dep) {
            if ($dep === 'core') {
                continue;
            }
            if (! DB::table('installed_modules')->where('slug', $dep)->where('is_active', true)->exists()) {
                return back()->with('error', __('modules.flash.dep_required', ['dep' => $dep]));
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
            return back()->with('error', __('modules.flash.install_error', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('modules.flash.installed', ['name' => $available[$slug]['name']]));
    }

    /**
     * Re-activates a previously deactivated module without running migrations.
     *
     * All required dependencies must currently be active before re-activation.
     * This mirrors the install() guard: a module that requires 'members' cannot
     * be activated while 'members' is deactivated.
     *
     * @param  string $slug
     * @return RedirectResponse
     */
    public function activate(string $slug): RedirectResponse
    {
        $available = $this->moduleLoader->detectAvailable();

        // All required dependencies must be active before re-activation.
        // 'core' is always active and therefore excluded from this check.
        foreach ($available[$slug]['requires'] ?? [] as $dep) {
            if ($dep === 'core') {
                continue;
            }
            if (! DB::table('installed_modules')->where('slug', $dep)->where('is_active', true)->exists()) {
                return back()->with('error', __('modules.flash.dep_activate_required', ['dep' => $dep, 'slug' => $slug]));
            }
        }

        DB::table('installed_modules')
            ->where('slug', $slug)
            ->update(['is_active' => true, 'updated_at' => now()]);

        Artisan::call('optimize:clear');

        return back()->with('success', __('modules.flash.activated', ['slug' => $slug]));
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
            return back()->with('error', __('modules.flash.core_deactivate_forbidden'));
        }

        DB::table('installed_modules')
            ->where('slug', $slug)
            ->update(['is_active' => false, 'updated_at' => now()]);

        Artisan::call('optimize:clear');

        return back()->with('success', __('modules.flash.deactivated', ['slug' => $slug]));
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
            return back()->with('error', __('modules.flash.core_delete_forbidden'));
        }

        $installed = DB::table('installed_modules')->where('slug', $slug)->first();

        if (! $installed) {
            return back()->with('error', __('modules.flash.not_installed', ['slug' => $slug]));
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

        if (! empty($dependents)) {
            return back()->with('error', __('modules.flash.has_dependents', ['modules' => implode(', ', $dependents)]));
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
            return back()->with('error', __('modules.flash.remove_error', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('modules.flash.removed', ['slug' => $slug]));
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
