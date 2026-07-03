{{--
    Management hook: Funktionen tab on the event detail page.
    Extension point: events.show.functions-panel
    Registered by: ManagementServiceProvider

    Data injected by ManagementServiceProvider::composeEventFunctionsPanel():
      $mgmtFuncItems → array<array{function: ManagementFunction, member: ?object, member_id: ?int}>
                       member_id = effective person ID (event override > global default > null)
                       Used by events-detail.js to pre-select the current member in the assign select.
--}}

{{-- ── Action bar ───────────────────────────────────────────────────────────── --}}
<div class="ck-pane-actions">
    <x-ck-button variant="primary" type="button" onclick="ckModalOpen('newFuncModal')">
        {{ __('events.function.new_btn') }}
    </x-ck-button>
</div>

{{-- ── Function cards per concept: name + assigned member or ⚠ not staffed ── --}}
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
            <div class="ck-func-card__name">{{ $mgmtFuncItem['function']->name }}</div>
            <div class="ck-func-card__member">
                @if($mgmtFuncItem['member'])
                    {{ $mgmtFuncItem['member']->last_name }}, {{ $mgmtFuncItem['member']->first_name }}
                @else
                    <x-ck-badge color="orange">{{ __('events.function.not_staffed') }}</x-ck-badge>
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