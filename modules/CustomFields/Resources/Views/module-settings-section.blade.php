{{--
    Hook-View: Modul-Einstellungen — Benutzerdefinierte Felder
    Daten werden per View Composer in CustomFieldsServiceProvider bereitgestellt:
    $cfAllDefs, $cfGrouped, $objectTypes, $fieldTypes, $cfDefsJs
--}}

<x-ck-card class="ck-mb-5">

    <x-slot:header>📋 Benutzerdefinierte Felder</x-slot:header>

    <x-slot:headerAction>
        <x-ck-button variant="primary" size="sm" onclick="cfDefModalOpen('create')">
            + Feld hinzufügen
        </x-ck-button>
    </x-slot:headerAction>

    @if($cfAllDefs->isEmpty())

        <p class="ck-empty-state">
            Noch keine eigenen Felder angelegt.<br>
            <a href="javascript:void(0)" onclick="cfDefModalOpen('create')">Jetzt anlegen</a>
        </p>

    @else

        @foreach($objectTypes as $typeKey => $typeLabel)
        @php $defsForType = $cfGrouped->get($typeKey, collect()); @endphp
        @continue($defsForType->isEmpty())

        @php
            $bodyId    = 'cfms-body-' . $typeKey;
            $chevronId = 'cfms-chevron-' . $typeKey;
        @endphp

        <div class="ck-mb-4">
            <div class="ck-section-header ck-section-header--collapsible"
                 onclick="ckSectionToggle('{{ $bodyId }}', '{{ $chevronId }}')">

                <div class="ck-section-header__icon ck-section-header__icon--slate">📋</div>

                <div class="ck-section-header__text">
                    <span class="ck-section-header__title">{{ $typeLabel }}</span>
                    <span class="ck-section-header__meta">
                        {{ $defsForType->count() }} {{ $defsForType->count() === 1 ? 'Feld' : 'Felder' }}
                    </span>
                </div>

                <span class="ck-accordion-chevron ck-accordion-chevron--open" id="{{ $chevronId }}">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </span>
            </div>

            <div id="{{ $bodyId }}">
                <div class="ck-table-wrap">
                    <table class="ck-table">
                        <thead>
                            <tr>
                                <th>Feldname</th>
                                <th>Typ</th>
                                <th>Pflichtfeld</th>
                                <th class="ck-table__actions">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($defsForType as $def)
                            <tr>
                                <td class="ck-table__bold">{{ $def->label }}</td>
                                <td>
                                    <x-ck-badge color="{{ match($def->field_type) {
                                        'select'           => 'purple',
                                        'checkbox'         => 'green',
                                        'date'             => 'amber',
                                        'number','decimal' => 'blue',
                                        default            => 'gray',
                                    } }}">{{ $fieldTypes[$def->field_type] ?? $def->field_type }}</x-ck-badge>
                                </td>
                                <td>
                                    @if($def->is_required)
                                        <x-ck-badge color="red">Pflicht</x-ck-badge>
                                    @else
                                        <span class="ck-text-muted">Optional</span>
                                    @endif
                                </td>
                                <td class="ck-table__actions">
                                    <div class="ck-table__action-cell">
                                        <x-ck-button variant="warning" size="icon"
                                            title="Feld bearbeiten"
                                            onclick="cfDefModalOpen('edit', null, {{ $def->id }})">
                                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                                            </svg>
                                        </x-ck-button>
                                        <form method="POST"
                                              action="{{ route('custom-fields.destroy', $def->id) }}"
                                              class="ck-inline-form">
                                            @csrf @method('DELETE')
                                            <x-ck-button variant="danger" size="icon" type="submit"
                                                title="Feld löschen"
                                                :confirm="'Feld »' . $def->label . '« und alle Werte löschen?'">
                                                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
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
            </div>
        </div>

        @endforeach

    @endif

</x-ck-card>

{{-- ══ Definitions-Modal ══════════════════════════════════════════════════ --}}
<x-ck-modal id="cfDefModal" title="Feld anlegen" size="md">
    <div class="ck-modal__section ck-modal__section--active">
        <form id="cfDefForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="cfDefMethod" value="POST">

            <x-ck-field type="select" label="Für Objekt-Typ" name="object_type"
                        id="cfDefObjectType" :required="true"
                        :options="array_merge(['' => '– auswählen –'], $objectTypes)" />

            <div class="ck-mt-3">
                <x-ck-field label="Feldname" name="label" id="cfDefLabel"
                            placeholder="z.B. Trikotgröße, Verein vorher" :required="true" />
            </div>

            <div class="ck-form-grid ck-form-grid--2 ck-mt-3">
                <x-ck-field type="select" label="Feldtyp" name="field_type"
                            id="cfDefFieldType" :required="true"
                            :options="array_merge(['' => '– auswählen –'], $fieldTypes)" />
                <x-ck-field label="Platzhaltertext" name="placeholder"
                            id="cfDefPlaceholder" placeholder="z.B. M, L, XL" />
            </div>

            {{-- Optionen: nur bei field_type='select' sichtbar --}}
            <div class="ck-mt-3 is-hidden" id="cfDefOptionsBlock">
                <x-ck-field type="textarea" label="Auswahloptionen (eine pro Zeile)"
                            name="options_raw" id="cfDefOptionsRaw" rows="4"
                            placeholder="Option A&#10;Option B&#10;Option C" />
            </div>

            <div class="ck-mt-3">
                <x-ck-field type="checkbox" name="is_required" id="cfDefIsRequired" value="1">
                    Pflichtfeld
                </x-ck-field>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'cfDefModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>
</x-ck-modal>

@push('scripts')
<script>
    window.CK_CustomFields = {
        definitions: @json($cfDefsJs),
        objectTypes: @json($objectTypes),
        fieldTypes:  @json($fieldTypes),
        routes: {
            store:  "{{ route('custom-fields.store') }}",
            update: "{{ url('custom-fields') }}"
        }
    };
</script>
@vite(['resources/js/modules/custom-fields-modal.js'])
@endpush
