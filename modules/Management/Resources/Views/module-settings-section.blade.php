{{--
    Hook view: admin.module-settings.sections — Management settings (priority 30).
    Manages global task categories used across all management tasks.

    Variables supplied by View Composer (ManagementServiceProvider):
      $mgmtTaskCategories  Collection  All ManagementTaskCategory records ordered by name.

    Plain HTML in foreach loops — no x-ck-* components inside (Blade 13.17).
--}}
@php
$mgmtColors = [
    ''       => __('teams.color.default'),
    'blue'   => __('teams.color.blue'),
    'green'  => __('teams.color.green'),
    'amber'  => __('teams.color.yellow'),
    'red'    => __('teams.color.red'),
    'orange' => __('teams.color.orange'),
    'purple' => __('teams.color.purple'),
    'pink'   => __('teams.color.pink'),
    'teal'   => __('teams.color.teal'),
    'navy'   => __('teams.color.navy'),
    'slate'  => __('teams.color.gray'),
];
@endphp

<div id="settings-management" class="ck-local-section">

    <div class="ck-section-header ck-section-header--colored ck-section-header--team-green">
        <div class="ck-section-header__icon">📋</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">{{ __('management.settings.section_title') }}</span>
            <span class="ck-section-header__meta">
                {{ $mgmtTaskCategories->count() }} {{ $mgmtTaskCategories->count() === 1 ? 'Kategorie' : 'Kategorien' }}
            </span>
        </div>
        <div class="ck-section-header__actions">
            <button type="button" class="ck-btn ck-btn--success ck-btn--icon"
                    title="{{ __('core.create') }}"
                    onclick="mgmtCategoryOpen('create')">+</button>
        </div>
    </div>

    <div class="ck-table-wrap">
        <table class="ck-table">
            <thead>
                <tr>
                    <th>{{ __('management.settings.category_name') }}</th>
                    <th>{{ __('management.settings.category_color') }}</th>
                    <th class="ck-table__actions">{{ __('core.col.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mgmtTaskCategories as $cat)
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
                                    onclick="mgmtCategoryOpen('edit', {{ $cat->id }}, {{ json_encode($cat->name) }}, {{ json_encode($cat->color ?? '') }})">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                                </svg>
                            </button>
                            <form method="POST"
                                  action="{{ route('management.task-categories.destroy', $cat->id) }}"
                                  class="ck-inline-form">
                                @csrf @method('DELETE')
                                <button type="submit" class="ck-btn ck-btn--danger ck-btn--icon"
                                        title="{{ __('Delete') }}"
                                        data-ck-confirm="{{ __('management.settings.confirm_delete_category', ['name' => $cat->name]) }}">
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
                    <td colspan="3" class="ck-empty-state">{{ __('management.settings.categories_empty') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- ── Category modal (create + edit) ───────────────────────────────────────── --}}
<x-ck-modal id="mgmtCategoryModal" :title="__('management.settings.edit_category')" size="sm">
    <form id="mgmtCategoryForm" method="POST">
        @csrf
        <input type="hidden" name="_method" id="mgmtCategoryMethod" value="POST">

        <x-ck-field :label="__('management.settings.category_name')"
                    name="name" id="mgmtCatName" type="text" :required="true" />

        {{-- Color picker: plain HTML radio buttons — matches Teams modal pattern --}}
        <div class="ck-field__group ck-mt-3">
            <label class="ck-field__label">{{ __('management.settings.category_color') }}</label>
            <div class="ck-color-picker" id="mgmtCatColorPicker">
                @foreach($mgmtColors as $colorKey => $colorLabel)
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
                onclick="ckModalClose(null, 'mgmtCategoryModal')">{{ __('Cancel') }}</x-ck-button>
        </div>
    </form>
</x-ck-modal>

@push('scripts')
<script>
var _mgmtCatRoutes = {
    store:  "{{ route('management.task-categories.store') }}",
    update: "{{ url('management/task-categories') }}"
};

/**
 * Opens the task-category modal in create or edit mode.
 * Updates the color picker selection and form action/method.
 *
 * @param {'create'|'edit'} mode
 * @param {number|null}     id
 * @param {string}          name
 * @param {string}          color  Colour slug or empty string for none.
 */
function mgmtCategoryOpen(mode, id, name, color) {
    var form   = document.getElementById('mgmtCategoryForm');
    var method = document.getElementById('mgmtCategoryMethod');

    if (mode === 'create') {
        form.action  = _mgmtCatRoutes.store;
        method.value = 'POST';
        document.getElementById('mgmtCatName').value = '';
        color = '';
    } else {
        form.action  = _mgmtCatRoutes.update + '/' + id;
        method.value = 'PATCH';
        document.getElementById('mgmtCatName').value = name || '';
        color = color || '';
    }

    document.getElementById('mgmtCatColorPicker')
        .querySelectorAll('.ck-color-swatch')
        .forEach(function (label) {
            var input    = label.querySelector('input[type="radio"]');
            var selected = input.value === color;
            input.checked = selected;
            label.classList.toggle('ck-color-swatch--selected', selected);
        });

    ckModalOpen('mgmtCategoryModal');
}
</script>
@endpush
