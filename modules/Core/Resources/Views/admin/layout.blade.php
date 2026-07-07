<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ClubKit') – {{ $ckSettings['club_name'] ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- ══════════════════════════════════════════════════════════════
         Dynamic CSS variables from the settings table.
         id="ck-dynamic-css" is read by appearance-modal.js and updated
         on AJAX save without requiring a full page reload.
    ══════════════════════════════════════════════════════════════ --}}
    @php $s = $ckSettings ?? []; @endphp
    <style id="ck-dynamic-css">
    :root {
        /* Brand bar */
        --ck-brand-bar-bg:          {{ $s['header_bg']           ?? '#0a1628' }};
        --ck-brand-bar-text:        {{ $s['brand_bar_text']      ?? '#ffffff' }};
        --ck-brand-bar-hover:       {{ $s['brand_bar_hover']     ?? '#e2e8f0' }};
        /* Navigation bar */
        --ck-nav-bar-bg:            {{ $s['nav_bar_bg']          ?? '#132238' }};
        --ck-nav-bar-text:          {{ $s['nav_bar_text']        ?? '#a0aec0' }};
        --ck-nav-bar-hover:         {{ $s['nav_bar_hover']       ?? '#e2e8f0' }};
        --ck-nav-bar-active-bar:    {{ $s['nav_bar_active_bar']  ?? '#60a5fa' }};
        --ck-nav-bar-font-size:     {{ ($s['nav_bar_font_size']  ?? '14') }}px;
        /* Sub-tab bar */
        --ck-subtab-bg:             {{ $s['subtab_bg']           ?? '#ffffff' }};
        --ck-subtab-text:           {{ $s['subtab_text']         ?? '#64748b' }};
        --ck-subtab-hover:          {{ $s['subtab_hover']        ?? '#1e293b' }};
        --ck-subtab-active-bar:     {{ $s['subtab_active_bar']   ?? '#60a5fa' }};
        --ck-subtab-font-size:      {{ ($s['subtab_font_size']   ?? '14') }}px;
        /* General */
        --ck-bg:                    {{ $s['body_bg']             ?? '#f0f3f8' }};
    }
    </style>
</head>
<body>

@php
    $moduleLoader = app(\App\Services\ModuleLoader::class);
    $navItems     = collect($moduleLoader->getNavItems())->sortBy('nav_order')->values();
    $hasSubtabs   = request()->routeIs('admin.*');

    $showNavEmojis = ($s['nav_bar_show_emojis'] ?? '1') === '1';
    $showSubEmojis = ($s['subtab_show_emojis']  ?? '1') === '1';

    $noEmoji = function(string $text): string {
        return trim(preg_replace('/^[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]\s*/u', '', $text));
    };
@endphp

{{-- ══════════════════════════════════════════════════════════════
     HEADER
══════════════════════════════════════════════════════════════════ --}}
<div class="ck-header">

    {{-- Row 1: brand bar --}}
    <div class="ck-brand-bar">
        <a href="{{ route('dashboard') }}" class="ck-header__brand">
            <div class="ck-header__logo">
                @if(!empty($s['logo_path']))
                    <img src="{{ asset('storage/' . $s['logo_path']) }}"
                         alt="{{ $s['club_name'] ?? config('app.name') }}">
                @else
                    CK
                @endif
            </div>
            <div>
                <div class="ck-header__app-name">{{ $s['club_name'] ?? config('app.name') }}</div>
                <div class="ck-header__app-sub">Verwaltungssystem</div>
            </div>
        </a>
        <div class="ck-header__user">
            <span class="ck-header__username">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ck-header__logout">Abmelden</button>
            </form>
        </div>
    </div>

    {{-- Row 2: main navigation bar --}}
    <div class="ck-nav-bar">
        <div class="ck-nav-bar__inner">
            <div class="ck-nav-bar__left">
                <a href="{{ route('dashboard') }}"
                   class="ck-nav-tab {{ request()->routeIs('dashboard') ? 'ck-nav-tab--active' : '' }}">
                    {{ $showNavEmojis ? '🏠 ' : '' }}Dashboard
                </a>
                @foreach($navItems as $item)
                    @if(auth()->user()->hasRole('admin') || auth()->user()->can($item['permission'] ?? 'view ' . $item['module']))
                        <a href="{{ route($item['route']) }}"
                           class="ck-nav-tab {{ request()->routeIs($item['module'] . '.*') ? 'ck-nav-tab--active' : '' }}">
                            {{ $showNavEmojis ? $item['label'] : $noEmoji($item['label']) }}
                        </a>
                    @endif
                @endforeach
            </div>
            <div class="ck-nav-bar__right">
                @role('admin')
                <a href="{{ route('admin.system.index') }}"
                   class="ck-nav-tab {{ request()->routeIs('admin.*') ? 'ck-nav-tab--active' : '' }}">
                    {{ $showNavEmojis ? '⚙️ ' : '' }}Einstellungen
                </a>
                @endrole
            </div>
        </div>
    </div>

