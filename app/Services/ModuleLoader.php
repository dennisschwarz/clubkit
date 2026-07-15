<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discovers, loads, and manages ClubKit modules at runtime.
 *
 * In production, active modules are read from the installed_modules database table
 * and their ServiceProviders are registered in installation order.
 *
 * In the test environment, all modules found on the filesystem are loaded without
 * any database access. This is required because Laravel resolves the URL generator
 * early in the application lifecycle (before setUp() migrations run), so all routes
 * must be registered at application boot time.
 *
 * Failures during loading (e.g. database not yet ready during installation)
 * are silently swallowed to avoid breaking the installer itself.
 */
class ModuleLoader
{
    private readonly string $modulesPath;

    /** @var list<string> Slugs of modules that have already been booted in this request */
    private array $loaded = [];

    /**
     * @return void
     */
    public function __construct()
    {
        $this->modulesPath = base_path('modules');
    }

    /**
     * Boots all active modules and registers their ServiceProviders.
     * Called from AppServiceProvider::boot().
     *
     * @return void
     */
    public function boot(): void
    {
        if (app()->environment('testing')) {
            // Test environment: load every module from the filesystem so that
            // routes are registered before Laravel's URL generator is resolved.
            $this->bootAllModulesFromFilesystem();
            return;
        }

        if (! $this->tableExists()) {
            return;
        }

        try {
            $slugs = DB::table('installed_modules')
                ->where('is_active', true)
                ->orderBy('installed_at')
                ->pluck('slug');
        } catch (\Throwable) {
            // Database not ready (e.g. during installation) – skip gracefully
            return;
        }

        foreach ($slugs as $slug) {
            try {
                $this->bootModule($slug);
            } catch (\Throwable $e) {
                // Log individually so one broken module does not prevent others from loading.
                logger()->error("[ClubKit] Failed to boot module '{$slug}': " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Returns all modules discovered on the filesystem (slug → config array).
     * Used by the installer and the admin module management panel.
     *
     * @return array<string, array<string, mixed>>
     */
    public function detectAvailable(): array
    {
        $modules = [];

        if (! is_dir($this->modulesPath)) {
            return $modules;
        }

        foreach (glob($this->modulesPath . '/*/module.json') as $file) {
            $config = json_decode(file_get_contents($file), true);
            if (! $config || ! isset($config['slug'])) {
                continue;
            }
            $modules[$config['slug']] = $config;
        }

        return $modules;
    }

    /**
     * Returns all installed modules keyed by slug from the database.
     *
     * @return array<string, object>
     */
    public function getInstalled(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return DB::table('installed_modules')
            ->orderBy('installed_at')
            ->get()
            ->keyBy('slug')
            ->toArray();
    }

    /**
     * Returns navigation items for all active modules (used by the admin layout).
     *
     * Each item is sourced from the module's provides.nav array in module.json.
     *
     * @return list<array<string, mixed>>
     */
    public function getNavItems(): array
    {
        $items = [];

        if (! $this->tableExists()) {
            return $items;
        }

        try {
            $slugs = DB::table('installed_modules')
                ->where('is_active', true)
                ->pluck('slug');

            foreach ($slugs as $slug) {
                $path = $this->modulePath($slug) . '/module.json';
                if (! file_exists($path)) {
                    continue;
                }

                $config = json_decode(file_get_contents($path), true);
                $nav    = $config['provides']['nav'] ?? [];

                foreach ($nav as $item) {
                    $item['module'] = $slug;
                    $items[]        = $item;
                }
            }
        } catch (\Throwable) {
            // Database unavailable – return what we have
        }

        return $items;
    }

    /**
     * Returns true when the given module is installed and currently active.
     *
     * @param  string $slug
     * @return bool
     */
    public function isActive(string $slug): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        return DB::table('installed_modules')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Resolves a dependency graph and returns a topologically ordered list of slugs.
     *
     * Dependencies come first ("dependencies before dependents" semantics):
     * if 'members' requires 'core', the result is ['core', 'members'].
     *
     * Uses recursive DFS (Depth-First Search). Each node is appended to the result
     * only after all its dependencies have been fully resolved.
     *
     * @param  string[]            $selected   Slugs of modules chosen for installation
     * @param  array<string, mixed> $available  All available modules (from detectAvailable())
     * @return string[]  Topologically ordered slug list (dependencies first)
     *
     * @throws \RuntimeException When a module or dependency is missing, or a cycle is detected
     */
    public function resolveDependencies(array $selected, array $available): array
    {
        $resolved = [];
        $visiting = []; // Cycle detection: slugs currently on the DFS stack

        $visit = function (string $slug) use (&$visit, &$resolved, &$visiting, $available): void {
            if (in_array($slug, $resolved, true)) {
                return; // Already fully resolved – skip
            }

            if (in_array($slug, $visiting, true)) {
                throw new \RuntimeException(
                    "Circular dependency detected: '$slug' transitively depends on itself."
                );
            }

            if (! isset($available[$slug])) {
                throw new \RuntimeException(
                    "Module '$slug' is not available (files missing)."
                );
            }

            $visiting[] = $slug; // Push onto the DFS stack

            foreach ($available[$slug]['requires'] ?? [] as $dep) {
                if (! isset($available[$dep])) {
                    throw new \RuntimeException(
                        "Dependency '$dep' required by '$slug' is not available."
                    );
                }
                $visit($dep);
            }

            // Pop from DFS stack and append to resolved list
            $visiting   = array_values(array_filter($visiting, fn ($v) => $v !== $slug));
            $resolved[] = $slug;
        };

        foreach ($selected as $slug) {
            $visit($slug);
        }

        return $resolved;
    }

    /**
     * Returns the absolute filesystem path to a module directory.
     * Converts a kebab-case slug to PascalCase (e.g. 'youth-club-mode' → 'YouthClubMode').
     *
     * @param  string $slug
     * @return string
     */
    public function modulePath(string $slug): string
    {
        $folder = implode('', array_map('ucfirst', explode('-', $slug)));
        return $this->modulesPath . '/' . $folder;
    }

    // ── Permission management ─────────────────────────────────────────────────

    /**
     * Creates the permissions declared in a module's module.json and grants them
     * to the admin role. Idempotent: calling this multiple times is safe.
     *
     * Called by ModuleController::install() after a successful module installation.
     * The admin role always receives all permissions of newly installed modules.
     *
     * Has no effect when spatie/laravel-permission is not installed.
     *
     * @param  string $slug
     * @return void
     */
    public function seedPermissions(string $slug): void
    {
        if (! class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }

        $path = $this->modulePath($slug) . '/module.json';
        if (! file_exists($path)) {
            return;
        }

        $config      = json_decode(file_get_contents($path), true);
        $permissions = $config['provides']['permissions'] ?? [];

        if (empty($permissions)) {
            return;
        }

        foreach ($permissions as $permName) {
            \Spatie\Permission\Models\Permission::findOrCreate($permName, 'web');
        }

        try {
            $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'web');
            $adminRole->givePermissionTo($permissions);
        } catch (\Throwable) {
            // Admin role not yet seeded – permissions will be assigned by the seeder
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Removes the permissions declared in a module's module.json from the database.
     * Called by ModuleController::remove() during module uninstallation.
     *
     * Has no effect when spatie/laravel-permission is not installed.
     *
     * @param  string $slug
     * @return void
     */
    public function removePermissions(string $slug): void
    {
        if (! class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }

        $path = $this->modulePath($slug) . '/module.json';
        if (! file_exists($path)) {
            return;
        }

        $config      = json_decode(file_get_contents($path), true);
        $permissions = $config['provides']['permissions'] ?? [];

        foreach ($permissions as $permName) {
            try {
                $perm = \Spatie\Permission\Models\Permission::findByName($permName, 'web');
                $perm->delete();
            } catch (\Throwable) {
                // Permission does not exist – nothing to remove
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Loads all modules found on the filesystem.
     * Used exclusively in the test environment (no database access).
     *
     * Modules are sorted so that 'core' is always booted first,
     * matching the dependency order expected by other modules.
     *
     * @return void
     */
    private function bootAllModulesFromFilesystem(): void
    {
        if (! is_dir($this->modulesPath)) {
            return;
        }

        $slugs = [];

        foreach (glob($this->modulesPath . '/*/module.json') as $file) {
            $config = json_decode(file_get_contents($file), true);
            if ($config && isset($config['slug'])) {
                $slugs[] = $config['slug'];
            }
        }

        // Core must always be booted first since other modules depend on it
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
     * Registers a single module's ServiceProvider with the application container.
     * Skips modules that have already been loaded in this request.
     *
     * @param  string $slug
     * @return void
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
     * Returns true when the installed_modules table exists and is accessible.
     *
     * @return bool
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