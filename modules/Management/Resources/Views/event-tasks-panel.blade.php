{{--
    Management-Hook: Task panel on the event detail page.
    Extension point: events.show.tasks-panel
    Registered by: ManagementServiceProvider

    Data provided by ManagementServiceProvider::composeEventTasksPanel() via View Composer.
    No @php blocks with DB queries in this view.

    Variables (injected by composer):
        $mgmtByCategory            → array<int|'allgemein', array{
                                         category: EventTaskCategory|null,
                                         tasks:    EventTask[],
                                         secDone:  int,
                                         secTotal: int,
                                         secColor: string,   // 'green' | 'orange' | 'gray'
                                     }>
                                     Key is category_id (int) or 'allgemein' for uncategorised tasks.
                                     "Allgemein" section always appears last.

        $mgmtEventCategories       → Collection<EventTaskCategory>  (for reference, not iterated here)

        $mgmtMemberMap             → array<event_task_id, list<array{id, member_id, name}>>
                                     Task-tab assignments only (time_from IS NULL).
                                     Slotted assignments (time_from set) live in the Einsatzplan tab.

        $mgmtAvailableGlobalTasks  → Collection<ManagementTask> not yet imported to this event
                                     (filtered by template_id — global tasks already imported are excluded)

        $mgmtPriorityColors        → array<string, string>   e.g. ['normal' => 'gray', ...]
        $mgmtPriorityLabels        → array<string, string>   e.g. ['normal' => 'Normal', ...]

    IMPORTANT: No anonymous Blade components (<x-ck-*>) anywhere in this file.
    Laravel 13.17 / PHP 8.4 Blade compiler generates broken PHP (extra endforeach tokens)
    whenever anonymous components interact with @foreach directives — in EITHER direction:
      - <x-ck-*> inside @foreach, OR
      - @foreach inside <x-ck-*>
    Plain HTML equivalents are used throughout.
--}}

{{-- ── Action bar ────────────────────────────────────────────────────────────── --}}
<div class="ck-pane-actions">
    <div class="ck-header-dropdown">
        {{-- Plain button — no <x-ck-button> to avoid Blade compiler bug --}}
        <button type="button"
            class="ck-btn ck-btn--success ck-header-dropdown__toggle"
            onclick="this.closest('.ck-header-dropdown').classList.toggle('ck-header-dropdown--open')">
            {{ __('events.task.add_header_btn') }} ▾
        </button>
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

{{-- ── Task sections: one collapsible block per category, "Allgemein" last ──────── --}}
@foreach($mgmtByCategory as $mgmtCatKey => $mgmtCatSection)

@php
    $mgmtCat      = $mgmtCatSection['category'];
    $mgmtCatId    = $mgmtCat ? $mgmtCat->id : null;
    $mgmtCatSlug  = $mgmtCat ? Str::slug($mgmtCat->name) : 'allgemein';
    $mgmtCatColor = $mgmtCat ? $mgmtCat->color : null;
@endphp

