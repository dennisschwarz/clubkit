@extends('core::admin.layout')
@section('title', $event->title)
@section('content')

@php
    /**
     * Determine which management sub-tabs are visible.
     *
     * A tab is shown only when BOTH conditions are true:
     *   1. The Management module is installed ($managementInstalled)
     *   2. The per-event flag is enabled on this event
     *
     * This means: if Management is not installed, no management tabs appear
     * regardless of the flag values. If Management is installed but the user
     * disabled the feature for this event, the tab is hidden.
     */
    $showTasks     = $managementInstalled && $event->tasks_enabled;
    $showSlots     = $managementInstalled && $event->slots_enabled;
    $showFunctions = $managementInstalled && $event->functions_enabled;
@endphp

{{-- ── Page action bar ─────────────────────────────────────────────────────── --}}
<div class="ck-page-header">
    <div class="ck-page-header__left">
        <a href="{{ route('events.index') }}" class="ck-btn ck-btn--warning">
            ← {{ __('events.back') }}
        </a>
    </div>
    <div class="ck-page-header__actions">

        {{-- Overview: Print + Delete. Active by default (overview is the initial active pane). --}}
        <div id="ckEvtAction-overview" class="ck-event-tab-action ck-event-tab-action--active">
            <x-ck-button variant="secondary" type="button" onclick="window.print()">
                🖨 {{ __('Print') }}
            </x-ck-button>
            <x-ck-button variant="danger"
                :confirm="__('events.confirm.delete_event', ['name' => $event->title])"
                :form="'deleteEventForm'">
                {{ __('Delete') }}
            </x-ck-button>
        </div>

        {{-- Tasks + Einsatzplan: CSV Import + New Category. --}}
        {{-- data-ck-tab-actions lists the tab IDs that activate this group (space-separated). --}}
        @if($showTasks || $showSlots)
        @php
            $mgmtActionTabs = trim(($showTasks ? 'tasks' : '') . ' ' . ($showSlots ? 'slots' : ''));
        @endphp
        <div class="ck-event-tab-action" data-ck-tab-actions="{{ $mgmtActionTabs }}">
            <x-ck-button variant="secondary" onclick="ckModalOpen('ckImportModal')">
                {{ __('events.import.open_btn') }}
            </x-ck-button>
            <x-ck-button variant="success" onclick="ckModalOpen('newCatModal')">
                {{ __('events.cat.add_category') }}
            </x-ck-button>
        </div>
        @endif

        {{-- Functions tab: "+ Eigene Funktion" (ad-hoc event-scoped function). --}}
        {{-- newEventFuncModal is rendered in event-functions-panel.blade.php   --}}
        {{-- and teleported to #ck-modal-root by app.js on DOMContentLoaded.    --}}
        @if($showFunctions)
        <div class="ck-event-tab-action" data-ck-tab-actions="functions">
            <x-ck-button variant="success" onclick="ckModalOpen('newEventFuncModal')">
                {{ __('events.function.new_event_btn') }}
            </x-ck-button>
        </div>
        @endif

    </div>
</div>

<form id="deleteEventForm" method="POST" action="{{ route('events.destroy', $event) }}" class="is-hidden">
    @csrf
    @method('DELETE')
</form>

@if(session('success'))
<div class="ck-alert ck-alert--success">{{ session('success') }}</div>
@endif

{{-- ── Tab bar ────────────────────────────────────────────────────────────── --}}
{{--
    Tabs are rendered conditionally:
    - Overview: always visible
    - Tasks / Slots / Functions: only when Management is installed AND the
      per-event flag ($event->tasks_enabled etc.) is true.
    Tab switching is handled by ckEvtTab() in events-detail.js.
--}}
<div class="ck-local-tabs">
    <button class="ck-local-tab ck-local-tab--active" type="button"
            onclick="ckEvtTab('overview', this)">
        <span class="ck-local-tab__dot ck-local-tab__dot--blue"></span>
        📊 {{ __('events.tab.overview') }}
    </button>

    @if($showTasks)
    <button class="ck-local-tab" type="button"
            onclick="ckEvtTab('tasks', this)">
        <span class="ck-local-tab__dot ck-local-tab__dot--teal"></span>
        📋 {{ __('events.tab.tasks') }}
    </button>
    @endif

    @if($showSlots)
    <button class="ck-local-tab" type="button"
            onclick="ckEvtTab('slots', this)">
        <span class="ck-local-tab__dot ck-local-tab__dot--amber"></span>
        🗓️ {{ __('events.tab.slots') }}
    </button>
    @endif

    @if($showFunctions)
    <button class="ck-local-tab" type="button"
            onclick="ckEvtTab('functions', this)">
        <span class="ck-local-tab__dot ck-local-tab__dot--purple"></span>
        ⚙️ {{ __('events.tab.functions') }}
    </button>
    @endif
</div>
{{-- ── Pane: Overview ──────────────────────────────────────────────────────── --}}
<div class="ck-local-section ck-local-section--active" id="ckEvtPane-overview">
{{-- Plain div — <x-ck-card> here wraps @ckHook (→ foreach) + nested <x-ck-button>, triggering --}}
{{-- Blade 13.17 compiler bug: anonymous component + foreach inside = extra endforeach token. --}}
<div class="ck-card ck-card--accent-blue ck-event-info ck-no-print">
    <div class="ck-card__header">
        <span class="ck-card__header-title">📅 {{ __('events.detail.event_details') }}</span>
    </div>
    <div class="ck-card__body">

    {{-- Hero grid: 2-col by default; single column when functions are disabled --}}
    <div class="ck-event-hero {{ $showFunctions ? '' : 'ck-event-hero--full' }}">

        {{-- Left column: date tile + time / location / description --}}
        <div class="ck-event-hero__left">

            <div class="ck-event-hero__head">
                <div class="ck-event-hero__date">
                    <span class="ck-event-hero__month">{{ $event->starts_at->translatedFormat('M') }}</span>
                    <span class="ck-event-hero__day">{{ $event->starts_at->format('j') }}</span>
                    <span class="ck-event-hero__weekday">{{ $event->starts_at->translatedFormat('l') }}</span>
                </div>
                <div class="ck-event-hero__meta">
                    <span class="ck-event-hero__meta-item">
                        ⏱ {{ $event->starts_at->format('H:i') }}{{ $event->ends_at ? ' – ' . $event->ends_at->format('H:i') . ' ' . __('events.time_suffix') : ' ' . __('events.time_suffix') }}
                    </span>
                    @if($event->location)
                    <span class="ck-event-hero__meta-item">📍 {{ $event->location }}</span>
                    @endif
                </div>
                {{-- Last child of __head — absolute top:0; right:0 via .ck-event-hero__edit-icon --}}
                <x-ck-button variant="warning" size="icon"
                             class="ck-event-hero__edit-icon"
                             onclick="ckModalOpen('editEventModal')"
                             title="{{ __('Edit') }}">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                    </svg>
                </x-ck-button>
            </div>

            @if($event->description)
            <div class="ck-event-hero__body">
                <div class="ck-event-hero__body-label">{{ __('events.detail.description') }}</div>
                <div class="ck-event-hero__body-text">{{ $event->description }}</div>
            </div>
            @endif

            @if($event->notes)
            <div class="ck-event-hero__body">
                <div class="ck-event-hero__body-label">{{ __('events.detail.notes') }}</div>
                <div class="ck-event-hero__body-text">{{ $event->notes }}</div>
            </div>
            @endif

        </div>

        {{-- Right column: Vereinsfunktionen summary (rendered by Management hook) --}}
        @if($showFunctions)
        <div class="ck-event-hero__right">
            @ckHook('events.show.hero-right')
        </div>
        @endif

    </div>
    </div>{{-- /.ck-card__body --}}
</div>{{-- /.ck-card ck-event-info --}}

{{--
    Extension point: events.show.overview-panel
    Registered by: ManagementServiceProvider (event-overview-panel.blade.php)
    Renders: 4 KPI tiles + Fortschritt nach Kategorie progress bars.
--}}
@ckHook('events.show.overview-panel')

{{-- Custom fields (CustomFields module) --}}
@if(!empty($eventCfDefs))
{{-- Plain div instead of <x-ck-card> — avoids Blade compiler bug: @foreach inside anonymous component --}}
{{-- triggers extra endforeach tokens in compiled PHP when ck-card.blade.php contains a @php block. --}}
<div class="ck-card ck-card--accent-gray">
    <div class="ck-card__header">
        <span class="ck-card__header-title">{{ __('events.detail.further_info') }}</span>
    </div>
    <div class="ck-card__body">
        <dl class="ck-event-meta">
            @foreach($eventCfDefs as $def)
            <div class="ck-event-meta__row">
                <dt>{{ $def->label }}</dt>
                <dd>{{ $eventCfValues[$def->id] ?? '–' }}</dd>
            </div>
            @endforeach
        </dl>
    </div>
</div>
@endif
</div>
{{-- ── Pane: Tasks ─────────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.tasks-panel
Registered by: ManagementServiceProvider (event-tasks-panel.blade.php)
Renders: collapsible task sections by category + add-task select.
--}}
<div class="ck-local-section" id="ckEvtPane-tasks"> @ckHook('events.show.tasks-panel') </div>
{{-- ── Pane: Slots / Schedule ──────────────────────────────────────────────── --}}
{{--
Extension point: events.show.einsatzplan-panel
Registered by: ManagementServiceProvider (event-einsatzplan-panel.blade.php)
Renders: event-day tasks with time-slot ETMs + add-slot form.
--}}
<div class="ck-local-section" id="ckEvtPane-slots"> @ckHook('events.show.slots-panel') </div>
{{-- ── Pane: Functions ─────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.functions-panel
Registered by: ManagementServiceProvider (event-functions-panel.blade.php)
Renders: management-functions cards with assigned members.
--}}
<div class="ck-local-section" id="ckEvtPane-functions"> @ckHook('events.show.functions-panel') </div>
{{-- ── Edit modal ───────────────────────────────────────────────────────────── --}}
{{--
    editEventModal  — PATCH  /events/{event}               (standard form submit)
    newTaskModal    — POST   /events/{event}/tasks          (AJAX, wired in events-detail.js)
    newCatModal     — POST   /management/task-categories    (AJAX, wired in events-detail.js)
    slotModal       — POST   /events/{event}/slots          (AJAX, wired in events-detail.js)
    newFuncModal    — POST   /events/{event}/functions       (AJAX, assigns existing ManagementFunction)
    newEventFuncModal — POST /events/{event}/event-functions (AJAX, creates ad-hoc function; rendered in event-functions-panel.blade.php)
--}}
<x-ck-modal id="editEventModal" title="{{ __('events.modal.edit_title') }}" size="md">
<form method="POST" action="{{ route('events.update', $event) }}">
@csrf
@method('PATCH')

<x-ck-field label="{{ __('events.field.title') }}" name="title"
    :value="old('title', $event->title)" :required="true" />

<div class="ck-form-grid ck-form-grid--2 ck-mt-4">
    <x-ck-field type="text" label="{{ __('events.field.starts_at') }}" name="starts_at"
        :value="old('starts_at', $event->starts_at->format('Y-m-d H:i'))"
        :required="true" data-ck-datetime="1" />
    <x-ck-field type="text" label="{{ __('events.field.ends_at') }}" name="ends_at"
        :value="old('ends_at', $event->ends_at?->format('Y-m-d H:i'))"
        data-ck-datetime="1" />
</div>

<x-ck-field label="{{ __('events.field.location') }}" name="location"
    class="ck-mt-4"
    :value="old('location', $event->location)" />

<x-ck-field type="textarea" label="{{ __('events.field.description') }}" name="description"
    class="ck-mt-4" rows="2"
    :value="old('description', $event->description)" />

<details class="ck-mt-3"{{ old('notes', $event->notes) ? ' open' : '' }}>
    <summary class="ck-text-muted ck-details-summary">
        {{ __('events.field.notes_toggle') }}
    </summary>
    <div class="ck-mt-2">
        <x-ck-field type="textarea" label="{{ __('events.field.notes') }}" name="notes"
            rows="2" :value="old('notes', $event->notes)" />
    </div>
</details>

{{-- Feature flags — only rendered when Management is installed --}}
@if($managementInstalled)
<div class="ck-event-flags-section">
    <div class="ck-event-flags-section__label">{{ __('events.field.active_features') }}</div>
    <div class="ck-form-grid ck-form-grid--3">
        <label class="ck-field__checkbox">
            <input type="checkbox" name="tasks_enabled" value="1"
                   {{ old('tasks_enabled', $event->tasks_enabled) ? 'checked' : '' }}>
            📋 {{ __('events.feature.tasks') }}
        </label>
        <label class="ck-field__checkbox">
            <input type="checkbox" name="functions_enabled" value="1"
                   {{ old('functions_enabled', $event->functions_enabled) ? 'checked' : '' }}>
            ⚙️ {{ __('events.feature.functions') }}
        </label>
        <label class="ck-field__checkbox">
            <input type="checkbox" name="slots_enabled" value="1"
                   {{ old('slots_enabled', $event->slots_enabled) ? 'checked' : '' }}>
            🗓️ {{ __('events.feature.slots') }}
        </label>
    </div>
</div>
@endif

<div class="ck-form-actions">
    <x-ck-button variant="primary" type="submit">{{ __('Save') }}</x-ck-button>
    <x-ck-button variant="secondary" type="button"
                 onclick="ckModalClose(null, 'editEventModal')">{{ __('Cancel') }}</x-ck-button>
</div>
</form>
</x-ck-modal>

{{-- ── New task modal (Tab 2: Aufgaben) ────────────────────────────────────── --}}
{{--
    Fields per concept: Bezeichnung (required) | Kategorie (optional select, populated via JS
    from CK_EventDetail.categories) | Priorität + Deadline (2 cols) | Verantwortliche (optional,
    populated via JS from CK_EventDetail.members).
    Submit: AJAX POST, wired in events-detail.js.
    Categories injected via ManagementServiceProvider → events.show.page.scripts hook.
--}}
<x-ck-modal id="newTaskModal" :title="__('events.task.modal_title')" size="md">

    {{-- Source: pick an existing ManagementTask from the library, or create a new one.
         Options are injected by events-detail.js from CK_EventDetail.globalTasks.
         The first option is always "Neue Aufgabe erstellen" (value="new").
         #newTaskSourceGroup is hidden in edit mode (events-detail.js). --}}
    <div id="newTaskSourceGroup">
        <x-ck-field
            type="select"
            :label="__('events.task.field_source')"
            name="new_task_source"
            id="newTaskSource"
            :options="['new' => __('events.task.source_new')]" />
    </div>

    {{-- Name field: only visible when source = "new" --}}
    <div id="newTaskNameGroup">
        <x-ck-field
            :label="__('events.task.field_name')"
            name="new_task_name"
            id="newTaskName" />
    </div>

    {{-- Priority + Deadline: priority only for new tasks; deadline always settable --}}
    <div class="ck-form-grid ck-form-grid--2">
        <div id="newTaskPriorityGroup">
            <x-ck-field
                type="select"
                :label="__('events.task.field_priority')"
                name="new_task_priority"
                id="newTaskPriority"
                :options="[
                    'normal'    => __('management.priority.normal'),
                    'important' => __('management.priority.important'),
                    'critical'  => __('management.priority.critical'),
                ]" />
        </div>
        <x-ck-field
            type="text"
            :label="__('events.task.field_deadline')"
            name="new_task_deadline"
            id="newTaskDeadline"
            data-ck-datetime="1" />
    </div>

    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="newTaskSubmitBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'newTaskModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── New category modal (Tab 2: Aufgaben → Dropdown Option 2) ───────────── --}}
<x-ck-modal id="newCatModal" :title="__('events.cat.modal_title')" size="sm">
    <x-ck-field
        :label="__('events.cat.field_name')"
        name="new_cat_name"
        id="newCatName"
        :required="true" />
    <div class="ck-field__group ck-mt-3">
        <label class="ck-field__label">{{ __('events.cat.field_color') }}</label>
        <div class="ck-color-picker" id="newCatColorPicker">
            @foreach($categoryColors as $ckColorKey => $ckColorLabel)
            <label class="ck-color-swatch{{ $ckColorKey === '' ? ' ck-color-swatch--selected' : '' }}" title="{{ $ckColorLabel }}">
                <input type="radio" name="new_cat_color" id="newCatColor"
                       value="{{ $ckColorKey }}" {{ $ckColorKey === '' ? 'checked' : '' }}>
                <span class="ck-color-swatch__dot ck-color-swatch__dot--{{ $ckColorKey ?: 'default' }}"></span>
            </label>
            @endforeach
        </div>
    </div>
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="newCatSubmitBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'newCatModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── Slot modal (Tab 3: Einsatzplan) ─────────────────────────────────────── --}}
{{--
    Fields per concept: Aufgabe (only Eventtag tasks, from CK_EventDetail.einsatzplanTasks) |
    Mitglied | Von + Bis nebeneinander | Validierung Von < Bis in events-detail.js.
    Submit: AJAX POST to CK_EventDetail.routes.slotsBase, wired in events-detail.js.
--}}
<x-ck-modal id="slotModal" :title="__('events.slot.modal_title')" size="sm">
    <x-ck-field
        type="select"
        :label="__('events.slot.field_task')"
        name="slot_task_id"
        id="slotModalTaskId"
        :options="[]" />
    {{-- Note: options populated from CK_EventDetail.einsatzplanTasks by events-detail.js --}}
    <x-ck-field
        type="select"
        :label="__('events.slot.field_member')"
        name="slot_member_id"
        id="slotModalMemberId"
        :options="[]" />
    {{-- Note: options populated from CK_EventDetail.members by events-detail.js --}}
    <div class="ck-form-grid ck-form-grid--2">
        <x-ck-field
            type="time"
            :label="__('events.slot.from')"
            name="slot_time_from"
            id="slotModalTimeFrom"
            :required="true" />
        <x-ck-field
            type="time"
            :label="__('events.slot.to')"
            name="slot_time_to"
            id="slotModalTimeTo"
            :required="true" />
    </div>
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="slotModalSubmitBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'slotModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── Add function modal (Tab 4: Funktionen) ────────────────────────────────── --}}
{{--
    Assigns an existing global management function to this event.
    New functions are created in Organisation → Funktionen.
    Options are populated by events-detail.js from CK_EventDetail.availableFunctions.
    Submit: AJAX POST to CK_EventDetail.routes.funcAddBase (events/{event}/functions).
--}}
<x-ck-modal id="newFuncModal" :title="__('events.function.modal_title')" size="sm">
    <x-ck-field
        type="select"
        :label="__('events.function.field_select')"
        name="new_func_id"
        id="newFuncSelect"
        :options="[]"
        :required="true" />
    {{-- Options are populated by events-detail.js from CK_EventDetail.availableFunctions --}}
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="newFuncSubmitBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'newFuncModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── Task member assign modal (Tab 2: Aufgaben → Verantwortliche zuweisen) ─── --}}
{{--
    Dual-listbox: available members on the left, assigned members on the right.
    Opened by .ck-task-assign-btn click in events-detail.js.
    Submit: batch POST /members (add) + DELETE /members/{id} (remove).
--}}
<x-ck-modal id="taskAssignModal" :title="__('events.task.assign_member')" size="lg">
    <p id="taskAssignLabel" class="ck-text-muted ck-mb-4"></p>
    <div class="ck-dual-listbox">
        <div class="ck-dual-listbox__col">
            <span class="ck-dual-listbox__label">{{ __('events.assign.available') }}</span>
            {{-- SortableJS source list: drag items → assigned list to add --}}
            <ul id="taskAssignAvailableList" class="ck-assign-list"></ul>
        </div>
        <div class="ck-dual-listbox__col">
            <span class="ck-dual-listbox__label">{{ __('events.assign.assigned') }}</span>
            {{-- SortableJS target list: sortable, accepts drops from available --}}
            <ul id="taskAssignSortList" class="ck-assign-list ck-assign-list--assigned"></ul>
        </div>
    </div>
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="taskAssignDoneBtn">
            {{ __('Done') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── Rename category modal (tasks tab: ✏ button per section header) ────────── --}}
{{--
    Opened by .ck-cat-rename-btn click in events-detail.js.
    Submit: AJAX PATCH /events/{event}/task-categories/{id} { name }.
    The hidden renameCatId value is set by JS before opening the modal.
--}}
<x-ck-modal id="renameCatModal" :title="__('events.cat.rename_modal_title')" size="sm">
    <x-ck-field
        :label="__('events.cat.field_name')"
        name="rename_cat_name"
        id="renameCatName"
        :required="true" />
    <div class="ck-field__group ck-mt-3">
        <label class="ck-field__label">{{ __('events.cat.field_color') }}</label>
        <div class="ck-color-picker" id="renameCatColorPicker">
            @foreach($categoryColors as $ckRnColorKey => $ckRnColorLabel)
            <label class="ck-color-swatch{{ $ckRnColorKey === '' ? ' ck-color-swatch--selected' : '' }}" title="{{ $ckRnColorLabel }}">
                <input type="radio" name="rename_cat_color"
                       value="{{ $ckRnColorKey }}" {{ $ckRnColorKey === '' ? 'checked' : '' }}>
                <span class="ck-color-swatch__dot ck-color-swatch__dot--{{ $ckRnColorKey ?: 'default' }}"></span>
            </label>
            @endforeach
        </div>
    </div>
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="renameCatSubmitBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'renameCatModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

@endsection
@push('scripts')
<script>
    /**
     * CK_EventDetail — Data bridge for events-detail.js.
     * tasks is populated by ManagementServiceProvider via events.show.page.scripts hook.
     */
    window.CK_EventDetail = {
        eventId: {{ $event->id }},
        csrf:    '{{ csrf_token() }}',
        routes:  {
            tasksBase:      "{{ url('events/' . $event->id . '/tasks') }}",
            slotsBase:      "{{ url('events/' . $event->id . '/slots') }}",
            membersBase:    "{{ url('events/' . $event->id . '/members') }}",
            mgmtTasksBase:  "{{ url('management/tasks') }}",
            categoriesBase: "{{ url('events/' . $event->id . '/task-categories') }}",
            funcAddBase:       "{{ url('events/' . $event->id . '/functions') }}",
            funcAssignBase:    "{{ url('events/' . $event->id . '/functions') }}",
            funcPanelFragment: "{{ url('events/' . $event->id . '/functions/panel-fragment') }}",
            eventFuncBase:     "{{ url('events/' . $event->id . '/event-functions') }}",
            teamsBase:      "{{ url('events/' . $event->id . '/teams') }}"
        },
        tasks:   {},
        members: @json($allMembersJs)
    };
</script>
{{-- Management injects CK_EventDetail.tasks + CK_EventDetail.einsatzplanTasks here. --}}
@ckHook('events.show.page.scripts')
@vite(['resources/js/modules/events-detail.js'])
@endpush