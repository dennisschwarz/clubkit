{{--
    Partial: sorted member rows for a team.
    Used by: teams::index (initial render) and TeamController::membersFragment() (AJAX sort).
    Variables: $team (Team), $members (Collection<Member with pivot>).
    Outputs <tr> elements only — no <tbody> wrapper.
--}}
@forelse($members as $member)
<tr>
    <td class="ck-table__bold">{{ $member->last_name }}, {{ $member->first_name }}</td>
    <td class="ck-text-muted">{{ $member->date_of_birth ? $member->date_of_birth->format('d.m.Y') : '—' }}</td>
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
            <span class="ck-badge ck-badge--green">{{ __('teams.eligible') }}</span>
        @else
            <span class="ck-badge ck-badge--gray">{{ __('teams.not_eligible') }}</span>
        @endif
    </td>
    @if($team->is_active)
    <td class="ck-table__actions">
        <div class="ck-table__action-cell">
            <form method="POST"
                  action="{{ route('teams.removeMember', [$team, $member]) }}"
                  class="ck-inline-form">
                @csrf @method('DELETE')
                <button type="submit" class="ck-btn ck-btn--danger ck-btn--sm"
                        data-ck-confirm="{{ __('teams.confirm_remove_member', ['name' => $member->last_name]) }}">
                    {{ __('Remove') }}
                </button>
            </form>
        </div>
    </td>
    @endif
</tr>
@empty
<tr>
    <td colspan="{{ ($team->is_competition ? 1 : 0) + ($team->is_active ? 1 : 0) + 3 }}"
        class="ck-empty-state">
        {{ __('teams.roster_empty') }}
    </td>
</tr>
@endforelse