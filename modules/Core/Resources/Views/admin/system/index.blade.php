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

{{-- Stat tiles --}}
<div class="ck-stat-grid ck-mb-6">

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">ClubKit</div>
        <div class="ck-stat-card__value ck-stat-card__value--lg">
            {{ $installed[0]->version ?? '–' }}
        </div>
        <div class="ck-stat-card__sub">
            Installiert: {{ $installedAt ?? '–' }}
        </div>
    </div>

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">Laravel</div>
        <div class="ck-stat-card__value ck-stat-card__value--lg">{{ $laravelVersion }}</div>
        <div class="ck-stat-card__sub">
            Umgebung: {{ config('app.env') }}
            @if(config('app.debug'))
                <x-ck-badge color="amber">DEBUG AN</x-ck-badge>
            @endif
        </div>
    </div>

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">PHP</div>
        <div class="ck-stat-card__value ck-stat-card__value--lg">{{ $phpVersion }}</div>
        <div class="ck-stat-card__sub">SAPI: {{ PHP_SAPI }}</div>
    </div>

    <div class="ck-stat-card {{ $migrationsStatus['ok'] ? 'ck-stat-card--ok' : 'ck-stat-card--warn' }}">
        <div class="ck-stat-card__label">Datenbank</div>
        <div class="ck-stat-card__value ck-stat-card__value--lg">
            {{ $migrationsStatus['ok'] ? 'Aktuell' : $migrationsStatus['pending'] . ' ausstehend' }}
        </div>
        <div class="ck-stat-card__sub">{{ $dbName }}</div>
    </div>

</div>

{{-- Details --}}
<div class="ck-two-col-grid">

    <x-ck-card>
        <x-slot:header>🔧 Konfiguration</x-slot:header>
        <table class="ck-table">
            <tbody>
                <tr>
                    <td class="ck-text-muted ck-table__label-col">App-URL</td>
                    <td>
                        <a href="{{ $appUrl }}" target="_blank" class="ck-link">
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
        <x-slot:header>🧩 Installierte Module ({{ count($installed) }})</x-slot:header>
        @if(empty($installed))
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
                    <td class="ck-font-weight-bold">{{ $module->name }}</td>
                    <td class="ck-text-muted">{{ $module->version }}</td>
                    <td>
                        @if($module->is_active)
                            <x-ck-badge color="green">{{ __('Active') }}</x-ck-badge>
                        @else
                            <x-ck-badge color="gray">{{ __('Inactive') }}</x-ck-badge>
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
