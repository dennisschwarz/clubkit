@extends('core::admin.layout')

@section('title', __('core.appearance.title'))

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ __('core.appearance.title') }}</h1>
        <p class="ck-page-subtitle">{{ __('core.appearance.subtitle') }}</p>
    </div>
    <div class="ck-page-header__actions">
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" data-appearance-save>
            {{ __('Save') }}
        </x-ck-button>
    </div>
</div>

{{-- ════════════════════════════════════════════════
     CLUB IDENTITY
════════════════════════════════════════════════ --}}
<x-ck-card>

    <x-slot:header>{{ __('core.appearance.identity_title') }}</x-slot:header>

    <div class="ck-settings-section">

        {{-- Club name --}}
        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.club_name') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.club_name_hint') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="text"
                       name="club_name"
                       class="ck-field__input"
                       data-setting
                       value="{{ $settings['club_name'] }}"
                       maxlength="60"
                       placeholder="z. B. HSV Langenfeld 1959 e.V.">
            </div>
        </div>

        {{-- Logo --}}
        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.logo') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.logo_hint') }}</div>
            </div>
            <div class="ck-settings-row__input">
                @if(!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}"
                         alt="{{ __('core.appearance.logo_alt') }}"
                         class="ck-appearance__logo-preview">
                @endif
                <input type="file"
                       name="logo"
                       class="ck-field__input"
                       data-setting
                       accept="image/jpeg,image/png,image/webp">
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     BRAND BAR (row 1 in header)
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>{{ __('core.appearance.brand_bar_title') }}</x-slot:header>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.bg_color') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="header_bg" class="ck-field__input" data-setting
                       value="{{ $settings['header_bg'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.text_color') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.text_color_hint_brand') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="brand_bar_text" class="ck-field__input" data-setting
                       value="{{ $settings['brand_bar_text'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.hover_color') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.hover_color_hint_brand') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="brand_bar_hover" class="ck-field__input" data-setting
                       value="{{ $settings['brand_bar_hover'] }}">
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     NAVIGATION BAR (row 2 in header)
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>{{ __('core.appearance.nav_bar_title') }}</x-slot:header>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.show_emojis') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.show_emojis_hint_nav') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <label class="ck-field__checkbox">
                    <input type="checkbox"
                           name="nav_bar_show_emojis"
                           class="ck-field__input"
                           data-setting
                           value="1"
                           {{ $settings['nav_bar_show_emojis'] === '1' ? 'checked' : '' }}>
                    {{ __('core.appearance.show_emojis') }}
                </label>
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.bg_color') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_bg" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_bg'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.text_color') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.text_inactive') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_text" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_text'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.hover_color') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.hover_color_hint_nav') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_hover" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_hover'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.active_highlight') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.active_highlight_hint_nav') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_active_bar" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_active_bar'] }}">
            </div>
        </div>

    </div>

</x-ck-card>

@push('scripts')
<script>
window.CK_Appearance = {
    routes: { update: "{{ route('admin.appearance.update') }}" }
};
</script>
@vite(['resources/js/modules/appearance-modal.js'])
@endpush

@endsection
