<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Creates a minimal field definition with a unique slug. */
function makeFieldDef(array $overrides = []): CustomFieldDefinition
{
    static $n = 0;
    $n++;
    return CustomFieldDefinition::create(array_merge([
        'object_type' => 'member',
        'label'       => 'Test Field ' . $n,
        'slug'        => 'test_field_' . $n,
        'field_type'  => 'text',
    ], $overrides));
}

// ── Creation ──────────────────────────────────────────────────────────────────

test('a field definition can be created', function () {
    $def = makeFieldDef(['label' => 'Trikotgröße', 'slug' => 'trikogroesse']);

    expect(CustomFieldDefinition::where('slug', 'trikogroesse')->exists())->toBeTrue();
});

test('required defaults to false', function () {
    $def = makeFieldDef();

    expect($def->is_required)->toBeFalse();
});

test('a select field can have options', function () {
    $def = makeFieldDef([
        'field_type' => 'select',
        'options'    => ['S', 'M', 'L', 'XL'],
    ]);

    expect($def->options)->toBe(['S', 'M', 'L', 'XL']);
});

test('a non-select field has null options', function () {
    $def = makeFieldDef(['field_type' => 'text', 'options' => null]);

    expect($def->options)->toBeNull();
});

// ── Object types ──────────────────────────────────────────────────────────────

test('field definition can target different object types', function () {
    $member = makeFieldDef(['object_type' => 'member']);
    $team   = makeFieldDef(['object_type' => 'team']);

    expect($member->object_type)->toBe('member');
    expect($team->object_type)->toBe('team');
});

// ── Slug uniqueness ───────────────────────────────────────────────────────────

test('slug must be unique per object_type', function () {
    makeFieldDef(['slug' => 'duplicate_slug', 'object_type' => 'member']);

    expect(fn () => makeFieldDef(['slug' => 'duplicate_slug', 'object_type' => 'member']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── Ordering ──────────────────────────────────────────────────────────────────

test('sort_order defaults to zero', function () {
    $def = makeFieldDef();

    expect($def->fresh()->sort_order)->toBe(0);
});

// ── creator() relation ────────────────────────────────────────────────────────

test('creator relation returns the user who created the definition', function () {
    $user = App\Models\User::factory()->create();
    $def  = makeFieldDef(['created_by' => $user->id]);

    expect($def->creator->id)->toBe($user->id);
});

test('creator relation is null when created_by is null', function () {
    $def = makeFieldDef(['created_by' => null]);

    expect($def->creator)->toBeNull();
});

// ── values() relation ─────────────────────────────────────────────────────────

test('a field definition can have multiple values', function () {
    $def = makeFieldDef();

    Modules\CustomFields\Models\CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 1, 'value' => 'A']);
    Modules\CustomFields\Models\CustomFieldValue::create(['definition_id' => $def->id, 'entity_id' => 2, 'value' => 'B']);

    expect($def->fresh()->values)->toHaveCount(2);
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration (database/migrations/
// 2026_06_22_135559_create_activity_log_table.php) with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists in the DB,
// attribute diffs are stored in attribute_changes — NOT in properties.
// properties only holds custom data (e.g. IP address via CoreServiceProvider).
//
// CORRECT:  $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S8–S13

test('creating a field definition writes a created activity log entry', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Log Field', 'slug' => 'log_field', 'field_type' => 'text',
    ]);

    $activity = Activity::where('subject_type', CustomFieldDefinition::class)
        ->where('subject_id', $def->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('custom-fields');
});

test('updating a field definition writes an updated activity log entry', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Original Label', 'slug' => 'original_label', 'field_type' => 'text',
    ]);

    $def->update(['label' => 'Updated Label']);

    $activity = Activity::where('subject_type', CustomFieldDefinition::class)
        ->where('subject_id', $def->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['label'])->toBe('Updated Label');
});

test('deleting a field definition writes a deleted activity log entry', function () {
    $def   = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Delete Me', 'slug' => 'delete_me', 'field_type' => 'text',
    ]);
    $defId = $def->id;
    $def->delete();

    $activity = Activity::where('subject_type', CustomFieldDefinition::class)
        ->where('subject_id', $defId)
        ->where('event', 'deleted')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('custom-fields');
});

test('activity log does not record the created_by field', function () {
    $def = CustomFieldDefinition::create([
        'object_type' => 'member', 'label' => 'Audit Field', 'slug' => 'audit_field', 'field_type' => 'text',
    ]);

    $activity = Activity::where('subject_type', CustomFieldDefinition::class)
        ->where('subject_id', $def->id)
        ->where('event', 'created')
        ->first();

    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    $attributes = $activity->attribute_changes['attributes'] ?? [];
    expect(array_key_exists('created_by', $attributes))->toBeFalse();
    expect(array_key_exists('label', $attributes))->toBeTrue();
});
