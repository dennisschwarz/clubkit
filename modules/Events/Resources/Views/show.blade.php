@extends('core::admin.layout')
@section('title', $event->title)

@section('content')

{{-- ── Breadcrumb + Aktionen ──────────────────────────────────────────────── --}}
<div class="ck-page-header">
    <div class="ck-page-header__left">
        <a href="{{ route('events.index') }}" class="ck-breadcrumb">← Termine</a>
        <h1 class="ck-page-header__title">{{ $event->title }}</h1>
    </div>
    <div class="ck-page-header__actions">
        <x-ck-button variant="secondary" type="button" onclick="window.print()">
            🖨 Drucken
        </x-ck-button>
        <x-ck-button variant="danger" size="sm"
            :confirm="'Termin \'' . $event->title . '\' wirklich löschen?'"
            :form="'deleteEventForm'">
            Löschen
        </x-ck-button>
        <x-ck-button variant="primary" type="button" onclick="ckModalOpen('editEventModal')">
            Bearbeiten
        </x-ck-button>
    </div>
</div>

<form id="deleteEventForm" method="POST" action="{{ route('events.destroy', $event) }}" class="is-hidden">
    @csrf @method('DELETE')
</form>

{{-- ── Flash-Meldungen ────────────────────────────────────────────────────── --}}
@if(session('success'))
    <div class="ck-alert ck-alert--success">{{ session('success') }}</div>
@endif

{{-- ── Event-Info ─────────────────────────────────────────────────────────── --}}
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
        Aufgaben-Fortschrittsbalken — Daten aus EventController ($totalTasks, $doneTasks).
        Events liest aus event_task direkt, kein Management-Import erforderlich.
        data-progress wird von events-detail.js via setProperty('--progress', ...) gelesen.
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
    ── Aufgaben-Panel ─────────────────────────────────────────────────────────
    Extension point: events.show.tasks-panel
    Registriert von: ManagementServiceProvider

    Rendert: Aufgaben-Sektionen (nach Kategorie), Aufgabe-hinzufügen-Form,
    Vereinsfunktionen-Card.
    Wenn Management nicht installiert ist: nichts wird gerendert.
--}}
@ckHook('events.show.tasks-panel')

{{--
    ── Teams-Panel ────────────────────────────────────────────────────────────
    Extension point: events.show.teams-panel
    Registriert von: TeamsServiceProvider

    Rendert die Teams-Card auf der Detailseite.
    Wenn Teams nicht installiert ist: nichts wird gerendert.
--}}
@ckHook('events.show.teams-panel')

{{-- ── Custom Fields ───────────────────────────────────────────────────────── --}}
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

{{-- ── Bearbeiten-Modal ────────────────────────────────────────────────────── --}}
<x-ck-modal id="editEventModal" title="Termin bearbeiten" size="md">
    <form method="POST" action="{{ route('events.update', $event) }}">
        @csrf
        @method('PATCH')

        <x-ck-field label="Bezeichnung" name="title"
            :value="old('title', $event->title)" :required="true" />
        <x-ck-field type="datetime-local" label="Beginn" name="starts_at"
            :value="old('starts_at', $event->starts_at->format('Y-m-d\TH:i'))" :required="true" />
        <x-ck-field type="datetime-local" label="Ende" name="ends_at"
            :value="old('ends_at', $event->ends_at?->format('Y-m-d\TH:i'))" />
        <x-ck-field label="Ort" name="location"
            :value="old('location', $event->location)" />
        <x-ck-field type="textarea" label="Beschreibung" name="description"
            :value="old('description', $event->description)" />
        <x-ck-field type="textarea" label="Notizen" name="notes"
            :value="old('notes', $event->notes)" />

        <div class="ck-modal__footer">
            <x-ck-button variant="primary" type="submit">Speichern</x-ck-button>
            <x-ck-button variant="secondary" type="button"
                onclick="ckModalClose(null, 'editEventModal')">Abbrechen</x-ck-button>
        </div>
    </form>
</x-ck-modal>

@endsection

@push('scripts')
<script>
    /**
     * CK_EventDetail — Data bridge for events-detail.js
     * tasks wird von ManagementServiceProvider via events.show.page.scripts befüllt.
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
    Management injiziert hier CK_EventDetail.tasks (verfügbare Aufgaben für Dropdown).
    Extension point: events.show.page.scripts
--}}
@ckHook('events.show.page.scripts')
@vite(['resources/js/modules/events-detail.js'])
@endpush
