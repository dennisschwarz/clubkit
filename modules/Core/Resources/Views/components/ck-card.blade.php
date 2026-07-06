@props([
    'accent'     => null,    // blue | green | teal | amber | purple | gray
    'dashed'     => false,
    'noPadding'  => false,
    'collapsible' => false,  // adds data-ck-collapsible attr; JS in app.js handles toggle
])

@php
    $classes = 'ck-card';
    if ($accent)    $classes .= ' ck-card--accent-' . $accent;
    if ($dashed)    $classes .= ' ck-card--dashed';
    if ($collapsible) $classes .= ' ck-card--collapsible';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>

    @isset($header)
    <div class="ck-card__header">
        {{ $header }}
    </div>
    @endisset

    <div class="{{ $noPadding ? '' : 'ck-card__body' }}">{{ $slot }}</div>

    @isset($footer)
    <div class="ck-card__footer">{{ $footer }}</div>
    @endisset

</div>