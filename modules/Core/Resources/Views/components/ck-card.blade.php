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

    {{-- Header: Titel LINKS, optionaler Action-Slot RECHTS --}}
    @isset($header)
    <div class="ck-card__header">
        <span class="ck-card__header-title">{{ $header }}</span>
        @isset($headerAction)
        <div class="ck-card__header-action">{{ $headerAction }}</div>
        @endisset
    </div>
    @endisset

    <div class="{{ $noPadding ? '' : 'ck-card__body' }}">{{ $slot }}</div>

    @isset($footer)
    <div class="ck-card__footer">{{ $footer }}</div>
    @endisset

</div>
