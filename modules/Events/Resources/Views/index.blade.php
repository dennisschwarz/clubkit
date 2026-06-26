@extends('core::admin.layout')
@section('title', 'Termine')
@section('content')
<div class="ck-page-header"> <div> <h1 class="ck-page-title">Termine</h1> <p class="ck-page-subtitle">{{ $events->total() }} Termin{{ $events->total() !== 1 ? 'e' : '' }} gesamt</p> </div> <x-ck-button variant="primary" onclick="evtModalOpen('create')"> + Termin anlegen </x-ck-button> </div>
@if($events->isEmpty())
<x-ck-card>
<p class="ck-empty-state">
Noch keine Termine angelegt.
<a href="javascript:void(0)" onclick="evtModalOpen('create')">Jetzt anlegen</a>
</p>
</x-ck-card>
@else
<div class="ck-table-wrap"> <table class="ck-table"> <thead> <tr> <th>Datum / Zeit</th> <th>Titel</th> <th>Ort</th> @if($teamsInstalled)<th>Teams</th>@endif <th>Besetzung</th> @ckHook('event.table.header') <th class="ck-table__actions">Aktionen</th> </tr> </thead> <tbody> @foreach($events as $event) @php $isPast = $event->starts_at->isPast(); @endphp <tr class="{{ $isPast ? 'ck-table__row--muted' : '' }}"> <td class="ck-event-date"> <span class="ck-event-date__day">{{ $event->starts_at->format('d.m.Y') }}</span> <span class="ck-event-date__time">{{ $event->starts_at->format('H:i') }} Uhr @if($event->ends_at)– {{ $event->ends_at->format('H:i') }}@endif </span> </td> <td> <span class="ck-table__bold">{{ $event->title }}</span> @if($event->description) <span class="ck-event-desc">{{ Str::limit($event->description, 60) }}</span> @endif </td> <td>{{ $event->location ?? '—' }}</td>
            @if($teamsInstalled)
            <td>
                @php $teamIdsForEvent = $eventTeamIds[$event->id] ?? []; @endphp
                @if(!empty($teamIdsForEvent))
                    @foreach($teams as $t)
                        @if(in_array($t->id, $teamIdsForEvent))
                            <x-ck-badge color="blue">{{ $t->name }}</x-ck-badge>
                        @endif
                    @endforeach
                @else
                    <span class="ck-text-muted">—</span>
                @endif
            </td>
            @endif

            <td>
                @php
                    $hasAny = false;
                    $fnIds  = $eventMgmtFunctionIds[$event->id] ?? [];
                    $tskIds = $eventTaskIds[$event->id] ?? [];
                @endphp
                @if($managementInstalled && !empty($fnIds))
                    @php $hasAny = true; @endphp
                    @foreach($mgmtFunctions as $fn)
                        @if(in_array($fn->id, $fnIds))
                        <div class="ck-event-function">
                            <x-ck-badge color="purple">{{ $fn->name }}</x-ck-badge>
                            @if($fn->members->isNotEmpty())
                            <span class="ck-event-function__members">
                                {{ $fn->members->map(fn($m) => $m->last_name . ', ' . $m->first_name)->join(' · ') }}
                            </span>
                            @endif
                        </div>
                        @endif
                    @endforeach
                @endif
                @if($managementInstalled && !empty($tskIds))
                    @php $hasAny = true; @endphp
                    @foreach($tasks as $task)
                        @if(in_array($task->id, $tskIds))
                            <x-ck-badge color="amber">{{ $task->name }}</x-ck-badge>
                        @endif
                    @endforeach
                @endif
                @if($event->assignments->isNotEmpty())
                    @php $hasAny = true; @endphp
                    @foreach($event->assignments as $a)
                    <span class="ck-event-organizer">
                        {{ $a->last_name }}, {{ $a->first_name }}
                        @if($a->pivot->description)
                            <span class="ck-event-organizer__role">({{ $a->pivot->description }})</span>
                        @endif
                    </span>
                    @endforeach
                @endif
                @if(!$hasAny)<span class="ck-text-muted">—</span>@endif
            </td>

            @ckHook('event.table.row')

            <td class="ck-table__actions">
                <div class="ck-table__action-cell">
                    <x-ck-button variant="warning" size="icon" title="Bearbeiten"
                        onclick="evtModalOpen('edit', {{ $event->id }})">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                        </svg>
                    </x-ck-button>
                    <form method="POST" action="{{ route('events.destroy', $event) }}" class="ck-inline-form">
                        @csrf @method('DELETE')
                        <x-ck-button variant="danger" size="icon" type="submit" title="Löschen"
                            :confirm="'Termin »' . $event->title . '« wirklich löschen?'">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
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
<div class="ck-pagination">{{ $events->links() }}</div> @endif @endif
{{-- ══ Modal ════════════════════════════════════════════════════════════════ --}}
<x-ck-modal id="evtModal" title="Termin" size="lg">
<x-slot:tabs>
    <button class="ck-modal-tab ck-modal-tab--active"
            id="evtDatenTabBtn"
            onclick="ckModalTab('evtModal', 'evtTab-daten', this)">
        📅 Termin-Daten
    </button>
    <button class="ck-modal-tab"
            id="evtOrgaTabBtn"
            onclick="ckModalTab('evtModal', 'evtTab-orga', this)">
        🏛️ Organisatorisches
    </button>
    @ckHook('event.modal.tabs')
</x-slot:tabs>

