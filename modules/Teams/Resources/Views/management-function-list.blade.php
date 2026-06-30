{{--
    Teams-Hook: Team-gruppierte Funktionsliste.
    Extension point: management.function.list (replaceable section)
    Registriert von: TeamsServiceProvider

    Ersetzt die flache Standard-Liste von Management vollständig.
    Empfängt $chevronSvg aus dem Parent-View-Scope (Management/index.blade.php).

    Data injected by View Composer (TeamsServiceProvider::registerViewComposers()):
      $ckDisplay  – gefilterte Funktionen-Collection (nach team_id GET-Parameter)
      $ckGeneral  – Funktionen ohne Team-Zuordnung
      $ckByTeam   – array[team_id => ['name', 'functions[]']] (team-spezifisch)
--}}
@if($ckDisplay->isEmpty())
    <x-ck-card>
        <p class="ck-empty-state">Keine Funktionen für diesen Team-Filter.</p>
    </x-ck-card>
@else

    {{-- Sektion: Allgemein (kein Team) --}}
    @if($ckGeneral->isNotEmpty())
    @php $ckBodyId = 'fn-section-general'; $ckChevronId = 'fn-chevron-general'; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--collapsible"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon ck-section-header__icon--slate">🌐</div>
            <span class="ck-section-header__title">Allgemein</span>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}">
            @include('management::_functions-table', ['groupFunctions' => $ckGeneral])
        </div>
    </div>
    @endif

    {{-- Sektionen: pro Team --}}
    @foreach($ckByTeam as $ckTeamId => $ckGroup)
    @php $ckBodyId = 'fn-section-team-' . $ckTeamId; $ckChevronId = 'fn-chevron-team-' . $ckTeamId; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--collapsible"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon ck-section-header__icon--blue">🏆</div>
            <span class="ck-section-header__title">{{ $ckGroup['name'] }}</span>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}">
            @include('management::_functions-table', ['groupFunctions' => collect($ckGroup['functions'])])
        </div>
    </div>
    @endforeach

@endif
