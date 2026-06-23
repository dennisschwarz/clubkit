<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ClubKit') – {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

@php
    $moduleLoader = app(\App\Services\ModuleLoader::class);
    $navItems = collect($moduleLoader->getNavItems())->sortBy('nav_order')->values();
    $hasSubtabs = request()->routeIs('admin.*');
@endphp

{{-- ══════════════════════════════════════════════════════════════
     HEADER
     Zeile 1: Brand-Bar (Logo | User + Logout)
     Zeile 2: Nav-Bar (Dashboard + Module | Einstellungen)
══════════════════════════════════════════════════════════════ --}}
<div class="ck-header">

    {{-- Zeile 1: Brand-Bar --}}
    <div class="ck-brand-bar">

        {{-- LINKS: Logo + App-Name --}}
        <a href="{{ route('dashboard') }}" class="ck-header__brand">
            <div class="ck-header__logo">CK</div>
            <div>
                <div class="ck-header__app-name">{{ config('app.name') }}</div>
                <div class="ck-header__app-sub">Verwaltungssystem</div>
            </div>
        </a>

        {{-- RECHTS: Username (→ Profil) + Abmelden --}}
        <div class="ck-header__user">
            @if(Route::has('profile.edit'))
                <a href="{{ route('profile.edit') }}" class="ck-header__username">
                    {{ auth()->user()->name }}
                </a>
            @else
                <span class="ck-header__username">{{ auth()->user()->name }}</span>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ck-header__logout">Abmelden</button>
            </form>
        </div>

    </div>{{-- /.ck-brand-bar --}}

    {{-- Zeile 2: Nav-Bar --}}
    <div class="ck-nav-bar">
        <div class="ck-nav-bar__inner">

            {{-- LINKS: Dashboard + Modul-Tabs (sortiert nach nav_order) --}}
            <div class="ck-nav-bar__left">
                <a href="{{ route('dashboard') }}"
                   class="ck-nav-tab {{ request()->routeIs('dashboard') ? 'ck-nav-tab--active' : '' }}">
                    🏠 Dashboard
                </a>
                @foreach($navItems as $item)
                    @if(auth()->user()->hasRole('admin') || auth()->user()->can($item['permission'] ?? 'view ' . $item['module']))
                        <a href="{{ route($item['route']) }}"
                           class="ck-nav-tab {{ request()->routeIs($item['module'] . '.*') ? 'ck-nav-tab--active' : '' }}">
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>

            {{-- RECHTS: Einstellungen --}}
            <div class="ck-nav-bar__right">
                @role('admin')
                <a href="{{ route('admin.system.index') }}"
                   class="ck-nav-tab {{ request()->routeIs('admin.*') ? 'ck-nav-tab--active' : '' }}">
                    ⚙️ Einstellungen
                </a>
                @endrole
            </div>

        </div>
    </div>{{-- /.ck-nav-bar --}}

</div>{{-- /.ck-header --}}

{{-- Sub-Tabs (nur unter /admin/*) --}}
@if($hasSubtabs)
<div class="ck-subtabbar-wrap">
    <nav class="ck-subtabbar">
        <a href="{{ route('admin.system.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.system.*') ? 'ck-subtab--active' : '' }}">
            🖥️ System
        </a>
        <a href="{{ route('admin.users.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.users.*') ? 'ck-subtab--active' : '' }}">
            👤 Nutzer
        </a>
        <a href="{{ route('admin.modules.index') }}"
           class="ck-subtab {{ request()->routeIs('admin.modules.*') ? 'ck-subtab--active' : '' }}">
            🧩 Module
        </a>
    </nav>
</div>
@endif

{{-- Body --}}
<div class="ck-body {{ $hasSubtabs ? 'ck-body--with-subtabs' : '' }}">

    @if(session('success'))
    <div class="ck-flash ck-flash--success" data-flash>✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="ck-flash ck-flash--error" data-flash>⚠️ {{ session('error') }}</div>
    @endif

    <main class="ck-content">
        @yield('content')
    </main>

</div>

@stack('scripts')
</body>
</html>