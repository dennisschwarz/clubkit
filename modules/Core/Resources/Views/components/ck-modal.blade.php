@props([
    'id'    => 'modal',
    'title' => '',
    'size'  => 'lg',
])

<div
    id="{{ $id }}"
    class="ck-modal-overlay"
    onclick="ckModalClose(event, '{{ $id }}')"
>
    <div class="ck-modal-content ck-modal-content--{{ $size }}" onclick="event.stopPropagation()">

        {{-- Header --}}
        <div class="ck-modal__header">
            <h2 id="{{ $id }}-title" class="ck-modal__title">{{ $title }}</h2>
            <button type="button" class="ck-modal__close" onclick="ckModalClose(null, '{{ $id }}')">
                &times;
            </button>
        </div>

        {{-- Tab-Leiste (optional) --}}
        @isset($tabs)
        <div class="ck-modal__tabbar">
            {{ $tabs }}
        </div>
        @endisset

        {{-- Body --}}
        <div class="ck-modal__body">
            {{ $slot }}
        </div>

    </div>
</div>
