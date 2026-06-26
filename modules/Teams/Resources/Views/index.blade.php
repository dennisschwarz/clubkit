@extends('core::admin.layout')
@section('title', 'Teams')

@section('content')

@php
$chevronSvg = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
</svg>';

$teamColors = [
    ''       => 'Standard',
    'blue'   => 'Blau',
    'navy'   => 'Navy',
    'green'  => 'Grün',
    'teal'   => 'Teal',
    'red'    => 'Rot',
    'orange' => 'Orange',
    'amber'  => 'Gelb',
    'purple' => 'Lila',
    'pink'   => 'Pink',
    'slate'  => 'Grau',
];
@endphp

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Teams</h1>
        <p class="ck-page-subtitle">{{ $teams->count() }} Teams gesamt</p>
    </div>
    <x-ck-button variant="primary" onclick="teamsModalOpen('create')">
        + Team anlegen
    </x-ck-button>
</div>

@forelse($teams as $team)
@php
    $bodyId    = 'team-body-' . $team->id;
    $chevronId = 'team-chevron-' . $team->id;
    $colorClass = $team->color
        ? 'ck-section-header--team-' . $team->color . ' ck-section-header--colored'
        : '';
    $metaParts = [];
    $metaParts[] = $team->is_competition ? 'Spielbetrieb' : 'Freizeitbetrieb';
    if ($team->age_class) $metaParts[] = $team->age_class;
    if ($team->season)    $metaParts[] = $team->season;
    if ($team->league)    $metaParts[] = $team->league;
    $metaParts[] = $team->members_count . ' ' . ($team->members_count === 1 ? 'Mitglied' : 'Mitglieder');
@endphp

<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--collapsible {{ $colorClass }}"
         onclick="ckSectionToggle('{{ $bodyId }}', '{{ $chevronId }}')">
        <div class="ck-section-header__icon {{ $team->color ? '' : ($team->is_competition ? 'ck-section-header__icon--blue' : 'ck-section-header__icon--slate') }}">
            🏆
        </div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">{{ $team->name }}</span>
            <span class="ck-section-header__meta">
                {{ implode(' · ', $metaParts) }}
                @if(!$team->is_active)· <span class="ck-section-header__meta--inactive">Inaktiv</span>@endif
            </span>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <x-ck-button variant="warning" size="icon"
                title="Team bearbeiten"
                onclick="teamsModalOpen('edit', {{ $team->id }})">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                </svg>
            </x-ck-button>
            <form method="POST" action="{{ route('teams.destroy', $team) }}" class="ck-inline-form">
                @csrf @method('DELETE')
                <x-ck-button variant="danger" size="icon" type="submit"
                    title="Team löschen"
                    :confirm="'Team »' . $team->name . '« wirklich löschen?'">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </x-ck-button>
            </form>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $chevronId }}">{!! $chevronSvg !!}</span>
    </div>

    <div id="{{ $bodyId }}">
        <div class="ck-table-wrap">
            <table class="ck-table">
                <thead>
                    <tr>
                        <th>Mitglied</th>
                        @if($team->is_competition)<th>Rückennummer</th>@endif
                        <th>Spielberechtigung</th>
                        @if($team->is_active)<th class="ck-table__actions">Aktionen</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($team->members as $member)
                    <tr>
                        <td class="ck-table__bold">{{ $member->last_name }}, {{ $member->first_name }}</td>
                        @if($team->is_competition)
                        <td>
                            @if($member->pivot->squad_number)
                                <span class="ck-accordion-member__number">#{{ $member->pivot->squad_number }}</span>
                            @else
                                <span class="ck-text-muted">—</span>
                            @endif
                        </td>
                        @endif
                        <td>
                            @if($member->eligible_to_play)
                                <x-ck-badge color="green">✓ Spielberechtigt</x-ck-badge>
                            @else
                                <x-ck-badge color="gray">Nicht spielberechtigt</x-ck-badge>
                            @endif
                        </td>
                        @if($team->is_active)
                        <td class="ck-table__actions">
                            <div class="ck-table__action-cell">
                                <form method="POST"
                                      action="{{ route('teams.removeMember', [$team, $member]) }}"
                                      class="ck-inline-form">
                                    @csrf @method('DELETE')
                                    <x-ck-button size="sm" variant="danger" type="submit"
                                        :confirm="$member->last_name . ' aus dem Kader entfernen?'">
                                        Entfernen
                                    </x-ck-button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ ($team->is_competition ? 1 : 0) + ($team->is_active ? 1 : 0) + 2 }}"
                            class="ck-empty-state">
                            Noch niemand im Kader.
                        </td>
                    </tr>
                    @endforelse

                    @if($team->is_active && isset($availableByTeam[$team->id]) && $availableByTeam[$team->id]->isNotEmpty())
                    <tr>
                        <td colspan="{{ ($team->is_competition ? 1 : 0) + ($team->is_active ? 1 : 0) + 2 }}">
                            <form method="POST" action="{{ route('teams.addMember', $team) }}" class="ck-add-member-inline">
                                @csrf
                                <select name="member_id" class="ck-field__input">
                                    <option value="">Mitglied auswählen…</option>
                                    @foreach($availableByTeam[$team->id] as $m)
                                    <option value="{{ $m->id }}">{{ $m->last_name }}, {{ $m->first_name }}</option>
                                    @endforeach
                                </select>
                                @if($team->is_competition)
                                <input type="number" name="squad_number" placeholder="Rückennr."
                                       min="1" max="99" class="ck-field__input ck-add-member-inline__number">
                                @endif
                                <x-ck-button type="submit" variant="success" size="sm">+ Hinzufügen</x-ck-button>
                            </form>
                        </td>
                    </tr>
                    @elseif($team->is_active && isset($availableByTeam[$team->id]) && $availableByTeam[$team->id]->isEmpty() && $team->members->isNotEmpty())
                    <tr>
                        <td colspan="{{ ($team->is_competition ? 1 : 0) + ($team->is_active ? 1 : 0) + 2 }}" class="ck-text-muted">
                            ✓ Alle Mitglieder sind bereits im Kader.
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@empty
<x-ck-card>
    <p class="ck-empty-state">
        Noch keine Teams angelegt.
        <a href="javascript:void(0)" onclick="teamsModalOpen('create')">Jetzt anlegen</a>
    </p>
