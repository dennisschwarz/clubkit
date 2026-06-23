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

{{-- ══ HEADER: 1 Zeile (Logo | Nav | User) ══ --}}
<div class="ck-header">
    <div class="ck-header__row">

        {{-- LINKS: Logo + Name --}}
        <div class="ck-header__brand">
            <div class="ck-header__logo">CK</div>
            <div>
                <div class="ck-header__app-name">{{ config('app.name') }}</div>
                <div class="ck-header__app-sub">Verwaltungssystem</div>
            </div>
        </div>

        {{-- MITTE: Tabs --}}
        <nav class="ck-header__nav">
            <a href="{{ route('dashboard') }}"
               class="ck-nav-tab {{ request()->routeIs('dashboard') ? 'ck-nav-tab--active' : '' }}">
                🏠 Dashboard
            </a>
            @foreach(app(\App\Services\ModuleLoader::class)->getNavItems() as $item)
                @if(auth()->user()->hasRole('admin') || auth()->user()->can($item['permission'] ?? 'view ' . $item['module']))
                    <a href="{{ route($item['route']) }}"
                       class="ck-nav-tab {{ request()->routeIs($item['module'] . '.*') ? 'ck-nav-tab--active' : '' }}">
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
            @role('admin')
            <a href="{{ route('admin.system.index') }}"
               class="ck-nav-tab {{ request()->routeIs('admin.*') ? 'ck-nav-tab--active' : '' }}">
                ⚙️ Einstellungen
            </a>
            @endrole
        </nav>

        {{-- RECHTS: User --}}
        <div class="ck-header__user">
            <span class="ck-header__username">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ck-header__logout">Abmelden</button>
            </form>
        </div>

    </div>
</div>

{{-- Sub-Tabs (nur Einstellungen) --}}
@if(request()->routeIs('admin.*'))
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
<div class="ck-body {{ request()->routeIs('admin.*') ? 'ck-body--with-subtabs' : '' }}">

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
