<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryTransaction;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a transaction can be created as income', function () {
    $tx = TreasuryTransaction::factory()->income()->create(['amount' => 100.00]);

    expect($tx->type)->toBe('income')
        ->and((float) $tx->amount)->toBe(100.0);
});

test('a transaction can be created as expense', function () {
    $tx = TreasuryTransaction::factory()->expense()->create(['amount' => 50.00]);

    expect($tx->type)->toBe('expense');
});

test('signed amount is positive for income', function () {
    $tx = TreasuryTransaction::factory()->income()->create(['amount' => 75.00]);

    expect($tx->signedAmount())->toBe(75.0);
});

test('signed amount is negative for expense', function () {
    $tx = TreasuryTransaction::factory()->expense()->create(['amount' => 40.00]);

    expect($tx->signedAmount())->toBe(-40.0);
});

test('a transaction belongs to an account', function () {
    $account = TreasuryAccount::factory()->create();
    $tx      = TreasuryTransaction::factory()->create(['account_id' => $account->id]);

    expect($tx->account->id)->toBe($account->id);
});

test('a transaction can have an optional category', function () {
    $tx = TreasuryTransaction::factory()->create(['category_id' => null]);

    expect($tx->category_id)->toBeNull();
});

test('activity log records creation of a transaction', function () {
    $tx = TreasuryTransaction::factory()->income()->create([
        'description' => 'Testbuchung',
        'amount'      => 100.00,
    ]);

    // Scope by subject_type + subject_id to avoid picking up the TreasuryAccount
    // log entry that the factory creates (both use log_name 'treasury').
    $log = \Spatie\Activitylog\Models\Activity::where('log_name', 'treasury')
        ->where('subject_type', TreasuryTransaction::class)
        ->where('subject_id', $tx->id)
        ->first();

    expect($log)->not->toBeNull();

    // Spatie activitylog v5 stores model attribute changes in `properties['attributes']`
    // when the config/activitylog.php file is not published (package default behaviour).
    // If the package config is published and `attribute_changes_column` is set to true,
    // model changes move to the separate `attribute_changes` column instead.
    // We support both layouts to remain config-agnostic.
    $description = $log->attribute_changes['attributes']['description']
        ?? $log->properties['attributes']['description']
        ?? null;

    expect($description)->toBe('Testbuchung');
});