{{--
    Management hook: Funktionen tab on the event detail page.
    Extension point: events.show.functions-panel
    Registered by: ManagementServiceProvider

    Data injected by ManagementServiceProvider::composeEventFunctionsPanel():
      $mgmtFuncItems → array<array{function: ManagementFunction, member: ?object}>
                       member is the effective person (event override > global default > null)
--}}

@if(empty($mgmtFuncItems))
<x-ck-card>
    <p class="ck-text-muted">{{ __('events.function.empty') }}</p>
</x-ck-card>
@else
<x-ck-card>
    <x-slot:header>{{ __('events.function.title') }}</x-slot:header>
    <table class="ck-table">
        <thead>
            <tr>
                <th>{{ __('events.function.col_function') }}</th>
                <th>{{ __('events.function.col_responsible') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mgmtFuncItems as $mgmtFuncItem)
            <tr>
                <td><strong>{{ $mgmtFuncItem['function']->name }}</strong></td>
                <td>
                    @if($mgmtFuncItem['member'])
                        {{ $mgmtFuncItem['member']->last_name }}, {{ $mgmtFuncItem['member']->first_name }}
                    @else
                        <span class="ck-badge ck-badge--orange">{{ __('events.function.not_staffed') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</x-ck-card>
@endif