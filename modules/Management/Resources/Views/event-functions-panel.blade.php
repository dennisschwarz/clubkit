{{--
    Management hook: Funktionen tab on the event detail page.
    Extension point: events.show.functions-panel
    Registered by: ManagementServiceProvider

    Data injected by EventFunctionsPanelComposer (Option C — two merged sources):
      $mgmtFuncItems             → array<array{source, id, name, member_id, member}>
                                   source = 'club'  → from event_management_function (badge: blue)
                                   source = 'event' → from event_functions            (badge: amber)
      $mgmtAvailableFunctionsJs  → array<id, array{id, name}>  (club functions not yet assigned)

    Blade note:
      Plain HTML throughout — no x-ck-* components (avoids Blade compiler issues
      with Blade-directive parsing in this file). Blade parses directives even in
      plain-text comments, so directive names are written without the at-sign here.
      app.js teleports all .ck-modal-overlay elements to #ck-modal-root.
--}}

{{-- ── Assigned functions list ──────────────────────────────────────────────── --}}
@if(empty($mgmtFuncItems))
<div class="ck-card">
    <div class="ck-card__body">
        <p class="ck-text-muted">{{ __('events.function.empty') }}</p>
    </div>
</div>
@else
<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th class="ck-table__col--sm">{{ __('events.function.col_type') }}</th>
                <th>{{ __('events.function.col_function') }}</th>
                <th class="ck-table__col--lg">{{ __('events.function.col_responsible') }}</th>
                <th class="ck-table__actions"></th>
            </tr>
        </thead>
        <tbody id="ckFuncAssignedTbody">
        @foreach($mgmtFuncItems as $mgmtFuncItem)
        <tr>
            {{-- Type badge: blue = club function, amber = ad-hoc event function --}}
            <td>
                @if($mgmtFuncItem['source'] === 'club')
                    <span class="ck-badge ck-badge--blue">{{ __('events.function.type_club') }}</span>
                @else
                    <span class="ck-badge ck-badge--amber">{{ __('events.function.type_event') }}</span>
                @endif
            </td>

            {{-- Function name --}}
            <td class="ck-table__bold">{{ $mgmtFuncItem['name'] }}</td>

            {{-- Inline member assign select.
                 Options populated by functions-tab.js from CK_EventDetail.members.
                 data-source dispatches to the correct PATCH route.
                 data-current-member-id lets JS pre-select the current person. --}}
            <td>
                <select class="ck-field__input ck-field__input--sm ck-func-assign-select"
                        data-source="{{ $mgmtFuncItem['source'] }}"
                        data-function-id="{{ $mgmtFuncItem['id'] }}"
                        data-current-member-id="{{ $mgmtFuncItem['member_id'] ?? '' }}">
                    <option value="">– {{ __('events.function.assign_placeholder') }} –</option>
                </select>
            </td>

            {{-- Remove button: trash icon, no text label.
                 data-source dispatches to the correct DELETE route. --}}
            <td class="ck-table__actions">
                <div class="ck-table__action-cell">
                    <button type="button"
                            class="ck-btn ck-btn--danger ck-btn--icon ck-func-remove-btn"
                            data-source="{{ $mgmtFuncItem['source'] }}"
                            data-function-id="{{ $mgmtFuncItem['id'] }}"
                            data-ck-confirm="{{ __('events.function.remove_confirm', ['name' => $mgmtFuncItem['name']]) }}"
                            title="{{ __('Remove') }}">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Available club functions (drag from here into the table above) ───────── --}}
{{-- SortableJS: #ckFuncAvailTbody (pull:clone) → #ckFuncAssignedTbody (put:true). --}}
{{-- "+" button on each row fires the same POST as drag-and-drop.                  --}}
<div class="ck-card ck-mt-4">
    <div class="ck-card__header">
        <span class="ck-card__header-title">{{ __('events.function.available_title') }}</span>
    </div>
    <div class="ck-table-wrap">
        <table class="ck-table">
            <tbody id="ckFuncAvailTbody">
            @if(empty($mgmtAvailableFunctionsJs))
            <tr>
                <td class="ck-text-muted">{{ __('events.function.available_empty') }}</td>
                <td></td>
            </tr>
            @endif
            @foreach($mgmtAvailableFunctionsJs as $mgmtAvailFn)
            <tr class="ck-func-avail-row" data-function-id="{{ $mgmtAvailFn['id'] }}"
                title="{{ __('events.function.drag_to_assign') }}">
                <td class="ck-table__bold">{{ $mgmtAvailFn['name'] }}</td>
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <button type="button"
                                class="ck-btn ck-btn--success ck-btn--icon ck-func-add-club-btn"
                                data-function-id="{{ $mgmtAvailFn['id'] }}"
                                title="{{ __('Add') }}">+</button>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── New ad-hoc event function modal ─────────────────────────────────────── --}}
{{-- Plain HTML: no x-ck-* components in this file (Blade compiler restriction). --}}
{{-- Opened by the green "Eigene Funktion" button in the page header.            --}}
<div id="newEventFuncModal"
     class="ck-modal-overlay"
     onclick="ckModalClose(event, 'newEventFuncModal')">
    <div class="ck-modal-content ck-modal-content--sm" onclick="event.stopPropagation()">
        <div class="ck-modal__header">
            <h2 class="ck-modal__title">{{ __('events.function.new_event_modal_title') }}</h2>
            <button type="button" class="ck-modal__close"
                    onclick="ckModalClose(null, 'newEventFuncModal')">&times;</button>
        </div>
        <div class="ck-modal__body">
            <div class="ck-field">
                <label class="ck-field__label" for="newEventFuncName">
                    {{ __('events.function.field_name') }}
                    <span class="ck-field__required">*</span>
                </label>
                <input type="text" id="newEventFuncName" class="ck-field__input" required>
            </div>
            <div class="ck-form-actions">
                <button type="button" class="ck-btn ck-btn--primary" id="newEventFuncSubmitBtn">
                    {{ __('Save') }}
                </button>
                <button type="button" class="ck-btn ck-btn--secondary"
                        onclick="ckModalClose(null, 'newEventFuncModal')">
                    {{ __('Cancel') }}
                </button>
            </div>
        </div>
    </div>
</div>