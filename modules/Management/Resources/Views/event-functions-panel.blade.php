{{--
    Management hook: Funktionen tab on the event detail page.
    Extension point: events.show.functions-panel
    Registered by: ManagementServiceProvider

    Data injected by ManagementServiceProvider::composeEventFunctionsPanel():
      $mgmtFuncItems             → array<array{function: ManagementFunction, member: ?object, member_id: ?int}>
                                   Only functions explicitly assigned to this event.
      $mgmtAvailableFunctionsJs  → array<id, array{id, name}>  (functions not yet assigned)
                                   Used by events-detail.js for the "Funktion hinzufügen" modal select.
--}}

{{-- ── Action bar ───────────────────────────────────────────────────────────── --}}
<div class="ck-pane-actions">
    <x-ck-button variant="primary" type="button" onclick="ckModalOpen('newFuncModal')">
        {{ __('events.function.new_btn') }}
    </x-ck-button>
</div>

{{-- ── Function cards: name + assigned person (or hint) + remove button ──────── --}}
@if(empty($mgmtFuncItems))
<x-ck-card>
    <p class="ck-text-muted">{{ __('events.function.empty') }}</p>
</x-ck-card>
@else
<x-ck-card>
    <x-slot:header>{{ __('events.function.title') }}</x-slot:header>
    <div class="ck-func-grid">
        @foreach($mgmtFuncItems as $mgmtFuncItem)
        <div class="ck-func-card">
            <div class="ck-func-card__header">
                <span class="ck-func-card__name">{{ $mgmtFuncItem['function']->name }}</span>
                {{-- Remove this function from the event --}}
                <button type="button"
                        class="ck-func-remove-btn ck-btn ck-btn--icon ck-btn--danger"
                        data-function-id="{{ $mgmtFuncItem['function']->id }}"
                        data-ck-confirm="{{ __('events.function.remove_confirm', ['name' => $mgmtFuncItem['function']->name]) }}"
                        aria-label="{{ __('Remove') }}">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <div class="ck-func-card__member">
                @if($mgmtFuncItem['member'])
                    {{ $mgmtFuncItem['member']->last_name }}, {{ $mgmtFuncItem['member']->first_name }}
                @else
                    <span class="ck-text-muted">{{ __('events.function.not_staffed') }}</span>
                @endif
            </div>
            {{--
                Inline assign select.
                Options are populated by events-detail.js from CK_EventDetail.members.
                The data-current-member-id attribute lets JS pre-select the current person.
                On change: PATCH /events/{event}/functions/{functionId} via events-detail.js.
            --}}
            <div class="ck-func-card__assign">
                <select class="ck-form-select ck-func-assign-select"
                        data-function-id="{{ $mgmtFuncItem['function']->id }}"
                        data-current-member-id="{{ $mgmtFuncItem['member_id'] ?? '' }}">
                    <option value="">– {{ __('events.function.assign_placeholder') }} –</option>
                </select>
            </div>
        </div>
        @endforeach
    </div>
</x-ck-card>
@endif