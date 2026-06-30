{{--
    Treasury module settings section.
    Rendered via HookRegistry at 'admin.module-settings.sections' with priority 40.
    Manages transaction categories (income/expense).
--}}

<div class="ck-settings-section" id="treasury-settings">
    <h2 class="ck-settings-section__title">💰 Vereinskasse – Kategorien</h2>
    <p class="ck-settings-section__description">
        Definiere Buchungskategorien für Einnahmen und Ausgaben.
        Jede Kategorie gehört zu genau einem Typ.
    </p>

    @if(session('success'))
        <div class="ck-alert ck-alert--success ck-mb-4">{{ session('success') }}</div>
    @endif

    {{-- ── Neue Kategorie anlegen ──────────────────────────────────────── --}}
    <form method="POST" action="{{ route('treasury.categories.store') }}" class="ck-settings-form ck-mb-6">
        @csrf
        <div class="ck-row ck-row--gap">
            <x-ck-field label="Name" name="name" type="text" placeholder="z.B. Mitgliedsbeiträge" :required="true" />
            <x-ck-field label="Typ" name="transaction_type" type="select"
                :options="['income' => '↑ Einnahme', 'expense' => '↓ Ausgabe']" :required="true" />
            <x-ck-field label="Farbe" name="color" type="select"
                :options="['' => 'Keine', 'green' => 'Grün', 'red' => 'Rot', 'blue' => 'Blau', 'orange' => 'Orange', 'gray' => 'Grau', 'purple' => 'Violett']" />
            <div class="ck-field-action">
                <x-ck-button type="submit" variant="primary">Anlegen</x-ck-button>
            </div>
        </div>
        @error('name')<p class="ck-field-error">{{ $message }}</p>@enderror
    </form>

    {{-- ── Einnahme-Kategorien ──────────────────────────────────────────── --}}
    @php
        $incomeCategories  = \Modules\Treasury\Models\TreasuryCategory::where('transaction_type', 'income')->orderBy('name')->get();
        $expenseCategories = \Modules\Treasury\Models\TreasuryCategory::where('transaction_type', 'expense')->orderBy('name')->get();
    @endphp

    <h3 class="ck-settings-section__subtitle ck-mt-4">↑ Einnahme-Kategorien</h3>
    @if($incomeCategories->isEmpty())
        <p class="ck-muted">Noch keine Einnahme-Kategorien angelegt.</p>
    @else
        <table class="ck-table ck-mb-4">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Farbe</th>
                    <th class="ck-table__col--actions">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incomeCategories as $cat)
                <tr>
                    <td>{{ $cat->name }}</td>
                    <td>
                        @if($cat->color)
                            <x-ck-badge :color="$cat->color">{{ $cat->color }}</x-ck-badge>
                        @else
                            <span class="ck-muted">–</span>
                        @endif
                    </td>
                    <td class="ck-table__col--actions">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="treasuryCategoryEdit({{ $cat->id }}, {{ json_encode($cat->name) }}, 'income', {{ json_encode($cat->color) }})">
                            Bearbeiten
                        </x-ck-button>
                        <form method="POST" action="{{ route('treasury.categories.destroy', $cat->id) }}">
                            @csrf @method('DELETE')
                            <x-ck-button type="submit" variant="danger" size="sm"
                                :confirm="'Kategorie »' . $cat->name . '« löschen?'">
                                Löschen
                            </x-ck-button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Ausgabe-Kategorien ───────────────────────────────────────────── --}}
    <h3 class="ck-settings-section__subtitle">↓ Ausgabe-Kategorien</h3>
    @if($expenseCategories->isEmpty())
        <p class="ck-muted">Noch keine Ausgabe-Kategorien angelegt.</p>
    @else
        <table class="ck-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Farbe</th>
                    <th class="ck-table__col--actions">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenseCategories as $cat)
                <tr>
                    <td>{{ $cat->name }}</td>
                    <td>
                        @if($cat->color)
                            <x-ck-badge :color="$cat->color">{{ $cat->color }}</x-ck-badge>
                        @else
                            <span class="ck-muted">–</span>
                        @endif
                    </td>
                    <td class="ck-table__col--actions">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="treasuryCategoryEdit({{ $cat->id }}, {{ json_encode($cat->name) }}, 'expense', {{ json_encode($cat->color) }})">
                            Bearbeiten
                        </x-ck-button>
                        <form method="POST" action="{{ route('treasury.categories.destroy', $cat->id) }}">
                            @csrf @method('DELETE')
                            <x-ck-button type="submit" variant="danger" size="sm"
                                :confirm="'Kategorie »' . $cat->name . '« löschen?'">
                                Löschen
                            </x-ck-button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Category edit modal --}}
<x-ck-modal id="treasuryCategoryModal" title="Kategorie bearbeiten" size="sm">
    <form id="treasuryCategoryForm" method="POST">
        @csrf @method('PATCH')
        <div class="ck-modal-section ck-modal-section--active">
            <x-ck-field label="Name" name="name" type="text" :required="true" />
            <x-ck-field label="Typ" name="transaction_type" type="select"
                :options="['income' => '↑ Einnahme', 'expense' => '↓ Ausgabe']" :required="true" />
            <x-ck-field label="Farbe" name="color" type="select"
                :options="['' => 'Keine', 'green' => 'Grün', 'red' => 'Rot', 'blue' => 'Blau', 'orange' => 'Orange', 'gray' => 'Grau', 'purple' => 'Violett']" />
        </div>
        <div class="ck-modal-footer">
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'treasuryCategoryModal')">Abbrechen</x-ck-button>
            <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
        </div>
    </form>
</x-ck-modal>

<script>
/**
 * Opens the category edit modal with pre-filled field values.
 */
function treasuryCategoryEdit(id, name, type, color) {
    const form = document.getElementById('treasuryCategoryForm');
    form.action = '/treasury/categories/' + id;
    form.querySelector('[name="name"]').value             = name || '';
    form.querySelector('[name="transaction_type"]').value = type || 'income';
    form.querySelector('[name="color"]').value            = color || '';
    ckModalOpen('treasuryCategoryModal');
}
</script>
