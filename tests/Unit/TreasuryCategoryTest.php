<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Treasury\Models\TreasuryCategory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a treasury category can be created as income type', function () {
    $cat = TreasuryCategory::factory()->income()->create(['name' => 'Mitgliedsbeitrag']);

    expect($cat->transaction_type)->toBe('income')
        ->and($cat->name)->toBe('Mitgliedsbeitrag');
});

test('a treasury category can be created as expense type', function () {
    $cat = TreasuryCategory::factory()->expense()->create(['name' => 'Anschaffung']);

    expect($cat->transaction_type)->toBe('expense');
});

test('a category does not accept both as transaction type', function () {
    // 'both' is not a valid enum value and must throw a database exception
    expect(fn () => TreasuryCategory::factory()->create(['transaction_type' => 'both']))
        ->toThrow(\Exception::class);
});

test('toJsOption returns the expected array shape', function () {
    $cat = TreasuryCategory::factory()->income()->create(['color' => 'green']);

    $option = $cat->toJsOption();

    expect($option)->toHaveKeys(['id', 'name', 'transaction_type', 'color'])
        ->and($option['transaction_type'])->toBe('income')
        ->and($option['color'])->toBe('green');
});
