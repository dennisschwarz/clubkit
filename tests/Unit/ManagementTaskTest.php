<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Management\Models\ManagementTask;
use Modules\Management\Models\ManagementTaskCategory;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('a management task can be created', function () {
    $task = ManagementTask::create(['name' => 'Getränkeverkauf']);

    expect(ManagementTask::where('id', $task->id)->where('name', 'Getränkeverkauf')->exists())->toBeTrue();
});

test('a task can have a description', function () {
    $task = ManagementTask::create([
        'name'        => 'Aufbau Tribüne',
        'description' => 'Bitte 2 Stunden vor Spielbeginn erscheinen.',
    ]);

    expect($task->description)->toContain('2 Stunden');
});

test('duplicate task names are allowed', function () {
    ManagementTask::create(['name' => 'Ordnerdienst']);
    ManagementTask::create(['name' => 'Ordnerdienst']);

    expect(ManagementTask::where('name', 'Ordnerdienst')->count())->toBe(2);
});

test('created_by is stored correctly', function () {
    $user = User::factory()->create();
    $task = ManagementTask::create(['name' => 'Fotograf', 'created_by' => $user->id]);

    expect($task->created_by)->toBe($user->id);
    expect($task->creator->is($user))->toBeTrue();
});

// ── Priority (requires 000052 migration) ─────────────────────────────────────

test('default priority is normal', function () {
    $task = ManagementTask::create(['name' => 'Standard']);

    // DB default applies when priority is not passed to create()
    expect($task->fresh()->priority)->toBe('normal');
})->skip(fn () => ! \Illuminate\Support\Facades\Schema::hasColumn('management_tasks', 'priority'),
    'Migration 000052 (add priority to management_tasks) has not been run yet.');

test('all three priority levels can be stored', function () {
    foreach (ManagementTask::PRIORITIES as $priority) {
        $task = ManagementTask::create(['name' => 'Prio Test', 'priority' => $priority]);
        expect($task->fresh()->priority)->toBe($priority);
    }
})->skip(fn () => ! \Illuminate\Support\Facades\Schema::hasColumn('management_tasks', 'priority'),
    'Migration 000052 (add priority to management_tasks) has not been run yet.');

test('scopeWithPriority filters correctly', function () {
    ManagementTask::create(['name' => 'Normal',    'priority' => 'normal']);
    ManagementTask::create(['name' => 'Important', 'priority' => 'important']);
    ManagementTask::create(['name' => 'Critical',  'priority' => 'critical']);

    expect(ManagementTask::withPriority('critical')->count())->toBe(1);
    expect(ManagementTask::withPriority('normal')->count())->toBe(1);
})->skip(fn () => ! \Illuminate\Support\Facades\Schema::hasColumn('management_tasks', 'priority'),
    'Migration 000052 (add priority to management_tasks) has not been run yet.');

test('updating priority writes an updated activity log entry', function () {
    $task = ManagementTask::create(['name' => 'Prioritätstest']);
    $task->update(['priority' => 'critical']);

    $activity = Activity::where('subject_type', ManagementTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['priority'])->toBe('critical');
})->skip(fn () => ! \Illuminate\Support\Facades\Schema::hasColumn('management_tasks', 'priority'),
    'Migration 000052 (add priority to management_tasks) has not been run yet.');

// ── Category ──────────────────────────────────────────────────────────────────

test('a task can be assigned to a category', function () {
    $cat  = ManagementTaskCategory::create(['name' => 'Spieltag']);
    $task = ManagementTask::create(['name' => 'Ordnerdienst', 'category_id' => $cat->id]);

    expect($task->fresh()->category->name)->toBe('Spieltag');
});

test('a task may have no category', function () {
    $task = ManagementTask::create(['name' => 'Allgemeine Aufgabe', 'category_id' => null]);

    expect($task->category_id)->toBeNull();
    expect($task->category)->toBeNull();
});

test('deleting a category sets category_id to null on its tasks', function () {
    $cat  = ManagementTaskCategory::create(['name' => 'Turnier']);
    $task = ManagementTask::create(['name' => 'Aufbau', 'category_id' => $cat->id]);

    $cat->delete();

    expect($task->fresh()->category_id)->toBeNull();
    expect(ManagementTask::find($task->id))->not->toBeNull();
});

// ── Template import into event tasks ─────────────────────────────────────────
//
// The old events() BelongsToMany pivot was removed in the task/category refactor.
// Global tasks now act as templates: EventTask.template_id references ManagementTask.id.
// This is a soft reference — deleting the template does not delete event tasks.

