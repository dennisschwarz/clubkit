@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'ck-btn ck-btn--primary']) }}
>
    {{ $slot }}
</button>
