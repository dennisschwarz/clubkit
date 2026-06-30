@extends('core::admin.layout')
@section('title', 'Eigene Felder – ' . $objectTypeLabel)

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Eigene Felder: {{ $objectTypeLabel }}</h1>
        <p class="ck-page-subtitle">{{ $entities->count() }} {{ $objectTypeLabel }}{{ $entities->count() !== 1 ? 'n' : '' }} · {{ $definitions->count() }} {{ $definitions->count() === 1 ? 'Feld' : 'Felder' }}</p>
    </div>
    <a href="{{ route('custom-fields.index') }}" class="ck-btn ck-btn--secondary">
        ← Zurück zu Felddefinitionen
    </a>
</div>

@if($definitions->isEmpty())
<x-ck-card>
    <p class="ck-empty-state">
        Noch keine Felder für {{ $objectTypeLabel }} konfiguriert.<br>
        <a href="{{ route('custom-fields.index') }}">Jetzt Felder anlegen →</a>
    </p>
</x-ck-card>
@elseif($entities->isEmpty())
<x-ck-card>
    <p class="ck-empty-state">
        Noch keine {{ $objectTypeLabel }}en vorhanden.
    </p>
</x-ck-card>
@else

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>{{ $objectTypeLabel }}</th>
                @foreach($definitions as $def)
                <th>{{ $def->label }}</th>
                @endforeach
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entities as $entity)
            @php $entityValues = $valuesByEntity[$entity->id] ?? []; @endphp
            <tr>
                <td class="ck-table__bold">{{ $entity->_label }}</td>
                @foreach($definitions as $def)
                <td>
                    @php $val = $entityValues[$def->id] ?? null; @endphp
                    @if($def->field_type === 'checkbox')
                        @if($val === '1')
                            <x-ck-badge color="green">✓ Ja</x-ck-badge>
                        @else
                            <span class="ck-text-muted">—</span>
                        @endif
                    @elseif($val !== null && $val !== '')
                        {{ $val }}
                    @else
                        <span class="ck-text-muted">—</span>
                    @endif
                </td>
                @endforeach
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="warning" size="sm"
                            onclick="cfValModalOpen({{ $entity->id }}, '{{ addslashes($entity->_label) }}')">
                            Bearbeiten
                        </x-ck-button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endif

{{-- ══ Feldwerte-Modal ════════════════════════════════════════════════════ --}}
@if($definitions->isNotEmpty())
<x-ck-modal id="cfValModal" title="Felder bearbeiten" size="md">
    <div class="ck-modal__section ck-modal__section--active">
        <form id="cfValForm" method="POST">
            @csrf
            <p class="ck-modal-entity-label" id="cfValEntityLabel"></p>

            @foreach($definitions as $def)
            <div class="ck-mt-3">
                @if($def->field_type === 'textarea')
                    <x-ck-field type="textarea" label="{{ $def->label }}"
                                name="values[{{ $def->id }}]" id="cfVal{{ $def->id }}"
                                :required="$def->is_required"
                                placeholder="{{ $def->placeholder }}" rows="3" />

                @elseif($def->field_type === 'checkbox')
                    <x-ck-field type="checkbox" name="values[{{ $def->id }}]"
                                id="cfVal{{ $def->id }}" value="1">
                        {{ $def->label }}
                    </x-ck-field>

                @elseif($def->field_type === 'select' && $def->options)
                    <x-ck-field type="select" label="{{ $def->label }}"
                                name="values[{{ $def->id }}]" id="cfVal{{ $def->id }}"
                                :required="$def->is_required"
                                :options="array_combine($def->options, $def->options)" />

                @else
                    {{-- text, number, decimal, date, email, phone, url, whatsapp --}}
                    @php
                        $inputType = match($def->field_type) {
                            'number', 'decimal' => 'number',
                            'date'              => 'date',
                            'email'             => 'email',
                            'url', 'whatsapp'   => 'url',
                            default             => 'text',
                        };
                    @endphp
                    <x-ck-field type="{{ $inputType }}" label="{{ $def->label }}"
                                name="values[{{ $def->id }}]" id="cfVal{{ $def->id }}"
                                :required="$def->is_required"
                                placeholder="{{ $def->placeholder }}"
                                @if($def->field_type === 'number') step="1" @endif
                                @if($def->field_type === 'decimal') step="0.01" @endif />
                @endif
            </div>
            @endforeach

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'cfValModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>
</x-ck-modal>
@endif

@push('scripts')
<script>
    window.CK_CFValues = {
        values:     @json($valuesJs),
        upsertBase: "{{ url('custom-fields/values/' . $objectType) }}"
    };
</script>
@vite(['resources/js/modules/custom-fields-modal.js'])
@endpush

@endsection