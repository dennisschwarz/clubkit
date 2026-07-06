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
                        <x-ck-button :href="route('events.show', $event)" variant="warning" size="icon"
                            title="{{ __('Edit') }}">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                        </x-ck-button>
                        <form method="POST" action="{{ route('events.destroy', $event) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="icon" type="submit"
                                title="{{ __('Delete') }}"
                                :confirm="'Termin »' . $event->title . '« wirklich löschen?'">
                                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
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
{{-- Compact layout: no section-header wrappers, feature-flag toggles at bottom. --}}
<x-ck-modal id="evtModal" title="{{ __('events.modal.create_title') }}" size="md">

    <form id="evtForm" method="POST" action="{{ route('events.store') }}">
        @csrf

        {{-- Core fields — no decorative section headers, just clean spacing --}}
        <x-ck-field label="{{ __('events.field.title') }}" name="title"
            id="evtTitle" :required="true" />

        <div class="ck-form-grid ck-form-grid--2 ck-mt-4">
            <x-ck-field type="text" label="{{ __('events.field.starts_at') }}" name="starts_at"
                id="evtStartsAt" :required="true" data-ck-datetime="1" />
            <x-ck-field type="text" label="{{ __('events.field.ends_at') }}" name="ends_at"
                id="evtEndsAt" data-ck-datetime="1" />
        </div>

        <x-ck-field label="{{ __('events.field.location') }}" name="location"
            id="evtLocation" class="ck-mt-4"
            placeholder="{{ __('events.field.location_placeholder') }}" />

        <x-ck-field type="textarea" label="{{ __('events.field.description') }}"
            name="description" id="evtDescription" class="ck-mt-4" rows="2"
            placeholder="{{ __('events.field.description_placeholder') }}" />

        {{-- Internal notes hidden by default behind a <details> toggle --}}
        <details class="ck-mt-3">
            <summary class="ck-text-muted" style="cursor:pointer;font-size:var(--ck-font-sm);user-select:none;">
                {{ __('events.field.notes_toggle') }}
            </summary>
            <div class="ck-mt-2">
                <x-ck-field type="textarea" label="{{ __('events.field.notes') }}"
                    name="notes" id="evtNotes" rows="2"
                    placeholder="{{ __('events.field.notes_placeholder') }}" />
            </div>
        </details>

        @ckHook('event.modal.sections')

        {{-- Feature flags — only shown when Management module is installed --}}
        @if($managementInstalled)
        <div class="ck-event-flags-section">
            <div class="ck-event-flags-section__label">{{ __('events.field.active_features') }}</div>
            <div class="ck-form-grid ck-form-grid--3">
                <label class="ck-field__checkbox">
                    <input type="checkbox" name="tasks_enabled" value="1" checked>
                    📋 {{ __('events.feature.tasks') }}
                </label>
                <label class="ck-field__checkbox">
                    <input type="checkbox" name="functions_enabled" value="1" checked>
                    ⚙️ {{ __('events.feature.functions') }}
                </label>
                <label class="ck-field__checkbox">
                    <input type="checkbox" name="slots_enabled" value="1" checked>
                    🗓️ {{ __('events.feature.slots') }}
                </label>
            </div>
        </div>
        @endif

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