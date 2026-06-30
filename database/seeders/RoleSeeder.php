<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ModuleLoader;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds all default roles from config/clubkit.php and assigns
 * all currently installed module permissions to the admin role.
 *
 * Role definitions are driven by config('clubkit.roles') so that
 * adding or renaming a role requires only a config change, not code changes here.
 *
 * Run standalone: php artisan db:seed --class=RoleSeeder
 * Idempotent: safe to run multiple times.
 */
class RoleSeeder extends Seeder
{
    /**
     * @return void
     */
    public function run(): void
    {
        // Clear the Spatie permission cache before starting
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Create permissions for all installed modules
        $moduleLoader = app(ModuleLoader::class);
        $installed    = $moduleLoader->getInstalled();

        foreach ($installed as $slug => $module) {
            $moduleLoader->seedPermissions((string) $slug);
        }

        // 2. Create all default roles from config (idempotent)
        $roles = config('clubkit.roles', []);

        foreach ($roles as $slug => $label) {
            Role::findOrCreate($slug, 'web');
        }

        // 3. super-admin: Gate::before() bypass — no explicit permissions needed

        // 4. admin: all currently available permissions
        $admin = Role::findByName('admin', 'web');
        $admin->syncPermissions(Permission::all());

        // 5. trainer and member: view-only permissions as a sensible default
        $viewPermissions = Permission::where('name', 'like', '%.view%')->get();

        foreach (['trainer', 'member'] as $roleSlug) {
            if (array_key_exists($roleSlug, $roles)) {
                Role::findByName($roleSlug, 'web')->syncPermissions($viewPermissions);
            }
        }

        // 6. Clear the Spatie permission cache after changes
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleNames = implode(', ', array_keys($roles));
        $this->command->info('Roles created: ' . $roleNames);
        $this->command->info('admin has ' . Permission::count() . ' permissions.');
    }
}
