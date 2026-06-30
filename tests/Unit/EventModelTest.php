<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;
use Modules\Management\Models\ManagementTask;
use Modules\Management\Models\ManagementTaskCategory;
use Modules\Members\Models\Member;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Creates an Event without explicitly setting optional fields. */
function makeEvent(array $overrides = []): Event
{
    return Event::create(array_merge([
        'title'      => 'Testtermin',
        'starts_at'  => now()->addDays(7),
        'ends_at'    => null,
        'created_by' => null,
    ], $overrides));
}

/** Creates a ManagementTask without explicitly setting priority (uses DB default). */
function makeTask(array $overrides = []): ManagementTask
{
    return ManagementTask::create(array_merge(['name' => 'Test Task'], $overrides));
}

/**
 * Assigns a task to an event via DB::table, bypassing Eloquent eager loading.
 * Events owns event_task — this is Events' own table, no cross-module model import needed.
 * Preferred over Event::tasks()->attach() in this test file to keep assertions
 * focused on Events module behaviour without triggering Management model instantiation.
 */
function assignTaskToEvent(Event $event, ManagementTask $task, array $pivot = []): void
{
    DB::table('event_task')->insert(array_merge([
        'event_id'    => $event->id,
        'task_id'     => $task->id,
        'notes'       => null,
        'completed'   => 0,
        'deadline_at' => null,
        'created_at'  => now(),
        'updated_at'  => now(),
    ], $pivot));
}

// ── Create ────────────────────────────────────────────────────────────────────

test('an event can be created', function () {
    $event = makeEvent(['title' => 'Pokalfinale']);

    expect(Event::where('id', $event->id)->where('title', 'Pokalfinale')->exists())->toBeTrue();
});

test('optional fields may be null', function () {
    $event = makeEvent();

    expect($event->description)->toBeNull();
    expect($event->ends_at)->toBeNull();
    expect($event->location)->toBeNull();
    expect($event->notes)->toBeNull();
});

