@extends('core::admin.layout')
@section('title', 'System')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">System-Überblick</h1>
        <p class="ck-page-subtitle">Status und Konfiguration dieser ClubKit-Installation.</p>
    </div>
    @if(!$migrationsStatus['ok'])
    <form method="POST" action="{{ route('admin.system.migrate') }}">
        @csrf
        <x-ck-button type="submit" variant="primary">
            {{ $migrationsStatus['pending'] }} Migration(en) ausführen
        </x-ck-button>
    </form>
    @endif
</div>

{{-- Stat-Kacheln --}}
<div class="ck-stat-grid" style="margin-bottom:var(--ck-space-6);">

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">ClubKit</div>
        <div class="ck-stat-card__value" style="font-size:20px;">
            {{ $installed->first()?->version ?? '–' }}
        </div>
        <div class="ck-stat-card__sub">
            Installiert: {{ $installedAt ?? '–' }}
        </div>
    </div>

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">Laravel</div>
        <div class="ck-stat-card__value" style="font-size:20px;">{{ $laravelVersion }}</div>
        <div class="ck-stat-card__sub">
            Umgebung: {{ config('app.env') }}
            @if(config('app.debug'))
                <x-ck-badge color="amber">DEBUG AN</x-ck-badge>
            @endif
        </div>
    </div>

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">PHP</div>
        <div class="ck-stat-card__value" style="font-size:20px;">{{ $phpVersion }}</div>
        <div class="ck-stat-card__sub">SAPI: {{ PHP_SAPI }}</div>
    </div>

    <div class="ck-stat-card {{ $migrationsStatus['ok'] ? 'ck-stat-card--ok' : 'ck-stat-card--warn' }}">
        <div class="ck-stat-card__label">Datenbank</div>
        <div class="ck-stat-card__value" style="font-size:20px;">
            {{ $migrationsStatus['ok'] ? 'Aktuell' : $migrationsStatus['pending'] . ' ausstehend' }}
        </div>
        <div class="ck-stat-card__sub">{{ $dbName }}</div>
    </div>

</div>

{{-- Details --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--ck-space-4);">

    <x-ck-card>
        <x-slot:header>🔧 Konfiguration</x-slot:header>
        <table class="ck-table">
            <tbody>
                <tr>
                    <td class="ck-text-muted" style="width:40%;">App-URL</td>
                    <td>
                        <a href="{{ $appUrl }}" target="_blank"
                           style="color:var(--ck-accent-dark); text-decoration:none;">
                            {{ $appUrl }}
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Umgebung</td>
                    <td>{{ config('app.env') }}</td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Debug</td>
                    <td>
                        @if(config('app.debug'))
                            <x-ck-badge color="amber">Aktiv – in Produktion deaktivieren!</x-ck-badge>
                        @else
                            <x-ck-badge color="green">Inaktiv</x-ck-badge>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Cache-Treiber</td>
                    <td>{{ config('cache.default') }}</td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Session-Treiber</td>
                    <td>{{ config('session.driver') }}</td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Datenbank</td>
                    <td>{{ $dbName }}</td>
                </tr>
            </tbody>
        </table>
    </x-ck-card>

    <x-ck-card>
        <x-slot:header>🧩 Installierte Module ({{ $installed->count() }})</x-slot:header>
        @if($installed->isEmpty())
        <p class="ck-text-muted">Keine Module installiert.</p>
        @else
        <table class="ck-table">
            <thead>
                <tr>
                    <th>Modul</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($installed as $module)
                <tr>
                    <td style="font-weight:600;">{{ $module->name }}</td>
                    <td class="ck-text-muted">{{ $module->version }}</td>
                    <td>
                        @if($module->is_active)
                            <x-ck-badge color="green">Aktiv</x-ck-badge>
                        @else
                            <x-ck-badge color="gray">Inaktiv</x-ck-badge>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </x-ck-card>

</div>

@endsection