test('a global task can be used as a template for event tasks', function () {
    $template = ManagementTask::create(['name' => 'Getränkeverkauf']);
    $event1   = Event::create(['title' => 'Turnier 1', 'starts_at' => now()->addDays(7)]);
    $event2   = Event::create(['title' => 'Turnier 2', 'starts_at' => now()->addDays(14)]);

    // Import: create event tasks that reference this global template.
    \Modules\Management\Models\EventTask::create([
        'event_id'    => $event1->id,
        'template_id' => $template->id,
        'name'        => $template->name,
    ]);
    \Modules\Management\Models\EventTask::create([
        'event_id'    => $event2->id,
        'template_id' => $template->id,
        'name'        => $template->name,
    ]);

    expect(\Modules\Management\Models\EventTask::where('template_id', $template->id)->count())->toBe(2);
});

test('deleting a global task template sets template_id to null — event tasks are preserved', function () {
    $template  = ManagementTask::create(['name' => 'Kassendienst']);
    $event     = Event::create(['title' => 'Turnier', 'starts_at' => now()->addDays(7)]);
    $eventTask = \Modules\Management\Models\EventTask::create([
        'event_id'    => $event->id,
        'template_id' => $template->id,
        'name'        => $template->name,
        'priority'    => 'important',
    ]);

    $eventTaskId = $eventTask->id;
    $template->delete();

    $fresh = \Modules\Management\Models\EventTask::find($eventTaskId);
    expect($fresh)->not->toBeNull();
    expect($fresh->template_id)->toBeNull();
    // Local name and priority are preserved after template deletion
    expect($fresh->name)->toBe('Kassendienst');
    expect($fresh->priority)->toBe('important');
});

// ── Scopes ────────────────────────────────────────────────────────────────────

test('a task without a team assignment is considered general', function () {
    $task = ManagementTask::create(['name' => 'Kasse zählen']);

    expect(ManagementTask::general()->where('id', $task->id)->exists())->toBeTrue();
});

test('scopeForTeam filters correctly', function () {
    $d1      = Team::factory()->create(['name' => 'D1']);
    $scoped  = ManagementTask::create(['name' => 'Getränke D1']);
    $general = ManagementTask::create(['name' => 'Allgemeine Kasse']);

    $scoped->teams()->sync([$d1->id]);

    $result = ManagementTask::forTeam($d1->id)->get();

    expect($result->contains($scoped))->toBeTrue();
    expect($result->contains($general))->toBeFalse();
});

// ── Member assignments ────────────────────────────────────────────────────────

test('multiple members can be assigned to a task', function () {
    $task    = ManagementTask::create(['name' => 'Aufbau']);
    $mueller = Member::factory()->create(['last_name' => 'Müller']);
    $schmidt = Member::factory()->create(['last_name' => 'Schmidt']);

    $task->members()->sync([$mueller->id, $schmidt->id]);

    expect($task->fresh()->members)->toHaveCount(2);
});

test('a member cannot be assigned to the same task twice', function () {
    $task   = ManagementTask::create(['name' => 'Abbau']);
    $member = Member::factory()->create();

    $task->members()->attach($member->id);

    expect(fn () => $task->members()->attach($member->id))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── Cascade delete ────────────────────────────────────────────────────────────

test('deleting a task cascades to all its pivot assignments', function () {
    $task   = ManagementTask::create(['name' => 'Turnierorganisation']);
    $team   = Team::factory()->create();
    $member = Member::factory()->create();

    $task->teams()->attach($team->id);
    $task->members()->attach($member->id);

    $taskId = $task->id;
    $task->delete();

    expect(\Illuminate\Support\Facades\DB::table('management_task_team')->where('task_id', $taskId)->exists())->toBeFalse();
    expect(\Illuminate\Support\Facades\DB::table('management_task_member')->where('task_id', $taskId)->exists())->toBeFalse();
    expect(ManagementTask::find($taskId))->toBeNull();
});

test('deleting a task does not delete its members', function () {
    $task   = ManagementTask::create(['name' => 'Kassendienst']);
    $member = Member::factory()->create(['last_name' => 'Testperson']);

    $task->members()->attach($member->id);
    $task->delete();

    expect(Member::find($member->id))->not->toBeNull();
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S11

test('creating a management task writes a created activity log entry', function () {
    $task = ManagementTask::create(['name' => 'Log Task']);

    $activity = Activity::where('subject_type', ManagementTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});

test('updating a task name writes an updated activity log entry', function () {
    $task = ManagementTask::create(['name' => 'Original']);
    $task->update(['name' => 'Updated']);

    $activity = Activity::where('subject_type', ManagementTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['name'])->toBe('Updated');
});

test('assigning a category to a task writes an updated activity log entry', function () {
    $cat  = ManagementTaskCategory::create(['name' => 'Spieltag']);
    $task = ManagementTask::create(['name' => 'Ordnerdienst']);
    $task->update(['category_id' => $cat->id]);

    $activity = Activity::where('subject_type', ManagementTask::class)
        ->where('subject_id', $task->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['category_id'])->toBe($cat->id);
});
