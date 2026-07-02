@extends('core::admin.layout')

@section('title', 'Aktivitätsprotokoll')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Aktivitätsprotokoll</h1>
        <p class="ck-page-subtitle">Alle Aktionen im System – wer wann was geändert hat.</p>
    </div>
</div>

{{-- Filters ──────────────────────────────────────────────────────────────── --}}
<x-ck-card>
    <form method="GET" action="{{ route('admin.activity-log.index') }}" class="ck-filter-form">
        <div class="ck-filter-row">

            {{-- URL format: ?filter[causer_id]=1 --}}
            <x-ck-field
                type="select"
                name="filter[causer_id]"
                label="Benutzer"
                :value="request('filter.causer_id')"
                :options="['' => 'Alle Benutzer'] + $users->pluck('name', 'id')->toArray()"
            />

            <x-ck-field
                type="select"
                name="filter[event]"
                label="Aktion"
                :value="request('filter.event')"
                :options="[
                    ''        => 'Alle Aktionen',
                    'created' => 'Erstellt',
                    'updated' => 'Geändert',
                    'deleted' => 'Gelöscht',
                ]"
            />

            <x-ck-field
                type="select"
                name="filter[log_name]"
                label="Modul"
                :value="request('filter.log_name')"
                :options="['' => 'Alle Module'] + $logNames->combine($logNames)->toArray()"
            />

            <x-ck-field
                type="date"
                name="filter[date_from]"
                label="Von"
                :value="request('filter.date_from')"
            />

            <x-ck-field
                type="date"
                name="filter[date_to]"
                label="Bis"
                :value="request('filter.date_to')"
            />

            <div class="ck-filter-actions">
                <x-ck-button type="submit" variant="primary">{{ __('Filter') }}</x-ck-button>
                <x-ck-button variant="secondary" tag="a" href="{{ route('admin.activity-log.index') }}">{{ __('Reset') }}</x-ck-button>
            </div>

        </div>
    </form>
</x-ck-card>

{{-- Table ───────────────────────────────────────────────────────────────── --}}
<x-ck-card>

    @if($activities->isEmpty())
        <p class="ck-empty-state">Keine Einträge gefunden.</p>
    @else

    <table class="ck-table">
        <thead>
            <tr>
                <x-ck-sort-header column="created_at" label="Zeit" />
                <th>Benutzer</th>
                <x-ck-sort-header column="event"      label="Aktion" />
                <th>Objekt</th>
                <x-ck-sort-header column="log_name"   label="Modul" />
                <th>IP</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activities as $activity)
            <tr>

                {{-- Timestamp --}}
                <td class="ck-table__nowrap">
                    {{ $activity->created_at->format('d.m.Y H:i') }}
                </td>

                {{-- Causer --}}
                <td>
                    @if($activity->causer)
                        {{ $activity->causer->name }}
                    @else
                        <span class="ck-muted">System</span>
                    @endif
                </td>

                {{-- Action badge --}}
                <td>
                    @php
                        $eventLabel = match($activity->event) {
                            'created'  => ['label' => __('Created'),  'color' => 'green'],
                            'updated'  => ['label' => __('Updated'),  'color' => 'blue'],
                            'deleted'  => ['label' => __('Deleted'),  'color' => 'red'],
                            'restored' => ['label' => __('Restored'), 'color' => 'amber'],
                            default    => ['label' => $activity->event ?? '–', 'color' => 'gray'],
                        };
                    @endphp
                    <x-ck-badge :color="$eventLabel['color']">{{ $eventLabel['label'] }}</x-ck-badge>
                </td>

                {{-- Subject (what was changed) --}}
                <td>
                    @if($activity->subject_type)
                        {{ class_basename($activity->subject_type) }}
                        @if($activity->subject_id)
                            <span class="ck-muted">#{{ $activity->subject_id }}</span>
                        @endif
                    @else
                        <span class="ck-muted">–</span>
                    @endif
                    @if($activity->description && $activity->description !== $activity->event)
                        <div class="ck-table__sub">{{ $activity->description }}</div>
                    @endif
                </td>

                {{-- Module --}}
                <td>
                    <span class="ck-muted">{{ $activity->log_name ?? 'default' }}</span>
                </td>

                {{-- IP address (from properties JSON) --}}
                <td class="ck-table__nowrap">
                    <span class="ck-muted">{{ $activity->properties->get('ip', '–') }}</span>
                </td>

                {{-- Changed fields (collapsed) --}}
                <td>
                    @php
                        $attrs = $activity->properties->get('attributes', []);
                        $old   = $activity->properties->get('old', []);
                    @endphp
                    @if(!empty($attrs))
                        <details class="ck-log-details">
                            <summary>{{ count($attrs) }} Feld(er)</summary>
                            <table class="ck-log-diff">
                                @foreach($attrs as $field => $newVal)
                                    <tr>
                                        <td class="ck-log-diff__field">{{ $field }}</td>
                                        @if(isset($old[$field]))
                                            <td class="ck-log-diff__old">{{ $old[$field] ?? '–' }}</td>
                                            <td class="ck-log-diff__arrow">→</td>
                                        @endif
                                        <td class="ck-log-diff__new">{{ $newVal ?? '–' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </details>
                    @else
                        <span class="ck-muted">–</span>
                    @endif
                </td>

            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pagination --}}
    <div class="ck-pagination">
        {{ $activities->links() }}
    </div>

    @endif

</x-ck-card>

@endsection
