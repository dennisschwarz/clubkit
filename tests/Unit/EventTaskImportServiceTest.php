<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;
use Modules\Management\Services\EventTaskImportService;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── parseCsv: basics ──────────────────────────────────────────────────────────

test('parseCsv returns empty array for empty content', function () {
    expect((new EventTaskImportService())->parseCsv(''))->toBeEmpty();
});

test('parseCsv returns empty array for header-only CSV', function () {
    $csv = "name,category,priority,deadline,notes,slot_start,slot_end,interval_minutes,capacity\n";
    expect((new EventTaskImportService())->parseCsv($csv))->toBeEmpty();
});

test('parseCsv parses a valid regular task row', function () {
    $csv = implode("\n", [
        'name,category,priority,deadline,notes',
        '"Pizza bestellen","Catering","normal","","3 Tage vorher"',
    ]);

    $rows = (new EventTaskImportService())->parseCsv($csv);

    expect($rows)->toHaveCount(1);
    expect($rows[0]->name)->toBe('Pizza bestellen');
    expect($rows[0]->category)->toBe('Catering');
    expect($rows[0]->priority)->toBe('normal');
    expect($rows[0]->deadline)->toBeNull();
    expect($rows[0]->notes)->toBe('3 Tage vorher');
    expect($rows[0]->status)->toBe('ok');
    expect($rows[0]->isSlotTask())->toBeFalse();
});

test('parseCsv parses a valid slot task row', function () {
    $csv = implode("\n", [
        'name,category,priority,notes,slot_start,slot_end,interval_minutes,capacity',
        '"Einlass","Freiwillige","normal","","18:00","22:00","60","2"',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->status)->toBe('ok');
    expect($row->isSlotTask())->toBeTrue();
    expect($row->slotStartTime)->toBe('18:00');
    expect($row->slotEndTime)->toBe('22:00');
    expect($row->slotIntervalMinutes)->toBe(60);
    expect($row->slotCapacity)->toBe(2);
});

test('parseCsv parses deadline as Y-m-d string', function () {
    $csv = implode("\n", [
        'name,deadline',
        'Aufbau,2027-08-09',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->deadline)->toBe('2027-08-09');
    expect($row->status)->toBe('ok');
});

test('parseCsv skips blank lines', function () {
    $csv = implode("\n", [
        'name,priority',
        'Task A,normal',
        '',
        'Task B,important',
        '',
    ]);

    expect((new EventTaskImportService())->parseCsv($csv))->toHaveCount(2);
});

test('parseCsv defaults priority to normal when column is absent', function () {
    $csv = implode("\n", [
        'name',
        'Aufbau',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->priority)->toBe('normal');
    expect($row->status)->toBe('ok');
});

// ── parseCsv: delimiter + encoding ───────────────────────────────────────────

test('parseCsv auto-detects semicolon delimiter', function () {
    $csv = implode("\n", [
        'name;category;priority',
        'Aufbau;Technik;important',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->name)->toBe('Aufbau');
    expect($row->category)->toBe('Technik');
    expect($row->priority)->toBe('important');
    expect($row->status)->toBe('ok');
});

test('parseCsv strips UTF-8 BOM', function () {
    $bom = "\xEF\xBB\xBF";
    $csv = $bom . implode("\n", [
        'name,priority',
        'Sicherheit,normal',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->name)->toBe('Sicherheit');
    expect($row->status)->toBe('ok');
});

test('parseCsv accepts German column aliases', function () {
    $csv = implode("\n", [
        'name,kategorie,notizen',
        '"Bühnentechnik","Technik","Früh aufbauen"',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->category)->toBe('Technik');
    expect($row->notes)->toBe('Früh aufbauen');
});

// ── parseCsv: validation ──────────────────────────────────────────────────────

test('parseCsv marks row invalid when name is missing', function () {
    $csv = implode("\n", [
        'name,priority',
        ',normal',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->status)->toBe('invalid');
    expect($row->errors)->toContain('name is required');
});

test('parseCsv marks row invalid when priority is unrecognised', function () {
    $csv = implode("\n", [
        'name,priority',
        'Aufbau,hoch',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->status)->toBe('invalid');
    expect(implode(' ', $row->errors))->toContain('priority');
});

test('parseCsv marks row invalid when slot fields are incomplete', function () {
    $csv = implode("\n", [
        'name,slot_start,slot_end,interval_minutes',
        'Einlass,18:00,,',   // slot_start set; slot_end and interval missing
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->status)->toBe('invalid');
    expect(implode(' ', $row->errors))->toContain('slot_start');
});

test('parseCsv marks row invalid when interval_minutes is not an allowed value', function () {
    $csv = implode("\n", [
        'name,slot_start,slot_end,interval_minutes',
        'Einlass,18:00,22:00,25',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->status)->toBe('invalid');
    expect(implode(' ', $row->errors))->toContain('interval_minutes');
});

test('parseCsv marks row invalid when slot_end is not after slot_start', function () {
    $csv = implode("\n", [
        'name,slot_start,slot_end,interval_minutes',
        'Einlass,22:00,18:00,60',
    ]);

    $row = (new EventTaskImportService())->parseCsv($csv)[0];

    expect($row->status)->toBe('invalid');
    expect(implode(' ', $row->errors))->toContain('slot_end');
});

test('parseCsv accepts all valid interval values', function () {
    foreach ([15, 30, 45, 60, 90, 120] as $interval) {
        $csv = implode("\n", [
            'name,slot_start,slot_end,interval_minutes',
            "Einlass,10:00,18:00,{$interval}",
        ]);

        $row = (new EventTaskImportService())->parseCsv($csv)[0];
        expect($row->status)->toBe('ok');
    }
});

// ── execute ───────────────────────────────────────────────────────────────────

test('execute creates EventTask records for valid rows', function () {
    $event  = Event::factory()->create();
    $user   = \App\Models\User::factory()->create();
    $rows   = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,priority',
        'Aufbau,normal',
        'Abbau,important',
    ]));

    $result = (new EventTaskImportService())->execute($event, $user->id, $rows);

    expect($result['imported'])->toBe(2);
    expect($result['skipped'])->toBe(0);
    expect(EventTask::where('event_id', $event->id)->count())->toBe(2);
});

test('execute skips invalid rows and counts them correctly', function () {
    $event  = Event::factory()->create();
    $user   = \App\Models\User::factory()->create();
    $rows   = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,priority',
        'Aufbau,normal',
        ',normal',           // invalid: name missing
    ]));

    $result = (new EventTaskImportService())->execute($event, $user->id, $rows);

    expect($result['imported'])->toBe(1);
    expect($result['skipped'])->toBe(1);
    expect(EventTask::where('event_id', $event->id)->count())->toBe(1);
});

test('execute creates missing categories automatically', function () {
    $event  = Event::factory()->create();
    $user   = \App\Models\User::factory()->create();
    $rows   = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,category,priority',
        'Einlass,Freiwillige,normal',
        'Bar,Freiwillige,normal',   // same category name → only one created
    ]));

    (new EventTaskImportService())->execute($event, $user->id, $rows);

    expect(EventTaskCategory::where('event_id', $event->id)->where('name', 'Freiwillige')->count())->toBe(1);
    expect(EventTask::where('event_id', $event->id)->count())->toBe(2);
});

test('execute reuses an existing category instead of creating a duplicate', function () {
    $event    = Event::factory()->create();
    $user     = \App\Models\User::factory()->create();
    $existing = EventTaskCategory::create([
        'event_id'   => $event->id,
        'name'       => 'Catering',
        'color'      => 'blue',
        'created_by' => $user->id,
    ]);
    $rows = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,category',
        'Pizza bestellen,Catering',
    ]));

    (new EventTaskImportService())->execute($event, $user->id, $rows);

    expect(EventTaskCategory::where('event_id', $event->id)->count())->toBe(1);
    expect(EventTask::where('category_id', $existing->id)->count())->toBe(1);
});

