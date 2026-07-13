<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventFunction;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('an event function can be created with a name', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Fotograf']);

    expect($fn->id)->not->toBeNull();
    expect($fn->name)->toBe('Fotograf');
    expect($fn->event_id)->toBe($event->id);
});

test('member_id defaults to null', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Moderator']);

    expect($fn->member_id)->toBeNull();
});

test('created_by may be null', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Technik']);

    expect($fn->created_by)->toBeNull();
});

test('member_id can be set and updated', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create([
        'event_id'  => $event->id,
        'name'      => 'Einlass',
        'member_id' => 42,
    ]);

    expect($fn->member_id)->toBe(42);

    $fn->update(['member_id' => null]);
    expect($fn->fresh()->member_id)->toBeNull();
});

// ── Cascade delete ────────────────────────────────────────────────────────────

test('deleting an event cascade-deletes its ad-hoc functions', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Catering']);
    $fnId  = $fn->id;

    $event->delete();

    expect(EventFunction::find($fnId))->toBeNull();
});

test('multiple ad-hoc functions can be created for the same event', function () {
    $event = Event::factory()->create();

    EventFunction::create(['event_id' => $event->id, 'name' => 'Fotograf']);
    EventFunction::create(['event_id' => $event->id, 'name' => 'Catering']);

    expect(EventFunction::where('event_id', $event->id)->count())->toBe(2);
});

// ── Activity Logging ──────────────────────────────────────────────────────────
//
// v6 with attribute_changes column: diffs live in attribute_changes, not properties.
// See ManagementFunctionTest.php for the full explanation.

test('creating an event function writes a created activity log entry', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Einlass']);

    $activity = Activity::where('subject_type', EventFunction::class)
        ->where('subject_id', $fn->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});

test('updating member_id writes an updated activity log entry', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Koordination']);
    $fn->update(['member_id' => 42]);

    $activity = Activity::where('subject_type', EventFunction::class)
        ->where('subject_id', $fn->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes['attributes']['member_id'])->toBe(42);
});

test('updating only name writes an updated log entry for name', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Original']);
    $fn->update(['name' => 'Geändert']);

    $activity = Activity::where('subject_type', EventFunction::class)
        ->where('subject_id', $fn->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes['attributes']['name'])->toBe('Geändert');
});
