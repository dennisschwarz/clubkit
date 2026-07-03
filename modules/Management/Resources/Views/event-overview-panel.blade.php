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
--}}

{{-- Render nothing if no tasks are assigned to this event yet --}}
@if(empty($mgmtOverviewByCategory))
    @php return; @endphp
@endif

{{-- ── KPI tiles ────────────────────────────────────────────────────────────── --}}
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

{{-- ── Fortschritt nach Kategorie ────────────────────────────────────────────── --}}
<x-ck-card>
    <x-slot:header>{{ __('events.overview.progress_title') }}</x-slot:header>
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
</x-ck-card>
