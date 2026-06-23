@extends('core::admin.layout')

@section('title', 'System')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">System-Überblick</h1>
        <p class="text-sm text-gray-500 mt-1">Status und Konfiguration dieser ClubKit-Installation.</p>
    </div>

    {{-- Info-Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- ClubKit --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">ClubKit</div>
            <div class="text-2xl font-bold text-gray-900">1.0.3-dev</div>
            @if($installedAt)
                <div class="text-xs text-gray-400 mt-1">Installiert: {{ $installedAt }}</div>
            @endif
        </div>

        {{-- Laravel --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Laravel</div>
            <div class="text-2xl font-bold text-gray-900">{{ $laravelVersion }}</div>
            <div class="text-xs text-gray-400 mt-1">
                Umgebung: <span class="{{ config('app.env') === 'production' ? 'text-green-600' : 'text-amber-600' }} font-medium">{{ config('app.env') }}</span>
                @if(config('app.debug'))
                    <span class="ml-2 text-red-600 font-medium">DEBUG AN</span>
                @endif
            </div>
        </div>

        {{-- PHP --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">PHP</div>
            <div class="text-2xl font-bold text-gray-900">{{ $phpVersion }}</div>
            <div class="text-xs text-gray-400 mt-1">SAPI: {{ php_sapi_name() }}</div>
        </div>

        {{-- Datenbank --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Datenbank</div>
            <div class="text-2xl font-bold {{ $migrationsStatus['ok'] ? 'text-green-600' : 'text-red-600' }}">
                {{ $migrationsStatus['ok'] ? 'Aktuell' : $migrationsStatus['pending'] . ' ausstehend' }}
            </div>
            <div class="text-xs text-gray-400 mt-1">{{ strtoupper(config('database.default')) }}: {{ $dbName }}</div>
        </div>

        {{-- App-URL --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">App-URL</div>
            <div class="text-sm font-mono text-gray-900 break-all">{{ $appUrl }}</div>
        </div>

    </div>

    {{-- Installierte Module --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Installierte Module</div>
        @if(count($installed))
            <div class="flex flex-wrap gap-2">
                @foreach($installed as $slug => $module)
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium
                                 {{ $module->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                        {{ $module->name }}
                        <span class="text-[10px] opacity-60">{{ $module->version }}</span>
                    </span>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">Keine Module installiert.</p>
        @endif
    </div>

    {{-- Migrations --}}
    @if(!$migrationsStatus['ok'])
    <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-5">
        <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-3">
            ⚠️ {{ $migrationsStatus['pending'] }} ausstehende Migration(en)
        </div>
        <form method="POST" action="{{ route('admin.system.migrate') }}">
            @csrf
            <button type="submit"
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition-colors">
                Migrationen ausführen
            </button>
        </form>
    </div>
    @endif

</div>
@endsection
