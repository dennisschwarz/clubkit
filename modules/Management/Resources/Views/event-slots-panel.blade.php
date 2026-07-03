{{--
    Management hook: Slots tab on the event detail page (Einsatzplan).
    Extension point: events.show.slots-panel
    Registered by: ManagementServiceProvider

    Data injected by ManagementServiceProvider::composeEventEinsatzplanPanel():
      $mgmtEinsatzTasks          → ManagementTask[] (event-day tasks only)
      $mgmtEinsatzSlotMap        → array<task_id, list<array{id, member_id, name, time_from, time_to}>>
      $mgmtEinsatzMembersJs      → array<id, array{id, name}> for member select
      $mgmtEinsatzPriorityColors → array<string, string>
      $mgmtEinsatzPriorityLabels → array<string, string>
--}}

{{-- ── Action bar ───────────────────────────────────────────────────────────── --}}
<div class="ck-pane-actions">
    <x-ck-button variant="primary" type="button" onclick="ckModalOpen('slotModal')">
        {{ __('events.slot.assign') }}
    </x-ck-button>
</div>

{{-- ── Slot table per concept: one row per Eventtag-Task ─────────────────── --}}
@if($mgmtEinsatzTasks->isEmpty())
<x-ck-card>
    <p class="ck-text-muted">{{ __('events.slot.no_tasks') }}</p>
</x-ck-card>
@else
<table class="ck-table">
    <thead>
        <tr>
            <th class="ck-table__col--checkbox"></th>
            <th>{{ __('events.slot.col_task') }}</th>
            <th>{{ __('events.slot.col_priority') }}</th>
            <th>{{ __('events.slot.col_slot') }}</th>
            <th>{{ __('events.slot.col_staffed') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($mgmtEinsatzTasks as $mgmtETask)
        <tr class="ck-task-row{{ $mgmtETask->ev_completed ? ' ck-task-row--done' : '' }}">
            <td class="ck-table__col--checkbox">
                <input type="checkbox"
                    class="ck-task-checkbox"
                    data-task-id="{{ $mgmtETask->id }}"
                    {{ $mgmtETask->ev_completed ? 'checked' : '' }}>
            </td>
            <td>{{ $mgmtETask->name }}</td>
            <td>
                <x-ck-badge :color="$mgmtEinsatzPriorityColors[$mgmtETask->priority] ?? 'gray'">
                    {{ $mgmtEinsatzPriorityLabels[$mgmtETask->priority] ?? $mgmtETask->priority }}
                </x-ck-badge>
            </td>
            <td>
                {{-- Time range badges for each slot --}}
                @foreach($mgmtEinsatzSlotMap[$mgmtETask->id] ?? [] as $slot)
                <span class="ck-slot-badge">{{ $slot['time_from'] }}–{{ $slot['time_to'] }}</span>
                @endforeach
            </td>
            <td>
                {{-- Member chip with × remove per slot --}}
                @foreach($mgmtEinsatzSlotMap[$mgmtETask->id] ?? [] as $slot)
                <span class="ck-member-chip">
                    {{ $slot['name'] }}
                    <button type="button"
                        class="ck-slot-remove-btn"
                        data-slot-id="{{ $slot['id'] }}"
                        aria-label="{{ __('Remove') }}">×</button>
                </span>
                @endforeach
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif