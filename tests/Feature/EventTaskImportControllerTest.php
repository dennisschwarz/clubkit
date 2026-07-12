<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;

beforeEach(function () {
    $this->event = Event::factory()->create();
    $this->user  = createUserWithPermission('events.manage');

    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'events',     'is_active' => 1],
        ['slug' => 'management', 'is_active' => 1],
    ]);

    seedPermissions();
});

// ── preview ───────────────────────────────────────────────────────────────────

test('preview returns parsed rows as JSON', function () {
    $csv  = implode("\n", ['name,priority', 'Aufbau,normal', 'Abbau,important']);
    $file = UploadedFile::fake()->createWithContent('tasks.csv', $csv);

    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import.preview', $this->event), ['csv' => $file])
        ->assertOk()
        ->assertJsonStructure(['rows', 'valid_count', 'invalid_count'])
        ->assertJsonPath('valid_count', 2)
        ->assertJsonPath('invalid_count', 0);
});

test('preview reports invalid rows in invalid_count', function () {
    $csv  = implode("\n", ['name,priority', ',normal']);  // name missing → invalid
    $file = UploadedFile::fake()->createWithContent('tasks.csv', $csv);

    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import.preview', $this->event), ['csv' => $file])
        ->assertOk()
        ->assertJsonPath('valid_count', 0)
        ->assertJsonPath('invalid_count', 1);
});

test('preview includes is_slot_task flag for slot rows', function () {
    $csv  = implode("\n", [
        'name,slot_start,slot_end,interval_minutes',
        'Einlass,18:00,22:00,60',
    ]);
    $file = UploadedFile::fake()->createWithContent('tasks.csv', $csv);

    $response = $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import.preview', $this->event), ['csv' => $file])
        ->assertOk();

    expect($response->json('rows.0.is_slot_task'))->toBeTrue();
});

test('preview returns 422 when no file is uploaded', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import.preview', $this->event), [])
        ->assertUnprocessable();
});

test('preview returns 403 without events.manage permission', function () {
    $this->actingAs(createPlainUser())
        ->postJson(route('events.management.tasks.import.preview', $this->event), [])
        ->assertForbidden();
});

// ── execute ───────────────────────────────────────────────────────────────────

test('execute creates tasks from a valid JSON payload', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [
                ['name' => 'Aufbau', 'priority' => 'normal'],
                ['name' => 'Abbau',  'priority' => 'important'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('skipped', 0);

    expect(EventTask::where('event_id', $this->event->id)->count())->toBe(2);
});

test('execute creates a new category when category name is unknown', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [
                ['name' => 'Einlass', 'category' => 'Freiwillige', 'priority' => 'normal'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    expect(EventTaskCategory::where('event_id', $this->event->id)->count())->toBe(1);
    expect(EventTask::where('event_id', $this->event->id)->count())->toBe(1);
});

test('execute creates a slot task when slot fields are provided', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [[
                'name'                  => 'Einlass',
                'priority'              => 'normal',
                'slot_start_time'       => '18:00',
                'slot_end_time'         => '22:00',
                'slot_interval_minutes' => 60,
                'slot_capacity'         => 2,
            ]],
        ])
        ->assertOk();

    $task = EventTask::where('event_id', $this->event->id)->firstOrFail();
    expect($task->slot_interval_minutes)->toBe(60);
    expect($task->slot_capacity)->toBe(2);
    expect(substr((string) $task->slot_start_time, 0, 5))->toBe('18:00');
});

test('execute returns 422 when tasks array is missing', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [])
        ->assertUnprocessable();
});

test('execute returns 422 when a task has no name', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [['priority' => 'normal']],
        ])
        ->assertUnprocessable();
});

test('execute returns 422 when priority is invalid', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [['name' => 'Test', 'priority' => 'hoch']],
        ])
        ->assertUnprocessable();
});

test('execute returns 422 when slot_interval_minutes is not in allowed values', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [[
                'name'                  => 'Test',
                'slot_start_time'       => '10:00',
                'slot_end_time'         => '18:00',
                'slot_interval_minutes' => 25,  // not in [15,30,45,60,90,120]
            ]],
        ])
        ->assertUnprocessable();
});

test('execute returns 403 without events.manage permission', function () {
    $this->actingAs(createPlainUser())
        ->postJson(route('events.management.tasks.import', $this->event), [
            'tasks' => [['name' => 'Test']],
        ])
        ->assertForbidden();
});

// ── template ──────────────────────────────────────────────────────────────────

test('template returns a CSV download with correct content-type', function () {
    $this->actingAs($this->user)
        ->get(route('events.management.tasks.import.template', $this->event))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('template response contains header row', function () {
    $response = $this->actingAs($this->user)
        ->get(route('events.management.tasks.import.template', $this->event));

    expect($response->getContent())->toContain('name,category,priority');
});

test('template returns 403 without events.manage permission', function () {
    $this->actingAs(createPlainUser())
        ->get(route('events.management.tasks.import.template', $this->event))
        ->assertForbidden();
});
