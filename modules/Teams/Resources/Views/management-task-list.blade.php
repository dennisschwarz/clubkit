{{--
    Teams hook: team-grouped task list.
    Extension point: management.task.list (replaceable section)
    Registered by: TeamsServiceProvider

    Replaces the flat default list from Management entirely.
    Receives $chevronSvg from the parent view scope (Management/index.blade.php).

    Data injected by View Composer (TeamsServiceProvider::registerViewComposers()):
      $ckDisplay  – filtered tasks collection (by team_id GET parameter)
      $ckGeneral  – tasks without any team assignment
      $ckByTeam   – array[team_id => ['name', 'color', 'tasks[]']] (team-specific)
--}}
@if($ckDisplay->isEmpty())
    <x-ck-card>
        <p class="ck-empty-state">Keine Aufgaben für diesen Team-Filter.</p>
    </x-ck-card>
@else

    @if($ckGeneral->isNotEmpty())
    @php $ckBodyId = 'task-section-general'; $ckChevronId = 'task-chevron-general'; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--collapsible"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon ck-section-header__icon--slate">🌐</div>
            <span class="ck-section-header__title">Allgemein</span>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}">
            @include('management::_tasks-table', ['groupTasks' => $ckGeneral])
        </div>
    </div>
    @endif

    @foreach($ckByTeam as $ckTeamId => $ckGroup)
    @php $ckBodyId = 'task-section-team-' . $ckTeamId; $ckChevronId = 'task-chevron-team-' . $ckTeamId; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--collapsible ck-section-header--colored ck-section-header--team-{{ $ckGroup['color'] ?? 'blue' }}"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon">🏆</div>
            <span class="ck-section-header__title">{{ $ckGroup['name'] }}</span>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}">
            @include('management::_tasks-table', ['groupTasks' => collect($ckGroup['tasks'])])
        </div>
    </div>
    @endforeach

@endif
