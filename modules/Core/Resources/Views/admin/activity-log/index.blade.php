@extends('core::admin.layout')

@section('title', __('core.appearance.title'))

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ __('core.appearance.title') }}</h1>
        <p class="ck-page-subtitle">{{ __('core.appearance.subtitle') }}</p>
    </div>
</div>

{{-- ════════════════════════════════════════════════
     CLUB IDENTITY
════════════════════════════════════════════════ --}}
<x-ck-card>

    <x-slot:header>{{ __('core.appearance.identity_title') }}</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            {{ __('Save') }}
        </x-ck-button>
    </x-slot:headerAction>

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

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            {{ __('Save') }}
        </x-ck-button>
    </x-slot:headerAction>

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

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            {{ __('Save') }}
        </x-ck-button>
    </x-slot:headerAction>

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

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.font_size') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <select name="nav_bar_font_size" class="ck-field__input" data-setting>
                    @foreach($fontSizes as $size)
                        <option value="{{ $size }}"
                            {{ $settings['nav_bar_font_size'] === $size ? 'selected' : '' }}>
                            {{ $size }} px
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     SUB-TAB BAR
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>{{ __('core.appearance.subtab_title') }}</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            {{ __('Save') }}
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.show_emojis') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.show_emojis_hint_subtab') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <label class="ck-field__checkbox">
                    <input type="checkbox"
                           name="subtab_show_emojis"
                           class="ck-field__input"
                           data-setting
                           value="1"
                           {{ $settings['subtab_show_emojis'] === '1' ? 'checked' : '' }}>
                    {{ __('core.appearance.show_emojis') }}
                </label>
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.bg_color') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_bg" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_bg'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.text_color') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.text_inactive_subtab') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_text" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_text'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.hover_color') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.hover_color_hint_subtab') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_hover" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_hover'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.active_highlight') }}</div>
                <div class="ck-settings-row__hint">{{ __('core.appearance.active_highlight_hint_subtab') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_active_bar" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_active_bar'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.font_size') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <select name="subtab_font_size" class="ck-field__input" data-setting>
                    @foreach($fontSizes as $size)
                        <option value="{{ $size }}"
                            {{ $settings['subtab_font_size'] === $size ? 'selected' : '' }}>
                            {{ $size }} px
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     GENERAL
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>{{ __('core.appearance.general_title') }}</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            {{ __('Save') }}
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">{{ __('core.appearance.body_bg') }}</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="body_bg" class="ck-field__input" data-setting
                       value="{{ $settings['body_bg'] }}">
            </div>
        </div>

    </div>

</x-ck-card>

{{-- Delete logo (only shown when a logo is set) --}}
@if(!empty($settings['logo_path']))
<x-ck-card class="ck-mt-5">
    <x-slot:header>{{ __('core.appearance.logo_remove_title') }}</x-slot:header>
    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
    </x-slot:headerAction>
    <form method="POST" action="{{ route('admin.appearance.logo.delete') }}">
        @csrf
        @method('DELETE')
        <x-ck-button
            variant="danger"
            type="submit"
            :confirm="__('core.appearance.logo_remove_confirm')">
            {{ __('Remove logo') }}
        </x-ck-button>
    </form>
</x-ck-card>
@endif

@push('scripts')
{{-- Data bridge: route + current CSS variables for JS --}}
<script>
window.CK_Appearance = {
    routes: {
        update: "{{ route('admin.appearance.update') }}"
    }
};
</script>
@vite('resources/js/modules/appearance-modal.js')
@endpush

@endsection
