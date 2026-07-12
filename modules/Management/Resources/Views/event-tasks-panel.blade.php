<?php
/*
 * Management hook: task panel on the event detail page.
 *
 * Extension point:  events.show.tasks-panel
 * Registered by:   ManagementServiceProvider
 * Composer:        ManagementServiceProvider::composeEventTasksPanel()
 *
 * Injected variables:
 *   $mgmtByCategory      array<int, {category, tasks, secDone, secTotal, secColor}>
 *                        Named categories only (integer keys).
 *   $mgmtAllgSection     array{tasks, secDone, secTotal, secColor}
 *                        Uncategorised tasks — always rendered as the first section.
 *   $mgmtEventCategories Collection<EventTaskCategory>
 *   $mgmtMemberMap       array<event_task_id, list<array{id, member_id, name}>>
 *   $mgmtAvailableGlobalTasks  Collection<ManagementTask>
 *   $mgmtPriorityColors  array<string, string>
 *   $mgmtPriorityLabels  array<string, string>
 */
?>

@php
    $mgmtAllgTasks  = ($mgmtAllgSection ?? [])['tasks']    ?? [];
    $mgmtAllgDone   = ($mgmtAllgSection ?? [])['secDone']  ?? 0;
    $mgmtAllgTotal  = ($mgmtAllgSection ?? [])['secTotal'] ?? 0;
    $mgmtPriColors  = $mgmtPriorityColors ?? [];
    $mgmtPriLabels  = $mgmtPriorityLabels ?? [];
    $mgmtMMap       = $mgmtMemberMap      ?? [];

    // Flat list of existing event tasks for duplicate detection in import.js.
    // Each entry: {name, category} — category is null for uncategorised tasks.
    $mgmtExistingTasksJs = [];
    foreach ($mgmtAllgTasks as $mgmtEt) {
        $mgmtExistingTasksJs[] = ['name' => $mgmtEt->name, 'category' => null];
    }
    foreach ($mgmtByCategory ?? [] as $mgmtCatSec) {
        foreach ($mgmtCatSec['tasks'] ?? [] as $mgmtEt) {
            $mgmtExistingTasksJs[] = [
                'name'     => $mgmtEt->name,
                'category' => $mgmtCatSec['category']->name,
            ];
        }
    }

    // Calendar days spanned by this event — passed to import.js for the
    // slot-task deadline picker. [{value: 'YYYY-MM-DD', label: 'Mo., 12.08.2026'}, ...]
    $mgmtEventDates = [];
    $mgmtDayNames   = ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'];
    $mgmtEvtStart   = $event->starts_at->copy()->startOfDay();
    $mgmtEvtEnd     = ($event->ends_at ?? $event->starts_at)->copy()->startOfDay();
    for ($mgmtDay = $mgmtEvtStart->copy(); $mgmtDay <= $mgmtEvtEnd; $mgmtDay->addDay()) {
        $mgmtEventDates[] = [
            'value' => $mgmtDay->format('Y-m-d'),
            'label' => $mgmtDayNames[$mgmtDay->dayOfWeek] . ', ' . $mgmtDay->format('d.m.Y'),
        ];
    }
@endphp

