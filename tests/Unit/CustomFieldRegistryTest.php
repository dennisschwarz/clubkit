<?php

use Modules\CustomFields\Services\CustomFieldRegistry;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Objekt-Typen ──────────────────────────────────────────────────────────────

test('member ist immer als Objekt-Typ verfügbar', function () {
    $types = CustomFieldRegistry::availableObjectTypes();

    expect($types)->toBeArray();
    expect($types)->toHaveKey('member');
    expect($types['member'])->toBe('Mitglied');
});

test('availableObjectTypes gibt ein nicht-leeres Array zurück', function () {
    expect(CustomFieldRegistry::availableObjectTypes())->not->toBeEmpty();
});

test('team ist verfügbar wenn die teams-Tabelle existiert', function () {
    $types = CustomFieldRegistry::availableObjectTypes();

    if (! array_key_exists('team', $types)) {
        $this->markTestSkipped('Teams-Modul ist in dieser Testumgebung nicht installiert.');
    }

    expect($types['team'])->toBe('Team');
});

// ── Feldtypen ─────────────────────────────────────────────────────────────────

test('alle Basis-Feldtypen sind vorhanden', function () {
    $types   = CustomFieldRegistry::fieldTypes();
    $pflicht = ['text', 'textarea', 'number', 'select', 'checkbox', 'date', 'email'];

    foreach ($pflicht as $typ) {
        // toHaveKey() prüft nur das Vorhandensein des Schlüssels (kein zweites Argument = kein Wert-Vergleich)
        expect($types)->toHaveKey($typ);
    }
});

test('number-Typ enthält Ganzzahl im Anzeigenamen', function () {
    expect(CustomFieldRegistry::fieldTypes())->toHaveKey('number');
    expect(CustomFieldRegistry::fieldTypes()['number'])->toContain('Ganzzahl');
});

test('decimal-Typ ist vorhanden', function () {
    expect(CustomFieldRegistry::fieldTypes())->toHaveKey('decimal');
});

test('alle Feldtypen haben nicht-leere deutsche Anzeigenamen', function () {
    foreach (CustomFieldRegistry::fieldTypes() as $key => $label) {
        expect($label)->toBeString();
        expect(strlen($label))->toBeGreaterThan(0);
    }
});

// ── Hilfsmethoden ─────────────────────────────────────────────────────────────

test('isValidObjectType gibt true für member', function () {
    expect(CustomFieldRegistry::isValidObjectType('member'))->toBeTrue();
});

test('isValidObjectType gibt false für unbekannte Typen', function () {
    expect(CustomFieldRegistry::isValidObjectType('invoice'))->toBeFalse();
    expect(CustomFieldRegistry::isValidObjectType(''))->toBeFalse();
    expect(CustomFieldRegistry::isValidObjectType('MEMBER'))->toBeFalse();
});

test('objectTypeLabel gibt deutschen Anzeigenamen zurück', function () {
    expect(CustomFieldRegistry::objectTypeLabel('member'))->toBe('Mitglied');
});

test('objectTypeLabel gibt den Key zurück bei unbekanntem Typ', function () {
    expect(CustomFieldRegistry::objectTypeLabel('unbekannt'))->toBe('unbekannt');
});

// ── inputFieldTypes ───────────────────────────────────────────────────────────

test('inputFieldTypes enthält keinen select-, checkbox- oder textarea-Typ', function () {
    $inputTypes = CustomFieldRegistry::inputFieldTypes();

    expect(in_array('select',   $inputTypes))->toBeFalse();
    expect(in_array('checkbox', $inputTypes))->toBeFalse();
    expect(in_array('textarea', $inputTypes))->toBeFalse();
});

test('inputFieldTypes enthält number und date', function () {
    $inputTypes = CustomFieldRegistry::inputFieldTypes();

    expect(in_array('number', $inputTypes))->toBeTrue();
    expect(in_array('date',   $inputTypes))->toBeTrue();
});
