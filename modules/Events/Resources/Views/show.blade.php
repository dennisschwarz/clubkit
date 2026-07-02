@extends('core::admin.layout')
@section('title', $event->title)

@section('content')

{{-- ── Breadcrumb + actions ──────────────────────────────────────────────── --}}
<div class="ck-page-header">
    <div class="ck-page-header__left">
        <a href="{{ route('events.index') }}" class="ck-breadcrumb">← Termine</a>
        <h1 class="ck-page-header__title">{{ $event->title }}</h1>
    </div>
    <div class="ck-page-header__actions">
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
    @csrf @method('DELETE')
</form>

{{-- ── Flash messages ────────────────────────────────────────────────────── --}}
@if(session('success'))
    <div class="ck-alert ck-alert--success">{{ session('success') }}</div>
@endif

{{-- ── Event details ─────────────────────────────────────────────────────── --}}
<x-ck-card class="ck-event-info ck-no-print">
    <x-slot:header>Termindetails</x-slot:header>

    <dl class="ck-event-meta">
        <div class="ck-event-meta__row">
            <dt>Beginn</dt>
            <dd>{{ $event->starts_at->format('d.m.Y H:i') }} Uhr</dd>
        </div>
        @if($event->ends_at)
        <div class="ck-event-meta__row">
            <dt>Ende</dt>
            <dd>{{ $event->ends_at->format('d.m.Y H:i') }} Uhr</dd>
        </div>
        @endif
        @if($event->location)
        <div class="ck-event-meta__row">
            <dt>Ort</dt>
            <dd>{{ $event->location }}</dd>
        </div>
        @endif
        @if($event->description)
        <div class="ck-event-meta__row">
            <dt>Beschreibung</dt>
            <dd>{{ $event->description }}</dd>
        </div>
        @endif
        @if($event->notes)
        <div class="ck-event-meta__row">
            <dt>Notizen</dt>
            <dd>{{ $event->notes }}</dd>
        </div>
        @endif
    </dl>

    {{--
        Task progress bar — data provided by EventController ($totalTasks, $doneTasks).
        Events reads from event_task directly; no Management import required.
        data-progress is read by events-detail.js via setProperty('--progress', ...).
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
    ── Tasks panel ─────────────────────────────────────────────────────────
    Extension point: events.show.tasks-panel
    Registered by: ManagementServiceProvider

    Renders: task sections (by category), add-task form, management-functions card.
    Without Management: nothing is rendered.
--}}
@ckHook('events.show.tasks-panel')

{{--
    ── Teams panel ────────────────────────────────────────────────────────
    Extension point: events.show.teams-panel
    Registered by: TeamsServiceProvider

    Renders the teams card on the detail page.
    Without Teams: nothing is rendered.
--}}
@ckHook('events.show.teams-panel')

{{-- ── Custom fields ───────────────────────────────────────────────────────── --}}
@if(!empty($eventCfDefs))
<x-ck-card>
    <x-slot:header>Weitere Informationen</x-slot:header>
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

{{-- ── Edit modal ────────────────────────────────────────────────────────── --}}
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

        <div class="ck-modal__footer">
            <x-ck-button variant="primary" type="submit">{{ __('Save') }}</x-ck-button>
            <x-ck-button variant="secondary" type="button"
                onclick="ckModalClose(null, 'editEventModal')">{{ __('Cancel') }}</x-ck-button>
        </div>
    </form>
</x-ck-modal>

@endsection

@push('scripts')
<script>
    /**
     * CK_EventDetail — Data bridge for events-detail.js
     * tasks is populated by ManagementServiceProvider via events.show.page.scripts.
     */
    window.CK_EventDetail = {
        eventId: {{ $event->id }},
        csrf:    '{{ csrf_token() }}',
        routes:  {
            tasksBase: "{{ url('events/' . $event->id . '/tasks') }}"
        },
        tasks:   {},
        members: @json($allMembersJs)
    };
</script>
{{--
    Management injects CK_EventDetail.tasks here (available tasks for dropdown).
    Extension point: events.show.page.scripts
--}}
@ckHook('events.show.page.scripts')
@vite(['resources/js/modules/events-detail.js'])
@endpush
