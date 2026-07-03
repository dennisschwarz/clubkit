{{--
    Management hook: Overview tab content on the event detail page.
    Extension point: events.show.overview-panel
    Registered by: ManagementServiceProvider

    Data injected by ManagementServiceProvider::composeEventOverviewPanel():
      $mgmtOverviewByCategory → array<string, array{secDone, secTotal}>
      $mgmtKpiTotalTasks      → int
      $mgmtKpiDoneTasks       → int
      $mgmtKpiPeopleCount     → int
      $mgmtKpiSlotsCount      → int
      $mgmtOvFunctions        → array<array{name, member_name: string|null}>
      $mgmtOvTeams            → Collection<object{id, name, color}>
--}}

{{-- ── KPI tiles — always visible (4 summary numbers) ───────────────────────── --}}
<div class="ck-kpi-grid">
    <div class="ck-kpi-card">
        <span class="ck-kpi-card__value">{{ $mgmtKpiTotalTasks }}</span>
        <span class="ck-kpi-card__label">{{ __('events.kpi.total_tasks') }}</span>
    </div>
    <div class="ck-kpi-card">
        <span class="ck-kpi-card__value">{{ $mgmtKpiDoneTasks }}</span>
        <span class="ck-kpi-card__label">{{ __('events.kpi.done_tasks') }}</span>
    </div>
    <div class="ck-kpi-card">
        <span class="ck-kpi-card__value">{{ $mgmtKpiPeopleCount }}</span>
        <span class="ck-kpi-card__label">{{ __('events.kpi.people') }}</span>
    </div>
    <div class="ck-kpi-card">
        <span class="ck-kpi-card__value">{{ $mgmtKpiSlotsCount }}</span>
        <span class="ck-kpi-card__label">{{ __('events.kpi.slots') }}</span>
    </div>
</div>

{{-- ── Task progress by category (always visible) ─────────────────────────── --}}
<x-ck-card>
    <x-slot:header>📋 {{ __('events.tab.tasks') }}</x-slot:header>
    @if(! empty($mgmtOverviewByCategory))
        @foreach($mgmtOverviewByCategory as $mgmtCatName => $mgmtCatData)
        <div class="ck-cat-progress">
            <div class="ck-cat-progress__header">
                <span class="ck-cat-progress__name">{{ $mgmtCatName }}</span>
                <span class="ck-cat-progress__count">
                    {{ $mgmtCatData['secDone'] }}/{{ $mgmtCatData['secTotal'] }}
                </span>
            </div>
            <div class="ck-cat-progress__bar">
                <div class="ck-cat-progress__fill"
                     data-progress="{{ $mgmtCatData['secTotal'] > 0 ? round($mgmtCatData['secDone'] / $mgmtCatData['secTotal'] * 100) : 0 }}">
                </div>
            </div>
        </div>
        @endforeach
    @else
        <p class="ck-muted">{{ __('events.task.empty') }}</p>
    @endif
</x-ck-card>

{{-- ── Einsatzplan summary (always visible) ───────────────────────────────── --}}
<x-ck-card>
    <x-slot:header>🗓️ {{ __('events.tab.slots') }}</x-slot:header>
    @if($mgmtKpiSlotsCount > 0)
        <p class="ck-muted">{{ $mgmtKpiSlotsCount }} {{ __('events.kpi.slots') }}</p>
    @else
        <p class="ck-muted">{{ __('events.overview.slots_empty') }}</p>
    @endif
</x-ck-card>

{{-- ── Functions summary (always visible) ────────────────────────────────── --}}
<x-ck-card>
    <x-slot:header>⚙️ {{ __('events.overview.functions_title') }}</x-slot:header>
    @if(! empty($mgmtOvFunctions))
        <ul class="ck-overview-summary">
            @foreach($mgmtOvFunctions as $mgmtOvFn)
            <li class="ck-overview-summary__item">
                <span>{{ $mgmtOvFn['name'] }}</span>
                @if($mgmtOvFn['member_name'])
                    <x-ck-badge color="green">{{ $mgmtOvFn['member_name'] }}</x-ck-badge>
                @else
                    <x-ck-badge color="red">{{ __('events.overview.unstaffed') }}</x-ck-badge>
                @endif
            </li>
            @endforeach
        </ul>
    @else
        <p class="ck-muted">{{ __('events.function.empty') }}</p>
    @endif
</x-ck-card>

{{-- ── Teams summary (always visible) ────────────────────────────────────── --}}
<x-ck-card>
    <x-slot:header>👥 {{ __('events.overview.teams_title') }}</x-slot:header>
    @if($mgmtOvTeams->isNotEmpty())
        <div class="ck-tag-list">
            @foreach($mgmtOvTeams as $mgmtOvTeam)
                <x-ck-badge :color="'team-' . ($mgmtOvTeam->color ?? 'default')">{{ $mgmtOvTeam->name }}</x-ck-badge>
            @endforeach
        </div>
    @else
        <p class="ck-muted">{{ __('events.teams.empty') }}</p>
    @endif
</x-ck-card>