{{-- Teams-Hook: <td> Teams-Badges in der Terminliste.
    Extension point: event.table.teams.row
    Registriert von: TeamsServiceProvider
    Data: $ckEventTeams (Collection) via View Composer (TeamsServiceProvider)
--}}
<td>
    @forelse($ckEventTeams as $ckTeam)
        <x-ck-badge color="blue">{{ $ckTeam->name }}</x-ck-badge>
    @empty
        <span class="ck-text-muted">—</span>
    @endforelse
</td>
