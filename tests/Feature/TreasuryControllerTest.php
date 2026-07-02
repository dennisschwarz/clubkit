<?php

use Illuminate\Support\Facades\DB;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryCategory;
use Modules\Treasury\Models\TreasuryTransaction;

// Seed the Treasury module into installed_modules before each test.
// RefreshDatabase clears installed_modules after every test, so this must
// run in beforeEach rather than once globally.
beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        'slug'         => 'treasury',
        'name'         => 'Treasury',
        'version'      => '1.0.0',
        'is_active'    => true,
        'installed_at' => now(),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    app(\App\Services\ModuleLoader::class)->seedPermissions('treasury');
});

// ── Auth guard ─────────────────────────────────────────────────────────────────

test('gast wird bei GET /treasury auf login weitergeleitet', function () {
    $this->get(route('treasury.index'))->assertRedirect(route('login'));
});

test('user ohne treasury.view bekommt 403', function () {
    $this->actingAs(createPlainUser())
        ->get(route('treasury.index'))
        ->assertForbidden();
});

// ── Index ──────────────────────────────────────────────────────────────────────

test('user mit treasury.view kann die Kassenübersicht aufrufen', function () {
    $this->actingAs(createUserWithPermission('treasury.view'))
        ->get(route('treasury.index'))
        ->assertOk();
});

// ── Transactions ───────────────────────────────────────────────────────────────

test('user mit treasury.transactions.manage kann eine Buchung anlegen', function () {
    $account = TreasuryAccount::factory()->create();

    $this->actingAs(createUserWithPermission('treasury.view', 'treasury.transactions.manage'))
        ->post(route('treasury.transactions.store'), [
            'account_id'       => $account->id,
            'type'             => 'income',
            'amount'           => 150.00,
            'description'      => 'Test-Buchung',
            'transaction_date' => '2026-06-01',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('treasury_transactions', [
        'account_id'  => $account->id,
        'type'        => 'income',
        'description' => 'Test-Buchung',
    ]);
});

test('type "both" schlägt bei der Validierung fehl', function () {
    $account = TreasuryAccount::factory()->create();

    $this->actingAs(createUserWithPermission('treasury.view', 'treasury.transactions.manage'))
        ->post(route('treasury.transactions.store'), [
            'account_id'       => $account->id,
            'type'             => 'both',
            'amount'           => 100.00,
            'description'      => 'Ungültig',
            'transaction_date' => '2026-06-01',
        ])
        ->assertSessionHasErrors('type');
});

test('eine Buchung kann aktualisiert werden', function () {
    $tx   = TreasuryTransaction::factory()->income()->create(['description' => 'Alt']);
    $user = createUserWithPermission('treasury.view', 'treasury.transactions.manage');

    $this->actingAs($user)
        ->patch(route('treasury.transactions.update', $tx->id), [
            'account_id'       => $tx->account_id,
            'type'             => 'income',
            'amount'           => $tx->amount,
            'description'      => 'Neu',
            'transaction_date' => $tx->transaction_date->format('Y-m-d'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('treasury_transactions', ['id' => $tx->id, 'description' => 'Neu']);
});

test('eine Buchung kann gelöscht werden', function () {
    $tx   = TreasuryTransaction::factory()->income()->create();
    $user = createUserWithPermission('treasury.view', 'treasury.transactions.manage');

    $this->actingAs($user)
        ->delete(route('treasury.transactions.destroy', $tx->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('treasury_transactions', ['id' => $tx->id]);
});

// ── Accounts ───────────────────────────────────────────────────────────────────

test('user mit treasury.accounts.manage kann ein Konto anlegen', function () {
    $this->actingAs(createUserWithPermission('treasury.view', 'treasury.accounts.manage'))
        ->post(route('treasury.accounts.store'), [
            'name'       => 'Jugendkasse',
            'visibility' => 'public',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('treasury_accounts', ['name' => 'Jugendkasse']);
});

test('ein Konto mit Buchungen kann nicht gelöscht werden', function () {
    $account = TreasuryAccount::factory()->create();
    TreasuryTransaction::factory()->income()->create(['account_id' => $account->id]);

    $this->actingAs(createUserWithPermission('treasury.view', 'treasury.accounts.manage'))
        ->delete(route('treasury.accounts.destroy', $account->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('treasury_accounts', ['id' => $account->id]);
});

// ── Categories ─────────────────────────────────────────────────────────────────

test('user mit treasury.categories.manage kann eine Kategorie anlegen', function () {
    $this->actingAs(createUserWithPermission('treasury.view', 'treasury.categories.manage'))
        ->post(route('treasury.categories.store'), [
            'name'             => 'Spende',
            'transaction_type' => 'income',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('treasury_categories', [
        'name'             => 'Spende',
        'transaction_type' => 'income',
    ]);
});

test('transaction_type "both" ist bei Kategorien ungültig', function () {
    $this->actingAs(createUserWithPermission('treasury.view', 'treasury.categories.manage'))
        ->post(route('treasury.categories.store'), [
            'name'             => 'Ungültig',
            'transaction_type' => 'both',
        ])
        ->assertSessionHasErrors('transaction_type');
});
