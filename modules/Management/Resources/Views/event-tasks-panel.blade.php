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
                        <th>Aufgabe</th>
                        <th>Priorität</th>
                        <th>Deadline</th>
                        <th>Notiz</th>
                        <th>Verantwortliche(r)</th>
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

                        <td class="ck-task-row__name">{{ $mgmtTask->name }}</td>

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
                            @forelse($mgmtMemberMap[$mgmtTask->id] ?? [] as $mgmtEtm)
                                <span class="ck-task-member">
                                    {{ $mgmtEtm['name'] }}
                                    @if($mgmtEtm['time_from'])
                                        <span class="ck-task-member__time">
                                            ({{ $mgmtEtm['time_from'] }}–{{ $mgmtEtm['time_to'] }})
                                        </span>
                                    @endif
                                </span>
                            @empty
                                <span class="ck-text-muted">–</span>
                            @endforelse
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
        <p class="ck-text-muted">Noch keine Aufgaben zugewiesen. Füge unten eine Aufgabe hinzu.</p>
    </x-ck-card>
@endforelse

{{-- ── Add task ──────────────────────────────────────────────────────────────── --}}
@if($mgmtGroupedAvailableTasks->isNotEmpty())
<x-ck-card class="ck-no-print">
    <x-slot:header>Aufgabe hinzufügen</x-slot:header>
    <div class="ck-add-task">
        <select id="addTaskSelect" class="ck-form-select">
            <option value="">– Aufgabe wählen –</option>
            @foreach($mgmtGroupedAvailableTasks as $mgmtGroupName => $mgmtGroupTasks)
                <optgroup label="{{ $mgmtGroupName }}">
                    @foreach($mgmtGroupTasks as $mgmtT)
                        <option value="{{ $mgmtT->id }}">{{ $mgmtT->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <x-ck-button variant="primary" type="button" id="addTaskBtn">
            Hinzufügen
        </x-ck-button>
    </div>
</x-ck-card>
@endif

{{-- ── Management functions ─────────────────────────────────────────────────── --}}
@if($mgmtFunctions->isNotEmpty())
<x-ck-card>
    <x-slot:header>Vereinsfunktionen</x-slot:header>
    <table class="ck-table">
        <thead>
            <tr><th>Funktion</th></tr>
        </thead>
        <tbody>
            @foreach($mgmtFunctions as $mgmtFn)
            <tr><td>{{ $mgmtFn->name }}</td></tr>
            @endforeach
        </tbody>
    </table>
</x-ck-card>
@endif
