{{--
    Management hook: Overview tab on the event detail page.
    Extension point : events.show.overview-panel
    Registered by  : ManagementServiceProvider

    Variables provided by ManagementServiceProvider::composeEventOverviewPanel():
      $mgmtKpiTotalTasks          (int)        total tasks assigned to this event
      $mgmtKpiDoneTasks           (int)        completed tasks
      $mgmtKpiOpenTasks           (int)        open tasks
      $mgmtKpiUnstaffedPrep       (int)        prep tasks without any ETM assignment
      $mgmtOverviewByCategory     (array)      category → {secDone, secTotal, unstaffedCount}
      $mgmtOvFunctions            (array)      {name, member_name|null}
      $mgmtOvTeams                (Collection) {id, name, color}
      $mgmtOvPrepByCategory       (array)      category → [{name, deadline, priority, completed}]
      $mgmtOvDayTasks             (array)      [{id, name, completed}]
      $mgmtOvDayMatrix            (array)      task_id → hour → [{name, initials}]
      $mgmtOvHours                (array)      ['14:00', '15:00', ...]
      $mgmtOvWeekData             (array)      [{label, range, days, members}]
      $mgmtOvActiveKwIdx          (int)        default visible KW index
      $mgmtOvUnstaffedPrepTasks   (array)      names of prep tasks without ETM

    Feature-flag variables (set in show.blade.php @php block, shared via view scope):
      $showTasks     (bool)  Management installed && $event->tasks_enabled
      $showSlots     (bool)  Management installed && $event->slots_enabled
      $showFunctions (bool)  Management installed && $event->functions_enabled
      $teamsInstalled (bool) Schema::hasTable('teams')
--}}

@php
    /**
     * Defensive defaults: the panel may theoretically be rendered outside
     * show.blade.php in tests or previews. Fall back to false so no card
     * renders unexpectedly.
     */
    $showTasks      = $showTasks      ?? false;
    $showSlots      = $showSlots      ?? false;
    $showFunctions  = $showFunctions  ?? false;
    $teamsInstalled = $teamsInstalled ?? false;
@endphp

{{-- ══════════════════════════════════════════════════════════════════════════
     SECTION: Tasks (guarded by $showTasks)
     Includes: KPI tiles + Progress by category
══════════════════════════════════════════════════════════════════════════ --}}

@if($showTasks)

{{-- ── 1. KPI tiles ────────────────────────────────────────────────────────── --}}
{{--
    Split-design tiles (Design System v2):
      .ck-kpi-card__top    → solid accent background, white number
      .ck-kpi-card__bottom → light tint background, coloured label
    Only rendered when there is at least one task.
--}}
<div class="ck-kpi-grid">

    <div class="ck-kpi-card">
        <div class="ck-kpi-card__top">
            <span class="ck-kpi-card__icon">📋</span>
            <span class="ck-kpi-card__value">{{ $mgmtKpiTotalTasks }}</span>
        </div>
        <div class="ck-kpi-card__bottom">
            <span class="ck-kpi-card__label">{{ __('events.kpi.total_tasks') }}</span>
        </div>
    </div>

    <div class="ck-kpi-card ck-kpi-card--success">
        <div class="ck-kpi-card__top">
            <span class="ck-kpi-card__icon">✅</span>
            <span class="ck-kpi-card__value">{{ $mgmtKpiDoneTasks }}</span>
        </div>
        <div class="ck-kpi-card__bottom">
            <span class="ck-kpi-card__label">{{ __('events.kpi.done_tasks') }}</span>
        </div>
    </div>

    <div class="ck-kpi-card ck-kpi-card--warning">
        <div class="ck-kpi-card__top">
            <span class="ck-kpi-card__icon">⏳</span>
            <span class="ck-kpi-card__value">{{ $mgmtKpiOpenTasks }}</span>
        </div>
        <div class="ck-kpi-card__bottom">
            <span class="ck-kpi-card__label">{{ __('events.kpi.open_tasks') }}</span>
        </div>
    </div>

    <div class="ck-kpi-card ck-kpi-card--danger">
        <div class="ck-kpi-card__top">
            <span class="ck-kpi-card__icon">⚠️</span>
            <span class="ck-kpi-card__value">{{ $mgmtKpiUnstaffedPrep }}</span>
        </div>
        <div class="ck-kpi-card__bottom">
            <span class="ck-kpi-card__label">{{ __('events.kpi.unstaffed') }}</span>
        </div>
    </div>

</div>

