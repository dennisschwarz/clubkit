<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\View\Composers\EventSlotsPanelComposer;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Post-rename: Einsatz → Shift ──────────────────────────────────────────────

test('EventSlotsPanelComposer injects mgmtShift* keys, not mgmtEinsatz*', function () {
    $event = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);

    $data = $view->getData();

    expect($data)->toHaveKey('mgmtShiftTasks');
    expect($data)->toHaveKey('mgmtShiftConfigured');
    expect($data)->toHaveKey('mgmtShiftUnconfigured');
    expect($data)->toHaveKey('mgmtShiftTimeColumns');
    expect($data)->toHaveKey('mgmtShiftGrid');
    expect($data)->toHaveKey('mgmtShiftSkipCols');
    expect($data)->not->toHaveKey('mgmtEinsatzTasks');
    expect($data)->not->toHaveKey('mgmtEinsatzConfigured');
    expect($data)->not->toHaveKey('mgmtEinsatzTimeColumns');
    expect($data)->not->toHaveKey('mgmtEinsatzGrid');
});

// ── Task classification ────────────────────────────────────────────────────────

test('EventSlotsPanelComposer splits tasks into configured and unconfigured', function () {
    $event     = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);
    $slotTask  = EventTask::create(['event_id' => $event->id, 'name' => 'Box office',
        'slot_start_time' => '09:00:00', 'slot_end_time' => '11:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 2]);
    $plainTask = EventTask::create(['event_id' => $event->id, 'name' => 'Setup crew']);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);
    $data = $view->getData();

    expect($data['mgmtShiftConfigured']->pluck('id')->toArray())->toContain($slotTask->id);
    expect($data['mgmtShiftUnconfigured']->pluck('id')->toArray())->toContain($plainTask->id);
    expect($data['mgmtShiftConfigured']->pluck('id')->toArray())->not->toContain($plainTask->id);
    expect($data['mgmtShiftUnconfigured']->pluck('id')->toArray())->not->toContain($slotTask->id);
});

// ── Time column generation ─────────────────────────────────────────────────────

test('EventSlotsPanelComposer generates time columns start inclusive, end exclusive', function () {
    $event = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);
    EventTask::create(['event_id' => $event->id, 'name' => 'Security',
        'slot_start_time' => '09:00:00', 'slot_end_time' => '11:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 1]);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);
    $cols = $view->getData()['mgmtShiftTimeColumns'];

    expect($cols)->toContain('09:00');
    expect($cols)->toContain('10:00');
    expect($cols)->not->toContain('11:00'); // end time is exclusive
});

test('EventSlotsPanelComposer merges and sorts time columns across multiple tasks', function () {
    $event = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);
    EventTask::create(['event_id' => $event->id, 'name' => 'Einlass',
        'slot_start_time' => '11:00:00', 'slot_end_time' => '12:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 1]);
    EventTask::create(['event_id' => $event->id, 'name' => 'Bar',
        'slot_start_time' => '09:00:00', 'slot_end_time' => '11:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 1]);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);

    expect($view->getData()['mgmtShiftTimeColumns'])->toBe(['09:00', '10:00', '11:00']);
});

// ── Event-day filter ───────────────────────────────────────────────────────────

test('EventSlotsPanelComposer excludes tasks with deadline before the event date', function () {
    $event        = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);
    $eventDayTask = EventTask::create(['event_id' => $event->id, 'name' => 'Stage crew', 'deadline_at' => null]);
    $prepTask     = EventTask::create(['event_id' => $event->id, 'name' => 'Order drinks', 'deadline_at' => '2027-08-09 10:00:00']);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);
    $ids = $view->getData()['mgmtShiftTasks']->pluck('id')->toArray();

    expect($ids)->toContain($eventDayTask->id);
    expect($ids)->not->toContain($prepTask->id);
});

// ── Colspan: tasks with a larger interval than the grid minimum ───────────────

test('EventSlotsPanelComposer sets colspan correctly when task intervals differ', function () {
    // Task A: 30-min interval → base (minInterval = 30) → colspan 1
    // Task B: 60-min interval → twice as wide → colspan 2
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);
    $taskA  = EventTask::create(['event_id' => $event->id, 'name' => 'Einlass',
        'slot_start_time' => '10:00:00', 'slot_end_time' => '12:00:00',
        'slot_interval_minutes' => 30, 'slot_capacity' => 1]);
    $taskB  = EventTask::create(['event_id' => $event->id, 'name' => 'Bar',
        'slot_start_time' => '10:00:00', 'slot_end_time' => '12:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 2]);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);
    $data = $view->getData();

    $grid     = $data['mgmtShiftGrid'];
    $skipCols = $data['mgmtShiftSkipCols'];
    $cols     = $data['mgmtShiftTimeColumns'];

    // Time labels: 10:00, 10:30, 11:00, 11:30 (all 30-min labels from both tasks)
    expect($cols)->toBe(['10:00', '10:30', '11:00', '11:30']);

    // Task A (30 min): colspan = 1 for all cells
    expect($grid[$taskA->id]['10:00']['colspan'])->toBe(1);
    expect($grid[$taskA->id]['10:30']['colspan'])->toBe(1);

    // Task B (60 min): colspan = 2 for all cells
    expect($grid[$taskB->id]['10:00']['colspan'])->toBe(2);
    expect($grid[$taskB->id]['11:00']['colspan'])->toBe(2);

    // Task A: no skip entries (colspan = 1)
    expect(isset($skipCols[$taskA->id]))->toBeFalse();

    // Task B: 10:30 and 11:30 must be skipped (covered by 10:00 and 11:00 respectively)
    expect($skipCols[$taskB->id]['10:30'] ?? false)->toBeTrue();
    expect($skipCols[$taskB->id]['11:30'] ?? false)->toBeTrue();
    expect(isset($skipCols[$taskB->id]['10:00']))->toBeFalse();
    expect(isset($skipCols[$taskB->id]['11:00']))->toBeFalse();
});

test('EventSlotsPanelComposer has no skipCols when all tasks share the same interval', function () {
    $event = Event::factory()->create(['starts_at' => Carbon::parse('2027-08-10 18:00:00')]);
    EventTask::create(['event_id' => $event->id, 'name' => 'Security',
        'slot_start_time' => '09:00:00', 'slot_end_time' => '12:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 1]);
    EventTask::create(['event_id' => $event->id, 'name' => 'Bar',
        'slot_start_time' => '10:00:00', 'slot_end_time' => '13:00:00',
        'slot_interval_minutes' => 60, 'slot_capacity' => 2]);

    $view = view('management::event-slots-panel', compact('event'));
    (new EventSlotsPanelComposer())->compose($view);

    expect($view->getData()['mgmtShiftSkipCols'])->toBeEmpty();
});

// ── Empty state ───────────────────────────────────────────────────────────────

test('EventSlotsPanelComposer returns empty state when no event is available', function () {
    $view = view('management::event-slots-panel');
    (new EventSlotsPanelComposer())->compose($view);
    $data = $view->getData();

    expect($data['mgmtShiftTasks']->isEmpty())->toBeTrue();
    expect($data['mgmtShiftConfigured']->isEmpty())->toBeTrue();
    expect($data['mgmtShiftTimeColumns'])->toBeEmpty();
    expect($data['mgmtShiftGrid'])->toBeEmpty();
    expect($data['mgmtShiftSkipCols'])->toBeEmpty();
});