@props([
    'accent'    => null,   // blue | green | gray
    'dashed'    => false,
    'noPadding' => false,
])

@php
    $classes = 'ck-card';
    if ($accent) $classes .= ' ck-card--accent-' . $accent;
    if ($dashed) $classes .= ' ck-card--dashed';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>

    {{-- Card header: renders slot content directly (callers may include action buttons) --}}
    @isset($header)
    <div class="ck-card__header">{{ $header }}</div>
    @endisset

    <div class="{{ $noPadding ? '' : 'ck-card__body' }}">{{ $slot }}</div>

    @isset($footer)
    <div class="ck-card__footer">{{ $footer }}</div>
    @endisset

</div>