test('execute persists slot task fields correctly', function () {
    $event = Event::factory()->create();
    $user  = \App\Models\User::factory()->create();
    $rows  = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,slot_start,slot_end,interval_minutes,capacity',
        'Einlass,18:00,22:00,60,2',
    ]));

    (new EventTaskImportService())->execute($event, $user->id, $rows);

    $task = EventTask::where('event_id', $event->id)->firstOrFail();
    expect($task->slot_interval_minutes)->toBe(60);
    expect($task->slot_capacity)->toBe(2);
    expect(substr((string) $task->slot_start_time, 0, 5))->toBe('18:00');
    expect(substr((string) $task->slot_end_time, 0, 5))->toBe('22:00');
});

test('execute returns zero counts when all rows are invalid', function () {
    $event  = Event::factory()->create();
    $user   = \App\Models\User::factory()->create();
    $rows   = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,priority',
        ',normal',
        ',important',
    ]));

    $result = (new EventTaskImportService())->execute($event, $user->id, $rows);

    expect($result['imported'])->toBe(0);
    expect($result['skipped'])->toBe(2);
    expect(EventTask::where('event_id', $event->id)->count())->toBe(0);
});

// ── ParsedTaskRow::toArray ────────────────────────────────────────────────────

test('ParsedTaskRow toArray includes is_slot_task flag', function () {
    $row = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,slot_start,slot_end,interval_minutes',
        'Einlass,18:00,22:00,60',
    ]))[0];

    $arr = $row->toArray();

    expect($arr['is_slot_task'])->toBeTrue();
    expect($arr['status'])->toBe('ok');
    expect($arr['errors'])->toBeEmpty();
    expect($arr['slot_start_time'])->toBe('18:00');
});

test('ParsedTaskRow toArray sets is_slot_task false for regular tasks', function () {
    $row = (new EventTaskImportService())->parseCsv(implode("\n", [
        'name,priority',
        'Aufbau,normal',
    ]))[0];

    expect($row->toArray()['is_slot_task'])->toBeFalse();
});
