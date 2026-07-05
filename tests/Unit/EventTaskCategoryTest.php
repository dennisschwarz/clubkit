<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('a category can be created for an event', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Ordnung']);

    expect(EventTaskCategory::where('id', $cat->id)->where('name', 'Ordnung')->exists())->toBeTrue();
});

test('color can be null', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'No Color']);

    expect($cat->fresh()->color)->toBeNull();
});

test('color can be set to a valid slug', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Blue Section', 'color' => 'blue']);

    expect($cat->fresh()->color)->toBe('blue');
});

test('COLORS constant contains all eleven expected colour slugs', function () {
    expect(EventTaskCategory::COLORS)->toContain('blue');
    expect(EventTaskCategory::COLORS)->toContain('green');
    expect(EventTaskCategory::COLORS)->toContain('amber');
    expect(EventTaskCategory::COLORS)->toContain('red');
    expect(EventTaskCategory::COLORS)->toContain('orange');
    expect(EventTaskCategory::COLORS)->toContain('purple');
    expect(EventTaskCategory::COLORS)->toContain('pink');
    expect(EventTaskCategory::COLORS)->toContain('teal');
    expect(EventTaskCategory::COLORS)->toContain('navy');
    expect(EventTaskCategory::COLORS)->toContain('slate');
    expect(EventTaskCategory::COLORS)->toContain('gray');
    expect(EventTaskCategory::COLORS)->toHaveCount(11);
});

test('sort_order defaults to 0', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Test']);

    expect($cat->fresh()->sort_order)->toBe(0);
});

// ── Relations ─────────────────────────────────────────────────────────────────

test('tasks relation returns all event tasks assigned to this category', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Aufbau']);

    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Stühle']);
    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Tische']);

    expect($cat->fresh()->tasks)->toHaveCount(2);
});

test('event relation returns the parent event', function () {
    $event = Event::factory()->create(['title' => 'Turnier']);
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Ordnung']);

    expect($cat->event->id)->toBe($event->id);
    expect($cat->event->title)->toBe('Turnier');
});

test('creator relation returns the user who created the category', function () {
    $user  = User::factory()->create();
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create([
        'event_id'   => $event->id,
        'name'       => 'VIP',
        'created_by' => $user->id,
    ]);

    expect($cat->creator->id)->toBe($user->id);
});

test('creator is null when created_by is not set', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'No Creator']);

    expect($cat->created_by)->toBeNull();
    expect($cat->creator)->toBeNull();
});

// ── Cascade behaviour ─────────────────────────────────────────────────────────

test('deleting a category sets category_id to null on its tasks (DB SET NULL)', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Einlass']);
    $task  = EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Kontrolle']);

    $cat->delete();

    expect(EventTask::find($task->id))->not->toBeNull();
    expect(EventTask::find($task->id)->category_id)->toBeNull();
});

test('deleting a category does not delete its tasks', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Kasse']);
    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Abrechnung']);
    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Wechselgeld']);

    $cat->delete();

    expect(EventTask::where('event_id', $event->id)->count())->toBe(2);
});

// ── Activity Log (Spatie v6) ──────────────────────────────────────────────────
//
// ClubKit has a published activity_log migration with the attribute_changes column.
// In v6, attribute diffs live in attribute_changes, NOT in properties.
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']

test('creating a category writes a created activity log entry', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Log Test']);

    $activity = Activity::where('subject_type', EventTaskCategory::class)
        ->where('subject_id', $cat->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});

test('updating name writes an updated activity log entry with attribute_changes', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Old Name']);
    $cat->update(['name' => 'New Name']);

    $activity = Activity::where('subject_type', EventTaskCategory::class)
        ->where('subject_id', $cat->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['name'])->toBe('New Name');
    expect($activity->attribute_changes['old']['name'])->toBe('Old Name');
});

test('updating color is logged in activity log', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Colour Test', 'color' => 'gray']);
    $cat->update(['color' => 'blue']);

    $activity = Activity::where('subject_type', EventTaskCategory::class)
        ->where('subject_id', $cat->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes['attributes']['color'])->toBe('blue');
    expect($activity->attribute_changes['old']['color'])->toBe('gray');
});

test('unchanged fields do not produce a log entry (dontLogEmptyChanges)', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Unchanged']);
    $count = Activity::where('subject_id', $cat->id)->count();

    $cat->update(['name' => 'Unchanged']);

    expect(Activity::where('subject_id', $cat->id)->count())->toBe($count);
});
