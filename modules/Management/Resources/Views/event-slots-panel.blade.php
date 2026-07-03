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

{{-- ── Slot table ───────────────────────────────────────────────────────────── --}}
@forelse($mgmtEinsatzTasks as $mgmtETask)
<details class="ck-event-section" open data-section="einsatz-{{ $mgmtETask->id }}">
    <summary class="ck-event-section__summary">
        <span class="ck-event-section__title">{{ $mgmtETask->name }}</span>
        <x-ck-badge :color="$mgmtEinsatzPriorityColors[$mgmtETask->priority] ?? 'gray'"
                    class="ck-event-section__badge">
            {{ $mgmtEinsatzPriorityLabels[$mgmtETask->priority] ?? $mgmtETask->priority }}
        </x-ck-badge>
    </summary>

    <div class="ck-event-section__body">
        @if(!empty($mgmtEinsatzSlotMap[$mgmtETask->id]))
        <table class="ck-table">
            <thead>
                <tr>
                    <th>{{ __('events.slot.from') }}</th>
                    <th>{{ __('events.slot.to') }}</th>
                    <th>{{ __('events.slot.person') }}</th>
                    <th class="ck-table__col--actions"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($mgmtEinsatzSlotMap[$mgmtETask->id] as $slot)
                <tr>
                    <td class="ck-slot-cell-time">{{ $slot['time_from'] }}</td>
                    <td class="ck-slot-cell-time">{{ $slot['time_to'] }}</td>
                    <td class="ck-slot-cell-member">{{ $slot['name'] }}</td>
                    <td class="ck-table__col--actions">
                        <x-ck-button variant="danger" size="sm" type="button"
                            class="ck-slot-remove-btn"
                            :data-slot-id="$slot['id']"
                            :confirm="'Einsatz von ' . $slot['name'] . ' entfernen?'">
                            ×
                        </x-ck-button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="ck-orga-section__empty">{{ __('events.slot.none') }}</p>
        @endif
    </div>
</details>
@empty
<x-ck-card>
    <p class="ck-text-muted">{{ __('events.slot.no_tasks') }}</p>
</x-ck-card>
@endforelse

{{-- ── Add slot form ────────────────────────────────────────────────────────── --}}
@if($mgmtEinsatzTasks->isNotEmpty())
<x-ck-card class="ck-no-print">
    <x-slot:header>{{ __('events.slot.assign') }}</x-slot:header>
    <div class="ck-form-grid ck-form-grid--2">
        <div class="ck-form-group">
            <label class="ck-label">{{ __('events.tab.tasks') }}</label>
            <select id="slotTaskSelect" class="ck-form-select">
                <option value="">{{ __('events.slot.select_task') }}</option>
                @foreach($mgmtEinsatzTasks as $mgmtETask)
                <option value="{{ $mgmtETask->id }}">{{ $mgmtETask->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="ck-form-group">
            <label class="ck-label">{{ __('events.slot.person') }}</label>
            <select id="slotMemberSelect" class="ck-form-select">
                <option value="">{{ __('events.slot.select_member') }}</option>
                @foreach($mgmtEinsatzMembersJs as $mgmtMember)
                <option value="{{ $mgmtMember['id'] }}">{{ $mgmtMember['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="ck-slot-time-row">
        <x-ck-field type="time" :label="__('events.slot.from')" name="slot_time_from" id="slotTimeFrom" :required="true" />
        <x-ck-field type="time" :label="__('events.slot.to')"   name="slot_time_to"   id="slotTimeTo"   :required="true" />
    </div>
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="addSlotBtn">
            {{ __('events.slot.save') }}
        </x-ck-button>
    </div>
</x-ck-card>
@endif