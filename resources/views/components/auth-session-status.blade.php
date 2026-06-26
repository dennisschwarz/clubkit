@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'ck-auth-status']) }}>
        {{ $status }}
    </div>
@endif