<details class="ck-event-section{{ $mgmtCatColor ? ' ck-event-section--color' : '' }}"
         open
         data-section="{{ $mgmtCatSlug }}"
         data-cat-id="{{ $mgmtCatId ?? '' }}">

    <summary class="ck-event-section__summary">

        @if($mgmtCatColor)
        <span class="ck-event-section__swatch ck-swatch--{{ $mgmtCatColor }}"
              aria-hidden="true"></span>
        @endif

        <span class="ck-event-section__title">
            {{ $mgmtCat ? $mgmtCat->name : __('events.task.section_general') }}
        </span>

        <span class="ck-badge ck-badge--{{ $mgmtCatSection['secColor'] }} ck-event-section__badge"
              data-section-badge="{{ $mgmtCatSlug }}">
            {{ $mgmtCatSection['secDone'] }}/{{ $mgmtCatSection['secTotal'] }}
        </span>

        @if($mgmtCat)
        <div class="ck-event-section__actions">
            <button type="button"
                class="ck-icon-btn ck-icon-btn--secondary ck-cat-rename-btn"
                data-cat-id="{{ $mgmtCat->id }}"
                data-cat-name="{{ $mgmtCat->name }}"
                data-cat-color="{{ $mgmtCatColor ?? '' }}"
                title="{{ __('events.cat.rename') }}"
                aria-label="{{ __('events.cat.rename') }}">✏</button>

            <button type="button"
                class="ck-icon-btn ck-icon-btn--danger ck-cat-delete-btn"
                data-cat-id="{{ $mgmtCat->id }}"
                data-cat-name="{{ $mgmtCat->name }}"
                data-task-count="{{ $mgmtCatSection['secTotal'] }}"
                title="{{ __('events.cat.delete') }}"
                aria-label="{{ __('events.cat.delete') }}">🗑</button>
        </div>
        @endif

        <button type="button"
            class="ck-icon-btn ck-icon-btn--success ck-event-section__add-task-btn ck-no-details-toggle"
            data-default-cat-id="{{ $mgmtCatId ?? '' }}"
            title="{{ __('events.task.add_task') }}"
            aria-label="{{ __('events.task.add_task') }}">+</button>

    </summary>

    <div class="ck-event-section__body">

        <table class="ck-table ck-task-table">
            <thead>
                <tr>
                    <th class="ck-table__col--drag"></th>
                    <th class="ck-table__col--checkbox"></th>
                    <th>{{ __('events.task.col_task') }}</th>
                    <th>{{ __('events.task.col_priority') }}</th>
                    <th>{{ __('events.task.col_deadline') }}</th>
                    <th>{{ __('events.task.col_notes') }}</th>
                    <th>{{ __('events.function.col_responsible') }}</th>
                    <th class="ck-table__col--actions"></th>
                </tr>
            </thead>
            <tbody class="ck-task-sortable"
                   data-cat-id="{{ $mgmtCatId ?? 'allgemein' }}">

                @if(empty($mgmtCatSection['tasks']))
                <tr class="ck-task-row--empty">
                    <td colspan="8" class="ck-table__empty-cell">
                        {{ __('events.task.section_empty') }}
                    </td>
                </tr>
                @endif

                @foreach($mgmtCatSection['tasks'] as $mgmtTask)
                <tr class="ck-task-row{{ $mgmtTask->completed ? ' ck-task-row--done' : '' }}"
                    data-task-id="{{ $mgmtTask->id }}"
                    data-section="{{ $mgmtCatSlug }}">

                    <td class="ck-table__col--drag">
                        <span class="ck-task-drag-handle"
                              title="{{ __('events.task.drag_hint') }}"
                              aria-hidden="true">⠿</span>
                    </td>

                    <td class="ck-table__col--checkbox">
                        <input type="checkbox"
                            class="ck-task-checkbox"
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
                        <span class="ck-badge ck-badge--{{ $mgmtPriorityColors[$mgmtTask->priority] ?? 'gray' }}">
                            {{ $mgmtPriorityLabels[$mgmtTask->priority] ?? $mgmtTask->priority }}
                        </span>
                    </td>

                    <td class="ck-task-row__deadline">
                        @if($mgmtTask->deadline_at)
                            {{ $mgmtTask->deadline_at->format('d.m.Y H:i') }}
                        @else
                            –
                        @endif
                    </td>

                    <td class="ck-task-row__notes">{{ $mgmtTask->notes ?: '–' }}</td>

                    <!-- assigned members -->
                    <td class="ck-task-row__members">
                        @foreach($mgmtMemberMap[$mgmtTask->id] ?? [] as $mgmtEtm)
                        <span class="ck-task-member">
                            {{ $mgmtEtm['name'] }}
                            <button type="button"
                                class="ck-etm-remove-btn"
                                data-etm-id="{{ $mgmtEtm['id'] }}"
                                data-member-id="{{ $mgmtEtm['member_id'] }}"
                                data-member-name="{{ $mgmtEtm['name'] }}"
                                aria-label="{{ __('Remove') }}">×</button>
                        </span>
                        @endforeach

                        <button type="button"
                            class="ck-btn ck-btn--secondary ck-btn--sm ck-task-assign-btn"
                            data-task-id="{{ $mgmtTask->id }}"
                            data-task-name="{{ $mgmtTask->name }}">
                            {{ __('events.task.assign_member_short') }}
                        </button>
                    </td>

                    <!-- row actions -->
                    <td class="ck-table__col--actions">
                        <button type="button"
                            class="ck-icon-btn ck-icon-btn--secondary ck-task-edit-btn"
                            data-task-id="{{ $mgmtTask->id }}"
                            data-task-name="{{ $mgmtTask->name }}"
                            data-task-priority="{{ $mgmtTask->priority ?? 'normal' }}"
                            data-task-deadline="{{ $mgmtTask->deadline_at ? $mgmtTask->deadline_at->format('Y-m-d H:i') : '' }}"
                            data-task-cat-id="{{ $mgmtTask->category_id ?? '' }}"
                            title="{{ __('Edit') }}"
                            aria-label="{{ __('Edit') }}">✏</button>
                        <button type="button"
                            class="ck-icon-btn ck-icon-btn--danger ck-task-remove-btn"
                            data-task-id="{{ $mgmtTask->id }}"
                            data-ck-confirm="{{ __('events.task.confirm_delete', ['name' => $mgmtTask->name]) }}"
                            title="{{ __('Delete') }}"
                            aria-label="{{ __('Delete') }}">🗑</button>
                    </td>
                </tr>
                @endforeach

            </tbody>
        </table>

    </div>
