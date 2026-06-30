{{--
    Teams-Hook: Team-Filter-Formular im Aufgaben-Tab-Header.
    Extension point: management.task.header.filter
    Registriert von: TeamsServiceProvider

    Data: $ckFilterTeams (Collection<Team>), $ckFilterValue (?int)
    — per View Composer aus TeamsServiceProvider::registerViewComposers()
--}}
@if($ckFilterTeams->isNotEmpty())
<form method="GET" class="ck-row">
    <input type="hidden" name="tab" value="aufgaben">
    <x-ck-field name="team_id" type="select"
        :value="$ckFilterValue"
        :options="['' => 'Alle Teams'] + $ckFilterTeams->pluck('name', 'id')->toArray()" />
    <x-ck-button type="submit" variant="secondary" size="sm">Filtern</x-ck-button>
    @if($ckFilterValue)
        <x-ck-button :href="route('management.index', ['tab' => 'aufgaben'])" variant="secondary" size="sm">
            Zurücksetzen
        </x-ck-button>
    @endif
</form>
@endif
