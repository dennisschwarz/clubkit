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
        <x-ck-button variant="secondary" type="button" onclick="window.print()">
            🖨 {{ __('Print') }}
        </x-ck-button>
        <x-ck-button variant="danger"
            :confirm="'Delete event \'' . $event->title . '\'?'"
            :form="'deleteEventForm'">
            {{ __('Delete') }}
        </x-ck-button>
    </div>
</div>

<form id="deleteEventForm" method="POST" action="{{ route('events.destroy', $event) }}" class="is-hidden">
    @csrf
    @method('DELETE')
</form>

@if(session('success'))
<div class="ck-alert ck-alert--success">{{ session('success') }}</div>
@endif

{{-- ── Tab bar (pill-style, Design System v2) ──────────────────────────────── --}}
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
{{-- ── Pane: Übersicht ─────────────────────────────────────────────────────── --}}
<div class="ck-local-section ck-local-section--active" id="ckEvtPane-overview">
<x-ck-card class="ck-event-info ck-no-print" accent="blue">
    <x-slot:header>
        <span class="ck-card__header-title">📅 {{ __('events.detail.event_details') }}</span>
        <button type="button" class="ck-btn ck-btn--secondary ck-btn--sm"
                onclick="ckModalOpen('editEventModal')">
            ✏ {{ __('Edit') }}
        </button>
    </x-slot:header>

    {{-- Hero grid: split when functions are enabled, single column otherwise --}}
    <div class="ck-event-hero {{ $showFunctions ? 'ck-event-hero--split' : 'ck-event-hero--full' }}">

        {{-- Left column: date tile + time / location / description --}}
        <div class="ck-event-hero__left">

            {{-- Date tile + time/location in a horizontal row --}}
            <div class="ck-event-hero__head">
                <div class="ck-event-hero__date">
                    <span class="ck-event-hero__month">{{ $event->starts_at->translatedFormat('M') }}</span>
                    <span class="ck-event-hero__day">{{ $event->starts_at->format('j') }}</span>
                    <span class="ck-event-hero__weekday">{{ $event->starts_at->translatedFormat('l') }}</span>
                </div>
                <div class="ck-event-hero__meta">
                    <span class="ck-event-hero__meta-item">
                        ⏱ {{ $event->starts_at->format('H:i') }}{{ $event->ends_at ? ' – ' . $event->ends_at->format('H:i') . ' Uhr' : ' Uhr' }}
                    </span>
                    @if($event->location)
                    <span class="ck-event-hero__meta-item">📍 {{ $event->location }}</span>
                    @endif
                </div>
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
</x-ck-card>

{{--
    Extension point: events.show.overview-panel
    Registered by: ManagementServiceProvider (event-overview-panel.blade.php)
    Renders: 4 KPI tiles + Fortschritt nach Kategorie progress bars.
--}}
@ckHook('events.show.overview-panel')

{{-- Custom fields (CustomFields module) --}}
@if(!empty($eventCfDefs))
<x-ck-card accent="gray">
    <x-slot:header><span class="ck-card__header-title">{{ __('events.detail.further_info') }}</span></x-slot:header>
    <dl class="ck-event-meta">
        @foreach($eventCfDefs as $def)
        <div class="ck-event-meta__row">
            <dt>{{ $def->label }}</dt>
            <dd>{{ $eventCfValues[$def->id] ?? '–' }}</dd>
        </div>
        @endforeach
    </dl>
</x-ck-card>
@endif
</div>
{{-- ── Pane: Aufgaben ───────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.tasks-panel
Registered by: ManagementServiceProvider (event-tasks-panel.blade.php)
Renders: collapsible task sections by category + add-task select.
--}}
<div class="ck-local-section" id="ckEvtPane-tasks"> @ckHook('events.show.tasks-panel') </div>
{{-- ── Pane: Einsatzplan ────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.einsatzplan-panel
Registered by: ManagementServiceProvider (event-einsatzplan-panel.blade.php)
Renders: event-day tasks with time-slot ETMs + add-slot form.
--}}
<div class="ck-local-section" id="ckEvtPane-slots"> @ckHook('events.show.slots-panel') </div>
{{-- ── Pane: Funktionen ─────────────────────────────────────────────────────── --}}
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
    newFuncModal    — POST   /events/{event}/functions      (AJAX, assigns existing function to event)
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
    <summary class="ck-text-muted" style="cursor:pointer;font-size:var(--ck-font-sm);user-select:none;">
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
    <x-ck-field
        :label="__('events.task.field_name')"
        name="new_task_name"
        id="newTaskName"
        :required="true" />
    <x-ck-field
        type="select"
        :label="__('events.task.field_category')"
        name="new_task_category_id"
        id="newTaskCategoryId"
        :options="[]" />
    {{-- Note: options populated from CK_EventDetail.categories by events-detail.js --}}
    <div class="ck-form-grid ck-form-grid--2">
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
        <x-ck-field
            type="text"
            :label="__('events.task.field_deadline')"
            name="new_task_deadline"
            id="newTaskDeadline"
            data-ck-datetime="1" />
    </div>
    <x-ck-field
        type="select"
        :label="__('events.task.field_responsible')"
        name="new_task_member_id"
        id="newTaskMemberId"
        :options="[]" />
    {{-- Note: options populated from CK_EventDetail.members by events-detail.js --}}
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
    CSS layout (.ck-assign-split) is added in Step 8.
--}}
<x-ck-modal id="taskAssignModal" :title="__('events.task.assign_member')" size="md">
    <p id="taskAssignLabel" class="ck-text-muted"></p>
    <div class="ck-assign-split">
        <div class="ck-assign-split__col">
            <div class="ck-assign-split__col-label">{{ __('events.assign.available') }}</div>
            <select id="taskAssignAvailableList"
                    class="ck-assign-split__list ck-form-select"
                    multiple
                    size="8">
            </select>
        </div>
        <div class="ck-assign-split__arrows">
            <x-ck-button variant="primary" size="sm" type="button" id="taskAssignAddBtn">→</x-ck-button>
            <x-ck-button variant="secondary" size="sm" type="button" id="taskAssignRemoveBtn">←</x-ck-button>
        </div>
        <div class="ck-assign-split__col">
            <div class="ck-assign-split__col-label">{{ __('events.assign.assigned') }}</div>
            <select id="taskAssignAssignedList"
                    class="ck-assign-split__list ck-form-select"
                    multiple
                    size="8">
            </select>
        </div>
    </div>
    <div class="ck-form-actions">
        <x-ck-button variant="primary" type="button" id="taskAssignSaveBtn">
            {{ __('Save') }}
        </x-ck-button>
        <x-ck-button variant="secondary" type="button"
            onclick="ckModalClose(null, 'taskAssignModal')">
            {{ __('Cancel') }}
        </x-ck-button>
    </div>
</x-ck-modal>

{{-- ── Rename category modal (Tab 2: Aufgaben → ✏ button) ─────────────────────── --}}
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
            funcAddBase:    "{{ url('events/' . $event->id . '/functions') }}",
            funcAssignBase: "{{ url('events/' . $event->id . '/functions') }}",
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