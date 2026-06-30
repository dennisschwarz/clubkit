<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Services\TreasuryVisibilityService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helper (local scope, no conflict with Pest.php global helpers) ─────────────

/**
 * Creates a user with the given permission for use in visibility tests.
 */
function makeTreasuryUser(string $permission): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::firstOrCreate([
        'name'       => $permission,
        'guard_name' => 'web',
    ]));
    $user->load(['roles', 'permissions']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    return $user;
}

// ── Public accounts ────────────────────────────────────────────────────────────

test('ein öffentliches Konto ist für jeden User mit treasury.view sichtbar', function () {
    $account = TreasuryAccount::factory()->create(['visibility' => 'public']);
    $user    = makeTreasuryUser('treasury.view');

    $service = new TreasuryVisibilityService();

    expect($service->userCanSeeAccount($user, $account->load('teams')))->toBeTrue();
});

test('ein öffentliches Konto ist ohne treasury.view nicht sichtbar', function () {
    $account = TreasuryAccount::factory()->create(['visibility' => 'public']);
    $user    = User::factory()->create();

    $service = new TreasuryVisibilityService();

    expect($service->userCanSeeAccount($user, $account->load('teams')))->toBeFalse();
});

// ── treasury.accounts.manage override ─────────────────────────────────────────

test('treasury.accounts.manage sieht alle Konten unabhängig von der Sichtbarkeit', function () {
    $account = TreasuryAccount::factory()->teamRestricted()->create();
    $user    = makeTreasuryUser('treasury.accounts.manage');

    $service = new TreasuryVisibilityService();

    expect($service->userCanSeeAccount($user, $account->load('teams')))->toBeTrue();
});

// ── Team-restricted accounts ───────────────────────────────────────────────────

test('ein team_restricted Konto ohne Team-Zuweisung ist für niemanden sichtbar', function () {
    $account = TreasuryAccount::factory()->teamRestricted()->create();
    $user    = makeTreasuryUser('treasury.view');

    $service = new TreasuryVisibilityService();

    expect($service->userCanSeeAccount($user, $account->load('teams')))->toBeFalse();
});

test('ein team_restricted Konto ist nicht sichtbar wenn der User kein Mitgliedsprofil hat', function () {
    $account = TreasuryAccount::factory()->teamRestricted()->create();
    $team    = Team::factory()->create();
    $account->teams()->sync([$team->id]);

    $user = makeTreasuryUser('treasury.view');

    $service = new TreasuryVisibilityService();

    expect($service->userCanSeeAccount($user, $account->load('teams')))->toBeFalse();
});

test('ein team_restricted Konto ist sichtbar wenn der User ein Mitglied des Teams ist', function () {
    $account = TreasuryAccount::factory()->teamRestricted()->create();
    $team    = Team::factory()->create();
    $account->teams()->sync([$team->id]);

    $user   = makeTreasuryUser('treasury.view');
    $member = Member::factory()->create(['user_id' => $user->id]);
    $team->members()->attach($member->id);

    $service = new TreasuryVisibilityService();

    expect($service->userCanSeeAccount($user, $account->load('teams')))->toBeTrue();
});

// ── visibleAccountIds ─────────────────────────────────────────────────────────

test('visibleAccountIds liefert nur sichtbare Konten', function () {
    $public     = TreasuryAccount::factory()->create(['visibility' => 'public']);
    $restricted = TreasuryAccount::factory()->teamRestricted()->create();

    $user = makeTreasuryUser('treasury.view');

    $ids = (new TreasuryVisibilityService())->visibleAccountIds($user);

    expect($ids)->toContain($public->id)
        ->not->toContain($restricted->id);
});