{{-- General section: hardcoded, always rendered first --}}
<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--collapsible ck-section-header--team-blue ck-section-header--colored"
         onclick="ckSectionToggle('ck-tasks-body-allgemein', 'ck-tasks-chev-allgemein')">
        <div class="ck-section-header__text">
            <div class="ck-section-header__title-row">
                <span class="ck-section-header__title">{{ __('events.task.section_general') }}</span>
                <span class="ck-badge ck-badge--{{ $mgmtAllgTotal > 0 && $mgmtAllgDone === $mgmtAllgTotal ? 'green' : ($mgmtAllgDone > 0 ? 'orange' : 'gray') }}"
                      id="ck-sec-badge-allgemein">{{ $mgmtAllgDone }}/{{ $mgmtAllgTotal }}</span>
            </div>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <x-ck-button variant="success" size="icon"
                onclick="ckOpenNewTask(''); event.stopPropagation();"
                title="{{ __('events.task.add_task') }}">+</x-ck-button>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="ck-tasks-chev-allgemein"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></span>
    </div>
    <div id="ck-tasks-body-allgemein">
        <div class="ck-table-wrap">
            <table class="ck-table">
                <thead>
                    <tr>
                        <th class="ck-table__col--checkbox"></th>
                        <th><button type="button" class="ck-sort-link ck-task-sort-btn" data-col="name"     onclick="ckTaskSortBy('name',this)"><span>{{ __('events.task.col_task') }}</span><span class="ck-sort-link__icon" aria-hidden="true">⇅</span></button></th>
                        <th><button type="button" class="ck-sort-link ck-task-sort-btn" data-col="priority" onclick="ckTaskSortBy('priority',this)"><span>{{ __('events.task.col_priority') }}</span><span class="ck-sort-link__icon" aria-hidden="true">⇅</span></button></th>
                        <th><button type="button" class="ck-sort-link ck-task-sort-btn" data-col="deadline" onclick="ckTaskSortBy('deadline',this)"><span>{{ __('events.task.col_deadline') }}</span><span class="ck-sort-link__icon" aria-hidden="true">⇅</span></button></th>
                        <th>{{ __('events.task.col_notes') }}</th>
                        <th>{{ __('events.function.col_responsible') }}</th>
                        <th class="ck-table__col--actions"></th>
                    </tr>
                </thead>
                <tbody class="ck-task-sortable" data-cat-id="allgemein">
                    @if(empty($mgmtAllgTasks))
                    <tr class="ck-task-row--empty">
                        <td colspan="8" class="ck-empty-state">{{ __('events.task.section_empty') }}</td>
                    </tr>
                    @endif
                    @foreach($mgmtAllgTasks as $mgmtTask)
                    @php
                        $mgmtSortPri = ['normal'=>'1','important'=>'2','critical'=>'3'][$mgmtTask->priority ?? 'normal'] ?? '1';
                        $mgmtSortDl  = $mgmtTask->deadline_at ? $mgmtTask->deadline_at->format('Y-m-d H:i') : '9999-12-31 00:00';
                    @endphp
                    <tr class="ck-task-row{{ $mgmtTask->completed ? ' ck-task-row--done' : '' }}"
                        data-task-id="{{ $mgmtTask->id }}" data-section="allgemein"
                        data-sort-name="{{ strtolower($mgmtTask->name) }}"
                        data-sort-priority="{{ $mgmtSortPri }}"
                        data-sort-deadline="{{ $mgmtSortDl }}">
                        <td class="ck-table__col--checkbox">
                            <input type="checkbox" class="ck-task-checkbox"
                                data-task-id="{{ $mgmtTask->id }}"
                                {{ $mgmtTask->completed ? 'checked' : '' }}>
                        </td>
                        <td class="ck-task-row__name">
                            {{ $mgmtTask->name }}
                            @if($mgmtTask->deadline_at === null)
                                <span class="ck-badge ck-badge--blue">{{ __('events.task.event_day_badge') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="ck-badge ck-badge--{{ $mgmtPriColors[$mgmtTask->priority] ?? 'gray' }}">
                                {{ $mgmtPriLabels[$mgmtTask->priority] ?? $mgmtTask->priority }}
                            </span>
                        </td>
                        <td class="ck-task-row__deadline">
                            @if($mgmtTask->deadline_at){{ $mgmtTask->deadline_at->format('d.m.Y H:i') }}@else–@endif
                        </td>
                        <td class="ck-task-row__notes">{{ $mgmtTask->notes ?: '–' }}</td>
                        <td class="ck-task-row__members">
                            @foreach($mgmtMMap[$mgmtTask->id] ?? [] as $mgmtEtm)
                            <span class="ck-task-member">
                                {{ $mgmtEtm['name'] }}
                                <button type="button"
                                        class="ck-etm-remove-btn"
                                        data-etm-id="{{ $mgmtEtm['id'] }}"
                                        data-member-id="{{ $mgmtEtm['member_id'] }}"
                                        data-member-name="{{ $mgmtEtm['name'] }}"
                                        data-sort-order="{{ $mgmtEtm['sort_order'] }}"
                                        title="Entfernen">×</button>
                            </span>
                            @endforeach
                        </td>
                        <td class="ck-table__col--actions">
                            <div class="ck-table__action-cell">
                                <x-ck-button variant="warning" size="icon"
                                    class="ck-task-edit-btn"
                                    data-task-id="{{ $mgmtTask->id }}"
                                    data-task-name="{{ $mgmtTask->name }}"
                                    data-task-priority="{{ $mgmtTask->priority ?? 'normal' }}"
                                    data-task-deadline="{{ $mgmtTask->deadline_at ? $mgmtTask->deadline_at->format('Y-m-d\TH:i') : '' }}"
                                    data-task-cat-id=""
                                    title="{{ __('Edit') }}">
                                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/></svg>
                                </x-ck-button>
                                <x-ck-button variant="secondary" size="icon"
                                    class="ck-task-assign-btn"
                                    data-task-id="{{ $mgmtTask->id }}"
                                    data-task-name="{{ $mgmtTask->name }}"
                                    title="{{ __('events.task.assign_member') }}">
                                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                                </x-ck-button>
                                <x-ck-button variant="danger" size="icon"
                                    class="ck-task-remove-btn"
                                    data-task-id="{{ $mgmtTask->id }}"
                                    data-ck-confirm="{{ __('events.task.confirm_delete', ['name' => $mgmtTask->name]) }}"
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
</div>

{{-- Named category sections --}}
@foreach($mgmtByCategory ?? [] as $mgmtCatKey => $mgmtCatSection)
@php
    $mgmtCat        = $mgmtCatSection['category'];
    $mgmtCatId      = $mgmtCat->id;
    $mgmtCatColor   = $mgmtCat->color ?? '';
    $mgmtColorClass = $mgmtCatColor
        ? 'ck-section-header--team-' . $mgmtCatColor . ' ck-section-header--colored'
        : '';
    $mgmtBodyId     = 'ck-tasks-body-' . $mgmtCatId;
    $mgmtChevId     = 'ck-tasks-chev-' . $mgmtCatId;
    $mgmtCatTasks   = $mgmtCatSection['tasks'];
    $mgmtCatDone    = $mgmtCatSection['secDone'];
    $mgmtCatTotal   = $mgmtCatSection['secTotal'];
@endphp

<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--collapsible {{ $mgmtColorClass }}"
         onclick="ckSectionToggle('{{ $mgmtBodyId }}', '{{ $mgmtChevId }}')">
        <div class="ck-section-header__text">
            <div class="ck-section-header__title-row">
                <span class="ck-section-header__title">{{ $mgmtCat->name }}</span>
                <span class="ck-badge ck-badge--{{ $mgmtCatTotal > 0 && $mgmtCatDone === $mgmtCatTotal ? 'green' : ($mgmtCatDone > 0 ? 'orange' : 'gray') }}"
                      id="ck-sec-badge-{{ $mgmtCatId }}">{{ $mgmtCatDone }}/{{ $mgmtCatTotal }}</span>
            </div>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <x-ck-button variant="warning" size="icon"
                onclick="ckOpenCatRename('{{ $mgmtCatId }}', '{{ addslashes($mgmtCat->name) }}', '{{ $mgmtCatColor }}'); event.stopPropagation();"
                title="{{ __('events.cat.edit') }}">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/></svg>
            </x-ck-button>
            <x-ck-button variant="danger" size="icon"
                class="ck-cat-delete-btn"
                data-cat-id="{{ $mgmtCatId }}"
                data-cat-name="{{ $mgmtCat->name }}"
                data-ck-confirm="{{ __('events.cat.confirm_delete', ['name' => $mgmtCat->name]) }}"
                title="{{ __('Delete') }}">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            </x-ck-button>
            <x-ck-button variant="success" size="icon"
                onclick="ckOpenNewTask('{{ $mgmtCatId }}'); event.stopPropagation();"
                title="{{ __('events.task.add_task') }}">+</x-ck-button>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $mgmtChevId }}"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></span>
    </div>
    <div id="{{ $mgmtBodyId }}">
        <div class="ck-table-wrap">
            <table class="ck-table">
                <thead>
                    <tr>
                        <th class="ck-table__col--checkbox"></th>
                        <th><button type="button" class="ck-sort-link ck-task-sort-btn" data-col="name"     onclick="ckTaskSortBy('name',this)"><span>{{ __('events.task.col_task') }}</span><span class="ck-sort-link__icon" aria-hidden="true">⇅</span></button></th>
                        <th><button type="button" class="ck-sort-link ck-task-sort-btn" data-col="priority" onclick="ckTaskSortBy('priority',this)"><span>{{ __('events.task.col_priority') }}</span><span class="ck-sort-link__icon" aria-hidden="true">⇅</span></button></th>
                        <th><button type="button" class="ck-sort-link ck-task-sort-btn" data-col="deadline" onclick="ckTaskSortBy('deadline',this)"><span>{{ __('events.task.col_deadline') }}</span><span class="ck-sort-link__icon" aria-hidden="true">⇅</span></button></th>
                        <th>{{ __('events.task.col_notes') }}</th>
                        <th>{{ __('events.function.col_responsible') }}</th>
                        <th class="ck-table__col--actions"></th>
                    </tr>
                </thead>
                <tbody class="ck-task-sortable" data-cat-id="{{ $mgmtCatId }}">
                    @if(empty($mgmtCatTasks))
                    <tr class="ck-task-row--empty">
                        <td colspan="8" class="ck-empty-state">{{ __('events.task.section_empty') }}</td>
                    </tr>
                    @endif
                    @foreach($mgmtCatTasks as $mgmtTask)
                    @php
                        $mgmtSortPri = ['normal'=>'1','important'=>'2','critical'=>'3'][$mgmtTask->priority ?? 'normal'] ?? '1';
                        $mgmtSortDl  = $mgmtTask->deadline_at ? $mgmtTask->deadline_at->format('Y-m-d H:i') : '9999-12-31 00:00';
                    @endphp
                    <tr class="ck-task-row{{ $mgmtTask->completed ? ' ck-task-row--done' : '' }}"
                        data-task-id="{{ $mgmtTask->id }}" data-section="{{ $mgmtCatId }}"
                        data-sort-name="{{ strtolower($mgmtTask->name) }}"
                        data-sort-priority="{{ $mgmtSortPri }}"
                        data-sort-deadline="{{ $mgmtSortDl }}">
                        <td class="ck-table__col--checkbox">
                            <input type="checkbox" class="ck-task-checkbox"
                                data-task-id="{{ $mgmtTask->id }}"
                                {{ $mgmtTask->completed ? 'checked' : '' }}>
                        </td>
                        <td class="ck-task-row__name">
                            {{ $mgmtTask->name }}
                            @if($mgmtTask->deadline_at === null)
                                <span class="ck-badge ck-badge--blue">{{ __('events.task.event_day_badge') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="ck-badge ck-badge--{{ $mgmtPriColors[$mgmtTask->priority] ?? 'gray' }}">
                                {{ $mgmtPriLabels[$mgmtTask->priority] ?? $mgmtTask->priority }}
                            </span>
                        </td>
                        <td class="ck-task-row__deadline">
                            @if($mgmtTask->deadline_at){{ $mgmtTask->deadline_at->format('d.m.Y H:i') }}@else–@endif
                        </td>
                        <td class="ck-task-row__notes">{{ $mgmtTask->notes ?: '–' }}</td>
                        <td class="ck-task-row__members">
                            @foreach($mgmtMMap[$mgmtTask->id] ?? [] as $mgmtEtm)
                            <span class="ck-task-member">
                                {{ $mgmtEtm['name'] }}
                                <button type="button"
                                        class="ck-etm-remove-btn"
                                        data-etm-id="{{ $mgmtEtm['id'] }}"
                                        data-member-id="{{ $mgmtEtm['member_id'] }}"
                                        data-member-name="{{ $mgmtEtm['name'] }}"
                                        data-sort-order="{{ $mgmtEtm['sort_order'] }}"
                                        title="Entfernen">×</button>
                            </span>
                            @endforeach
                        </td>
                        <td class="ck-table__col--actions">
                            <div class="ck-table__action-cell">
                                <x-ck-button variant="warning" size="icon"
                                    class="ck-task-edit-btn"
                                    data-task-id="{{ $mgmtTask->id }}"
                                    data-task-name="{{ $mgmtTask->name }}"
                                    data-task-priority="{{ $mgmtTask->priority ?? 'normal' }}"
                                    data-task-deadline="{{ $mgmtTask->deadline_at ? $mgmtTask->deadline_at->format('Y-m-d\TH:i') : '' }}"
                                    data-task-cat-id="{{ $mgmtCatId }}"
                                    title="{{ __('Edit') }}">
                                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/></svg>
                                </x-ck-button>
                                <x-ck-button variant="secondary" size="icon"
                                    class="ck-task-assign-btn"
                                    data-task-id="{{ $mgmtTask->id }}"
                                    data-task-name="{{ $mgmtTask->name }}"
                                    title="{{ __('events.task.assign_member') }}">
                                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                                </x-ck-button>
                                <x-ck-button variant="danger" size="icon"
                                    class="ck-task-remove-btn"
                                    data-task-id="{{ $mgmtTask->id }}"
                                    data-ck-confirm="{{ __('events.task.confirm_delete', ['name' => $mgmtTask->name]) }}"
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
</div>

@endforeach

@include('management::event-tasks-import-modal')

@push('scripts')
<script>
// Inject Management task library into the JS bridge for the new-task modal source dropdown.
if (window.CK_EventDetail) {
    window.CK_EventDetail.globalTasks = @json($mgmtGlobalTasksJs ?? []);
}
// Data bridge for the CSV import modal (events/import.js).
window.CK_Import = {
    routes: {
        import:   "{{ route('events.management.tasks.import', $event) }}",
        template: "{{ route('events.management.tasks.import.template', $event) }}",
    },
    // Existing event tasks for duplicate detection: [{name, category|null}, ...]
    existingTasks: @json($mgmtExistingTasksJs),
    // Calendar days of this event for the slot-task deadline picker:
    // [{value: 'YYYY-MM-DD', label: 'Mo., 12.08.2026'}, ...]
    // One entry = single date (shown as fixed text); multiple = select dropdown.
    eventDates: @json($mgmtEventDates),
};
</script>
@endpush