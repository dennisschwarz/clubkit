<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\ModuleLoader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the application database with the minimum required data for ClubKit to run.
 *
 * Only the Core module is registered here.
 * All other modules are installed via the Module Manager UI after the first login.
 *
 * Admin credentials are read from config/clubkit.php (ADMIN_EMAIL, ADMIN_NAME,
 * ADMIN_PASSWORD env keys). Falls back to admin@local.dev / admin123.
 *
 * Idempotent: safe to run multiple times (migrate:fresh --seed or db:seed).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application database.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Clear permission cache to avoid stale data
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Register Core module only
        $now = now();

        DB::table('installed_modules')->insertOrIgnore([
            'slug'         => 'core',
            'name'         => 'Core',
            'version'      => '1.0.0',
            'is_active'    => true,
            'installed_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // 3. Seed Core permissions and grant them to the admin role
        $loader = app(ModuleLoader::class);
        $loader->seedPermissions('core');

        // 4. Create admin role and admin user
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );

        $admin = User::firstOrCreate(
            ['email' => config('clubkit.admin.email', 'admin@local.dev')],
            [
                'name'              => config('clubkit.admin.name', 'Admin'),
                'password'          => Hash::make(config('clubkit.admin.password', 'admin123')),
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([$adminRole]);

        $this->command->info('Core module registered.');
        $this->command->info('Admin: ' . $admin->email . ' / ' . config('clubkit.admin.password', 'admin123'));
        $this->command->info('Install additional modules via the Module Manager.');
    }
}