<form id="evtForm" method="POST">
    @csrf
    <input type="hidden" name="_method" id="evtFormMethod" value="POST">

    {{-- ── TAB 1: TERMIN-DATEN ──────────────────────────────────────────── --}}
    <div id="evtTab-daten" class="ck-modal__section ck-modal__section--active">

        {{-- Blau: Kerninfos (Wann, Wo) --}}
        <div class="ck-orga-section ck-orga-section--blue">
            <div class="ck-orga-section__head">📌 Wann &amp; Wo</div>
            <div class="ck-orga-section__body">
                <x-ck-field label="Titel" name="title" id="evtTitle" :required="true" />
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

        {{-- Neutral: Texte --}}
        <div class="ck-orga-section ck-orga-section--neutral ck-mt-4">
            <div class="ck-orga-section__head">📝 Beschreibung &amp; Notizen</div>
            <div class="ck-orga-section__body">
                <x-ck-field type="textarea" label="Beschreibung (optional)"
                    name="description" id="evtDescription"
                    placeholder="Kurze Beschreibung des Termins für alle Beteiligten." />
                <x-ck-field type="textarea" label="Interne Notizen"
                    name="notes" id="evtNotes"
                    placeholder="Nur für Administratoren sichtbar." />
            </div>
        </div>

        @ckHook('event.modal.sections')

    </div>{{-- /#evtTab-daten --}}

    {{-- ── TAB 2: ORGANISATORISCHES ─────────────────────────────────────── --}}
    <div id="evtTab-orga" class="ck-modal__section">

        @if($managementInstalled)
        <div class="ck-org-split">

            {{-- Lila: Vereinsfunktionen (Rollen/Personen) --}}
            <div class="ck-orga-section ck-orga-section--purple">
                <div class="ck-orga-section__head">⚙️ Vereinsfunktionen</div>
                <div class="ck-orga-section__body">
                    @if($mgmtFunctions->isNotEmpty())
                    <div class="ck-checkbox-list ck-checkbox-list--tall">
                        @foreach($mgmtFunctions as $fn)
                        <label class="ck-checkbox-item">
                            <input type="checkbox" name="management_function_ids[]"
                                   id="evtMgmtFn{{ $fn->id }}" value="{{ $fn->id }}">
                            <span>
                                <strong>{{ $fn->name }}</strong>
                                @if($fn->members->isNotEmpty())
                                <span class="ck-text-muted">
                                    — {{ $fn->members->map(fn($m) => $m->last_name . ', ' . $m->first_name)->join('; ') }}
                                </span>
                                @else
                                <span class="ck-text-muted">— Noch keine Mitglieder</span>
                                @endif
                            </span>
                        </label>
                        @endforeach
                    </div>
                    @else
                    <p class="ck-orga-section__empty">Noch keine Funktionen angelegt.</p>
                    @endif
                </div>
            </div>

            {{-- Blau: Vereinsaufgaben (wiederkehrende Aufgaben) --}}
            <div class="ck-orga-section ck-orga-section--blue">
                <div class="ck-orga-section__head">📋 Vereinsaufgaben</div>
                <div class="ck-orga-section__body">
                    @if($tasks->isNotEmpty())
                    <div class="ck-checkbox-list ck-checkbox-list--tall">
                        @foreach($tasks as $task)
                        <label class="ck-checkbox-item">
                            <input type="checkbox" name="task_ids[]"
                                   id="evtTask{{ $task->id }}" value="{{ $task->id }}">
                            <span>{{ $task->name }}</span>
                        </label>
                        @endforeach
                    </div>
                    @else
                    <p class="ck-orga-section__empty">Noch keine Aufgaben angelegt.</p>
                    @endif
                </div>
            </div>

        </div>{{-- /.ck-org-split --}}
        @endif

        {{-- Amber: Einmalige Aufgaben (Sonderzuweisungen) --}}
        <div class="ck-orga-section ck-orga-section--amber ck-mt-4">
            <div class="ck-orga-section__head">✏️ Einmalige Aufgaben</div>
            <div class="ck-orga-section__body">
                <div id="evtAssignmentList"></div>
                <x-ck-button type="button" variant="secondary" size="sm" onclick="evtAddAssignment()">
                    + Person hinzufügen
                </x-ck-button>
            </div>
        </div>

        {{-- Grün: Teams --}}
        @if($teamsInstalled && $teams->isNotEmpty())
        <div class="ck-orga-section ck-orga-section--green ck-mt-4">
            <div class="ck-orga-section__head">🏆 Teams</div>
            <div class="ck-orga-section__body">
                <div class="ck-checkbox-list ck-checkbox-list--inline">
                    @foreach($teams as $team)
                    <label class="ck-checkbox-item">
                        <input type="checkbox" name="team_ids[]"
                               id="evtTeam{{ $team->id }}" value="{{ $team->id }}">
                        <span class="ck-team-dot ck-team-dot--{{ $team->color ?: 'default' }}"></span>
                        <span>{{ $team->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /#evtTab-orga --}}

    {{-- Aktions-Leiste --}}
    <div class="ck-form-actions">
        <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
        <x-ck-button type="button" variant="secondary" onclick="ckModalClose(null, 'evtModal')">
            Abbrechen
        </x-ck-button>
    </div>

</form>
</x-ck-modal>
@push('scripts')
<script> window.CK_Events = { events: @json($eventsJs), members: @json($membersJs), teams: @json($teamsJs), tasks: @json($tasksJs), managementFunctions: @json($mgmtFunctionsJs), flags: { teamsInstalled: @json($teamsInstalled), managementInstalled: @json($managementInstalled) }, customFields: { definitions: @json($eventCfDefs), values: @json($eventCfValues), upsertRoute: "{{ url('custom-fields/values/event') }}" }, routes: { store: "{{ route('events.store') }}", update: "{{ url('events') }}" } }; </script> <script src="{{ asset('js/modules/events-modal.js') }}"></script>
@ckHook('event.page.scripts')
@endpush
@endsection