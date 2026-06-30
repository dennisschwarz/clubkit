@extends('core::admin.layout')

@section('title', 'Aktivitätsprotokoll')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Aktivitätsprotokoll</h1>
        <p class="ck-page-subtitle">Alle Aktionen im System – wer wann was geändert hat.</p>
    </div>
</div>

{{-- Filter ──────────────────────────────────────────────────────────────── --}}
<x-ck-card>
    <form method="GET" action="{{ route('admin.activity-log.index') }}" class="ck-filter-form">
        <div class="ck-filter-row">

            <x-ck-field
                type="select"
                name="causer_id"
                label="Benutzer"
                :value="request('causer_id')"
                :options="['' => 'Alle Benutzer'] + $users->pluck('name', 'id')->toArray()"
            />

            <x-ck-field
                type="select"
                name="event"
                label="Aktion"
                :value="request('event')"
                :options="[
                    ''        => 'Alle Aktionen',
                    'created' => 'Erstellt',
                    'updated' => 'Geändert',
                    'deleted' => 'Gelöscht',
                ]"
            />

            <x-ck-field
                type="select"
                name="log_name"
                label="Modul"
                :value="request('log_name')"
                :options="['' => 'Alle Module'] + $logNames->combine($logNames)->toArray()"
            />

            <x-ck-field
                type="date"
                name="date_from"
                label="Von"
                :value="request('date_from')"
            />

            <x-ck-field
                type="date"
                name="date_to"
                label="Bis"
                :value="request('date_to')"
            />

            <div class="ck-filter-actions">
                <x-ck-button type="submit" variant="primary">Filtern</x-ck-button>
                <x-ck-button variant="secondary" tag="a" href="{{ route('admin.activity-log.index') }}">Zurücksetzen</x-ck-button>
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
                <th>Zeit</th>
                <th>Benutzer</th>
                <th>Aktion</th>
                <th>Objekt</th>
                <th>Modul</th>
                <th>IP</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activities as $activity)
            <tr>

                {{-- Time --}}
                <td class="ck-table__nowrap">
                    {{ $activity->created_at->format('d.m.Y H:i') }}
                </td>

                {{-- Causer (user who triggered the action) --}}
                <td>
                    @if($activity->causer)
                        {{ $activity->causer->name }}
                    @else
                        <span class="ck-muted">System</span>
                    @endif
                </td>

                {{-- Event badge --}}
                <td>
                    @php
                        $eventLabel = match($activity->event) {
                            'created'  => ['label' => 'Erstellt',  'color' => 'green'],
                            'updated'  => ['label' => 'Geändert',  'color' => 'blue'],
                            'deleted'  => ['label' => 'Gelöscht',  'color' => 'red'],
                            'restored' => ['label' => 'Wiederhergestellt', 'color' => 'amber'],
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

                {{-- Log name (module) --}}
                <td>
                    <span class="ck-muted">{{ $activity->log_name ?? 'default' }}</span>
                </td>

                {{-- IP address (stored inside properties JSON) --}}
                <td class="ck-table__nowrap">
                    <span class="ck-muted">{{ $activity->properties->get('ip', '–') }}</span>
                </td>

                {{-- Changed attributes (collapsed summary) --}}
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
