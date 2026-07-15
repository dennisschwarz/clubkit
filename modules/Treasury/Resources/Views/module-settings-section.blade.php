{{--
    Hook view: admin.module-settings.sections — Treasury settings (priority 40).
    Manages transaction categories (income / expense).

    Data loaded inline via a php block — no dedicated View Composer needed
    because the data is simple and module-local.

    Plain HTML in foreach loops — no x-ck-* components inside (Blade 13.17).
--}}
@php
    $incomeCategories  = \Modules\Treasury\Models\TreasuryCategory::where('transaction_type', 'income')->orderBy('name')->get();
    $expenseCategories = \Modules\Treasury\Models\TreasuryCategory::where('transaction_type', 'expense')->orderBy('name')->get();

    $treasuryColors = [
        ''       => __('teams.color.default'),
        'green'  => __('teams.color.green'),
        'red'    => __('teams.color.red'),
        'blue'   => __('teams.color.blue'),
        'orange' => __('teams.color.orange'),
        'amber'  => __('teams.color.yellow'),
        'purple' => __('teams.color.purple'),
        'teal'   => __('teams.color.teal'),
        'slate'  => __('teams.color.gray'),
    ];
@endphp

<div id="settings-treasury" class="ck-local-section">

    <div class="ck-section-header ck-section-header--colored ck-section-header--team-amber">
        <div class="ck-section-header__icon">💰</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">{{ __('treasury.settings.section_title') }}</span>
            <span class="ck-section-header__meta">
                {{ $incomeCategories->count() + $expenseCategories->count() }} {{ __('treasury.settings.categories_count') }}
            </span>
        </div>
        <div class="ck-section-header__actions">
            <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                    title="{{ __('core.create') }}"
                    onclick="treasuryCategoryOpen('create')">+</button>
        </div>
    </div>

    {{-- ── Income: header + table as one flex item (no gap between header and table) --}}
    <div>
        <div class="ck-section-header ck-section-header--flush ck-section-header--colored ck-section-header--team-green">
            <div class="ck-section-header__icon">↑</div>
            <div class="ck-section-header__text">
                <span class="ck-section-header__title">{{ __('treasury.settings.income_title') }}</span>
                <span class="ck-section-header__meta">
                    {{ $incomeCategories->count() }} {{ $incomeCategories->count() === 1 ? 'Kategorie' : 'Kategorien' }}
                </span>
            </div>
        </div>
        <div class="ck-body--flush-top">
            <div class="ck-table-wrap">
                <table class="ck-table">
                    <thead>
                        <tr>
                            <th>{{ __('treasury.settings.category_name') }}</th>
                            <th>{{ __('treasury.settings.category_color') }}</th>
                            <th class="ck-table__actions">{{ __('core.col.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomeCategories as $cat)
                        <tr>
                            <td class="ck-table__bold">{{ $cat->name }}</td>
                            <td>
                                @if($cat->color)
                                    <span class="ck-badge ck-badge--{{ $cat->color }}">{{ $cat->color }}</span>
                                @else
                                    <span class="ck-muted">–</span>
                                @endif
                            </td>
                            <td class="ck-table__actions">
                                <div class="ck-table__action-cell">
                                    <button type="button" class="ck-btn ck-btn--warning ck-btn--icon"
                                            title="{{ __('Edit') }}"
                                            onclick="treasuryCategoryOpen('edit', {{ $cat->id }}, {{ json_encode($cat->name) }}, 'income', {{ json_encode($cat->color ?? '') }})">
                                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                                        </svg>
                                    </button>
                                    <form method="POST"
                                          action="{{ route('treasury.categories.destroy', $cat->id) }}"
                                          class="ck-inline-form">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ck-btn ck-btn--danger ck-btn--icon"
                                                title="{{ __('Delete') }}"
                                                data-ck-confirm="{{ __('treasury.settings.confirm_delete', ['name' => $cat->name]) }}">
                                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="ck-empty-state">{{ __('treasury.settings.income_empty') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Expense: header + table as one flex item ────────────────────────────── --}}
    <div>
        <div class="ck-section-header ck-section-header--flush ck-section-header--colored ck-section-header--team-red">
            <div class="ck-section-header__icon">↓</div>
            <div class="ck-section-header__text">
                <span class="ck-section-header__title">{{ __('treasury.settings.expense_title') }}</span>
                <span class="ck-section-header__meta">
                    {{ $expenseCategories->count() }} {{ $expenseCategories->count() === 1 ? 'Kategorie' : 'Kategorien' }}
                </span>
            </div>
        </div>
        <div class="ck-body--flush-top">
            <div class="ck-table-wrap">
                <table class="ck-table">
                    <thead>
                        <tr>
                            <th>{{ __('treasury.settings.category_name') }}</th>
                            <th>{{ __('treasury.settings.category_color') }}</th>
                            <th class="ck-table__actions">{{ __('core.col.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenseCategories as $cat)
                        <tr>
                            <td class="ck-table__bold">{{ $cat->name }}</td>
                            <td>
                                @if($cat->color)
                                    <span class="ck-badge ck-badge--{{ $cat->color }}">{{ $cat->color }}</span>
                                @else
                                    <span class="ck-muted">–</span>
                                @endif
                            </td>
                            <td class="ck-table__actions">
                                <div class="ck-table__action-cell">
                                    <button type="button" class="ck-btn ck-btn--warning ck-btn--icon"
                                            title="{{ __('Edit') }}"
                                            onclick="treasuryCategoryOpen('edit', {{ $cat->id }}, {{ json_encode($cat->name) }}, 'expense', {{ json_encode($cat->color ?? '') }})">
                                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                                        </svg>
                                    </button>
                                    <form method="POST"
                                          action="{{ route('treasury.categories.destroy', $cat->id) }}"
                                          class="ck-inline-form">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ck-btn ck-btn--danger ck-btn--icon"
                                                title="{{ __('Delete') }}"
                                                data-ck-confirm="{{ __('treasury.settings.confirm_delete', ['name' => $cat->name]) }}">
                                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="ck-empty-state">{{ __('treasury.settings.expense_empty') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ── Category modal (create + edit) ────────────────────────────────────────── --}}
<x-ck-modal id="treasuryCategoryModal" :title="__('treasury.settings.edit_category')" size="sm">
    <form id="treasuryCategoryForm" method="POST">
        @csrf
        <input type="hidden" name="_method" id="treasuryCategoryMethod" value="POST">

        <x-ck-field :label="__('treasury.settings.category_name')"
                    name="name" id="treasuryCatName" type="text" :required="true" />

        <div class="ck-mt-3">
            <x-ck-field :label="__('treasury.settings.category_type')"
                        name="transaction_type" id="treasuryCatType" type="select"
                        :options="[
                            'income'  => __('treasury.settings.type_income'),
                            'expense' => __('treasury.settings.type_expense'),
                        ]" :required="true" />
        </div>

        {{-- Color picker: plain HTML radio buttons — matches Teams modal pattern --}}
        <div class="ck-field__group ck-mt-3">
            <label class="ck-field__label">{{ __('treasury.settings.category_color') }}</label>
            <div class="ck-color-picker" id="treasuryCatColorPicker">
                @foreach($treasuryColors as $colorKey => $colorLabel)
                <label class="ck-color-swatch{{ $colorKey === '' ? ' ck-color-swatch--selected' : '' }}"
                       title="{{ $colorLabel }}">
                    <input type="radio" name="color" value="{{ $colorKey }}"
                           {{ $colorKey === '' ? 'checked' : '' }}>
                    <span class="ck-color-swatch__dot ck-color-swatch__dot--{{ $colorKey ?: 'default' }}"></span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="ck-form-actions">
            <x-ck-button type="submit" variant="primary">{{ __('Save') }}</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'treasuryCategoryModal')">{{ __('Cancel') }}</x-ck-button>
        </div>
    </form>
</x-ck-modal>

@push('scripts')
<script>
var _treasuryCatRoutes = {
    store:  "{{ route('treasury.categories.store') }}",
    update: "{{ url('treasury/categories') }}"
};

/**
 * Opens the treasury category modal in create or edit mode.
 * Updates the color picker selection, type dropdown and form action/method.
 *
 * @param {'create'|'edit'}    mode
 * @param {number|null}        id
 * @param {string}             name
 * @param {'income'|'expense'} type
 * @param {string}             color  Colour slug or empty string for none.
 */
function treasuryCategoryOpen(mode, id, name, type, color) {
    var form   = document.getElementById('treasuryCategoryForm');
    var method = document.getElementById('treasuryCategoryMethod');

    if (mode === 'create') {
        form.action  = _treasuryCatRoutes.store;
        method.value = 'POST';
        document.getElementById('treasuryCatName').value = '';
        document.getElementById('treasuryCatType').value = 'income';
        color = '';
    } else {
        form.action  = _treasuryCatRoutes.update + '/' + id;
        method.value = 'PATCH';
        document.getElementById('treasuryCatName').value = name  || '';
        document.getElementById('treasuryCatType').value = type  || 'income';
        color = color || '';
    }

    document.getElementById('treasuryCatColorPicker')
        .querySelectorAll('.ck-color-swatch')
        .forEach(function (label) {
            var input    = label.querySelector('input[type="radio"]');
            var selected = input.value === color;
            input.checked = selected;
            label.classList.toggle('ck-color-swatch--selected', selected);
        });

    ckModalOpen('treasuryCategoryModal');
}
</script>
@endpush
