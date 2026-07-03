{{--
    Management hook: Funktionen tab on the event detail page.
    Extension point: events.show.functions-panel
    Registered by: ManagementServiceProvider

    Data injected by ManagementServiceProvider::composeEventFunctionsPanel():
      $mgmtFuncItems → array<array{function: ManagementFunction, member: ?object}>
                       member is the effective person (event override > global default > null)
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
        </div>
        @endforeach
    </div>
</x-ck-card>
@endif