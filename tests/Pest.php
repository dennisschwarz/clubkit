<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature-Tests: TestCase + RefreshDatabase (DB nach jedem Test zurückgesetzt)
| Unit-Tests:    Kein RefreshDatabase nötig (keine DB-Zugriffe)
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Globale Hilfsfunktionen
|--------------------------------------------------------------------------
*/

/**
 * Legt einen User mit den angegebenen Permissions an.
 */
function createUserWithPermission(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $perm) {
        $permission = Permission::firstOrCreate([
            'name'       => $perm,
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);
    }

    // Relations neu laden – kein fresh() damit Spatie den selben Objekt-State nutzt
    $user->load(['roles', 'permissions']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Legt einen User mit der super-admin-Rolle an.
 *
 * Gate::before in AppServiceProvider prüft $user->hasRole('super-admin').
 * Damit das in Tests zuverlässig klappt:
 *  - KEIN fresh() (würde Relations-Cache verlieren und DB-Reload triggern)
 *  - Rollen explizit laden (load()) NACH assignRole()
 *  - forgetCachedPermissions() NACH dem Laden, nicht davor
 */
function createSuperAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    // Rollen explizit neu laden – gleiche Objekt-Instanz bleibt erhalten
    $user->load(['roles', 'permissions']);

    // Cache NACH dem Laden leeren
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Legt einen einfachen User ohne jede Permission an.
 */
function createPlainUser(): User
{
    return User::factory()->create();
}

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
