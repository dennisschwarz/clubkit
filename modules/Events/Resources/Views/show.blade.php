@extends('core::admin.layout')
@section('title', $event->title)
@section('content')
{{-- ── Page header ─────────────────────────────────────────────────────────── --}}
<div class="ck-page-header">
    <div class="ck-page-header__left">
        <a href="{{ route('events.index') }}" class="ck-breadcrumb">← Termine</a>
        <h1 class="ck-page-header__title">{{ $event->title }}</h1>
    </div>
    <div class="ck-page-header__actions">
        {{-- Tab-specific action buttons live inside each tab pane (hook views), not here. --}}
        <x-ck-button variant="secondary" type="button" onclick="window.print()">
            🖨 {{ __('Print') }}
        </x-ck-button>
        <x-ck-button variant="danger" size="sm"
            :confirm="'Termin \'' . $event->title . '\' wirklich löschen?'"
            :form="'deleteEventForm'">
            {{ __('Delete') }}
        </x-ck-button>
        <x-ck-button variant="primary" type="button" onclick="ckModalOpen('editEventModal')">
            {{ __('Edit') }}
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
{{-- ── Tab bar ──────────────────────────────────────────────────────────────── --}}
{{--
5 tabs: Übersicht | Aufgaben | Einsatzplan | Funktionen | Teams
Tab switching: ckEvtTab(id, btn) in events-detail.js (global, outside IIFE).
Content of tabs 2–4 is provided by ManagementServiceProvider hooks.
Content of tab 5 is provided by TeamsServiceProvider hook.
Without Management: tabs 2–4 show an empty-state card.
Without Teams: tab 5 shows an empty-state card.
--}}
<div class="ck-event-tab-bar">
    <button class="ck-event-tab ck-event-tab--active" type="button" onclick="ckEvtTab('overview', this)">{{ __('events.tab.overview') }}</button>
    <button class="ck-event-tab" type="button" onclick="ckEvtTab('tasks', this)">{{ __('events.tab.tasks') }}</button>
    <button class="ck-event-tab" type="button" onclick="ckEvtTab('slots', this)">{{ __('events.tab.slots') }}</button>
    <button class="ck-event-tab" type="button" onclick="ckEvtTab('functions', this)">{{ __('events.tab.functions') }}</button>
    <button class="ck-event-tab" type="button" onclick="ckEvtTab('teams', this)">{{ __('events.tab.teams') }}</button>
</div>
{{-- ── Pane: Übersicht ─────────────────────────────────────────────────────── --}}
<div class="ck-event-tab-pane ck-event-tab-pane--active" id="ckEvtPane-overview">
<x-ck-card class="ck-event-info ck-no-print">
    <x-slot:header>{{ __('events.detail.event_details') }}</x-slot:header>
    <dl class="ck-event-meta">
        <div class="ck-event-meta__row">
            <dt>{{ __('events.detail.starts_at') }}</dt>
            <dd>{{ $event->starts_at->format('d.m.Y H:i') }} Uhr</dd>
        </div>
        @if($event->ends_at)
        <div class="ck-event-meta__row">
            <dt>{{ __('events.detail.ends_at') }}</dt>
            <dd>{{ $event->ends_at->format('d.m.Y H:i') }} Uhr</dd>
        </div>
        @endif
        @if($event->location)
        <div class="ck-event-meta__row">
            <dt>{{ __('events.detail.location') }}</dt>
            <dd>{{ $event->location }}</dd>
        </div>
        @endif
        @if($event->description)
        <div class="ck-event-meta__row">
            <dt>{{ __('events.detail.description') }}</dt>
            <dd>{{ $event->description }}</dd>
        </div>
        @endif
        @if($event->notes)
        <div class="ck-event-meta__row">
            <dt>{{ __('events.detail.notes') }}</dt>
            <dd>{{ $event->notes }}</dd>
        </div>
        @endif
    </dl>

    {{--
        Task progress bar.
        $totalTasks / $doneTasks injected by EventController::show().
        event_task table guard applied in controller; variables are always set (0 if absent).
    --}}
    @if($totalTasks > 0)
    <div class="ck-event-progress">
        <div class="ck-event-progress__bar">
            <div class="ck-event-progress__fill"
                 data-progress="{{ round($doneTasks / $totalTasks * 100) }}">
            </div>
        </div>
        <span class="ck-event-progress__label">
            <span id="global-done-count">{{ $doneTasks }}</span> / {{ $totalTasks }} Aufgaben erledigt
        </span>
    </div>
    @endif
