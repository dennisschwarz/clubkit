<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;
use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Creates a minimal event starting in 7 days. */
function etmEvent(): Event
{
    return Event::create(['title' => 'Test Event', 'starts_at' => now()->addDays(7)]);
}

/** Creates a minimal management task. */
function etmTask(): ManagementTask
{
    return ManagementTask::create(['name' => 'Test Task']);
}

/** Creates an EventTaskMember without a time slot (responsible person). */
function makeEtm(array $overrides = []): EventTaskMember
{
    $event  = $overrides['event']  ?? etmEvent();
    $task   = $overrides['task']   ?? etmTask();
    $member = $overrides['member'] ?? Member::factory()->create();

    $event->tasks()->attach($task->id);

    return EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
        'time_from' => $overrides['time_from'] ?? null,
        'time_to'   => $overrides['time_to']   ?? null,
    ]);
}

// ── hasTimeSlot ───────────────────────────────────────────────────────────────

test('hasTimeSlot returns false when both times are null', function () {
    $etm = makeEtm();
    expect($etm->hasTimeSlot())->toBeFalse();
});

test('hasTimeSlot returns true when time_from is set', function () {
    $etm = makeEtm([
        'time_from' => '2027-07-15 10:00:00',
        'time_to'   => '2027-07-15 12:00:00',
    ]);
    expect($etm->hasTimeSlot())->toBeTrue();
});

// ── Boot hook: time_from / time_to must both be null or both be set ───────────

test('saving throws LogicException when time_from is set but time_to is null', function () {
    $event  = etmEvent();
    $task   = etmTask();
    $member = Member::factory()->create();
    $event->tasks()->attach($task->id);

    expect(fn () => EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
        'time_from' => '2027-07-15 10:00:00',
        'time_to'   => null,
    ]))->toThrow(\LogicException::class);
});

test('saving throws LogicException when time_to is set but time_from is null', function () {
    $event  = etmEvent();
    $task   = etmTask();
    $member = Member::factory()->create();
    $event->tasks()->attach($task->id);

    expect(fn () => EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
        'time_from' => null,
        'time_to'   => '2027-07-15 12:00:00',
    ]))->toThrow(\LogicException::class);
});

// ── Carbon casts ─────────────────────────────────────────────────────────────

test('time_from and time_to are cast to Carbon instances', function () {
    $etm = makeEtm([
        'time_from' => '2027-07-15 10:00:00',
        'time_to'   => '2027-07-15 12:00:00',
    ]);

    expect($etm->fresh()->time_from)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($etm->fresh()->time_to)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($etm->fresh()->time_from->format('H:i'))->toBe('10:00');
    expect($etm->fresh()->time_to->format('H:i'))->toBe('12:00');
});

// ── Relations ─────────────────────────────────────────────────────────────────

test('event relation returns the correct event', function () {
    $event  = etmEvent();
    $task   = etmTask();
    $member = Member::factory()->create();
    $event->tasks()->attach($task->id);

    $etm = EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
    ]);

    expect($etm->event->id)->toBe($event->id);
});

test('member relation returns the correct member', function () {
    $etm = makeEtm();
    expect($etm->member)->toBeInstanceOf(\Modules\Members\Models\Member::class);
});

// Note: EventTaskMember intentionally has NO task() relation to ManagementTask.
// Management is an optional module (not in Events' requires[]).
// The task_id is accessible as $etm->task_id (int). Any display of task data
// is handled by the Management module via DB::table('management_tasks').

// ── Cascade delete ────────────────────────────────────────────────────────────

test('deleting the event removes the event_task_member row', function () {
    $etm     = makeEtm();
    $etmId   = $etm->id;
    $eventId = $etm->event_id;

    Event::find($eventId)->delete();

    expect(EventTaskMember::find($etmId))->toBeNull();
});

// ── Activity Log ──────────────────────────────────────────────────────────────

test('creating an assignment produces an activity log entry', function () {
    $etm = makeEtm();

    $activity = \Spatie\Activitylog\Models\Activity::where('log_name', 'events')
        ->where('subject_type', EventTaskMember::class)
        ->where('subject_id', $etm->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('created');
});
