@props([
    'variant' => 'primary',
    'size'    => 'md',
    'type'    => 'button',
    'confirm' => null,
    'href'    => null,
])

@php
    $classes = 'ck-btn ck-btn--' . $variant . ($size !== 'md' ? ' ck-btn--' . $size : '');
@endphp

@if($href)
<a href="{{ $href }}" class="{{ $classes }}" {{ $attributes }}>{{ $slot }}</a>
@else
{{--
    When :confirm is set the button is always rendered as type="button" to prevent
    the form from submitting directly. The global [data-ck-confirm] click handler
    in app.js opens the confirm modal instead, and calls form.requestSubmit()
    after the user confirms — which triggers the S27 double-submit guard.
--}}
<button
    type="{{ $confirm ? 'button' : $type }}"
    class="{{ $classes }}"
    @if($confirm) data-ck-confirm="{{ $confirm }}" @endif
    {{ $attributes }}
>{{ $slot }}</button>
@endif
