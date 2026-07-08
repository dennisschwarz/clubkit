@extends('core::admin.layout')
@section('title', __('import.step2_subtitle'))

@section('content')
<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ __('import.title') }}</h1>
        <p class="ck-page-subtitle">{{ __('import.step2_subtitle') }}</p>
    </div>
    <div class="ck-row ck-row--gap">
        <form method="POST" action="{{ route('import.cancel', $session->id) }}">
            @csrf
            <x-ck-button variant="secondary" type="submit">{{ __('Cancel') }}</x-ck-button>
        </form>
    </div>
</div>

{{-- Info banner: DFBnet gender indicator --}}
@if($session->source === 'dfbnet')
<div class="ck-alert ck-alert--info ck-mb-4">
    {!! __('import.dfbnet_info') !!}
</div>
@endif

<x-ck-card>
    <x-slot:header>
        <span>
            <strong>{{ $session->filename }}</strong>
            &nbsp;·&nbsp;
            {{ __('import.rows_count', ['count' => count($session->raw_rows)]) }}
            &nbsp;·&nbsp;
            {{ __('import.source_label') }} <x-ck-badge color="blue">{{ strtoupper($session->source) }}</x-ck-badge>
        </span>
    </x-slot:header>

    {{-- "Add field" button in card header (only when CustomFields module is active) --}}
    @if($customFieldsEnabled)
    <x-slot:headerAction>
        <x-ck-button variant="secondary" size="sm" onclick="ckModalOpen('cfDefModal')">
            {{ __('import.add_custom_field') }}
        </x-ck-button>
    </x-slot:headerAction>
    @endif

    <form method="POST" action="{{ route('import.mapping.save', $session->id) }}">
        @csrf

        <p class="ck-mb-4">
            Weise jeder erkannten CSV-Spalte ein Mitgliederfeld zu oder wähle
            <em>„Überspringen"</em>, wenn das Feld nicht importiert werden soll.
            @if($customFieldsEnabled)
                Fehlt ein passendes Feld? Klicke auf
                <a href="javascript:void(0)" onclick="ckModalOpen('cfDefModal')">
                    + Benutzerdefiniertes Feld anlegen
                </a>
                — das neue Feld erscheint sofort in den Dropdowns.
            @endif
        </p>

        <div class="ck-table-wrap">
            <table class="ck-table">
                <thead>
                    <tr>
                        <th class="ck-table__col--md">{{ __('import.col.csv_column') }}</th>
                        <th>{{ __('import.col.sample_values') }}</th>
                        <th class="ck-table__col--lg">{{ __('import.col.map_to') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($session->column_headers as $header)
                    <tr>
                        <td><strong>{{ $header }}</strong></td>
                        <td>
                            @foreach ($session->samples[$header] ?? [] as $sample)
                                <x-ck-badge color="gray">{{ Str::limit($sample, 30) }}</x-ck-badge>
                            @endforeach
                        </td>
                        <td>
                            {{-- .ck-mapping-select is used by the import JS to inject new custom-field options --}}
                            <select name="mapping[{{ $header }}]" class="ck-field__input ck-mapping-select">

                                <option value="skip"
                                    {{ ($suggested[$header] ?? 'skip') === 'skip' ? 'selected' : '' }}>
                                    {{ __('import.skip_option') }}
                                </option>

                                <optgroup label="{{ __('import.group.member_fields') }}">
                                    @foreach ($memberFields as $fieldKey => $fieldLabel)
                                    <option value="{{ $fieldKey }}"
                                        {{ ($suggested[$header] ?? '') === $fieldKey ? 'selected' : '' }}>
                                        {{ $fieldLabel }}
                                    </option>
                                    @endforeach
                                </optgroup>

                                @if ($customFieldsEnabled && count($customFields))
                                <optgroup label="{{ __('import.group.custom_fields') }}">
                                    @foreach ($customFields as $cf)
                                    <option value="cf:{{ $cf['slug'] }}">
                                        {{ $cf['label'] }} ({{ $cf['field_type'] }})
                                    </option>
                                    @endforeach
                                </optgroup>
                                @endif

                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ck-form-actions ck-mt-4">
            <x-ck-button variant="primary" type="submit">
                {{ __('Confirm mapping and load preview →') }}
            </x-ck-button>
        </div>
    </form>
</x-ck-card>

{{-- ══ Create custom field (only when CustomFields module is active) ══════════════════ --}}
@if($customFieldsEnabled)
<x-ck-modal id="cfDefModal" :title="__('import.cf_modal_title')" size="md">
    <div class="ck-modal__section ck-modal__section--active">
        <form id="cfDefForm" method="POST" action="{{ route('custom-fields.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cfDefMethod" value="POST">
            <input type="hidden" name="object_type" value="member">

            <p class="ck-text-muted ck-mb-4">
                <small>{{ __('import.cf_modal_info') }}</small>
            </p>

            <x-ck-field :label="__('custom-fields.field.label')" name="label" id="cfDefLabel"
                        placeholder="z.B. Trikotgröße, Verein vorher" :required="true" />

            <div class="ck-form-grid ck-form-grid--2 ck-mt-3">
                <x-ck-field type="select" :label="__('custom-fields.field.type')" name="field_type"
                            id="cfDefFieldType" :required="true"
                            :options="array_merge(['' => __('import.type_select_placeholder')], $fieldTypes)" />
                <x-ck-field :label="__('custom-fields.field.placeholder')" name="placeholder"
                            id="cfDefPlaceholder" placeholder="z.B. M, L, XL" />
            </div>

            <div class="ck-mt-3 is-hidden" id="cfDefOptionsBlock">
                <x-ck-field type="textarea" :label="__('custom-fields.field.options')"
                            name="options_raw" id="cfDefOptionsRaw" rows="4"
                            placeholder="Option A&#10;Option B&#10;Option C" />
            </div>

            <div class="ck-mt-3">
                <x-ck-field type="checkbox" name="is_required" id="cfDefIsRequired" value="1">
                    {{ __('custom-fields.field.required_checkbox') }}
                </x-ck-field>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary" id="cfDefSubmitBtn">{{ __('Create') }}</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'cfDefModal')">{{ __('Cancel') }}</x-ck-button>
            </div>
        </form>
    </div>
</x-ck-modal>
@endif

@endsection

@push('scripts')
@if($customFieldsEnabled)
@vite('resources/js/modules/custom-fields-modal.js')
<script>
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content
        || (document.querySelector('[name="_token"]') || {}).value
        || '';

    document.addEventListener('DOMContentLoaded', function () {
        const cfDefForm   = document.getElementById('cfDefForm');
        const cfSubmitBtn = document.getElementById('cfDefSubmitBtn');
        if (!cfDefForm) return;

        cfDefForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (cfSubmitBtn) cfSubmitBtn.disabled = true;

            fetch(cfDefForm.action, {
                method:  'POST',
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     csrfToken,
                },
                body: new FormData(cfDefForm),
            })
            .then(function (resp) {
                if (!resp.ok) {
                    return resp.json().then(function (d) {
                        const msg = d.message
                            || Object.values(d.errors || {}).flat().join(', ')
                            || 'Fehler beim Anlegen';
                        throw new Error(msg);
                    });
                }
                return resp.json();
            })
            .then(function (data) {
                // Inject the new option into every .ck-mapping-select dropdown
                const optVal  = 'cf:' + data.slug;
                const optText = data.label + ' (' + data.field_type + ')';

                document.querySelectorAll('.ck-mapping-select').forEach(function (select) {
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === optVal) return;
                    }
                    let optgroup = select.querySelector('optgroup[label="Benutzerdefinierte Felder"]');
                    if (!optgroup) {
                        optgroup = document.createElement('optgroup');
                        optgroup.label = 'Benutzerdefinierte Felder';
                        select.appendChild(optgroup);
                    }
                    const opt = document.createElement('option');
                    opt.value       = optVal;
                    opt.textContent = optText;
                    optgroup.appendChild(opt);
                });

                ckModalClose(null, 'cfDefModal');
                cfDefForm.reset();
                const optBlock = document.getElementById('cfDefOptionsBlock');
                if (optBlock) optBlock.classList.add('is-hidden');
            })
            .catch(function (err) {
                alert('Fehler: ' + err.message);
            })
            .finally(function () {
                if (cfSubmitBtn) cfSubmitBtn.disabled = false;
            });
        });
    });
}());
</script>
@endif
@endpush