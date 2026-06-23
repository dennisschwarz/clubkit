@props([
    'variant' => 'primary',
    'size'    => 'md',
    'type'    => 'button',
    'confirm' => null,
    'href'    => null,
])

@php
    $classes = 'ck-btn ck-btn--' . $variant . ($size !== 'md' ? ' ck-btn--' . $size : '');
    $tag     = $href ? 'a' : 'button';
@endphp

@if($href)
<a href="{{ $href }}" class="{{ $classes }}" {{ $attributes }}>{{ $slot }}</a>
@else
<button
    type="{{ $type }}"
    class="{{ $classes }}"
    {{ $confirm ? "onclick=\"return confirm('" . addslashes($confirm) . "')\"" : '' }}
    {{ $attributes }}
>{{ $slot }}</button>
@endif
