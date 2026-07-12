{{--
    Management hook: Einsatzplan tab on the event detail page.
    Extension point: events.show.slots-panel
    Registered by:   ManagementServiceProvider
    Composer:        EventSlotsPanelComposer

    Variables injected by EventSlotsPanelComposer:
      $mgmtShiftTasks          → Collection<EventTask>  (all event-day tasks)
      $mgmtShiftConfigured     → Collection<EventTask>  (tasks with slot config set)
      $mgmtShiftUnconfigured   → Collection<EventTask>  (tasks without slot config)
      $mgmtShiftTimeColumns    → list<string>            (sorted H:i slot-start labels)
      $mgmtShiftGrid           → array<task_id, array<label, cell>>

    Delete pattern (app.js Strategy C):
      <x-ck-button variant="danger" size="icon"
          class="ck-task-remove-btn"
          data-task-id="{{ $task->id }}"
          data-ck-confirm="{{ __('...') }}">
          [trash SVG]
      </x-ck-button>
      → data-ck-confirm intercepts click, opens confirm modal.
      → After confirm: data-ck-confirm removed, button re-clicked,
        task-modal.js AJAX DELETE handler fires.
      → No <form> needed.

    $event is always available from the parent view data (EventController::show).
--}}

{{-- ── Shift plan grid card — always rendered ─────────────────────────────── --}}
{{-- Plain div instead of x-ck-card: avoids Blade compiler bug with @foreach inside anonymous components. --}}
<div class="ck-card ck-mb-4">
    <div class="ck-card__header">
        <span class="ck-card__header-title">🗓 {{ __('events.slot.grid_title') }}</span>
        {{-- "+" button: same pattern as section headers in event-tasks-panel.blade.php --}}
        {{-- "+" opens slot-config modal (select task + set time range / interval / capacity) --}}
        <x-ck-button variant="success" size="icon"
            onclick="ckOpenShiftConfig(null)"
            title="{{ __('events.slot.add_to_grid') }}">+</x-ck-button>
    </div>

    @if($mgmtShiftConfigured->isEmpty() || count($mgmtShiftTimeColumns) === 0)
        <div class="ck-empty-state">{{ __('events.slot.no_configured_tasks') }}</div>
    @else
        <div class="ck-shift-grid-wrap">
            <table class="ck-shift-grid">
                <thead>
                    <tr>
                        <th class="ck-shift-grid__task-col">{{ __('events.slot.col_task') }}</th>
                        @foreach($mgmtShiftTimeColumns as $mgmtECol)
                        <th class="ck-shift-grid__time-col">{{ $mgmtECol }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($mgmtShiftConfigured as $mgmtCTask)
                    @php
                        $mgmtTaskCells = $mgmtShiftGrid[$mgmtCTask->id] ?? [];
                        $mgmtCapacity  = (int) ($mgmtCTask->slot_capacity ?? 1);
                        $mgmtSlotStart = substr((string) $mgmtCTask->slot_start_time, 0, 5);
                        $mgmtSlotEnd   = substr((string) $mgmtCTask->slot_end_time, 0, 5);
                        // $mgmtTaskCellsJs removed — JS reads slot data from window.CK_ShiftGrid[taskId].
                    @endphp
                    <tr>
                        {{--
                            Task name cell.
                            Layout: action buttons FIRST in DOM → appear top-right
                            (.ck-table__action-cell: flex + justify-content:flex-end).
                            Name + meta follow below.
                        --}}
                        <td class="ck-einsatz-grid__task-col">
                            {{-- 2-column layout: task info (left, grows) | action buttons (right, fixed) --}}
                            <div class="ck-einsatz-task-cell">
                                <div class="ck-einsatz-task-cell__info">
                                    <div class="ck-shift-task-name">{{ $mgmtCTask->name }}</div>
                                    <div class="ck-table__sub">
                                        {{ $mgmtSlotStart }}–{{ $mgmtSlotEnd }},
                                        {{ $mgmtCTask->slot_interval_minutes }} min /
                                        {{ $mgmtCapacity }} {{ __('events.slot.persons') }}
                                    </div>
                                </div>
                                <div class="ck-einsatz-task-cell__actions">
                                    {{-- Edit slot config --}}
                                    <x-ck-button variant="warning" size="icon"
                                        onclick="ckOpenShiftConfig({{ $mgmtCTask->id }})"
                                        title="{{ __('Edit') }}">
                                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/></svg>
                                    </x-ck-button>
                                    {{--
                                        Delete EventTask — Strategy C (app.js line 507).
                                        data-ck-confirm intercepts → confirm modal → re-click
                                        → task-modal.js .ck-task-remove-btn AJAX DELETE fires.
                                    --}}
                                    <x-ck-button variant="danger" size="icon"
                                        class="ck-task-remove-btn"
                                        data-task-id="{{ $mgmtCTask->id }}"
                                        data-ck-confirm="{{ __('events.task.confirm_delete', ['name' => $mgmtCTask->name]) }}"
                                        title="{{ __('Delete') }}">
                                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    </x-ck-button>
                                </div>
                            </div>
                        </td>

                        {{-- One cell per global time-column label --}}
                        @foreach($mgmtShiftTimeColumns as $mgmtECol)
                        @php
                            $mgmtCell    = $mgmtTaskCells[$mgmtECol] ?? null;
                            // Skip flag: column is visually covered by a preceding colspan > 1 cell.
                            $mgmtSkip    = $mgmtShiftSkipCols[$mgmtCTask->id][$mgmtECol] ?? false;
                            $mgmtColspan = $mgmtCell ? ($mgmtCell['colspan'] ?? 1) : 1;
                            if ($mgmtCell) {
                                $mgmtCount = count($mgmtCell['assigned']);
                                if ($mgmtCount >= $mgmtCell['capacity']) {
                                    $mgmtMod = 'ck-shift-cell--green';
                                } elseif ($mgmtCount > 0) {
                                    $mgmtMod = 'ck-shift-cell--amber';
                                } else {
                                    $mgmtMod = 'ck-shift-cell--red';
                                }
                            }
                        @endphp
                        @if(!$mgmtSkip)
                        @if($mgmtCell)
                        {{--
                            Click opens assign modal for ALL slots of this task.
                            task-name stored in a data attribute (avoids HTML-attribute quoting
                            issues with @js() which outputs raw JSON double-quotes).
                            Slot data is read from window.CK_ShiftGrid[taskId] (set below).
                            colspan > 1 for tasks whose interval is larger than the grid minimum.
                        --}}
                        <td class="ck-shift-cell {{ $mgmtMod }}"
                            @if($mgmtColspan > 1) colspan="{{ $mgmtColspan }}" @endif
                            data-task-name="{{ $mgmtCTask->name }}"
                            onclick="ckOpenShiftAssign({{ $mgmtCTask->id }}, this)"
                            title="{{ __('events.slot.click_to_assign') }}">
                            @foreach($mgmtCell['assigned'] as $mgmtSlot)
                            @php
                                /* Initials from "Nachname, Vorname" format:
                                   "Müller, Marianne" → "MM" */
                                $mgmtParts     = explode(', ', $mgmtSlot['name'], 2);
                                $mgmtFirstName = trim($mgmtParts[1] ?? $mgmtSlot['name']);
                                $mgmtLastInit  = strtoupper(substr(trim($mgmtParts[0] ?? ''), 0, 1));
                                $mgmtFirsInit  = strtoupper(substr($mgmtFirstName, 0, 1));
                                $mgmtInitials  = $mgmtFirsInit . $mgmtLastInit;
                            @endphp
                            {{-- Avatar chip: circle with initials + compact × remove button --}}
                            <span class="ck-shift-chip" title="{{ $mgmtSlot['name'] }}">
                                <span class="ck-avatar ck-avatar--sm" aria-hidden="true">{{ $mgmtInitials }}</span>
                                <button type="button"
                                    class="ck-shift-chip__remove ck-slot-remove-btn"
                                    data-slot-id="{{ $mgmtSlot['id'] }}"
                                    title="{{ __('Remove') }}: {{ $mgmtSlot['name'] }}"
                                    onclick="event.stopPropagation(); ckSlotRemove(this)">×</button>
                            </span>
                            @endforeach
                            {{-- Empty-cell affordance: "+" indicates the cell is clickable to assign a member. --}}
                            @if(count($mgmtCell['assigned']) === 0)
                            <span class="ck-shift-cell__add-hint">+</span>
                            @endif
                            <span class="ck-shift-count">{{ count($mgmtCell['assigned']) }}/{{ $mgmtCell['capacity'] }}</span>
                        </td>
                        @else
                        <td class="ck-shift-cell ck-shift-cell--out"></td>
                        @endif
                        @endif
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ── Unconfigured tasks — standard ck-table with icon action buttons ────── --}}
@if($mgmtShiftUnconfigured->isNotEmpty())
<div class="ck-card">
    <div class="ck-card__header">
        <span class="ck-card__header-title">{{ __('events.slot.unconfigured_tasks') }}</span>
    </div>
    <div class="ck-table-wrap">
        <table class="ck-table">
            <tbody>
                @foreach($mgmtShiftUnconfigured as $mgmtUTask)
                <tr>
                    <td class="ck-table__bold">{{ $mgmtUTask->name }}</td>
                    <td class="ck-table__actions">
                        <div class="ck-table__action-cell">
                            {{-- Add to grid: green "+" like section-header add-task buttons --}}
                            <x-ck-button variant="success" size="icon"
                                onclick="ckOpenShiftConfig({{ $mgmtUTask->id }})"
                                title="{{ __('events.slot.add_to_grid') }}">+</x-ck-button>
                            {{-- Delete EventTask — same Strategy C as above --}}
                            <x-ck-button variant="danger" size="icon"
                                class="ck-task-remove-btn"
                                data-task-id="{{ $mgmtUTask->id }}"
                                data-ck-confirm="{{ __('events.task.confirm_delete', ['name' => $mgmtUTask->name]) }}"
                                title="{{ __('Delete') }}">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            </x-ck-button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Modal: shift plan configure ─────────────────────────────────────────── --}}
