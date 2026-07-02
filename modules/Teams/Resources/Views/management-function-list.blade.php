{{--
    Teams hook: team-grouped function list.
    Extension point: management.function.list (replaceable section)
    Registered by: TeamsServiceProvider

    Replaces the flat default list from Management entirely.
    Receives $chevronSvg from the parent view scope (Management/index.blade.php).

    Data injected by View Composer (TeamsServiceProvider::registerViewComposers()):
      $ckDisplay  – filtered functions collection (by team_id GET parameter)
      $ckGeneral  – functions without any team assignment
      $ckByTeam   – array[team_id => ['name', 'color', 'functions[]']] (team-specific)
--}}
@if($ckDisplay->isEmpty())
    <x-ck-card>
        <p class="ck-empty-state">Keine Funktionen für diesen Team-Filter.</p>
    </x-ck-card>
@else

    {{-- Section: General (no team) --}}
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

    {{-- Sections: one per team --}}
    @foreach($ckByTeam as $ckTeamId => $ckGroup)
    @php $ckBodyId = 'fn-section-team-' . $ckTeamId; $ckChevronId = 'fn-chevron-team-' . $ckTeamId; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--collapsible ck-section-header--colored ck-section-header--team-{{ $ckGroup['color'] ?? 'blue' }}"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon">🏆</div>
            <span class="ck-section-header__title">{{ $ckGroup['name'] }}</span>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}">
            @include('management::_functions-table', ['groupFunctions' => collect($ckGroup['functions'])])
        </div>
    </div>
    @endforeach

@endif
