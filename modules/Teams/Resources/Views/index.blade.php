@extends('core::admin.layout')
@section('title', __('teams.title'))

@section('content')

@php
$chevronSvg = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
</svg>';

$teamColors = [
    ''       => __('teams.color.default'),
    'blue'   => __('teams.color.blue'),
    'navy'   => __('teams.color.navy'),
    'green'  => __('teams.color.green'),
    'teal'   => __('teams.color.teal'),
    'red'    => __('teams.color.red'),
    'orange' => __('teams.color.orange'),
    'amber'  => __('teams.color.yellow'),
    'purple' => __('teams.color.purple'),
    'pink'   => __('teams.color.pink'),
    'slate'  => __('teams.color.gray'),
];
@endphp

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ __('teams.title') }}</h1>
        <p class="ck-page-subtitle">{{ __('teams.count', ['count' => $teams->count()]) }}</p>
    </div>
    <x-ck-button variant="primary" onclick="teamsModalOpen('create')">
        {{ __('teams.create') }}
    </x-ck-button>
</div>

{{--
    Sort bar: teams use an accordion layout without column headers,
    so a compact sort dropdown is used instead of sortable column headers.
    URL format: ?sort=name | ?sort=-name | ?sort=-is_active | etc.
--}}
<div class="ck-sort-bar ck-mb-4">
    <form method="GET">
        <select name="sort" class="ck-field__input ck-field__input--sm"
                onchange="this.form.submit()">
            <option value="name"            {{ request('sort', 'name') === 'name'            ? 'selected' : '' }}>{{ __('teams.sort.az') }}</option>
            <option value="-name"           {{ request('sort') === '-name'                   ? 'selected' : '' }}>{{ __('teams.sort.za') }}</option>
            <option value="-is_active"      {{ request('sort') === '-is_active'              ? 'selected' : '' }}>{{ __('teams.sort.status') }}</option>
            <option value="is_active"       {{ request('sort') === 'is_active'               ? 'selected' : '' }}>{{ __('teams.sort.status') }}</option>
            <option value="-is_competition" {{ request('sort') === '-is_competition'         ? 'selected' : '' }}>{{ __('teams.sort.members') }}</option>
        </select>
    </form>
</div>

