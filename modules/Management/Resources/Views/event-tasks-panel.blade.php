{{--
    Management-Hook: Full task panel on the event detail page.
    Extension point: events.show.tasks-panel
    Registered by: ManagementServiceProvider

    Data is provided by ManagementServiceProvider::composeEventTasksPanel() via View Composer.
    No @php blocks or DB queries in this view.

    Variables (injected by composer):
        $mgmtByCategory            → array<string, array{tasks, secDone, secTotal, secColor}>
        $mgmtGroupedAvailableTasks → Collection grouped by category name
        $mgmtMemberMap             → array<int, list<array{id, member_id, name, time_from, time_to}>>
        $mgmtFunctions             → Collection<ManagementFunction>
        $mgmtPriorityColors        → array<string, string>
        $mgmtPriorityLabels        → array<string, string>
--}}

{{-- ── Action bar ───────────────────────────────────────────────────────────── --}}
<div class="ck-pane-actions">
    <div class="ck-header-dropdown">
        <x-ck-button variant="success" type="button"
            class="ck-header-dropdown__toggle"
            onclick="this.closest('.ck-header-dropdown').classList.toggle('ck-header-dropdown--open')">
            {{ __('events.task.add_header_btn') }} ▾
        </x-ck-button>
        <div class="ck-header-dropdown__menu">
            <button class="ck-header-dropdown__item" type="button"
                onclick="ckModalOpen('newTaskModal'); this.closest('.ck-header-dropdown').classList.remove('ck-header-dropdown--open')">
                {{ __('events.task.new_task') }}
            </button>
            <button class="ck-header-dropdown__item" type="button"
                onclick="ckModalOpen('newCatModal'); this.closest('.ck-header-dropdown').classList.remove('ck-header-dropdown--open')">
                {{ __('events.task.new_category') }}
            </button>
        </div>
    </div>
</div>

{{-- ── Task sections (by category, collapsible) ─────────────────────────────── --}}
@forelse($mgmtByCategory as $mgmtCatName => $mgmtCatSection)

    <details class="ck-event-section" open data-section="{{ Str::slug($mgmtCatName) }}">
        <summary class="ck-event-section__summary">
            <span class="ck-event-section__title">{{ $mgmtCatName }}</span>
            <x-ck-badge :color="$mgmtCatSection['secColor']" class="ck-event-section__badge"
                data-section-badge="{{ Str::slug($mgmtCatName) }}">
                {{ $mgmtCatSection['secDone'] }}/{{ $mgmtCatSection['secTotal'] }}
            </x-ck-badge>
        </summary>

        <div class="ck-event-section__body">
            <table class="ck-table">
                <thead>
                    <tr>
                        <th class="ck-table__col--checkbox"></th>
                        <th>{{ __('events.task.col_task') }}</th>
                        <th>{{ __('events.task.col_priority') }}</th>
                        <th>{{ __('events.task.col_deadline') }}</th>
                        <th>{{ __('events.task.col_notes') }}</th>
                        <th>{{ __('events.function.col_responsible') }}</th>
                        <th class="ck-table__col--actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mgmtCatSection['tasks'] as $mgmtTask)
                    <tr class="ck-task-row{{ $mgmtTask->ev_completed ? ' ck-task-row--done' : '' }}"
                        data-task-id="{{ $mgmtTask->id }}"
                        data-section="{{ Str::slug($mgmtCatName) }}">

                        {{-- Done checkbox (AJAX) --}}
                        <td class="ck-table__col--checkbox">
                            <input type="checkbox"
                                class="ck-task-checkbox"
                                data-task-id="{{ $mgmtTask->id }}"
                                {{ $mgmtTask->ev_completed ? 'checked' : '' }}>
                        </td>

                        <td class="ck-task-row__name">
                            {{ $mgmtTask->name }}
                            @if($mgmtTask->ev_deadline === null)
                                <x-ck-badge color="blue">{{ __('events.task.event_day_badge') }}</x-ck-badge>
                            @endif
                        </td>

                        <td>
                            <x-ck-badge :color="$mgmtPriorityColors[$mgmtTask->priority] ?? 'gray'">
                                {{ $mgmtPriorityLabels[$mgmtTask->priority] ?? $mgmtTask->priority }}
                            </x-ck-badge>
                        </td>

                        <td class="ck-task-row__deadline">
                            {{ $mgmtTask->ev_deadline
                                ? \Carbon\Carbon::parse($mgmtTask->ev_deadline)->format('d.m.Y H:i')
                                : '–' }}
                        </td>

                        <td class="ck-task-row__notes">{{ $mgmtTask->ev_notes ?: '–' }}</td>

                        <td class="ck-task-row__members">
                            {{-- Non-slotted ETMs (time_from = null): show name + × remove --}}
                            {{-- Slotted ETMs (time_from set): show name + time, managed in Einsatzplan tab --}}
                            @foreach($mgmtMemberMap[$mgmtTask->id] ?? [] as $mgmtEtm)
                                <span class="ck-task-member">
                                    {{ $mgmtEtm['name'] }}
                                    @if($mgmtEtm['time_from'])
                                        <span class="ck-task-member__time">
                                            ({{ $mgmtEtm['time_from'] }}–{{ $mgmtEtm['time_to'] }})
                                        </span>
                                    @else
                                        <button type="button"
                                            class="ck-etm-remove-btn"
                                            data-etm-id="{{ $mgmtEtm['id'] }}"
                                            aria-label="{{ __('Remove') }}">×</button>
                                    @endif
                                </span>
                            @endforeach
                            {{-- Inline assign select — options populated by events-detail.js from CK_EventDetail.members --}}
                            <select class="ck-task-assign-select ck-form-select"
                                    data-task-id="{{ $mgmtTask->id }}">
                                <option value="">{{ __('events.task.assign_member') }}</option>
                            </select>
                        </td>

                        <td class="ck-table__col--actions">
                            <x-ck-button variant="danger" size="sm"
                                type="button"
                                class="ck-task-remove-btn"
                                :data-task-id="$mgmtTask->id"
                                :confirm="'Aufgabe \'' . $mgmtTask->name . '\' vom Termin entfernen?'">
                                ×
                            </x-ck-button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

@empty
    <x-ck-card>
        <p class="ck-text-muted">{{ __('events.task.empty') }}</p>
    </x-ck-card>
@endforelse

{{-- ── Add task ──────────────────────────────────────────────────────────────── --}}
@if($mgmtGroupedAvailableTasks->isNotEmpty())
<x-ck-card class="ck-no-print">
    <x-slot:header>{{ __('events.task.add_card_header') }}</x-slot:header>
    <div class="ck-add-task">
        <select id="addTaskSelect" class="ck-form-select">
            <option value="">{{ __('events.task.select_placeholder') }}</option>
            @foreach($mgmtGroupedAvailableTasks as $mgmtGroupName => $mgmtGroupTasks)
                <optgroup label="{{ $mgmtGroupName }}">
                    @foreach($mgmtGroupTasks as $mgmtT)
                        <option value="{{ $mgmtT->id }}">{{ $mgmtT->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <x-ck-button variant="primary" type="button" id="addTaskBtn">
            {{ __('Add') }}
        </x-ck-button>
    </div>
</x-ck-card>
@endif

{{-- Management functions have moved to the dedicated Funktionen tab (events.show.functions-panel). --}}