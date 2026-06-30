<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Management\Models\ManagementTask;
use Modules\Management\Models\ManagementTaskCategory;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('a task category can be created', function () {
    $cat = ManagementTaskCategory::create(['name' => 'Spieltag']);

    expect(ManagementTaskCategory::where('id', $cat->id)->where('name', 'Spieltag')->exists())->toBeTrue();
});

test('duplicate category names are allowed', function () {
    ManagementTaskCategory::create(['name' => 'Turnier']);
    ManagementTaskCategory::create(['name' => 'Turnier']);

    expect(ManagementTaskCategory::where('name', 'Turnier')->count())->toBe(2);
});

// ── Tasks relation ────────────────────────────────────────────────────────────

test('a category knows its tasks', function () {
    $cat   = ManagementTaskCategory::create(['name' => 'Spieltag']);
    $task1 = ManagementTask::create(['name' => 'Ordner', 'category_id' => $cat->id]);
    $task2 = ManagementTask::create(['name' => 'Kassierer', 'category_id' => $cat->id]);

    expect($cat->fresh()->tasks)->toHaveCount(2);
    $ids = $cat->fresh()->tasks->pluck('id')->toArray();
    expect($ids)->toContain($task1->id)->toContain($task2->id);
});

test('a category with no tasks has an empty tasks relation', function () {
    $cat = ManagementTaskCategory::create(['name' => 'Leer']);

    expect($cat->tasks)->toBeEmpty();
});

// ── Delete behaviour ──────────────────────────────────────────────────────────

test('deleting a category sets category_id to null on its tasks', function () {
    $cat  = ManagementTaskCategory::create(['name' => 'Turnier']);
    $task = ManagementTask::create(['name' => 'Aufbau', 'category_id' => $cat->id]);

    $catId = $cat->id;
    $cat->delete();

    expect(ManagementTaskCategory::find($catId))->toBeNull();
    expect($task->fresh()->category_id)->toBeNull();
    expect(ManagementTask::find($task->id))->not->toBeNull(); // task is preserved
});

test('deleting a category with multiple tasks nullifies all of them', function () {
    $cat   = ManagementTaskCategory::create(['name' => 'Vereinsabend']);
    $task1 = ManagementTask::create(['name' => 'Aufbau',  'category_id' => $cat->id]);
    $task2 = ManagementTask::create(['name' => 'Abbau',   'category_id' => $cat->id]);
    $task3 = ManagementTask::create(['name' => 'Getränke','category_id' => $cat->id]);

    $cat->delete();

    expect($task1->fresh()->category_id)->toBeNull();
    expect($task2->fresh()->category_id)->toBeNull();
    expect($task3->fresh()->category_id)->toBeNull();
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S11

test('creating a category writes a created activity log entry', function () {
    $cat = ManagementTaskCategory::create(['name' => 'Log Category']);

    $activity = Activity::where('subject_type', ManagementTaskCategory::class)
        ->where('subject_id', $cat->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});

test('updating a category name writes an updated activity log entry', function () {
    $cat = ManagementTaskCategory::create(['name' => 'Before']);
    $cat->update(['name' => 'After']);

    $activity = Activity::where('subject_type', ManagementTaskCategory::class)
        ->where('subject_id', $cat->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['name'])->toBe('After');
});

test('deleting a category writes a deleted activity log entry', function () {
    $cat   = ManagementTaskCategory::create(['name' => 'Delete Me']);
    $catId = $cat->id;
    $cat->delete();

    $activity = Activity::where('subject_type', ManagementTaskCategory::class)
        ->where('subject_id', $catId)
        ->where('event', 'deleted')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});
