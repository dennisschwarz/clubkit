<?php

declare(strict_types=1);

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
| Feature tests: TestCase + RefreshDatabase (DB reset after each test).
| Unit tests:    No RefreshDatabase needed (no DB access).
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Global Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a user and assign the given permissions to them.
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

    // Reload relations – no fresh() to keep Spatie using the same object state.
    $user->load(['roles', 'permissions']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Create a user with the super-admin role.
 *
 * Gate::before in AppServiceProvider checks $user->hasRole('super-admin').
 * For reliable test behaviour:
 *  - NO fresh() (would lose the relations cache and trigger a DB reload)
 *  - Reload roles explicitly (load()) AFTER assignRole()
 *  - forgetCachedPermissions() AFTER loading, not before
 */
function createSuperAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    // Reload roles – same object instance is kept.
    $user->load(['roles', 'permissions']);

    // Clear cache AFTER loading.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Create a plain user without any permissions or roles.
 */
function createPlainUser(): User
{
    return User::factory()->create();
}

/**
 * Flush Spatie's permission cache after seeding installed_modules.
 *
 * Call this in beforeEach() after DB::table('installed_modules')->insertOrIgnore()
 * to ensure freshly registered module permissions are visible in the current test.
 */
function seedPermissions(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