</details>

@endforeach

{{-- ── Add section button — always below Allgemein ─────────────────────────── --}}
<button type="button"
    class="ck-btn ck-btn--secondary ck-btn--sm ck-add-section-btn"
    onclick="ckModalOpen('newCatModal')">
    + {{ __('events.cat.add_section') }}
</button>

{{-- ── Empty state (no categories at all — should not occur after Allgemein fix) --}}
@if(count($mgmtByCategory) === 0)
<div class="ck-card">
    <p class="ck-text-muted">{{ __('events.task.empty') }}</p>
</div>
@endif

{{-- ── Import from global task library ───────────────────────────────────────── --}}
@if($mgmtAvailableGlobalTasks->isNotEmpty())

@php
    // Group by global category name.
    $mgmtImportGrouped = [];
    foreach ($mgmtAvailableGlobalTasks as $mgmtImportTask) {
        $mgmtCatRef   = $mgmtImportTask->category;
        $mgmtGroupKey = ($mgmtCatRef !== null ? $mgmtCatRef->name : null)
                        ?? __('events.task.import_no_category');
        $mgmtImportGrouped[$mgmtGroupKey][] = $mgmtImportTask;
    }
    ksort($mgmtImportGrouped);
@endphp

{{-- Plain div instead of <x-ck-card> — avoids Blade compiler bug with @foreach inside components --}}
<div class="ck-card ck-no-print ck-mt-4">
    <div class="ck-card__header">
        <span class="ck-card__header-title">{{ __('events.task.import_card_header') }}</span>
    </div>
    <div class="ck-card__body">
        <div class="ck-add-task">
            <select id="importTaskSelect" class="ck-form-select">
                <option value="">{{ __('events.task.import_placeholder') }}</option>
                @foreach($mgmtImportGrouped as $mgmtGroupLabel => $mgmtGroupTasks)
                <optgroup label="{{ $mgmtGroupLabel }}">
                    @foreach($mgmtGroupTasks as $mgmtImportT)
                    <option value="{{ $mgmtImportT->id }}"
                            data-priority="{{ $mgmtImportT->priority ?? 'normal' }}">
                        {{ $mgmtImportT->name }}
                    </option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
            {{-- Plain button — no <x-ck-button> to avoid Blade compiler bug --}}
            <button type="button" class="ck-btn ck-btn--primary" id="importTaskBtn">
                {{ __('events.task.import_btn') }}
            </button>
        </div>
    </div>
</div>

@endif