<x-ck-modal id="ckShiftConfigModal" :title="__('events.slot.config_modal_title')" size="sm">
    <x-ck-field
        type="select"
        :label="__('events.slot.field_task')"
        name="shift_config_task_id"
        id="ckShiftConfigTaskId"
        :options="$mgmtShiftTasks->pluck('name', 'id')->toArray()" />

    <div class="ck-form-grid ck-form-grid--2 ck-mt-4">
        <x-ck-field type="time" :label="__('events.slot.field_start')"
            name="shift_config_start" id="ckShiftConfigStart" :required="true" />
        <x-ck-field type="time" :label="__('events.slot.field_end')"
            name="shift_config_end" id="ckShiftConfigEnd" :required="true" />
    </div>

    <div class="ck-form-grid ck-form-grid--2 ck-mt-4">
        <x-ck-field type="select" :label="__('events.slot.field_interval')"
            name="shift_config_interval" id="ckShiftConfigInterval"
            :options="[15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '60 min', 90 => '90 min', 120 => '120 min']" />
        <x-ck-field type="number" :label="__('events.slot.field_capacity')"
            name="shift_config_capacity" id="ckShiftConfigCapacity"
            :value="1" min="1" max="20" />
    </div>

    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="ckShiftConfigSubmitBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'ckShiftConfigModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── Modal: assign members — 2-col grid (pool + slot drop-zones) ────────── --}}
{{--
    Layout: left = member pool (not assigned to ANY slot of this task).
            right = ALL slots of the task as individual SortableJS drop-zones.

    ckOpenShiftAssign(taskId, taskName, allSlotsArray) in globals.js populates:
      - #shiftAssignAvailableList — members not in any slot
      - #slotAssignZones          — one .ck-slot-zone per slot (built dynamically)

    SortableJS is re-initialised on every modal open (zones are dynamic).
    AJAX (assign / remove) handled in slot-modal.js :: initSlotModal().
--}}
<x-ck-modal id="ckShiftAssignModal" :title="__('events.slot.assign_modal_title')" size="lg">

    {{-- Task name for context --}}
    <p id="ckShiftAssignLabel" class="ck-text-muted ck-mb-4"></p>

    {{-- Set by ckOpenShiftAssign() --}}
    <input type="hidden" id="ckShiftAssignTaskId" value="">

    {{-- 2-col grid: member pool (left) | slot drop-zones (right) --}}
    <div class="ck-slot-assign-grid">

        {{-- Left column: available members (drag source) --}}
        <div class="ck-slot-assign-pool">
            <span class="ck-dual-listbox__label">{{ __('events.assign.available') }}</span>
            <ul id="shiftAssignAvailableList" class="ck-assign-list"></ul>
        </div>

        {{-- Right column: slot zones built dynamically by ckOpenShiftAssign() --}}
        <div class="ck-slot-assign-zones" id="slotAssignZones">
            {{-- Populated with .ck-slot-zone elements on each modal open --}}
        </div>

    </div>

    <div class="ck-form-actions ck-form-actions--spread">
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'ckShiftAssignModal')">
            {{ __('Cancel') }}
        </x-ck-button>
        <x-ck-button variant="success" type="button" id="ckShiftAssignDoneBtn">
            {{ __('Save') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{--
    Shift grid data bridge.
    Plain script tag — NOT via push/stack — because ckHook renders views via
    view()->render() which does not propagate pushed scripts to the parent stack.
    The inline tag executes synchronously during HTML parsing, guaranteeing
    window.CK_ShiftGrid is set before any user interaction.

    Structure: { "taskId": { "HH:MM": { time_from, time_to, capacity, assigned[] } } }
    Consumer:  ckOpenShiftAssign() in globals.js
--}}
<script>window.CK_ShiftGrid = @json($mgmtShiftGrid);</script>