{{-- ── 2. Progress by category ─────────────────────────────────────────────── --}}
{{--
    Accent "teal" matches the Tasks tab dot colour (Design System v2).
    Progress bars: 14px height (see event-detail.css .ck-cat-progress__bar).
    Bar fill uses CSS custom property --progress set by events-detail.js.
--}}
<x-ck-card accent="teal" :collapsible="true">
    <x-slot:header>
        <span class="ck-card__header-title">📊 {{ __('events.overview.progress_title') }}</span>
    </x-slot:header>

    @if(! empty($mgmtOverviewByCategory))
        @foreach($mgmtOverviewByCategory as $mgmtCatName => $mgmtCatData)
        <div class="ck-cat-progress">
            <div class="ck-cat-progress__header">
                <span class="ck-cat-progress__name">{{ $mgmtCatName }}</span>
                <div class="ck-cat-progress__meta">
                    @if(($mgmtCatData['unstaffedCount'] ?? 0) > 0)
                    <span class="ck-cat-progress__unstaffed">
                        ⚠ {{ $mgmtCatData['unstaffedCount'] }} {{ __('events.overview.unstaffed_label') }}
                    </span>
                    @endif
                    <span class="ck-cat-progress__count">
                        {{ $mgmtCatData['secDone'] }}/{{ $mgmtCatData['secTotal'] }}
                    </span>
                </div>
            </div>
            <div class="ck-cat-progress__bar">
                <div class="ck-cat-progress__fill"
                     data-progress="{{ $mgmtCatData['secTotal'] > 0 ? round($mgmtCatData['secDone'] / $mgmtCatData['secTotal'] * 100) : 0 }}">
                </div>
            </div>
        </div>
        @endforeach
    @else
        <p class="ck-empty-state">{{ __('events.task.empty') }}</p>
    @endif
</x-ck-card>

@endif {{-- $showTasks --}}


{{-- ══════════════════════════════════════════════════════════════════════════
     SECTION: Slots / Schedule (guarded by $showSlots)
     Includes: Zeitplan (preparation schedule) + Staffing matrix (event-day)
══════════════════════════════════════════════════════════════════════════ --}}

@if($showSlots)

