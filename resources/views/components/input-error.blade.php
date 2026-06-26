@props(['messages' => []])

@if ($messages)
    @foreach ((array) $messages as $message)
        <p {{ $attributes->merge(['class' => 'ck-form-error']) }}>
            {{ $message }}
        </p>
    @endforeach
@endif
