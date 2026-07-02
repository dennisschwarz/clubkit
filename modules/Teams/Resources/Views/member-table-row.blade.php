<td>
    @forelse($ckMemberTeams as $ckTeam)
        <x-ck-badge :color="'team-' . ($ckTeam->color ?: 'default')">{{ $ckTeam->name }}</x-ck-badge>
    @empty
        <span class="ck-text-muted">—</span>
    @endforelse
</td>
