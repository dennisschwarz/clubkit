{{-- resources/views/admin/system/index.blade.php --}}
@extends('admin.layout')

@section('title', 'System')

@section('content')

{{-- Seiten-Header --}}
<div class="mb-6">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">System-Übersicht</h1>
    <p class="mt-1 text-sm text-slate-500">Status und Konfiguration dieser ClubKit-Installation.</p>
</div>

{{-- Update Banner (nur wenn Migrations ausstehen) --}}
@if($hasPending)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <span class="text-2xl">🔔</span>
            <div class="flex-1">
                <p class="font-semibold text-amber-900">Update verfügbar</p>
                <p class="mt-1 text-sm text-amber-700">Es gibt ausstehende Datenbankmigrationen. Bitte jetzt ausführen.</p>
            </div>
            <form method="POST" action="{{ route('admin.system.migrate') }}">
                @csrf
                <button type="submit"
                        onclick="return confirm('Migrations jetzt ausführen?')"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 transition">
                    Migrations ausführen →
                </button>
            </form>
        </div>
    </div>
@endif

{{-- Status Karten --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

    {{-- ClubKit Version --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">ClubKit</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $info['clubkit_version'] }}</p>
        @if($info['installed_at'])
            <p class="mt-1 text-xs text-slate-400">Installiert: {{ $info['installed_at'] }}</p>
        @endif
    </div>

    {{-- Laravel --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Laravel</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $info['laravel_version'] }}</p>
        <p class="mt-1 text-xs text-slate-400">Umgebung:
            <span class="font-medium {{ $info['env'] === 'production' ? 'text-green-600' : 'text-amber-600' }}">
                {{ $info['env'] }}
            </span>
            @if($info['debug'])
                <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">DEBUG AN</span>
            @endif
        </p>
    </div>

    {{-- PHP --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">PHP</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $info['php_version'] }}</p>
        <p class="mt-1 text-xs text-slate-400">SAPI: {{ $info['php_sapi'] }}</p>
    </div>

    {{-- Datenbank --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Datenbank</p>
        <div class="mt-1 flex items-center gap-2">
            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $info['db_status'] ? 'bg-green-500' : 'bg-red-500' }}"></span>
            <p class="text-lg font-bold text-slate-900">{{ $info['db_status'] ? 'Verbunden' : 'Fehler' }}</p>
        </div>
        <p class="mt-1 text-xs text-slate-400">{{ strtoupper($info['db_driver']) }}: {{ $info['db_name'] }}</p>
    </div>

    {{-- App URL --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">App-URL</p>
        <p class="mt-1 truncate text-sm font-semibold text-blue-600">
            <a href="{{ $info['app_url'] }}" target="_blank">{{ $info['app_url'] }}</a>
        </p>
    </div>

    {{-- Migrations Status --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Migrations</p>
        <div class="mt-1 flex items-center gap-2">
            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $hasPending ? 'bg-amber-400' : 'bg-green-500' }}"></span>
            <p class="text-lg font-bold text-slate-900">
                {{ $hasPending ? 'Ausstehend' : 'Aktuell' }}
            </p>
        </div>
        <p class="mt-1 text-xs text-slate-400">
            {{ $hasPending ? 'Neue Migrations vorhanden' : 'Alle Migrations ausgeführt' }}
        </p>
    </div>

</div>

{{-- Module --}}
@if(!empty($info['modules']))
    <div class="mt-6">
        <h2 class="mb-3 text-base font-bold text-slate-700">Installierte Module</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($info['modules'] as $module)
                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                    {{ $module }}
                </span>
            @endforeach
        </div>
    </div>
@endif

{{-- Migrations manuell (immer sichtbar, auch wenn aktuell) --}}
@if(!$hasPending)
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold text-slate-700">Migrations manuell ausführen</h2>
        <p class="mt-1 text-xs text-slate-400">Alle Migrations sind aktuell. Trotzdem ausführen (z.B. nach manuellem Upload neuer Migration-Dateien).</p>
        <form method="POST" action="{{ route('admin.system.migrate') }}" class="mt-3">
            @csrf
            <button type="submit"
                    onclick="return confirm('Migrations jetzt ausführen?')"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                Migrations prüfen & ausführen
            </button>
        </form>
    </div>
@endif

@endsection
