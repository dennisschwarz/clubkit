<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryTransaction;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Basic model creation ───────────────────────────────────────────────────

test('a treasury account can be created with required fields', function () {
    $account = TreasuryAccount::factory()->create(['name' => 'Hauptkasse', 'visibility' => 'public']);

    expect($account->id)->not->toBeNull()
        ->and($account->name)->toBe('Hauptkasse')
        ->and($account->visibility)->toBe('public')
        ->and($account->parent_id)->toBeNull();
});

test('a treasury account can be a sub-account of another', function () {
    $parent = TreasuryAccount::factory()->create(['name' => 'Elternkonto']);
    $child  = TreasuryAccount::factory()->childOf($parent)->create(['name' => 'Unterkonto']);

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->parent->name)->toBe('Elternkonto');
});

test('a treasury account defaults to public visibility', function () {
    $account = TreasuryAccount::factory()->create();

    expect($account->visibility)->toBe('public');
});

test('a team_restricted account can be created', function () {
    $account = TreasuryAccount::factory()->teamRestricted()->create();

    expect($account->visibility)->toBe('team_restricted');
});

// ── Balance computation ────────────────────────────────────────────────────

test('computed balance returns zero for an account with no transactions', function () {
    $account = TreasuryAccount::factory()->create();

    expect($account->computedBalance())->toBe(0.0);
});

test('computed balance is positive when income exceeds expense', function () {
    $account = TreasuryAccount::factory()->create();
    TreasuryTransaction::factory()->income()->create(['account_id' => $account->id, 'amount' => 200.00]);
    TreasuryTransaction::factory()->expense()->create(['account_id' => $account->id, 'amount' => 50.00]);

    expect($account->computedBalance())->toBe(150.0);
});

test('computed balance is negative when expense exceeds income', function () {
    $account = TreasuryAccount::factory()->create();
    TreasuryTransaction::factory()->expense()->create(['account_id' => $account->id, 'amount' => 300.00]);

    expect($account->computedBalance())->toBe(-300.0);
});

// ── Consolidated balance ───────────────────────────────────────────────────

test('consolidated balance includes sub-account transactions', function () {
    $parent = TreasuryAccount::factory()->create();
    $child  = TreasuryAccount::factory()->childOf($parent)->create();

    TreasuryTransaction::factory()->income()->create(['account_id' => $parent->id, 'amount' => 100.00]);
    TreasuryTransaction::factory()->income()->create(['account_id' => $child->id, 'amount' => 50.00]);

    expect($parent->load('children')->consolidatedBalance())->toBe(150.0);
});

// ── JS option helper ──────────────────────────────────────────────────────

test('toJsOption returns the expected array shape', function () {
    $account = TreasuryAccount::factory()->create([
        'name'       => 'Testkasse',
        'visibility' => 'public',
    ]);

    $option = $account->toJsOption();

    expect($option)->toHaveKeys(['id', 'name', 'visibility', 'parent_id', 'description']);
    expect($option['name'])->toBe('Testkasse');
});
