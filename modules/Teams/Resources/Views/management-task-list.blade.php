{{--
    Teams hook: task list with team/category grouping toggle.
    Extension point: management.task.list (replaceable section)
    Registered by: TeamsServiceProvider

    View Composer data (TeamsServiceProvider::registerViewComposers()):
      $ckGeneral         – tasks without any team assignment    (team view)
      $ckByTeam          – array[team_id => ['name','color','tasks[]']]
      $ckCategoryGeneral – tasks without a category             (category view)
      $ckByCategory      – array[category_id => ['name','color','tasks[]']]
--}}
@php $ckGroupMode = request('task_group', 'team'); @endphp

{{-- ── Grouping toggle ────────────────────────────────────────────────────── --}}
<div class="ck-mgmt-group-toggle ck-mb-4">
    <a href="{{ request()->fullUrlWithQuery(['task_group' => 'team', 'tab' => 'aufgaben']) }}"
       class="ck-btn ck-btn--sm {{ $ckGroupMode !== 'category' ? 'ck-btn--primary' : 'ck-btn--secondary' }}">
        🏆 {{ __('management.group.by_team') }}
    </a>
    <a href="{{ request()->fullUrlWithQuery(['task_group' => 'category', 'tab' => 'aufgaben']) }}"
       class="ck-btn ck-btn--sm {{ $ckGroupMode === 'category' ? 'ck-btn--primary' : 'ck-btn--secondary' }}">
        🏷️ {{ __('management.group.by_category') }}
    </a>
</div>

@if($ckGroupMode === 'category')

    {{-- ══ CATEGORY VIEW ═══════════════════════════════════════════════════════ --}}

    {{-- Section: General (no category) — always shown --}}
    @php $ckBodyId = 'task-section-cat-general'; $ckChevronId = 'task-chevron-cat-general'; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--flush ck-section-header--collapsible ck-section-header--colored ck-section-header--team-steel"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon">🌐</div>
            <div class="ck-section-header__text">
                <span class="ck-section-header__title">Allgemein</span>
            </div>
            <div class="ck-section-header__actions" onclick="event.stopPropagation()">
                <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                        onclick="event.stopPropagation(); mgmtModalOpen('task', 'create', null, null, null);"
                        title="{{ __('management.create_task') }}">+</button>
            </div>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}" class="ck-body--flush-top">
            @include('management::_tasks-table', ['groupTasks' => $ckCategoryGeneral, 'teamId' => null, 'categoryId' => 'allgemein'])
        </div>
    </div>

    {{-- Sections: one per category — always shown, even when empty --}}
    @foreach($ckByCategory as $ckCatId => $ckGroup)
    @php $ckBodyId = 'task-section-cat-' . $ckCatId; $ckChevronId = 'task-chevron-cat-' . $ckCatId; @endphp
    <div class="ck-mb-5">
        <div class="ck-section-header ck-section-header--flush ck-section-header--collapsible ck-section-header--colored ck-section-header--team-{{ $ckGroup['color'] ?? 'blue' }}"
             onclick="ckSectionToggle('{{ $ckBodyId }}', '{{ $ckChevronId }}')">
            <div class="ck-section-header__icon">🏷️</div>
            <div class="ck-section-header__text">
                <span class="ck-section-header__title">{{ $ckGroup['name'] }}</span>
            </div>
            <div class="ck-section-header__actions" onclick="event.stopPropagation()">
                <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                        onclick="event.stopPropagation(); mgmtModalOpen('task', 'create', null, null, {{ $ckCatId }});"
                        title="{{ __('management.create_task') }}">+</button>
            </div>
            <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $ckChevronId }}">{!! $chevronSvg !!}</span>
        </div>
        <div id="{{ $ckBodyId }}" class="ck-body--flush-top">
            @include('management::_tasks-table', ['groupTasks' => collect($ckGroup['tasks']), 'teamId' => null, 'categoryId' => $ckCatId])
        </div>
    </div>
    @endforeach

@else

    {{-- ══ TEAM VIEW (existing) ════════════════════════════════════════════════ --}}

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

@endif