</x-ck-card>

{{--
    Extension point: events.show.overview-panel
    Registered by: ManagementServiceProvider (event-overview-panel.blade.php)
    Renders: 4 KPI tiles + Fortschritt nach Kategorie progress bars.
--}}
@ckHook('events.show.overview-panel')

{{-- Custom fields (CustomFields module) --}}
@if(!empty($eventCfDefs))
<x-ck-card>
    <x-slot:header>{{ __('events.detail.further_info') }}</x-slot:header>
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
<div class="ck-event-tab-pane" id="ckEvtPane-tasks"> @ckHook('events.show.tasks-panel') </div>
{{-- ── Pane: Einsatzplan ────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.einsatzplan-panel
Registered by: ManagementServiceProvider (event-einsatzplan-panel.blade.php)
Renders: event-day tasks with time-slot ETMs + add-slot form.
--}}
<div class="ck-event-tab-pane" id="ckEvtPane-slots"> @ckHook('events.show.slots-panel') </div>
{{-- ── Pane: Funktionen ─────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.functions-panel
Registered by: ManagementServiceProvider (event-functions-panel.blade.php)
Renders: management-functions cards with assigned members.
--}}
<div class="ck-event-tab-pane" id="ckEvtPane-functions"> @ckHook('events.show.functions-panel') </div>
{{-- ── Pane: Teams ──────────────────────────────────────────────────────────── --}}
{{--
Extension point: events.show.teams-panel
Registered by: TeamsServiceProvider (event-show-teams-panel.blade.php)
Renders: assigned teams tag list.
--}}
<div class="ck-event-tab-pane" id="ckEvtPane-teams"> @ckHook('events.show.teams-panel') </div>
{{-- ── Edit modal ───────────────────────────────────────────────────────────── --}}
{{--
    editEventModal  — PATCH /events/{event}  (standard form submit)
    newTaskModal    — POST  /events/{event}/tasks (AJAX, wired in events-detail.js)
    newCatModal     — POST  /management/categories (AJAX, wired in events-detail.js)
    slotModal       — POST  /events/{event}/slots  (AJAX, wired in events-detail.js)
    newFuncModal    — POST  /management/functions  (AJAX, wired in events-detail.js)
--}}
<x-ck-modal id="editEventModal" title="Termin bearbeiten" size="md">
<form method="POST" action="{{ route('events.update', $event) }}">
@csrf
@method('PATCH')
<x-ck-field label="Bezeichnung" name="title"
         :value="old('title', $event->title)" :required="true" />
<x-ck-field type="text" label="Beginn" name="starts_at"
         :value="old('starts_at', $event->starts_at->format('Y-m-d H:i'))"
         :required="true" data-ck-datetime="1" />
<x-ck-field type="text" label="Ende" name="ends_at"
         :value="old('ends_at', $event->ends_at?->format('Y-m-d H:i'))"
         data-ck-datetime="1" />
<x-ck-field label="Ort" name="location"
         :value="old('location', $event->location)" />
<x-ck-field type="textarea" label="Beschreibung" name="description"
         :value="old('description', $event->description)" />
<x-ck-field type="textarea" label="Notizen" name="notes"
         :value="old('notes', $event->notes)" />
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

{{-- ── New function modal (Tab 4: Funktionen) ──────────────────────────────── --}}
{{-- Fields per concept: Bezeichnung only — keine Priorität, keine Deadline, keine Kategorie. --}}
<x-ck-modal id="newFuncModal" :title="__('events.function.modal_title')" size="sm">
    <x-ck-field
        :label="__('events.function.field_name')"
        name="new_func_name"
        id="newFuncName"
        :required="true" />
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
            tasksBase:     "{{ url('events/' . $event->id . '/tasks') }}",
            slotsBase:     "{{ url('events/' . $event->id . '/slots') }}",
            membersBase:   "{{ url('events/' . $event->id . '/members') }}",
            mgmtTasksBase: "{{ url('management/tasks') }}",
            categoriesBase:"{{ url('management/task-categories') }}",
            functionsBase: "{{ url('management/functions') }}",
            funcAssignBase:"{{ url('events/' . $event->id . '/functions') }}"
        },
        tasks:   {},
        members: @json($allMembersJs)
    };
</script>
{{-- Management injects CK_EventDetail.tasks + CK_EventDetail.einsatzplanTasks here. --}}
@ckHook('events.show.page.scripts')
@vite(['resources/js/modules/events-detail.js'])
@endpush