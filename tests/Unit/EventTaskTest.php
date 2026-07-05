<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;
use Modules\Management\Models\EventTaskMember;
use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('an event task can be created with a direct name', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Einlasskontrolle']);

    expect(EventTask::where('id', $task->id)->where('name', 'Einlasskontrolle')->exists())->toBeTrue();
});

test('default priority is normal', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Test Task']);

    expect($task->fresh()->priority)->toBe('normal');
});

test('default completed is false', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Test Task']);

    expect($task->fresh()->completed)->toBeFalse();
});

test('default sort_order is 0', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Test Task']);

    expect($task->fresh()->sort_order)->toBe(0);
});

test('all three PRIORITIES values can be stored', function () {
    $event = Event::factory()->create();

    foreach (EventTask::PRIORITIES as $priority) {
        $task = EventTask::create(['event_id' => $event->id, 'name' => 'Prio Task', 'priority' => $priority]);
        expect($task->fresh()->priority)->toBe($priority);
    }
});

test('PRIORITIES constant contains exactly normal, important and critical', function () {
    expect(EventTask::PRIORITIES)->toContain('normal');
    expect(EventTask::PRIORITIES)->toContain('important');
    expect(EventTask::PRIORITIES)->toContain('critical');
    expect(EventTask::PRIORITIES)->toHaveCount(3);
});

// ── Casts ─────────────────────────────────────────────────────────────────────

test('deadline_at is cast to a Carbon instance when set', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create([
        'event_id'    => $event->id,
        'name'        => 'Prep Task',
        'deadline_at' => '2027-07-10 12:00:00',
    ]);

    expect($task->fresh()->deadline_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($task->fresh()->deadline_at->toDateString())->toBe('2027-07-10');
});

test('deadline_at can be null (event-day task)', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Day Task']);

    expect($task->fresh()->deadline_at)->toBeNull();
});

test('completed is cast to boolean', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Done Task', 'completed' => true]);

    $fresh = $task->fresh();
    expect($fresh->completed)->toBeTrue();
    expect($fresh->completed)->toBeBool(); // bool is a PHP primitive, not a class — use toBeBool()
});

// ── Relations ─────────────────────────────────────────────────────────────────

test('category relation returns the event-local category', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Ordnung']);
    $task  = EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Einlass']);

    expect($task->fresh()->category->id)->toBe($cat->id);
    expect($task->fresh()->category->name)->toBe('Ordnung');
});

test('category can be null', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Uncategorised', 'category_id' => null]);

    expect($task->category)->toBeNull();
});

test('template relation returns the global management task', function () {
    $template = ManagementTask::create(['name' => 'Getränkeverkauf']);
    $event    = Event::factory()->create();
    $task     = EventTask::create([
        'event_id'    => $event->id,
        'template_id' => $template->id,
        'name'        => $template->name,
    ]);

    expect($task->fresh()->template->id)->toBe($template->id);
    expect($task->fresh()->template->name)->toBe('Getränkeverkauf');
});

test('template relation returns null when template_id is null', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Direct Task', 'template_id' => null]);

    expect($task->template)->toBeNull();
});

test('members relation returns all EventTaskMember assignments', function () {
    $event  = Event::factory()->create();
    $member = Member::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Aufbau']);

    EventTaskMember::create(['event_task_id' => $task->id, 'member_id' => $member->id]);

    expect($task->fresh()->members)->toHaveCount(1);
    expect($task->fresh()->members->first()->member_id)->toBe($member->id);
});

test('creator relation returns the user who created the task', function () {
    $user  = User::factory()->create();
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Kasse', 'created_by' => $user->id]);

    expect($task->fresh()->creator->id)->toBe($user->id);
});

// ── Cascade behaviour ─────────────────────────────────────────────────────────

test('deleting a category sets category_id to null on event tasks (DB SET NULL)', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Aufbau']);
    $task  = EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Stühle']);

    $cat->delete();

    $fresh = EventTask::find($task->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->category_id)->toBeNull();
});

test('deleting an event task cascades to member assignments (DB CASCADE)', function () {
    $event  = Event::factory()->create();
    $member = Member::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Abbau']);
    $etm    = EventTaskMember::create(['event_task_id' => $task->id, 'member_id' => $member->id]);

    $task->delete();

    expect(EventTaskMember::find($etm->id))->toBeNull();
    // The member itself is NOT deleted
    expect(Member::find($member->id))->not->toBeNull();
});

test('deleting a global task template sets template_id to null — task data is preserved', function () {
    $template = ManagementTask::create(['name' => 'Kassendienst']);
    $event    = Event::factory()->create();
    $task     = EventTask::create([
        'event_id'    => $event->id,
        'template_id' => $template->id,
        'name'        => 'Kassendienst',
        'priority'    => 'important',
    ]);

    $taskId = $task->id;
    $template->delete();

    $fresh = EventTask::find($taskId);
    expect($fresh)->not->toBeNull();
    expect($fresh->template_id)->toBeNull();
    // Local copy of name and priority is preserved after template deletion
    expect($fresh->name)->toBe('Kassendienst');
    expect($fresh->priority)->toBe('important');
});

// ── Activity Log (Spatie v6) ──────────────────────────────────────────────────
//
// ClubKit has a published activity_log migration with the attribute_changes column.
// In v6, attribute diffs live in attribute_changes, NOT in properties.
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']

test('creating an event task writes a created activity log entry', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Log Task']);

    $activity = Activity::where('subject_type', EventTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});

test('updating name writes an updated activity log entry', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Original']);
    $task->update(['name' => 'Updated']);

    $activity = Activity::where('subject_type', EventTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['name'])->toBe('Updated');
    expect($activity->attribute_changes['old']['name'])->toBe('Original');
});

test('toggling completed writes an updated activity log entry', function () {
    $event = Event::factory()->create();
    // Explicitly set completed => false so Eloquent's $original tracks the value.
    // Without this, getOriginal('completed') returns null and Spatie logs old.completed as null.
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Done Test', 'completed' => false]);
    $task->update(['completed' => true]);

    $activity = Activity::where('subject_type', EventTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes['attributes']['completed'])->toBeTrue();
    expect($activity->attribute_changes['old']['completed'])->toBeFalse();
});

test('unchanged fields do not produce a log entry (dontLogEmptyChanges)', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Unchanged']);
    $count = Activity::where('subject_id', $task->id)->count();

    $task->update(['name' => 'Unchanged']);

    expect(Activity::where('subject_id', $task->id)->count())->toBe($count);
});
