{{--
    Teams hook: team-grouped task list.
    Extension point: management.task.list (replaceable section)
    Registered by: TeamsServiceProvider

    Replaces the flat default list from Management entirely.
    Receives $chevronSvg from the parent view scope (Management/index.blade.php).

    Data injected by View Composer (TeamsServiceProvider::registerViewComposers()):
      $ckDisplay  – filtered tasks collection (by team_id GET parameter)
      $ckGeneral  – tasks without any team assignment
      $ckByTeam   – array[team_id => ['name', 'color', 'tasks[]']] — ALL teams seeded
--}}

{{-- Section: General (no team) — always shown --}}
@php $ckBodyId = 'task-section-general'; $ckChevronId = 'task-chevron-general'; @endphp
<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--flush ck-section-header--collapsible ck-section-header--colored ck-section-header--team-steel"
         onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
        <div class="ck-section-header__icon">🌐</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">Allgemein</span>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                    onclick="event.stopPropagation(); mgmtModalOpen('task', 'create', null, null);"
                    title="{{ __('management.create_task') }}">+</button>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
    </div>
    <div id="{{ $ckBodyId }}" class="ck-body--flush-top">
        @include('management::_tasks-table', ['groupTasks' => $ckGeneral, 'teamId' => null])
    </div>
</div>

{{-- Sections: one per team — always shown, even when empty --}}
@foreach($ckByTeam as $ckTeamId => $ckGroup)
@php $ckBodyId = 'task-section-team-' . $ckTeamId; $ckChevronId = 'task-chevron-team-' . $ckTeamId; @endphp
<div class="ck-mb-5">
    <div class="ck-section-header ck-section-header--flush ck-section-header--collapsible ck-section-header--colored ck-section-header--team-{{ $ckGroup['color'] ?? 'blue' }}"
         onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
        <div class="ck-section-header__icon">🏆</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">{{ $ckGroup['name'] }}</span>
        </div>
        <div class="ck-section-header__actions" onclick="event.stopPropagation()">
            <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                    onclick="event.stopPropagation(); mgmtModalOpen('task', 'create', null, {{ $ckTeamId }});"
                    title="{{ __('management.create_task') }}">+</button>
        </div>
        <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
    </div>
    <div id="{{ $ckBodyId }}" class="ck-body--flush-top">
        @include('management::_tasks-table', ['groupTasks' => collect($ckGroup['tasks']), 'teamId' => $ckTeamId])
    </div>
</div>
@endforeach
