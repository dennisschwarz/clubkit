@extends('core::admin.layout')
@section('title', 'System')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">System-Überblick</h1>
        <p class="ck-page-subtitle">Status und Konfiguration dieser ClubKit-Installation.</p>
    </div>
    <form method="POST" action="{{ route('admin.system.migrate') }}">
        @csrf
        <x-ck-button type="submit" variant="primary">Migrationen ausführen</x-ck-button>
    </form>
</div>

{{-- Stat-Kacheln --}}
<div class="ck-stat-grid" style="margin-bottom: var(--ck-space-6);">

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">ClubKit</div>
        <div class="ck-stat-card__value" style="font-size:20px;">{{ $info['app_version'] ?? '–' }}</div>
        <div class="ck-stat-card__sub">
            Installiert: {{ $info['installed_at'] ?? '–' }}
        </div>
    </div>

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">Laravel</div>
        <div class="ck-stat-card__value" style="font-size:20px;">{{ $info['laravel_version'] ?? '–' }}</div>
        <div class="ck-stat-card__sub">
            Umgebung: {{ $info['env'] ?? '–' }}
            @if($info['debug'] ?? false)
                <x-ck-badge color="amber" style="margin-left:4px;">DEBUG AN</x-ck-badge>
            @endif
        </div>
    </div>

    <div class="ck-stat-card">
        <div class="ck-stat-card__label">PHP</div>
        <div class="ck-stat-card__value" style="font-size:20px;">{{ $info['php_version'] ?? '–' }}</div>
        <div class="ck-stat-card__sub">SAPI: {{ $info['php_sapi'] ?? '–' }}</div>
    </div>

    <div class="ck-stat-card {{ ($info['db_status'] ?? '') === 'Aktuell' ? 'ck-stat-card--ok' : 'ck-stat-card--danger' }}">
        <div class="ck-stat-card__label">Datenbank</div>
        <div class="ck-stat-card__value" style="font-size:20px;">{{ $info['db_status'] ?? '–' }}</div>
        <div class="ck-stat-card__sub">{{ $info['db_name'] ?? '–' }}</div>
    </div>

</div>

{{-- Details --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--ck-space-4);">

    <x-ck-card>
        <x-slot:header>🔧 Konfiguration</x-slot:header>
        <table class="ck-table" style="margin:-1px;">
            <tbody>
                <tr>
                    <td class="ck-text-muted" style="width:40%;">App-URL</td>
                    <td>
                        <a href="{{ $info['app_url'] ?? '#' }}" target="_blank"
                           style="color:var(--ck-accent-dark); text-decoration:none;">
                            {{ $info['app_url'] ?? '–' }}
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Umgebung</td>
                    <td>{{ $info['env'] ?? '–' }}</td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Debug-Modus</td>
                    <td>
                        @if($info['debug'] ?? false)
                            <x-ck-badge color="amber">Aktiv</x-ck-badge>
                        @else
                            <x-ck-badge color="green">Inaktiv</x-ck-badge>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Cache</td>
                    <td>{{ $info['cache_driver'] ?? '–' }}</td>
                </tr>
                <tr>
                    <td class="ck-text-muted">Session</td>
                    <td>{{ $info['session_driver'] ?? '–' }}</td>
                </tr>
            </tbody>
        </table>
    </x-ck-card>

    <x-ck-card>
        <x-slot:header>🧩 Installierte Module</x-slot:header>
        @if(empty($installedModules))
        <p class="ck-text-muted">Keine Module installiert.</p>
        @else
        <table class="ck-table" style="margin:-1px;">
            <thead>
                <tr>
                    <th>Modul</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($installedModules as $module)
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
