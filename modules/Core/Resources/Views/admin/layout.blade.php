<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ClubKit') – {{ config('app.name') }}</title>

    {{-- Vite: kompiliertes CSS + globales JS --}}
    @vite(['resources/js/app.js'])

    {{-- Tailwind CDN nur als Fallback wenn Vite noch nicht gebaut hat --}}
    @if(!file_exists(public_path('build/manifest.json')))
    <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f3f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        #ck-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 999;
            background: #0a1628;
            box-shadow: 0 2px 12px rgba(0,0,0,0.4);
        }
        /* Logo-Zeile ~58px + Tab-Leiste ~42px */
        #ck-content { padding-top: 100px; min-height: 100vh; }
        #ck-content.has-subtabs { padding-top: 138px; }

        .ck-tab:hover { color: white !important; }
        .ck-tabbar { overflow-x: auto; scrollbar-width: none; }
        .ck-tabbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body>

<!-- ══════════════════ HEADER (fixed) ══════════════════ -->
<div id="ck-header">

    {{-- Logo-Zeile --}}
    <div style="display:flex; align-items:center; gap:12px; padding:10px 20px; border-bottom:1px solid rgba(255,255,255,0.1);">
        <div style="width:38px; height:38px; border-radius:50%; background:white; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:11px; color:#0a1628; flex-shrink:0;">CK</div>
        <div>
            <div style="font-size:15px; font-weight:700; color:white; line-height:1.2;">{{ config('app.name') }}</div>
            <div style="font-size:11px; color:rgba(255,255,255,0.45);">Verwaltungssystem</div>
        </div>
        <div style="margin-left:auto; display:flex; align-items:center; gap:16px;">
            <span style="color:rgba(255,255,255,0.5); font-size:13px;">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" style="color:rgba(255,255,255,0.45); font-size:13px; background:none; border:none; cursor:pointer;">
                    Abmelden
                </button>
            </form>
        </div>
    </div>

    {{-- Haupt-Tab-Leiste --}}
    <div class="ck-tabbar" style="display:flex; padding:0 16px;">

        <a href="{{ route('dashboard') }}" class="ck-tab"
           style="flex-shrink:0; padding:10px 16px; font-size:14px; font-weight:500; text-decoration:none; white-space:nowrap;
                  border-bottom:3px solid {{ request()->routeIs('dashboard') ? '#60a5fa' : 'transparent' }};
                  color:{{ request()->routeIs('dashboard') ? 'white' : 'rgba(255,255,255,0.55)' }};">
            🏠 Dashboard
        </a>

        @foreach(app(\App\Services\ModuleLoader::class)->getNavItems() as $item)
            @if(auth()->user()->hasRole('admin') || auth()->user()->can($item['permission'] ?? 'view ' . $item['module']))
                <a href="{{ route($item['route']) }}" class="ck-tab"
                   style="flex-shrink:0; padding:10px 16px; font-size:14px; font-weight:500; text-decoration:none; white-space:nowrap;
                          border-bottom:3px solid {{ request()->routeIs($item['module'] . '.*') ? '#60a5fa' : 'transparent' }};
                          color:{{ request()->routeIs($item['module'] . '.*') ? 'white' : 'rgba(255,255,255,0.55)' }};">
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach

        @role('admin')
        <a href="{{ route('admin.system.index') }}" class="ck-tab"
           style="flex-shrink:0; padding:10px 16px; font-size:14px; font-weight:500; text-decoration:none; white-space:nowrap;
                  border-bottom:3px solid {{ request()->routeIs('admin.*') ? '#60a5fa' : 'transparent' }};
                  color:{{ request()->routeIs('admin.*') ? 'white' : 'rgba(255,255,255,0.55)' }};">
            ⚙️ Einstellungen
        </a>
        @endrole

    </div>
</div>

{{-- Sub-Tabs Einstellungen (ebenfalls fixed, direkt unter Header) --}}
@if(request()->routeIs('admin.*'))
<div style="position:fixed; top:100px; left:0; right:0; z-index:998; background:white; border-bottom:2px solid #e2e8f0;">
    <div class="ck-tabbar" style="max-width:1040px; margin:0 auto; padding:0 20px; display:flex;">
        <a href="{{ route('admin.system.index') }}"
           style="padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap;
                  color:{{ request()->routeIs('admin.system.*') ? '#0a1628' : '#64748b' }};
                  border-bottom:2px solid {{ request()->routeIs('admin.system.*') ? '#0a1628' : 'transparent' }};
                  margin-bottom:-2px;">
            🖥️ System
        </a>
        <a href="{{ route('admin.users.index') }}"
           style="padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap;
                  color:{{ request()->routeIs('admin.users.*') ? '#0a1628' : '#64748b' }};
                  border-bottom:2px solid {{ request()->routeIs('admin.users.*') ? '#0a1628' : 'transparent' }};
                  margin-bottom:-2px;">
            👤 Nutzer
        </a>
        <a href="{{ route('admin.modules.index') }}"
           style="padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap;
                  color:{{ request()->routeIs('admin.modules.*') ? '#0a1628' : '#64748b' }};
                  border-bottom:2px solid {{ request()->routeIs('admin.modules.*') ? '#0a1628' : 'transparent' }};
                  margin-bottom:-2px;">
            🧩 Module
        </a>
    </div>
</div>
@endif

<!-- ══════════════════ CONTENT ══════════════════ -->
<div id="ck-content" class="{{ request()->routeIs('admin.*') ? 'has-subtabs' : '' }}">

    @if(session('success'))
    <div data-flash style="background:#f0fdf4; border-bottom:1px solid #86efac; padding:8px 20px; font-size:13px; color:#166534; text-align:center;">
        ✅ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div data-flash style="background:#fef2f2; border-bottom:1px solid #fca5a5; padding:8px 20px; font-size:13px; color:#991b1b; text-align:center;">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    <main style="max-width:1040px; margin:0 auto; padding:24px 20px 60px;">
        @yield('content')
    </main>

</div>

{{-- Modul-spezifische Scripts (Data Bridge + externe JS-Dateien) --}}
@stack('scripts')

</body>
</html>
