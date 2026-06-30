@extends('core::admin.layout')

@section('title', 'Module')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Module</h1>
        <p class="ck-page-subtitle">Installierte und verfügbare Module verwalten.</p>
    </div>
</div>

{{-- Installierte Module --}}
<div>
    <div class="ck-section-label ck-mb-3">
        Installiert ({{ count($installed) }})
    </div>

    @forelse($installed as $slug => $module)
    <div class="ck-module-card {{ $module->is_active ? 'ck-module-card--active' : 'ck-module-card--inactive' }}">
        <div class="ck-module-card__header">
            <div class="ck-module-card__info">
                <div class="ck-module-card__title-row">
                    <span class="ck-module-card__name">{{ $module->name }}</span>
                    <span class="ck-module-card__version">v{{ $module->version }}</span>
                    @if($module->is_active)
                        <x-ck-badge color="green">Aktiv</x-ck-badge>
                    @else
                        <x-ck-badge color="gray">Inaktiv</x-ck-badge>
                    @endif
                    @if($slug === 'core')
                        <x-ck-badge color="blue">Pflicht</x-ck-badge>
                    @endif
                </div>
                <div class="ck-module-card__meta">
                    Installiert: {{ \Carbon\Carbon::parse($module->installed_at)->format('d.m.Y H:i') }}
                </div>
            </div>

            @if($slug !== 'core')
            <div class="ck-module-card__actions">
                @if($module->is_active)
                <form method="POST" action="{{ route('admin.modules.deactivate', $slug) }}">
                    @csrf
                    <x-ck-button type="submit" variant="warning" size="sm">Deaktivieren</x-ck-button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.modules.activate', $slug) }}">
                    @csrf
                    <x-ck-button type="submit" variant="secondary" size="sm">Aktivieren</x-ck-button>
                </form>
                @endif

                {{-- Öffnet das Bestätigungs-Modal statt browser confirm() --}}
                <x-ck-button type="button" variant="danger" size="sm"
                    onclick="ckModuleRemoveOpen('{{ $slug }}', '{{ $module->name }}', '{{ route('admin.modules.remove', $slug) }}')">
                    Entfernen
                </x-ck-button>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="ck-module-card__empty">
        Keine Module installiert.
    </div>
    @endforelse
</div>

{{-- Verfügbare (noch nicht installierte) Module --}}
@php
    $notInstalled = array_filter($available, fn($slug) => !isset($installed[$slug]), ARRAY_FILTER_USE_KEY);
@endphp

@if(!empty($notInstalled))
<div class="ck-mt-5">
    <div class="ck-section-label ck-mb-3">
        Verfügbar – nicht installiert ({{ count($notInstalled) }})
    </div>

    @foreach($notInstalled as $slug => $config)
    <div class="ck-module-card ck-module-card--available">
        <div class="ck-module-card__header">
            <div class="ck-module-card__info">
                <div class="ck-module-card__title-row">
                    <span class="ck-module-card__name">{{ $config['name'] }}</span>
                    <span class="ck-module-card__version">v{{ $config['version'] ?? '1.0.0' }}</span>
                </div>
                @if(!empty($config['description']))
                <div class="ck-module-card__meta">{{ $config['description'] }}</div>
                @endif
                @if(!empty(array_filter($config['requires'] ?? [], fn($r) => $r !== 'core')))
                <div class="ck-module-card__requires">
                    Benötigt: {{ implode(', ', array_filter($config['requires'], fn($r) => $r !== 'core')) }}
                </div>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.modules.install', $slug) }}" class="ck-module-card__actions">
                @csrf
                <x-ck-button type="submit" variant="primary" size="sm">+ Installieren</x-ck-button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Keine module.json gefunden --}}
@if(empty($available))
<div class="ck-alert ck-alert--warning">
    ⚠️ Keine Module in <code>modules/</code> gefunden. Prüfe ob die Modul-Ordner mit <code>module.json</code> vorhanden sind.
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     SHARED DELETE FORM
     Action wird dynamisch durch ckModuleRemoveOpen() gesetzt.
══════════════════════════════════════════════════════════════ --}}
<form id="ck-module-remove-form" method="POST" action="">
    @csrf
    @method('DELETE')
</form>

{{-- ══════════════════════════════════════════════════════════════
     MODULE REMOVE CONFIRM MODAL
══════════════════════════════════════════════════════════════ --}}
<div id="ck-module-remove-modal" class="ck-modal-overlay" onclick="ckModalClose(event, 'ck-module-remove-modal')">
    <div class="ck-modal ck-modal--sm">
        <div class="ck-modal__header">
            <h2 class="ck-modal__title">🗑 Modul entfernen</h2>
        </div>

        <div class="ck-modal__body">
            <p class="ck-module-remove__question">
                Modul <strong id="ck-module-remove-name"></strong> wirklich entfernen?
            </p>
            <div class="ck-alert ck-alert--danger">
                ⚠️ <strong>Achtung:</strong> Alle Tabellen und Daten dieses Moduls werden
                <strong>unwiderruflich gelöscht</strong>. Dieser Vorgang kann nicht rückgängig
                gemacht werden.
            </div>
        </div>

        <div class="ck-modal__footer">
            <x-ck-button variant="secondary" onclick="ckModalClose(null, 'ck-module-remove-modal')">
                Abbrechen
            </x-ck-button>
            <x-ck-button variant="danger" onclick="ckModuleRemoveConfirm()">
                Endgültig entfernen
            </x-ck-button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    /**
     * Opens the module remove confirmation modal.
     * Sets the module name in the modal text and the DELETE route on the shared form.
     *
     * @param {string} slug   - Module slug
     * @param {string} name   - Human-readable module name for the modal text
     * @param {string} action - DELETE route to set on the form
     */
    window.ckModuleRemoveOpen = function (slug, name, action) {
        document.getElementById('ck-module-remove-name').textContent = name;
        document.getElementById('ck-module-remove-form').action = action;
        ckModalOpen('ck-module-remove-modal');
    };

    /**
     * Submits the shared delete form and closes the modal.
     * The loading overlay is shown automatically by app.js' form-submit listener.
     */
    window.ckModuleRemoveConfirm = function () {
        ckModalClose(null, 'ck-module-remove-modal');
        document.getElementById('ck-module-remove-form').submit();
    };

}());
</script>
@endpush

@endsection
