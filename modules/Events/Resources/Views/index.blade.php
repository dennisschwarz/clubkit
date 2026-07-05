@extends('core::admin.layout')
@section('title', 'Termine')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Termine</h1>
        <p class="ck-page-subtitle">{{ $events->total() }} Termin{{ $events->total() !== 1 ? 'e' : '' }} gesamt</p>
    </div>
    <x-ck-button variant="primary" onclick="evtModalOpen()">
        + Termin anlegen
    </x-ck-button>
</div>

@if($events->isEmpty())
<x-ck-card>
    <p class="ck-empty-state">
        Noch keine Termine angelegt.
        <a href="javascript:void(0)" onclick="evtModalOpen()">Jetzt anlegen</a>
    </p>
</x-ck-card>
@else

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <x-ck-sort-header column="starts_at" label="Datum / Zeit" />
                <x-ck-sort-header column="title"     label="Titel" />
                <x-ck-sort-header column="location"  label="Ort" />
                {{--
                    Teams injects the <th> for the teams column here.
                    Extension point: event.table.teams.header
                    Registered by: TeamsServiceProvider
                    Only present when Teams is active.
                --}}
                @ckHook('event.table.teams.header')
                <th>Besetzung</th>
                @ckHook('event.table.header')
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
            @php $isPast = $event->starts_at->isPast(); @endphp
            <tr class="{{ $isPast ? 'ck-table__row--muted' : '' }}">

                {{-- Date / time --}}
                <td class="ck-event-date">
                    <span class="ck-event-date__day">{{ $event->starts_at->format('d.m.Y') }}</span>
                    <span class="ck-event-date__time">
                        {{ $event->starts_at->format('H:i') }} Uhr
                        @if($event->ends_at)– {{ $event->ends_at->format('H:i') }}@endif
                    </span>
                </td>

                {{-- Title + short description --}}
                <td>
                    <a href="{{ route('events.show', $event) }}" class="ck-table__link">
                        {{ $event->title }}
                    </a>
                    @if($event->description)
                        <span class="ck-event-desc">{{ Str::limit($event->description, 60) }}</span>
                    @endif
                </td>

                {{-- Location --}}
                <td>{{ $event->location ?? '—' }}</td>

                {{--
                    Teams injects the <td> teams-badges cell here.
                    Extension point: event.table.teams.row
                    Registered by: TeamsServiceProvider
                --}}
                @ckHook('event.table.teams.row')

                {{-- Staffing: content is injected by Management via hook --}}
                <td>
                    {{--
                        Extension point: event.table.staffing.row
                        Registered by: ManagementServiceProvider
                        Renders function and task badges.
                        Without Management: empty cell with –
                    --}}
                    @if(app('ck.hooks')->has('event.table.staffing.row'))
                        @ckHook('event.table.staffing.row')
                    @else
                        <span class="ck-text-muted">—</span>
                    @endif
                </td>

                @ckHook('event.table.row')

                {{-- Actions --}}
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <x-ck-button :href="route('events.show', $event)" variant="secondary" size="sm">
                            {{ __('Edit') }}
                        </x-ck-button>
                        <form method="POST" action="{{ route('events.destroy', $event) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="'Termin »' . $event->title . '« wirklich löschen?'">
                                {{ __('Delete') }}
                            </x-ck-button>
                        </form>
                    </div>
                </td>

            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($events->hasPages())
<div class="ck-table__pagination ck-table__pagination--standalone">{{ $events->links() }}</div>
@endif
@endif

{{-- ══ Quick-Create Modal ══════════════════════════════════════════════════════ --}}
<x-ck-modal id="evtModal" title="Termin anlegen" size="md">

    <form id="evtForm" method="POST" action="{{ route('events.store') }}">
        @csrf

        <div class="ck-orga-section ck-orga-section--blue">
            <div class="ck-orga-section__head">📌 Wann &amp; Wo</div>
            <div class="ck-orga-section__body">
                <x-ck-field label="Bezeichnung" name="title" id="evtTitle" :required="true" />
                <div class="ck-form-grid ck-form-grid--2">
                    <x-ck-field type="text" label="Beginn" name="starts_at"
                        id="evtStartsAt" :required="true" data-ck-datetime="1" />
                    <x-ck-field type="text" label="Ende (optional)" name="ends_at"
                        id="evtEndsAt" data-ck-datetime="1" />
                </div>
                <x-ck-field label="Ort" name="location" id="evtLocation"
                    placeholder="z.B. Vereinsheim, Sportplatz" />
            </div>
        </div>

        <div class="ck-orga-section ck-orga-section--neutral ck-mt-4">
            <div class="ck-orga-section__head">📝 Beschreibung &amp; Notizen</div>
            <div class="ck-orga-section__body">
                <x-ck-field type="textarea" label="Beschreibung (optional)"
                    name="description" id="evtDescription"
                    placeholder="Kurze Beschreibung des Termins." />
                <x-ck-field type="textarea" label="Interne Notizen"
                    name="notes" id="evtNotes"
                    placeholder="Nur für Administratoren sichtbar." />
            </div>
        </div>

        @ckHook('event.modal.sections')

        <div class="ck-form-actions">
            <x-ck-button type="submit" variant="primary">{{ __('Create') }}</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'evtModal')">
                {{ __('Cancel') }}
            </x-ck-button>
        </div>

    </form>
</x-ck-modal>

@push('scripts')
<script>
    window.CK_Events = {
        routes: {
            store: "{{ route('events.store') }}"
        }
    };
</script>
@vite(['resources/js/modules/events-modal.js'])
@ckHook('event.page.scripts')
@endpush

@endsection
