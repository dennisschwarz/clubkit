<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Idempotent: safe to run multiple times.
     * Admin credentials via .env (ADMIN_EMAIL, ADMIN_PASSWORD) or defaults.
     */
    public function run(): void
    {
        // Spatie: Permission-Cache leeren vor dem Seeden
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Admin-Rolle anlegen (firstOrCreate = idempotent)
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );

        // Admin-User anlegen (idempotent via E-Mail)
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@local.dev')],
            [
                'name'               => env('ADMIN_NAME', 'Admin'),
                'password'           => Hash::make(env('ADMIN_PASSWORD', 'admin123')),
                'email_verified_at'  => now(),
            ]
        );

        // Rolle zuweisen (syncRoles ersetzt, keine Duplikate)
        $admin->syncRoles([$adminRole]);

        $this->command->info('Admin-User: ' . $admin->email);
        $this->command->info('Passwort:   ' . env('ADMIN_PASSWORD', 'admin123'));
    }
}
