{{--
    Teams hook: Teams tab on the event detail page.
    Extension point: events.show.teams-panel
    Registered by: TeamsServiceProvider

    Data injected by TeamsServiceProvider view composer:
        $ckShowTeams      → Collection<Team>  — teams currently assigned to this event
        $ckAvailableTeams → Collection<Team>  — teams not yet assigned (for the add-select)

    JS: events-detail.js handles #teamAddBtn (POST) and .ck-team-remove-btn (DELETE)
        via CK_EventDetail.routes.teamsBase
--}}

{{-- ── Action bar: assign team ─────────────────────────────────────────────── --}}
@if($ckAvailableTeams->isNotEmpty())
<div class="ck-pane-actions">
    <div class="ck-add-task">
        <select id="teamAddSelect" class="ck-form-select">
            <option value="">{{ __('events.teams.select_placeholder') }}</option>
            @foreach($ckAvailableTeams as $ckAvailTeam)
                <option value="{{ $ckAvailTeam->id }}">{{ $ckAvailTeam->name }}</option>
            @endforeach
        </select>
        <x-ck-button variant="primary" type="button" id="teamAddBtn">
            {{ __('events.teams.add_btn') }}
        </x-ck-button>
    </div>
</div>
@endif

{{-- ── Assigned teams table ────────────────────────────────────────────────── --}}
@if($ckShowTeams->isNotEmpty())
<x-ck-card>
    <x-slot:header>{{ __('events.teams.panel_header') }}</x-slot:header>
    <table class="ck-table">
        <thead>
            <tr>
                <th>{{ __('events.teams.col_team') }}</th>
                <th class="ck-table__col--actions"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($ckShowTeams as $ckShowTeam)
            <tr>
                <td>
                    <x-ck-badge :color="'team-' . ($ckShowTeam->color ?? 'default')">
                        {{ $ckShowTeam->name }}
                    </x-ck-badge>
                </td>
                <td class="ck-table__col--actions">
                    <button type="button"
                            class="ck-team-remove-btn ck-btn ck-btn--danger ck-btn--sm"
                            data-team-id="{{ $ckShowTeam->id }}"
                            data-ck-confirm="{{ __('events.teams.remove_confirm', ['name' => $ckShowTeam->name]) }}">
                        ×
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</x-ck-card>
@else
<x-ck-card>
    <p class="ck-text-muted">
        {{ __('events.teams.empty') }}
        @if($ckAvailableTeams->isEmpty())
            {{ __('events.teams.empty_no_teams') }}
        @endif
    </p>
</x-ck-card>
@endif