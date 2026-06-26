<?php

use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Anlegen ───────────────────────────────────────────────────────────────────

test('eine Felddefinition kann angelegt werden', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member',
        'label'       => 'Trikotgröße',
        'slug'        => 'trikotgroesse',
        'field_type'  => 'text',
    ]);

    $this->assertDatabaseHas('custom_field_definitions', [
        'id' => $def->id, 'label' => 'Trikotgröße', 'object_type' => 'member',
    ]);
});

test('Pflichtfeld und Sortierung können gesetzt werden', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'team',
        'label'       => 'Heimtrikot-Farbe',
        'slug'        => 'heimtrikot_farbe',
        'field_type'  => 'text',
        'placeholder' => 'z.B. Blau-Weiß',
        'is_required' => true,
        'sort_order'  => 20,
    ]);

    expect($def->is_required)->toBeTrue();
    expect($def->sort_order)->toBe(20);
    expect($def->placeholder)->toBe('z.B. Blau-Weiß');
});

// ── Slug-Eindeutigkeit ────────────────────────────────────────────────────────

test('gleicher Slug ist für verschiedene object_types erlaubt', function () {
    CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Notiz', 'slug' => 'notiz', 'field_type' => 'text']);
    CustomFieldDefinition::create(['object_type' => 'team',   'label' => 'Notiz', 'slug' => 'notiz', 'field_type' => 'text']);

    expect(CustomFieldDefinition::where('slug', 'notiz')->count())->toBe(2);
});

test('gleicher Slug im selben object_type verletzt Unique-Constraint', function () {
    CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Verein vorher', 'slug' => 'verein_vorher', 'field_type' => 'text']);

    expect(fn () => CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Duplikat', 'slug' => 'verein_vorher', 'field_type' => 'text']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── Select-Optionen ───────────────────────────────────────────────────────────

test('Optionen werden als JSON-Array gespeichert und korrekt ausgelesen', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Trikotgröße',
        'slug'        => 'trikotgroesse', 'field_type' => 'select',
        'options'     => ['S', 'M', 'L', 'XL'],
    ]);

    expect($def->fresh()->options)->toBeArray();
    expect($def->fresh()->options)->toBe(['S', 'M', 'L', 'XL']);
});

test('optionsAsText() gibt Optionen zeilengetrennt zurück', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Position', 'slug' => 'position',
        'field_type'  => 'select', 'options' => ['Torwart', 'Verteidiger', 'Mittelfeld', 'Stürmer'],
    ]);

    expect($def->optionsAsText())->toBe("Torwart\nVerteidiger\nMittelfeld\nStürmer");
});

test('optionsAsText() gibt leeren String zurück wenn keine Optionen gesetzt', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Notiz', 'slug' => 'notiz', 'field_type' => 'text',
    ]);

    expect($def->optionsAsText())->toBe('');
});

test('hasOptions() ist true nur für select-Typ', function () {
    $select  = CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Größe',   'slug' => 'groesse',   'field_type' => 'select']);
    $text    = CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Notiz',   'slug' => 'notiz',     'field_type' => 'text']);
    $number  = CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Gewicht', 'slug' => 'gewicht',   'field_type' => 'number']);

    expect($select->hasOptions())->toBeTrue();
    expect($text->hasOptions())->toBeFalse();
    expect($number->hasOptions())->toBeFalse();
});

// ── Feldtypen ─────────────────────────────────────────────────────────────────

test('number-Typ kann gespeichert werden', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Trikotnummer', 'slug' => 'trikotnummer', 'field_type' => 'number',
    ]);

    expect($def->field_type)->toBe('number');
});

test('is_required wird korrekt als Boolean gecastet', function () {
    $pflicht  = CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Pflicht',   'slug' => 'pflicht',  'field_type' => 'text', 'is_required' => true]);
    $optional = CustomFieldDefinition::create(['object_type' => 'member', 'label' => 'Optional',  'slug' => 'optional', 'field_type' => 'text', 'is_required' => false]);

    expect($pflicht->fresh()->is_required)->toBeTrue();
    expect($optional->fresh()->is_required)->toBeFalse();
});

// ── Cascade-Delete ────────────────────────────────────────────────────────────

test('beim Löschen einer Definition werden alle Werte kaskadiert gelöscht', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Sport', 'slug' => 'sport', 'field_type' => 'text',
    ]);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 1, 'value' => 'Fußball']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 2, 'value' => 'Tennis']);

    $defId = $def->id;
    $def->delete();

    $this->assertDatabaseMissing('custom_field_definitions', ['id'       => $defId]);
    $this->assertDatabaseMissing('custom_field_values',      ['field_id' => $defId]);
});
