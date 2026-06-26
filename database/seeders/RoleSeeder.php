<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ModuleLoader;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RoleSeeder
 *
 * Legt die drei Standard-Rollen an und verknüpft die Admin-Rolle
 * mit allen bisher installierten Permissions.
 *
 * Ausführen: php artisan db:seed --class=RoleSeeder
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie Cache leeren bevor wir beginnen
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── 1. Permissions aller installierten Module anlegen ──────────────
        $moduleLoader = app(ModuleLoader::class);
        $installed    = $moduleLoader->getInstalled();

        foreach ($installed as $slug => $module) {
            $moduleLoader->seedPermissions((string) $slug);
        }

        // ── 2. Standard-Rollen anlegen (idempotent) ────────────────────────
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin      = Role::findOrCreate('admin',       'web');
        Role::findOrCreate('user', 'web');

        // super-admin bekommt KEINE Permissions – Gate::before() bypass genügt

        // admin bekommt alle vorhandenen Permissions
        $admin->syncPermissions(Permission::all());

        // user bekommt nur Lesezugriff
        $viewPermissions = Permission::where('name', 'like', '%.view%')->get();
        $userRole = Role::findByName('user', 'web');
        $userRole->syncPermissions($viewPermissions);

        // Spatie Cache erneut leeren
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('✅ Rollen angelegt: super-admin, admin, user');
        $this->command->info('   admin hat ' . Permission::count() . ' Permissions.');
    }
}
