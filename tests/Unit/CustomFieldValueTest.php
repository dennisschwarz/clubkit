<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────────────────────────

/** Creates a simple field definition, incrementing the slug on each call. */
function makeDef(array $overrides = []): CustomFieldDefinition
{
    static $i = 0;
    $i++;
    return CustomFieldDefinition::create(array_merge([
        'object_type' => 'member',
        'label'       => 'Test Field ' . $i,
        'slug'        => 'test_field_' . $i,
        'field_type'  => 'text',
    ], $overrides));
}

// ── Saving values ─────────────────────────────────────────────────────────────

test('a field value can be stored', function () {
    $def = makeDef();
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 42, 'value' => 'M']);

    expect(
        CustomFieldValue::where('definition_id', $def->id)
            ->where('entity_id', 42)
            ->where('value', 'M')
            ->exists()
    )->toBeTrue();
});

test('a field value may be null', function () {
    $def = makeDef();
    $val = CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => null]);

    expect($val->fresh()->value)->toBeNull();
});

test('different entities can have independent values for the same field', function () {
    $def = makeDef();
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => 'M']);
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 2, 'value' => 'XL']);
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 3, 'value' => 'S']);

    expect(CustomFieldValue::where('definition_id', $def->id)->count())->toBe(3);
});

// ── Unique constraint ─────────────────────────────────────────────────────────

test('only one value is allowed per field and entity', function () {
    $def = makeDef();
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 5, 'value' => 'Old']);

    expect(fn () => CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 5, 'value' => 'New']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('updateOrCreate updates an existing value without creating a duplicate', function () {
    $def = makeDef();
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 7, 'value' => 'Old']);

    CustomFieldValue::updateOrCreate(
        ['definition_id' => $def->id, 'entity_id' => 7],
        ['value'         => 'New']
    );

    expect(CustomFieldValue::where('definition_id', $def->id)->where('entity_id', 7)->count())->toBe(1);
    expect(CustomFieldValue::where('definition_id', $def->id)->where('entity_id', 7)->value('value'))->toBe('New');
});

test('updateOrCreate creates a new value when none exists', function () {
    $def = makeDef();

    CustomFieldValue::updateOrCreate(
        ['definition_id' => $def->id, 'entity_id' => 99],
        ['value'         => 'Newly created']
    );

    expect(
        CustomFieldValue::where('definition_id', $def->id)
            ->where('entity_id', 99)
            ->where('value', 'Newly created')
            ->exists()
    )->toBeTrue();
});

// ── Cascade delete ────────────────────────────────────────────────────────────

test('deleting the definition cascades to all its values', function () {
    $def = makeDef();
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => 'A']);
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 2, 'value' => 'B']);

    $defId = $def->id;
    $def->delete();

    expect(CustomFieldValue::where('definition_id', $defId)->count())->toBe(0);
});

// ── Relations ─────────────────────────────────────────────────────────────────

test('a value knows its definition', function () {
    $def = makeDef(['label' => 'Heimatverein', 'slug' => 'heimatverein']);
    $val = CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => 'SV Test']);

    expect($val->definition->label)->toBe('Heimatverein');
});

test('a definition knows all its values', function () {
    $def = makeDef();
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 10, 'value' => 'X']);
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 11, 'value' => 'Y']);

    expect($def->fresh()->values)->toHaveCount(2);
});

// ── Checkbox edge case ────────────────────────────────────────────────────────

test('checkbox values are stored as 1 or 0', function () {
    $def = makeDef(['label' => 'Active', 'slug' => 'active', 'field_type' => 'checkbox']);
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => '1']);
    CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 2, 'value' => '0']);

    expect(CustomFieldValue::where('definition_id', $def->id)->where('entity_id', 1)->value('value'))->toBe('1');
    expect(CustomFieldValue::where('definition_id', $def->id)->where('entity_id', 2)->value('value'))->toBe('0');
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S8

test('creating a field value writes a created activity log entry', function () {
    $def = makeDef();
    $val = CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => 'Logged']);

    $activity = Activity::where('subject_type', CustomFieldValue::class)
        ->where('subject_id', $val->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('custom-fields');
});

test('updating a field value writes an updated activity log entry', function () {
    $def = makeDef();
    $val = CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => 'Before']);
    $val->update(['value' => 'After']);

    $activity = Activity::where('subject_type', CustomFieldValue::class)
        ->where('subject_id', $val->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['value'])->toBe('After');
});
