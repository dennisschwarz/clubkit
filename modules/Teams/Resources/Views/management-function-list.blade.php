{{--
    Teams hook: team-grouped function list.
    Extension point: management.function.list (replaceable section)
    Registered by: TeamsServiceProvider

    Replaces the flat default list from Management entirely.
    Receives $chevronSvg from the parent view scope (Management/index.blade.php).

    Data injected by View Composer (TeamsServiceProvider::registerViewComposers()):
      $ckDisplay  – filtered functions collection (by team_id GET parameter)
      $ckGeneral  – functions without any team assignment
      $ckByTeam   – array[team_id => ['name', 'color', 'functions[]']] — ALL teams seeded
--}}

{{-- Section: General (no team) — always shown --}}
@php $ckBodyId = 'fn-section-general'; $ckChevronId = 'fn-chevron-general'; @endphp
<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--flush ck-section-header--collapsible ck-section-header--colored ck-section-header--team-steel"
         onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
        <div class="ck-section-header__icon">🌐</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">Allgemein</span>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                    onclick="event.stopPropagation(); mgmtModalOpen('function', 'create', null, null);"
                    title="{{ __('management.create_function') }}">+</button>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
    </div>
    <div id="{{ $ckBodyId }}" class="ck-body--flush-top">
        @include('management::_functions-table', ['groupFunctions' => $ckGeneral, 'teamId' => null])
    </div>
</div>

{{-- Sections: one per team — always shown, even when empty --}}
@foreach($ckByTeam as $ckTeamId => $ckGroup)
@php $ckBodyId = 'fn-section-team-' . $ckTeamId; $ckChevronId = 'fn-chevron-team-' . $ckTeamId; @endphp
<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--flush ck-section-header--collapsible ck-section-header--colored ck-section-header--team-{{ $ckGroup['color'] ?? 'blue' }}"
         onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
        <div class="ck-section-header__icon">🏆</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">{{ $ckGroup['name'] }}</span>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                    onclick="event.stopPropagation(); mgmtModalOpen('function', 'create', null, {{ $ckTeamId }});"
                    title="{{ __('management.create_function') }}">+</button>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
    </div>
    <div id="{{ $ckBodyId }}" class="ck-body--flush-top">
        @include('management::_functions-table', ['groupFunctions' => collect($ckGroup['functions']), 'teamId' => $ckTeamId])
    </div>
</div>
@endforeach
