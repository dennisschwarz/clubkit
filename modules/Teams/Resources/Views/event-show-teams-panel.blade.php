{{--
    Teams-Hook: Teams-Card auf der Termindetailseite.
    Extension point: events.show.teams-panel
    Registriert von: TeamsServiceProvider

    Data: $ckShowTeams (Collection<Team>)
    — per View Composer aus TeamsServiceProvider::registerViewComposers()
--}}
@if($ckShowTeams->isNotEmpty())
<x-ck-card>
    <x-slot:header>Teams</x-slot:header>
    <ul class="ck-tag-list">
        @foreach($ckShowTeams as $ckShowTeam)
            <li><x-ck-badge color="blue">{{ $ckShowTeam->name }}</x-ck-badge></li>
        @endforeach
    </ul>
</x-ck-card>
@endif
