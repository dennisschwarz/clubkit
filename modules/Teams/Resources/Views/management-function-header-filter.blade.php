{{--
    Teams-Hook: Team-Filter-Formular im Funktionen-Tab-Header.
    Extension point: management.function.header.filter
    Registriert von: TeamsServiceProvider

    Data: $ckFilterTeams (Collection<Team>), $ckFilterValue (?int)
    — beide per View Composer aus TeamsServiceProvider::registerViewComposers()
--}}
@if($ckFilterTeams->isNotEmpty())
<form method="GET" class="ck-row">
    <input type="hidden" name="tab" value="funktionen">
    <x-ck-field name="team_id" type="select"
        :value="$ckFilterValue"
        :options="['' => 'Alle Teams'] + $ckFilterTeams->pluck('name', 'id')->toArray()" />
    <x-ck-button type="submit" variant="secondary" size="sm">Filtern</x-ck-button>
    @if($ckFilterValue)
        <x-ck-button :href="route('management.index')" variant="secondary" size="sm">
            Zurücksetzen
        </x-ck-button>
    @endif
</form>
@endif
