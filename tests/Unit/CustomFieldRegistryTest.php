<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CustomFields\Services\CustomFieldRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Object types ──────────────────────────────────────────────────────────────

test('member is always available as an object type', function () {
    $types = CustomFieldRegistry::availableObjectTypes();

    expect($types)->toBeArray();
    expect($types)->toHaveKey('member');
    expect($types['member'])->toBe('Mitglied');
});

test('availableObjectTypes returns a non-empty array', function () {
    expect(CustomFieldRegistry::availableObjectTypes())->not->toBeEmpty();
});

test('team is available when the teams table exists', function () {
    $types = CustomFieldRegistry::availableObjectTypes();

    if (! array_key_exists('team', $types)) {
        $this->markTestSkipped('Teams module is not installed in this test environment.');
    }

    expect($types['team'])->toBe('Team');
});

// ── Field types ───────────────────────────────────────────────────────────────

test('all required base field types are present', function () {
    $types    = CustomFieldRegistry::fieldTypes();
    $required = ['text', 'textarea', 'number', 'select', 'checkbox', 'date', 'email'];

    foreach ($required as $type) {
        expect($types)->toHaveKey($type);
    }
});

test('the number type label contains the word Ganzzahl', function () {
    expect(CustomFieldRegistry::fieldTypes())->toHaveKey('number');
    expect(CustomFieldRegistry::fieldTypes()['number'])->toContain('Ganzzahl');
});

test('the decimal type is present', function () {
    expect(CustomFieldRegistry::fieldTypes())->toHaveKey('decimal');
});

test('all field types have non-empty display labels', function () {
    foreach (CustomFieldRegistry::fieldTypes() as $key => $label) {
        expect($label)->toBeString();
        expect(strlen($label))->toBeGreaterThan(0);
    }
});

// ── Helper methods ────────────────────────────────────────────────────────────

test('isValidObjectType returns true for member', function () {
    expect(CustomFieldRegistry::isValidObjectType('member'))->toBeTrue();
});

test('isValidObjectType returns false for unknown types', function () {
    expect(CustomFieldRegistry::isValidObjectType('invoice'))->toBeFalse();
    expect(CustomFieldRegistry::isValidObjectType(''))->toBeFalse();
    expect(CustomFieldRegistry::isValidObjectType('MEMBER'))->toBeFalse();
});

test('objectTypeLabel returns the German display name for a known type', function () {
    expect(CustomFieldRegistry::objectTypeLabel('member'))->toBe('Mitglied');
});

test('objectTypeLabel returns the key itself for an unknown type', function () {
    expect(CustomFieldRegistry::objectTypeLabel('unknown'))->toBe('unknown');
});

// ── inputFieldTypes ───────────────────────────────────────────────────────────

test('inputFieldTypes does not include select, checkbox, or textarea', function () {
    $inputTypes = CustomFieldRegistry::inputFieldTypes();

    expect(in_array('select',   $inputTypes))->toBeFalse();
    expect(in_array('checkbox', $inputTypes))->toBeFalse();
    expect(in_array('textarea', $inputTypes))->toBeFalse();
});

test('inputFieldTypes includes number and date', function () {
    $inputTypes = CustomFieldRegistry::inputFieldTypes();

    expect(in_array('number', $inputTypes))->toBeTrue();
    expect(in_array('date',   $inputTypes))->toBeTrue();
});
