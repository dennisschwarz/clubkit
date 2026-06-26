<?php

use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// Hilfsfunktion: Feld-Definition anlegen
function makeDef(array $overrides = []): CustomFieldDefinition
{
    static $i = 0;
    $i++;
    return CustomFieldDefinition::create(array_merge([
        'object_type' => 'member',
        'label'       => 'Testfeld ' . $i,
        'slug'        => 'testfeld_' . $i,
        'field_type'  => 'text',
    ], $overrides));
}

// ── Speichern ─────────────────────────────────────────────────────────────────

test('ein Feldwert kann gespeichert werden', function () {
    $def = makeDef();
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 42, 'value' => 'M']);

    $this->assertDatabaseHas('custom_field_values', ['field_id' => $def->id, 'entity_id' => 42, 'value' => 'M']);
});

test('Feldwert darf null sein', function () {
    $def = makeDef();
    $val = CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 1, 'value' => null]);

    expect($val->fresh()->value)->toBeNull();
});

test('verschiedene Entitäten können eigene Werte haben', function () {
    $def = makeDef();
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 1, 'value' => 'M']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 2, 'value' => 'XL']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 3, 'value' => 'S']);

    expect(CustomFieldValue::where('field_id', $def->id)->count())->toBe(3);
});

// ── Unique-Constraint ─────────────────────────────────────────────────────────

test('pro Feld und Entität darf es nur einen Wert geben', function () {
    $def = makeDef();
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 5, 'value' => 'Alt']);

    expect(fn () => CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 5, 'value' => 'Neu']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('updateOrCreate aktualisiert vorhandenen Wert ohne Duplikat', function () {
    $def = makeDef();
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 7, 'value' => 'Alt']);

    CustomFieldValue::updateOrCreate(
        ['field_id' => $def->id, 'entity_id' => 7],
        ['value'    => 'Neu']
    );

    expect(CustomFieldValue::where('field_id', $def->id)->where('entity_id', 7)->count())->toBe(1);
    expect(CustomFieldValue::where('field_id', $def->id)->where('entity_id', 7)->value('value'))->toBe('Neu');
});

test('updateOrCreate legt neuen Wert an wenn nicht vorhanden', function () {
    $def = makeDef();

    CustomFieldValue::updateOrCreate(
        ['field_id' => $def->id, 'entity_id' => 99],
        ['value'    => 'Neu angelegt']
    );

    $this->assertDatabaseHas('custom_field_values', ['field_id' => $def->id, 'entity_id' => 99, 'value' => 'Neu angelegt']);
});

// ── Cascade-Delete ────────────────────────────────────────────────────────────

test('beim Löschen der Definition werden alle Werte kaskadiert gelöscht', function () {
    $def = makeDef();
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 1, 'value' => 'A']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 2, 'value' => 'B']);

    $defId = $def->id;
    $def->delete();

    expect(CustomFieldValue::where('field_id', $defId)->count())->toBe(0);
});

// ── Relationen ────────────────────────────────────────────────────────────────

test('Feldwert kennt seine Definition', function () {
    $def = makeDef(['label' => 'Heimatverein', 'slug' => 'heimatverein']);
    $val = CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 1, 'value' => 'SV Test']);

    expect($val->definition->label)->toBe('Heimatverein');
});

test('Definition kennt alle ihre Werte', function () {
    $def = makeDef();
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 10, 'value' => 'X']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 11, 'value' => 'Y']);

    expect($def->fresh()->values)->toHaveCount(2);
});

// ── Checkbox-Sonderfall ───────────────────────────────────────────────────────

test('Checkbox-Werte werden als 1 oder 0 gespeichert', function () {
    $def = makeDef(['label' => 'Aktiv', 'slug' => 'aktiv', 'field_type' => 'checkbox']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 1, 'value' => '1']);
    CustomFieldValue::create(['field_id' => $def->id, 'entity_id' => 2, 'value' => '0']);

    expect(CustomFieldValue::where('field_id', $def->id)->where('entity_id', 1)->value('value'))->toBe('1');
    expect(CustomFieldValue::where('field_id', $def->id)->where('entity_id', 2)->value('value'))->toBe('0');
});