{{-- ── 3. Schedule / Zeitplan (Vorbereitungsaufgaben) ─────────────────────── --}}
{{--
    Accent "blue" — calendar/timeline concept, differentiates from Einsatzmatrix (amber).
    Two views toggled by ckZeitplanView() in events-detail.js:
      "week" → Wochenplan (KW grid)
      "cat"  → Nach Kategorie (prep task list)
--}}
<x-ck-card accent="blue" :collapsible="true">
    <x-slot:header>
        <span class="ck-card__header-title">📅 {{ __('events.overview.zeitplan_title') }}</span>
    </x-slot:header>

    {{-- Toolbar: view toggle + KW navigation. data-view drives CSS for KW nav. --}}
    <div class="ck-zeitplan-toolbar" id="ckZeitplanToolbar" data-view="week">
        <div class="ck-zeitplan-toggle">
            <button type="button"
                    class="ck-zeitplan-toggle__btn ck-zeitplan-toggle__btn--active"
                    onclick="ckZeitplanView('week', this)">
                📅 {{ __('events.overview.zeitplan_week') }}
            </button>
            <button type="button"
                    class="ck-zeitplan-toggle__btn"
                    onclick="ckZeitplanView('cat', this)">
                📋 {{ __('events.overview.zeitplan_cat') }}
            </button>
        </div>

        {{-- KW navigation arrows (Wochenplan view only) --}}
        @if(! empty($mgmtOvWeekData))
        <div class="ck-kw-nav" id="ckZeitplanKwNav">
            <button type="button"
                    class="ck-kw-nav__btn"
                    id="ckKwPrev"
                    onclick="ckKwNav(-1)"
                    {{ $mgmtOvActiveKwIdx === 0 ? 'disabled' : '' }}>
                ‹
            </button>
            <div class="ck-kw-nav__info">
                <span class="ck-kw-nav__label" id="ckKwNavLabel">
                    {{ $mgmtOvWeekData[$mgmtOvActiveKwIdx]['label'] ?? '' }}
                </span>
                <span class="ck-kw-nav__range" id="ckKwNavRange">
                    {{ $mgmtOvWeekData[$mgmtOvActiveKwIdx]['range'] ?? '' }}
                </span>
            </div>
            <button type="button"
                    class="ck-kw-nav__btn"
                    id="ckKwNext"
                    onclick="ckKwNav(1)"
                    {{ $mgmtOvActiveKwIdx >= (count($mgmtOvWeekData) - 1) ? 'disabled' : '' }}>
                ›
            </button>
        </div>
        @endif
    </div>

    {{-- Wochenplan view --}}
    <div id="ckZeitplanWeek" data-active-idx="{{ $mgmtOvActiveKwIdx }}">
        @if(! empty($mgmtOvWeekData))
            @foreach($mgmtOvWeekData as $kwIdx => $kw)
            <div class="ck-kw-pane {{ $kwIdx === $mgmtOvActiveKwIdx ? 'ck-kw-pane--active' : '' }}"
                 id="ckKwPane-{{ $kwIdx }}"
                 data-kw-label="{{ $kw['label'] }}"
                 data-kw-range="{{ $kw['range'] }}">

                {{-- Member column (left) --}}
                <div class="ck-kw-pane__members">
                    <div class="ck-kw-col-header">{{ __('events.kw.member_col') }}</div>

                    @forelse($kw['members'] as $kwMember)
                    <div class="ck-kw-member">
                        <div class="ck-avatar ck-avatar--sm" title="{{ $kwMember['name'] }}">
                            @if($kwMember['photo'])
                                <img src="{{ asset('storage/' . $kwMember['photo']) }}"
                                     alt="{{ $kwMember['name'] }}">
                            @else
                                {{ $kwMember['initials'] }}
                            @endif
                        </div>
                        <div>
                            <div class="ck-kw-member__name">{{ $kwMember['name'] }}</div>
                            <div class="ck-kw-member__count">
                                {{ $kwMember['done'] }}/{{ $kwMember['total'] }} {{ __('events.kw.tasks_count') }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="ck-kw-member">
                        <span class="ck-text-muted">{{ __('events.kw.no_assignments') }}</span>
                    </div>
                    @endforelse
                </div>

                {{-- Day grid (right, scrollable) --}}
                <div class="ck-kw-pane__days">
                    {{-- Day header row --}}
                    <div class="ck-kw-day-header">
                        @foreach($kw['days'] as $kwDay)
                        <div class="ck-kw-day {{ $kwDay['isEvent'] ? 'ck-kw-day--event' : '' }}">
                            <span class="ck-kw-day__wd">
                                {{ $kwDay['wd'] }}{{ $kwDay['isEvent'] ? ' 🎯' : '' }}
                            </span>
                            <span class="ck-kw-day__date">{{ $kwDay['short'] }}</span>
                        </div>
                        @endforeach
                    </div>

                    {{-- Member × day data rows --}}
                    @foreach($kw['members'] as $kwMember)
                    <div class="ck-kw-row">
                        @foreach($kw['days'] as $kwDay)
                        <div class="ck-kw-cell {{ $kwDay['isEvent'] ? 'ck-kw-cell--event' : '' }}">
                            @foreach($kwMember['byDate'][$kwDay['date']] ?? [] as $kwTask)
                            <div class="ck-kw-task {{ $kwTask['completed'] ? 'ck-kw-task--done' : '' }}"
                                 title="{{ $kwTask['name'] }}">
                                {{ $kwTask['name'] }}
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                    @endforeach

                    {{-- Empty placeholder row for weeks without member assignments --}}
                    @if(empty($kw['members']))
                    <div class="ck-kw-row">
                        @foreach($kw['days'] as $kwDay)
                        <div class="ck-kw-cell {{ $kwDay['isEvent'] ? 'ck-kw-cell--event' : '' }}"></div>
                        @endforeach
                    </div>
                    @endif
                </div>

            </div>{{-- /.ck-kw-pane --}}
            @endforeach
        @else
            <p class="ck-empty-state">{{ __('events.overview.zeitplan_empty') }}</p>
        @endif
    </div>

    {{-- Nach Kategorie view (hidden by default, toggled by ckZeitplanView) --}}
    <div id="ckZeitplanCat" class="is-hidden">
        @if(! empty($mgmtOvPrepByCategory))
        <div class="ck-zeitplan-catview">
            @foreach($mgmtOvPrepByCategory as $mgmtPrepCat => $mgmtPrepTasks)
            <div class="ck-ov-prep-category">
                <div class="ck-ov-prep-category__name">{{ $mgmtPrepCat }}</div>
                <ul class="ck-ov-prep-list">
                    @foreach($mgmtPrepTasks as $mgmtPrepTask)
                    <li class="ck-ov-prep-list__item {{ $mgmtPrepTask['completed'] ? 'is-done' : '' }}">
                        <span class="ck-ov-prep-list__check">
                            {{ $mgmtPrepTask['completed'] ? '✓' : '○' }}
                        </span>
                        <span class="ck-ov-prep-list__name">{{ $mgmtPrepTask['name'] }}</span>
                        <span class="ck-ov-prep-list__deadline">{{ $mgmtPrepTask['deadline'] }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
        @else
            <p class="ck-empty-state">{{ __('events.overview.zeitplan_empty') }}</p>
        @endif
    </div>

    {{-- Footer: prep tasks without any member assignment --}}
    @if(! empty($mgmtOvUnstaffedPrepTasks))
    <div class="ck-zeitplan-unbesetzt">
        <span class="ck-zeitplan-unbesetzt__label">⚠ {{ __('events.overview.unstaffed_prep_label') }}</span>
        @foreach($mgmtOvUnstaffedPrepTasks as $mgmtUnbesetztName)
        <span class="ck-zeitplan-unbesetzt__tag">{{ $mgmtUnbesetztName }}</span>
        @endforeach
    </div>
    @endif

</x-ck-card>

{{-- ── 4. Staffing matrix (event-day tasks × hour grid) ───────────────────── --}}
{{--
    Amber — Einsatzplan/staffing operations (distinct from Zeitplan which is now blue).
    Shows event-day tasks (those without a prep deadline) mapped across
    hour columns, with member avatars in each cell.
--}}
<x-ck-card accent="amber" :collapsible="true">
    <x-slot:header>
        <span class="ck-card__header-title">🗓️ {{ __('events.overview.matrix_title') }}</span>
    </x-slot:header>

    @if(! empty($mgmtOvDayTasks) && ! empty($mgmtOvHours))
    <div class="ck-ov-matrix-wrap">
        <table class="ck-ov-matrix">
            <thead>
                <tr>
                    <th class="ck-ov-matrix__task-col">{{ __('events.slot.col_task') }}</th>
                    @foreach($mgmtOvHours as $mgmtOvHour)
                    <th class="ck-ov-matrix__hour-col">{{ $mgmtOvHour }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($mgmtOvDayTasks as $mgmtDayTask)
                <tr class="{{ $mgmtDayTask['completed'] ? 'ck-ov-matrix__row--done' : '' }}">
                    <td class="ck-ov-matrix__task-name">{{ $mgmtDayTask['name'] }}</td>
                    @foreach($mgmtOvHours as $mgmtOvHour)
                    <td class="ck-ov-matrix__cell">
                        @if(! empty($mgmtOvDayMatrix[$mgmtDayTask['id']][$mgmtOvHour]))
                        <div class="ck-ov-matrix__av-wrap">
                            @foreach($mgmtOvDayMatrix[$mgmtDayTask['id']][$mgmtOvHour] as $mgmtSlotMember)
                            <div class="ck-avatar ck-avatar--sm" title="{{ $mgmtSlotMember['name'] }}">
                                {{ $mgmtSlotMember['initials'] }}
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @elseif(! empty($mgmtOvDayTasks))
    {{-- Day tasks exist but no hour columns — fallback without grid --}}
    <div class="ck-ov-matrix-tasks">
        @foreach($mgmtOvDayTasks as $mgmtDayTask)
        <div class="ck-ov-matrix-tasks__row">
            <span>{{ $mgmtDayTask['name'] }}</span>
            <x-ck-badge color="gray">{{ __('events.overview.unstaffed') }}</x-ck-badge>
        </div>
        @endforeach
    </div>
    @else
    <p class="ck-empty-state">{{ __('events.overview.matrix_empty') }}</p>
    @endif
</x-ck-card>

@endif {{-- $showSlots --}}


{{-- Functions summary was moved to the hero card right column.
     See: management::event-hero-functions + events.show.hero-right hook. --}}


{{-- ══════════════════════════════════════════════════════════════════════════
     SECTION: Teams (guarded by $teamsInstalled — module presence, not a flag)
     Teams are shown whenever the Teams module is installed, regardless of
     per-event feature flags. There is no dedicated teams_enabled flag.
══════════════════════════════════════════════════════════════════════════ --}}

@if($teamsInstalled)

{{-- ── 6. Teams assigned to this event ────────────────────────────────────── --}}
{{-- Accent "green" matches the Teams concept colour (Design System v2). --}}
<x-ck-card accent="green" :collapsible="true">
    <x-slot:header>
        <span class="ck-card__header-title">👥 {{ __('events.overview.teams_title') }}</span>
    </x-slot:header>

    @if($mgmtOvTeams->isNotEmpty())
    <div class="ck-tag-list">
        @foreach($mgmtOvTeams as $mgmtOvTeam)
        <x-ck-badge :color="'team-' . ($mgmtOvTeam->color ?? 'default')">
            {{ $mgmtOvTeam->name }}
        </x-ck-badge>
        @endforeach
    </div>
    @else
    <p class="ck-empty-state">{{ __('events.teams.empty') }}</p>
    @endif
</x-ck-card>

@endif {{-- $teamsInstalled --}}