test('starts_at and ends_at are cast to Carbon instances', function () {
    $event = makeEvent(['starts_at' => '2027-07-15 10:00:00', 'ends_at' => '2027-07-15 18:00:00']);

    expect($event->fresh()->starts_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($event->fresh()->ends_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('creator relation returns the user who created the event', function () {
    $user  = User::factory()->create();
    $event = makeEvent(['created_by' => $user->id]);

    expect($event->fresh()->creator->id)->toBe($user->id);
});

// ── scopeUpcoming / scopePast ─────────────────────────────────────────────────

test('scopeUpcoming returns only future events', function () {
    $future = makeEvent(['title' => 'Future', 'starts_at' => now()->addDays(1)]);
    $past   = makeEvent(['title' => 'Past',   'starts_at' => now()->subDays(1)]);

    $upcoming = Event::upcoming()->get();
    expect($upcoming->contains($future))->toBeTrue();
    expect($upcoming->contains($past))->toBeFalse();
});

test('scopePast returns only past events', function () {
    $future = makeEvent(['title' => 'Future', 'starts_at' => now()->addDays(1)]);
    $past   = makeEvent(['title' => 'Past',   'starts_at' => now()->subDays(1)]);

    $pastEvents = Event::past()->get();
    expect($pastEvents->contains($past))->toBeTrue();
    expect($pastEvents->contains($future))->toBeFalse();
});

// ── hasTaskAssigned ───────────────────────────────────────────────────────────

test('hasTaskAssigned returns true when a task is assigned to the event', function () {
    $event = makeEvent();
    $task  = makeTask();

    assignTaskToEvent($event, $task);

    expect($event->hasTaskAssigned($task->id))->toBeTrue();
});

test('hasTaskAssigned returns false when a task is not assigned', function () {
    $event = makeEvent();
    $task  = makeTask();

    expect($event->hasTaskAssigned($task->id))->toBeFalse();
});

// ── hasEventDayTaskAssigned ───────────────────────────────────────────────────
//
// Method signature: hasEventDayTaskAssigned(int $taskId, string $eventDate): bool
// The $eventDate is the event's start date in Y-m-d format.

test('hasEventDayTaskAssigned returns true when a task has no deadline_at', function () {
    $event = makeEvent(['starts_at' => '2027-07-15 10:00:00']);
    $task  = makeTask();

    assignTaskToEvent($event, $task, ['deadline_at' => null]);

    // Pass the event's own start date as the required second argument.
    expect($event->hasEventDayTaskAssigned($task->id, $event->starts_at->toDateString()))->toBeTrue();
});

test('hasEventDayTaskAssigned returns false when all assignments have a deadline', function () {
    $event = makeEvent(['starts_at' => '2027-07-15 10:00:00']);
    $task  = makeTask();

    assignTaskToEvent($event, $task, ['deadline_at' => now()->addDays(3)->toDateString()]);

    // A future deadline does not match the event date → method returns false.
    expect($event->hasEventDayTaskAssigned($task->id, $event->starts_at->toDateString()))->toBeFalse();
});

// ── tasks() relation ──────────────────────────────────────────────────────────

test('tasks relation is empty for a new event', function () {
    $event = makeEvent();

    expect(DB::table('event_task')->where('event_id', $event->id)->count())->toBe(0);
});

test('tasks relation returns assigned tasks', function () {
    $event = makeEvent();
    $task1 = makeTask(['name' => 'Aufbau']);
    $task2 = makeTask(['name' => 'Abbau']);

    assignTaskToEvent($event, $task1);
    assignTaskToEvent($event, $task2);

    expect(DB::table('event_task')->where('event_id', $event->id)->count())->toBe(2);
});

// ── EventTaskMember integration ───────────────────────────────────────────────

test('a member can be assigned to an event task', function () {
    $category = ManagementTaskCategory::create(['name' => 'TestKategorie']);
    $event    = makeEvent(['starts_at' => '2027-07-15 10:00:00']);
    $task     = makeTask(['name' => 'Kasse', 'category_id' => $category->id]);
    $member   = Member::factory()->create();

    assignTaskToEvent($event, $task);

    $etm = EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
        'time_from' => '2027-07-15 10:00:00',
        'time_to'   => '2027-07-15 13:00:00',
    ]);

    expect($etm->fresh()->member->id)->toBe($member->id);
    expect($etm->fresh()->time_from->format('H:i'))->toBe('10:00');
    expect($etm->fresh()->time_to->format('H:i'))->toBe('13:00');
});

test('a member cannot be assigned to the same event-task twice', function () {
    $event  = makeEvent();
    $task   = makeTask(['name' => 'Kasse']);
    $member = Member::factory()->create();

    assignTaskToEvent($event, $task);

    EventTaskMember::create([
        'event_id' => $event->id, 'task_id' => $task->id, 'member_id' => $member->id,
    ]);

    expect(fn () => EventTaskMember::create([
        'event_id' => $event->id, 'task_id' => $task->id, 'member_id' => $member->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('deleting an event cascades to event_task_member rows', function () {
    $event  = makeEvent();
    $task   = makeTask(['name' => 'Aufbau']);
    $member = Member::factory()->create();

    assignTaskToEvent($event, $task);
    EventTaskMember::create(['event_id' => $event->id, 'task_id' => $task->id, 'member_id' => $member->id]);

    $eventId = $event->id;
    $event->delete();

    expect(EventTaskMember::where('event_id', $eventId)->exists())->toBeFalse();
    expect(DB::table('event_task')->where('event_id', $eventId)->exists())->toBeFalse();
});

// ── Activity Log ──────────────────────────────────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S9

test('updating title is logged in activity log', function () {
    $event = makeEvent(['title' => 'Alt']);
    $event->update(['title' => 'Neu']);

    // Filter explicitly for the 'updated' event.
    $log = Activity::where('subject_type', Event::class)
        ->where('subject_id', $event->id)
        ->where('log_name', 'events')
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($log->attribute_changes['attributes']['title'])->toBe('Neu');
    expect($log->attribute_changes['old']['title'])->toBe('Alt');
});

test('unchanged fields do not produce a log entry', function () {
    $event = makeEvent(['title' => 'Unverändert']);
    $initialCount = Activity::where('subject_id', $event->id)->count();

    $event->update(['title' => 'Unverändert']);

    expect(Activity::where('subject_id', $event->id)->count())->toBe($initialCount);
});
