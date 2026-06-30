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
    @if($confirm) onclick="return confirm({{ Illuminate\Support\Js::from($confirm) }})" @endif
    {{ $attributes }}
>{{ $slot }}</button>
@endif
