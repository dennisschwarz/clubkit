{{--
    Sortable table-column header.

    Renders a <th> element with a clickable link.
    A click sets ?{param}={column} (ASC) or ?{param}=-{column} (DESC).
    The active column is highlighted.

    Props:
      column   string  Column name in the query (e.g. 'last_name', 'created_at')
      label    string  Display text in the header
      param    string  URL parameter name (default: 'sort')
                       For pages with multiple lists: 'fn_sort', 'task_sort' etc.
      justify  string  'start' (default) | 'end' for right-aligned columns (e.g. amount)

    Usage:
      <x-ck-sort-header column="last_name" label="Name" />
      <x-ck-sort-header column="amount"    label="Betrag" justify="end" />
      <x-ck-sort-header column="name"      label="Funktion" param="fn_sort" />
--}}
@props([
    'column'  => '',
    'label'   => '',
    'param'   => 'sort',
    'justify' => 'start',
])

@php
    $current  = request($param, '');
    $isAsc    = $current === $column;
    $isDesc   = $current === '-' . $column;
    $isActive = $isAsc || $isDesc;

    // Toggle: active+ASC → DESC; anything else → ASC
    $next = $isAsc ? '-' . $column : $column;
@endphp

<th {{ $attributes->merge(['class' => 'ck-sort-th']) }}>
    <a href="{{ request()->fullUrlWithQuery([$param => $next]) }}"
       class="ck-sort-link{{ $isActive ? ' ck-sort-link--active' : '' }}{{ $justify === 'end' ? ' ck-sort-link--right' : '' }}">
        <span>{{ $label }}</span>
        <span class="ck-sort-icon" aria-hidden="true">
            @if($isAsc)&#8593;@elseif($isDesc)&#8595;@else&#8645;@endif
        </span>
    </a>
</th>
