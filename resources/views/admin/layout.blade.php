{{-- resources/views/admin/layout.blade.php --}}
<!DOCTYPE html>
<html lang="de" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClubKit Admin – @yield('title', 'Übersicht')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-slate-800">

{{-- Top Bar --}}
<header class="bg-slate-900 text-white shadow-md">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-lg font-extrabold tracking-tight">⚙️ ClubKit</span>
                <span class="rounded bg-slate-700 px-2 py-0.5 text-xs text-slate-300">Admin</span>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-400">{{ auth()->user()->name }}</span>
                <a href="{{ route('dashboard') }}" class="text-slate-300 hover:text-white transition">← App</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-300 hover:text-white transition">Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>

{{-- Sub-Tab Navigation --}}
<nav class="border-b border-slate-200 bg-white shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex gap-1 overflow-x-auto">
            @php
                $tabs = [
                    ['route' => 'admin.system.index', 'label' => '🖥️ System',   'match' => 'admin/system'],
                    // Weitere Tabs werden hier ergänzt:
                    // ['route' => 'admin.teams.index',  'label' => '🏟️ Teams',    'match' => 'admin/teams'],
                    // ['route' => 'admin.members.index','label' => '👥 Mitglieder','match' => 'admin/members'],
                    // ['route' => 'admin.settings',     'label' => '🔧 Einstellungen','match' => 'admin/settings'],
                ];
            @endphp

            @foreach($tabs as $tab)
                @php
                    $isActive = request()->is($tab['match']) || request()->is($tab['match'] . '/*');
                @endphp
                <a href="{{ route($tab['route']) }}"
                   class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold transition
                          {{ $isActive
                              ? 'border-blue-600 text-blue-600'
                              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</nav>

{{-- Flash Messages --}}
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            ⚠️ {{ session('error') }}
        </div>
    @endif
</div>

{{-- Page Content --}}
<main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
    @yield('content')
</main>

</body>
</html>
