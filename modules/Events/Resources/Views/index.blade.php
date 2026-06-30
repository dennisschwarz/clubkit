@extends('core::admin.layout')
@section('title', 'Termine')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Termine</h1>
        <p class="ck-page-subtitle">{{ $events->total() }} Termin{{ $events->total() !== 1 ? 'e' : '' }} gesamt</p>
    </div>
    <x-ck-button variant="primary" onclick="evtModalOpen()">
        + Termin anlegen
    </x-ck-button>
</div>

@if($events->isEmpty())
<x-ck-card>
    <p class="ck-empty-state">
        Noch keine Termine angelegt.
        <a href="javascript:void(0)" onclick="evtModalOpen()">Jetzt anlegen</a>
    </p>
</x-ck-card>
@else

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Datum / Zeit</th>
                <th>Titel</th>
                <th>Ort</th>
                @if($teamsInstalled)<th>Teams</th>@endif
                <th>Besetzung</th>
                @ckHook('event.table.header')
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
            @php $isPast = $event->starts_at->isPast(); @endphp
            <tr class="{{ $isPast ? 'ck-table__row--muted' : '' }}">

                {{-- Datum / Zeit --}}
                <td class="ck-event-date">
                    <span class="ck-event-date__day">{{ $event->starts_at->format('d.m.Y') }}</span>
                    <span class="ck-event-date__time">
                        {{ $event->starts_at->format('H:i') }} Uhr
                        @if($event->ends_at)– {{ $event->ends_at->format('H:i') }}@endif
                    </span>
                </td>

                {{-- Titel + Kurzbeschreibung --}}
                <td>
                    <a href="{{ route('events.show', $event) }}" class="ck-table__link">
                        {{ $event->title }}
                    </a>
                    @if($event->description)
                        <span class="ck-event-desc">{{ Str::limit($event->description, 60) }}</span>
                    @endif
                </td>

                {{-- Ort --}}
                <td>{{ $event->location ?? '—' }}</td>

                {{-- Teams (nur wenn Modul aktiv) --}}
                @if($teamsInstalled)
                <td>
                    @php $teamIdsForEvent = $eventTeamIds[$event->id] ?? []; @endphp
                    @forelse($teams->whereIn('id', $teamIdsForEvent) as $t)
                        <x-ck-badge color="blue">{{ $t->name }}</x-ck-badge>
                    @empty
                        <span class="ck-text-muted">—</span>
                    @endforelse
                </td>
                @endif

                {{-- Besetzung: Vereinsfunktionen + Aufgaben-Badges --}}
                <td>
                    @php
                        $hasAny = false;
                        $fnIds  = $eventMgmtFunctionIds[$event->id] ?? [];
                        $tskIds = $eventTaskIds[$event->id] ?? [];
                    @endphp

                    @if($managementInstalled && !empty($fnIds))
                        @php $hasAny = true; @endphp
                        @foreach($mgmtFunctions->whereIn('id', $fnIds) as $fn)
                            <x-ck-badge color="purple">{{ $fn->name }}</x-ck-badge>
                        @endforeach
                    @endif

                    @if($managementInstalled && !empty($tskIds))
                        @php $hasAny = true; @endphp
                        @foreach($tasks->whereIn('id', $tskIds) as $task)
                            <x-ck-badge color="amber">{{ $task->name }}</x-ck-badge>
                        @endforeach
                    @endif

                    @if(!$hasAny)
                        <span class="ck-text-muted">—</span>
                    @endif
                </td>

                @ckHook('event.table.row')

                {{-- Aktionen --}}
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        {{-- Detail-Seite öffnen (kein Modal: Bearbeiten passiert auf der Detail-Seite) --}}
                        <a href="{{ route('events.show', $event) }}"
                           class="ck-btn ck-btn--secondary ck-btn--sm"
                           title="Details &amp; Bearbeiten">
                            Bearbeiten
                        </a>
                        <form method="POST" action="{{ route('events.destroy', $event) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="'Termin »' . $event->title . '« wirklich löschen?'">
                                Löschen
                            </x-ck-button>
                        </form>
                    </div>
                </td>

            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($events->hasPages())
<div class="ck-pagination">{{ $events->links() }}</div>
@endif
@endif

{{-- ══ Quick-Create Modal ═══════════════════════════════════════════════════ --}}
{{--
    Intentionally minimal: only the five basic event fields.
    After store(), the user is redirected to the detail page where all
    task/function/team assignments are managed.
--}}
<x-ck-modal id="evtModal" title="Termin anlegen" size="md">

    <form id="evtForm" method="POST" action="{{ route('events.store') }}">
        @csrf

        {{-- Wann & Wo --}}
        <div class="ck-orga-section ck-orga-section--blue">
            <div class="ck-orga-section__head">📌 Wann &amp; Wo</div>
            <div class="ck-orga-section__body">
                <x-ck-field label="Bezeichnung" name="title" id="evtTitle" :required="true" />
                <div class="ck-form-grid ck-form-grid--2">
                    <x-ck-field type="text" label="Beginn" name="starts_at"
                        id="evtStartsAt" :required="true" placeholder="TT.MM.JJJJ HH:MM" />
                    <x-ck-field type="text" label="Ende (optional)" name="ends_at"
                        id="evtEndsAt" placeholder="TT.MM.JJJJ HH:MM" />
                </div>
                <x-ck-field label="Ort" name="location" id="evtLocation"
                    placeholder="z.B. Vereinsheim, Sportplatz" />
            </div>
        </div>

        {{-- Beschreibung & Notizen --}}
        <div class="ck-orga-section ck-orga-section--neutral ck-mt-4">
            <div class="ck-orga-section__head">📝 Beschreibung &amp; Notizen</div>
            <div class="ck-orga-section__body">
                <x-ck-field type="textarea" label="Beschreibung (optional)"
                    name="description" id="evtDescription"
                    placeholder="Kurze Beschreibung des Termins." />
                <x-ck-field type="textarea" label="Interne Notizen"
                    name="notes" id="evtNotes"
                    placeholder="Nur für Administratoren sichtbar." />
            </div>
        </div>

        @ckHook('event.modal.sections')

        <div class="ck-form-actions">
            <x-ck-button type="submit" variant="primary">Anlegen</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'evtModal')">
                Abbrechen
            </x-ck-button>
        </div>

    </form>
</x-ck-modal>

@push('scripts')
<script>
    window.CK_Events = {
        routes: {
            store: "{{ route('events.store') }}"
        }
    };
</script>
@vite(['resources/js/modules/events-modal.js'])
@ckHook('event.page.scripts')
@endpush