</div>

{{-- Admin sub-tab bar --}}
@if($hasSubtabs)
<div class="ck-subtabbar-wrap">
    <nav class="ck-subtabbar">
        <a href="{{ route('admin.system.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.system.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '🖥️ ' : '' }}System
        </a>
        <a href="{{ route('admin.users.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.users.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '👤 ' : '' }}Nutzer
        </a>
        <a href="{{ route('admin.roles.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.roles.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '🔒 ' : '' }}Rollen & Rechte
        </a>
        <a href="{{ route('admin.modules.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.modules.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '🧩 ' : '' }}Module
        </a>
        <a href="{{ route('admin.appearance.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.appearance.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '🎨 ' : '' }}Erscheinungsbild
        </a>
        <a href="{{ route('admin.module-settings.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.module-settings.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '🔧 ' : '' }}Modul-Einstellungen
        </a>
        <a href="{{ route('admin.activity-log.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.activity-log.*') ? 'ck-subtab--active' : '' }}">
            {{ $showSubEmojis ? '📋 ' : '' }}Aktivitätsprotokoll
        </a>
    </nav>
</div>
@endif

{{-- Body --}}
<div class="ck-body {{ $hasSubtabs ? 'ck-body--with-subtabs' : '' }}">

    {{-- ══════════════════════════════════════════════════════════════
         Noscript flash fallback.
         Visible only when JavaScript is disabled.
         JS users receive toasts via window.CK_Notifications + ckNotify().
         CSS: resources/css/components/layout.css → .ck-flash
    ══════════════════════════════════════════════════════════════ --}}
    <noscript>
        @if(session('success'))
        <div class="ck-flash ck-flash--success">✅ {{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="ck-flash ck-flash--error">⚠️ {{ session('error') }}</div>
        @endif
        @if(session('warning'))
        <div class="ck-flash ck-flash--warning">🔔 {{ session('warning') }}</div>
        @endif
    </noscript>

    <main class="ck-content">
        @yield('content')
    </main>
</div>

{{-- ══════════════════════════════════════════════════════════════
     JS NOTIFICATION BRIDGE
     Passes server-side flash session data to the JS toast system.
     Read by app.js → DOMContentLoaded → ckNotify() for each entry.
     Only rendered when there is at least one flash message.
══════════════════════════════════════════════════════════════ --}}
@if(session()->hasAny(['success', 'error', 'warning']))
<script>
window.CK_Notifications = [
    @if(session('success'))
    { type: 'success', message: {{ Js::from(session('success')) }} },
    @endif
    @if(session('error'))
    { type: 'error', message: {{ Js::from(session('error')) }} },
    @endif
    @if(session('warning'))
    { type: 'warning', message: {{ Js::from(session('warning')) }} },
    @endif
];
</script>
@endif

{{-- ══════════════════════════════════════════════════════════════
     JS LANGUAGE BRIDGE
     Passes localised notification strings to JS modules so that
     AJAX toasts (member-teams.js, youth-club-mode.js etc.) are
     translated. Keys mirror lang/{locale}/notifications.php.
══════════════════════════════════════════════════════════════ --}}
<script>
window.CK_Lang = {
    notifications: {
        teams_saved:          {{ Js::from(__('notifications.teams_saved')) }},
        teams_save_error:     {{ Js::from(__('notifications.teams_save_error')) }},
        teams_member_added:   {{ Js::from(__('notifications.teams_member_added')) }},
        teams_member_removed: {{ Js::from(__('notifications.teams_member_removed')) }},
        relation_added:       {{ Js::from(__('notifications.relation_added')) }},
        relation_add_error:   {{ Js::from(__('notifications.relation_add_error')) }},
        relation_removed:     {{ Js::from(__('notifications.relation_removed')) }},
        relation_delete_error:{{ Js::from(__('notifications.relation_delete_error')) }},
        network_error:        {{ Js::from(__('notifications.network_error')) }},
    }
};
</script>

@stack('scripts')

{{-- ══════════════════════════════════════════════════════════════
     MODAL ROOT
     All .ck-modal-overlay elements are teleported here by app.js
     so they sit above all page content in the DOM stacking context.
══════════════════════════════════════════════════════════════ --}}
<div id="ck-modal-root"></div>

{{-- ══════════════════════════════════════════════════════════════
     GLOBAL CONFIRM MODAL
     Triggered by [data-ck-confirm] buttons (rendered by x-ck-button
     with :confirm="...") and by window.ckConfirm() from JS modules.
     Both paths share the same modal so delete confirmations are
     visually consistent across the entire application.
     JS: app.js → ckConfirm() / [data-ck-confirm] handler
     CSS: resources/css/components/modals.css → .ck-modal__footer
══════════════════════════════════════════════════════════════ --}}
<div id="ck-confirm-overlay" class="ck-modal-overlay" onclick="if(event.target===this){ ckConfirmCancel(); }">
    <div class="ck-modal-content ck-modal-content--sm" onclick="event.stopPropagation()">

        <div class="ck-modal__header">
            <h2 class="ck-modal__title">🗑 Löschen bestätigen</h2>
            <button type="button" class="ck-modal__close" onclick="ckConfirmCancel()">&times;</button>
        </div>

        <div class="ck-modal__body">
            <p id="ck-confirm-text" class="ck-confirm__text"></p>
            <div class="ck-alert ck-alert--danger">
                ⚠️ Diese Aktion kann nicht rückgängig gemacht werden.
            </div>
        </div>

        <div class="ck-modal__footer">
            <button type="button" id="ck-confirm-ok" class="ck-btn ck-btn--danger">
                Ja, löschen
            </button>
            <button type="button" class="ck-btn ck-btn--secondary"
                onclick="ckConfirmCancel()">
                Abbrechen
            </button>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     GLOBAL SHARED DELETE FORM
     Used by [data-delete-url] buttons across all module list views.
     The app.js [data-ck-confirm] handler sets this form's action
     dynamically before calling requestSubmit() after confirmation.

     Why: HTML <form> is a block element. Wrapping each table-row
     delete button in its own <form> breaks the inline layout of
     <td class="ck-table__action-cell">. A single shared form
     outside the table avoids that problem entirely.

     Usage in views:
       <x-ck-button variant="danger" size="icon"
           data-delete-url="{{ route('things.destroy', $thing) }}"
           :confirm="'Wirklich löschen?'">
           ...
       </x-ck-button>

     JS:  resources/js/app.js → [data-ck-confirm] + data-delete-url
══════════════════════════════════════════════════════════════ --}}
<form id="ck-delete-form" method="POST" action="">
    @csrf
    @method('DELETE')
</form>

{{-- ══════════════════════════════════════════════════════════════
     LOADING OVERLAY
     CSS: resources/css/components/modals.css → .ck-loading-overlay
     JS:  resources/js/app.js → showLoading() / pageshow
     Both id and class are required: id for JS lookup, class for CSS.
══════════════════════════════════════════════════════════════ --}}
<div id="ck-loading-overlay" class="ck-loading-overlay">
    <div class="ck-loading-spinner"></div>
</div>

</body>
</html>