</x-ck-card>
@endforelse

{{-- ══ Modal mit Tabs ═════════════════════════════════════════════════════ --}}
<x-ck-modal id="teamModal" title="Team" size="md">

    <x-slot:tabs>
        <button class="ck-modal-tab ck-modal-tab--active"
                id="teamDatenTabBtn"
                onclick="ckModalTab('teamModal', 'teamTab-daten', this)">
            🏆 Team-Daten
        </button>
        @ckHook('team.modal.tabs')
    </x-slot:tabs>

    {{-- Tab: Team-Daten --}}
    <div id="teamTab-daten" class="ck-modal__section ck-modal__section--active">
        <form id="teamForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="teamFormMethod" value="POST">

            <x-ck-field label="Teamname" name="name" id="tFieldName" :required="true" />

            {{-- Teamfarbe --}}
            <div class="ck-field__group ck-mt-3">
                <label class="ck-field__label">Teamfarbe</label>
                <div class="ck-color-picker" id="tColorPicker">
                    @foreach($teamColors as $colorKey => $colorLabel)
                    <label class="ck-color-swatch{{ $colorKey === '' ? ' ck-color-swatch--selected' : '' }}"
                           title="{{ $colorLabel }}">
                        <input type="radio" name="color"
                               value="{{ $colorKey }}"
                               {{ $colorKey === '' ? 'checked' : '' }}>
                        <span class="ck-color-swatch__dot ck-color-swatch__dot--{{ $colorKey ?: 'default' }}"></span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="ck-mt-3">
                <x-ck-field type="checkbox" name="is_active" id="tFieldActive" :checked="true">
                    Team ist aktiv
                </x-ck-field>
            </div>

            <div class="ck-mt-3">
                <div class="ck-competition-block" id="tCompetitionBlock">
                    <label class="ck-competition-block__header">
                        <input type="checkbox" name="is_competition" id="tFieldIsCompetition"
                               value="1" onchange="teamsToggleCompetition(this.checked)">
                        <span>Spielbetrieb (Wettbewerb)</span>
                        <span class="ck-competition-block__chevron" id="tCompetitionChevron">▼</span>
                    </label>
                    <div class="ck-competition-block__body is-hidden" id="tCompetitionBody">
                        <div class="ck-form-grid ck-form-grid--2">
                            <x-ck-field label="Saison" name="season" id="tFieldSeason"
                                        placeholder="z.B. 2026/27" />
                            <x-ck-field label="Altersklasse" name="age_class" id="tFieldAgeClass"
                                        placeholder="z.B. D-Jugend" />
                            <div class="ck-form-grid__span-2">
                                <x-ck-field label="Liga" name="league" id="tFieldLeague"
                                            placeholder="z.B. Kreisliga A" />
                            </div>
                        </div>
                        <div class="ck-competition-block__divider"></div>
                        <label class="ck-competition-block__sub-check">
                            <input type="checkbox" name="eligible_only" id="tFieldEligibleOnly" value="1">
                            <div>
                                <span class="ck-competition-block__sub-label">Nur Spielberechtigte</span>
                                <span class="ck-competition-block__sub-hint">
                                    Nur Mitglieder mit Spielberechtigung dürfen in den Kader
                                </span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'teamModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

    @ckHook('team.modal.sections')

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Teams = {
        teams: @json($teamsJs),
        customFields: {
            definitions: @json($teamCfDefs),
            values: @json($teamCfValues),
            upsertRoute: "{{ url('custom-fields/values/team') }}"
        },
        routes: {
            store:  "{{ route('teams.store') }}",
            update: "{{ url('teams') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/teams-modal.js') }}"></script>
@ckHook('team.page.scripts')
@endpush

@endsection