@forelse($teams as $team)
@php
    $bodyId    = 'team-body-' . $team->id;
    $chevronId = 'team-chevron-' . $team->id;
    $colorClass = $team->color
        ? 'ck-section-header--team-' . $team->color . ' ck-section-header--colored'
        : '';
    $metaParts = [];
    $metaParts[] = $team->is_competition ? __('teams.competition') : __('teams.leisure');
    if ($team->age_class) $metaParts[] = $team->age_class;
    if ($team->season)    $metaParts[] = $team->season;
    if ($team->league)    $metaParts[] = $team->league;
    $metaParts[] = $team->members_count . ' ' . __('teams.col.member');
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
                @if(!$team->is_active) · <span class="ck-section-header__meta--inactive">{{ __('teams.inactive') }}</span>@endif
            </span>
        </div>
        {{-- Action buttons: stop propagation so clicks don't toggle the accordion --}}
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            {{-- Edit team data --}}
            <x-ck-button variant="warning" size="icon"
                title="{{ __('Edit') }}"
                onclick="teamsModalOpen('edit', {{ $team->id }})">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                </svg>
            </x-ck-button>
            {{-- Manage roster via Dual Listbox modal --}}
            @if($team->is_active)
            <x-ck-button variant="secondary" size="icon"
                :title="__('teams.manage_roster')"
                onclick="openRosterModal({{ $team->id }})">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                </svg>
            </x-ck-button>
            @endif
            {{-- Delete team --}}
            <form method="POST" action="{{ route('teams.destroy', $team) }}" class="ck-inline-form">
                @csrf @method('DELETE')
                <x-ck-button variant="danger" size="icon" type="submit"
                    title="{{ __('Delete') }}"
                    :confirm="__('teams.confirm_delete', ['name' => $team->name])">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
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
                        <th>{{ __('teams.col.member') }}</th>
                        @if($team->is_competition)<th>{{ __('teams.col.squad_number') }}</th>@endif
                        <th>{{ __('teams.col.eligible') }}</th>
                        @if($team->is_active)<th class="ck-table__actions">{{ __('core.col.actions') }}</th>@endif
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
                                <x-ck-badge color="green">{{ __('teams.eligible') }}</x-ck-badge>
                            @else
                                <x-ck-badge color="gray">{{ __('teams.not_eligible') }}</x-ck-badge>
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
                                        :confirm="__('teams.confirm_remove_member', ['name' => $member->last_name])">
                                        {{ __('Remove') }}
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
                            {{ __('teams.roster_empty') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@empty
<x-ck-card>
    <p class="ck-empty-state">
        {{ __('teams.empty') }}
        <x-ck-button type="button" variant="primary" size="sm" onclick="teamsModalOpen('create')">
            {{ __('core.create_now') }}
        </x-ck-button>
    </p>
</x-ck-card>
@endforelse

{{-- ══ Roster Dual Listbox Modal ═══════════════════════════════════════════ --}}
<x-ck-modal id="teamRosterModal" :title="__('teams.modal_roster')" size="lg">
    <form id="rosterForm" method="POST">
        @csrf
        @method('PUT')

        <p class="ck-text-muted ck-font-sm ck-mb-4">{{ __('events.assign.ctrl_hint') }}</p>

        <div class="ck-dual-listbox">
            <div class="ck-dual-listbox__col">
                <span class="ck-dual-listbox__label">{{ __('teams.available') }}</span>
                <select id="rosterAvail" multiple class="ck-dual-listbox__list">
                    {{-- populated by openRosterModal() in teams-modal.js --}}
                </select>
            </div>
            <div class="ck-dual-listbox__controls">
                <x-ck-button type="button" variant="secondary" onclick="ckRosterMove('right')">→</x-ck-button>
                <x-ck-button type="button" variant="secondary" onclick="ckRosterMove('left')">←</x-ck-button>
            </div>
            <div class="ck-dual-listbox__col">
                <span class="ck-dual-listbox__label">{{ __('teams.in_roster') }}</span>
                {{--
                    name="member_ids[]" – all options are selected before submit
                    by the rosterForm submit handler in teams-modal.js.
                --}}
                <select id="rosterCurrent" name="member_ids[]" multiple class="ck-dual-listbox__list">
                    {{-- populated by openRosterModal() in teams-modal.js --}}
                </select>
            </div>
        </div>

        <div class="ck-form-actions">
            <x-ck-button type="submit" variant="primary">{{ __('teams.save_roster') }}</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'teamRosterModal')">{{ __('Cancel') }}</x-ck-button>
        </div>
    </form>
</x-ck-modal>

{{-- ══ Team Edit Modal ═════════════════════════════════════════════════════ --}}
<x-ck-modal id="teamModal" :title="__('teams.title')" size="md">

    <x-slot:tabs>
        <button class="ck-modal-tab ck-modal-tab--active"
                id="teamDatenTabBtn"
                onclick="ckModalTab('teamModal', 'teamTab-daten', this)">
            {{ __('teams.tab_data') }}
        </button>
        @ckHook('team.modal.tabs')
    </x-slot:tabs>

    <div id="teamTab-daten" class="ck-modal__section ck-modal__section--active">
        <form id="teamForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="teamFormMethod" value="POST">

            <x-ck-field :label="__('teams.field.name')" name="name" id="tFieldName" :required="true" />

            <div class="ck-field__group ck-mt-3">
                <label class="ck-field__label">{{ __('teams.field.color') }}</label>
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
                    {{ __('teams.field.active') }}
                </x-ck-field>
            </div>

            <div class="ck-mt-3">
                <div class="ck-competition-block" id="tCompetitionBlock">
                    <label class="ck-competition-block__header">
                        <input type="checkbox" name="is_competition" id="tFieldIsCompetition"
                               value="1" onchange="teamsToggleCompetition(this.checked)">
                        <span>{{ __('teams.field.competition') }}</span>
                        <span class="ck-competition-block__chevron" id="tCompetitionChevron">▼</span>
                    </label>
                    <div class="ck-competition-block__body is-hidden" id="tCompetitionBody">
                        <div class="ck-form-grid ck-form-grid--2">
                            <x-ck-field :label="__('teams.field.season')"    name="season"    id="tFieldSeason"    placeholder="z.B. 2026/27" />
                            <x-ck-field :label="__('teams.field.age_class')" name="age_class" id="tFieldAgeClass" placeholder="z.B. D-Jugend" />
                            <div class="ck-form-grid__span-2">
                                <x-ck-field :label="__('teams.field.league')" name="league" id="tFieldLeague" placeholder="z.B. Kreisliga A" />
                            </div>
                        </div>
                        <div class="ck-competition-block__divider"></div>
                        <label class="ck-competition-block__sub-check">
                            <input type="checkbox" name="eligible_only" id="tFieldEligibleOnly" value="1">
                            <div>
                                <span class="ck-competition-block__sub-label">{{ __('teams.eligible_only') }}</span>
                                <span class="ck-competition-block__sub-hint">{{ __('teams.eligible_only_hint') }}</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">{{ __('Save') }}</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'teamModal')">{{ __('Cancel') }}</x-ck-button>
            </div>
        </form>
    </div>

    @ckHook('team.modal.sections')

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Teams = {
        teams:     @json($teamsJs),
        roster:    @json($rosterByTeamJs),
        available: @json($availableByTeamJs),
        customFields: {
            definitions: @json($teamCfDefs),
            values: @json($teamCfValues),
            upsertRoute: "{{ url('custom-fields/values/team') }}"
        },
        routes: {
            store:      "{{ route('teams.store') }}",
            update:     "{{ url('teams') }}",
            syncRoster: "{{ url('teams') }}"
        }
    };
</script>
@vite('resources/js/modules/teams-modal.js')
@ckHook('team.page.scripts')
@endpush

@endsection