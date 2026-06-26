@extends('core::admin.layout')
@section('title', 'Dashboard')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Willkommen, {{ auth()->user()->name }}</h1>
        <p class="ck-page-subtitle">Systemübersicht – {{ now()->format('d. F Y') }}</p>
    </div>
</div>

{{-- ── Kennzahlen ─────────────────────────────────────────── --}}
@if(!empty($stats))
<div class="ck-stat-grid ck-mb-5">
    @foreach($stats as $stat)
    <a href="{{ $stat['link'] }}" class="ck-stat-card ck-stat-card--{{ $stat['color'] ?: 'default' }} ck-stat-card--link">
        <div class="ck-stat-card__label">{{ $stat['icon'] }} {{ $stat['label'] }}</div>
        <div class="ck-stat-card__value">{{ $stat['value'] }}</div>
    </a>
    @endforeach
    {{-- Fix 7: Extension Point – andere Module können hier Kacheln hinzufügen --}}
    @ckHook('dashboard.stats')
</div>
@endif

{{-- ── Installierte Module ─────────────────────────────────── --}}
<div class="ck-col-grid ck-col-grid--2">

    <x-ck-card>
        <x-slot:header>🧩 Installierte Module</x-slot:header>
        @if(empty($modules))
        <div class="ck-empty-state">Keine Module installiert.</div>
        @else
        <div class="ck-settings-section">
            @foreach($modules as $mod)
            <div class="ck-settings-row">
                {{-- Fix 1: stdClass-Objekte brauchen Pfeil-Notation, keine Array-Notation --}}
                <div class="ck-settings-row__label">{{ $mod->name ?? $mod->slug }}</div>
                <div class="ck-settings-row__input">
                    <x-ck-badge color="green">v{{ $mod->version ?? '–' }}</x-ck-badge>
                </div>
            </div>
            @endforeach
        </div>
        @endif
        <x-slot:footer>
            <a href="{{ route('admin.modules.index') }}" class="ck-link">Module verwalten →</a>
        </x-slot:footer>
    </x-ck-card>

    <x-ck-card>
        <x-slot:header>⚡ Schnellaktionen</x-slot:header>
        <div class="ck-quick-actions">
            @if(Schema::hasTable('members'))
            <a href="{{ route('members.index') }}" class="ck-quick-action">
                <span class="ck-quick-action__icon">🧑‍🤝‍🧑</span>
                <span>Mitglieder</span>
            </a>
            @endif
            @if(Schema::hasTable('teams'))
            <a href="{{ route('teams.index') }}" class="ck-quick-action">
                <span class="ck-quick-action__icon">⚽</span>
                <span>Teams</span>
            </a>
            @endif
            <a href="{{ route('admin.users.index') }}" class="ck-quick-action">
                <span class="ck-quick-action__icon">👤</span>
                <span>Nutzer</span>
            </a>
            <a href="{{ route('admin.appearance.index') }}" class="ck-quick-action">
                <span class="ck-quick-action__icon">🎨</span>
                <span>Design</span>
            </a>
        </div>
        {{-- Extension Point – andere Module können hier Schnellaktionen ergänzen --}}
        @ckHook('dashboard.quick-actions')
    </x-ck-card>

</div>

@endsection
