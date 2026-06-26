@props(['value' => '', 'for' => ''])

<label
    for="{{ $for }}"
    {{ $attributes->merge(['class' => 'ck-form-label']) }}
>
    {{ $value ?: $slot }}
</label>
