@extends('core::admin.layout')
@section('title', 'Organisation')

@section('content')

{{-- Chevron SVG – wird in Hook-Views über den View-Scope weitergegeben --}}
@php
$chevronSvg = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
</svg>';
@endphp

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">🏛️ Organisation</h1>
        <p class="ck-page-subtitle">Vereinsfunktionen, Aufgaben, Verantwortliche und Zuständigkeiten</p>
    </div>
</div>

{{-- ── Lokale Sub-Tabs ──────────────────────────────────────────────── --}}
<div class="ck-local-tabs ck-mb-5">
    <button class="ck-local-tab ck-local-tab--purple {{ request('tab') !== 'aufgaben' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('mgmtTab-funktionen', this)">
        ⚙️ Funktionen
    </button>
    <button class="ck-local-tab ck-local-tab--blue {{ request('tab') === 'aufgaben' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('mgmtTab-aufgaben', this)">
        📋 Aufgaben
    </button>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     TAB: Funktionen
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtTab-funktionen"
     class="ck-local-section {{ request('tab') !== 'aufgaben' ? 'ck-local-section--active' : '' }}">

    <div class="ck-row ck-row--between ck-mb-4">
        <div class="ck-row">
            {{--
                Teams injiziert hier einen Team-Filter, wenn das Modul aktiv ist.
                Extension point: management.function.header.filter
                Registriert von: TeamsServiceProvider
            --}}
            @ckHook('management.function.header.filter')
        </div>
        <x-ck-button variant="success" onclick="mgmtModalOpen('function', 'create')">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Neue Funktion
        </x-ck-button>
    </div>

    @if($functions->isEmpty())
        <x-ck-card>
            <p class="ck-empty-state">Noch keine Funktionen angelegt.
                <a href="javascript:void(0)" onclick="mgmtModalOpen('function', 'create')">Jetzt anlegen</a>
            </p>
        </x-ck-card>
    @else
        {{--
            Wenn Teams aktiv ist, übernimmt teams::management-function-list die komplette
            Darstellung (mit Team-Filter und Team-Gruppierung).
            Ohne Teams: einfache flache Liste aller Funktionen.
            Extension point: management.function.list (replaceable section)
        --}}
        @if(app('ck.hooks')->has('management.function.list'))
            @ckHook('management.function.list')
        @else
            @include('management::_functions-table', ['groupFunctions' => $functions])
        @endif
    @endif

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     TAB: Aufgaben
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtTab-aufgaben"
     class="ck-local-section {{ request('tab') === 'aufgaben' ? 'ck-local-section--active' : '' }}">

    <div class="ck-row ck-row--between ck-mb-4">
        <div class="ck-row">
            {{--
                Extension point: management.task.header.filter
                Registriert von: TeamsServiceProvider
            --}}
            @ckHook('management.task.header.filter')
        </div>
        <x-ck-button variant="success" onclick="mgmtModalOpen('task', 'create')">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Neue Aufgabe
        </x-ck-button>
    </div>

    @if($tasks->isEmpty())
        <x-ck-card>
            <p class="ck-empty-state">Noch keine Aufgaben angelegt.
                <a href="javascript:void(0)" onclick="mgmtModalOpen('task', 'create')">Jetzt anlegen</a>
            </p>
        </x-ck-card>
    @else
        {{--
            Extension point: management.task.list (replaceable section)
            Registriert von: TeamsServiceProvider
        --}}
        @if(app('ck.hooks')->has('management.task.list'))
            @ckHook('management.task.list')
        @else
            @include('management::_tasks-table', ['groupTasks' => $tasks])
        @endif
    @endif

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL: Funktion anlegen / bearbeiten
     Raw HTML statt <x-ck-modal> + <x-slot:tabs> – PHP 8.4 Blade-Slot-Bug umgehen.
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtFunctionModal"
     class="ck-modal-overlay"
     onclick="ckModalClose(event, 'mgmtFunctionModal')">
    <div class="ck-modal-content ck-modal-content--md" onclick="event.stopPropagation()">

        <div class="ck-modal__header">
            <h2 class="ck-modal__title">Funktion</h2>
            <button type="button" class="ck-modal__close"
                    onclick="ckModalClose(null, 'mgmtFunctionModal')">&times;</button>
        </div>

        <div class="ck-modal__tabbar">
            <button class="ck-modal-tab ck-modal-tab--active"
                    onclick="ckModalTab('mgmtFunctionModal', 'mgmtFunctionTab-form', this)">
                ⚙️ Funktionsdaten
            </button>
            @ckHook('management.function.modal.tabs')
        </div>

        <div class="ck-modal__body">

            <div id="mgmtFunctionTab-form" class="ck-modal__section ck-modal__section--active">
                <form id="mgmtFunctionForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="mgmtFunctionFormMethod" value="POST">
                    <x-ck-field label="Funktionsname" name="name" id="mgmtFunctionFieldName" :required="true"
                                placeholder="z.B. Trainer, Co-Trainer, Betreuer, Kassenwart" />

                    {{--
                        Teams injiziert hier die Team-Checkbox-Liste.
                        Extension point: management.function.modal.teams
                        Registriert von: TeamsServiceProvider
                        Enthält: <div id="mgmtFunctionTeamList"> mit Checkboxen
                    --}}
                    @ckHook('management.function.modal.teams')

                    @if($membersActive && $members->isNotEmpty())
                    <div class="ck-field ck-mt-4">
                        <span class="ck-field__label">Personen <span class="ck-text-muted ck-font-normal">(optional)</span></span>
                        <div class="ck-multiselect-list ck-multiselect-list--scrollable" id="mgmtFunctionMemberList">
                            @foreach($members as $member)
                            <label class="ck-multiselect-item">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="ck-multiselect-item__checkbox">
                                <span class="ck-multiselect-item__label">{{ $member->last_name }}, {{ $member->first_name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <div class="ck-form-actions">
                        <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                        <x-ck-button type="button" variant="secondary" onclick="ckModalClose(null, 'mgmtFunctionModal')">Abbrechen</x-ck-button>
                    </div>
                </form>
            </div>

            @ckHook('management.function.modal.sections')

        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL: Aufgabe anlegen / bearbeiten
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtTaskModal"
     class="ck-modal-overlay"
     onclick="ckModalClose(event, 'mgmtTaskModal')">
    <div class="ck-modal-content ck-modal-content--md" onclick="event.stopPropagation()">

        <div class="ck-modal__header">
            <h2 class="ck-modal__title">Aufgabe</h2>
            <button type="button" class="ck-modal__close"
                    onclick="ckModalClose(null, 'mgmtTaskModal')">&times;</button>
        </div>

        <div class="ck-modal__tabbar">
            <button class="ck-modal-tab ck-modal-tab--active"
                    onclick="ckModalTab('mgmtTaskModal', 'mgmtTaskTab-form', this)">
                📋 Aufgabendaten
            </button>
            @ckHook('management.task.modal.tabs')
        </div>

        <div class="ck-modal__body">

            <div id="mgmtTaskTab-form" class="ck-modal__section ck-modal__section--active">
                <form id="mgmtTaskForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="mgmtTaskFormMethod" value="POST">
                    <x-ck-field label="Aufgabenbezeichnung" name="name" id="mgmtTaskFieldName" :required="true"
                                placeholder="z.B. Platzpflege, Materialwart, Schriftführer" />
                    <x-ck-field type="textarea" label="Beschreibung" name="description" id="mgmtTaskFieldDesc"
                                placeholder="Optionale Beschreibung der Aufgabe" />

                    {{--
                        Extension point: management.task.modal.teams
                        Registriert von: TeamsServiceProvider
                        Enthält: <div id="mgmtTaskTeamList"> mit Checkboxen
                    --}}
                    @ckHook('management.task.modal.teams')

                    @if($membersActive && $members->isNotEmpty())
                    <div class="ck-field ck-mt-4">
                        <span class="ck-field__label">Personen <span class="ck-text-muted ck-font-normal">(optional)</span></span>
                        <div class="ck-multiselect-list ck-multiselect-list--scrollable" id="mgmtTaskMemberList">
                            @foreach($members as $member)
                            <label class="ck-multiselect-item">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="ck-multiselect-item__checkbox">
                                <span class="ck-multiselect-item__label">{{ $member->last_name }}, {{ $member->first_name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <div class="ck-form-actions">
                        <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                        <x-ck-button type="button" variant="secondary" onclick="ckModalClose(null, 'mgmtTaskModal')">Abbrechen</x-ck-button>
                    </div>
                </form>
            </div>

            @ckHook('management.task.modal.sections')

        </div>
    </div>
</div>

@push('scripts')
<script>
    window.CK_Management = {
        functions: @json($functionsJs),
        tasks:     @json($tasksJs),
        customFieldsFunction: {
            definitions: @json($mgmtFunctionCfDefs),
            values:      @json($mgmtFunctionCfValues),
            upsertRoute: "{{ url('custom-fields/values/management_function') }}"
        },
        customFieldsTask: {
            definitions: @json($mgmtTaskCfDefs),
            values:      @json($mgmtTaskCfValues),
            upsertRoute: "{{ url('custom-fields/values/management_task') }}"
        },
        routes: {
            functionStore:  "{{ route('management.functions.store') }}",
            functionUpdate: "{{ url('management/functions') }}",
            taskStore:      "{{ route('management.tasks.store') }}",
            taskUpdate:     "{{ url('management/tasks') }}"
        }
    };
</script>
@vite('resources/js/modules/management-modal.js')
{{--
    Teams injiziert hier:
    - window.CK_Teams.functionTeamIds / taskTeamIds (für Modal-Vorbefüllung)
    - Event-Listener auf ck:management.function.modal.open / ck:management.task.modal.open
    Extension point: management.page.scripts
--}}
@ckHook('management.page.scripts')
@endpush

@endsection
