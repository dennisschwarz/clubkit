@props([
    'label'       => '',
    'name'        => '',
    'type'        => 'text',
    'value'       => '',
    'required'    => false,
    'options'     => [],      // für type="select": ['value' => 'Label']
    'placeholder' => '',
    'hint'        => null,
    'id'          => null,
    'checked'     => false,   // für type="checkbox"
])

@php
    $fieldId    = $id ?? $name;
    $hasError   = $errors->has($name);
    $wrapClass  = 'ck-field' . ($hasError ? ' ck-field--error' : '');
@endphp

<div class="{{ $wrapClass }}">

    @if($label && $type !== 'checkbox')
    <label class="ck-field__label" for="{{ $fieldId }}">
        {{ $label }}
        @if($required)<span class="ck-field__required">*</span>@endif
        @if($hint)<span class="ck-field__hint">{{ $hint }}</span>@endif
    </label>
    @endif

    @if($type === 'select')
        <select
            name="{{ $name }}"
            id="{{ $fieldId }}"
            class="ck-field__input"
            {{ $required ? 'required' : '' }}
            {{ $attributes }}
        >
            @foreach($options as $val => $optLabel)
            <option value="{{ $val }}" {{ old($name, $value) == $val ? 'selected' : '' }}>
                {{ $optLabel }}
            </option>
            @endforeach
        </select>

    @elseif($type === 'checkbox')
        <label class="ck-field__checkbox">
            <input
                type="checkbox"
                name="{{ $name }}"
                id="{{ $fieldId }}"
                value="1"
                {{ old($name, $checked) ? 'checked' : '' }}
                {{ $attributes }}
            >
            {{ $slot->isNotEmpty() ? $slot : $label }}
        </label>

    @elseif($type === 'textarea')
        <textarea
            name="{{ $name }}"
            id="{{ $fieldId }}"
            class="ck-field__input"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes }}
        >{{ old($name, $value) }}</textarea>

    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            class="ck-field__input"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes }}
        >
    @endif

    @error($name)
    <p class="ck-field__error">{{ $message }}</p>
    @enderror

</div>
